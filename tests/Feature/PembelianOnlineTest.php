<?php

namespace Tests\Feature;

use App\Models\KedatanganBahan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PembelianOnlineTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
    }

    public function test_tokopedia_dan_shopee_tersedia_dan_platform_baru_bisa_dikelola_tanpa_kode(): void
    {
        $this->assertEqualsCanonicalizing(['Tokopedia', 'Shopee'], DB::table('platform_belanja')->pluck('nama')->all());

        $this->actingAs($this->admin)->post('/platform-belanja', ['nama' => 'Lazada'])->assertRedirect();
        $id = DB::table('platform_belanja')->where('nama', 'Lazada')->value('id');
        $this->assertNotNull($id);

        $this->actingAs($this->admin)->post('/platform-belanja', ['nama' => 'Lazada'])->assertSessionHasErrors('nama'); // unik

        $this->actingAs($this->admin)->patch("/platform-belanja/{$id}/toggle")->assertRedirect();
        $this->assertEquals(0, DB::table('platform_belanja')->where('id', $id)->value('aktif'));

        $this->actingAs($this->admin)->delete("/platform-belanja/{$id}")->assertRedirect();
        $this->assertNull(DB::table('platform_belanja')->where('id', $id)->value('id'));
    }

    public function test_platform_yang_sudah_dipakai_tidak_bisa_dihapus(): void
    {
        $shopee = DB::table('platform_belanja')->where('nama', 'Shopee')->value('id');
        $sup = DB::table('supplier')->insertGetId(['nama_supplier' => 'Toko X', 'alamat' => '-', 'kontak' => '0']);
        KedatanganBahan::create(['no_surat_jalan' => 'SPX-1', 'id_supplier' => $sup, 'tgl_datang' => now()->toDateString(), 'id_platform' => $shopee, 'no_pesanan_online' => '25100ABC', 'no_resi' => 'SPXID1', 'nama_toko_online' => 'Toko X']);

        $this->actingAs($this->admin)->delete("/platform-belanja/{$shopee}")->assertSessionHas('error');
        $this->assertNotNull(DB::table('platform_belanja')->where('id', $shopee)->value('id'));
    }

    public function test_kedatangan_menyimpan_info_pembelian_online_dan_form_menampilkan_pilihan_platform(): void
    {
        $tokped = DB::table('platform_belanja')->where('nama', 'Tokopedia')->value('id');
        $sup = DB::table('supplier')->insertGetId(['nama_supplier' => 'Toko Y', 'alamat' => '-', 'kontak' => '0']);
        $gudang = DB::table('gudang')->insertGetId(['nama_gudang' => 'Utama']);
        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M', 'tipe_mesin' => 'x']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K']);
        $bahan = DB::table('bahan_baku')->insertGetId(['kode_bahan' => 'B1', 'nama_bahan' => 'Kain', 'id_mesin' => $mesin, 'id_kategori' => $kat, 'stok' => 0, 'nilai_konversi' => 1, 'satuan_stok' => 'm', 'status' => 'aktif']);

        $this->actingAs($this->admin)->get('/kedatangan-bahan/create')->assertOk()->assertSee('Tokopedia')->assertSee('Shopee')->assertSee('Sumber Pembelian');

        $this->actingAs($this->admin)->post('/kedatangan-bahan', [
            'no_surat_jalan' => 'TP-777', 'id_supplier' => $sup, 'tgl_datang' => now()->toDateString(), 'id_platform' => $tokped,
            'nama_toko_online' => 'Toko Y Official', 'no_pesanan_online' => 'INV/2510/TP', 'no_resi' => 'JNE123',
            'id_gudang' => $gudang, 'id_bahan' => [$bahan], 'qty' => [5],
        ])->assertRedirect();

        $k = KedatanganBahan::where('no_surat_jalan', 'TP-777')->firstOrFail();
        $this->assertEquals($tokped, $k->id_platform);
        $this->assertEquals('JNE123', $k->no_resi);

        // Offline: info online dikosongkan walau terkirim.
        $this->actingAs($this->admin)->post('/kedatangan-bahan', [
            'no_surat_jalan' => 'OFF-1', 'id_supplier' => $sup, 'tgl_datang' => now()->toDateString(), 'no_resi' => 'ABAIKAN',
            'id_gudang' => $gudang, 'id_bahan' => [$bahan], 'qty' => [1],
        ])->assertRedirect();
        $this->assertNull(KedatanganBahan::where('no_surat_jalan', 'OFF-1')->value('no_resi'));
    }

    public function test_faktur_menambah_ongkir_biaya_layanan_dan_mengurangi_voucher(): void
    {
        $sup = DB::table('supplier')->insertGetId(['nama_supplier' => 'Toko Z', 'alamat' => '-', 'kontak' => '0']);
        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M', 'tipe_mesin' => 'x']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K']);
        $bahan = DB::table('bahan_baku')->insertGetId(['kode_bahan' => 'B2', 'nama_bahan' => 'Tinta', 'id_mesin' => $mesin, 'id_kategori' => $kat, 'stok' => 10, 'nilai_konversi' => 1, 'satuan_stok' => 'pcs', 'status' => 'aktif']);
        $k = KedatanganBahan::create(['no_surat_jalan' => 'SP-9', 'id_supplier' => $sup, 'tgl_datang' => now()->toDateString(), 'status' => 'Selesai']);
        // Kedatangan Selesai yang realistis selalu punya detail bahan (penjaga server-side memeriksanya).
        DB::table('kedatangan_bahan_detail')->insert(['id_kedatangan' => $k->id_kedatangan, 'id_bahan' => $bahan, 'qty' => 2, 'status' => 'OK']);

        $this->actingAs($this->admin)->post('/pembelian-bahan', [
            'id_kedatangan' => $k->id_kedatangan, 'no_faktur' => 'ONL-1', 'tgl_transaksi' => now()->toDateString(), 'tipe_pembelian' => 'Cash',
            'id_bahan' => [$bahan], 'qty' => [2], 'harga' => [100000], 'diskon' => [0], 'potongan' => [0],
            'ongkir' => 15000, 'biaya_layanan' => 2000, 'voucher' => 7000,
        ]);

        $p = DB::table('pembelian_bahan')->where('no_faktur', 'ONL-1')->first();
        $this->assertNotNull($p);
        // 2 x 100.000 + 15.000 + 2.000 - 7.000 = 210.000
        $this->assertEquals(210000, (float) $p->grand_total);
        $this->assertEquals(15000, (float) $p->ongkir);
        $this->assertEquals(7000, (float) $p->voucher);
    }

    public function test_tambah_supplier_cepat_dari_form_kedatangan(): void
    {
        $r = $this->actingAs($this->admin)->postJson('/supplier/store-ajax', ['nama_supplier' => 'PT Baru Jaya', 'kontak' => '0812', 'alamat' => 'Jl. A'])->assertOk()->assertJson(['status' => 'success']);
        $this->assertEquals('PT Baru Jaya', DB::table('supplier')->where('id_supplier', $r->json('data.id_supplier'))->value('nama_supplier'));

        // Nama sama (huruf besar/kecil berbeda) ditolak, tidak dobel.
        $this->actingAs($this->admin)->postJson('/supplier/store-ajax', ['nama_supplier' => 'pt baru jaya', 'kontak' => '0812'])->assertStatus(422);
        $this->assertEquals(1, DB::table('supplier')->where('nama_supplier', 'PT Baru Jaya')->count());

        $this->actingAs($this->admin)->get('/kedatangan-bahan/create')->assertOk()->assertSee('supplier-baru', false)->assertSee('SIMPAN SUPPLIER');
    }

    public function test_petugas_kedatangan_tanpa_menu_supplier_tetap_bisa_tambah_supplier_cepat(): void
    {
        $role = Role::factory()->create(['nama_role' => 'Gudang']);
        DB::table('menus')->insertOrIgnore(['id_menu' => 17, 'nama_menu' => 'Kedatangan Bahan', 'url_link' => 'kedatangan-bahan', 'parent_id' => 0]);
        DB::table('role_permissions')->insert(['id_role' => $role->id_role, 'id_menu' => 17]);
        $gudang = User::factory()->create(['id_role' => $role->id_role]);

        $this->actingAs($gudang)->postJson('/supplier/store-ajax', ['nama_supplier' => 'CV Gudang', 'kontak' => '081'])->assertOk();
        // Tapi tetap tidak boleh membuka master supplier lengkap.
        $this->actingAs($gudang)->get('/supplier')->assertForbidden();
    }
}
