<?php

namespace Tests\Feature;

use App\Http\Controllers\SettingHargaController;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cetak Daftar Harga + Tier per Golongan Konsumen.
 *
 * Kontrak:
 * - Izin: tanpa login = redirect ke /masuk; role tanpa roles.boleh_lihat_harga
 *   = 403 (walau menu harga terbuka); role dengan hak = 200.
 * - Output HANYA harga jual: tidak ada kata/kolom HPP, modal, margin,
 *   harga beli.
 * - Angka matriks = HargaService (tes khususnya ada di
 *   DaftarHargaCetakSelisihRehearsalTest).
 * - Filter golongan (satu/semua) & kategori (produk/jasa/finishing).
 * - Item tanpa tier ditampilkan satu baris flat; produk tanpa harga tidak
 *   dicetak; finishing tanpa tier (mis. Potong Manual A3) tampil dengan
 *   catatan minimum charge, tidak error.
 * - Query eager: jumlah query menyusun + merender seluruh daftar tetap
 *   kecil (tanpa N+1).
 */
class DaftarHargaCetakTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idGolA = 1;
    private int $idGolB = 2;

    protected function setUp(): void
    {
        parent::setUp();

        $db = config('database.connections.mysql.database');
        if ($db !== 'percetakan_erp_testing') {
            $this->markTestSkipped("Testing DB bukan percetakan_erp_testing (sekarang: {$db}). Test dilewati demi keamanan.");
        }

        $this->admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);

        // Seed dasar: dua golongan, mesin, produk + tier, jasa, finishing.
        DB::table('golongan_konsumen')->insert([
            ['id_golongan' => 1, 'nama_golongan' => 'Retail Cetak', 'keterangan' => '-'],
            ['id_golongan' => 2, 'nama_golongan' => 'Grosir Korporat', 'keterangan' => '-'],
        ]);
        $idMesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'EPSON S80670', 'tipe_mesin' => 'Digital']);

        $this->idProduk = DB::table('produk')->insertGetId([
            'nama_produk' => 'BR-Banner Roll', 'id_mesin' => $idMesin, 'metode_hitung' => 'ukuran',
            'min_order' => 0, 'keterangan' => '-', 'status' => 'aktif', 'barcode' => '',
        ]);
        $this->idProdukTanpaHarga = DB::table('produk')->insertGetId([
            'nama_produk' => 'BR-Produk Tanpa Harga', 'id_mesin' => $idMesin, 'metode_hitung' => 'quantity',
            'min_order' => 0, 'keterangan' => '-', 'status' => 'aktif', 'barcode' => '',
        ]);
        $this->idProdukNonaktif = DB::table('produk')->insertGetId([
            'nama_produk' => 'BR-Produk Nonaktif', 'id_mesin' => $idMesin, 'metode_hitung' => 'quantity',
            'min_order' => 0, 'keterangan' => '-', 'status' => 'nonaktif', 'barcode' => '',
        ]);

        // Tier produk: rentang berbeda per golongan (baris & kolom campuran).
        DB::table('setting_harga')->insert([
            ['id_produk' => $this->idProduk, 'id_golongan' => 1, 'range_min' => 1, 'range_max' => null, 'harga_jual' => 25000],
            ['id_produk' => $this->idProduk, 'id_golongan' => 1, 'range_min' => 11, 'range_max' => null, 'harga_jual' => 22000],
            ['id_produk' => $this->idProduk, 'id_golongan' => 2, 'range_min' => 1, 'range_max' => null, 'harga_jual' => 19000],
        ]);

        // Jasa TANPA tier tapi punya harga_default -> satu baris harga flat.
        $this->idJasa = DB::table('jasa')->insertGetId([
            'nama_jasa' => 'BR-Jasa Kaca', 'harga_default' => 50000, 'biaya_operasional' => 0, 'biaya_tinta' => 0, 'keterangan' => '-',
        ]);

        // Jasa tanpa tier DAN tanpa harga (tidak dicetak).
        DB::table('jasa')->insert([
            'nama_jasa' => 'BR-Jasa Tanpa Harga', 'harga_default' => 0, 'biaya_operasional' => 0, 'biaya_tinta' => 0, 'keterangan' => '-',
        ]);

        // Finishing DENGAN tier + minimum charge.
        $this->idFinishing = DB::table('finishing')->insertGetId([
            'nama_finishing' => 'BR-QC Warna', 'harga_default' => 1000, 'minimum_charge' => 30000,
            'metode_hitung' => 'BARIS', 'keterangan' => '-',
        ]);
        DB::table('finishing_harga')->insert([
            ['id_finishing' => $this->idFinishing, 'range_min' => 1, 'range_max' => 5, 'harga' => 8000],
            ['id_finishing' => $this->idFinishing, 'range_min' => 6, 'range_max' => null, 'harga' => 6000],
        ]);

        // Finishing LINEAR_GRID TANPA tier (analog "Potong Manual A3" di
        // data nyata): harus tampil satu baris flat + catatan, bukan error.
        $this->idFinishingTanpaTier = DB::table('finishing')->insertGetId([
            'nama_finishing' => 'BR-Potong Manual A3', 'harga_default' => 450, 'minimum_charge' => 5000,
            'metode_hitung' => 'LINEAR_GRID', 'keterangan' => '-',
        ]);
    }

    private int $idProduk;
    private int $idProdukTanpaHarga;
    private int $idProdukNonaktif;
    private int $idJasa;
    private int $idFinishing;
    private int $idFinishingTanpaTier;

    /** ========================================================= IZIN */

    public function test_tanpa_login_redirect_ke_status_masuk(): void
    {
        $this->get('/setting-harga/cetak')
            ->assertStatus(302)
            ->assertRedirect(route('login'));
    }

    public function test_role_tanpa_hak_lihat_harga_ditolak_403(): void
    {
        $role = Role::factory()->create(['nama_role' => 'Kasir', 'boleh_lihat_harga' => 0]);
        // Menu 14 (prefix setting-harga) TERBUKA untuk role ini via
        // role_permissions — keburu ditutup 403 oleh AksesHarga di action.
        DB::table('role_permissions')->insert([
            'id_role' => $role->id_role, 'id_menu' => 14, 'can_view' => 1, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0,
        ]);
        $kasir = User::factory()->create(['id_role' => $role->id_role]);
        $this->actingAs($kasir);

        $this->get('/setting-harga/cetak')->assertForbidden();
    }

    public function test_role_dengan_hak_lihat_harga_dan_menu_terbuka_dapat_200(): void
    {
        $role = Role::factory()->create(['nama_role' => 'Owner Tampak Harga', 'boleh_lihat_harga' => 1]);
        DB::table('role_permissions')->insert([
            'id_role' => $role->id_role, 'id_menu' => 14, 'can_view' => 1, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0,
        ]);
        $owner = User::factory()->create(['id_role' => $role->id_role]);
        $this->actingAs($owner);

        $this->get('/setting-harga/cetak')
            ->assertOk()
            ->assertViewIs('master.setting_harga.cetak');
    }

    /** ================================================== HALAMAN & ISI */

    public function test_halaman_menampilkan_blok_semua_kategori_dan_tombol_di_daftar_harga(): void
    {
        $this->actingAs($this->admin);

        // Tombol "Cetak Daftar Harga" ada di halaman daftar harga (/setting-harga).
        $daftarHtml = $this->get('/setting-harga')->getContent();
        $this->assertStringContainsString('setting-harga/cetak', $daftarHtml);
        $this->assertStringContainsString('Cetak Daftar Harga', $daftarHtml);

        $html = $this->get('/setting-harga/cetak')->getContent();

        $this->assertStringContainsString('BR-Banner Roll', $html);
        $this->assertStringContainsString('BR-Jasa Kaca', $html);
        $this->assertStringContainsString('BR-QC Warna', $html);
        $this->assertStringContainsString('BR-Potong Manual A3', $html);

        // Kolom golongan angka.
        $this->assertStringContainsString('Retail Cetak', $html);
        $this->assertStringContainsString('Grosir Korporat', $html);
        // Angka tier produk per golongan.
        $this->assertStringContainsString('Rp 25.000', $html);
        $this->assertStringContainsString('Rp 19.000', $html);
        // Harga flat jasa & finishing tanpa tier.
        $this->assertStringContainsString('Rp 50.000', $html);
        $this->assertStringContainsString('Rp 450', $html);

        // Catatan minimum charge finishing.
        $this->assertStringContainsString('minimum charge Rp 30.000', $html);
        $this->assertStringContainsString('minimum charge Rp 5.000', $html);
    }

    public function test_item_nonaktif_atau_tanpa_harga_tidak_dicetak(): void
    {
        $this->actingAs($this->admin);
        $html = $this->get('/setting-harga/cetak')->getContent();

        $this->assertStringNotContainsString('BR-Produk Nonaktif', $html);
        $this->assertStringNotContainsString('BR-Produk Tanpa Harga', $html);
        $this->assertStringNotContainsString('BR-Jasa Tanpa Harga', $html);
    }

    public function test_output_tidak_memuat_hpp_modal_margin_harga_beli(): void
    {
        $this->actingAs($this->admin);
        $html = $this->get('/setting-harga/cetak')->getContent();

        // Yang diuji = umpan CETAK: hanya isi <main> (layout aside/nav berisi
        // link laporan HPP dan modul lain yang justru di-SEMBUNYIKAN saat
        // cetak), dan buang embed script/style (CSS memakai properti margin
        // & JS memakai openModal - bukan kolom/harga yang dicetak).
        preg_match('/<main[\s\S]*?<\/main>/i', $html, $m);
        $isi = $m[0] ?? '';
        $isi = preg_replace('/<script[\s\S]*?<\/script>/i', '', $isi);
        $isi = preg_replace('/<style[\s\S]*?<\/style>/i', '', $isi);
        $isi = preg_replace('/<aside[\s\S]*?<\/aside>/i', '', $isi);

        $this->assertDoesNotMatchRegularExpression('/(?i)\bhpp\b|\bmodal\b|\bmargin\b|harga beli/', $isi);
    }

    public function test_filter_golongan_hanya_menyajikan_kolom_tersebut(): void
    {
        $this->actingAs($this->admin);

        $html = $this->get('/setting-harga/cetak?golongan=1')->getContent();
        $this->assertStringContainsString('Retail Cetak', $html);
        $this->assertStringNotContainsString('Grosir Korporat', $html);
        // Harga tier gol 1 saja yang tampil (gol 2 flats hilang).
        $this->assertStringContainsString('Rp 25.000', $html);

        // Filter kategori jasa: produk & finishing tidak muncul.
        $htmlJasa = $this->get('/setting-harga/cetak?kategori=jasa')->getContent();
        $this->assertStringContainsString('BR-Jasa Kaca', $htmlJasa);
        $this->assertStringNotContainsString('BR-Banner Roll', $htmlJasa);
        $this->assertStringNotContainsString('BR-QC Warna', $htmlJasa);
    }

    /** ============================ EAGER QUERY & STRUKTUR DATA */

    public function test_jumlah_query_menyusun_dan_merender_tetap_kecil(): void
    {
        $this->actingAs($this->admin);

        DB::enableQueryLog();
        $controller = app(SettingHargaController::class);
        $data = $controller->susunDataDaftarHarga(new Request(['kategori' => 'semua']));
        // Render telah: nilai $errors dibagikan middleware normal; render
        // langsung tanpa request HTTP perlu diisi manual (bagian layout).
        view('master.setting_harga.cetak', $data)
            ->with('errors', new \Illuminate\Support\ViewErrorBag)
            ->render();
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 80+ sumber data harus dibangun dengan eager load; ambang 40 jauh
        // di bawah pola N+1 per item (di lingkungan ini item ~5).
        $this->assertLessThan(40, $jumlah, "Query: {$jumlah}");
    }

    public function test_produk_tanpa_tier_memiliki_struktur_benar(): void
    {
        $controller = app(SettingHargaController::class);
        $data = $controller->susunDataDaftarHarga(new Request(['kategori' => 'produk', 'golongan' => 1]));

        // Blok produk pin tier: kolom hanya gol 1, harga dari setting_harga.
        $blokBanner = collect($data['bloks'])->firstWhere('id', $this->idProduk);
        $this->assertNotNull($blokBanner);
        $this->assertSame(1, $data['golongan']->count());
        // Dua baris tier gol 1: 1-∞ lalu 11-∞ (urut rentang naik).
        $this->assertSame([25000.0, 22000.0], array_map(
            fn ($b) => $b['harga'][$this->idGolA]['harga'],
            $blokBanner['baris']
        ));
    }
}
