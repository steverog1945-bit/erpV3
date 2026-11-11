<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menegakkan hak akses per-role di level ROUTE, bukan cuma menyembunyikan
 * link sidebar seperti sebelumnya (lihat catatan di config/menu_access.php).
 * Role Administrator selalu lolos; role lain dicek terhadap role_permissions
 * berdasarkan menu yang dipetakan untuk URL yang sedang diakses.
 */
class CheckMenuAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if (optional($user->role)->nama_role === 'Administrator') {
            return $next($request);
        }

        $path = trim($request->path(), '/');

        // Prefix khusus Administrator: ditolak untuk SEMUA role lain berapa pun
        // id_menu yang dimilikinya - gembok ini menang atas mapping 'rules'.
        foreach (config('menu_access.khusus_administrator', []) as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                abort(403, 'Halaman ini khusus Administrator. Hubungi Administrator untuk meminta perubahan.');
            }
        }

        // Prefix yang bebas untuk siapa pun yang sudah login (dashboard,
        // logout, dst) - berlaku untuk SEMUA metode (logout = POST) dan
        // dicek SEBELUM gembok baca-saja, kalau tidak role baca-saja
        // (Owner) tidak akan pernah bisa logout sama sekali.
        foreach (config('menu_access.always_allow', []) as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $next($request);
            }
        }

        // Role baca-saja (Owner): tidak boleh mengubah data apa pun, walau tombolnya terlihat.
        if (!$request->isMethodSafe() && in_array(optional($user->role)->nama_role, config('menu_access.roles_baca_saja', []), true)) {
            abort(403, 'Role Anda bersifat baca-saja (audit). Perubahan data dilakukan oleh Administrator/staf terkait.');
        }

        // AKSES LIHAT (GET/HEAD): keputusannya dialihkan ke SATU SUMBER
        // KEBENARAN di Izin::bolehPath() - fungsi yang sama dipakai saat
        // login untuk memvalidasi URL "intended" milik sesi lama, supaya
        // jawaban "boleh lihat path ini?" tidak pernah berbeda antara
        // middleware dan alur login (akar bug 403 saat ganti akun/role).
        if ($request->isMethodSafe()) {
            if (!\App\Support\Izin::bolehPath($user, $path)) {
                abort(403, 'Anda tidak memiliki izin untuk melihat halaman ini. Hubungi Administrator untuk meminta akses.');
            }
            return $next($request);
        }

        // Halaman harga tetap digembok untuk SEMUA metode - mutasi data di
        // halaman berharga oleh role tanpa hak lihat harga sama-sama ditolak
        // (untuk GET/HEAD pemeriksaannya sudah termasuk di Izin::bolehPath()).
        if (!\App\Support\AksesHarga::bolehLihat($user) && \App\Support\AksesHarga::pathKhususHarga($path)) {
            abort(403, 'Role Anda tidak diizinkan melihat atau mengubah harga. Halaman ini khusus role yang berhak.');
        }

        // Aksi dari metode HTTP: POST = tambah, PUT/PATCH = kelola (ubah), DELETE = hapus.
        // POST yang sebenarnya hanya "membaca" (pratinjau/cek) didaftarkan di menu_access.post_baca.
        $aksi = \App\Support\Izin::aksiDariMetode($request->method());
        if ($aksi === 'add' && in_array($path, config('menu_access.post_baca', []), true)) {
            $aksi = 'view';
        }

        $rules = config('menu_access.rules', []);
        $matchedPrefix = null;

        foreach ($rules as $prefix => $menuIds) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                if ($matchedPrefix === null || strlen($prefix) > strlen($matchedPrefix)) {
                    $matchedPrefix = $prefix;
                }
            }
        }

        if ($matchedPrefix === null) {
            // URL ini belum dipetakan ke menu manapun. Tolak demi keamanan
            // (fail closed) - untuk route yang sudah ada di aplikasi ini
            // seharusnya tidak pernah kejadian karena semua sudah dipetakan;
            // ini jaring pengaman untuk route baru yang lupa didaftarkan.
            abort(403, 'Rute ini belum terdaftar di peta akses. Hubungi Administrator.');
        }

        if (!\App\Support\Izin::boleh($user, $rules[$matchedPrefix], $aksi)) {
            $label = ['view' => 'melihat data di', 'add' => 'menambah data di', 'edit' => 'mengubah/mengelola data di', 'delete' => 'menghapus data di'][$aksi];
            abort(403, "Anda tidak memiliki izin untuk {$label} modul ini. Hubungi Administrator untuk meminta akses.");
        }

        return $next($request);
    }
}
