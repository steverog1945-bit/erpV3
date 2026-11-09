<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\KasKecil;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KasKecilApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function kasKecil(User $admin): KasKecil
    {
        $idKategori = DB::table('kategori_kas_kecil')->insertGetId(['nama_kategori' => 'ATK', 'kode_akun' => '5100']);
        $k = KasKecil::create([
            'tanggal' => now()->toDateString(), 'id_kategori' => $idKategori, 'keterangan' => 'Beli kertas',
            'nominal' => 50000, 'id_user' => $admin->id_user,
        ]);
        AccountingService::postKasKecil('KK-' . $k->id, '5100', 50000);

        return $k;
    }

    public function test_hapus_kas_kecil_hanya_mengajukan_lalu_disetujui_membalik_jurnal(): void
    {
        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $admin = User::factory()->create(['id_role' => $role->id_role]);
        $this->actingAs($admin);
        $k = $this->kasKecil($admin);

        $this->delete("/kas-kecil/{$k->id}", ['alasan' => 'Salah catat'])->assertSessionHas('success');
        $this->assertNotNull(KasKecil::find($k->id), 'tidak boleh langsung terhapus');
        $this->assertEquals(50000, (float) DB::table('jurnal_umum')->where('kode_akun', '5100')->sum(DB::raw('debet - kredit')));

        $req = ApprovalRequest::where('tipe_aksi', 'batalkan_kas_kecil')->firstOrFail();
        $this->patch("/approval/{$req->id}/approve", ['catatan' => 'OK']);

        $this->assertNull(KasKecil::find($k->id));
        $this->assertEquals(0.0, (float) DB::table('jurnal_umum')->where('kode_akun', '5100')->sum(DB::raw('debet - kredit')) + 0);
    }

    public function test_pengajuan_ganda_untuk_catatan_yang_sama_ditolak(): void
    {
        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $admin = User::factory()->create(['id_role' => $role->id_role]);
        $this->actingAs($admin);
        $k = $this->kasKecil($admin);

        $this->delete("/kas-kecil/{$k->id}");
        $this->delete("/kas-kecil/{$k->id}")->assertSessionHas('error');

        $this->assertEquals(1, ApprovalRequest::where('tipe_aksi', 'batalkan_kas_kecil')->count());
    }
}
