<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\HargaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HargaSiapCetakTest extends TestCase
{
    use RefreshDatabase;

    private int $idProduk;
    private int $idKonsumen;
    private int $idGol;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        DB::table('roles')->where('nama_role', 'Administrator')->update(['boleh_lihat_harga' => true]);
        $this->idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $this->idKonsumen = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $this->idGol]);
        $this->idProduk = DB::table('produk')->insertGetId(['nama_produk' => 'Stiker Uji', 'metode_hitung' => 'ukuran', 'status' => 'Aktif']);
        DB::table('setting_harga')->insert(['id_produk' => $this->idProduk, 'id_golongan' => $this->idGol, 'range_min' => 0.01, 'harga_jual' => 50000]);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user, 'saldo_awal' => 0, 'status_lock' => 'open']);
    }

    private function aturHarga(): void
    {
        $this->actingAs($this->admin)->postJson("/harga-siap-cetak/{$this->idProduk}", ['tier' => [
            ['min' => 0, 'max' => 5, 'harga' => [$this->idGol => 30000]],
            ['min' => 5.01, 'max' => null, 'harga' => [$this->idGol => 25000]],
        ]])->assertOk();
    }

    private function nota(bool $siap, float $desain = 0, ?int $idDesainer = null)
    {
        return $this->actingAs($this->admin)->postJson('/penjualan/store', ['id_konsumen' => $this->idKonsumen, 'id_golongan' => $this->idGol, 'items' => [[
            'id_produk' => $this->idProduk, 'nama_file' => 'a.jpg', 'p' => 100, 'l' => 100, 'qty_input' => 1, 'biaya_cetak' => 30000,
            'biaya_desain' => $desain, 'id_desainer' => $idDesainer, 'siap_cetak' => $siap,
        ]]]);
    }

    public function test_harga_service_memakai_tier_siap_cetak_tanpa_promo_dan_terpisah_dari_price_list(): void
    {
        $this->aturHarga();

        $this->assertEquals(50000, HargaService::getHargaEfektif($this->idProduk, $this->idGol, 1)['harga_jual']);
        $siap = HargaService::getHargaEfektif($this->idProduk, $this->idGol, 1, true);
        $this->assertEquals(30000, $siap['harga_jual']);
        $this->assertTrue($siap['siap_cetak']);
        $this->assertFalse($siap['is_promo']);
        $this->assertEquals(25000, HargaService::getHargaEfektif($this->idProduk, $this->idGol, 10, true)['harga_jual']);
        $this->assertContains($this->idProduk, HargaService::produkDenganHargaSiapCetak());
    }

    public function test_simpan_dan_tampil_grid_harga_serta_tolak_rentang_tumpang_tindih(): void
    {
        $this->aturHarga();
        $d = $this->actingAs($this->admin)->getJson("/harga-siap-cetak/{$this->idProduk}")->assertOk()->json();
        $this->assertCount(2, $d['tier']);
        $this->assertEquals(30000, $d['tier'][0]['harga'][$this->idGol]);
        $this->assertNull($d['tier'][1]['max']);

        $this->postJson("/harga-siap-cetak/{$this->idProduk}", ['tier' => [
            ['min' => 0, 'max' => 5, 'harga' => [$this->idGol => 1000]],
            ['min' => 3, 'max' => 9, 'harga' => [$this->idGol => 900]],
        ]])->assertStatus(422);
        $this->assertEquals(2, DB::table('harga_siap_cetak')->where('id_produk', $this->idProduk)->count(), 'gagal validasi tidak boleh mengubah data');

        $this->postJson("/harga-siap-cetak/{$this->idProduk}", ['tier' => []])->assertOk();
        $this->assertEquals(0, DB::table('harga_siap_cetak')->count());
        $this->get('/harga-siap-cetak')->assertOk()->assertSee('Harga File Siap Cetak');
    }

    public function test_api_get_harga_siap_cetak(): void
    {
        $this->aturHarga();
        $this->actingAs($this->admin)->getJson("/api/penjualan/get-harga/{$this->idProduk}/{$this->idGol}/1?siap_cetak=1")->assertOk()->assertJsonPath('harga_jual', 30000);
        $this->getJson("/api/penjualan/get-harga/{$this->idProduk}/{$this->idGol}/1")->assertOk()->assertJsonPath('harga_jual', 50000);
    }

    public function test_order_siap_cetak_memakai_harga_sendiri_tanpa_desain_dan_komisi_dengan_penanda(): void
    {
        $this->aturHarga();
        $this->nota(true, 100000, $this->admin->id_user)->assertOk();

        $d = DB::table('transaksi_detail')->first();
        $this->assertEquals(1, $d->file_siap_cetak);
        $this->assertEquals(0, (float) $d->biaya_desain);
        $this->assertNull($d->id_desainer);
        $this->assertEquals(0, (float) $d->komisi_desain);
        $this->assertStringContainsString('FILE SIAP CETAK', (string) $d->catatan_produksi);
    }

    public function test_order_siap_cetak_ditolak_bila_harga_belum_diatur_dan_order_biasa_tidak_terpengaruh(): void
    {
        $this->nota(true)->assertStatus(422);
        $this->assertEquals(0, DB::table('transaksi_detail')->count());

        $this->nota(false, 20000)->assertOk();
        $d = DB::table('transaksi_detail')->first();
        $this->assertEquals(0, $d->file_siap_cetak);
        $this->assertEquals(20000, (float) $d->biaya_desain);
    }

    public function test_role_tanpa_hak_lihat_harga_ditolak_walau_menu_price_list_dicentang(): void
    {
        $r = Role::factory()->create(['nama_role' => 'Operator Desain']);
        DB::table('roles')->where('id_role', $r->id_role)->update(['boleh_lihat_harga' => false]);
        DB::table('menus')->insertOrIgnore(['id_menu' => 14, 'nama_menu' => 'Price List', 'url_link' => 'setting-harga', 'parent_id' => 0]);
        DB::table('role_permissions')->insert(['id_role' => $r->id_role, 'id_menu' => 14, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 1]);
        $u = User::factory()->create(['id_role' => $r->id_role]);

        $this->actingAs($u)->get('/harga-siap-cetak')->assertForbidden();
        $this->postJson("/harga-siap-cetak/{$this->idProduk}", ['tier' => []])->assertForbidden();
    }
}
