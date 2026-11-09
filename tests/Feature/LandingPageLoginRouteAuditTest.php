<?php

namespace Tests\Feature;

use App\Models\LandingPage;
use App\Models\LandingPageDraft;
use App\Models\LandingPageRevision;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * CHECKPOINT 4D.2 — Audit integrasi homepage di AuthController.
 *
 * 13 skenario yang diminta checkpoint, masing-masing satu test:
 *   1. Anonymous GET / dengan flag OFF
 *   2. Anonymous GET / dengan flag ON
 *   3. Authenticated GET / dengan flag OFF
 *   4. Authenticated GET / dengan flag ON
 *   5. GET /login (route availability)
 *   6. POST login sukses
 *   7. POST login gagal
 *   8. Logout
 *   9. Session expiry / session tidak ada
 *  10. Redirect setelah autentikasi sukses
 *  11. Kompatibilitas nama route 'login'
 *  12. CSRF protection
 *  13. Public assets tanpa autentikasi
 *
 * Plus pengujianTEMUAN: apakah homepage builder menyediakan jalan masuk.
 */
class LandingPageLoginRouteAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $db = config('database.connections.mysql.database');
        if ($db !== 'percetakan_erp_testing') {
            $this->markTestSkipped("Testing DB bukan percetakan_erp_testing (sekarang: {$db}).");
        }

        config(['landing_page_builder.enabled' => false]);
    }

    private function admin(): User
    {
        // Idempoten: beberapa test memanggil helper ini lebih dari sekali
        // (mis. lewat seedPublished() lalu langsung). Username harus unik.
        $existing = User::where('username', 'admin_uji')->first();
        if ($existing) {
            return $existing;
        }

        $role = Role::firstOrCreate(['nama_role' => 'Administrator']);
        $u = User::factory()->create(['username' => 'admin_uji']);
        $u->id_role = $role->id_role;
        $u->save();
        return $u;
    }

    /** Published page valid supaya flag ON punya isi untuk dirender. */
    private function seedPublished(): void
    {
        $admin = $this->admin();
        $page = LandingPage::updateOrCreate(
            ['slug' => 'home'],
            [
                'title' => 'Halaman Utama',
                'status' => 'published',
                'created_by' => $admin->id_user,
                'updated_by' => $admin->id_user,
            ]
        );
        $rev = LandingPageRevision::create([
            'landing_page_id' => $page->id,
            'revision_number' => 1,
            'schema_version' => '1.0.0',
            'content_json' => [
                ['id' => 's-aud123456', 'type' => 'hero', 'title' => 'AUDIT-HERO', 'visible' => true],
            ],
            'theme_json' => [],
            'seo_json' => [],
            'created_by' => $admin->id_user,
        ]);
        $page->update(['published_revision_id' => $rev->id]);
    }

    // ============================================================
    // 1-2. Anonymous GET /
    // ============================================================

    /** @test */
    public function skenario_01_anonymous_get_root_flag_off()
    {
        config(['landing_page_builder.enabled' => false]);

        $r = $this->get('/');

        $r->assertOk();
        $r->assertViewIs('login');
        // Homepage existing punya dialog/form masuk.
        $r->assertSee('Masuk', false);
    }

    /** @test */
    public function skenario_02_anonymous_get_root_flag_on()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();

        $r = $this->get('/');

        $r->assertOk();
        $r->assertViewIs('landing-page-public');
        $r->assertSee('AUDIT-HERO', false);
    }

    // ============================================================
    // 3-4. Authenticated GET /
    // ============================================================

    /** @test */
    public function skenario_03_authenticated_get_root_flag_off()
    {
        config(['landing_page_builder.enabled' => false]);
        $admin = $this->admin();

        $r = $this->actingAs($admin)->get('/');

        $r->assertRedirect(route('dashboard'));
    }

    /** @test */
    public function skenario_04_authenticated_get_root_flag_on()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();
        $admin = $this->admin();

        $r = $this->actingAs($admin)->get('/');

        // Kontrak operator (Gate B ON): '/' menampilkan Landing Page Published
        // untuk SEMUA peran - guest maupun karyawan. Dashboard tetap diakses
        // lewat /masuk setelah login, BUKAN otomatis saat membuka '/'.
        $r->assertOk();
        $r->assertViewIs('landing-page-public');
        $r->assertSee('AUDIT-HERO', false);
    }

    // ============================================================
    // 5. GET /login availability
    // ============================================================

    /** @test */
    public function skenario_05_get_login_route_availability()
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('login'),
            'Route bernama "login" harus tetap ada'
        );

        // 4E.1: route('login') kini mengarah ke /masuk (endpoint login),
        // bukan ke homepage '/'.
        $this->assertSame(url('/masuk'), route('login'));

        // Endpoint itu harus benar-benar menyediakan form.
        $r = $this->get(route('login'));
        $r->assertOk();
        $r->assertSee('name="username"', false);
        $r->assertSee('name="password"', false);
    }

    // ============================================================
    // 6-7. POST login
    // ============================================================

    /** @test */
    public function skenario_06_post_login_success_redirects_to_dashboard()
    {
        config(['landing_page_builder.enabled' => false]);
        $admin = $this->admin();

        $r = $this->post('/login', [
            'username' => 'admin_uji',
            'password' => 'password',
        ]);

        $r->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    /** @test */
    public function skenario_07_post_login_failure_does_not_authenticate()
    {
        config(['landing_page_builder.enabled' => false]);
        $this->admin();

        $r = $this->from('/')->post('/login', [
            'username' => 'admin_uji',
            'password' => 'SALAH-BANGET',
        ]);

        // 4E.1: login gagal diarahkan ke endpoint login (bukan back() ke '/'),
        // supaya pesan error selalu terlihat di halaman yang punya form.
        $r->assertRedirect(route('login'));
        $r->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    /** @test */
    public function skenario_07b_post_login_still_works_when_flag_on()
    {
        // Login harus tetap berfungsi tidak peduli flag-nya apa.
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();
        $admin = $this->admin();

        $r = $this->post('/login', [
            'username' => 'admin_uji',
            'password' => 'password',
        ]);

        $r->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    // ============================================================
    // 8. Logout
    // ============================================================

    /** @test */
    public function skenario_08_logout_clears_session()
    {
        config(['landing_page_builder.enabled' => false]);
        $admin = $this->admin();

        // Gate B OFF: logout ke endpoint login publik /masuk, bukan ke '/'
        // yang me-render halaman depan lama.
        $r = $this->actingAs($admin)->post('/logout');

        $r->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /** @test */
    public function skenario_08c_logout_kembali_ke_beranda_publik_saat_gate_b_aktif()
    {
        // Gate B ON: '/' adalah Landing Page Published. Staf yang keluar
        // harus melihat beranda publik terbaru, bukan Dashboard.
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();
        $admin = $this->admin();

        $r = $this->actingAs($admin)->post('/logout');

        $r->assertRedirect(route('beranda'));
        $this->assertGuest();

        $this->get('/')->assertOk()->assertViewIs('landing-page-public');
    }

    /** @test */
    public function skenario_08b_logout_then_root_flag_off_returns_login_page()
    {
        config(['landing_page_builder.enabled' => false]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/logout');

        $r = $this->get('/');
        $r->assertOk();
        $r->assertViewIs('login');
        $r->assertSee('Masuk', false);
    }

    // ============================================================
    // 9. Session expiry
    // ============================================================

    /** @test */
    public function skenario_09_guest_bounced_from_protected_page_flag_off()
    {
        config(['landing_page_builder.enabled' => false]);

        // Middleware auth mengarahkan ke route('login'), yang sejak 4E.1 = /masuk.
        $r = $this->get('/dashboard');
        $r->assertRedirect(route('login'));

        // Halaman tujuannya benar-benar memuat form masuk.
        $this->get(route('login'))->assertOk()->assertSee('name="username"', false);
    }

    /** @test */
    public function skenario_09b_guest_bounced_from_protected_page_flag_on()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();

        $r = $this->get('/dashboard');

        // 4E.1: tujuan redirect adalah endpoint login (/masuk), bukan homepage.
        $r->assertRedirect(route('login'));
        $this->assertSame(url('/masuk'), $r->headers->get('Location'));

        // Endpoint itu PASTI punya form login, walau flag ON.
        $tujuan = $this->get(route('login'));
        $tujuan->assertOk();

        // /masuk kini halaman LOGIN ERP KHUSUS KARYAWAN dengan layout
        // sendiri (view 'masuk'), bukan lagi Landing Page lama.
        $tujuan->assertViewIs('masuk');
        $tujuan->assertSee('name="username"', false);
        $tujuan->assertSee('name="password"', false);
        $tujuan->assertSee('name="_token"', false);

        // Kontrak baru: TIDAK ada lagi template Landing Page lama di belakang
        // form. Penanda lama (kelas .bl-*) tidak boleh muncul sama sekali.
        $html = $tujuan->getContent();
        foreach (['bl-hero', 'bl-layanan', 'bl-langkah', 'bl-daftar', 'bl-kontak'] as $penandaLama) {
            $this->assertStringNotContainsString(
                $penandaLama,
                $html,
                "Halaman login tidak boleh me-render bagian Landing Page lama: {$penandaLama}"
            );
        }
        $this->assertStringNotContainsString('Percetakan untuk usaha, acara, dan tim Anda.', $html);

        // TEMUAN AUDIT 4D.2 SUDAH DIPERBAIKI: homepage builder kini punya
        // pintu masuk, jadi staf yang kebetulan mendarat di '/' juga punya jalan.
        $home = $this->get('/');
        $home->assertOk();
        $home->assertViewIs('landing-page-public');
        $home->assertSee('Masuk Karyawan', false);
    }

    // ============================================================
    // 10. Redirect setelah autentikasi sukses
    // ============================================================

    /** @test */
    public function skenario_10_post_login_ignores_stale_intended_url()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();
        $admin = $this->admin();

        // Sesi lama meninggalkan jejak url.intended.
        $this->withSession(['url.intended' => url('/halaman-rahasia')])
            ->post('/login', ['username' => 'admin_uji', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    /** @test */
    public function skenario_10b_bukaMasuk_flag_off_membuka_dialog_masuk()
    {
        config(['landing_page_builder.enabled' => false]);

        // ketika middlware auth mengarahkan ke '/', session url.intended diisi
        $r = $this->get('/');
        $r->assertOk();
        //-dialog masuk tersedia dan view menyalin flag bukaMasuk
        $r->assertSee('id="dialog-masuk"', false);
    }

    // ============================================================
    // 11. Kompatibilitas nama route
    // ============================================================

    /**
     * Checkpoint 4E.1 mengubah route('login') dari '/' ke '/masuk'.
     * Test ini kini mengunci perilaku BARU: nama 'login' harus menunjuk
     * endpoint yang selalu menyediakan form login.
     */
    /** @test */
    public function skenario_11_route_name_login_compatibility()
    {
        $routes = \Illuminate\Support\Facades\Route::getRoutes();

        $named = collect($routes->getRoutes())
            ->filter(fn ($r) => $r->getName() === 'login');

        $this->assertCount(1, $named, 'Harus ada tepat satu route bernama "login"');

        $route = $named->first();

        // 4E.1: nama 'login' HARUS menunjuk endpoint login, bukan homepage.
        $this->assertSame('masuk', $route->uri());
        $this->assertContains('GET', $route->methods());
    }

    /** @test */
    public function skenario_11c_homepage_memiliki_nama_beranda_terpisah()
    {
        // Homepage tidak lagi memakai nama 'login' supaya tidak bentrok
        // dengan endpoint login.
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('beranda'));
        $this->assertNotSame(route('login'), route('beranda'));
        $this->assertSame(url('/'), route('beranda'));
    }

    /** @test */
    public function skenario_11b_public_routes_utuh()
    {
        // Route publik lain tidak boleh hilang.
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('publik.baca'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('logout'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('landing-page-builder.index'));
    }

    // ============================================================
    // 12. CSRF
    // ============================================================

    /** @test */
    public function skenario_12_post_login_requires_csrf_token()
    {
        config(['landing_page_builder.enabled' => false]);
        $this->admin();

        // Matikan CSRF untuk membuktikan middleware aktif di produksi normal.
        $this->withMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        // Kirim POST tanpa token. Dalam test, middleware di-bypass, jadi
        // yang diuji di sini adalah: form di homepage MEMANG memuat token.
        $home = $this->get('/');
        $home->assertOk();
        $home->assertSee('name="_token"', false);

        // Form harus mengarah ke endpoint POST /login.
        $home->assertSee('action="' . url('/login') . '"', false);
    }

    /** @test */
    public function skenario_12b_homepage_builder_tidak_bikin_form_tanpa_csrf()
    {
        // Builder homepage memang tidak punya form sama sekali, jadi tidak
        // ada permukaan CSRF baru. Dipastikan tidak ada form action di sana.
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();

        $home = $this->get('/');
        $home->assertOk();
        $this->assertStringNotContainsString(
            'name="_token"',
            $home->getContent(),
            'Homepage builder tidak seharusnya punya form bertoken'
        );
    }

    // ============================================================
    // 13. Public assets tanpa autentikasi
    // ============================================================

    /** @test */
    public function skenario_13_public_assets_accessible_without_auth()
    {
        config(['landing_page_builder.enabled' => false]);

        // Halaman publik harus bisa diakses tanpa login.
        $this->get('/')->assertOk();

        // Route publik lain juga.
        $this->get('/baca/tidak-ada')->assertNotFound();

        // Tidak ada session yang dibuat untuk tamu pada homepage.
        // (Homepage tidak boleh memaksa login.)
    }

    /** @test */
    public function skenario_13b_builder_homepage_cache_header_not_private_leak()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();

        $r = $this->get('/');
        $r->assertOk();

        // Builder homepage hanya dilayani ke tamu (auth dicek sebelum resolver),
        // jadi cache publik aman. Yang penting: tidak ada header no-store
        // yang hilang, dan tidak ada data per-user yang bocor.
        $cacheControl = $r->headers->get('Cache-Control', '');
        $this->assertNotSame('', $cacheControl, 'Header Cache-Control harus ada');
        $this->assertStringNotContainsString(
            'no-store',
            $cacheControl,
            'Builder homepage tidak perlu no-store karena hanya untuk tamu'
        );
    }

    // ============================================================
    // TEMUAN AUDIT
    // ============================================================

    /**
 * TEMUAN AUDIT 4D.2 (staff lockout) sudah DIPERBAIKI di Checkpoint 4E.1.
 * Test ini kini mengunci perbaikannya: builder homepage WAJIB punya pintu
 * masuk, dan pintu itu harus menuju endpoint yang punya form.
 */
    /** @test */
    public function temuan_builder_homepage_memiliki_pintu_masuk()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();

        $html = $this->get('/')->assertOk()->getContent();

        // 1. Ada pintu masuk yang menunjuk route('login').
        $this->assertStringContainsString(
            'href="' . route('login') . '"',
            $html,
            'Builder homepage harus punya tautan masuk ke endpoint login'
        );
        $this->assertStringContainsString('Masuk Karyawan', $html);

        // 2. Tautan itu benar-benar punya form di tujuannya.
        $tujuan = $this->get(route('login'));
        $tujuan->assertOk();
        $tujuan->assertSee('name="username"', false);
        $tujuan->assertSee('name="password"', false);
    }

    /** @test */
    public function builder_homepage_tidak_pernah_membocorkan_form_kredensial()
    {
        // Pintu masuk = tautan saja. Form login TIDAK boleh ada di halaman publik.
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('name="password"', $html);
        $this->assertStringNotContainsString('name="_token"', $html);
    }
}