<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Audit Fase 4 (keamanan): SETIAP rute yang berada di grup `menu.access` harus
 * menolak (403) pengguna yang loginnya sah tetapi rolenya tidak punya satu pun
 * izin menu - untuk semua verba HTTP. Ini menguji penegakan di sisi server,
 * bukan sekadar link sidebar yang disembunyikan. Rute baru yang lupa dipetakan
 * di config/menu_access.php akan langsung membuat tes ini gagal.
 */
class MatriksRuteKeamananTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array{0:string,1:string}> [verba, uri] */
    private function ruteTerlindungi(): array
    {
        $hasil = [];
        foreach (Route::getRoutes() as $rute) {
            if (!in_array('menu.access', $rute->gatherMiddleware(), true)) {
                continue;
            }
            $uri = preg_replace('/\{[^}?]+\??\}/', '1', $rute->uri());
            foreach ($rute->methods() as $verba) {
                if (in_array($verba, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }
                $hasil[] = [$verba, '/' . ltrim($uri, '/')];
            }
        }

        return $hasil;
    }

    public function test_pengguna_tanpa_izin_ditolak_di_semua_rute_terlindungi(): void
    {
        $role = Role::factory()->create(['nama_role' => 'Tanpa Izin']);
        $user = User::factory()->create(['id_role' => $role->id_role]);
        $this->actingAs($user);

        $bebas = (array) config('menu_access.always_allow', []);
        $bocor = [];
        $jumlah = 0;

        foreach ($this->ruteTerlindungi() as [$verba, $uri]) {
            $path = trim($uri, '/');
            foreach ($bebas as $awalan) {
                if ($path === $awalan || str_starts_with($path, $awalan . '/')) {
                    continue 2;
                }
            }

            $jumlah++;
            $status = $this->call($verba, $uri)->getStatusCode();
            if ($status !== 403) {
                $bocor[] = "{$verba} {$uri} -> {$status}";
            }
        }

        $this->assertGreaterThan(200, $jumlah, 'Pemindaian rute terlalu sedikit - periksa filter middleware');
        $this->assertSame([], $bocor, "Rute terlindungi yang TIDAK menolak pengguna tanpa izin:\n" . implode("\n", $bocor));
    }

    public function test_role_baca_saja_tidak_bisa_mengubah_apa_pun_lewat_rute_mana_pun(): void
    {
        $role = Role::factory()->create(['nama_role' => 'Owner']);
        $user = User::factory()->create(['id_role' => $role->id_role]);
        $this->actingAs($user);

        $bebas = (array) config('menu_access.always_allow', []);
        $bocor = [];

        foreach ($this->ruteTerlindungi() as [$verba, $uri]) {
            if (in_array($verba, ['GET'], true)) {
                continue;
            }
            $path = trim($uri, '/');
            foreach ($bebas as $awalan) {
                if ($path === $awalan || str_starts_with($path, $awalan . '/')) {
                    continue 2;
                }
            }
            $status = $this->call($verba, $uri)->getStatusCode();
            if ($status !== 403) {
                $bocor[] = "{$verba} {$uri} -> {$status}";
            }
        }

        $this->assertSame([], $bocor, "Role baca-saja (Owner) lolos mengubah data:\n" . implode("\n", $bocor));
    }

    public function test_rute_di_luar_grup_menu_access_hanya_login_logout_dan_sinyal(): void
    {
        $terbuka = [];
        foreach (Route::getRoutes() as $rute) {
            $mw = $rute->gatherMiddleware();
            if (in_array('menu.access', $mw, true)) {
                continue;
            }
            $terbuka[] = implode('|', array_diff($rute->methods(), ['HEAD'])) . ' /' . ltrim($rute->uri(), '/') . ' [' . implode(',', array_filter($mw, 'is_string')) . ']';
        }

        // Yang wajib publik: homepage, endpoint login (route('login') sejak 4E.1),
        // aksi login (dibatasi laju), logout, cek kesehatan bawaan, sinyal (khusus login).
        $harapan = [
            'GET /up',
            'GET /',
            'GET /masuk', // endpoint login staf (Checkpoint 4E.1) - tujuan route('login')
            'POST /login',
            'POST /logout',
            'GET /sinyal',
            'GET /baca/{slug}', // tulisan yang ditandai publik (PublikController, scope publik)
            'GET /landing-page', // Landing Page published (LandingPageBuilderController::publicLandingPage)
            //                            Rute publik TERPISAH dari '/' supaya halaman tetap
            //                            bisa dibuka lengkap ketika Gate B
            //                            (LANDING_PAGE_BUILDER_ENABLED) dimatikan.
            //                            Membaca published revision yang valid saja -
            //                            tidak pernah draft, tidak pernah data ERP.
            'GET /manifest.webmanifest', // PWA: manifest untuk pasang app - wajib publik
            'GET /sw.js', // PWA: service worker - wajib publik (diambil browser saat load)
            'POST /client-log', // log error klien (graffik/umpan balik) - butuh login + throttle:30,1, bukan data bisnis
            'POST /profil/foto', // self-service foto profil sendiri - butuh login; controller hanya menulis milik user yang login
            'DELETE /profil/foto',
            'GET /lacak/{id}', // portal pelacakan publik - HMAC signed URL (URL::hasValidSignature diverifikasi di controller, 403 tanpa signature valid)
        ];
        $ditemukan = array_map(fn ($b) => preg_replace('/ \[.*\]$/', '', $b), $terbuka);
        $asing = array_values(array_filter($ditemukan, fn ($b) => !in_array($b, $harapan, true) && !str_starts_with($b, 'GET /storage') && $b !== 'PUT /storage/{path}' && !str_starts_with($b, 'GET /api/') && !str_starts_with($b, 'POST /api/')));

        $this->assertSame([], $asing, "Rute di luar menu.access yang tak terduga:\n" . implode("\n", $asing));
    }

    public function test_rute_unggah_disk_lokal_bawaan_framework_menolak_permintaan_tanpa_tanda_tangan(): void
    {
        // PUT /storage/{path} milik Laravel hanya sah untuk URL bertanda tangan (temporaryUploadUrl).
        $this->put('/storage/apa-saja.txt', ['x' => 'y'])->assertForbidden();
        $this->actingAs(User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Tanpa Izin'])->id_role]))
            ->put('/storage/apa-saja.txt', ['x' => 'y'])->assertForbidden();
    }
}
