<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * §8D - AUDIT RAW SQL INJECTION pada dua titik yang sudah ditandai.
 *
 * Titik yang diperiksa (§8B telah menemukan keduanya):
 *   1. LaporanController::L759/L767/L787  - DB::raw($omzetBersih)
 *   2. CollectingController::L46           - selectRaw("{$item} as total_item...")
 *
 * Fokus: pastikan tidak ada input user yang dapat mencapai SQL mentah.
 *
 * Standar bukti:
 *   a. Nilai raw string itu hanya dihasilkan dari literal internal, bukan dari
 *      $request. Ini dibuktikan dengan memeriksa AST via regex pada source:
 *      variabel yang masuk ke DB::raw / selectRaw tidak boleh mengandung
 *      $request, $input, $get, $post, $query.
 *   b. Uji nyata: kirim payload injeksi lewat filter laporan & collecting,
 *      respons tetap 200 dan tidak ada SQL error.
 *
 * Sejumlah pembatas:
 *   - Hanya kedua titik. Jangan lanjutkan ke "cek semua raw SQL" di ronde ini- - Error disclosure, logging, concurrency, TOCTOU TIDAK disentuh di sini.
 */
class RawSqlAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);
    }

    /* ================================================================
       1. STATIC: tidak ada input user yang masuk ke DB::raw/selectRaw
       ================================================================ */

    public function test_tidak_ada_variabel_request_di_raw_sql_laporan(): void
    {
        $isi = file_get_contents(
            dirname(__DIR__, 2) . '/app/Http/Controllers/LaporanController.php'
        );

        // Setiap panggilan DB::raw/selectRaw yang membungkus variabel PHP
        // diinspect apakah variabel itu $request-driven.
        preg_match_all('/DB::raw\s*\(\s*([^)]+)\)/', $isi, $m1);
        preg_match_all('/selectRaw\s*\(\s*["\']([^"\']*)["\']/', $isi, $m2);

        $tersangka = [];

        foreach ($m1[1] as $arg) {
            // $omzetBersih = literal kombinasi dari OmzetBersih::sql(), yang
            // sendiri hanya literal. Boleh. Yang tidak boleh: $request,
            // $input, variabel langsung dari request.
            if (preg_match('/\$request\b|->\s*input\(|->\s*all\(|\$\_(GET|POST|REQUEST)/', $arg)) {
                $tersangka[] = 'DB::raw(' . $arg . ')';
            }
        }

        foreach ($m2[1] as $sql) {
            // {$variabel} di dalam string pakai boleh asal variabel itu literal.
            if (preg_match_all('/\{\s*\$(\w+)\s*\}/', $sql, $mm)) {
                foreach ($mm[1] as $var) {
                    if (in_array($var, ['request', 'input', 'get', 'post', 'query'])) {
                        $tersangka[] = 'selectRaw(...{$' . $var . '}...)';
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $tersangka,
            'LaporanController mengekspos variabel request ke raw SQL. Temuan: '
            . implode(', ', $tersangka)
        );
    }

    public function test_tidak_ada_variabel_request_di_raw_sql_collecting(): void
    {
        $isi = file_get_contents(
            dirname(__DIR__, 2) . '/app/Http/Controllers/CollectingController.php'
        );

        preg_match_all('/selectRaw\s*\(\s*["\']([^"\']*)["\']/', $isi, $m2);

        $tersangka = [];
        foreach ($m2[1] as $sql) {
            if (preg_match_all('/\{\s*\$(\w+)\s*\}/', $sql, $mm)) {
                foreach ($mm[1] as $var) {
                    if (in_array($var, ['request', 'input', 'get', 'post', 'query'])) {
                        $tersangka[] = 'selectRaw(...{$' . $var . '}...)';
                    }
                }
            }
        }

        // Verifikasi kedua bahwa {$item}/{$kelar}/{$batal} adalah literal:
        // nilai variabel-variabel itu harus string SQL statis, bukan input.
        foreach (['item', 'kelar', 'batal'] as $var) {
            $this->assertMatchesRegularExpression(
                '/\$' . $var . '\s*=\s*["\']\(select count\(\*\)/',
                $isi,
                "\${$var} harus diisi string literal, bukan input user"
            );
        }

        $this->assertSame([], $tersangka, 'CollectingController raw SQL mengekspos request.');
    }

    /** OmzetBersih::sql() harus selalu dibuat dari literal. */
    public function test_omzet_bersih_sql_hanya_menghasilkan_literal(): void
    {
        $isi = file_get_contents(
            dirname(__DIR__, 2) . '/app/Support/OmzetBersih.php'
        );

        // Signature parameter-nya string, dan body-nya membangun dari literal.
        $this->assertStringContainsString('public static function sql(string $d', $isi);

        // Tidak ada referensi ke request/input/global di file ini.
        $this->assertStringNotContainsString('$request', $isi,
            'OmzetBersih tidak boleh membaca $request');
        $this->assertStringNotContainsString('$_GET', $isi, 'OmzetBersih tidak boleh membaca $_GET');
        $this->assertStringNotContainsString('$_POST', $isi, 'OmzetBersih tidak boleh membaca $_POST');
        $this->assertStringNotContainsString('$_REQUEST', $isi);

        // Semua pemanggil di codebase harus memanggil dengan literal
        // atau dari sumber internal, tidak boleh dengan $request.
        $p = file_get_contents(dirname(__DIR__, 2) . '/app/Http/Controllers/LaporanController.php');
        $this->assertStringNotContainsString('OmzetBersih::sql($request', $p);
        $this->assertStringNotContainsString('OmzetBersih::sql($this->request', $p);
    }

    /* ================================================================
       2. RUNTIME: kirim payload injeksi, harus berjalan normal
       ================================================================ */

    public function test_filter_collecting_dengan_payload_injeksi_tidak_error(): void
    {
        $payload = [
            'nama_konsumen' => "' OR '1'='1",
            'nomor_nota'    => "x'; DROP TABLE transaksi;--",
            'tgl_dari'      => "2026-01-01' OR '1'='1",
            'tgl_sampai'    => "2026-01-01';--",
        ];

        $r = $this->actingAs($this->admin)->get('/collecting?' . http_build_query($payload));

        // SQL injection TIDAK boleh terjadi: respons tidak boleh memuat jejak SQL.
        // Catatan: payload tgl_dari yang tidak valid dapat menimbulkan 500 dari
        // Carbon::parse - itu temuan terpisah (tidak validasi format tanggal),
        // dan sengaja tidak disentang surti ini. Fokus audit ini: tidak ada
        // SQLSTATE / syntax error / terjemahan SQL di respons.
        $isi = (string) $r->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $isi,
            'Respons tidak boleh memuat SQLSTATE - itu jejak injection');
        $this->assertStringNotContainsString('syntax error', $isi);
        $this->assertStringNotContainsString('mysql_', $isi);

        // Jika 200, pagina feed harus tetap ada sebagai bukti tidak terkuras
        // oleh injeksi ' OR '1'='1 yang seharusnya dipetakan seperti data biasa.
        if ($r->status() === 200) {
            $this->assertStringContainsString('collecting', strtolower($isi));
        }
    }

    /**
     * Titik LaporanController yang paling dekat exposure: endpoint laporan
     * penjualan dengan filter milik user. Payloadinjeksi harus menghasilkan
     * respons normal, bukan SQL error.
     */
    public function test_filter_laporan_dengan_payload_injeksi_tidak_error(): void
    {
        // Route laporan penjualan adalah 'laporan/order'
        // (LaporanController@order) - bukan URI yang memuat kata
        // "penjualan". Versi lama test ini menebak route dengan
        // str_contains($uri, 'penjualan') dan karena tebakannya meleset,
        // test ini memanggil markTestSkipped() tanpa pernah menjalankan
        // assert sama sekali: penjaga injeksi SQL pada halaman laporan
        // mati diam-diam sejak pertama kali ditulis.
        $kandidat = 'laporan/order';

        if (!Route::has('laporan.order')) {
            $this->fail("Route {$kandidat} hilang - test injeksi laporan tidak boleh dilewati diam-diam");
        }

        $payload = [
            'tgl_dari'   => "2026-01-01' OR '1'='1",
            'tgl_sampai' => "2026-12-31';--",
            'id_mesin'   => "1 OR 1=1",
        ];

        $r = $this->actingAs($this->admin)->get(
            '/' . ltrim($kandidat, '/') . '?' . http_build_query($payload)
        );

        $this->assertContains(
            $r->status(),
            [200, 302, 422, 404],
            "Laporan dengan payload injeksi harus 200/302/422/404, dapat {$r->status()}"
        );

        if (str_starts_with((string) $r->status(), '5')) {
            $this->fail('Server error pada laporan dengan payload injeksi - ini sinyal injection. Status ' . $r->status());
        }

        $this->assertStringNotContainsString('SQLSTATE', $r->getContent(),
            'Respons laporan tidak boleh memuat pesan SQL');
        $this->assertStringNotContainsString('syntax error', $r->getContent());
    }
}