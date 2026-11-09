<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Melayani berkas unggahan yang berisi data usaha (desain pelanggan, bukti bayar, bukti serah terima)
 * hanya untuk pengguna yang sudah login. Alamat tetap /storage/<folder>/<berkas>, jadi tampilan dan data
 * lama tidak berubah; yang berubah hanya Apache tidak lagi melayaninya langsung (lihat public/.htaccess).
 * Logo, gambar postingan, dan halaman login tetap publik karena memang harus tampil sebelum login.
 */
class BerkasPrivatController extends Controller
{
    public const FOLDER = ['order_thumbnails', 'jersey_thumbnails', 'jersey_bukti_bayar', 'kas_kecil_bukti', 'bukti_serah_terima', 'berkas_order', 'profil'];

    /**
     * Folder -> prefix rute di config/menu_access.php yang berhak melihat isinya. Id menunya dibaca dari konfigurasi
     * yang sama dengan pengecekan rute, jadi selalu sinkron. Login saja tidak cukup: bukti Kas Kecil tidak untuk
     * operator produksi, berkas order tidak untuk staf yang tidak menangani order.
     */
    private const MENU_FOLDER = [
        'kas_kecil_bukti'    => ['kas-kecil'],
        'jersey_bukti_bayar' => ['jersey'],
        'jersey_thumbnails'  => ['jersey'],
        'order_thumbnails'   => ['penjualan', 'berkas-order', 'spk', 'spk-desain', 'produksi', 'collecting', 'kasir', 'qc'],
        'berkas_order'       => ['penjualan', 'berkas-order', 'spk', 'spk-desain', 'produksi', 'collecting', 'kasir', 'qc'],
        'bukti_serah_terima' => ['collecting', 'penjualan', 'kasir'],
    ];

    private static function menuUntuk(string $folder): array
    {
        $aturan = (array) config('menu_access.rules', []);
        $id = [];
        foreach (self::MENU_FOLDER[$folder] ?? [] as $prefix) {
            foreach ($aturan as $kunci => $menu) {
                if ($kunci === $prefix || str_starts_with($kunci, $prefix . '/')) {
                    $id = array_merge($id, (array) $menu);
                }
            }
        }

        return array_values(array_unique($id));
    }

    public function tampil(string $folder, string $path): BinaryFileResponse
    {
        abort_unless(in_array($folder, self::FOLDER, true), 404);
        abort_if(str_contains($path, '..') || str_contains($path, "\0") || str_contains($path, '\\'), 404);

        // Foto profil: boleh diakses oleh pengguna yang login (miliknya sendiri).
        // Folder lain tetap ikut aturan menu_access.
        if ($folder !== 'profil') {
            abort_unless(\App\Support\Izin::boleh(auth()->user(), self::menuUntuk($folder), 'view'), 403);
        } else {
            abort_unless(auth()->check(), 403);
        }

        $relatif = $folder . '/' . $path;
        $disk = Storage::disk('public');
        abort_unless($disk->exists($relatif), 404);

        $respons = response()->file($disk->path($relatif), [
            'X-Content-Type-Options' => 'nosniff',
            // Singkat: komputer bersama tidak menyimpan bukti bayar/berkas pelanggan lama setelah pengguna keluar.
            'Cache-Control' => 'private, max-age=300',
        ]);

        // Berkas yang bisa membawa skrip tidak boleh menjalankannya (unggahan lama mungkin tidak melalui BerkasAman).
        $ext = strtolower(pathinfo($relatif, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'], true)) {
            $respons->headers->set('Content-Security-Policy', "default-src 'none'; script-src 'none'; object-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'");
            $respons->headers->set('Content-Disposition', 'attachment; filename="' . basename($relatif) . '"');
        }

        return $respons;
    }
}
