<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi hanya request yang menulis data.
 *
 * Kenapa tidak throttle langsung di route grup saja: limiternya ikut
 * jalan untuk GET, dan halaman kasir memanggil /sinyal setiap beberapa
 * detik selama jam operasional. Dengan satu limiter untuk semua, kasir
 * yang sedang menghitung akan ikut terkunci bukan karena salah, tapi
 * karena memang bekerja.
 *
 * Jadi metode baca dibiarkan sepenuhnya, dan hanya POST/PUT/PATCH/
 * DELETE yang dihitung. Dipasang sekali di route grup, bukan ditulis
 * per route - ada 158 route tulis, dan limiter yang ditulis manual
 * pasti ada yang terlewat.
 */
class ThrottleWriteRequests
{
    public const METODE_TULIS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** Batas per menit, per pengguna. Longgar: cukup menahan pengulangan
     *  tak terbatas, tidak sampai mengganggu pemakaian sah. */
    public const MAKS_PER_MENIT = 180;

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getMethod(), self::METODE_TULIS, true)) {
            return $next($request);
        }

        $kunci = $this->kunci($request);

        // tooManyAttempts dihitung DULUAN, sebelum controller jalan.
        // RateLimiter::attempt() tidak boleh dipakai di sini: saat
        // kuota habis ia mengembalikan null, dan null dari middleware
        // menghasilkan respons 500 - bukan 429. Gejalanya sangat
        // menyesatkan: form tetap gagal, tapi pesannyainstead error
        // server sehingga tidak ada yang tahu sudah kena batas.
        if (RateLimiter::tooManyAttempts($kunci, self::MAKS_PER_MENIT)) {
            $sisa = RateLimiter::availableIn($kunci);

            return $this->tolak($request, $sisa);
        }

        RateLimiter::hit($kunci, 60);

        return $next($request);
    }

    protected function tolak(Request $request, int $sisa): Response
    {
        $pesan = 'Terlalu banyak permintaan. Coba lagi dalam ' . max(1, (int) ceil($sisa / 60)) . ' menit.';

        $respons = $request->expectsJson()
            ? response()->json(['message' => $pesan], 429)
            : back()->withInput($request->except(['_token', 'password', 'password_confirmation']))
                     ->with('error', $pesan);

        $respons->headers->set('Retry-After', (string) max(1, $sisa));

        return $respons;
    }

    /**
     * Kunci per-user, bukan per-IP: beberapa orang sering berbagi satu
     * IP kantor, dan dengan kunci IP mereka akan saling mengunci.
     */
    protected function kunci(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();

        return 'tulis|' . ($id ? 'u' . $id : 'ip' . $request->ip());
    }
}
