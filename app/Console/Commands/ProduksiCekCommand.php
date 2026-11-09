<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Pemeriksaan kesiapan produksi (LAN, satu server). HANYA MEMBACA: tidak
 * mengubah berkas, konfigurasi, maupun data.
 *
 *   php artisan produksi:cek
 *
 * Kode keluar 1 bila ada temuan GAGAL, supaya bisa dipanggil dari skrip
 * pemantau. Setiap baris menyebut alasan dan langkah perbaikannya.
 */
class ProduksiCekCommand extends Command
{
    protected $signature = 'produksi:cek {--json : Keluaran JSON}';
    protected $description = 'Periksa kesiapan produksi: konfigurasi, backup, penjadwal, keamanan MySQL, ruang disk';

    /** @var array<int, array{0:string,1:string,2:string,3:string}> [status, area, temuan, perbaikan] */
    private array $hasil = [];

    public function handle(): int
    {
        $this->cekAplikasi();
        $this->cekBackup();
        $this->cekDatabase();
        $this->cekServer();

        if ($this->option('json')) {
            $this->line(json_encode(array_map(fn ($r) => ['status' => $r[0], 'area' => $r[1], 'temuan' => $r[2], 'perbaikan' => $r[3]], $this->hasil), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->table(['Status', 'Area', 'Temuan', 'Perbaikan'], array_map(fn ($r) => [$r[0], $r[1], $r[2], $r[3]], $this->hasil));
        }

        $gagal = count(array_filter($this->hasil, fn ($r) => $r[0] === 'GAGAL'));
        $waspada = count(array_filter($this->hasil, fn ($r) => $r[0] === 'WASPADA'));
        if (!$this->option('json')) {
            $this->line("Ringkasan: {$gagal} gagal, {$waspada} waspada, " . (count($this->hasil) - $gagal - $waspada) . ' sesuai.');
        }

        return $gagal > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function catat(string $status, string $area, string $temuan, string $perbaikan = '-'): void
    {
        $this->hasil[] = [$status, $area, $temuan, $perbaikan];
    }

    private function cekAplikasi(): void
    {
        $env = (string) config('app.env');
        $env === 'production'
            ? $this->catat('OK', 'Aplikasi', 'APP_ENV=production')
            : $this->catat('WASPADA', 'Aplikasi', "APP_ENV={$env}", 'Set APP_ENV=production di .env setelah data uji dibersihkan.');

        config('app.debug')
            ? $this->catat('GAGAL', 'Aplikasi', 'APP_DEBUG=true: detail galat tampil ke pengguna', 'Set APP_DEBUG=false di .env.')
            : $this->catat('OK', 'Aplikasi', 'APP_DEBUG=false');

        config('app.key')
            ? $this->catat('OK', 'Aplikasi', 'APP_KEY terisi')
            : $this->catat('GAGAL', 'Aplikasi', 'APP_KEY kosong', 'php artisan key:generate');

        $level = (string) config('logging.channels.' . config('logging.default') . '.level', env('LOG_LEVEL', 'debug'));
        $level === 'debug'
            ? $this->catat('WASPADA', 'Log', 'LOG_LEVEL=debug: log tumbuh cepat dan memuat detail query', 'Set LOG_LEVEL=warning untuk produksi.')
            : $this->catat('OK', 'Log', "LOG_LEVEL={$level}");

        $log = storage_path('logs/laravel.log');
        if (is_file($log) && filesize($log) > 100 * 1024 * 1024) {
            $this->catat('WASPADA', 'Log', 'laravel.log ' . round(filesize($log) / 1048576) . ' MB', 'Gunakan LOG_CHANNEL=daily agar berputar otomatis.');
        }

        file_exists(public_path('storage')) // junction Windows tidak dikenali is_dir/is_link di PHP
            ? $this->catat('OK', 'Berkas', 'public/storage tersedia')
            : $this->catat('GAGAL', 'Berkas', 'public/storage belum ada: gambar/berkas unggahan tidak tampil', 'php artisan storage:link');

        // Cache konfigurasi. Dua hal yang harus dicek di sini, karena
        // keduanya pernah jadi penyebab error 500 untuk pengguna:
        //   - cache yang menunjuk DATABASE UJI: aplikasi web lalu melayani
        //     permintaan ke database test, tempat tabelnya belum lengkap ->
        //     "table doesn't exist" / "unknown column" di semua halaman.
        //   - cache yang TIDAK PUNYA app.key: berarti berkasnya rusak atau
        //     terpotong -> "No application encryption key has been specified".
        $pathCache = base_path('bootstrap/cache/config.php');
        if (is_file($pathCache)) {
            $cache = @include $pathCache;
            $dbCache = (string) (is_array($cache) ? ($cache['database']['connections']['mysql']['database'] ?? '') : '');
            $keyCache = is_array($cache) ? (string) ($cache['app']['key'] ?? '') : '';

            if ($keyCache === '') {
                $this->catat('GAGAL', 'Aplikasi', 'Cache konfigurasi tidak punya app.key (berkas rusak/terpotong)', 'php artisan config:clear lalu php artisan app:config-cache');
            } elseif (preg_match('/testing|_test|_uji/i', $dbCache)) {
                $this->catat('GAGAL', 'Aplikasi', "Cache konfigurasi menunjuk database uji ({$dbCache})", 'php artisan config:clear, lalu bangun ulang dengan php artisan app:config-cache');
            } else {
                $this->catat('OK', 'Aplikasi', "Cache konfigurasi aman (database {$dbCache})");
            }
        } else {
            $this->catat('WASPADA', 'Performa', 'Konfigurasi belum di-cache', 'Di server produksi: php artisan app:config-cache. Di komputer development JANGAN di-cache - konfigurasi ter-cache mengalahkan phpunit.xml sehingga test bisa mengosongkan database asli.');
        }

        // Zona waktu: PHP (aplikasi) dan MySQL harus menunjuk jam yang sama.
        try {
            $db = DB::selectOne('select now() as n')->n;
            $selisih = abs(strtotime($db) - time());
            $selisih <= 120
                ? $this->catat('OK', 'Waktu', 'Jam aplikasi dan MySQL sama (zona ' . config('app.timezone') . ')')
                : $this->catat('GAGAL', 'Waktu', "Jam aplikasi dan MySQL berbeda {$selisih} detik", 'Samakan timezone: config app.timezone dan database.connections.mysql.timezone.');
        } catch (\Throwable $e) {
            $this->catat('GAGAL', 'Database', 'Tidak bisa terhubung ke database', 'Periksa DB_* di .env dan status MySQL.');
        }
    }

    private function cekBackup(): void
    {
        $dasar = (string) config('backup.disk_path');
        $terbaru = null;
        foreach (['daily', 'weekly', 'monthly', 'manual'] as $tipe) {
            foreach (File::glob($dasar . DIRECTORY_SEPARATOR . $tipe . DIRECTORY_SEPARATOR . '*.sql') as $f) {
                $terbaru = max($terbaru ?? 0, filemtime($f));
            }
        }

        if ($terbaru === null) {
            $this->catat('GAGAL', 'Backup', 'Belum ada berkas backup sama sekali', 'php artisan backup:run daily lalu pasang penjadwal (scripts/pasang-penjadwal.ps1).');
        } else {
            $umurJam = (time() - $terbaru) / 3600;
            $umurJam <= 26
                ? $this->catat('OK', 'Backup', 'Backup terakhir ' . round($umurJam, 1) . ' jam lalu')
                : $this->catat('GAGAL', 'Backup', 'Backup terakhir ' . round($umurJam / 24, 1) . ' hari lalu: penjadwal tidak berjalan', 'Pasang Task Scheduler: scripts/pasang-penjadwal.ps1 (jalankan sebagai Administrator).');
        }

        config('backup.secondary_disk')
            ? $this->catat('OK', 'Backup', 'Salinan kedua (di luar server) dikonfigurasi')
            : $this->catat('WASPADA', 'Backup', 'Tidak ada salinan di luar server: disk rusak = data dan backup hilang bersama', 'Salin storage/app/backups ke disk/komputer lain (BACKUP_SECONDARY_DISK atau tugas salin terjadwal).');

        $mysqldump = (string) config('backup.mysqldump_path');
        ($mysqldump !== 'mysqldump' && is_file($mysqldump)) || $this->ada('mysqldump')
            ? $this->catat('OK', 'Backup', 'mysqldump ditemukan')
            : $this->catat('GAGAL', 'Backup', 'mysqldump tidak ditemukan: backup akan gagal', 'Set MYSQLDUMP_PATH=C:/xampp/mysql/bin/mysqldump.exe di .env.');
    }

    private function cekDatabase(): void
    {
        try {
            $bind = collect(DB::select("show variables like 'bind_address'"))->first()->Value ?? null;
            in_array((string) $bind, ['', '*', '0.0.0.0', '::'], true)
                ? $this->catat('WASPADA', 'Database', 'MySQL mendengarkan semua antarmuka (bind_address ' . ($bind === '' ? 'tidak diset' : $bind) . ')', 'Tambah bind-address=127.0.0.1 di my.ini; klien LAN tidak perlu akses langsung ke MySQL.')
                : $this->catat('OK', 'Database', 'MySQL hanya mendengarkan ' . ($bind ?? 'localhost'));
        } catch (\Throwable $e) {
            // izin SHOW VARIABLES tidak tersedia: lewati.
        }

        $user = (string) config('database.connections.mysql.username');
        $sandi = (string) config('database.connections.mysql.password');
        ($user === 'root' && $sandi === '')
            ? $this->catat('WASPADA', 'Database', 'Aplikasi memakai akun root tanpa password', 'Bila data sudah asli: buat akun khusus aplikasi dengan hak terbatas dan beri password.')
            : $this->catat('OK', 'Database', 'Akun database aplikasi: ' . $user);

        try {
            $tertunda = collect(app('migrator')->getMigrationFiles(database_path('migrations')))
                ->keys()->diff(app('migration.repository')->getRan())->count();
            $tertunda === 0
                ? $this->catat('OK', 'Database', 'Tidak ada migrasi tertunda')
                : $this->catat('GAGAL', 'Database', "{$tertunda} migrasi belum dijalankan", 'Cadangkan dulu, lalu php artisan migrate.');
        } catch (\Throwable $e) {
        }

        try {
            $fk = (int) DB::selectOne('select count(*) as c from information_schema.referential_constraints where constraint_schema = database()')->c;
            $fk >= 100
                ? $this->catat('OK', 'Database', "{$fk} foreign key terpasang")
                : $this->catat('WASPADA', 'Database', "Hanya {$fk} foreign key (desain menargetkan sekitar 115): integritas relasi lemah", 'Jalankan migrasi korektif restore_missing_foreign_keys.');
        } catch (\Throwable $e) {
        }
    }

    private function cekServer(): void
    {
        $bebas = @disk_free_space(storage_path());
        if ($bebas !== false) {
            $gb = $bebas / 1073741824;
            $gb < 5
                ? $this->catat('GAGAL', 'Server', 'Ruang disk tersisa ' . round($gb, 1) . ' GB', 'Kosongkan disk; backup dan log butuh ruang.')
                : $this->catat('OK', 'Server', 'Ruang disk tersisa ' . round($gb) . ' GB');
        }

        ini_get('display_errors') && !in_array(strtolower((string) ini_get('display_errors')), ['0', 'off', 'stderr'], true)
            ? $this->catat('WASPADA', 'Server', 'php.ini display_errors aktif (versi CLI mungkin berbeda dari Apache)', 'Set display_errors=Off pada php.ini yang dipakai Apache.')
            : $this->catat('OK', 'Server', 'display_errors nonaktif');

        function_exists('opcache_get_status') && ini_get('opcache.enable')
            ? $this->catat('OK', 'Server', 'OPcache aktif')
            : $this->catat('WASPADA', 'Server', 'OPcache nonaktif: halaman lebih lambat', 'Aktifkan zend_extension=opcache di php.ini.');
    }

    private function ada(string $perintah): bool
    {
        $cek = strncasecmp(PHP_OS, 'WIN', 3) === 0 ? "where {$perintah} 2>NUL" : "command -v {$perintah} 2>/dev/null";

        return trim((string) @shell_exec($cek)) !== '';
    }
}
