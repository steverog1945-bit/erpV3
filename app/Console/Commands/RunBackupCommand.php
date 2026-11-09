<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class RunBackupCommand extends Command
{
    protected $signature = 'backup:run {tipe=daily : daily|weekly|monthly|manual}';

    protected $description = 'Jalankan backup database (mysqldump) dan terapkan retensi sesuai tipe';

    public function handle(): int
    {
        $tipe = $this->argument('tipe');

        if (!in_array($tipe, ['daily', 'weekly', 'monthly', 'manual'], true)) {
            $this->error("Tipe tidak valid: {$tipe}. Pakai daily|weekly|monthly|manual.");
            return self::FAILURE;
        }

        try {
            $log = BackupService::run($tipe);
            $this->info("Backup {$tipe} berhasil: {$log->file_path} (" . angka($log->ukuran_bytes / 1024, 1) . " KB)");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Backup {$tipe} gagal: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
