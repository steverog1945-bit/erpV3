<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AUDIT C1: tier harga bisa terduplikasi saat kirim ganda.
 *
 * TIGA endpoint yang diperiksa:
 *   - POST /finishing/{id}/harga        FinishingHargaController@store
 *   - POST /jasa/{id}/harga             JasaHargaController@store
 *   - POST /setting-harga/{id}/tier     SettingHargaController@storeRow
 *
 * Pertanyaan yang harus dijawab test ini, bukan asumsi:
 *   Q1 Dua POST identik berurutan -> berapa baris di database?
 *   Q2 Dua POST dengan rentang BERBEDA -> tetap bisa dua-duanya? (wajib,
 *      ini pembuatan tier yang sah dan tidak boleh diblokir)
 *   Q3 POST kedua harus dijawab dengan cara yang|Original tidak berubah?
 *   Q4 Submit yang gagal validasi -> sisa baris?
 *   Q5 Apakah endpoint ini terlindungi SubmitSekaliJalan? Middleware itu
 *      hanya jalan bila ada submit_id. Ketiga form di sini dikirim lewat
 *      fetch() dengan body JSON, jadi submit_id tidak pernah ikut - dan ini
 *      diuji langsung dengan mengirim request yang TIDAK membawa submit_id
 *      (setara JavaScript dimatikan dan POST dikirim mentah).
 *
 * Skema yang sudah diperiksa lebih dulu (bukti, bukan asumsi):
 *   finishing_harga : UNIQUE hanya id_finishing_harga (primary key)
 *   jasa_harga      : UNIQUE hanya id_jasa_harga (primary key)
 *   setting_harga   : UNIQUE hanya id_harga (primary key)
 * Tidak ada satu pun dari tiga tabel yang punya constraint unik pada
 * kombinasi rentang, jadi constraint database tidak bisa diandalkan.
 */
class TierHargaKirimGandaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idFinishing;
    private int $idJasa;
    private int $idProduk;
    private int $idGolongan;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-05 10:00:00'));

        $this->admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);

        $now = now();

        $this->idGolongan = (int) (DB::table('golongan_konsumen')->where('nama_golongan', 'Umum')->value('id_golongan')
            ?: DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Umum', 'keterangan' => '-']));

        $satuan = (int) (DB::table('satuan')->where('nama_satuan', 'Pcs')->value('id_satuan')
            ?: DB::table('satuan')->insertGetId([
                'nama_satuan' => 'Pcs', 'nilai_konversi' => 1, 'tipe_satuan' => 'Barang',
            ]));

        // Kolom diambil dari skema sebenarnya, bukan asumsi.
        $this->idFinishing = (int) (DB::table('finishing')->where('nama_finishing', 'Lipat')->value('id_finishing')
            ?: DB::table('finishing')->insertGetId([
                'nama_finishing' => 'Lipat', 'harga_default' => 0, 'id_satuan' => $satuan,
                'metode_hitung' => 'quantity', 'created_at' => $now, 'updated_at' => $now,
            ]));

        $this->idJasa = (int) (DB::table('jasa')->where('nama_jasa', 'Jasa Uji')->value('id_jasa')
            ?: DB::table('jasa')->insertGetId([
                'nama_jasa' => 'Jasa Uji', 'id_satuan' => $satuan, 'harga_default' => 0,
                'biaya_operasional' => 0, 'biaya_tinta' => 0, 'keterangan' => '-',
                'created_at' => $now, 'updated_at' => $now,
            ]));

        $this->idProduk = (int) (DB::table('produk')->where('nama_produk', 'Produk Uji')->value('id_produk')
            ?: DB::table('produk')->insertGetId([
                'nama_produk' => 'Produk Uji', 'barcode' => 'PUJI', 'metode_hitung' => 'quantity',
                'min_order' => 0, 'min_panjang' => 0, 'min_lebar' => 0,
                'status' => 'aktif', 'created_at' => $now, 'updated_at' => $now,
            ]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /* ---------- helper ---------- */

    private function postFinishing(array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)
            ->postJson(route('finishing.harga.store', $this->idFinishing), $data);
    }

    private function postJasa(array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)
            ->postJson(route('jasa.harga.store', $this->idJasa), $data);
    }

    private function postSettingHarga(array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)
            ->postJson(route('setting-harga.tier.store', $this->idProduk), $data);
    }

    private function tier(int $min, ?int $max = null, int $harga = 10000): array
    {
        return ['range_min' => $min, 'range_max' => $max, 'harga' => $harga];
    }

    private function tierSettingHarga(int $min, ?int $max = null, int $harga = 10000): array
    {
        return [
            'id_golongan' => $this->idGolongan,
            'range_min' => $min,
            'range_max' => $max,
            'harga_jual' => $harga,
        ];
    }

    private function nFinishing(): int
    {
        return DB::table('finishing_harga')->where('id_finishing', $this->idFinishing)->count();
    }

    private function nJasa(): int
    {
        return DB::table('jasa_harga')->where('id_jasa', $this->idJasa)->count();
    }

    private function nSettingHarga(): int
    {
        return DB::table('setting_harga')
            ->where('id_produk', $this->idProduk)
            ->where('id_golongan', $this->idGolongan)
            ->count();
    }

    /* ================================================================
       Q1 - dua POST identik berurutan (setara klik ganda)
       ================================================================ */

    public function test_finishing_kirim_ganda_identik_tidak_membuat_tier_ganda(): void
    {
        $p = $this->tier(1, 10, 5000);

        $r1 = $this->postFinishing($p);
        $r2 = $this->postFinishing($p);

        $r1->assertSuccessful();
        $this->assertSame(
            1,
            $this->nFinishing(),
            'Kirim ganda pada finishing_harga menghasilkan ' . $this->nFinishing() . ' tier. Harusnya 1.'
        );

        // Permintaan kedua harus ditolak dengan pesan, bukan diam-diam sukses.
        $this->assertFalse(
            $r2->json('success', true),
            'Permintaan kedua tidak boleh dilaporkan sukses'
        );
        $this->assertNotEmpty($r2->json('message'), 'Permintaan kedua harus punya pesan yang bisa ditampilkan');
    }

    public function test_jasa_kirim_ganda_identik_tidak_membuat_tier_ganda(): void
    {
        $p = $this->tier(1, 10, 5000);

        $r1 = $this->postJasa($p);
        $r2 = $this->postJasa($p);

        $r1->assertSuccessful();
        $this->assertSame(
            1,
            $this->nJasa(),
            'Kirim ganda pada jasa_harga menghasilkan ' . $this->nJasa() . ' tier. Harusnya 1.'
        );
        $this->assertFalse($r2->json('success', true), 'Permintaan kedua tidak boleh dilaporkan sukses');
        $this->assertNotEmpty($r2->json('message'), 'Permintaan kedua harus punya pesan');
    }

    public function test_setting_harga_kirim_ganda_identik_tidak_membuat_tier_ganda(): void
    {
        $p = $this->tierSettingHarga(1, 10, 5000);

        $r1 = $this->postSettingHarga($p);
        $r2 = $this->postSettingHarga($p);

        $r1->assertSuccessful();
        $this->assertSame(
            1,
            $this->nSettingHarga(),
            'Kirim ganda pada setting_harga menghasilkan ' . $this->nSettingHarga() . ' tier. Harusnya 1.'
        );
    }

    /* ================================================================
       Q1b - tanpa submit_id (setara JavaScript dimatikan)
       ================================================================ */

    public function test_endpoint_tier_aman_terhadap_post_mentah_tanpa_submit_id(): void
    {
        // Dua POST identik tanpa submit_id sama sekali. Kalau endpointnya
        // bergantung pada middleware idempotensi, ini akan menghasilkan
        // dua baris dan test ini gagal.
        $this->postFinishing($this->tier(1, 10, 5000))->assertSuccessful();
        $this->postFinishing($this->tier(1, 10, 5000));
        $this->assertSame(1, $this->nFinishing(), 'POST mentah tanpa submit_id harus tetap aman');

        $this->postJasa($this->tier(1, 10, 5000))->assertSuccessful();
        $this->postJasa($this->tier(1, 10, 5000));
        $this->assertSame(1, $this->nJasa(), 'POST mentah tanpa submit_id harus tetap aman');

        $this->postSettingHarga($this->tierSettingHarga(1, 10, 5000))->assertSuccessful();
        $this->postSettingHarga($this->tierSettingHarga(1, 10, 5000));
        $this->assertSame(1, $this->nSettingHarga(), 'POST mentah tanpa submit_id harus tetap aman');
    }

    /* ================================================================
       Q2 - rentang BERBEDA tetap harus diterima (tier yang sah)
       ================================================================ */

    public function test_tier_dengan_rentang_berbeda_tetap_diterima(): void
    {
        $this->postFinishing($this->tier(1, 10, 5000))->assertSuccessful();
        $this->postFinishing($this->tier(11, 20, 4500))->assertSuccessful();
        $this->postFinishing($this->tier(21, null, 4000))->assertSuccessful();
        $this->assertSame(3, $this->nFinishing(), 'Tiga rentang berbeda harus ketiganya tersimpan');

        $this->postJasa($this->tier(1, 10, 5000))->assertSuccessful();
        $this->postJasa($this->tier(11, 20, 4500))->assertSuccessful();
        $this->assertSame(2, $this->nJasa(), 'Dua rentang berbeda harus ketiganya tersimpan');

        $this->postSettingHarga($this->tierSettingHarga(1, 10, 5000))->assertSuccessful();
        $this->postSettingHarga($this->tierSettingHarga(11, 20, 4500))->assertSuccessful();
        $this->assertSame(2, $this->nSettingHarga(), 'Dua rentang berbeda harus tetap diterima');
    }

    /** Rentang yang tumpang tindih sebagian pun harus ditolak. */
    public function test_rentang_tumpang_tindih_sebagian_ditolak_di_semua_tiga_endpoint(): void
    {
        $this->postFinishing($this->tier(1, 10, 5000))->assertSuccessful();
        $this->postFinishing($this->tier(5, 15, 5000));
        $this->assertSame(1, $this->nFinishing(), 'Rentang 5-15 tumpang tindih 1-10, harus ditolak');

        $this->postJasa($this->tier(1, 10, 5000))->assertSuccessful();
        $this->postJasa($this->tier(5, 15, 5000));
        $this->assertSame(1, $this->nJasa(), 'Rentang 5-15 tumpang tindih 1-10, harus ditolak');

        $this->postSettingHarga($this->tierSettingHarga(1, 10, 5000))->assertSuccessful();
        $this->postSettingHarga($this->tierSettingHarga(5, 15, 5000));
        $this->assertSame(1, $this->nSettingHarga(), 'Rentang tumpang tindih harus ditolak');
    }

    /* ================================================================
       Q4 - kegagalan validasi tidak meninggalkan data separuh
       ================================================================ */

    public function test_validasi_gagal_tidak_meninggalkan_baris(): void
    {
        // range_min 0 melanggar min:0.01 / min:1
        $this->postFinishing(['range_min' => 0, 'range_max' => 10, 'harga' => 5000]);
        $this->assertSame(0, $this->nFinishing(), 'Tidak boleh ada baris tersisa setelah validasi gagal');

        $this->postJasa(['range_min' => 0, 'range_max' => 10, 'harga' => 5000]);
        $this->assertSame(0, $this->nJasa(), 'Tidak boleh ada baris tersisa setelah validasi gagal');

        $this->postSettingHarga(['id_golongan' => $this->idGolongan, 'range_min' => 0, 'range_max' => 10, 'harga_jual' => 5000]);
        $this->assertSame(0, $this->nSettingHarga(), 'Tidak boleh ada baris tersisa setelah validasi gagal');

        // range_max < range_min melanggar gte
        $this->postFinishing(['range_min' => 10, 'range_max' => 5, 'harga' => 5000]);
        $this->assertSame(0, $this->nFinishing(), 'range_max < range_min harus ditolak');
    }

    /* ================================================================
       Q3 - bentuk response tidak berubah
       ================================================================ */

    public function test_bentuk_response_sukses_tetap_apa_adanya(): void
    {
        $r = $this->postFinishing($this->tier(1, 10, 5000));
        $r->assertSuccessful();
        $this->assertTrue($r->json('success'));
        $this->assertNotEmpty($r->json('message'));
        $this->assertNotNull($r->json('row'), 'Response harus tetap membawa row');
        // Auto-increment tidak direset antar-test saat RefreshDatabase memakai
// transaksi, jadi id yang benar-benar diuji adalah "ada dan positif",
// bukan angkanya persis 1.
$this->assertGreaterThan(0, (int) $r->json('row.id_finishing_harga'), 'Response harus membawa row yang tersimpan');

        $r = $this->postJasa($this->tier(1, 10, 5000));
        $r->assertSuccessful();
        $this->assertTrue($r->json('success'));
        $this->assertNotEmpty($r->json('message'));
        $this->assertNotNull($r->json('row'), 'Response harus tetap membawa row');
        $this->assertGreaterThan(0, (int) $r->json('row.id_jasa_harga'), 'Response harus membawa row yang tersimpan');

        $r = $this->postSettingHarga($this->tierSettingHarga(1, 10, 5000));
        $r->assertSuccessful();
        $this->assertTrue($r->json('success'));
        $this->assertNotEmpty($r->json('message'));
        $this->assertNotNull($r->json('row'), 'Response harus tetap membawa row');
    }

    /** Penolakan harus konsisten di tiga endpoint: success=false + pesan. */
    public function test_penolakan_duplikat_punya_bentuk_respon_yang_konsisten(): void
    {
        $this->postFinishing($this->tier(1, 10, 5000))->assertSuccessful();
        $r = $this->postFinishing($this->tier(1, 10, 5000));
        $this->assertFalse($r->json('success', true));
        $this->assertNotEmpty($r->json('message'));

        $this->postJasa($this->tier(1, 10, 5000))->assertSuccessful();
        $r = $this->postJasa($this->tier(1, 10, 5000));
        $this->assertFalse($r->json('success', true));
        $this->assertNotEmpty($r->json('message'));

        $this->postSettingHarga($this->tierSettingHarga(1, 10, 5000))->assertSuccessful();
        $r = $this->postSettingHarga($this->tierSettingHarga(1, 10, 5000));
        $this->assertFalse($r->json('success', true));
        $this->assertNotEmpty($r->json('message'));
    }

    /* ================================================================
       Q5 - apakah ketiga endpoint benar-benar tidak memakai submit_id?
       Ini mengunci asal masalah: kalau suatu saat formnya diubah supaya
       mengirim submit_id, test ini harus ikut diperbarui.
       ================================================================ */

    public function test_form_tier_memakai_fetch_json_tanpa_submit_id(): void
    {
        $files = [
            resource_path('views/finishing/index.blade.php') => 'submitTierFormAjax',
            resource_path('views/master/jasa/index.blade.php') => 'submitJasaTierFormAjax',
            resource_path('views/master/setting_harga/index.blade.php') => 'submitHargaTierFormAjax',
        ];

        foreach ($files as $file => $helper) {
            $isi = file_get_contents($file);
            $this->assertStringContainsString($helper, $isi, "Helper $helper harus ada di $file");

            // Yang diperiksa adalah BADAN helper - bagian yang benar-benar
            // menyusun payload. Memeriksa seluruh file terlalu kasar: satu
            // komentar saja sudah cukup untuk membuatnya gagal.
            $badan = $this->badanFungsi($isi, $helper);
            $this->assertNotNull($badan, "Tidak bisa menemukan badan fungsi $helper");

            $this->assertStringNotContainsString(
                'submit_id',
                $badan,
                "Helper $helper tidak boleh mengirim submit_id - SubmitSekaliJalan "
                . 'hanya jalan bila submit_id ada di body'
            );

            // Bukti bahwa jalur ini memang JSON, bukan submit form native.
            $this->assertStringContainsString(
                'application/json',
                $badan,
                "Helper $helper harus mengirim body JSON"
            );
            $this->assertStringNotContainsString(
                'form.submit()',
                $badan,
                "Helper $helper tidak boleh mengandalkan form.submit() native"
            );
        }
    }

    /** Badan sebuah async function, dihitung dengan keseimbangan kurung kurawal. */
    private function badanFungsi(string $isi, string $nama): ?string
    {
        if (!preg_match('/(?:async\s+)?function\s+' . preg_quote($nama, '/') . '\s*\([^)]*\)\s*\{/', $isi, $m)) {
            return null;
        }
        $dari = strpos($isi, '{', strpos($isi, $nama));
        if ($dari === false) {
            return null;
        }
        $depth = 0;
        for ($i = $dari; $i < strlen($isi); $i++) {
            if ($isi[$i] === '{') {
                $depth++;
            } elseif ($isi[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($isi, $dari, $i - $dari + 1);
                }
            }
        }

        return null;
    }

    /** Setelah perbaikan, ketiga halaman harus punya penaga klik ganda lokal. */
    public function test_form_tier_memiliki_penaga_klik_ganda_lokal(): void
    {
        $files = [
            resource_path('views/finishing/index.blade.php') => 'finishing',
            resource_path('views/master/jasa/index.blade.php') => 'jasa',
            resource_path('views/master/setting_harga/index.blade.php') => 'setting harga',
        ];

        foreach ($files as $file => $label) {
            $isi = file_get_contents($file);
            $this->assertTrue(
                preg_match('/\.disabled\s*=\s*true/', $isi) === 1
                && preg_match('/\.disabled\s*=\s*false/', $isi) === 1,
                "$label harus punya penaga klik ganda lokal (disabled true lalu false)"
            );
        }
    }
}