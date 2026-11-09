<?php

namespace Tests\Feature;

use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuotationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idProduk;
    private int $idJasa;
    private int $idFinishing;
    private int $idGolongan;
    private int $idKonsumen;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $this->admin = User::factory()->create(['id_role' => $role->id_role]);
        $this->actingAs($this->admin);

        $this->idGolongan = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $this->idKonsumen = DB::table('konsumen')->insertGetId([
            'nama_konsumen' => 'Pak Budi', 'telepon' => '0812-3456-7890', 'alamat' => '-', 'id_golongan' => $this->idGolongan,
        ]);
        $this->idProduk = DB::table('produk')->insertGetId(['nama_produk' => 'Banner Flexi', 'metode_hitung' => 'ukuran']);
        DB::table('setting_harga')->insert(['id_produk' => $this->idProduk, 'id_golongan' => $this->idGolongan, 'harga_jual' => 30000]);
        $this->idJasa = DB::table('jasa')->insertGetId(['nama_jasa' => 'Desain Logo']);
        DB::table('jasa_harga')->insert(['id_jasa' => $this->idJasa, 'harga' => 75000, 'range_min' => 0]);
        $this->idFinishing = DB::table('finishing')->insertGetId(['nama_finishing' => 'Mata Ayam', 'harga_default' => 5000]);
    }

    private function data(array $override = []): array
    {
        return array_merge([
            'tanggal' => now()->toDateString(), 'berlaku_sampai' => now()->addDays(7)->toDateString(),
            'id_konsumen' => $this->idKonsumen, 'nama_pelanggan' => '', 'diskon' => 0,
            // Banner 300 x 500 cm = 15 m2 x Rp 30.000 = 450.000 (+ finishing 5.000)
            'items' => [['tipe' => 'Produk', 'id_produk' => $this->idProduk, 'p' => 300, 'l' => 500, 'qty' => 1, 'id_finishing' => $this->idFinishing]],
        ], $override);
    }

    public function test_harga_dihitung_otomatis_dari_price_list_seperti_input_order(): void
    {
        $q = QuotationService::simpan($this->data());

        $item = $q->items->first();
        $this->assertEquals(15.0, (float) $item->qty_hitung);
        $this->assertEquals(30000, (float) $item->harga_satuan);
        $this->assertEquals(5000, (float) $item->biaya_finishing);
        $this->assertEquals(455000, (float) $item->subtotal);
        $this->assertEquals(455000, (float) $q->total);
        $this->assertEquals('Pak Budi', $q->nama_pelanggan);
        $this->assertEquals('Draft', $q->status);
        $this->assertStringStartsWith('QT-', $q->nomor);
    }

    public function test_harga_manual_menimpa_otomatis_dan_jasa_memakai_harga_jasa(): void
    {
        $q = QuotationService::simpan($this->data([
            'diskon' => 10000,
            'items' => [
                ['tipe' => 'Produk', 'id_produk' => $this->idProduk, 'p' => 100, 'l' => 100, 'qty' => 2, 'harga_satuan' => 40000, 'biaya_desain' => 20000],
                ['tipe' => 'Jasa', 'id_jasa' => $this->idJasa, 'qty' => 2],
            ],
        ]));

        // Produk: 1 m2 x 2 = 2 x 40.000 + desain 20.000 = 100.000; Jasa: 2 x 75.000 = 150.000
        $this->assertEquals([100000.0, 150000.0], $q->items->pluck('subtotal')->map(fn ($v) => (float) $v)->all());
        $this->assertEquals(250000, (float) $q->subtotal);
        $this->assertEquals(240000, (float) $q->total);
    }

    public function test_harga_belum_diatur_dan_diskon_berlebih_ditolak(): void
    {
        $produkTanpaHarga = DB::table('produk')->insertGetId(['nama_produk' => 'Tanpa Harga', 'metode_hitung' => 'quantity']);

        try {
            QuotationService::simpan($this->data(['items' => [['tipe' => 'Produk', 'id_produk' => $produkTanpaHarga, 'qty' => 1]]]));
            $this->fail('Seharusnya ditolak');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('belum diatur', $e->getMessage());
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Diskon');
        QuotationService::simpan($this->data(['diskon' => 999999999]));
    }

    public function test_alur_status_kirim_tanggapi_dan_hanya_draft_yang_bisa_diubah(): void
    {
        $this->post('/quotation', $this->data());
        $q = Quotation::firstOrFail();

        $this->patch("/quotation/{$q->id}/tanggapi", ['keputusan' => 'Disetujui'])->assertSessionHas('error');
        $this->patch("/quotation/{$q->id}/kirim")->assertSessionHas('success');
        $this->assertEquals('Terkirim', $q->fresh()->status);

        $this->patch("/quotation/{$q->id}", $this->data())->assertSessionHas('error');
        $this->delete("/quotation/{$q->id}")->assertSessionHas('error');

        $this->patch("/quotation/{$q->id}/tanggapi", ['keputusan' => 'Disetujui'])->assertSessionHas('success');
        $this->assertEquals('Disetujui', $q->fresh()->status);
    }

    public function test_penawaran_kedaluwarsa_tidak_bisa_disetujui_atau_dijadikan_order(): void
    {
        $q = QuotationService::simpan($this->data(['tanggal' => now()->subDays(10)->toDateString(), 'berlaku_sampai' => now()->subDay()->toDateString()]));
        $q->update(['status' => 'Terkirim']);

        $this->assertEquals('Kedaluwarsa', $q->fresh()->status_efektif);
        $this->patch("/quotation/{$q->id}/tanggapi", ['keputusan' => 'Disetujui'])->assertSessionHas('error');
        $this->assertFalse(QuotationService::tandaiMenjadiOrder($q->id, 1));
    }

    public function test_order_dari_penawaran_menandai_penawaran_menjadi_order(): void
    {
        $q = QuotationService::simpan($this->data());
        QuotationService::kirim($q);

        $idTransaksi = DB::table('transaksi')->insertGetId([
            'nomor_nota' => 'INV-QT-1', 'tipe_order' => 'Reguler', 'id_konsumen' => $this->idKonsumen, 'id_user_kasir' => $this->admin->id_user,
            'tgl_order' => now(), 'total_bayar' => 455000, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_collecting' => 'Belum Diambil',
        ]);

        $this->assertTrue(QuotationService::tandaiMenjadiOrder($q->id, $idTransaksi));
        $this->assertEquals(['Menjadi Order', $idTransaksi], [$q->fresh()->status, $q->fresh()->id_transaksi]);
        $this->assertFalse(QuotationService::tandaiMenjadiOrder($q->id, $idTransaksi), 'tidak bisa dikonversi dua kali');
    }

    public function test_input_order_menampilkan_rincian_penawaran_dan_mengirim_quotation_id(): void
    {
        $q = QuotationService::simpan($this->data());
        QuotationService::kirim($q);

        $this->get('/penjualan/kasir?quotation=' . $q->id)->assertOk()
            ->assertSee($q->nomor)->assertSee('Banner Flexi')->assertSee('quotation_id: ' . $q->id, false);

        $this->get('/penjualan/kasir')->assertOk()->assertDontSee($q->nomor)->assertSee('quotation_id: null', false);

        // Draft belum boleh dipakai sebagai acuan order.
        $draft = QuotationService::simpan($this->data());
        $this->get('/penjualan/kasir?quotation=' . $draft->id)->assertOk()->assertDontSee($draft->nomor);
    }

    public function test_halaman_daftar_form_cetak_dan_whatsapp(): void
    {
        $q = QuotationService::simpan($this->data());
        QuotationService::kirim($q);

        $this->get('/quotation')->assertOk()->assertSee($q->nomor)->assertSee('Terkirim');
        $this->get('/quotation/create')->assertOk()->assertSee('Banner Flexi');
        // Tombol "Buat Nota dari Penawaran" hanya muncul setelah pelanggan menyetujui.
        QuotationService::tanggapi($q->fresh(), 'Disetujui');
        $this->get("/quotation/{$q->id}/edit")->assertOk()->assertSee('wa.me/6281234567890', false)->assertSee('Buat Nota dari Penawaran');
        $this->get("/quotation/{$q->id}/cetak")->assertOk()->assertSee('PENAWARAN')->assertSee('455.000');
    }

    public function test_akses_quotation_untuk_kasir_tapi_tidak_untuk_gudang(): void
    {
        $roleKasir = Role::factory()->create(['nama_role' => 'Kasir']);
        $kasir = User::factory()->create(['id_role' => $roleKasir->id_role]);
        $roleGudang = Role::factory()->create(['nama_role' => 'Gudang']);
        $gudang = User::factory()->create(['id_role' => $roleGudang->id_role]);
        DB::table('menus')->insertOrIgnore(['id_menu' => 3, 'nama_menu' => 'Transaksi Baru', 'url_link' => 'penjualan', 'parent_id' => 0]);
        DB::table('role_permissions')->insert(['id_role' => $roleKasir->id_role, 'id_menu' => 3]);

        $this->actingAs($kasir)->get('/quotation')->assertOk();
        $this->actingAs($gudang)->get('/quotation')->assertForbidden();
    }
}
