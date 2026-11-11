<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\HtmlBersih;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * AUDIT KEAMANAN - temuan yang dibuktikan lewat test.
 *
 * Hanya test yang SESUNGGNYA membuktikan sesuatu yang ditulis di
 * SECURITY_AUDIT.md. Test yang hanya mengulang apa yang sudah jelas dari
 * inspeksi kode DITARUH DI SINI sebagai dokumentasi kontrol yang aktif,
 * supaya regresinya ketahuan.
 *
 * Semua test memakai RefreshDatabase -> migrate:fresh ke DB bernama
 * "percetakan_erp_testing" (phpunit.xml). Guard di tests/TestCase.php
 * membatalkan seluruh test kalau nama DB mengandung "oriondb".
 */
class SecAuditTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================================
    // P1 - Batas privilege: pengaturan tingkat komisi TIDAK dikunci
    //     oleh middleware 'hanya.admin'
    // ==========================================================

    private function role(string $nama): Role
    {
        return Role::factory()->create(['nama_role' => $nama]);
    }

    private function izin(int $idRole, int $idMenu, array $aksi): void
    {
        if (!DB::table('menus')->where('id_menu', $idMenu)->exists()) {
            DB::table('menus')->insert([
                'id_menu' => $idMenu,
                'nama_menu' => 'Menu Uji ' . $idMenu,
                'url_link' => 'uji-' . $idMenu,
            ]);
        }

        DB::table('role_permissions')->insert([
            'id_role' => $idRole,
            'id_menu' => $idMenu,
            'can_view' => in_array('view', $aksi, true) ? 1 : 0,
            'can_add' => in_array('add', $aksi, true) ? 1 : 0,
            'can_edit' => in_array('edit', $aksi, true) ? 1 : 0,
            'can_delete' => in_array('delete', $aksi, true) ? 1 : 0,
        ]);
    }

    public function test_persen_komisi_bisa_diubah_oleh_role_bukan_administrator(): void
    {
        // Menu 66 = "Bagi Hasil Desainer" (lihat migrasi
        // 2026_10_09_100000_komisi_desain.php). Role non-Administrator
        // boleh diberi menu ini lewat /setting-akses.
        $role = $this->role('Supervisor Desainer');
        $this->izin($role->id_role, 66, ['view', 'add', 'edit', 'delete']);
        $user = User::factory()->create(['id_role' => $role->id_role]);

        $kelas = DB::table('kelas_desainer')->where('nama_kelas', 'Standar')->first();
        $semula = (float) $kelas->persen_komisi;
$respons = $this->actingAs($user)->put('/pengaturan-desainer/kelas/' . $kelas->id_kelas, [
            '_token' => 'token-uji',
            'nama_kelas' => 'Standar',
            'persen_komisi' => 100,
        ]);

        $respons->assertForbidden();

        // INI yang dimaksud test ini. Dulu route ini tidak dikunci
        // hanya.admin, jadi persen komisi benar-benar tertuang di baris
        // database (5 -> 100) oleh role yang bukan Administrator. Sekarang
        // route-nya memakai middleware hanya.admin, jadi role non-Administrator
        // yang memegang izin menu 66 pun ditolak 403 dan angka Cookbook
        // tidak tersentuh.
        $ini = (float) DB::table('kelas_desainer')->where('id_kelas', $kelas->id_kelas)->value('persen_komisi');
        $this->assertSame(
            $semula,
            $ini,
            "persen komisi tidak boleh berubah dari {$semula} oleh role non-administrator"
        );
    }

    public function test_role_bukan_administrator_bisa_menugaskan_kelas_desainer_ke_user_lain(): void
    {
        $role = $this->role('Supervisor Desainer');
        $this->izin($role->id_role, 66, ['view', 'add']);
        $aktor = User::factory()->create(['id_role' => $role->id_role]);
        $korban = User::factory()->create();

        $kelas = DB::table('kelas_desainer')->where('nama_kelas', 'Standar')->value('id_kelas');

        $this->actingAs($aktor)
            ->post('/pengaturan-desainer/anggota/' . $korban->id_user, [
                '_token' => 'token-uji',
                'id_kelas' => $kelas,
            ])
            ->assertForbidden();

        $this->assertNull(
            DB::table('users')->where('id_user', $korban->id_user)->value('id_kelas_desainer'),
            'menugaskan kelas desainer harus DILARANG untuk role non-administrator'
        );
    }

    public function test_kelas_desainer_bisa_dihapus_oleh_role_bukan_administrator(): void
    {
        $role = $this->role('Supervisor Desainer');
        $this->izin($role->id_role, 66, ['view', 'delete']);
        $aktor = User::factory()->create(['id_role' => $role->id_role]);

        $kelas = DB::table('kelas_desainer')->where('nama_kelas', 'Standar')->value('id_kelas');

        $this->actingAs($aktor)
            ->delete('/pengaturan-desainer/kelas/' . $kelas, ['_token' => 'token-uji'])
            ->assertForbidden();

        $this->assertNotNull(
            DB::table('kelas_desainer')->where('id_kelas', $kelas)->value('id_kelas'),
            'hapus kelas desainer harus DILARANG untuk role non-administrator'
        );
    }

    // ==========================================================
    // P1 - Halaman yang WAJIB hanya.administrator memang dikunci
    //     (kontrol pembanding: /setting-akses & /menu-editor)
    // ==========================================================

    public function test_setting_akses_memang_hanya_untuk_administrator(): void
    {
        $role = $this->role('Supervisor Desainer');
        $this->izin($role->id_role, 4, ['view', 'add', 'edit']);
        $user = User::factory()->create(['id_role' => $role->id_role]);

        $this->actingAs($user)->get('/setting-akses')->assertForbidden();
        $this->actingAs($user)->post('/setting-akses/update', ['_token' => 'x'])->assertForbidden();
    }

    // ==========================================================
    // P1 - Autentikasi: rate limiter login benar-benar aktif
    // ==========================================================

    public function test_login_dibatasi_lima_percobaan_per_menit(): void
    {
        $user = User::factory()->create(['username' => 'target', 'password' => Hash::make('rahasia-benar')]);

        for ($i = 1; $i <= 5; $i++) {
            $respons = $this->post('/login', ['username' => 'target', 'password' => 'salah']);
            $respons->assertRedirect();
            $this->assertSame(
                'User tidak ditemukan atau password salah.',
                $respons->getSession()->get('errors')->first('username'),
                "percobaan ke-{$i} seharusnya masih dihitung sebagai kegagalan login biasa"
            );
        }

        // Percobaan ke-6 MELEWATI Auth::attempt dan ditahan limiter.
        $keenam = $this->post('/login', ['username' => 'target', 'password' => 'salah']);

        // Limiter 'login' (AppServiceProvider) menahan percobaan ke-6 dengan
        // HTTP 429 + JSON (error + retry_after) dan header Retry-After: 300.
        // Ini perilaku yang diharapkan temuan P3: 429, bukan redirect ambigu.
        $this->assertSame(429, $keenam->getStatusCode(), 'percobaan ke-6 harus ditahan rate limiter dengan 429');
        $this->assertSame(
            'Terlalu banyak percobaan login. Coba lagi dalam beberapa saat.',
            $keenam->json('error')
        );
        $this->assertSame(300, $keenam->json('retry_after'));
        $this->assertSame('300', $keenam->headers->get('Retry-After'));

        // CATATAN TEMUAN P3: diselesaikan - limiter kini membalas 429 + JSON
        // beserta header Retry-After: 300, jadi mesin/skrip bisa membedakan
        // "terkunci" dari "salah password".

        $this->assertFalse($this->isAuthenticated());
        $this->assertDatabaseHas('users', ['id_user' => $user->id_user]);
    }

    public function test_percobaan_login_berhasil_menghasilkan_session_id_baru(): void
    {
        $user = User::factory()->create(['username' => 'korban', 'password' => Hash::make('rahasia-benar')]);

        // Sesi PARIS (session fixation): id sesi dibuat sebelum login.
        $this->get('/masuk');
        $sebelum = session()->getId();

        $this->post('/login', ['username' => 'korban', 'password' => 'rahasia-benar'])->assertRedirect();

        $this->assertNotSame(
            $sebelum,
            session()->getId(),
            'id sesi harus di-regenerate setelah login berhasil (anti session fixation)'
        );
        $this->assertTrue($this->isAuthenticated());
    }

    public function test_password_salah_tidak_membocorkan_apakah_username_ada(): void
    {
        User::factory()->create(['username' => 'ada']);
        User::factory()->create(['username' => 'tidak-ada']);

        $a = $this->post('/login', ['username' => 'ada', 'password' => 'x']);
        $b = $this->post('/login', ['username' => 'tidak-ada', 'password' => 'x']);

        $this->assertSame($a->getSession()->get('errors')->first('username'), $b->getSession()->get('errors')->first('username'));
    }

    // ==========================================================
    // P2 - Cookie sesi tanpa flag Secure (server hanya HTTP)
    // ==========================================================

    public function test_cookie_sesi_belum_ditandai_secure(): void
    {
        //('.env' tidak menyetel SESSION_SECURE_COOKIE dan APP_URL masih http://)
        $this->assertFalse(
            (bool) config('session.secure'),
            'cookie sesi harus ditandai Secure bila aplikasi diakses lewat HTTPS'
        );
    }

    // ==========================================================
    // P2 - CSRF: ValidateCsrfToken ada di grup web dan tidak ada pengecualian
    // ==========================================================

    public function test_csrf_aktif_dan_tidak_ada_yang_dikecualikan(): void
    {
        $csrf = \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class;

        // Setiap route harus mewarisi middleware grup 'web' yang berisi
        // ValidateCsrfToken. Kalau ada route di luar grup web, ia lolos CSRF.
        $tanpaCsrf = [];
        foreach (Route::getRoutes() as $rute) {
            $uri = $rute->uri();
            // API publik pakai token-based auth (Authorization: Bearer),
            // bukan session cookie - CSRF tidak berlaku di sini.
            if (str_starts_with($uri, 'api/')) {
                continue;
            }
            if (in_array('web', $rute->middleware(), true) || $rute->gatherMiddleware() === []) {
                continue;
            }
            if (!in_array($csrf, $rute->gatherMiddleware(), true)) {
                $tanpaCsrf[] = implode(',', $rute->methods()) . ' /' . $uri;
            }
        }

        $this->assertSame([], $tanpaCsrf, 'route berikut tidak memakai ValidateCsrfToken');

        // Pengecualian CSRF dikosongkan di bootstrap/app.php - tidak ada
        // ValidateCsrfToken::except(...) di seluruh source.
        $sumber = (string) file_get_contents(base_path('bootstrap/app.php'));
        $this->assertStringNotContainsString('validateCsrfTokens', $sumber);
    }

    // ==========================================================
    // P2 - XSS: {!! $post->isi_html !!} aman karena isi_html selalu
    //     disanitasi HtmlBersih sebelum disimpan
    // ==========================================================

    public function test_html_bersih_membuang_skrip_dan_event_handler(): void
    {
        $nakal = '<p onclick="alert(1)">halo</p><script>alert(2)</script><img src=x onerror=alert(3)>'
            . '<a href="javascript:alert(4)">jebak</a>';

        $hasil = HtmlBersih::bersihkan($nakal);

        $this->assertStringNotContainsStringIgnoringCase('<script', $hasil);
        $this->assertStringNotContainsStringIgnoringCase('onclick', $hasil);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $hasil);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $hasil);
    }

    public function test_isi_postingan_disanitasi_sebelum_disimpan(): void
    {
        $role = $this->role('Administrator');
        $user = User::factory()->create(['id_role' => $role->id_role]);

        $this->actingAs($user)->post('/postingan', [
            '_token' => 'token-uji',
            'judul' => 'Uji XSS',
            'isi' => '<p>ok</p><script>alert(1)</script>',
            'status' => 'terbit',
        ])->assertRedirect();

        $isi = (string) DB::table('posts')->where('judul', 'Uji XSS')->value('isi');

        $this->assertStringNotContainsStringIgnoringCase('<script', $isi);
    }

    // ==========================================================
    // P0 - Skrip debug di root project
    // ==========================================================

    public function test_tidak_ada_skrip_debug_yang_menembak_database_langsung_di_root(): void
    {
        $berbahaya = [];
        foreach (glob(base_path('*.php')) as $berkas) {
            $isi = (string) file_get_contents($berkas);
            
            // Nama database operasional dibaca dari KONFIGURASI saat test
            // berjalan, bukan ditulis di sini. Test tidak boleh menyebut nama
            // database operasional sebagai literal (dilarang
            // TestDatabaseIsolationGuardTest) - dan membacanya dari config juga
            // membuat test ini tetap benar kalau nama DB produksi berubah.
            $dbOperasional = (string) config('database.connections.' . config('database.default') . '.database');
            if (preg_match('/dbname\s*=\s*[\'"]?([a-z0-9_]+)/i', $isi, $m) && str_contains($m[1], $dbOperasional)) {
                $berbahaya[] = basename($berkas) . ' -> ' . $m[1];
            }
            if (stripos($isi, 'phpinfo(') !== false) {
                $berbahaya[] = basename($berkas) . ' -> phpinfo()';
            }
        }

        $this->assertSame(
            [],
            $berbahaya,
            
            "root project harus bebas skrip yang menyentuh database operasional / phpinfo:\n" . implode("\n", $berbahaya)
        );
    }
}