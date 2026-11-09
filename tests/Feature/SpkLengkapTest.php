<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SpkLengkapTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idDetail;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);

        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'CENTURY UJI', 'tipe_mesin' => 'Direct Print']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K']);
        $m2 = DB::table('satuan')->insertGetId(['nama_satuan' => 'm2', 'nilai_konversi' => 1]);
        $bahan = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'TC1', 'nama_bahan' => 'TC Textile Uji 120cm', 'id_mesin' => $mesin, 'id_kategori' => $kat, 'stok' => 5,
            'nilai_konversi' => 1, 'lebar_roll' => 1.2, 'satuan_stok' => 'm2', 'id_satuan_hitung' => $m2, 'id_satuan_stok' => $m2, 'status' => 'aktif',
        ]);
        $produk = DB::table('produk')->insertGetId(['nama_produk' => 'TC Direct Uji', 'metode_hitung' => 'ukuran', 'status' => 'Aktif', 'id_mesin' => $mesin, 'spk_lengkap' => 1]);
        DB::table('bom_detail')->insert(['id_produk' => $produk, 'id_bahan' => $bahan, 'cara_hitung_pemakaian' => '(P*L)/10000', 'created_at' => now(), 'updated_at' => now()]);

        $g = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $k = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Konsumen Uji SPK', 'telepon' => '0811999', 'alamat' => '-', 'id_golongan' => $g]);
        $t = DB::table('transaksi')->insertGetId(['nomor_nota' => 'N-SPK-1', 'tipe_order' => 'Reguler', 'id_konsumen' => $k, 'id_user_kasir' => $this->admin->id_user, 'tgl_order' => now(), 'total_bayar' => 1, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_order' => 'Diterima Kasir']);
        $this->idDetail = DB::table('transaksi_detail')->insertGetId([
            'id_transaksi' => $t, 'id_produk' => $produk, 'nama_file' => 'banner-tc.jpg', 'thumbnail' => 'order_thumbnails/uji.jpg', 'p_ukuran' => 120, 'l_ukuran' => 200, 'qty' => 2,
            'subtotal' => 1, 'status_spk' => 'Antre', 'catatan_produksi' => 'Jahit keliling rapi',
        ]);
        $fin = DB::table('finishing')->insertGetId(['nama_finishing' => 'Jahit Keliling Uji', 'harga_default' => 1, 'metode_hitung' => 'keliling']);
        DB::table('transaksi_detail_finishing')->insert(['id_detail' => $this->idDetail, 'id_finishing' => $fin, 'status_finishing' => 'Standar', 'biaya' => 1, 'catatan' => 'Benang putih', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_spk_lengkap_memuat_gambar_konsumen_desainer_bahan_dan_finishing(): void
    {
        $this->actingAs($this->admin)->get("/spk/{$this->idDetail}/print/lengkap")->assertOk()
            ->assertSee('Konsumen Uji SPK')->assertSee('0811999')->assertSee('order_thumbnails/uji.jpg')
            ->assertSee('Desainer / Petugas Input')->assertSee('TC Textile Uji 120cm')
            ->assertSee('Jahit Keliling Uji')->assertSee('Benang putih')->assertSee('Jahit keliling rapi');
    }

    public function test_perkiraan_panjang_kain_dari_lebar_roll(): void
    {
        // 120 x 200 cm x 2 = 4,8 m2 ; lebar roll 1,2 m -> 4 m kain
        $this->actingAs($this->admin)->get("/spk/{$this->idDetail}/print/lengkap")->assertOk()->assertSee('4 m')->assertSee('4,8');
    }

    public function test_tim_produksi_tetap_thermal_dan_desainer_punya_tombol_spk_desain(): void
    {
        $html = $this->actingAs($this->admin)->get('/spk')->assertOk()->getContent();
        $this->assertStringContainsString("/spk/{$this->idDetail}/print/thermal", $html);      // thermal untuk produksi selalu ada
        $this->assertStringNotContainsString("/spk/{$this->idDetail}/print/lengkap", $html);
        $this->assertStringContainsString("/spk-desain/{$this->idDetail}", $html);              // tombol SPK Desain (admin/desainer)
    }

    public function test_operator_desain_bisa_cetak_spk_desain_tanpa_menu_spk_dan_operator_produksi_tidak_melihat_tombolnya(): void
    {
        $role = Role::factory()->create(['nama_role' => 'Operator Desain']);
        foreach ([41, 23] as $id) {
            DB::table('menus')->insertOrIgnore(['id_menu' => $id, 'nama_menu' => "M{$id}", 'url_link' => "m{$id}", 'parent_id' => 0]);
            DB::table('role_permissions')->insert(['id_role' => $role->id_role, 'id_menu' => $id]);
        }
        $desain = User::factory()->create(['id_role' => $role->id_role]);

        $this->actingAs($desain)->get("/spk-desain/{$this->idDetail}")->assertOk()->assertSee('Konsumen Uji SPK')->assertSee('Desainer / Petugas Input');
        $this->actingAs($desain)->get("/produksi?status_produksi=semua")->assertOk()->assertSee("/spk-desain/{$this->idDetail}", false);

        $rolePro = Role::factory()->create(['nama_role' => 'Operator Produksi']);
        DB::table('role_permissions')->insert(['id_role' => $rolePro->id_role, 'id_menu' => 23]);
        $produksi = User::factory()->create(['id_role' => $rolePro->id_role]);
        $this->actingAs($produksi)->get("/produksi?status_produksi=semua")->assertOk()->assertDontSee("/spk-desain/{$this->idDetail}", false)->assertSee("/spk/{$this->idDetail}/print/thermal", false);
    }

    public function test_produk_tanpa_tanda_tidak_punya_spk_desain(): void
    {
        DB::table('produk')->update(['spk_lengkap' => 0]);
        $this->actingAs($this->admin)->get("/spk-desain/{$this->idDetail}")->assertNotFound();
        $html = $this->actingAs($this->admin)->get('/spk')->assertOk()->getContent();
        $this->assertStringNotContainsString('/spk-desain/', $html);
    }
}
