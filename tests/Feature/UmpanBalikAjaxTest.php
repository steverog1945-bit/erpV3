<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BEHAVIORAL AUDIT — Pattern B: mutasi AJAX (fetch() JSON).
 *
 * Pertanyaan audit (per endpoint):
 *   1. server menolak isian tidak valid?  -> 422 + JSON "message"
 *      (handler JS di view membaca res.ok / data.message lalu
 *      menampilkan Swal — dibuktikan terpisah di browser)
 *   2. tidak ada record baru / record lama
 *      tidak berubah?                       -> count & row dibandingkan
 *
 * Temuan kelas bug (dibuktikan di sini, akar masalahnya):
 *   Fetch WITHOUT header "Accept: application/json" membuat
 *   Laravel membalas validasi dengan 302 redirect (bukan 422
 *   JSON). Browser mengikuti redirect, response.json() gagal
 *   parse, handler catch menampilkan pesan GENERIK — alasan
 *   penolakan sesungguhnya tidak pernah terlihat operator.
 *   Endpoint terdampak: konsumen/store-ajax (penjualan/create,
 *   penjualan-barang/create), jersey upload-thumbnail,
 *   jersey keuangan upload-bukti, penjualan upload-thumbnail.
 *
 * Fixture: role Administrator. Database testing (RefreshDatabase).
 */
class UmpanBalikAjaxTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $idRole = (int) DB::table('roles')->insertGetId(['nama_role' => 'Administrator']);
        $this->admin = User::factory()->create(['id_role' => $idRole]);
    }

    /** Request JSON yang meminta balasan JSON (Accept: application/json). */
    private function jsonAccept(string $method, string $uri, array $payload = [])
    {
        return $this->actingAs($this->admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->$method($uri, $payload);
    }

    /** Request JSON TANPA Accept — meniru fetch() yang lupa header. */
    private function jsonTanpaAccept(string $method, string $uri, array $payload = [])
    {
        return $this->actingAs($this->admin)->$method($uri, $payload);
    }

    /* ==================================================================
     * A. TIER HARGA (fetch JSON: finishing-harga, jasa-harga,
     *    setting-harga, harga-siap-cetak)
     * ================================================================== */

    public function test_tier_finishing_ditolak_server(): void
    {
        $idFinishing = DB::table('finishing')->insertGetId([
            'nama_finishing' => 'Finishing Uji', 'harga_default' => 1000, 'metode_hitung' => 'quantity',
        ]);
        $sebelum = DB::table('finishing_harga')->count();

        $r = $this->jsonAccept('post', "/finishing/{$idFinishing}/harga", [
            'range_min' => '', 'harga' => '',
        ]);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['range_min']);
        $this->assertSame($sebelum, DB::table('finishing_harga')->count());
    }

    public function test_tier_finishing_ubah_ditolak_server(): void
    {
        $idFinishing = DB::table('finishing')->insertGetId([
            'nama_finishing' => 'Finishing Uji', 'harga_default' => 1000, 'metode_hitung' => 'quantity',
        ]);
        $idTier = DB::table('finishing_harga')->insertGetId([
            'id_finishing' => $idFinishing, 'range_min' => 1, 'range_max' => 10, 'harga' => 5000,
        ]);
        $sebelum = DB::table('finishing_harga')->where('id_finishing_harga', $idTier)->first();

        $r = $this->jsonAccept('patch', "/finishing-harga/{$idTier}", [
            'range_min' => '', 'harga' => '',
        ]);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['range_min']);
        $this->assertEquals($sebelum, DB::table('finishing_harga')->where('id_finishing_harga', $idTier)->first());
    }

    public function test_tier_jasa_ditolak_server(): void
    {
        $idJasa = DB::table('jasa')->insertGetId(['nama_jasa' => 'Jasa Uji']);
        $sebelum = DB::table('jasa_harga')->count();

        $r = $this->jsonAccept('post', "/jasa/{$idJasa}/harga", [
            'range_min' => '', 'harga' => '',
        ]);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['range_min']);
        $this->assertSame($sebelum, DB::table('jasa_harga')->count());
    }

    public function test_tier_jasa_ubah_ditolak_server(): void
    {
        $idJasa = DB::table('jasa')->insertGetId(['nama_jasa' => 'Jasa Uji']);
        $idTier = DB::table('jasa_harga')->insertGetId([
            'id_jasa' => $idJasa, 'range_min' => 1, 'range_max' => 10, 'harga' => 5000,
        ]);
        $sebelum = DB::table('jasa_harga')->where('id_jasa_harga', $idTier)->first();

        $r = $this->jsonAccept('patch', "/jasa-harga/{$idTier}", [
            'range_min' => '', 'harga' => '',
        ]);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['range_min']);
        $this->assertEquals($sebelum, DB::table('jasa_harga')->where('id_jasa_harga', $idTier)->first());
    }

    public function test_tier_setting_harga_ditolak_server(): void
    {
        $idProduk = $this->produkUji();
        $sebelum = DB::table('setting_harga')->count();

        $r = $this->jsonAccept('post', "/setting-harga/{$idProduk}/tier", [
            'id_golongan' => '', 'range_min' => '', 'harga_jual' => '',
        ]);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['id_golongan']);
        $this->assertSame($sebelum, DB::table('setting_harga')->count());
    }

    public function test_tier_setting_harga_ubah_ditolak_server(): void
    {
        $idProduk = $this->produkUji();
        $idGolongan = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Golongan Uji']);
        $idHarga = DB::table('setting_harga')->insertGetId([
            'id_produk' => $idProduk, 'id_golongan' => $idGolongan,
            'range_min' => 1, 'range_max' => 10, 'harga_jual' => 5000, 'qty_berlaku' => 1,
        ]);
        $sebelum = DB::table('setting_harga')->where('id_harga', $idHarga)->first();

        $r = $this->jsonAccept('patch', "/setting-harga-tier/{$idHarga}", [
            'range_min' => '', 'harga_jual' => '',
        ]);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['range_min']);
        $this->assertEquals($sebelum, DB::table('setting_harga')->where('id_harga', $idHarga)->first());
    }

    public function test_copy_golongan_ditolak_server(): void
    {
        $idProduk = $this->produkUji();
        $sebelum = DB::table('setting_harga')->count();

        $r = $this->jsonAccept('post', "/setting-harga/{$idProduk}/copy-golongan", [
            'id_golongan_sumber' => '',
        ]);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['id_golongan_sumber']);
        $this->assertSame($sebelum, DB::table('setting_harga')->count());
    }

    public function test_harga_siap_cetak_ditolak_server(): void
    {
        $idProduk = $this->produkUji();

        $r = $this->jsonAccept('post', "/harga-siap-cetak/{$idProduk}", [
            // tier terisi tapi baris pertama tidak punya "min"
            'tier' => [['max' => 10]],
        ]);

        $r->assertStatus(422);
        $this->assertNotNull($r->json('message'));
    }

    /* ==================================================================
     * B. MASTER AJAX (supplier & konsumen store-ajax)
     * ================================================================== */

    public function test_supplier_ajax_ditolak_dan_pesan_tersedia(): void
    {
        $sebelum = DB::table('supplier')->count();

        $r = $this->jsonAccept('post', '/supplier/store-ajax', [
            'nama_supplier' => '', 'kontak' => '',
        ]);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['nama_supplier']);
        // pesan penolakan tersedia untuk handler JS (Swal menampilkan res.message)
        $this->assertIsString($r->json('message'));
        $this->assertStringContainsString('Nama supplier wajib diisi.', $r->json('message'));
        $this->assertSame($sebelum, DB::table('supplier')->count());
    }

    public function test_konsumen_ajax_ditolak_dengan_accept(): void
    {
        $this->kasBuka();
        $sebelum = DB::table('konsumen')->count();

        $r = $this->jsonAccept('post', '/konsumen/store-ajax', [
            'nama_konsumen' => '', 'id_golongan' => '',
        ]);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['nama_konsumen']);
        $this->assertStringContainsString('Nama konsumen wajib diisi.', $r->json('message'));
        $this->assertSame($sebelum, DB::table('konsumen')->count());
    }

    /**
     * SUDAH DIPERBAIKI: fetch() di penjualan/create & penjualan-barang/create
     * sekarang mengirim header Accept: application/json, sehingga operator
     * melihat pesan validasi yang sebenarnya. Kontrak server tetap dijaga
     * tesnya di sini: klien yang TIDAK mengirim Accept mendapat 302 redirect.
     */
    public function test_konsumen_ajax_tanpa_accept_mendapat_redirect_bukan_json(): void
    {
        $this->kasBuka();

        $r = $this->jsonTanpaAccept('post', '/konsumen/store-ajax', [
            'nama_konsumen' => '', 'id_golongan' => '',
        ]);

        // bukan 422 JSON: validasi dibalas redirect (browser tidak bisa
        // membaca alasan penolakan dari response body JSON)
        $r->assertStatus(302);
        $this->assertStringNotContainsString('application/json', (string) $r->headers->get('content-type'));
    }

    /**
     * Test pembuatan konsumen sukses via store-ajax.
     * Butuh: golongan ada + sesi kas buka.
     */
    public function test_konsumen_ajax_berhasil_dibuat(): void
    {
        $this->kasBuka();
        $idGolongan = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Golongan Test']);
        $sebelum = DB::table('konsumen')->count();

        $r = $this->jsonAccept('post', '/konsumen/store-ajax', [
            'nama_konsumen' => 'Pelanggan Test',
            'telepon'       => '08123456789',
            'id_golongan'   => $idGolongan,
            'tipe_konsumen' => 'permanent',
        ]);

        $r->assertStatus(200);
        $r->assertJson(['status' => 'success']);
        $this->assertStringContainsString('Pelanggan Test', $r->json('data.nama_konsumen'));
        $this->assertSame($sebelum + 1, DB::table('konsumen')->count());
    }

    /* ==================================================================
     * C. JERSEY MASTER (fetch JSON via kirimJson)
     * ================================================================== */

    public function test_jersey_designer_ditolak_server(): void
    {
        $sebelum = DB::table('jersey_designers')->count();

        $r = $this->jsonAccept('post', '/jersey/master/designers', ['name' => '']);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['name']);
        $this->assertSame($sebelum, DB::table('jersey_designers')->count());
    }

    public function test_jersey_designer_ubah_ditolak_server(): void
    {
        $id = DB::table('jersey_designers')->insertGetId(['name' => 'Desainer Uji']);
        $sebelum = DB::table('jersey_designers')->where('id', $id)->first();

        $r = $this->jsonAccept('put', "/jersey/master/designers/{$id}", ['name' => '']);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['name']);
        $this->assertEquals($sebelum, DB::table('jersey_designers')->where('id', $id)->first());
    }

    public function test_jersey_product_type_ditolak_server(): void
    {
        $sebelum = DB::table('jersey_product_types')->count();

        $r = $this->jsonAccept('post', '/jersey/master/product-types', ['name' => '']);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['name']);
        $this->assertSame($sebelum, DB::table('jersey_product_types')->count());
    }

    public function test_jersey_category_ditolak_server(): void
    {
        $sebelum = DB::table('jersey_categories')->count();

        $r = $this->jsonAccept('post', '/jersey/master/categories', ['name' => '']);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['name']);
        $this->assertSame($sebelum, DB::table('jersey_categories')->count());
    }

    public function test_jersey_size_ditolak_server(): void
    {
        $sebelum = DB::table('jersey_sizes')->count();

        $r = $this->jsonAccept('post', '/jersey/master/sizes', ['name' => '']);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['name']);
        $this->assertSame($sebelum, DB::table('jersey_sizes')->count());
    }

    public function test_jersey_size_ubah_ditolak_server(): void
    {
        $id = DB::table('jersey_sizes')->insertGetId([
            'name' => 'Ukuran Uji', 'sort_order' => 0, 'price_surcharge' => 0, 'kelompok' => 'dewasa', 'price_percent' => 0,
        ]);
        $sebelum = DB::table('jersey_sizes')->where('id', $id)->first();

        $r = $this->jsonAccept('put', "/jersey/master/sizes/{$id}", ['name' => '']);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['name']);
        $this->assertEquals($sebelum, DB::table('jersey_sizes')->where('id', $id)->first());
    }

    public function test_jersey_material_ditolak_server(): void
    {
        $sebelum = DB::table('jersey_materials')->count();

        $r = $this->jsonAccept('post', '/jersey/master/materials', ['name' => '']);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['name']);
        $this->assertSame($sebelum, DB::table('jersey_materials')->count());
    }

    public function test_jersey_component_ditolak_server(): void
    {
        $sebelum = DB::table('jersey_components')->count();

        $r = $this->jsonAccept('post', '/jersey/master/components', ['name' => '']);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['name']);
        $this->assertSame($sebelum, DB::table('jersey_components')->count());
    }

    public function test_jersey_sleeve_type_ditolak_server(): void
    {
        $sebelum = DB::table('jersey_sleeve_types')->count();

        $r = $this->jsonAccept('post', '/jersey/master/sleeve-types', ['name' => '']);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['name']);
        $this->assertSame($sebelum, DB::table('jersey_sleeve_types')->count());
    }

    public function test_jersey_collar_type_ditolak_server(): void
    {
        $sebelum = DB::table('jersey_collar_types')->count();

        $r = $this->jsonAccept('post', '/jersey/master/collar-types', ['name' => '']);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['name']);
        $this->assertSame($sebelum, DB::table('jersey_collar_types')->count());
    }

    public function test_jersey_usage_ditolak_server(): void
    {
        $r = $this->jsonAccept('post', '/jersey/master/usage', [
            'jersey_size_id' => '', 'jersey_component_id' => '',
        ]);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['jersey_size_id']);
    }

    public function test_jersey_sleeve_usage_ditolak_server(): void
    {
        $r = $this->jsonAccept('post', '/jersey/master/sleeve-usage', [
            'jersey_size_id' => '', 'jersey_sleeve_type_id' => '',
        ]);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['jersey_size_id']);
    }

    public function test_jersey_collar_usage_ditolak_server(): void
    {
        $r = $this->jsonAccept('post', '/jersey/master/collar-usage', [
            'jersey_size_id' => '', 'jersey_collar_type_id' => '',
        ]);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['jersey_size_id']);
    }

    public function test_jersey_price_setting_ditolak_server(): void
    {
        $sebelum = DB::table('jersey_price_settings')->count();

        $r = $this->jsonAccept('post', '/jersey/master/price-settings', [
            'qty_min' => '', 'price' => '',
        ]);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['qty_min']);
        $this->assertSame($sebelum, DB::table('jersey_price_settings')->count());
    }

    public function test_jersey_price_setting_ubah_ditolak_server(): void
    {
        $id = DB::table('jersey_price_settings')->insertGetId([
            'qty_min' => 1, 'qty_max' => 10, 'price' => 10000,
        ]);
        $sebelum = DB::table('jersey_price_settings')->where('id', $id)->first();

        $r = $this->jsonAccept('put', "/jersey/master/price-settings/{$id}", [
            'qty_min' => '', 'price' => '',
        ]);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['qty_min']);
        $this->assertEquals($sebelum, DB::table('jersey_price_settings')->where('id', $id)->first());
    }

    public function test_jersey_price_setting_bulk_ditolak_server(): void
    {
        $sebelum = DB::table('jersey_price_settings')->count();

        $r = $this->jsonAccept('post', '/jersey/master/price-settings/bulk', [
            'jersey_product_type_id' => '', 'tiers' => [],
        ]);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['jersey_product_type_id']);
        $this->assertSame($sebelum, DB::table('jersey_price_settings')->count());
    }

    /* ==================================================================
     * D. UNGGAH BERKAS (fetch FormData)
     * ================================================================== */

    public function test_unggah_gambar_postingan_ditolak_server(): void
    {
        $r = $this->jsonAccept('post', '/postingan/unggah-gambar', []);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['gambar']);
    }

    public function test_thumbnail_jersey_ditolak_server(): void
    {
        $r = $this->jsonAccept('post', '/jersey/upload-thumbnail', []);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['thumbnail']);
    }

    /** Temuan yang sama untuk thumbnail jersey. */
    public function test_thumbnail_jersey_tanpa_accept_mendapat_redirect_bukan_json(): void
    {
        $r = $this->jsonTanpaAccept('post', '/jersey/upload-thumbnail', []);

        $r->assertStatus(302);
        $this->assertStringNotContainsString('application/json', (string) $r->headers->get('content-type'));
    }

    public function test_bukti_jersey_ditolak_server(): void
    {
        $r = $this->jsonAccept('post', '/jersey/keuangan/upload-bukti', []);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['bukti']);
    }

    /** Temuan yang sama untuk bukti bayar jersey. */
    public function test_bukti_jersey_tanpa_accept_mendapat_redirect_bukan_json(): void
    {
        $r = $this->jsonTanpaAccept('post', '/jersey/keuangan/upload-bukti', []);

        $r->assertStatus(302);
        $this->assertStringNotContainsString('application/json', (string) $r->headers->get('content-type'));
    }

    public function test_thumbnail_penjualan_ditolak_server(): void
    {
        $r = $this->jsonAccept('post', '/penjualan/upload-thumbnail', []);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['thumbnail']);
    }

    /** Temuan yang sama untuk thumbnail penjualan. */
    public function test_thumbnail_penjualan_tanpa_accept_mendapat_redirect_bukan_json(): void
    {
        $r = $this->jsonTanpaAccept('post', '/penjualan/upload-thumbnail', []);

        $r->assertStatus(302);
        $this->assertStringNotContainsString('application/json', (string) $r->headers->get('content-type'));
    }

    public function test_bukti_kas_kecil_ditolak_server(): void
    {
        $r = $this->jsonAccept('post', '/kas-kecil/upload-bukti', []);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['bukti']);
    }

    /* ==================================================================
     * E. OPERASIONAL AJAX (bayar jersey, detail opname)
     * ================================================================== */

    public function test_bayar_jersey_ditolak_server(): void
    {
        $idOrder = DB::table('jersey_orders')->insertGetId([
            'order_number' => 'JS-UJI-1', 'order_date' => '2026-01-01',
            'customer_name' => 'Pelanggan Uji', 'total' => 10000,
        ]);
        $sebelum = DB::table('jersey_order_payments')->count();

        $r = $this->jsonAccept('post', "/jersey/keuangan/{$idOrder}/bayar", [
            'tanggal_bayar' => '', 'jumlah' => '', 'metode_bayar' => '',
        ]);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['tanggal_bayar']);
        $this->assertSame($sebelum, DB::table('jersey_order_payments')->count());
    }

    public function test_detail_opname_ditolak_server(): void
    {
        $idOpname = DB::table('stock_opnames')->insertGetId([
            'nomor' => 'SO-UJI', 'tanggal' => '2026-01-01', 'status' => 'Draft',
            'id_user_pembuat' => $this->admin->getAuthIdentifier(),
        ]);
        $sebelum = DB::table('stock_opname_details')->count();

        $r = $this->jsonAccept('patch', "/stock-opname/{$idOpname}", ['detail' => '']);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['detail']);
        $this->assertSame($sebelum, DB::table('stock_opname_details')->count());
    }

    /* ==================================================================
     * F. PENJUALAN.STORE (validasi manual, selalu JSON)
     * ================================================================== */

    public function test_penjualan_store_keranjang_kosong_ditolak_json(): void
    {
        $sebelum = DB::table('transaksi')->count();

        $r = $this->jsonAccept('post', '/penjualan/store', [
            'id_konsumen' => 1, 'id_golongan' => 1, 'items' => [],
        ]);

        $r->assertStatus(400);
        $r->assertJson(['status' => 'error']);
        $this->assertStringContainsString('Keranjang kosong', $r->json('msg'));
        $this->assertSame($sebelum, DB::table('transaksi')->count());
    }

    public function test_penjualan_store_konsumen_kosong_ditolak_json(): void
    {
        $sebelum = DB::table('transaksi')->count();

        $r = $this->jsonAccept('post', '/penjualan/store', [
            'id_konsumen' => '', 'id_golongan' => 1, 'items' => [['tipe' => 'bahan']],
        ]);

        $r->assertStatus(400);
        $r->assertJson(['status' => 'error']);
        $this->assertStringContainsString('Konsumen wajib dipilih', $r->json('msg'));
        $this->assertSame($sebelum, DB::table('transaksi')->count());
    }

    /* ------------------------------------------------------------------
     * Fixture helper
     * ------------------------------------------------------------------ */

    private function produkUji(): int
    {
        return (int) DB::table('produk')->insertGetId([
            'nama_produk' => 'Produk Uji', 'id_mesin' => null, 'metode_hitung' => 'quantity',
            'barcode' => null, 'spk_lengkap' => 0, 'min_order' => 0,
            'min_panjang' => 0, 'min_lebar' => 0, 'keterangan' => '-', 'status' => 'aktif',
        ]);
    }

    /** Buka sesi kas untuk admin (konsumen/store-ajax menolak tanpa sesi kas). */
    private function kasBuka(): void
    {
        DB::table('kas_harian')->insert([
            'tgl_kas' => now()->format('Y-m-d'),
            'id_user' => $this->admin->getAuthIdentifier(),
            'saldo_awal' => 0, 'saldo_akhir' => 0,
            'status_lock' => 'open',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
