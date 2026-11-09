<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Perawatan data harian (dijadwalkan 03:00). Hanya menyentuh data teknis dan arsip; data transaksi,
 * jurnal, stok, dan pembayaran tidak pernah dihapus.
 *  - cache kedaluwarsa dan sesi yang sudah lewat masa berlakunya dihapus;
 *  - log audit lebih tua dari AUDIT_ARSIP_BULAN (bawaan 24) DIPINDAH ke audit_logs_arsip, bukan dihapus;
 *  - riwayat hasil backup (backup_logs) lebih tua dari 1 tahun dihapus (berkas backup punya retensi sendiri).
 */
class RapikanDataCommand extends Command
{
    protected $signature = 'data:rapikan {--pratinjau : Hanya hitung, tidak mengubah apa pun}';

    protected $description = 'Hapus cache/sesi kedaluwarsa, pindahkan log audit lama ke arsip';

    public function handle(): int
    {
        $pratinjau = (bool) $this->option('pratinjau');
        $hasil = [];

        if (Schema::hasTable('cache')) {
            $q = DB::table('cache')->where('expiration', '<', time());
            $hasil[] = ['Cache kedaluwarsa', $pratinjau ? $q->count() : $q->delete()];
        }

        if (Schema::hasTable('sessions')) {
            $batas = time() - ((int) config('session.lifetime', 120) * 60);
            $q = DB::table('sessions')->where('last_activity', '<', $batas);
            $hasil[] = ['Sesi kedaluwarsa', $pratinjau ? $q->count() : $q->delete()];
        }

        $bulan = max(6, (int) env('AUDIT_ARSIP_BULAN', 24));
        if (Schema::hasTable('audit_logs') && Schema::hasTable('audit_logs_arsip')) {
            $batasTanggal = now()->subMonths($bulan)->startOfDay()->toDateTimeString();
            $dipindah = 0;
            $gagal = false;
            $jumlah = DB::table('audit_logs')->where('created_at', '<', $batasTanggal)->count();
            if (!$pratinjau) {
                // Bertahap per 5.000 baris supaya tabel tidak terkunci lama saat kasir sedang bekerja.
                do {
                    $id = DB::table('audit_logs')->where('created_at', '<', $batasTanggal)->orderBy('id')->limit(5000)->pluck('id');
                    if ($id->isEmpty()) {
                        break;
                    }
                    // INSERT biasa (bukan IGNORE): baris yang gagal tersalin, mis. id sudah terpakai di arsip, menggagalkan
                    // batch ini dan log aslinya tidak ikut terhapus. Jumlah yang tersalin dicek sebelum menghapus.
                    try {
                        DB::transaction(function () use ($id) {
                            $daftar = $id->implode(',');
                            $tersalin = DB::affectingStatement("INSERT INTO audit_logs_arsip SELECT * FROM audit_logs WHERE id IN ({$daftar})");
                            if ($tersalin !== $id->count()) {
                                throw new \RuntimeException("hanya {$tersalin} dari {$id->count()} baris tersalin ke arsip");
                            }
                            DB::table('audit_logs')->whereIn('id', $id)->delete();
                        });
                    } catch (\Throwable $e) {
                        $this->error('Pemindahan log audit ke arsip dihentikan, log yang belum tersalin tidak dihapus: ' . $e->getMessage());
                        $gagal = true;
                        break;
                    }
                    $dipindah += $id->count();
                } while (true);
            }
            $hasil[] = ["Log audit > {$bulan} bulan dipindah ke arsip", $pratinjau ? $jumlah : $dipindah];
        }

        if (Schema::hasTable('backup_logs')) {
            $q = DB::table('backup_logs')->where('created_at', '<', now()->subYear());
            $hasil[] = ['Riwayat backup > 1 tahun', $pratinjau ? $q->count() : $q->delete()];
        }

        $this->table(['Data', $pratinjau ? 'Akan diproses' : 'Diproses'], $hasil);

        return empty($gagal) ? self::SUCCESS : self::FAILURE;
    }
}
