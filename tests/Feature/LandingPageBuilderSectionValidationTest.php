<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use App\Models\LandingPage;

/**
 * Checkpoint 5A — Validasi Properti Section & Keamanan Konten.
 *
 * Berkas ini menguji validateContentJson() yang diperluas di Checkpoint 5A:
 * field numerik (padding/margin/fontSize), referensi gambar, dan repeater
 * items. SemuaRules ini menyentuh jalur yang dipakai renderer bersama,
 * jadi kegagalan di sini berarti konten berbahaya bisa mencapai halaman publik.
 *
 * Database: percetakan_erp_testing (dijamin TestCase guard).
 */
class LandingPageBuilderSectionValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['nama_role' => 'Administrator']);
        $role = Role::where('nama_role', 'Administrator')->firstOrFail();

        $this->admin = User::factory()->create();
        $this->admin->id_role = $role->id_role;
        $this->admin->save();
    }

    private function createPage(): LandingPage
    {
        return LandingPage::create([
            'slug' => 'home',
            'title' => 'Halaman Utama',
            'status' => 'draft',
            'created_by' => $this->admin->id_user,
            'updated_by' => $this->admin->id_user,
        ]);
    }

    /**
     * Kirim content_json dan kembalikan respons.
     *
     * base_version minimal 1 (aturan controller), jadi default-nya 1.
     */
    private function saveDraft(array $sections, int $baseVersion = 1)
    {
        $page = $this->createPage();

        return $this->actingAs($this->admin)->putJson(
            route('landing-page-builder.draft.save'),
            [
                'content_json' => $sections,
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => $baseVersion,
            ]
        );
    }

    /** Section dasar yang valid. */
    private function section(array $overrides = []): array
    {
        return array_merge([
            'id' => 's-abc123def',
            'type' => 'hero',
            'title' => 'Judul',
        ], $overrides);
    }

    // ===== FIELD NUMERIK: PADDING / MARGIN / FONT SIZE =====

    /** @test */
    public function padding_and_margin_within_range_are_accepted()
    {
        $response = $this->saveDraft([
            $this->section([
                'paddingTop' => 40,
                'paddingBottom' => 24,
                'marginTop' => -12,
                'marginBottom' => 8,
                'fontSize' => 24,
            ]),
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        // content_json di-cast otomatis menjadi array oleh model.
        $stored = \App\Models\LandingPageDraft::firstOrFail()->content_json;

        $this->assertSame(40, $stored[0]['paddingTop']);
        $this->assertSame(-12, $stored[0]['marginTop']);
        $this->assertSame(24, $stored[0]['fontSize']);
    }

    /** @test */
    public function numeric_strings_from_number_inputs_are_accepted()
    {
        // <input type="number"> mengirim string; harus tetap diterima asal bulat.
        $response = $this->saveDraft([
            $this->section([
                'paddingTop' => '40',
                'fontSize' => '18',
            ]),
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    /** @test */
    public function font_size_above_maximum_is_rejected()
    {
        $response = $this->saveDraft([$this->section(['fontSize' => 500])]);
        $response->assertStatus(422);
    }

    /** @test */
    public function font_size_below_minimum_is_rejected()
    {
        $response = $this->saveDraft([$this->section(['fontSize' => 2])]);
        $response->assertStatus(422);
    }

    /** @test */
    public function negative_padding_is_rejected()
    {
        $response = $this->saveDraft([$this->section(['paddingTop' => -10])]);
        $response->assertStatus(422);
    }

    /** @test */
    public function non_numeric_font_size_is_rejected()
    {
        // Coba menyuntik CSS lewat string bebas.
        $response = $this->saveDraft([
            $this->section(['fontSize' => '16; background:url(javascript:alert(1))']),
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function fractional_numeric_value_is_rejected()
    {
        $response = $this->saveDraft([$this->section(['fontSize' => 16.5])]);
        $response->assertStatus(422);
    }

    // ===== REFERENSI GAMBAR =====

    /** @test */
    public function internal_media_path_image_is_accepted()
    {
        $response = $this->saveDraft([
            $this->section(['image' => 'landing-media/lm_1700000000_abc.jpg']),
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    /** @test */
    public function https_image_url_is_accepted()
    {
        $response = $this->saveDraft([
            $this->section(['image' => 'https://cdn.example.com/hero.jpg']),
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    /** @test */
    public function javascript_scheme_image_is_rejected()
    {
        $response = $this->saveDraft([
            $this->section(['image' => 'javascript:alert(1)']),
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function data_uri_image_is_rejected()
    {
        $response = $this->saveDraft([
            $this->section(['image' => 'data:image/svg+xml;base64,PHN2Zz4=']),
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function image_path_traversal_is_rejected()
    {
        $response = $this->saveDraft([
            $this->section(['image' => 'landing-media/../../.env']),
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function javascript_scheme_button_url_is_rejected()
    {
        $response = $this->saveDraft([
            $this->section([
                'buttonLabel' => 'Klik',
                'buttonUrl' => 'javascript:alert(document.cookie)',
            ]),
        ]);

        $response->assertStatus(422);
    }

    // ===== REPEATER ITEMS =====

    /** @test */
    public function items_are_accepted_for_section_that_supports_them()
    {
        $response = $this->saveDraft([
            $this->section([
                'type' => 'services',
                'items' => [
                    ['title' => 'Cetak Digital', 'desc' => 'Offset & digital'],
                    ['title' => 'Buku', 'desc' => 'Percetakan buku'],
                ],
            ]),
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    /** @test */
    public function items_above_limit_are_rejected()
    {
        $items = [];
        for ($i = 0; $i < 51; $i++) {
            $items[] = ['title' => 'Item ' . $i];
        }

        $response = $this->saveDraft([
            $this->section(['type' => 'services', 'items' => $items]),
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function item_with_unknown_field_is_rejected()
    {
        $response = $this->saveDraft([
            $this->section([
                'type' => 'services',
                'items' => [['title' => 'X', 'unexpectedField' => 'y']],
            ]),
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function item_field_exceeding_length_is_rejected()
    {
        $response = $this->saveDraft([
            $this->section([
                'type' => 'services',
                'items' => [['title' => str_repeat('a', 201)]],
            ]),
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function item_with_dangerous_image_scheme_is_rejected()
    {
        $response = $this->saveDraft([
            $this->section([
                'type' => 'portfolio',
                'items' => [['title' => 'X', 'image' => 'javascript:alert(1)']],
            ]),
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function items_must_be_a_list()
    {
        $response = $this->saveDraft([
            $this->section([
                'type' => 'services',
                'items' => ['kunci' => ['title' => 'X']],
            ]),
        ]);

        $response->assertStatus(422);
    }

    // ===== TYPE & ATURAN DASAR (regresi) =====

    /** @test */
    public function unsupported_section_type_is_rejected()
    {
        $response = $this->saveDraft([$this->section(['type' => 'skrip-jahat'])]);
        $response->assertStatus(422);
    }

    /** @test */
    public function section_without_title_is_rejected()
    {
        // Aturan lama dipertahankan: title wajib terisi.
        $response = $this->saveDraft([$this->section(['title' => ''])]);
        $response->assertStatus(422);
    }

    /** @test */
    public function layout_columns_must_be_integer()
    {
        // Editor mengirim integer; string harus ditolak agar bug tidak
        // bisa datang dari sisi klien.
        $response = $this->saveDraft([$this->section(['layoutColumns' => '3'])]);
        $response->assertStatus(422);
    }

    /** @test */
    public function layout_columns_integer_is_accepted()
    {
        $response = $this->saveDraft([$this->section(['layoutColumns' => 3])]);
        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    /** @test */
    public function duplicate_section_ids_are_rejected()
    {
        $response = $this->saveDraft([
            $this->section(['id' => 's-samasama1']),
            $this->section(['id' => 's-samasama1']),
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function section_id_with_wrong_format_is_rejected()
    {
        $response = $this->saveDraft([$this->section(['id' => 'ID-JANGAN-BENAR'])]);
        $response->assertStatus(422);
    }

    /** @test */
    public function maximum_section_count_is_enforced()
    {
        $sections = [];
        for ($i = 0; $i < 101; $i++) {
            $sections[] = $this->section([
                'id' => 's-' . str_pad((string) $i, 9, '0', STR_PAD_LEFT),
            ]);
        }

        $response = $this->saveDraft($sections);
        $response->assertStatus(422);
    }

    // ===== OTORISASI (regresi) =====

    /** @test */
    public function non_admin_is_rejected_from_saving()
    {
        $otherRole = Role::create(['nama_role' => 'Kasir']);
        $kasir = User::factory()->create();
        $kasir->id_role = $otherRole->id_role;
        $kasir->save();

        $this->actingAs($kasir)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => [$this->section()],
                'base_version' => 1,
            ])
            ->assertStatus(403);
    }
}