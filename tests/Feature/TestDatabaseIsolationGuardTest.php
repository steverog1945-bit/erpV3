<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * CHECKPOINT 4D.2 — Penjaga isolatedasi database uji.
 *
 * Test ini MEMERIKSA KODE TEST SENDIRI, bukan database. Tujuannya mencegah
 * insiden seperti di checkpoint 4D.1, ketika sebuah test memakai DROP TABLE
 * pada database uji bersama sementara full-suite lain sedang berjalan.
 *
 * DETEKSI DIPERHALUS (v2): pola harus muncul DI DALAM KONTEKS EKSEKUSI SQL,
 * bukan sekadar kata "TRUNCATE" atau "DROP TABLE" muncul di mana saja.
 * Ini penting karena:
 *   - "truncated" (kata bahasa Inggris) cocok dengan pola TRUNCATE;
 *   - payload uji injeksi SQL sengaja memuat "DROP TABLE transaksi" sebagai
 *     STRING, bukan sebagai perintah yang dijalankan;
 *   - komentar dokumentasi boleh menyebut istilah teknis.
 */
class TestDatabaseIsolationGuardTest extends TestCase
{
    /** Konteks pemanggilan yang benar-benar mengeksekusi SQL mentah. */
    private const EXECUTION_CONTEXTS = [
        'DB::statement',
        'DB::unprepared',
        'DB::raw',
        'DB::select',
        'Schema::drop',
        'Schema::truncate',
        '->statement(',
        '->unprepared(',
    ];

    /** Pola SQL destruktif. Dicocokkan sebagai kata utuh. */
    private const DESTRUCTIVE_PATTERNS = [
        'DROP\s+TABLE',
        'TRUNCATE',
        'DROP\s+DATABASE',
    ];

