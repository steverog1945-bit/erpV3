<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * CATATAN: TIDAK ada override $baseUrl di sini, dan itu disengaja.
     *
     * Pada Laravel versi yang dipakai proyek ini, test client menyusun URL
     * request lewat MakesHttpRequests::prepareUrlForRequest() yang memanggil
     * url($uri) - bukan $this->baseUrl. Override $baseUrl karena itu tidak
     * berpengaruh apa pun.
     *
     * Yang berbahaya justru sebaliknya: memaksa root URL lewat
     * URL::forceRootUrl() (pernah ada di AppServiceProvider::boot()) membuat
     * setiap request test dijalankan sebagai
     * "http://localhost/grafikaorion/public/", sehingga path info-nya
     * "/grafikaorion/public/" - dan SEMUA test yang memanggil route apa pun
     * berakhir 404.
     *
     * Instalasi subdirektori memang sudah ditangani Laravel sendiri lewat
     * request()->root() yang membaca SCRIPT_NAME Apache.
     */

    /**
     * Nama database yang TIDAK BOLEH PERNAH disentuh oleh test, apa pun konfigurasi.
     *
     * Pengecualian ini sengaja memakai pencocokan substring normalized agar
     * nama seperti 'oriondb', 'oriondb_backup', atau 'OrionDB_2026' tetap
     * tertangkap. Guard sebelumnya hanya cek `str_contains($db,'testing')`,
     * sehingga database bernama 'oriondb_testing_prod' akan lolos.
     */
    private const FORBIDDEN_DB_FRAGMENTS = ['oriondb'];

    public function createApplication()
    {
        $app = parent::createApplication();

        $config = $app['config'];
        $connection = $config->get('database.default');
        $db = (string) $config->get("database.connections.{$connection}.database");

        $this->guardAgainstProductionDatabase($db);

        return $app;
    }

    /**
     * Berhenti sebelum satu query pun dijalankan bila target database tidak aman.
     *
     * Aturan:
     *  - nama wajib mengandung 'testing' (ATAU :memory:)
     *  - nama wajib TIDAK mengandung salah satu fragmen terlarang
     *  - nama wajib bukan kosong
     */
    private function guardAgainstProductionDatabase(string $db): void
    {
        if ($db === ':memory:') {
            return;
        }

        if (trim($db) === '') {
            $this->bail('nama database kosong');
        }

        $normalized = mb_strtolower($db);

        foreach (self::FORBIDDEN_DB_FRAGMENTS as $forbidden) {
            if (str_contains($normalized, $forbidden)) {
                $this->bail(
                    "nama database '{$db}' mengandung '{$forbidden}' "
                    . "- ini database operasional dan DILARANG disentuh oleh test"
                );
            }
        }

        if (!str_contains($normalized, 'testing')) {
            $this->bail(
                "nama database '{$db}' tidak mengandung 'testing'. "
                . 'Kemungkinan konfigurasi ter-cache. Jalankan: php artisan config:clear'
            );
        }
    }

    /**
     * Hentikan seluruh proses test dengan pesan yang bisa ditindaklanjuti.
     */
    private function bail(string $reason): void
    {
        fwrite(STDERR, "\n");
        fwrite(STDERR, "=========================================================\n");
        fwrite(STDERR, " TEST DIBATALKAN - KEAMANAN DATABASE\n");
        fwrite(STDERR, "---------------------------------------------------------\n");
        fwrite(STDERR, " {$reason}\n");
        fwrite(STDERR, "=========================================================\n\n");
        exit(1);
    }
}
