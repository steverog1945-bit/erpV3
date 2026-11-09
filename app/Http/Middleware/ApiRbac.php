<?php

namespace App\Http\Middleware;

use App\Support\Izin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RBAC untuk routes/api.php - rekanan CheckMenuAccess di sisi API.
 *
 * Tanpa middleware ini, SATU token API yang sah membaca SEMUA endpoint API
 * walau role pemiliknya di web tidak pernah diberi menu laporan kas,
 * piutang, pembayaran, SPK, stok, atau data konsumen (operasi baca jadi
 * akses baca-umum). Middleware ini menutup celah itu dengan aturan yang
 * SAMA dengan web:
 *
 *  - Peta endpoint -> id_menu ada di config('orion_api.menus') dan selaras
 *    dengan config/menu_access.php (penjelasan per baris di sana).
 *  - Hak dicek lewat App\Support\Izin::boleh() - sumber kebenaran yang
 *    sama dengan table role_permissions/user_permissions web, termasuk
 *    aturan role "hanya lihat" dan syarat view untuk aksi tulis.
 *  - Aksi diturunkan dari metode HTTP (Izin::aksiDariMetode).
 *  - Endpoint yang belum dipetakan = 403 untuk non-Administrator
 *    (fail-closed, seperti CheckMenuAccess) - endpoint baru tidak bocor
 *    sebelum peta diperbarui.
 *  - Administrator selalu lolos (Policy yang sama dengan web) supaya ada
 *    satu jalur pemulihan bila peta salah tulis.
 *
 * Urutan middleware: throttle:api -> api.auth -> di sini -> controller.
 */
class ApiRbac
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // api.auth seharusnya sudah berhenti di 401; baris ini jaring
        // pengaman bila routing berubah tanpa sengaja.
        if (!$user) {
            return $this->tolak('Autentikasi API diperlukan.');
        }

        // Sama seperti CheckMenuAccess: Owner dan role baca-saja tidak boleh
        // menjalankan aksi tulis walaupun baris izin menandai can_add=true.
        if (Izin::aksiDariMetode($request->method()) !== 'view'
            && in_array(optional($user->role)->nama_role, (array) config('menu_access.roles_baca_saja', []), true)) {
            return $this->tolak('Role ini hanya boleh melihat data.');
        }

        // Path request tanpa prefix 'api/' (contoh: 'transaksi/12/pembayaran').
        $path = trim($request->path(), '/');
        $path = str_starts_with($path, 'api/') ? substr($path, 4) : $path;

        // Pilih peta dengan prefix TERPANJANG yang cocok (konsisten dengan
        // CheckMenuAccess: 'laporan/kas' memenangkan 'laporan').
        $tercocok = null;

        // Route spesifik didahulukan dari prefix. Contoh: /transaksi/{id}/pembayaran
        // adalah modul PEMBAYARAN, bukan riwayat nota, walau URL-nya diawali transaksi/.
        // Kunci nama route dibaca secara literal karena mengandung tanda titik.
        $routeName = $request->route()?->getName();
        $routeMenus = (array) config('orion_api.route_menus', []);
        $definisi = $routeName ? ($routeMenus[$routeName] ?? null) : null;

        foreach ((array) config('orion_api.menus', []) as $prefix => $def) {
            if ($definisi !== null) break;
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                if ($tercocok === null || strlen($prefix) > strlen($tercocok)) {
                    $tercocok = $prefix;
                    $definisi = $def;
                }
            }
        }

        // Belum dipetakan = fail-closed.
        if ($definisi === null) {
            return $this->tolak('Endpoint API ini belum dibuka untuk role apa pun.');
        }

        // Nilai peta per metode HTTP (.transaksi) dibedakan dari daftar
        // id_menu biasa: peta punya kunci string metode, daftar berisi int.
        $idMenus = is_array($definisi) && !array_is_list($definisi)
            ? ($definisi[strtoupper($request->method())] ?? null)
            : $definisi;

        if (empty($idMenus)) {
            // Metode tidak terdaftar (mis. delete pada endpoint lihat) = tolak.
            return $this->tolak('Metode ' . $request->method() . ' tidak dibuka pada endpoint ini.');
        }

        if (Izin::boleh($user, (array) $idMenus, Izin::aksiDariMetode($request->method()))) {
            return $next($request);
        }

        return $this->tolak('Token API ini tidak memiliki hak menu untuk endpoint ini.');
    }

    private function tolak(string $pesan): Response
    {
        return response()->json(['message' => $pesan], 403)->withHeaders([
            'Cache-Control' => 'no-store',
        ]);
    }
}
