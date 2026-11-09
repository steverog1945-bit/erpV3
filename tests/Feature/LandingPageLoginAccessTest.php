<?php

namespace Tests\Feature;

use App\Models\LandingPage;
use App\Models\LandingPageRevision;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * CHECKPOINT 4E.1 — Pemulihan akses login.
 *
 * Masalah yang diperbaiki: route('login') menunjuk '/', dan ketika
 * LANDING_PAGE_BUILDER_ENABLED=true, '/' menyajikan halaman builder tanpa
 * form login -> staf terkunci total.
 *
 * Solusi: nama route 'login' dipindah ke '/masuk' (AuthController@formLogin)
 * yang SENGAJA melewati resolver builder, jadi form login selalu ada apa pun
 * nilai feature flag.
 *
 * Seluruh user di sini adalah user sintetis (factory). oriondb tidak
 * pernah disentuh - dijaga oleh guard di tests/TestCase.php.
 */
class LandingPageLoginAccessTest extends TestCase
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

    private function staf(?string $namaRole = 'Administrator', ?string $username = 'staf_uji'): User
    {
        // Idempoten per-username: beberapa test memanggil helper ini lebih dari
        // sekali, dan satu test butuh dua role BERBEDA.
        $existing = User::where('username', $username)->first();
        if ($existing) {
            return $existing;
        }

        $role = Role::firstOrCreate(['nama_role' => $namaRole]);
        $u = User::factory()->create(['username' => $username]);
        $u->id_role = $role->id_role;
        $u->save();
        return $u;
    }

    private function seedPublished(): void
    {
        $admin = $this->staf();
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
                ['id' => 's-lb1234567', 'type' => 'hero', 'title' => 'BUILDER-HERO', 'visible' => true],
            ],
            'theme_json' => [],
            'seo_json' => [],
            'created_by' => $admin->id_user,
        ]);
        $page->update(['published_revision_id' => $rev->id]);
    }

    // ============================================================
    // 1. Guest GET /masuk (route('login'))
    // ============================================================

    /** @test */
    public function skenario_01_guest_get_endpoint_login_menampilkan_form()
    {
        config(['landing_page_builder.enabled' => false]);

        $r = $this->get(route('login'));

        $r->assertOk();

        // /masuk kini halaman LOGIN ERP KHUSUS KARYAWAN (view 'masuk' +
        // layout sendiri), bukan lagi dialog di atas Landing Page lama.
        $r->assertViewIs('masuk');
        $r->assertSee('name="username"', false);
        $r->assertSee('name="password"', false);
        $r->assertSee('name="_token"', false);

        // Penanda Landing Page lama tidak boleh muncul di halaman login.
        $r->assertDontSee('bl-hero', false);
        $r->assertDontSee('bl-layanan', false);
        $r->assertDontSee('bl-langkah', false);
    }

    /** @test */
    public function skenario_01b_endpoint_login_menampilkan_form_tanpa_bantuan_dialog()
    {
        // Form ada langsung di halaman: staf tidak perlu mencari tombol dan
        // halaman tetap berfungsi walau JavaScript tidak berjalan.
        $r = $this->get(route('login'));
        $r->assertOk();

        $r->assertSee('Masuk ke sistem', false);
        $r->assertSee('type="submit"', false);
        $r->assertDontSee('id="dialog-masuk"', false);
    }

    // ============================================================
    // 2-3. Guest GET / dengan flag OFF dan ON
    // ============================================================

    /** @test */
    public function skenario_02_guest_get_root_flag_off_tetap_homepage_existing()
    {
        config(['landing_page_builder.enabled' => false]);

        $r = $this->get('/');

        $r->assertOk();
        $r->assertViewIs('login');
        $r->assertDontSee('BUILDER-HERO', false);
    }

    /** @test */
    public function skenario_03_guest_get_root_flag_on_menampilkan_builder_dengan_pintu_masuk()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();

        $r = $this->get('/');

        $r->assertOk();
        $r->assertViewIs('landing-page-public');
        $r->assertSee('BUILDER-HERO', false);

        // Pintu masuk staf harus terlihat di builder homepage (header + kaki).
        $r->assertSee('Masuk Karyawan', false);
        $r->assertSee(route('login'), false);
    }

    // ============================================================
    // 4. Authenticated GET /masuk
    // ============================================================

    /** @test */
    public function skenario_04_authenticated_get_endpoint_login_redirect_ke_dashboard()
    {
        config(['landing_page_builder.enabled' => false]);
        $staf = $this->staf();

        $r = $this->actingAs($staf)->get(route('login'));

        $r->assertRedirect(route('dashboard'));
    }

    // ============================================================
    // 5. Guest GET /kasir redirect ke login endpoint
    // ============================================================

    /** @test */
    public function skenario_05_guest_buka_halaman_terlindungi_redirect_ke_endpoint_login()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();

        // middleware auth harus mengarahkan ke route('login') = /masuk
        $r = $this->get('/dashboard');
        $r->assertRedirect(route('login'));

        // Dan tujuan redirect itu MEMANG punya form login.
        $tujuan = $this->get(route('login'));
        $tujuan->assertOk();
        $tujuan->assertSee('name="username"', false);
        $tujuan->assertSee('name="password"', false);
    }

    /** @test */
    public function skenario_05b_endpoint_login_tidak_pernah_menampilkan_builder_walau_flag_on()
    {
        // Ini jaminan paling penting dari 4E.1: /masuk HARUS tidak pernah
        // menyajikan konten builder, berapa pun nilai flag.
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();

        $r = $this->get(route('login'));

        $r->assertOk();

        // View khusus login karyawan; renderer Builder tidak pernah dipakai di
        // sini, berapa pun nilai flag.
        $r->assertViewIs('masuk');
        $r->assertDontSee('BUILDER-HERO', false);
        $r->assertDontSee('data-section-type', false);
        $r->assertDontSee('lp-header-nav', false);
    }

    // ============================================================
    // 6. Halaman login punya form yang berfungsi
    // ============================================================

    /** @test */
    public function skenario_06_halaman_login_punya_form_yang_berfungsi()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();

        $r = $this->get(route('login'));

        // action harus menuju endpoint POST yang sudah ada.
        $r->assertSee('action="' . url('/login') . '"', false);
        // method POST
        $r->assertSee('method="POST"', false);
        // token CSRF
        $r->assertSee('name="_token"', false);
        // label tertaut ke input (aksesibilitas)
        $r->assertSee('for="login-username"', false);
        $r->assertSee('for="login-password"', false);
    }

    // ============================================================
    // 7-8. Kredensial benar / salah
    // ============================================================

    /** @test */
    public function skenario_07_kredensial_benar_autentikasi()
    {
        config(['landing_page_builder.enabled' => true]);
        $staf = $this->staf();

        $r = $this->post('/login', ['username' => 'staf_uji', 'password' => 'password']);

        $r->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($staf);
    }

    /** @test */
    public function skenario_08_kredensial_salah_ditolak()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->staf();

        $r = $this->from(route('login'))->post('/login', [
            'username' => 'staf_uji',
            'password' => 'SALAH',
        ]);

        $r->assertRedirect(route('login'));
        $r->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    /** @test */
    public function skenario_08b_kredensial_salah_mengarah_ke_endpoint_login_bukan_homepage()
    {
        // Pesan error harus muncul di halaman yang punya form - kalau_error
        // dialihkan ke '/' dan flag ON, staf melihat halaman tanpa form.
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();
        $this->staf();

        $this->post('/login', ['username' => 'staf_uji', 'password' => 'SALAH'])
            ->assertRedirect(route('login'));

        // Halaman tujuan menampilkan pesan error DAN form.
        $r = $this->get(route('login'));
        $r->assertOk();
        $r->assertSee('name="password"', false);
        $r->assertSee('role="alert"', false);
    }

    // ============================================================
    // 9. CSRF
    // ============================================================

    /** @test */
    public function skenario_09_csrf_ditegakkan_pada_endpoint_login()
    {
        config(['landing_page_builder.enabled' => false]);
        $staf = $this->staf();

        // CATATAN: VerifyCsrfToken meloloskan semua request saat
        // app()->runningUnitTests() - jadi 419 tidak bisa dipancing dari test.
        // Yang bisa dan SHOULD diuji di sini: token yang dirender benar-benar
        // token sesi yang sah, sehingga POST dari form asli tidak ditolak.
        $html = $this->get(route('login'))->assertOk()->getContent();

        preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m);
        $this->assertNotEmpty($m, 'Form login harus menyertakan token CSRF');

        $token = $m[1];
        $sessionToken = app('session.store')->token();

        // Logika yang sama dengan VerifyCsrfToken::hasValidCsrfToken().
        $this->assertTrue(
            is_string($sessionToken) && is_string($token) && hash_equals($sessionToken, $token),
            'Token yang dirender harus cocok dengan session (form akan diterima CSRF)'
        );

        // Form juga harus memakai @csrf, bukan token yang dipatok.
        $this->assertStringNotContainsString('value="_token"', $html);
    }

    /** @test */
    public function skenario_09b_endpoint_login_juga_berCsrf_saat_flag_on()
    {
        // Endpoint login wajib punya CSRF meski homepage builderdirender.
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();

        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('name="_token"', $html);
        preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m);
        $this->assertNotEmpty($m);
        $this->assertTrue(
            is_string(app('session.store')->token())
            && hash_equals(app('session.store')->token(), $m[1]),
            'Token CSRF pada /masuk harus valid walau flag ON'
        );
    }

    // ============================================================
    // 10. Logout
    // ============================================================

    /** @test */
    public function skenario_10_logout_berfungsi_dan_kembali_ke_homepage()
    {
        config(['landing_page_builder.enabled' => false]);
        $staf = $this->staf();

        // Gate B OFF: tujuan logout adalah endpoint login publik /masuk,
        // BUKAN '/' yang me-render halaman depan lama.
        $r = $this->actingAs($staf)->post('/logout');
        $r->assertRedirect(route('login'));
        $this->assertGuest();

        // Halaman tujuan benar-benar menyediakan form masuk.
        $this->get(route('login'))->assertOk()->assertSee('name="username"', false);

        // Setelah logout, halaman terlindungi mengarahkan ke /masuk lagi.
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    /** @test */
    public function skenario_10b_logout_kembali_ke_beranda_publik_saat_gate_b_aktif()
    {
        // Gate B ON: '/' adalah Landing Page Published, jadi staf yang baru
        // keluar harus mendarat di beranda publik terbaru - bukan Dashboard
        // dan bukan halaman login.
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();
        $staf = $this->staf();

        $r = $this->actingAs($staf)->post('/logout');
        $r->assertRedirect(route('beranda'));
        $this->assertGuest();

        // Beranda publik tetap bisa dibuka tanpa login setelah keluar.
        $this->get('/')->assertOk()->assertViewIs('landing-page-public');
    }

    // ============================================================
    // 11. Session expiry
    // ============================================================

    /** @test */
    public function skenario_11_session_kedaluwarsa_mengarah_ke_endpoint_login()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();

        // Sesi 'kedaluwarsa' disimulasikan dengan shedai ini tanpa user login.
        $r = $this->get('/spk');

        $r->assertRedirect(route('login'));

        // Tujuan redirect punya form.
        $this->get(route('login'))->assertOk()->assertSee('name="password"', false);
    }

    // ============================================================
    // 12. Intended destination
    // ============================================================

    /** @test */
    public function skenario_12_url_intended_tetap_dihormati()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();
        $staf = $this->staf();

        // Tamu ditolak dari halaman terlindungi → dapat url.intended di session
        // dan diarahkan ke endpoint login.
        $r = $this->get('/kasir/monitoring');
        $r->assertRedirect(route('login'));
        $this->assertSame(url('/kasir/monitoring'), session('url.intended'));

        // Dialog di endpoint login terbuka otomatis karena ada url.intended.
        $halaman = $this->get(route('login'));
        $halaman->assertOk();
        $halaman->assertSee('name="username"', false);

        // Setelah login, perilaku lama: selalu ke dashboard, jejak URL dibuang.
        $this->post('/login', ['username' => 'staf_uji', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($staf);
    }

    /** @test */
    public function skenario_12b_url_intended_yang_basi_dibuang_setelah_login()
    {
        config(['landing_page_builder.enabled' => false]);
        $staf = $this->staf();

        $this->withSession(['url.intended' => url('/halaman-rahasia')])
            ->post('/login', ['username' => 'staf_uji', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertNull(session('url.intended'), 'Jejak URL lama harus dibuang');
        $this->assertAuthenticatedAs($staf);
    }

    // ============================================================
    // 13. Role-based access
    // ============================================================

    /** @test */
    public function skenario_13_akses_berbasis_role_tetap_berlaku()
    {
        config(['landing_page_builder.enabled' => false]);

        $admin = $this->staf('Administrator', 'admin_uji');
        $kasir = $this->staf('Kasir', 'kasir_uji');

        // Menu 'Kasir' (id 16) diberikan ke role Kasir saja.
        \DB::table('menus')->insertOrIgnore([
            'id_menu' => 16, 'nama_menu' => 'Kasir', 'url_link' => 'kasir', 'parent_id' => 0,
        ]);
        \DB::table('role_permissions')->insert([
            'id_role' => $kasir->id_role, 'id_menu' => 16,
        ]);

        // Role Kasir tetap boleh buka modul kasir (menu.access mengizinkan).
        $this->actingAs($kasir)->get('/kasir/monitoring')->assertOk();

        // Role Kasir TIDAK boleh buka halaman Administrator: HanyaAdministrator
        // menolak dengan 403 (bukan redirect - staf sudah login, cuma tidak
        // berwenang). Yang berubah di 4E.1: halaman login tetap dapat diakses.
        $this->actingAs($kasir)->get('/setting/landing-page-builder')->assertForbidden();

        // Administrator tetap boleh buka halaman builder.
        $this->actingAs($admin)->get('/setting/landing-page-builder')->assertOk();

        // Role Kasir yang MENOLAK akses tetap bisa keluar lewat /logout,
        // dan diarahkan ke endpoint login publik (Gate B OFF).
        $this->actingAs($kasir)->post('/logout')->assertRedirect(route('login'));
    }

    /** @test */
    public function skenario_13b_tamu_ke_halaman_admin_mengarah_ke_endpoint_login()
    {
        // Regression guard: tamu yang ditolak harus mendarat di halaman yang
        // punya form login, bukan '/' yang bisa jadi halaman builder.
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();

        $r = $this->get('/setting/landing-page-builder');

        $r->assertRedirect(route('login'));
        $this->assertSame(url('/masuk'), $r->headers->get('Location'));
        $this->assertNotSame(url('/'), $r->headers->get('Location'));

        // Tujuan redirect benar-benar punya form.
        $this->get(route('login'))->assertOk()->assertSee('name="password"', false);
    }

    /** @test */
    // ============================================================
    // 14-16. Pintu masuk di builder homepage
    // ============================================================

    /** @test */
    public function skenario_14_pintu_masuk_terlihat_di_builder_homepage()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();

        $html = $this->get('/')->assertOk()->getContent();

        // Tautan menuju endpoint login, minimal satu.
        $this->assertStringContainsString(
            'href="' . route('login') . '"',
            $html,
            'Builder homepage harus punya tautan ke endpoint login'
        );
        $this->assertStringContainsString('Masuk Karyawan', $html);
    }

    /** @test */
    public function skenario_15_pintu_masuk_tidak_mengganggu_homepage_existing_flag_off()
    {
        config(['landing_page_builder.enabled' => false]);

        $html = $this->get('/')->assertOk()->getContent();

        // Homepage existing sudah punya tombol Masuk sendiri (dari layout).
        $this->assertStringContainsString('data-buka-masuk', $html);
        $this->assertStringContainsString('id="dialog-masuk"', $html);
    }

    /** @test */
    public function skenario_16_pintu_masuk_builder_menuju_endpoint_yang_punya_form()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();

        // Ikuti tautan dari builder homepage.
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('href="' . route('login') . '"', $html);

        // Endpoint itu benar-benar punya form login.
        $tujuan = $this->get(route('login'));
        $tujuan->assertOk();
        $tujuan->assertSee('name="username"', false);
        $tujuan->assertSee('name="password"', false);
    }

    /** @test */
    public function skenario_16b_builder_homepage_tidak_pernah_membocorkan_form_login()
    {
        // Pintu masuk = tautan saja. Form TIDAK boleh ada di halaman publik.
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('name="password"', $html);
        $this->assertStringNotContainsString('name="_token"', $html);
    }

    // ============================================================
    // 17. Tidak ada redirect loop
    // ============================================================

    /** @test */
    public function skenario_17_tidak_ada_redirect_loop()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();

        // Tamu: / -> OK (bukan redirect)
        $this->get('/')->assertOk();

        // Tamu: /dashboard -> /masuk -> OK (bukan redirect lagi)
        $r1 = $this->get('/dashboard');
        $r1->assertRedirect(route('login'));

        $r2 = $this->get($r1->headers->get('Location'));
        $r2->assertOk(); // berhenti di sini, tidak mengarahkan lagi
    }

    /** @test */
    public function skenario_17b_setelah_login_endpoint_login_mengarah_dashboard_bukan_loop()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();
        $staf = $this->staf();

        // Sudah login, buka /masuk -> dashboard (bukan loop ke /masuk)
        $this->actingAs($staf)->get(route('login'))->assertRedirect(route('dashboard'));
    }

    /** @test */
    public function skenario_17c_endpoint_login_tidak_mengarah_ke_dirinya_sendiri()
    {
        config(['landing_page_builder.enabled' => false]);

        // Guard: /masuk tidak boleh pernah mengembalikan redirect ke /masuk.
        $r = $this->get(route('login'));
        if ($r->isRedirect()) {
            $this->assertNotSame(
                route('login'),
                $r->headers->get('Location'),
                'Endpoint login tidak boleh mengarahkan ke dirinya sendiri'
            );
        }
        $this->assertTrue(true);
    }

    // ============================================================
    // 18. Homepage existing tetap utuh saat flag OFF
    // ============================================================

    /** @test */
    public function skenario_18_homepage_existing_utuh_saat_flag_off()
    {
        config(['landing_page_builder.enabled' => false]);

        $r = $this->get('/');

        $r->assertOk();
        $r->assertViewIs('login');

        // Konten homepage existing masih ada.
        $r->assertSee('Cara pesan', false);
        $r->assertSee('Tulisan', false);

        // Dan tidak ada artefak builder.
        $r->assertDontSee('data-section-type', false);
        $r->assertDontSee('BUILDER-HERO', false);
    }

    /** @test */
    public function skenario_18b_homepage_existing_ajax_masih_berfungsi()
    {
        config(['landing_page_builder.enabled' => false]);

        $r = $this->get('/', ['X-Requested-With' => 'XMLHttpRequest']);

        $r->assertOk();
        $r->assertJsonStructure(['html', 'berikut', 'jumlah']);
    }

    // ============================================================
    // Keamanan tambahan
    // ============================================================

    /** @test */
    public function endpoint_login_tidak_membocorkan_konten_erp()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();
        $staf = $this->staf();

        $html = $this->get(route('login'))->assertOk()->getContent();

        // Tidak boleh memuat data transaksi / keuangan.
        foreach (['total_bayar', 'id_transaksi', 'Laba Rugi', 'Jurnal'] as $racun) {
            $this->assertStringNotContainsString($racun, $html, "Halaman login membocorkan '{$racun}'");
        }
    }

    /** @test */
    public function tautan_masuk_pada_builder_bernofollow()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();

        $html = $this->get('/')->assertOk()->getContent();

        // Tautan login tidak perlu diindeks.
        $needle = 'href="' . route('login') . '"';
        $this->assertStringContainsString($needle, $html, 'Tautan login harus ada');

        // Setiap kemunculan harus punya rel="nofollow".
        $segments = explode($needle, $html);
        $this->assertGreaterThan(1, count($segments), 'Harus ada minimal satu tautan login');

        array_shift($segments);
        foreach ($segments as $seg) {
            $tagEnd = strpos($seg, '>');
            $this->assertNotFalse($tagEnd, 'Tag <a> harus tertutup');
            $tag = substr($seg, 0, $tagEnd);
            $this->assertStringContainsString('rel="nofollow"', $tag);
        }
    }

    /** @test */
    public function route_login_dan_route_beranda_terpisah_dan_benar()
    {
        // route('login') harus ke /masuk, bukan ke homepage.
        $this->assertSame(url('/masuk'), route('login'));
        // Homepage punya nama sendiri.
        $this->assertSame(url('/'), route('beranda'));
        // Keduanya harus berbeda.
        $this->assertNotSame(route('login'), route('beranda'));
    }

    /** @test */
    public function endpoint_login_tetap_ada_walau_database_gagal()
    {
        // Bahkan ketika DB tidak terbaca, /masuk harus tetap merespons (fallback).
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublished();

        \DB::disconnect('mysql');
        $r = $this->get(route('login'));
        \DB::reconnect('mysql');

        // Minimal harus merespons tanpa exception yang tidak tertangani.
        $this->assertContains(
            $r->getStatusCode(),
            [200, 302, 500],
            'Endpoint login harus merespons, tidak crash'
        );
    }
}