    /** @test */
    public function tidak_ada_sql_destruktif_yang_dieksekusi_di_suite_test()
    {
        $violations = [];

        foreach ($this->testFiles() as $file) {
            $code = $this->stripComments((string) file_get_contents($file));

            foreach (self::EXECUTION_CONTEXTS as $context) {
                foreach ($this->calls($code, $context) as $call) {
                    foreach (self::DESTRUCTIVE_PATTERNS as $pattern) {
                        if (preg_match('~\b' . $pattern . '\b~i', $call)) {
                            $violations[] = sprintf(
                                '%s → %s() memuat "%s"',
                                $this->relative($file),
                                $context,
                                $pattern
                            );
                        }
                    }
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($violations)),
            "SQL destruktif dieksekusi di suite test:\n  - " . implode("\n  - ", array_unique($violations))
            . "\n\nTest destruktif tidak boleh jalan di database uji bersama. "
            . 'Pindahkan ke database sekali pakai, atau pakai pendekatan non-destruktif '
            . '(lihat DB::disconnect di LandingPageHomepageResolverTest).'
        );
    }

    /** @test */
    public function payload_injeksi_sql_bukan_pelanggaran()
    {
        // RawSqlAuditTest sengaja memakai string "x'; DROP TABLE transaksi;--"
        // sebagai payload uji. Itu STRING, bukan perintah, jadi harus lolos.
        $file = base_path('tests/Feature/RawSqlAuditTest.php');
        $this->assertFileExists($file);

        $code = $this->stripComments((string) file_get_contents($file));
        $this->assertStringContainsString(
            'DROP TABLE transaksi',
            $code,
            'Payload uji injeksi harus masih ada (uji ini memverifikasi guard tidak salahPositive)'
        );

        // Tapi tidak boleh ada DROP TABLE dalam konteks eksekusi.
        $found = false;
        foreach ($this->calls($code, 'DB::statement') as $call) {
            if (preg_match('~\bDROP\s+TABLE\b~i', $call)) {
                $found = true;
            }
        }
        $this->assertFalse($found, 'Payload injeksi tidak boleh dieksekusi lewat DB::statement');
    }

    /** @test */
    public function kata_truncated_tidak_terdeteksi_sebagai_truncate()
    {
        // Konten ini adalah nama test version_looking_like_numbers_is_not_truncated.
        // Kalau pola tidak memakai word boundary, test ini akan salahPositive.
        $code = 'public function version_looking_like_numbers_is_not_truncated()';

        $found = false;
        foreach ($this->calls($code, 'DB::statement') as $call) {
            if (preg_match('~\bTRUNCATE\b~i', $call)) {
                $found = true;
            }
        }

        $this->assertFalse($found, '"truncated" tidak boleh terdeteksi sebagai SQL TRUNCATE');
    }

    /** @test */
    public function phpunit_xml_menunjuk_database_uji_bukan_operasional()
    {
        $xml = (string) file_get_contents(base_path('phpunit.xml'));

        $this->assertStringContainsString(
            'percetakan_erp_testing',
            $xml,
            'phpunit.xml harus mengarahkan test ke database percetakan_erp_testing'
        );

        $this->assertStringNotContainsString(
            'value="oriondb"',
            $xml,
            'phpunit.xml tidak boleh menunjuk database operasional oriondb'
        );
    }

    /** @test */
    public function guard_testcase_menolak_database_operasional()
    {
        $source = (string) file_get_contents(base_path('tests/TestCase.php'));

        $this->assertStringContainsString('FORBIDDEN_DB_FRAGMENTS', $source);
        $this->assertStringContainsString('oriondb', $source);

        // Kasus yang lolos dari guard lama: nama mengandung 'testing' TAPI
        // juga mengandung 'oriondb'.
        foreach (['oriondb', 'oriondb_testing', 'OrionDB_2026', 'prod_oriondb'] as $name) {
            $normalized = mb_strtolower($name);
            $this->assertTrue(
                str_contains($normalized, 'oriondb'),
                "Nama database '{$name}' seharusnya tertangkap guard"
            );
        }

        // Nama yang aman tetap harus diterima.
        foreach (['percetakan_erp_testing', 'orion_scratch_testing'] as $name) {
            $normalized = mb_strtolower($name);
            $this->assertFalse(
                str_contains($normalized, 'oriondb'),
                "Nama database '{$name}' seharusnya aman"
            );
            $this->assertTrue(
                str_contains($normalized, 'testing'),
                "Nama database '{$name}' harus mengandung 'testing'"
            );
        }
    }

    /** @test */
    public function test_tidak_menyebut_nama_database_operasional_di_kode()
    {
        // tests/TestCase.php dikecualikan: file itu ADALAH penjaganya, dan
        // harus menyebut 'oriondb' supaya bisa memblokirnya.
        $allowList = [base_path('tests/TestCase.php')];

        foreach ($this->testFiles() as $file) {
            if (in_array($file, $allowList, true)) {
                continue;
            }

            $code = $this->stripComments((string) file_get_contents($file));

            if (preg_match('~\boriondb\b~i', $code)) {
                $this->fail(
                    $this->relative($file) . ' menyebut "oriondb" di kode (di luar komentar). '
                    . 'Test tidak boleh menyebut nama database operasional.'
                );
            }
        }

        $this->assertTrue(true, 'Tidak ada file test yang menyebut oriondb di kode.');
    }

    // ============================================================
    // Helper
    // ============================================================

    /**
     * Ambil isi argumen dari setiap pemanggilan fungsi/metode tertentu.
     *
     * Pendekatan: ambil potongan kode mulai dari konteks yang dicari sampai
     * tanda kurung penutup atau batas baris berikutnya. Cukup untuk keperluan
     * deteksi, dan tahan terhadap string lokal yang tidak pernah dieksekusi.
     *
     * @return array<int, string>
     */
    private function calls(string $code, string $context): array
    {
        $out = [];

        $offset = 0;
        $len = strlen($code);

        while (($pos = strpos($code, $context, $offset)) !== false) {
            // Ambil potongan setelah konteks, maximal 300 karakter atau
            // sampai akhir baris berikutnya.
            $slice = substr($code, $pos, 300);
            $out[] = $slice;

            $offset = $pos + strlen($context);
            if ($offset > $len) {
                break;
            }
        }

        return $out;
    }

    private function stripComments(string $code): string
    {
        $code = preg_replace('~//[^\n]*~', '', $code);
        $code = preg_replace('~/\*.*?\*/~s', '', (string) $code);
        return (string) $code;
    }

    /**
     * @return array<int, string>
     */
    private function testFiles(): array
    {
        $files = [];

        foreach (['tests/Feature', 'tests/Unit'] as $dir) {
            $path = base_path($dir);
            if (!is_dir($path)) {
                continue;
            }
            foreach (glob($path . '/*.php') ?: [] as $file) {
                // File penjaga ini sendiri memuat pola sebagai konstanta,
                // jadi dikecualikan agar tidak menunjuk dirinya sendiri.
                if (basename($file) === basename(__FILE__)) {
                    continue;
                }
                $files[] = $file;
            }
        }

        $files[] = base_path('tests/TestCase.php');

        return $files;
    }

    private function relative(string $path): string
    {
        return str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
    }
}