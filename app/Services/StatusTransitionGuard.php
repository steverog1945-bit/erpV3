<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/**
 * StatusTransitionGuard - satu-satunya tempat aturan transisi status ditulis.
 *
 * Skema lifecycle order di aplikasi ini TIDAK punya satu kolom `status`
 * gabungan. Seperti hasil audit §8B, statusnya hidup di empat field yang
 * terpisah:
 *
 *   transaksi.status_pembayaran : Belum Bayar | DP | Lunas
 *   transaksi.status_nota       : Aktif | Dibatalkan
 *   transaksi.status_order      : Menunggu Kasir | Diterima Kasir | Dibatalkan
 *   transaksi_detail.status_spk : Antre | Desain | Cetak | Selesai | Pending | Batal
 *   transaksi.status_collecting : Belum Diambil | Sudah Diambil
 *
 * Sebelum service ini ada, tiap controller meng-update field di atas secara
 * langsung tanpa mengecek apakah transisinya benar. Guard ini menolak
 * transisi yang terbukti ilegal dan melempar ValidationException (HTTP 422)
 * supaya tidak mungkin lolos ke database.
 *
 * Prinsip minimal-invasive: guard ini HANYA menolak transisi yang jelas-jelas
 * salah arah (mundur) atau menyentuh state terminal. Transisi maju, ke state
 * yang sama (idempoten), dan cancel semua tetap lolos - sejumlah alur produksi
 * masih mengandalkan cancel dari berbagai titik.
 */
class StatusTransitionGuard
{
    private const PEMBAYARAN = ['Belum Bayar', 'DP', 'Lunas'];
    private const NOTA = ['Aktif', 'Dibatalkan'];
    private const ORDER = ['Menunggu Kasir', 'Diterima Kasir', 'Dibatalkan'];
    private const SPK = ['Antre', 'Desain', 'Cetak', 'Selesai', 'Pending', 'Batal'];
    private const AMBIL = ['Belum Diambil', 'Sudah Diambil'];

    public static function validateStatusPembayaran(string $current, string $next): void
    {
        self::cekAllowlist($next, self::PEMBAYARAN, 'status_pembayaran');

        // Pembayaran hanya boleh maju, tidak boleh mundur.
        // Belum Bayar -> DP -> Lunas adalah satu-sarangnya arah yang sah.
        $rank = array_flip(['Belum Bayar', 'DP', 'Lunas']);
        if ($rank[$next] < $rank[$current]) {
            throw ValidationException::withMessages([
                'status_pembayaran' => "Transisi status pembayaran dari '{$current}' ke '{$next}' tidak sah. Pembayaran hanya dapat maju (Belum Bayar -> DP -> Lunas).",
            ]);
        }
    }

    public static function validateStatusNota(string $current, string $next): void
    {
        self::cekAllowlist($next, self::NOTA, 'status_nota');

        // Sekali nota dibatalkan, ia terkunci - tidak dapat kembali ke Aktif.
        if ($current === 'Dibatalkan' && $next !== 'Dibatalkan') {
            throw ValidationException::withMessages([
                'status_nota' => "Nota yang sudah 'Dibatalkan' tidak dapat diubah kembali ke '{$next}'.",
            ]);
        }
    }

    /**
     * Order lifecycle: Menunggu Kasir -> Diterima Kasir (via POST terimaTransaksi).
     * Dibatalkan terminal mengikuti status_nota (diset bersamaan di eksekusiPembatalanNota).
     */
    public static function validateStatusOrder(string $current, string $next): void
    {
        self::cekAllowlist($next, self::ORDER, 'status_order');

        if ($current === 'Diterima Kasir' && $next !== 'Diterima Kasir') {
            throw ValidationException::withMessages([
                'status_order' => "Transaksi yang sudah 'Diterima Kasir' tidak dapat diubah ke '{$next}'.",
            ]);
        }

        if ($current === 'Dibatalkan' && $next !== 'Dibatalkan') {
            throw ValidationException::withMessages([
                'status_order' => "Transaksi yang sudah 'Dibatalkan' tidak dapat diubah ke '{$next}'.",
            ]);
        }

        $allowedFrom = [
            'Menunggu Kasir' => ['Diterima Kasir', 'Dibatalkan'],
        ];
        if (isset($allowedFrom[$current]) && $next !== $current && !in_array($next, $allowedFrom[$current], true)) {
            throw ValidationException::withMessages([
                'status_order' => "Transisi status order dari '{$current}' ke '{$next}' tidak diizinkan.",
            ]);
        }
    }

    public static function validateStatusSpk(string $current, string $next): void
    {
        self::cekAllowlist($next, self::SPK, 'status_spk');

        // Selesai bersifat terminal: tidak ada yang boleh mengubahnya lagi,
        // KECUALI pembatalan nota (Selesai -> Batal). Koreksi/pembatalan nota
        // penjualan-langsung memang membatalkan item yang statusnya Selesai -
        // terbukti oleh NotaKoreksiCetakTest (koreksi harus membalik stok &
        // jurnal). Pembatalan nota produk yang sudah selesai diproduksi tetap
        // terkunci di level nota, bukan di sini.
        if ($current === 'Selesai' && $next !== 'Selesai' && $next !== 'Batal') {
            throw ValidationException::withMessages([
                'status_spk' => "Status SPK yang sudah 'Selesai' tidak dapat diubah ke '{$next}'.",
            ]);
        }

        // Batal juga terminal.
        if ($current === 'Batal' && $next !== 'Batal') {
            throw ValidationException::withMessages([
                'status_spk' => "Status SPK yang sudah 'Batal' tidak dapat diubah ke '{$next}'.",
            ]);
        }

        // Valid next states from a given current state.
        $allowedFrom = [
            'Antre'  => ['Desain', 'Cetak', 'Pending', 'Batal'],
            'Desain' => ['Cetak', 'Selesai', 'Pending', 'Batal', 'Antre'],
            'Cetak'  => ['Selesai', 'Pending', 'Batal', 'Antre'],
            'Pending' => ['Antre', 'Desain', 'Cetak', 'Selesai', 'Batal'],
        ];
        if (isset($allowedFrom[$current]) && $next !== $current && !in_array($next, $allowedFrom[$current], true)) {
            throw ValidationException::withMessages([
                'status_spk' => "Transisi status SPK dari '{$current}' ke '{$next}' tidak diizinkan. Dari '{$current}' hanya boleh: " . implode(', ', $allowedFrom[$current]) . '.',
            ]);
        }
    }

    public static function validateStatusCollecting(string $current, string $next): void
    {
        self::cekAllowlist($next, self::AMBIL, 'status_collecting');

        // Sekali barang sudah diambil, statusnya tidak dapat diubah ke Belum Diambil.
        if ($current === 'Sudah Diambil' && $next === 'Belum Diambil') {
            throw ValidationException::withMessages([
                'status_collecting' => "Transaksi yang sudah 'Sudah Diambil' tidak dapat dikembalikan ke 'Belum Diambil'.",
            ]);
        }
    }

    private static function cekAllowlist(string $nilai, array $diizinkan, string $field): void
    {
        if (!in_array($nilai, $diizinkan, true)) {
            throw ValidationException::withMessages([
                $field => "Nilai status '{$nilai}' tidak dikenal. Diizinkan: " . implode(', ', $diizinkan) . '.',
            ]);
        }
    }
}