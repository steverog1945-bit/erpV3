<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use App\Models\LandingPage;
use App\Models\LandingPageDraft;
use App\Models\AuditLog;

/**
 * Landing Page Builder — Draft Persistence API Tests.
 * All tests run on percetakan_erp_testing via phpunit.xml env override.
 */
class LandingPageBuilderDraftApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['nama_role' => 'Administrator']);
        $this->admin = User::factory()->create();
        $adminRole = Role::where('nama_role', 'Administrator')->firstOrFail();
        $this->admin->id_role = $adminRole->id_role;
        $this->admin->save();

        $this->user = User::factory()->create();
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

    // ===== AUTHORIZATION =====

    /** @test */
    public function guest_cannot_get_draft()
    {
        $this->getJson(route('landing-page-builder.draft.show'))
            ->assertStatus(401);
    }

    /** @test */
    public function guest_cannot_put_draft()
    {
        $this->putJson(route('landing-page-builder.draft.save'), [])
            ->assertStatus(401);
    }

    /** @test */
    public function non_admin_cannot_get_draft()
    {
        $this->actingAs($this->user)
            ->getJson(route('landing-page-builder.draft.show'))
            ->assertStatus(403);
    }

    /** @test */
    public function non_admin_cannot_put_draft()
    {
        $this->actingAs($this->user)
            ->putJson(route('landing-page-builder.draft.save'), [])
            ->assertStatus(403);
    }

    // ===== ADMIN GET =====

    /** @test */
    public function admin_can_get_draft()
    {
        $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.draft.show'))
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'page_id',
                    'slug',
                    'title',
                    'status',
                    'draft' => ['version', 'content_json', 'theme_json', 'seo_json'],
                    'published_revision',
                    'updated_at',
                ],
            ]);
    }

    /** @test */
    public function admin_get_creates_draft_if_not_exists()
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.draft.show'))
            ->assertStatus(200);

        $pageId = $response->json('data.page_id');
        $this->assertDatabaseHas('landing_page_drafts', [
            'landing_page_id' => $pageId,
            'version' => 1,
        ]);
    }

    // ===== ADMIN PUT =====

    /** @test */
    public function admin_can_save_draft()
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.draft.show'))
            ->assertStatus(200);

        $pageId = $response->json('data.page_id');

        $sections = [
            ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Hero Section', 'visible' => true],
        ];

        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => $sections,
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'version', 'updated_at']);

        $this->assertDatabaseHas('landing_page_drafts', [
            'landing_page_id' => $pageId,
            'version' => 2,
        ]);
    }

    /** @test */
    public function version_increments_on_save()
    {
        $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.draft.show'));

        $sections = [
            ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Test', 'visible' => true],
        ];

        $response = $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => $sections,
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ]);

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('version'));
    }

    /** @test */
    public function draft_reload_returns_saved_content()
    {
        $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.draft.show'));

        $sections = [
            ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Hero Title', 'visible' => true],
            ['id' => 's-def789012', 'type' => 'services', 'title' => 'Services', 'visible' => true],
        ];

        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => $sections,
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(200);

        // Reload
        $response = $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.draft.show'))
            ->assertStatus(200);

        $this->assertEquals('Hero Title', $response->json('data.draft.content_json.0.title'));
        $this->assertEquals('Services', $response->json('data.draft.content_json.1.title'));
    }

    // ===== VALIDATION =====

    /** @test */
    public function invalid_section_type_rejected()
    {
        $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.draft.show'));

        $sections = [
            ['id' => 's-abc123456', 'type' => 'invalid-type', 'title' => 'Test', 'visible' => true],
        ];

        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => $sections,
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function duplicate_section_id_rejected()
    {
        $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.draft.show'));

        $sections = [
            ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Test 1', 'visible' => true],
            ['id' => 's-abc123456', 'type' => 'about', 'title' => 'Test 2', 'visible' => true],
        ];

        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => $sections,
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function unsafe_url_rejected()
    {
        $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.draft.show'));

        $sections = [
            ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Test', 'buttonUrl' => 'javascript:alert(1)', 'visible' => true],
        ];

        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => $sections,
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function unsupported_style_rejected()
    {
        $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.draft.show'));

        $sections = [
            ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Test', 'styleBg' => 'rainbow', 'visible' => true],
        ];

        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => $sections,
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function oversized_payload_rejected()
    {
        $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.draft.show'));

        $sections = [
            ['id' => 's-abc123456', 'type' => 'hero', 'title' => str_repeat('A', 2000), 'visible' => true],
        ];

        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => $sections,
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function missing_base_version_rejected()
    {
        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => [],
                'theme_json' => [],
                'seo_json' => [],
            ])
            ->assertStatus(422);
    }

    // ===== CONCURRENCY =====

    /** @test */
    public function stale_version_returns_409()
    {
        $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.draft.show'));

        $sections = [
            ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Test', 'visible' => true],
        ];

        // First save: version 1 -> 2
        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => $sections,
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(200);

        // Second save with stale version 1
        $sections2 = [
            ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Updated', 'visible' => true],
        ];

        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => $sections2,
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(409);
    }

    /** @test */
    public function concurrent_saves_one_succeeds_one_conflicts()
    {
        $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.draft.show'));

        $sectionsA = [
            ['id' => 's-aaa111222', 'type' => 'hero', 'title' => 'From A', 'visible' => true],
        ];
        $sectionsB = [
            ['id' => 's-bbb333444', 'type' => 'hero', 'title' => 'From B', 'visible' => true],
        ];

        // Both try to save with base_version 1
        $responseA = $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => $sectionsA,
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ]);

        $responseB = $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => $sectionsB,
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ]);

        // One should succeed, one should conflict
        $statuses = [$responseA->status(), $responseB->status()];
        $this->assertContains(200, $statuses);
        $this->assertContains(409, $statuses);
    }

    // ===== AUDIT LOG =====

    /** @test */
    public function audit_log_created_on_save()
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.draft.show'));

        $pageId = $response->json('data.page_id');

        $sections = [
            ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Test', 'visible' => true],
        ];

        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => $sections,
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => 'LandingPage',
            'auditable_id' => $pageId,
            'event' => 'draft_saved',
        ]);
    }

    // ===== NO PUBLISHED POINTER MUTATION =====

    /** @test */
    public function save_draft_does_not_create_revision()
    {
        $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.draft.show'));

        $sections = [
            ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Test', 'visible' => true],
        ];

        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => $sections,
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(200);

        // No revision should be created
        $this->assertDatabaseCount('landing_page_revisions', 0);
    }

    /** @test */
    public function save_draft_does_not_change_published_pointer()
    {
        $page = $this->createPage();

        $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.draft.show'));

        $sections = [
            ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Test', 'visible' => true],
        ];

        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => $sections,
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(200);

        $page->refresh();
        $this->assertNull($page->published_revision_id);
        $this->assertEquals('draft', $page->status);
    }

    // ===== HIDDEN SECTIONS PRESERVED =====

    /** @test */
    public function hidden_sections_preserved_in_draft()
    {
        $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.draft.show'));

        $sections = [
            ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Visible', 'visible' => true],
            ['id' => 's-def789012', 'type' => 'about', 'title' => 'Hidden', 'visible' => false],
        ];

        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => $sections,
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(200);

        $response = $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.draft.show'));

        $this->assertFalse($response->json('data.draft.content_json.1.visible'));
    }

    // ===== SECTION ORDER PRESERVED =====

    /** @test */
    public function section_order_preserved()
    {
        $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.draft.show'));

        $sections = [
            ['id' => 's-aaa111222', 'type' => 'hero', 'title' => 'First', 'visible' => true],
            ['id' => 's-bbb333444', 'type' => 'about', 'title' => 'Second', 'visible' => true],
            ['id' => 's-ccc555666', 'type' => 'services', 'title' => 'Third', 'visible' => true],
        ];

        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => $sections,
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(200);

        $response = $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.draft.show'));

        $content = $response->json('data.draft.content_json');
        $this->assertEquals('First', $content[0]['title']);
        $this->assertEquals('Second', $content[1]['title']);
        $this->assertEquals('Third', $content[2]['title']);
    }
}
