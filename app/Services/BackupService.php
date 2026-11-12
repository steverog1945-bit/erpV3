<?php

namespace App\Services;

use App\Models\BackupLog;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Backup & Recovery (roadmap LEVEL 1 - WAJIB, "wajib sebelum production").
 * Menjalankan mysqldump ke storage/app/backups/{tipe}/ (di luar public/,
 * tidak pernah bisa diakses lewat web - lihat catatan security di audit)
 * lalu menerapkan retensi (hapus file lama di luar batas config('backup.
 * retention')). Setiap eksekusi dicatat ke backup_logs baik sukses maupun
 * gagal, supaya halaman /backup selalu punya riwayat yang bisa ditelusuri.
 */
class BackupService
{
    /**
     * Verifikasi backup terakhir per tipe (daily/weekly/monthly/manual):
     * file ada, ukuran wajar & cocok dengan log, dan dump benar-benar
     * berisi struktur tabel inti. Ini BUKAN restore penuh - hanya tanda
     * bahwa backup tidak kosong/rusak (restore butuh ambiente darurat
     * terpisah). Kembalikan ringkasan per tipe untuk UI & CLI.
     */
    public static function verifikasiTerbaru(?string $hanyaTipe = null): array
    {
        $hasil = [];
        $tipeList = $hanyaTipe ? [$hanyaTipe] : ['daily', 'weekly', 'monthly', 'manual'];
        foreach ($tipeList as $tipe) {
            $log = BackupLog::where('tipe', $tipe)->where('status', 'Sukses')->orderByDesc('started_at')->first();
            if (!$log) {
                $hasil[] = ['tipe' => $tipe, 'label' => ucfirst($tipe), 'status' => 'belum-ada', 'ok' => false, 'pesan' => 'Belum ada backup sukses untuk tipe ini.', 'cek' => []];
                continue;
            }
            $hasil[] = array_merge(['tipe' => $tipe, 'label' => ucfirst($tipe), 'status' => 'dicek'], self::verifikasi($log));
        }

        return $hasil;
    }

    /** Verifikasi satu log backup. @return array{ok:bool, pesan:string, cek:array<string,bool>, file?:string, size?:int} */
    public static function verifikasi(BackupLog $log): array
    {
        $cek = [];
        $file = (string) ($log->file_path ?? '');
        $cek['File backup tercatat'] = $file !== '' && is_file($file);
        if (!$cek['File backup tercatat']) {
            return ['ok' => false, 'pesan' => 'File backup tidak ditemukan di disk.', 'cek' => $cek, 'file' => $file];
        }

        $size = (int) filesize($file);
        $cek['Ukuran file wajar (>1 KB)'] = $size > 1024;
        if ($log->ukuran_bytes) {
            $logSize = (int) $log->ukuran_bytes;
            $cek['Ukuran cocok dengan log'] = abs($size - $logSize) <= max(1024, (int) round($logSize * 0.05));
        }

        // mysqldump menulis struktur dulu: cukup baca 512 KB pertama.
        $head = @file_get_contents($file, false, null, 0, 524288);
        $cek['Berisi CREATE TABLE'] = $head !== false && stripos($head, 'CREATE TABLE') !== false;
        foreach (['transaksi', 'bahan_baku', 'users'] as $tabel) {
            $cek['Berisi tabel `' . $tabel . '`'] = $head !== false && stripos($head, '`' . $tabel . '`') !== false;
        }

        $ok = !in_array(false, $cek, true);

        return ['ok' => $ok, 'pesan' => $ok ? 'Backup terlihat utuh & bisa dibaca.' : 'Isi backup TIDAK lengkap - buat backup baru sekarang.', 'cek' => $cek, 'file' => $file, 'size' => $size];
    }

    public static function run(string $tipe): BackupLog
    {
        $startedAt = now();
        // P1: status awal 'Berjalan' - sebelumnya langsung 'Sukses' padahal
        // dump belum jalan; proses mati mendadak meninggalkan baris
        // "Sukses" kosong (false positive di halaman /backup). Kolom enum
        // diperluas 'Berjalan' lewat migrasi 2026_10_18_000001.
        $log = BackupLog::create(['tipe' => $tipe, 'status' => 'Berjalan', 'started_at' => $startedAt]);

        try {
            $dir = config('backup.disk_path') . DIRECTORY_SEPARATOR . $tipe;
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Dua cadangan di detik yang sama tidak boleh saling menimpa: beri akhiran _2, _3, ...
            $dasar = "backup_{$tipe}_" . $startedAt->format('Ymd_His');
            $filename = $dasar . '.sql';
            for ($n = 2; file_exists($dir . DIRECTORY_SEPARATOR . $filename); $n++) {
                $filename = $dasar . '_' . $n . '.sql';
            }
            $filePath = $dir . DIRECTORY_SEPARATOR . $filename;

            $dump = self::dump();
            file_put_contents($filePath, $dump);
            $size = filesize($filePath);

            self::terapkanRetensi($dir, $tipe);

            [$offsiteStatus, $offsiteError] = self::salinKeLokasiKedua($dump, $tipe, $filename);

            $log->update([
                'file_path' => $filePath,
                'ukuran_bytes' => $size,
                'status' => 'Sukses',
                'offsite_status' => $offsiteStatus,
                'offsite_error' => $offsiteError,
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'Gagal',
                'pesan_error' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            throw $e;
        }

        return $log->fresh();
    }

    private static function dump(): string
    {
        $db = config('database.connections.mysql');

        $command = [
            config('backup.mysqldump_path'),
            '--host=' . $db['host'],
            '--port=' . $db['port'],
            '--user=' . $db['username'],
            '--single-transaction',
            '--routines',
            '--triggers',
            $db['database'],
        ];

        // Password lewat env var MYSQL_PWD (bukan --password= di argumen
        // command) supaya tidak muncul di daftar proses OS - hanya
        // ditambahkan kalau memang ada password (XAMPP root lokal umumnya
        // tanpa password).
        $env = $db['password'] !== '' ? ['MYSQL_PWD' => $db['password']] : null;

        $process = new Process($command, null, $env);
        $process->setTimeout(300);
        $process->mustRun();

        return $process->getOutput();
    }

    /**
     * Salin dump ke disk KEDUA yang terpisah dari server ini (lihat catatan
     * di config/backup.php). Kegagalan di sini SENGAJA tidak melempar
     * exception - backup lokal yang sudah berhasil tidak boleh ikut
     * ditandai gagal gara-gara lokasi kedua bermasalah (mis. koneksi
     * jaringan ke S3 sedang putus); statusnya dicatat terpisah supaya tetap
     * terlihat & bisa memicu notifikasi (lihat NotifikasiService).
     *
     * @return array{0: ?string, 1: ?string} [offsite_status, offsite_error]
     */
    private static function salinKeLokasiKedua(string $dump, string $tipe, string $filename): array
    {
        $disk = config('backup.secondary_disk');
        if (!$disk) {
            return [null, null];
        }

        try {
            Storage::disk($disk)->put($tipe . '/' . $filename, $dump);

            // Retensi yang sama dengan salinan lokal, supaya lokasi kedua tidak tumbuh tanpa batas.
            $maks = (int) config("backup.retention.{$tipe}", 7);
            $berkas = collect(Storage::disk($disk)->files($tipe))
                ->filter(fn ($f) => str_ends_with($f, '.sql'))
                ->sortByDesc(fn ($f) => Storage::disk($disk)->lastModified($f))
                ->values();
            foreach ($berkas->slice($maks) as $lama) {
                Storage::disk($disk)->delete($lama);
            }

            return ['Sukses', null];
        } catch (\Throwable $e) {
            return ['Gagal', $e->getMessage()];
        }
    }

    private static function terapkanRetensi(string $dir, string $tipe): void
    {
        $maxSimpan = config("backup.retention.{$tipe}", 7);

        $files = collect(glob($dir . DIRECTORY_SEPARATOR . '*.sql'))
            // Urut waktu ubah lalu nama (berisi cap waktu), supaya berkas di detik yang sama tetap urut benar.
            ->sortByDesc(fn (string $f) => sprintf('%012d', filemtime($f)) . basename($f))
            ->values();

        foreach ($files->slice($maxSimpan) as $fileLama) {
            @unlink($fileLama);
        }
    }
}
