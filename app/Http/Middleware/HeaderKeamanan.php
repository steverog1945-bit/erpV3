<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan pada setiap respons.
 *
 * Dipasang sebagai middleware global, bukan per route, karena header ini
 * harus berlaku juga di halaman yang biasanya menampilkan pesan dari
 * server: 403, 404, 422, dan redirect. Header yang hanya dipasang di
 * halaman sukses justru hilang di halaman yang paling perlu.
 *
 * Nilai setiap directive CSP dipilih berdasarkan apa yang benar-benar ada
 * di aplikasi ini, bukan dari daftar contoh. Alasan pemilihan ada di
 * komentar masing-masing, supaya saat kode berubah orang tahu nilai mana
 * yang boleh dilonggarkan dan mana yang tidak.
 */
class HeaderKeamanan
{
    /**
     * Content-Security-Policy.
     *
     * Catatan jujur: 'unsafe-inline' dan 'unsafe-eval' masih ada, jadi
     * CSP ini belum menutup XSS sepenuhnya. Yang dicakupnya adalah arah
     * yang paling sering dipakai: menyuntik <script> dari domain lain,
     * memuat modul dari luar, dan menyisipkan object, embed, atau base.
     *
     * 'unsafe-inline' masih ada karena ada 93 <script> inline dan 416
     * atribut on* (306 di antaranya onclick) di 174 file blade.
     * Mengubah semuanya menjadi addEventListener adalah pekerjaan
     * besar yang bisa merusak alur yang sedang berjalan, bukan
     * perubahan konfigurasi. Yang bisa dilakukan sekarang tanpa risiko
     * adalah mengunci arah pemuatan. Nanti ketika handler inline sudah
     * dibersihkan, cukup hapus satu token dari script-src di sini.
     *
     * 'unsafe-eval' masih ada karena SweetAlert2 memanggil new Function()
     * dan jQuery punya globalEval. Dua-duanya dipaketkan di
     * public/vendor, dan berjalan di hampir semua halaman. Mengganti
     * SweetAlert2 berarti mengganti seluruh sistem notifikasi.
     */
    protected function csp(): string
    {
        $directive = [
            // Directive yang tidak disebut di bawah mewarisi default-src.
            "default-src 'self'",

            // Mencegah skrip dari domain lain. Sisanya lihat catatan kelas.
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",

            // 117 atribut style dan 35 blok <style> inline. Yang dicegah
            // di sini adalah stylesheet dari luar, yang bisa mencuri isi
            // field lewat background-image ke URL asing.
            "style-src 'self' 'unsafe-inline'",

            // https: ikut diizinkan karena artikel Postingan ditulis admin
            // lewat editor dan bisa memuat gambar dari luar. Risikonya
            // rendah untuk aplikasi internal, paling jauh ada pelacak
            // klik, bukan kebocoran data. Kalau someday isi artikel
            // selalu milik perusahaan sendiri, hapus https: dari sini.
            "img-src 'self' data: blob: https:",

            "font-src 'self' data:",

            // fetch dan XHR hanya boleh ke asal sendiri. Inilah yang
            // mencegah data halaman dikirim ke server lain seandainya
            // ada skrip yang bisa disuntik.
            "connect-src 'self'",

            "frame-src 'self'",
            "worker-src 'self' blob:",
            "media-src 'self' blob:",

            // <object> dan <embed> tidak dipakai di aplikasi ini. Plugin
            // adalah salah satu cara lama untuk menjalankan kode yang
            // dikontrol orang lain.
            "object-src 'none'",

            // Tidak ada <base> di aplikasi ini, jadi ini gratis: mencegah
            // penyisipan <base href> yang mengalihkan semua URL relatif.
            "base-uri 'self'",

            // Form hanya boleh dikirim ke asal sendiri, mencegah
            // kebocoran data lewat form yang dikirim ke server lain.
            "form-action 'self'",

            // Anti-clickjacking. X-Frame-Options di bawah adalah versi
            // lama untuk peramban yang belum mengenal frame-ancestors.
            "frame-ancestors 'self'",
        ];

        return implode('; ', $directive);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $respons = $next($request);

        // HSTS hanya bermakna di koneksi HTTPS. Browser mengabaikannya
        // di atas HTTP, jadi mengirimnya di localhost tidak merusak
        // apa pun. Tapi mengirimnya dari server produksi yang masih HTTP
        // akan membekukan situs di HSTS dan membuatnya tidak bisa dibuka
        // selama max-age. Karena itu dikirim hanya kalau request-nya
        // memang sudah terenkripsi.
        if ($request->isSecure()) {
            $respons->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        // Berhenti menebak-nebak tipe konten. Tanpa ini peramban bisa
        // mengabaikan Content-Type dan menebak sendiri, dan sebuah file
        // teks bisa dieksekusi sebagai HTML.
        $respons->headers->set('X-Content-Type-Options', 'nosniff');

        // X-XSS-Protection sengaja disetel 0, bukan 1; mode=block. Filter
        // lawas itu sudah terbukti bisa membuka celah baru pada peramban
        // lama alih-alih menutupnya, dan peramban modern mengabaikannya.
        $respons->headers->set('X-XSS-Protection', '0');

        // Kirim asal saja, bukan URL lengkap, dan jangan kirim path
        // ketika pindah ke domain lain.
        $respons->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Versi lama dari anti-clickjacking, untuk peramban yang belum
        // mengenal frame-ancestors.
        $respons->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Kamera diizinkan pada asal sendiri karena halaman monitoring
        // kasir memindai barcode dengan kamera. Sisanya dimatikan:
        // aplikasi ini tidak memakai geolokasi, mikrofon, atau pembayaran.
        $respons->headers->set(
            'Permissions-Policy',
            'camera=(self), geolocation=(), microphone=(), payment=(), usb=(), '
            . 'magnetometer=(), accelerometer=(), gyroscope=()'
        );

        // Respons yang sudah menetapkan CSP sendiri (mis. BerkasPrivatController untuk berkas aktif) tidak ditimpa.
        if (!$respons->headers->has('Content-Security-Policy')) {
            $respons->headers->set('Content-Security-Policy', $this->csp());
        }

        return $respons;
    }
}
