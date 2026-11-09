<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Jalan pintas "lanjut ke mana?" yang muncul di notifikasi setelah sebuah aksi
 * (lihat toast 'lanjut' di layouts/app.blade.php). Tombol HANYA dimunculkan bila
 * role pengguna memang berhak membuka halaman tujuan; kalau tidak, cukup teks
 * petunjuk tanpa tombol.
 */
class JalurLanjut
{
    public static function set(string $teks, string $routeName, string $tombol, array $params = []): void
    {
        $url = route($routeName, $params);
        session()->flash('lanjut', [
            'teks' => $teks,
            'url' => self::boleh($url) ? $url : null,
            'tombol' => $tombol,
        ]);
    }

    private static function routeDibatasi(string $url): bool
    {
        try {
            $route = app('router')->getRoutes()->match(\Illuminate\Http\Request::create($url, 'GET'));

            return in_array('restrict.input-only', $route->gatherMiddleware(), true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function boleh(string $url): bool
    {
        return self::bolehUntuk(auth()->user(), $url);
    }

    public static function bolehUntuk($user, string $url): bool
    {
        if (!$user) {
            return false;
        }
        if (optional($user->role)->nama_role === 'Administrator') {
            return true;
        }

        // Halaman yang dijaga middleware 'restrict.input-only' (role hanya boleh input baru) tidak boleh ditawarkan
        // sebagai tujuan - membukanya pasti 403.
        if (\App\Http\Middleware\RestrictInputOnlyRoles::dibatasi($user) && self::routeDibatasi($url)) {
            return false;
        }

        $path = trim(parse_url($url, PHP_URL_PATH) ?? '', '/');
        $path = preg_replace('#^percetakan-app/public/?#', '', $path);
        if (!AksesHarga::bolehLihat($user) && AksesHarga::pathKhususHarga($path)) {
            return false;
        }

        $rules = config('menu_access.rules', []);
        $cocok = null;
        foreach ($rules as $prefix => $ids) {
            if (($path === $prefix || str_starts_with($path, $prefix . '/')) && ($cocok === null || strlen($prefix) > strlen($cocok))) {
                $cocok = $prefix;
            }
        }
        if ($cocok === null) {
            return false;
        }

        return Izin::boleh($user, $rules[$cocok], 'view');
    }
}
