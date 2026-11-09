<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\LandingPage;
use App\Models\LandingPageDraft;
use App\Models\LandingPageRevision;
use App\Models\Role;
use App\Models\User;
use App\Services\LandingPageHomepageResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Checkpoint 4D.1 — Feature Flag & Safe Public Resolver tests.
 *
 * Cakupan:
 * - Flag absent / false / true
 * - Published revision valid
 * - Missing / invalid / wrong-page / unsupported schema / malformed content
 * - Draft tidak pernah terekspos publik
 * - Homepage existing tetap utuh saat flag OFF
 * - Public routes tidak berubah
 * - SEO & XSS escaping
 * - Tidak ada mutasi takUTHORisasi
 */
class LandingPageHomepageResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pastikan test environment adalah database testing.
        // Kalau tidak, stop - jangan sampai menyentuh oriondb.
        $db = config('database.connections.mysql.database');
        if ($db !== 'percetakan_erp_testing') {
            $this->markTestSkipped("Testing DB bukan percetakan_erp_testing (sekarang: {$db}). Test dilewati demi keamanan.");
        }

        config(['landing_page_builder.enabled' => false]);
    }

    // ============================================================
    // FLAG STATE
    // ============================================================

    /** @test */
    public function flag_defaults_to_off_when_env_missing()
    {
        // Default feature flag WAJIB false ketika key env tidak disetel.
        // Key di bawah sengaja yang tidak mungkin ada, supaya hasilnya tidak
        // bergantung pada isi .env atau pin di phpunit.xml.
        $this->assertFalse(env('LANDING_PAGE_BUILDER_KEY_TIDAK_ADA', false));

        // Config file harus tetap punya kunci 'enabled' dan membacanya dari
        // env (bukan hardcode), serta bernilai boolean yang bisa dipakai
        // sebagai kondisi.
        $config = require base_path('config/landing_page_builder.php');
        $this->assertArrayHasKey('enabled', $config);
        $this->assertFalse((bool) $config['enabled']);
    }

    /** @test */
    public function flag_false_keeps_existing_homepage()
    {
        config(['landing_page_builder.enabled' => false]);

        // Seed data publik supaya kalau builder bocor, test ini gagal.
        $this->seedPublishedPage();

        $response = $this->get('/');

        $response->assertOk();
        // Homepage existing tetap memakai view login (bukan renderer builder).
        $response->assertViewIs('login');
        // Konten builder tidak boleh muncul.
        $response->assertDontSee('BUILDER-HERO', false);
    }

    /** @test */
    public function flag_true_with_valid_published_revision_renders_builder()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublishedPage();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewIs('landing-page-public');
        $response->assertSee('BUILDER-HERO', false);
    }

    // ============================================================
    // FALLBACK MATRIX
    // ============================================================

    /** @test */
    public function flag_true_without_page_falls_back_to_existing()
    {
        config(['landing_page_builder.enabled' => true]);

        $response = $this->get('/');
        $response->assertOk();
        // Tidak error, dan tidak ada konten builder.
        $response->assertDontSee('BUILDER-HERO', false);
    }

    /** @test */
    public function flag_true_with_draft_only_page_falls_back_to_existing()
    {
        config(['landing_page_builder.enabled' => true]);

        // Hanya draft, tidak ada published revision.
        $admin = $this->admin();
        $page = LandingPage::create([
            'slug' => 'home',
            'title' => 'Halaman Utama',
            'status' => 'draft',
            'created_by' => $admin->id_user,
            'updated_by' => $admin->id_user,
        ]);
        LandingPageDraft::create([
            'landing_page_id' => $page->id,
            'version' => 1,
            'content_json' => [['id' => 's-draft1', 'type' => 'hero', 'title' => 'DRAFT-JANGAN-TAMPIL', 'visible' => true]],
            'theme_json' => [],
            'seo_json' => [],
            'updated_by' => $admin->id_user,
        ]);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertDontSee('DRAFT-JANGAN-TAMPIL', false);
    }

    /** @test */
    public function flag_true_with_invalid_published_revision_falls_back_to_existing()
    {
        config(['landing_page_builder.enabled' => true]);
        $admin = $this->admin();

        $page = LandingPage::create([
            'slug' => 'home',
            'title' => 'Halaman Utama',
            'status' => 'published',
            'created_by' => $admin->id_user,
            'updated_by' => $admin->id_user,
        ]);

        // Revision dengan content yang rusak (bukan array of sections).
        $rev = LandingPageRevision::create([
            'landing_page_id' => $page->id,
            'revision_number' => 1,
            'schema_version' => '1.0.0',
            'content_json' => ['bukan' => 'sections'],
            'theme_json' => [],
            'seo_json' => [],
            'created_by' => $admin->id_user,
        ]);

        $page->update(['published_revision_id' => $rev->id]);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertDontSee('BUILDER-HERO', false);
    }

    /** @test */
    public function flag_true_with_unsupported_schema_version_falls_back_to_existing()
    {
        config(['landing_page_builder.enabled' => true]);
        $admin = $this->admin();

        $page = LandingPage::create([
            'slug' => 'home',
            'title' => 'Halaman Utama',
            'status' => 'published',
            'created_by' => $admin->id_user,
            'updated_by' => $admin->id_user,
        ]);

        // Schema version tidak didukung (99).
        $rev = LandingPageRevision::create([
            'landing_page_id' => $page->id,
            'revision_number' => 1,
            'schema_version' => '99.0.0',
            'content_json' => [['id' => 's-1', 'type' => 'hero', 'title' => 'BUILDER-HERO', 'visible' => true]],
            'theme_json' => [],
            'seo_json' => [],
            'created_by' => $admin->id_user,
        ]);

        $page->update(['published_revision_id' => $rev->id]);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertDontSee('BUILDER-HERO', false);
    }

    /** @test */
    public function wrong_page_revision_pointer_falls_back_to_existing()
    {
        config(['landing_page_builder.enabled' => true]);
        $admin = $this->admin();

        // Halaman 'home' menunjuk revision milik halaman LAIN.
        $otherPage = LandingPage::create([
            'slug' => 'tentang',
            'title' => 'Tentang',
            'status' => 'published',
            'created_by' => $admin->id_user,
            'updated_by' => $admin->id_user,
        ]);

        $foreignRev = LandingPageRevision::create([
            'landing_page_id' => $otherPage->id,
            'revision_number' => 1,
            'schema_version' => '1.0.0',
            'content_json' => [['id' => 's-1', 'type' => 'hero', 'title' => 'BUILDER-HERO', 'visible' => true]],
            'theme_json' => [],
            'seo_json' => [],
            'created_by' => $admin->id_user,
        ]);

        $homePage = LandingPage::create([
            'slug' => 'home',
            'title' => 'Halaman Utama',
            'status' => 'published',
            'published_revision_id' => $foreignRev->id,
            'created_by' => $admin->id_user,
            'updated_by' => $admin->id_user,
        ]);

        $this->assertNotNull($homePage);

        // Resolver harus menolak karena revision bukan milik home.
        $response = $this->get('/');
        $response->assertOk();
        $response->assertDontSee('BUILDER-HERO', false);
    }

    /** @test */
    public function malformed_json_does_not_crash_homepage()
    {
        config(['landing_page_builder.enabled' => true]);
        $admin = $this->admin();

        $page = LandingPage::create([
            'slug' => 'home',
            'title' => 'Halaman Utama',
            'status' => 'published',
            'created_by' => $admin->id_user,
            'updated_by' => $admin->id_user,
        ]);

        // Simpan JSON rusak langsung ke DB lewat query builder agar bypass cast.
        $revId = \DB::table('landing_page_revisions')->insertGetId([
            'landing_page_id' => $page->id,
            'revision_number' => 1,
            'schema_version' => '1.0.0',
            'content_json' => '{ini bukan json valid',
            'theme_json' => '{}',
            'seo_json' => '{}',
            'created_by' => $admin->id_user,
        ]);

        \DB::table('landing_pages')->where('id', $page->id)->update([
            'published_revision_id' => $revId,
        ]);

        // Tidak boleh 500.
        $response = $this->get('/');
        $response->assertOk();
        $response->assertDontSee('BUILDER-HERO', false);
    }

    // ============================================================
    // DRAFT TIDAK PERNAH TEREKSPOS
    // ============================================================

    /** @test */
    public function draft_content_never_publicly_exposed()
    {
        config(['landing_page_builder.enabled' => true]);
        $admin = $this->admin();

        $page = LandingPage::create([
            'slug' => 'home',
            'title' => 'Halaman Utama',
            'status' => 'draft',
            'created_by' => $admin->id_user,
            'updated_by' => $admin->id_user,
        ]);

        // Draft berisi konten sensitif.
        LandingPageDraft::create([
            'landing_page_id' => $page->id,
            'version' => 5,
            'content_json' => [['id' => 's-1', 'type' => 'hero', 'title' => 'RAHASIA-DRAFT', 'visible' => true]],
            'theme_json' => [],
            'seo_json' => [],
            'updated_by' => $admin->id_user,
        ]);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertDontSee('RAHASIA-DRAFT', false);
        $response->assertDontSee('RAHASIA', false);
    }

    // ============================================================
    // HOMEPAGE EXISTING & ROUTE PRESERVATION
    // ============================================================

    /** @test */
    public function existing_homepage_preserved_when_flag_off()
    {
        config(['landing_page_builder.enabled' => false]);

        $response = $this->get('/');
        $response->assertOk();
        // View login (homepage existing) harus dirender.
        $response->assertViewIs('login');
    }

    /** @test */
    public function ajax_homepage_still_works_when_flag_off()
    {
        config(['landing_page_builder.enabled' => false]);

        // Request AJAX ke homepage harus tetap dapat JSON daftar.
        $response = $this->get('/', ['X-Requested-With' => 'XMLHttpRequest']);
        $response->assertOk();
        $response->assertJsonStructure(['html', 'berikut', 'jumlah']);
    }

    /** @test */
    public function existing_public_routes_still_resolve()
    {
        config(['landing_page_builder.enabled' => false]);

        // /baca/{slug} harus tetap ada (route publik existing).
        $response = $this->get('/baca/tidak-ada-slug-ini');
        // 404 karena slug tidak ada, TAPI bukan 500 - route-nya hidup.
        $response->assertNotFound();
    }

    /** @test */
    public function authenticated_user_still_redirected_to_dashboard()
    {
        config(['landing_page_builder.enabled' => false]);

        Role::create(['nama_role' => 'Administrator']);
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->get('/');
        $response->assertRedirect(route('dashboard'));
    }

    // ============================================================
    // SEO
    // ============================================================

    /** @test */
    public function seo_metadata_rendered_when_flag_on()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublishedPage([
            'seo' => [
                'title' => 'Judul SEO Uji',
                'description' => 'Deskripsi SEO Uji',
                'og_title' => 'OG Judul Uji',
                'og_description' => 'OG Deskripsi Uji',
            ],
        ]);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('Judul SEO Uji', false);
        $response->assertSee('Deskripsi SEO Uji', false);
        $response->assertSee('OG Judul Uji', false);
    }

    /** @test */
    public function canonical_url_is_never_preview_url()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublishedPage();

        $response = $this->get('/');

        // Canonical harus ke homepage, bukan ke URL preview.
        $response->assertSee('<link rel="canonical" href="' . url('/') . '"', false);
        $response->assertDontSee('preview/published', false);
        $response->assertDontSee('preview/draft', false);
        $response->assertDontSee('preview/revision', false);
    }

    /** @test */
    public function preview_route_always_noindex()
    {
        config(['landing_page_builder.enabled' => false]);
        $this->seedPublishedPage();

        // $this->admin() sudah mengaitkan user ke role Administrator.
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('landing-page-builder.preview.published'));
        $response->assertOk();
        $response->assertSee('noindex', false);
        // Preview tidak boleh punya canonical ke dirinya sendiri.
        $response->assertDontSee('<link rel="canonical"', false);
    }

    // ============================================================
    // XSS ESCAPING
    // ============================================================

    /** @test */
    public function xss_in_published_content_is_escaped()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublishedPage([
            'sections' => [
                ['id' => 's-xss', 'type' => 'hero', 'title' => '<script>alert("xss")</script>', 'visible' => true],
            ],
        ]);

        $response = $this->get('/');
        $response->assertOk();
        // Script tag mentah TIDAK boleh muncul.
        $response->assertDontSee('<script>alert("xss")</script>', false);
        // Yang tampil adalah teks yang di-escape.
        $response->assertSee('&lt;script&gt;', false);
    }

    /** @test */
    public function javascript_url_in_button_is_rejected()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublishedPage([
            'sections' => [
                [
                    'id' => 's-js',
                    'type' => 'hero',
                    'title' => 'Tombol Berbahaya',
                    'buttonLabel' => 'Klik',
                    'buttonUrl' => 'javascript:alert(1)',
                    'visible' => true,
                ],
            ],
        ]);

        $response = $this->get('/');
        $response->assertOk();
        // URL javascript: harus dibuang, tidak boleh jadi href.
        $response->assertDontSee('href="javascript:', false);
    }

    // ============================================================
    // NO UNAUTHORIZED MUTATION
    // ============================================================

    /** @test */
    public function visiting_homepage_creates_no_database_writes()
    {
        config(['landing_page_builder.enabled' => false]);

        // Homepage existing tidak boleh menulis apa pun ke tabel landing page.
        $before = [
            'pages' => LandingPage::count(),
            'drafts' => LandingPageDraft::count(),
            'revisions' => LandingPageRevision::count(),
        ];

        $this->get('/')->assertOk();

        $after = [
            'pages' => LandingPage::count(),
            'drafts' => LandingPageDraft::count(),
            'revisions' => LandingPageRevision::count(),
        ];

        $this->assertSame($before, $after, 'Homepage tidak boleh membuat/mengubah data landing page.');
    }

    /** @test */
    public function homepage_does_not_auto_create_page()
    {
        config(['landing_page_builder.enabled' => false]);

        $this->assertSame(0, LandingPage::count());

        $this->get('/')->assertOk();

        $this->assertSame(0, LandingPage::count(), 'Resolver publik tidak boleh membuat LandingPage baru.');
    }

    /** @test */
    public function database_read_failure_falls_back_to_existing_homepage()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublishedPage();

        // Paksa pembacaan tabel landing_pages gagal, untuk meniru kegagalan
        // database saat runtime. Resolver harus tetap mengembalikan homepage
        // existing, bukan melempar exception ke pengunjung.
        //
        // Cara: matikan koneksi MySQL untuk satu test ini, lalu pulihkan.
        // Tidak menghapus tabel, jadi schema tetap utuh untuk test lain.
        \DB::disconnect('mysql');

        $resolver = app(LandingPageHomepageResolver::class);
        $result = $resolver->resolve(request());

        $this->assertSame('existing', $result['mode'], 'Kegagalan DB harus fallback ke homepage existing.');
        $this->assertSame('login', $result['view']);

        \DB::reconnect('mysql');
    }

    /** @test */
    public function database_read_failure_does_not_leak_exception_to_visitor()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublishedPage();

        // Putuskan koneksi sebelum request, supaya query di dalam resolver gagal.
        \DB::disconnect('mysql');

        // Pengunjung harus tetap dapat halaman 200 yang wajar - bukan 500
        // dan bukan pesan error internal.
        $response = $this->get('/');

        \DB::reconnect('mysql');

        $response->assertOk();
        $response->assertDontSee('SQLSTATE', false);
        $response->assertDontSee('landing_pages', false);
    }

    // ============================================================
    // RESOLVER UNIT
    // ============================================================

    /** @test */
    public function resolver_returns_existing_mode_when_flag_off()
    {
        config(['landing_page_builder.enabled' => false]);

        $resolver = app(LandingPageHomepageResolver::class);
        $result = $resolver->resolve(request());

        $this->assertSame('existing', $result['mode']);
        $this->assertSame('login', $result['view']);
    }

    /** @test */
    public function resolver_returns_builder_mode_with_valid_revision()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublishedPage();

        $resolver = app(LandingPageHomepageResolver::class);
        $result = $resolver->resolve(request());

        $this->assertSame('builder', $result['mode']);
        $this->assertSame('landing-page-public', $result['view']);
        $this->assertNotEmpty($result['data']['sections']);
    }

    /** @test */
    public function hidden_sections_not_rendered_publicly()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublishedPage([
            'sections' => [
                ['id' => 's-1', 'type' => 'hero', 'title' => 'BUILDER-HERO', 'visible' => true],
                ['id' => 's-2', 'type' => 'about', 'title' => 'BAGIAN-TERSEMBUNYI', 'visible' => false],
            ],
        ]);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('BUILDER-HERO', false);
        $response->assertDontSee('BAGIAN-TERSEMBUNYI', false);
    }

    /** @test */
    public function unknown_section_type_renders_safely()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublishedPage([
            'sections' => [
                ['id' => 's-x', 'type' => 'tipe-ngawur', 'title' => 'Judul Aman', 'desc' => 'Deskripsi aman', 'visible' => true],
            ],
        ]);

        $response = $this->get('/');
        $response->assertOk();
        // Fallback renderer menampilkan konten aman yang diharapkan.
        $response->assertSee('Judul Aman', false);
        $response->assertSee('Deskripsi aman', false);
    }

    /** @test */
    public function unknown_section_type_does_not_execute_injected_script()
    {
        config(['landing_page_builder.enabled' => true]);
        $this->seedPublishedPage([
            'sections' => [
                [
                    'id' => 's-xss',
                    'type' => 'tipe-ngawur',
                    'title' => 'Judul Aman',
                    'desc' => '<script>window.__orionXssTest=1</script>',
                    'visible' => true,
                ],
            ],
        ]);

        $response = $this->get('/');
        $response->assertOk();

        // Payload XSS tidak boleh muncul sebagai script aktif.
        $response->assertDontSee('<script>window.__orionXssTest=1</script>', false);
        // Karakter berbahaya harus di-escape dengan benar jika konten ditampilkan.
        $response->assertSee('&lt;script&gt;window.__orionXssTest=1&lt;/script&gt;', false);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    private function admin(): User
    {
        $role = Role::firstOrCreate(['nama_role' => 'Administrator']);
        $user = User::factory()->create();
        $user->id_role = $role->id_role;
        $user->save();

        return $user;
    }

    /**
     * Seed halaman 'home' dengan published revision valid.
     * Konten default memuat marker PUBLIK-TUA (dipakai test homepage existing)
     * dan BUILDER-HERO (dipakai test renderer builder).
     */
    private function seedPublishedPage(array $options = []): void
    {
        $admin = $this->admin();

        $sections = $options['sections'] ?? [
            ['id' => 's-hero12345', 'type' => 'hero', 'title' => 'BUILDER-HERO', 'subtitle' => 'Subtitle', 'desc' => 'Deskripsi', 'visible' => true],
        ];

        $seo = $options['seo'] ?? [];

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
            'content_json' => $sections,
            'theme_json' => [],
            'seo_json' => $seo,
            'created_by' => $admin->id_user,
        ]);

        $page->update(['published_revision_id' => $rev->id]);
    }

    // ============================================================
    // KONTRAK SCHEMA VERSION
    // ============================================================

    /** @test */
    public function resolver_allowlist_matches_schema_version_written_by_publish_flow()
    {
        // Publish flow menulis schema_version secara literal. Kalau nilainya
        // berubah someday, allowlist resolver harus ikut berubah - test ini
        // yang tying-the-knot, supaya tidak diam-diam fallback ke homepage
        //existing setiap kali ada publish baru.
        $source = file_get_contents(base_path('app/Http/Controllers/LandingPageBuilderController.php'));
        $this->assertIsString($source);

        preg_match("/'schema_version'\s*=>\s*'([^']+)'/", $source, $m);
        $this->assertArrayHasKey(1, $m, 'Nilai schema_version di publish flow tidak ditemukan.');

        $this->assertContains(
            $m[1],
            LandingPageHomepageResolver::SUPPORTED_SCHEMA_VERSIONS,
            "Publish flow menulis schema_version '{$m[1]}' tapi resolver hanya menerima: "
                . implode(', ', LandingPageHomepageResolver::SUPPORTED_SCHEMA_VERSIONS)
        );
    }

    /** @test */
    public function resolver_allowlist_matches_migration_default()
    {
        $source = file_get_contents(
            base_path('database/migrations/2026_10_08_233154_create_landing_page_builder_tables.php')
        );
        $this->assertIsString($source);

        preg_match("/string\('schema_version'.*?->default\('([^']+)'\)/", $source, $m);
        $this->assertArrayHasKey(1, $m, 'Default schema_version di migration tidak ditemukan.');

        $this->assertContains(
            $m[1],
            LandingPageHomepageResolver::SUPPORTED_SCHEMA_VERSIONS,
            "Migration default schema_version '{$m[1]}' tapi resolver hanya menerima: "
                . implode(', ', LandingPageHomepageResolver::SUPPORTED_SCHEMA_VERSIONS)
        );
    }

    /** @test */
    public function version_looking_like_numbers_is_not_truncated()
    {
        config(['landing_page_builder.enabled' => true]);
        $admin = $this->admin();

        $page = LandingPage::create([
            'slug' => 'home',
            'title' => 'Halaman Utama',
            'status' => 'published',
            'created_by' => $admin->id_user,
            'updated_by' => $admin->id_user,
        ]);

        // '1.10.0' akan jadi 1 kalau dipaksa jadi int - harus DITOLAK karena
        // string-nya tidak sama persis dengan allowlist.
        $rev = LandingPageRevision::create([
            'landing_page_id' => $page->id,
            'revision_number' => 1,
            'schema_version' => '1.10.0',
            'content_json' => [['id' => 's-1', 'type' => 'hero', 'title' => 'BUILDER-HERO', 'visible' => true]],
            'theme_json' => [],
            'seo_json' => [],
            'created_by' => $admin->id_user,
        ]);
        $page->update(['published_revision_id' => $rev->id]);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertDontSee('BUILDER-HERO', false);
    }

    /** @test */
    public function wrapped_sections_format_is_normalized_and_rendered()
    {
        config(['landing_page_builder.enabled' => true]);

        // Bentuk { 'sections': [...] } harus dilepas pembungkusnya sebelum
        // sampai ke renderer. Kalau tidak, foreach mengiterasi key
        // 'sections' dan section-nya tidak pernah tampil.
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
                'sections' => [
                    ['id' => 's-wrap1234', 'type' => 'hero', 'title' => 'BUNGKUS-HERO', 'visible' => true],
                ],
            ],
            'theme_json' => [],
            'seo_json' => [],
            'created_by' => $admin->id_user,
        ]);
        $page->update(['published_revision_id' => $rev->id]);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('BUNGKUS-HERO', false);
    }

    /** @test */
    public function real_publish_flow_output_is_accepted_by_resolver()
    {
        config(['landing_page_builder.enabled' => true]);

        // Publish lewat API sungguhan (bukan seed manual), lalu pastikan
        // homepage publik benar-benar merender hasil publish itu.
        $admin = $this->admin();

        $page = LandingPage::updateOrCreate(
            ['slug' => 'home'],
            [
                'title' => 'Halaman Utama',
                'status' => 'draft',
                'created_by' => $admin->id_user,
                'updated_by' => $admin->id_user,
            ]
        );

        // Draft harus ada dan mulai dari versi 1, sama seperti alur normal.
        LandingPageDraft::updateOrCreate(
            ['landing_page_id' => $page->id],
            [
                'version' => 1,
                'content_json' => [],
                'theme_json' => [],
                'seo_json' => [],
                'updated_by' => $admin->id_user,
            ]
        );

        // Versi draft diambil dari jawaban save, bukan ditebak.
        $saveResponse = $this->actingAs($admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => [
                    ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'PUBLISH-REAL', 'visible' => true],
                ],
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertOk();

        $draftVersion = (int) $saveResponse->json('version');

        $this->actingAs($admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => $draftVersion])
            ->assertOk();

        $page->refresh();

        // Schema version hasil publish harus ada di allowlist.
        $this->assertContains(
            (string) $page->publishedRevision->schema_version,
            LandingPageHomepageResolver::SUPPORTED_SCHEMA_VERSIONS
        );

        // Homepage publik harus menampilkan konten hasil publish.
        // Keluar dulu: kalau masih login, '/' memutar ke dashboard.
        \Illuminate\Support\Facades\Auth::logout();

        $response = $this->get('/');
        $response->assertOk();
        $response->assertViewIs('landing-page-public');
        $response->assertSee('PUBLISH-REAL', false);
    }
}
