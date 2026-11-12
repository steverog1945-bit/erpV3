<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menutup sesi akun yang dinonaktifkan MENENGAH SESI.
 *
 * Login sudah menolak akun nonaktif di pintu depan (lihat AuthController::login),
 * tapi akun yang dinonaktifkan SAAT masih login (karyawan baru resign) tetap
 * membawa sesi lamanya sampai habis. Middleware ini memeriksa tiap request web:
 * akun yang is_active-nya bukan 1 langsung dikeluarkan dan dipaksa masuk ulang,
 * dengan pesan yang jelas ke halaman login.
 *
 * Dipasang di grup 'web' (bootstrap/app.php) - berlaku untuk semua halaman
 * dan endpoint yang memakai session, apa pun route middleware lainnya.
 * Tamu (belum login) lolos tanpa apa-apa.
 */
class PastikanUserAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! (int) ($user->is_active ?? 1)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Akun Anda dinonaktifkan. Hubungi Administrator.',
                    'akun_nonaktif' => true,
                ], 403);
            }

            return redirect()->route('login')->withErrors([
                'username' => 'Akun Anda dinonaktifkan (karyawan resign / tidak aktif). Hubungi Administrator.',
            ]);
        }

        return $next($request);
    }
}
