<?php

namespace App\Support;

/**
 * Aturan berkas desain yang boleh diunggah (gambar acuan order).
 *
 * Dulu upload dibatasi gambar raster saja (jpg/jpeg/png/webp) dan dikompres
 * di browser sampai 10 KB dengan sisi terpanjang 480 px. Hasilnya terlalu
 * kecil untuk dicetak di SPK: huruf dan detail di dalam desain jadi pecah,
 * jadi acuan warnanya sendiri tidak trustworthy.
 *
 * Sekarang:
 *  - Semua format gambar umum boleh: jpg, jpeg, png, webp, gif, bmp, svg,
 *    tif, tiff, heic, heif, avif, jfif, ico.
 *  - Dokumen desain juga boleh: pdf, cdr, ai, eps, psd. Berkas seperti ini
 *    TIDAK bisa dikompres lewat canvas browser (browser tidak bisa
 *    membukanya sebagai gambar), jadi dikirim apa adanya.
 *  - Gambar dikompres di browser sampai 200 KB dengan resolusi jauh lebih
 *    besar (lihat partials/image-compress-script.blade.php), supaya tetap
 *    jernih saat dicetak di SPK.
 *
 * EKSTENSI dicek dari nama file asal yang dikirim browser, bukan dari tebakan
 * MIME Laravel. Alasannya: format desain seperti CDR & EPS hampir selalu punya
 * MIME generik (application/octet-stream), jadi aturan 'mimes' akan menolaknya
 * padahal berkasnya benar.
 *
 * Daftar format ini juga jadi satu-satunya sumber kebenaran untuk sisi
 * server (validasi upload) dan sisi peramban (pratinjau), supaya keduanya
 * tidak bisa berbeda pendapat tentang berkas mana yang boleh.
 */
class BerkasDesain
{
    /**
     * Format yang bisa dibuka peramban sebagai gambar (bisa jadi <img>) dan
     * bisa dikompres lewat canvas.
     */
    public const FORMAT_GAMBAR = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tif', 'tiff'];

    /**
     * Format gambar yang sah, tapi peramban tidak bisa menampilkannya
     * (heic, heif, avif, jfif, ico). Berkasnya tetap boleh diunggah.
     */
    public const FORMAT_GAMBAR_LAIN = ['heic', 'heif', 'avif', 'jfif', 'ico'];

    /**
     * Dokumen desain - sumber file untuk produksi (PDF, CDR, AI, EPS, PSD).
     */
    public const FORMAT_DOKUMEN = ['pdf', 'cdr', 'ai', 'eps', 'psd'];

    /**
     * Batas ukuran berkas gambar (KB). Hasil kompres hanya sampai 200 KB,
     * jadi batas ini murni jaring pengaman kalau ada berkas yang lolos
     * tanpa kompresi.
     */
    public const MAKS_GAMBAR_KB = 10240;

    /**
     * Batas ukuran dokumen desain (KB). Dokumen desain tidak dikompres, jadi
     * batasnya lebih longgar - satu file CDR lengkap dengan font & gambar
     * tertanam bisa saja lewat 5 MB.
     */
    // 39936 KB = 39 MB - sengaja sedikit DI BAWAH post_max_size PHP XAMPP
    // (40 MB). Kalau batas aplikasi menempel persis di langit-langit PHP,
    // ujung depan multipart bisa mendorong body POST lewat post_max_size,
    // PHP membuang seluruh unggahan, dan pengguna melihat galat
    // "Periksa jaringan" yang menyesatkan padahal berkasnya cuma kebesaran.
    public const MAKS_DOKUMEN_KB = 39936;

    /** Semua format yang boleh diunggah. */
    public static function semuaFormat(): array
    {
        return array_merge(self::FORMAT_GAMBAR, self::FORMAT_GAMBAR_LAIN, self::FORMAT_DOKUMEN);
    }

    /**
     * Ekstensi huruf kecil dari nama file atau path (tanpa titik).
     *
     * Dua bentuk input diterima: nama berkas lengkap ("desain.pdf") dan
     * ekstensinya saja ("pdf"). pathinfo() tidak mengembalikan 'extension'
     * untuk string tanpa titik, jadi kasus kedua ditangani sendiri.
     */
    public static function ekstensi(string $namaAtauPath): string
    {
        $nama = basename(trim((string) $namaAtauPath));

        if (!str_contains($nama, '.')) {
            return strtolower(trim($nama, '. '));
        }

        return strtolower(pathinfo($nama, PATHINFO_EXTENSION));
    }

    /** Format ini boleh diunggah? */
    public static function diizinkan(string $ekstensi): bool
    {
        return in_array(strtolower(trim((string) $ekstensi, '. ')), self::semuaFormat(), true);
    }

    /** Peramban bisa menampilkan berkas ini sebagai gambar (<img>)? */
    public static function bisaDitampilkan(string $namaAtauPath): bool
    {
        return in_array(self::ekstensi($namaAtauPath), self::FORMAT_GAMBAR, true);
    }

    /** Batas ukuran (KB) untuk format tertentu. */
    public static function maksKilobyte(string $ekstensi): int
    {
        return in_array(strtolower(trim((string) $ekstensi, '. ')), self::FORMAT_DOKUMEN, true)
            ? self::MAKS_DOKUMEN_KB
            : self::MAKS_GAMBAR_KB;
    }

    /** Daftar format untuk pesan error (biar operator tahu apa yang boleh). */
    public static function daftarFormat(): string
    {
        return implode(', ', array_map('strtoupper', self::semuaFormat()));
    }
}
