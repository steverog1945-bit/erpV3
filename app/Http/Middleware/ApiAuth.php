<?php

namespace App\Http\Middleware;

use App\Services\ApiTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware autentikasi REST API publik (API-5).
 *
 * Token HANYA diterima lewat header `Authorization: Bearer <token>`
 * (masa berlaku 24 jam per config orion_api.token_masa_aktif_jam):
 *  - Parameter query `?api_token=` DITOLAK selalu 401, sekalipun ada
 *    Bearer valid di header yang sama. Token di URL bocor ke access
 *    log server, history browser, dan header Referer - karenanya jalur
 *    ini dihapus total, bukan cuma tidak dipakai.
 *  - Pencocokan HANYA lewat SHA-256 (users.api_token_hash). Token
 *    plaintext era lama di kolom users.api_token tidak pernah dicocokkan
 *    lagi; pemegang token lama wajib terbitkan token baru via
 *    POST /api/auth/token pada cutover yang disetujui owner.
 *  - Token kadaluarsa, dicabut (logout/rotasi), atau salah format
 *    (bukannya 64 hex) => 401. Pesan sengaja digeneralisasi agar tidak
 *    memberi sinyal bagian mana yang salah.
 *
 * Nilai token tidak pernah ditulis ke log, respons error, atau
 * exception - kredensial tidak boleh tumpah dari jalur diagnostik.
 */
class ApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // Jalur token-di-URL ditolak tegas sedari PARAMETERNYA hadir - bahkan
        // kalau nilainya kosong atau permintaan yang sama membawa Bearer sah
        // di header. Cek KANDAUNGAN parameter, bukan truthy-ness nilainya.
        if (array_key_exists('api_token', $request->query())) {
            return response()->json([
                'error' => 'Token API tidak boleh lewat URL. Kirim header Authorization: Bearer <token>.',
            ], 401);
        }

        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'error' => 'Token API diperlukan. Sertakan header Authorization: Bearer <token>.',
            ], 401);
        }

        $user = app(ApiTokenService::class)->cocokkan($token);

        if (!$user) {
            return response()->json([
                'error' => 'Token API tidak valid, kedaluwarsa, atau sudah dicabut.',
            ], 401);
        }

        // Pasang user ke request dan auth guard untuk controller yang
        // memakai auth()->user() atau $request->user().
        auth()->setUser($user);

        return $next($request);
    }
}
