<?php

namespace App\Console\Commands;

use App\Services\StatusPembayaran;
use Illuminate\Console\Command;

/**
 * Menyelaraskan transaksi.status_pembayaran yang kolom salinannya tertinggal
 * dari pembayaran_riwayat.
 *
 * Kebutuhan ini nyata: kolom itu ditulis hanya di prosesBayar(), jadi setiap
 * kali pembayaran_riwayat berubah lewat jalur lain (koreksi harga/qty yang
 * membuat total nota turun di bawah yang sudah dibayar, void, impor) kolomnya
 * tidak ikut turun. Efeknya bukan angka yang salah sedikit, tapi LAYAR yang
 * menunjuk dua arah: badge "Lunas" bersebelahan dengan catatan "Belum Bayar".
 * Command ini menutup sekalian sumber masalahnya dengan menulis ulang kolom
 * dari catatan pembayaran, supaya perbaikannya permanen dan bukan sekadar
 * disembunyikan di tampilan.
 *
 * Perintah hanya MEMBACA lalu menulis status turunan - tidak pernah mengubah
 * transaksi.total_bayar maupun pembayaran_riwayat, jadi angka uangnya tidak
 * pernah berubah.
 */
class SelaraskanStatusPembayaranCommand extends Command
{
    protected $signature = 'kas:selaraskan-status
                            {--dry-run : Tampilkan baris yang akan diperbaiki tanpa menulis}';

    protected $description = 'Samakan transaksi.status_pembayaran dengan pembayaran yang benar-benar tercatat (tidak mengubah angka uang)';

    public function handle(): int
    {
        $hasil = StatusPembayaran::cariYangSelaras();

        if ($hasil->isEmpty()) {
            $this->info('Semua status pembayaran sudah cocok dengan catatan pembayaran.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Nota', 'Tagihan', 'Terbayar', 'Tercatat', 'Seharusnya'],
            $hasil->map(function ($t) {
                return [
                    $t->id_transaksi,
                    $t->nomor_nota,
                    number_format((float) $t->total_bayar, 0, ',', '.'),
                    number_format((float) $t->total_terbayar_real, 0, ',', '.'),
                    (string) $t->status_pembayaran,
                    (string) $t->status_sebenarnya,
                ];
            })->all()
        );

        $jumlah = $hasil->count();
        $this->line("<info>{$jumlah} baris tidak cocok.</info>");

        if ($this->option('dry-run')) {
            $this->comment('Mode dry-run: tidak ada yang ditulis.');

            return self::SUCCESS;
        }

        $hasilTulis = StatusPembayaran::selaraskan();
        $this->info("{$hasilTulis['diperbaiki']} baris diperbaiki.");

        return self::SUCCESS;
    }
}