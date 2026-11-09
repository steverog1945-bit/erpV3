<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel as KernelContract;
use Illuminate\Support\Facades\File;

/**
 * Membangun cache konfigurasi dengan cara yang AMAN untuk aplikasi yang
 * sedang dipakai.
 *
 * MASALAH YANG DISELESAIKAN
 * -------------------------
 * `php artisan config:cache` milik Laravel menulis berkas cache konfigurasi
 * dengan File::put() - TIDAK atomik:
 *
 * 1. berkas sempat terisi separuh / kosong sesaat. Permintaan web yang
 *    kebetulan membaca berkas pada saat itu menerima konfigurasi tanpa
 *    app.key, dan hasilnya error 500 "No application encryption key has
 *    been specified" - seluruh halaman mati, bukan cuma satu fitur.
 * 2. Konfigurasi yang tersimpan bisa diambil dari environment proses yang
 *    tidak bersih. Contohnya: setelah `php artisan test`, variabel
 *    DB_DATABASE=percetakan_erp_testing dari phpunit.xml masih ada di
 *    proses, dan berkas cache bisa capturing nilai itu. Aplikasi web lalu
 *    melayani permintaan ke DATABASE UJI - dan setiap tabel yang belum
 *    ada di sana memunculkan error 500 ("table doesn't exist", "unknown
 *    column") untuk pengguna.
 *
 * CARA KERJA PERINTAH INI
 * ----------------------
 * 1. Membersihkan variabel environment yang bisa diwarisi dari proses lain,
 *    sehingga .env menjadi satu-satunya sumber kebenaran.
 * 2. Membangun konfigurasi dari aplikasi yang baru di-boot.
 * 3. MENOLAK kalau konfigurasi itu tidak aman: app.key kosong, app.env
 *    "testing", atau nama database menunjuk database uji. Cache lama
 *    dibiarkan utuh - lebih baik cache lama daripada aplikasi yang salah
 *    menunjuk database.
 * 4. Menulis berkas secara ATOMIK: tulis ke berkas sementara, lalu rename
 *    (rename menimpa di Windows bila berkas target bisa ditulis). Permintaan
 *    web hanya akan melihat berkas lama yang utuh, atau berkas baru yang
 *    utuh - tidak pernah separuh.
 * 5. Membaca ulang berkasnya untuk memastikan app.key benar-benar ada.
 *
 * CATATAN PENTING UNTUK KOMPUTER PENGEMBANGAN
 * -------------------------------------------
 * Di komputer yang juga dipakai menjalankan test otomatis, perintah ini
 * sebaiknya TIDAK dipakai: konfigurasi ter-cache mengalahkan phpunit.xml,
 * sehingga test bisa berjalan di database asli dan mengosongkannya
 * (lihat docs/AUDIT_TOTAL.md). Perintah ini dipakai di server produksi.
 */
class ConfigCacheAmanCommand extends Command
{
    protected $signature = 'app:config-cache
                            {--force : Tetap tulis meski konfigurasi mencurigakan (hanya untuk diagnosis)}';

    protected $description = 'Bangun cache konfigurasi secara atomik & menolak konfigurasi yang menunjuk database uji';

    /** Pola nama database uji - kalau konfigurasi cache menunjuk salah satu, aplikasi web akan salah. */
    private const POLA_DATABASE_UJI = ['testing', '_test', '_uji', 'test_'];

    public function handle(): int
    {
        $this->components->info('Membangun cache konfigurasi (mode aman)...');

        // 1. Buang environment yang diwarisi dari proses lain. Tanpa ini,
        //    variabel yang diset PHPUnit (DB_DATABASE ke database uji)
        //    bisa ikut ter-cache dan aplikasi web ikut mengarah ke sana.
        $this->bersihkanEnvironment();

        // 2. Bangun konfigurasi dari aplikasi yang baru di-boot.
        $config = $this->konfigurasiBaru();

        // 3. Tolak konfigurasi yang tidak aman.
        if (!$this->periksa($config) && !$this->option('force')) {
            $this->components->error('Cache konfigurasi TIDAK ditulis. Cache lama (kalau ada) dibiarkan utuh.');
            $this->components->warn('Perbaiki .env, lalu jalankan ulang perintah ini.');

            return self::FAILURE;
        }

        // 4. Tulis atomik.
        $path = $this->laravel->getCachedConfigPath();
        $sementara = $path . '.tmp';
        File::ensureDirectoryExists(dirname($path));
        File::put($sementara, '<?php return ' . var_export($config, true) . ';' . PHP_EOL);

        // rename() menimpa berkas target di Windows (bila target bisa ditulis)
        // dan di POSIX. Berkas lama tidak pernah terlihat dalam keadaan
        // separuh - inilah inti perbaikannya.
        if (!@rename($sementara, $path)) {
            @unlink($sementara);
            $this->components->error("Gagal menulis " . $path);

            return self::FAILURE;
        }

        // 5. Verifikasi hasil tulisnya.
        $tersimpan = @include $path;
        if (!is_array($tersimpan) || empty($tersimpan['app']['key'])) {
            $this->components->error('Verifikasi gagal: app.key tidak ada di berkas cache yang baru ditulis.');

            return self::FAILURE;
        }

        $this->components->info('Cache konfigurasi tersimpan: ' . $path);
        $this->components->twoColumnDetail('app.env', (string) $tersimpan['app']['env']);
        $this->components->twoColumnDetail('app.key', 'terisi (' . strlen((string) $tersimpan['app']['key']) . ' karakter)');
        $this->components->twoColumnDetail('database', (string) ($tersimpan['database']['connections']['mysql']['database'] ?? '-'));

        return self::SUCCESS;
    }

    /** Buang variabel environment yang bisa diwarisi dari proses lain. */
    private function bersihkanEnvironment(): void
    {
        $kunci = [
            'APP_ENV', 'APP_KEY', 'APP_DEBUG',
            'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
        ];

        foreach ($kunci as $kunciEnv) {
            putenv($kunciEnv);
            unset($_ENV[$kunciEnv], $_SERVER[$kunciEnv]);
        }
    }

    /** Konfigurasi dari aplikasi yang baru di-boot (setelah environment dibersihkan). */
    private function konfigurasiBaru(): array
    {
        $app = require $this->laravel->bootstrapPath('app.php');
        $app->useStoragePath($this->laravel->storagePath());
        $app->make(KernelContract::class)->bootstrap();

        return $app['config']->all();
    }

    /** Periksa konfigurasi sebelum ditulis. True = aman. */
    private function periksa(array $config): bool
    {
        $aman = true;

        $key = (string) ($config['app']['key'] ?? '');
        if ($key === '') {
            $this->components->error('app.key kosong - aplikasi akan error 500 di semua halaman ("No application encryption key").');
            $aman = false;
        }

        $env = (string) ($config['app']['env'] ?? '');
        if ($env === 'testing') {
            $this->components->error('app.env=testing - konfigurasi produksi tidak boleh memakai environment test.');
            $aman = false;
        }

        if (!$this->option('force')) {
            $db = (string) ($config['database']['connections']['mysql']['database'] ?? '');
            $lower = strtolower($db);
            foreach (self::POLA_DATABASE_UJI as $pola) {
                if ($pola !== '' && str_contains($lower, $pola)) {
                    $this->components->error("Database menunjuk database uji ({$db}) - aplikasi web akan melayani permintaan ke sana dan memunculkan error 500.");
                    $aman = false;
                    break;
                }
            }
        }

        return $aman;
    }
}
