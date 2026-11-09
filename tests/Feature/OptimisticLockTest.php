<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\VersiBaris;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OptimisticLockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
    }

    public function test_helper_versi_baris(): void
    {
        $baris = (object) ['updated_at' => '2026-09-25 10:00:00'];
        $this->assertSame('2026-09-25 10:00:00', VersiBaris::token($baris));
        $this->assertStringContainsString('_versi', (string) VersiBaris::field($baris));

        $sama = Request::create('/x', 'POST', ['_versi' => '2026-09-25 10:00:00']);
        $beda = Request::create('/x', 'POST', ['_versi' => '2026-09-25 09:00:00']);
        $tanpa = Request::create('/x', 'POST', []);
        $this->assertFalse(VersiBaris::bentrok($sama, $baris));
        $this->assertTrue(VersiBaris::bentrok($beda, $baris));
        $this->assertFalse(VersiBaris::bentrok($tanpa, $baris)); // form lama tanpa versi tetap jalan
    }

    public function test_tier_harga_ditolak_bila_sudah_diubah_orang_lain_dan_versi_baru_dikembalikan(): void
    {
        $g = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $p = DB::table('produk')->insertGetId(['nama_produk' => 'P', 'metode_hitung' => 'quantity', 'status' => 'Aktif']);
        $id = DB::table('setting_harga')->insertGetId(['id_produk' => $p, 'id_golongan' => $g, 'range_min' => 1, 'harga_jual' => 5000, 'created_at' => '2026-09-25 09:00:00', 'updated_at' => '2026-09-25 09:00:00']);

        // Admin B lebih dulu mengubah (updated_at berubah); admin A masih membawa versi lama.
        DB::table('setting_harga')->where('id_harga', $id)->update(['harga_jual' => 6000, 'updated_at' => '2026-09-25 09:30:00']);

        $this->actingAs($this->admin)->patchJson("/setting-harga-tier/{$id}", ['range_min' => 1, 'harga_jual' => 7000, '_versi' => '2026-09-25 09:00:00'])
            ->assertStatus(409)->assertJson(['success' => false]);
        $this->assertEquals(6000, (float) DB::table('setting_harga')->where('id_harga', $id)->value('harga_jual'));

        $res = $this->actingAs($this->admin)->patchJson("/setting-harga-tier/{$id}", ['range_min' => 1, 'harga_jual' => 7000, '_versi' => '2026-09-25 09:30:00'])->assertOk();
        $this->assertEquals(7000, (float) DB::table('setting_harga')->where('id_harga', $id)->value('harga_jual'));
        $this->assertNotEmpty($res->json('versi'));
    }

    public function test_koreksi_harga_bahan_ditolak_bila_versi_lama(): void
    {
        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M', 'tipe_mesin' => 'x']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K']);
        $id = DB::table('bahan_baku')->insertGetId(['kode_bahan' => 'B1', 'nama_bahan' => 'Bahan', 'id_mesin' => $mesin, 'id_kategori' => $kat, 'stok' => 1, 'nilai_konversi' => 1, 'harga_rata_rata' => 1000, 'satuan_stok' => 'pcs', 'status' => 'aktif', 'created_at' => '2026-09-25 09:00:00', 'updated_at' => '2026-09-25 09:30:00']);

        $this->actingAs($this->admin)->patch("/bahan-baku/{$id}/harga", ['harga_rata_rata' => 2000, 'alasan' => 'x', '_versi' => '2026-09-25 09:00:00'])->assertSessionHas('error');
        $this->assertEquals(1000, (float) DB::table('bahan_baku')->where('id_bahan', $id)->value('harga_rata_rata'));

        $this->actingAs($this->admin)->patch("/bahan-baku/{$id}/harga", ['harga_rata_rata' => 2000, 'alasan' => 'x', '_versi' => '2026-09-25 09:30:00'])->assertSessionHas('success');
        $this->assertEquals(2000, (float) DB::table('bahan_baku')->where('id_bahan', $id)->value('harga_rata_rata'));
    }

    public function test_hak_akses_role_ditolak_bila_admin_lain_sudah_mengubah(): void
    {
        DB::table('menus')->insertOrIgnore(['id_menu' => 7, 'nama_menu' => 'Data Konsumen', 'url_link' => 'konsumen', 'parent_id' => 0]);
        $role = Role::factory()->create(['nama_role' => 'Kasir']);

        $halaman = $this->actingAs($this->admin)->get('/setting-akses?role=' . $role->id_role)->assertOk();
        preg_match('/name="_versi" value="([a-f0-9]{32})"/', $halaman->getContent(), $m);
        $versiLama = $m[1];

        // Admin lain mengubah lebih dulu.
        $this->actingAs($this->admin)->post('/setting-akses/update', ['role_id' => $role->id_role, 'izin' => [7 => ['view' => 1]], '_versi' => $versiLama])->assertRedirect();
        $this->assertEquals(1, DB::table('role_permissions')->where('id_role', $role->id_role)->count());

        // Admin pertama menyimpan dengan versi lama -> ditolak, hak tidak tertimpa.
        $this->actingAs($this->admin)->post('/setting-akses/update', ['role_id' => $role->id_role, 'izin' => [], '_versi' => $versiLama])->assertSessionHas('error');
        $this->assertEquals(1, DB::table('role_permissions')->where('id_role', $role->id_role)->count());
    }
}
