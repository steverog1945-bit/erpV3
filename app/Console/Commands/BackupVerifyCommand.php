<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

/**
 * Verifikasi backup terakhir per tipe: file ada, ukuran wajar & cocok
 * dengan log, dan dump berisi struktur tabel inti (transaksi, bahan_baku,
 * users). BUKAN restore penuh - hanya bukti backup tidak kosong/rusak.
 */
class BackupVerifyCommand extends Command
{
    protected $signature = 'backup:verify {--tipe=daily}';

    protected $description = 'Verifikasi backup terakhir per tipe (daily|weekly|monthly|manual)';

    public function handle(): int
    {
        $tipe = $this->option('tipe');
        if (!in_array($tipe, ['daily', 'weekly', 'monthly', 'manual', 'semua'], true)) {
            $this->error("Tipe tidak valid: {$tipe}. Pakai daily|weekly|monthly|manual|semua.");
            return self::FAILURE;
        }

        $hasil = BackupService::verifikasiTerbaru($tipe === 'semua' ? null : $tipe);
        $gagal = 0;
        foreach ($hasil as $h) {
            $ok = (bool) ($h['ok'] ?? false);
            $baris = ($ok ? 'LULUS' : 'GAGAL') . ' [' . $h['tipe'] . '] ' . $h['pesan'];
            if ($ok) {
                $this->info($baris);
            } else {
                $this->error($baris);
                $gagal++;
            }
            foreach ((array) ($h['cek'] ?? []) as $label => $lulus) {
                $this->line('   - ' . $label . ': ' . ($lulus ? 'ok' : 'GAGAL'));
            }
        }

        return $gagal === 0 ? self::SUCCESS : self::FAILURE;
    }
}
