<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PenyesuaianStokManualTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        return User::factory()->create(['id_role' => $role->id_role]);
    }

    /** Pengaju non-Administrator (Administrator otomatis disetujui). */
    private function petugasGudang(): User
    {
        $role = Role::factory()->create(['nama_role' => 'Gudang']);
        DB::table('menus')->insertOrIgnore(['id_menu' => 1, 'nama_menu' => 'Bahan Baku', 'url_link' => 'bahan-baku', 'parent_id' => 0]);
        DB::table('role_permissions')->insert(['id_role' => $role->id_role, 'id_menu' => 1, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 0]);

        return User::factory()->create(['id_role' => $role->id_role]);
    }

    private function buatBahan(float $stok = 100): int
    {
        $idMesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'Mesin ' . uniqid(), 'tipe_mesin' => 'Digital']);
        $idKategori = DB::table('kategori')->insertGetId(['nama_kategori' => 'Kategori ' . uniqid()]);

        return DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'BHN-' . uniqid(), 'nama_bahan' => 'Flexi Korea', 'stok' => $stok,
            'id_mesin' => $idMesin, 'id_kategori' => $idKategori, 'satuan_stok' => 'm2',
            'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function ajukanKeluar(User $admin, int $idBahan, float $qty): void
    {
        $this->actingAs($this->petugasGudang())->post('/kartu-stok/adjustment', [
            'id_bahan' => $idBahan, 'tipe' => 'OUT', 'qty' => $qty, 'keterangan' => 'Rusak',
        ]);
    }

    public function test_pengajuan_tidak_langsung_mengubah_stok(): void
    {
        $admin = $this->admin();
        $idBahan = $this->buatBahan(100);

        $this->ajukanKeluar($admin, $idBahan, 5);

        $this->assertEquals(100, DB::table('bahan_baku')->where('id_bahan', $idBahan)->value('stok'));
        $this->assertDatabaseCount('kartu_stok', 0);
        $this->assertDatabaseHas('approval_requests', [
            'tipe_aksi' => 'penyesuaian_stok_manual', 'referensi_id' => $idBahan, 'status' => 'Menunggu',
        ]);
    }

    public function test_setujui_keluar_mengurangi_stok_dan_mencatat_kartu_stok(): void
    {
        $admin = $this->admin();
        $idBahan = $this->buatBahan(100);
        $this->ajukanKeluar($admin, $idBahan, 5);

        $req = ApprovalRequest::where('tipe_aksi', 'penyesuaian_stok_manual')->first();
        $this->actingAs($admin)->patch("/approval/{$req->id}/approve", ['catatan' => 'OK']);

        $this->assertEquals(95, DB::table('bahan_baku')->where('id_bahan', $idBahan)->value('stok'));
        $this->assertDatabaseHas('kartu_stok', [
            'id_bahan' => $idBahan, 'tipe' => 'OUT', 'stok_sebelum' => 100, 'stok_sesudah' => 95,
        ]);
        $this->assertEquals('Disetujui', $req->fresh()->status);
    }

    public function test_setujui_revisi_menetapkan_stok_baru(): void
    {
        $admin = $this->admin();
        $idBahan = $this->buatBahan(100);
        $this->actingAs($this->petugasGudang())->post('/kartu-stok/adjustment', [
            'id_bahan' => $idBahan, 'tipe' => 'ADJUSTMENT', 'stok_baru' => 90, 'keterangan' => 'Hitung fisik',
        ]);

        $req = ApprovalRequest::where('tipe_aksi', 'penyesuaian_stok_manual')->first();
        $this->actingAs($admin)->patch("/approval/{$req->id}/approve");

        $this->assertEquals(90, DB::table('bahan_baku')->where('id_bahan', $idBahan)->value('stok'));
        $this->assertDatabaseHas('kartu_stok', ['id_bahan' => $idBahan, 'tipe' => 'ADJUSTMENT', 'qty' => 10]);
    }

    public function test_tolak_pengajuan_tidak_mengubah_stok(): void
    {
        $admin = $this->admin();
        $idBahan = $this->buatBahan(100);
        $this->ajukanKeluar($admin, $idBahan, 5);

        $req = ApprovalRequest::where('tipe_aksi', 'penyesuaian_stok_manual')->first();
        $this->actingAs($admin)->patch("/approval/{$req->id}/reject", ['catatan' => 'Tidak valid']);

        $this->assertEquals(100, DB::table('bahan_baku')->where('id_bahan', $idBahan)->value('stok'));
        $this->assertDatabaseCount('kartu_stok', 0);
        $this->assertEquals('Ditolak', $req->fresh()->status);
    }

    public function test_pengajuan_keluar_melebihi_stok_ditolak_sejak_awal(): void
    {
        $admin = $this->admin();
        $idBahan = $this->buatBahan(10);

        $this->ajukanKeluar($admin, $idBahan, 50);

        $this->assertDatabaseCount('approval_requests', 0);
    }

    public function test_persetujuan_gagal_kalau_stok_sudah_tidak_cukup(): void
    {
        $admin = $this->admin();
        $idBahan = $this->buatBahan(100);
        $this->ajukanKeluar($admin, $idBahan, 80);

        // Stok terpakai lain selama menunggu approval.
        DB::table('bahan_baku')->where('id_bahan', $idBahan)->update(['stok' => 20]);

        $req = ApprovalRequest::where('tipe_aksi', 'penyesuaian_stok_manual')->first();
        $response = $this->actingAs($admin)->patch("/approval/{$req->id}/approve");

        $response->assertSessionHas('error');
        $this->assertEquals(20, DB::table('bahan_baku')->where('id_bahan', $idBahan)->value('stok'));
        $this->assertEquals('Menunggu', $req->fresh()->status);
    }

    public function test_penyesuaian_oleh_administrator_langsung_berlaku_dan_tetap_tercatat(): void
    {
        $admin = $this->admin();
        $idBahan = $this->buatBahan(100);

        $this->actingAs($admin)->post('/kartu-stok/adjustment', [
            'id_bahan' => $idBahan, 'tipe' => 'OUT', 'qty' => 5, 'keterangan' => 'Rusak',
        ])->assertSessionHas('success');

        $this->assertEquals(95, DB::table('bahan_baku')->where('id_bahan', $idBahan)->value('stok'));
        $this->assertEquals(1, DB::table('kartu_stok')->where('id_bahan', $idBahan)->where('tipe', 'OUT')->count());
        $this->assertDatabaseHas('approval_requests', ['tipe_aksi' => 'penyesuaian_stok_manual', 'referensi_id' => $idBahan, 'status' => 'Disetujui']);

        // Revisi stok (hasil opname) juga langsung berlaku.
        $this->post('/kartu-stok/adjustment', ['id_bahan' => $idBahan, 'tipe' => 'ADJUSTMENT', 'stok_baru' => 80, 'keterangan' => 'Opname'])->assertSessionHas('success');
        $this->assertEquals(80, DB::table('bahan_baku')->where('id_bahan', $idBahan)->value('stok'));
    }

    public function test_optimistic_locking_menolak_penyesuaian_bila_stok_sudah_berubah_sejak_formulir_dibuka(): void
    {
        $admin = $this->admin();
        $idBahan = $this->buatBahan(100);
        $this->actingAs($admin);

        // Formulir dibuka saat stok 100, lalu pengguna lain mengubah stok menjadi 90.
        DB::table('bahan_baku')->where('id_bahan', $idBahan)->update(['stok' => 90]);
        $this->post('/kartu-stok/adjustment', ['id_bahan' => $idBahan, 'tipe' => 'OUT', 'qty' => 5, 'keterangan' => 'Rusak', 'stok_dibuka' => 100])->assertSessionHas('error');
        $this->assertEquals(90, DB::table('bahan_baku')->where('id_bahan', $idBahan)->value('stok'));
        $this->assertDatabaseCount('approval_requests', 0);

        // Dengan stok terbaru diketahui, penyesuaian berjalan normal.
        $this->post('/kartu-stok/adjustment', ['id_bahan' => $idBahan, 'tipe' => 'OUT', 'qty' => 5, 'keterangan' => 'Rusak', 'stok_dibuka' => 90])->assertSessionHas('success');
        $this->assertEquals(85, DB::table('bahan_baku')->where('id_bahan', $idBahan)->value('stok'));
    }

    public function test_qty_bukan_angka_ditolak_server(): void
    {
        $admin = $this->admin();
        $idBahan = $this->buatBahan(100);

        $this->actingAs($admin)->post('/kartu-stok/adjustment', ['id_bahan' => $idBahan, 'tipe' => 'OUT', 'qty' => 'abc', 'keterangan' => 'x'])->assertSessionHasErrors('qty');
        $this->assertEquals(100, DB::table('bahan_baku')->where('id_bahan', $idBahan)->value('stok'));
    }

    public function test_administrator_dengan_administrator_lain_pengajuannya_menunggu_bukan_ditolak(): void
    {
        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $adminA = User::factory()->create(['id_role' => $role->id_role]);
        $adminB = User::factory()->create(['id_role' => $role->id_role]);
        $idBahan = $this->buatBahan(100);

        $this->actingAs($adminA)->post('/kartu-stok/adjustment', ['id_bahan' => $idBahan, 'tipe' => 'OUT', 'qty' => 5, 'keterangan' => 'Rusak'])
            ->assertSessionHas('success');

        // Ada Administrator lain: pengajuan menunggu dia (pemisahan tugas), tidak langsung ditolak.
        $req = ApprovalRequest::where('tipe_aksi', 'penyesuaian_stok_manual')->firstOrFail();
        $this->assertSame('Menunggu', $req->status);
        $this->assertEquals(100, (float) DB::table('bahan_baku')->where('id_bahan', $idBahan)->value('stok'));

        $this->actingAs($adminB)->patch("/approval/{$req->id}/approve", ['catatan' => 'OK'])->assertSessionHas('success');
        $this->assertEquals(95, (float) DB::table('bahan_baku')->where('id_bahan', $idBahan)->value('stok'));
    }
}
