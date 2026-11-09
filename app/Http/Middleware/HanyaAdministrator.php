<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi halaman pengaturan hak akses untuk role Administrator saja.
 *
 * Kenapa perlu, kalau CheckMenuAccess sudah memeriksa menu?
 *
 * Karena CheckMenuAccess memakai izin yang DISIMPAN di role_permissions -
 * tabel yang justru bisa dihapus oleh halaman ini. Jadi begitu role lain
 * diberi izin "Pengaturan Akses", role itu bisa:
 *   - mencentang semua modul untuk role-nya sendiri, dan
 *   - menuliskan row role_permissions sesuka hatinya,
 * lalu memakai akses barulah itu di halaman berikutnya. Menmemberikan
 * hak akses berarti memberi hak untuk mengambil hak akses - escalate.
 *
 * Pada saat pemeriksaan ini dilakukan: saat ini tidak ada role selain Administrator yang punya
 * menu ini, jadi celahnya belum bisa dieksploitasi. Tapi ini satu
 * perubahan centang di UI, dan karena itu tidak boleh dibiarkan
 * bergantung pada keadaan sekarang.
 *
 * Pemeriksaan dilakukan di jalur request, bukan hanya dengan menyembunyikan
 * tombol di tampilan.
 */
class HanyaAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $roleName = optional($user->role)->nama_role ?? '';
        if (! str_starts_with($roleName, 'Administrator')) {
            abort(403, 'Hanya Administrator yang boleh mengubah hak akses. Hubungi Administrator jika Anda merasa kekurangan akses.');
        }

        return $next($request);
    }
}
