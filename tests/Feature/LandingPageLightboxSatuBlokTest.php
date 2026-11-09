<?php

namespace Tests\Feature;

use App\Models\LandingPage;
use App\Models\LandingPageRevision;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi dedup skrip lightbox Landing Page Builder.
 *
 * Kontrak:
 * - Halaman publik ('/' dengan builder aktif) hanya membuat OVERLAY lightbox
 *   SEKALI (`var overlay = document.createElement` muncul sekali) padahal
 *   halaman punya banyak section (dulu 12 salinan skrip tumpang-tindih).
 * - Skrip terlindung guard window.__lpLightboxBound.
 * - Data user yang mengandung "</script>" tetap dirender aman (tidak
 *   merusak halaman / tidak bocor keluar dari atribut HTML), bukti perbaikan
 *   A5 (json_encode -> @js di scroll-reveal & jersey/master).
 */
class LandingPageLightboxSatuBlokTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $db = config('database.connections.mysql.database');
        if ($db !== 'percetakan_erp_testing') {
            $this->markTestSkipped("Testing DB bukan percetakan_erp_testing (sekarang: {$db}). Test dilewati demi keamanan.");
        }

        config(['landing_page_builder.enabled' => true]);
    }

    private function seedPublishedPage(array $sections): void
    {
        $admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);

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
            'seo_json' => [],
            'created_by' => $admin->id_user,
        ]);

        $page->update(['published_revision_id' => $rev->id]);
    }

    /** @test */
    public function halaman_publik_dengan_banyak_section_membuat_overlay_sekali()
    {
        $bangkitkan = '</script><img src=x onerror=alert(1)>';

        $this->seedPublishedPage([
            ['id' => 's-hero-1', 'type' => 'hero', 'title' => 'Judul Utama', 'subtitle' => 'Sub', 'desc' => 'Deskripsi', 'btn1' => 'Mulai', 'btn1_href' => '/daftar', 'visible' => true],
            ['id' => 's-port-1', 'type' => 'portfolio', 'title' => 'Portofolio', 'columns' => 3, 'visible' => true, 'items' => [[
                'title' => 'Katalog ' . $bangkitkan,
                'desc' => 'Katalog kertas premium.',
                'category' => 'Banner',
                'image' => '/storage/landing-media/tes.png',
                'alt_text' => 'Katalog',
            ]]],
            ['id' => 's-testi-1', 'type' => 'testimonials', 'title' => 'Testimoni', 'visible' => true, 'items' => [[
                'quote' => 'Pesan ' . $bangkitkan,
                'nama' => 'Budi',
            ]]],
            ['id' => 's-faq-1', 'type' => 'faq', 'title' => 'FAQ', 'visible' => true, 'items' => [[
                'question' => 'Tanya ' . $bangkitkan,
                'answer' => 'Jawaban.',
            ]]],
        ]);

        $res = $this->get('/');

        $res->assertOk();
        $res->assertViewIs('landing-page-public');

        $html = $res->getContent();

        // Kontrak utama: HANYA SATU pengecekan lightbox pada seluruh halaman,
        // walau ada 4 section. Dulu: 12 salinan -> 4-12 overlay tumpang-tindih.
        $this->assertSame(
            1,
            substr_count($html, 'var overlay = document.createElement'),
            'Skrip lightbox masih terduplikasi di keluaran render halaman publik.'
        );
        $this->assertSame(1, substr_count($html, 'function bukaLightbox'));

        // Guard dedup tersedia di keluaran.
        $this->assertTrue(
            substr_count($html, '__lpLightboxBound') >= 2,
            'Guard window.__lpLightboxBound tidak ditemukan.'
        );

        // A5: data user yang berisi "</script>" TIDAK boleh lolos mentah
        // (merusak halaman = LiveScript break-out).
        $this->assertStringNotContainsString($bangkitkan, $html);
        // Data tetap tampil (sudah di-share/larang tanda < >).
        $this->assertStringContainsString('&lt;/script&gt;&lt;img', $html);

        // Filter portfolio tetap ada pasca dedup (kalau hilang, portfolio tidak bisa dipilah).
        $this->assertStringContainsString('data-lpb-portfolio-filter', $html);
        // FAQ accordion dan hamburger header tidak boleh ikut terhapus.
        $this->assertStringContainsString('lp-faq-list', $html);
        $this->assertStringContainsString('setOpen', $html);
    }

    /** @test */
    public function empty_state_pakai_teks_yang_benar_per_section()
    {
        $this->seedPublishedPage([
            ['id' => 's-svc', 'type' => 'services', 'title' => 'Layanan', 'visible' => true],
            ['id' => 's-prd', 'type' => 'product-grid', 'title' => 'Produk', 'visible' => true],
            ['id' => 's-por', 'type' => 'portfolio', 'title' => 'Portofolio', 'visible' => true],
            ['id' => 's-tes', 'type' => 'testimonials', 'title' => 'Testimoni', 'visible' => true],
            ['id' => 's-sta', 'type' => 'statistics', 'title' => 'Statistik', 'visible' => true],
            ['id' => 's-faq', 'type' => 'faq', 'title' => 'FAQ', 'visible' => true],
        ]);

        $res = $this->get('/');
        $res->assertOk();
        $html = $res->getContent();

        $this->assertStringContainsString('Belum ada layanan.', $html);
        $this->assertStringContainsString('Belum ada produk.', $html);
        $this->assertStringContainsString('Belum ada karya portfolio.', $html);
        $this->assertStringContainsString('Belum ada testimoni klien.', $html);
        $this->assertStringContainsString('Belum ada data statistik.', $html);
        $this->assertStringContainsString('Belum ada pertanyaan umum.', $html);

        // Teks testimoni tidak lagi membanjiri section lain.
        $this->assertSame(1, substr_count($html, 'Belum ada testimoni klien.'));
        // Empty state untuk services tidak lagi memakai teks testimoni.
        $this->assertStringNotContainsString('<p>Belum ada testimoni klien.</p>' . "\n" . '                            <small>Layanan', $html);
    }
}
