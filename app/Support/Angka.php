<?php

namespace App\Support;

/**
 * SATU-SATUNYA cara menampilkan angka di aplikasi (view, pesan, laporan).
 * Format Indonesia: pemisah ribuan titik, desimal koma - "200.000" (dua ratus
 * ribu), "12,5" (dua belas koma lima).
 *
 * Nol di belakang koma dipotong HANYA dari bagian pecahan. Pola lama
 * rtrim(rtrim(number_format($x, 0, ',', '.'), '0'), ',') salah besar: tanpa
 * pecahan, rtrim('0') ikut memakan nol bilangan bulat ("200.000" jadi "200.").
 * Jangan pernah menulis ulang pola itu; pakai fungsi di sini.
 *
 * Pemisah ribuan pada INPUT hanya muncul saat mengetik (lihat
 * partials/rupiah-format-script) dan selalu dibersihkan sebelum dikirim;
 * nilai yang disimpan/dikirim ke server tidak pernah memakai pemisah.
 */
class Angka
{
    /** Tampilan: "200.000", "12,5". Nol pecahan dipotong; $maks = jumlah desimal maksimum. */
    public static function format(mixed $nilai, int $maks = 2): string
    {
        $teks = number_format((float) $nilai, max(0, $maks), ',', '.');

        return ($maks > 0 && str_contains($teks, ',')) ? rtrim(rtrim($teks, '0'), ',') : $teks;
    }

    /** Rupiah utuh: "Rp 1.300.000". */
    public static function rupiah(mixed $nilai, bool $denganAwalan = true): string
    {
        $teks = number_format((float) $nilai, 0, ',', '.');

        return $denganAwalan ? 'Rp ' . $teks : $teks;
    }

    /** Angka polos untuk atribut/input type=number: "12.5" (titik desimal, tanpa pemisah ribuan, nol pecahan dipotong). */
    public static function polos(mixed $nilai, int $maks = 4): string
    {
        $teks = number_format((float) $nilai, max(0, $maks), '.', '');

        return ($maks > 0 && str_contains($teks, '.')) ? rtrim(rtrim($teks, '0'), '.') : $teks;
    }

    /** Isi awal kolom uang/angka yang diformat saat mengetik: "1.300.000" atau "281.057,64" (koma = desimal). */
    public static function input(mixed $nilai, int $maks = 0): string
    {
        return self::format($nilai, $maks);
    }

    /** Kebalikan tampilan/input: "1.300.000,5" -> 1300000.5 (untuk nilai yang datang dalam format Indonesia). */
    public static function parse(mixed $teks): float
    {
        if (is_int($teks) || is_float($teks)) {
            return (float) $teks;
        }
        $teks = trim((string) $teks);
        if ($teks === '') {
            return 0.0;
        }
        // Tanpa koma & tanpa lebih dari satu titik dan bukan pola ribuan penuh -> sudah polos ("12.5").
        if (!str_contains($teks, ',') && preg_match('/^-?\d+(\.\d+)?$/', $teks) && !preg_match('/^-?\d{1,3}(\.\d{3})+$/', $teks)) {
            return (float) $teks;
        }

        return (float) str_replace(',', '.', str_replace('.', '', $teks));
    }
}
