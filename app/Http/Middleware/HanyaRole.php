<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi halaman pada daftar role eksplisit: hanya role yang disebut
 * (nama_role PERSIS, tanpa wildcard) yang boleh membukanya. Role di luar
 * daftar mendapat 403.
 *
 * Administrator SELALU lolos - sama seperti middleware eksklusif lain di
 * aplikasi ini (HanyaAdministrator, CheckMenuAccess) - supaya tim dukungan
 * selalu bisa memeriksa halaman milik role lain.
 *
 * Dipakai sebagai 'role.only:Nama Role A,Nama Role B' di route. Parameter
 * adalah daftar nama role dipisah koma; spasi di sekitar nama dipangkas.
 */
class HanyaRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request); // tamu: biar middleware 'auth' yang menangani
        }

        $namaRole = optional($user->role)->nama_role;

        if ($namaRole === 'Administrator') {
            return $next($request);
        }

        $roles = array_map('trim', $roles);

        if ($namaRole !== null && $namaRole !== '' && in_array($namaRole, $roles, true)) {
            return $next($request);
        }

        abort(403, 'Halaman ini khusus untuk role tertentu. Hubungi Administrator untuk meminta akses.');
    }
}
