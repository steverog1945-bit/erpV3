<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Pemeriksaan dan penyimpanan berkas unggahan yang aman (audit Fase 5).
 *
 * Latar: aturan `extensions:` milik Laravel hanya membaca NAMA berkas dari
 * klien, bukan isinya. Berkas HTML yang diberi nama "desain.pdf" lolos, lalu
 * store() memilih ekstensi dari ISI berkas dan menyimpannya sebagai ".html".
 * Karena berkas di storage publik dilayani Apache langsung, skripnya berjalan
 * di asal aplikasi: penyusup internal cukup menunggu Administrator membuka
 * tautan berkas untuk bertindak atas nama Administrator.
 *
 * Aturan di sini:
 * 1. Ekstensi dari klien harus ada di daftar putih.
 * 2. Isi berkas (deteksi finfo) tidak boleh berupa tipe yang bisa berjalan di
 *    peramban atau server (HTML, XML/SVG, JS, PHP, skrip shell, executable).
 * 3. Format gambar/PDF/ZIP harus benar-benar bertipe itu; format desain (ai,
 *    psd, cdr, eps) boleh bertipe biner umum karena finfo sering tidak
 *    mengenalinya, tetapi tetap tunduk pada aturan nomor 2.
 * 4. Berkas disimpan dengan nama acak DAN ekstensi dari daftar putih (bukan
 *    tebakan dari isi), sehingga tidak pernah tersimpan dengan ekstensi yang
 *    dipilih penyerang lewat isi berkas.
 */
class BerkasAman
{
    public const DAFTAR_PUTIH = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'zip', 'ai', 'psd', 'cdr', 'eps'];

    /** Tipe isi yang tidak boleh disimpan apa pun namanya. */
    private const TERLARANG = [
        'text/html', 'application/xhtml+xml', 'text/xml', 'application/xml', 'image/svg+xml',
        'text/javascript', 'application/javascript', 'application/x-javascript',
        'text/x-php', 'application/x-httpd-php', 'application/x-php',
        'text/x-shellscript', 'application/x-shellscript', 'application/x-sh', 'text/x-python', 'text/x-perl',
        'application/x-msdownload', 'application/x-dosexec', 'application/x-executable', 'application/x-sharedlib',
        'application/vnd.microsoft.portable-executable', 'application/x-msdos-program', 'application/hta',
    ];

    /** Format yang isinya harus cocok persis dengan ekstensinya. */
    private const KETAT = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'zip' => ['application/zip', 'application/x-zip-compressed', 'application/x-zip'],
    ];

    /** @return string|null pesan galat untuk operator, atau null bila berkas aman */
    public static function periksa(UploadedFile $file, array $diizinkan = self::DAFTAR_PUTIH): ?string
    {
        $ekstensi = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($ekstensi, $diizinkan, true)) {
            return 'Jenis berkas tidak diizinkan. Gunakan: ' . implode(', ', $diizinkan) . '.';
        }

        $tipe = strtolower((string) $file->getMimeType());

        if (in_array($tipe, self::TERLARANG, true)) {
            return 'Isi berkas terdeteksi sebagai ' . $tipe . ' dan tidak boleh diunggah, walaupun namanya berakhiran .' . $ekstensi . '.';
        }

        if (isset(self::KETAT[$ekstensi]) && !in_array($tipe, self::KETAT[$ekstensi], true)) {
            return 'Isi berkas tidak sesuai dengan ekstensinya (.' . $ekstensi . ' tetapi terdeteksi ' . ($tipe ?: 'tidak dikenal') . '). Simpan ulang dari aplikasi desain lalu unggah lagi.';
        }

        return null;
    }

    /** Simpan dengan nama acak dan ekstensi dari daftar putih. Kembalikan path relatif pada disk. */
    public static function simpan(UploadedFile $file, string $folder, string $disk = 'public'): string
    {
        $ekstensi = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($ekstensi, self::DAFTAR_PUTIH, true)) {
            throw new \InvalidArgumentException('Ekstensi berkas tidak diizinkan.');
        }

        return $file->storeAs($folder, Str::random(40) . '.' . $ekstensi, $disk);
    }
}
