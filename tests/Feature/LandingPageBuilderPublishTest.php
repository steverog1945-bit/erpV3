<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use App\Models\LandingPage;
use App\Models\LandingPageDraft;
use App\Models\LandingPageRevision;
use App\Models\AuditLog;

/**
 * Landing Page Builder — Publish & Restore Tests.
 */
class LandingPageBuilderPublishTest extends TestCase
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

    private function createPageWithDraft(): array
    {
        $page = LandingPage::create([
            'slug' => 'home',
            'title' => 'Halaman Utama',
            'status' => 'draft',
            'created_by' => $this->admin->id_user,
            'updated_by' => $this->admin->id_user,
        ]);

        $draft = LandingPageDraft::create([
            'landing_page_id' => $page->id,
            'version' => 1,
            'content_json' => [
                ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Hero', 'visible' => true],
            ],
            'theme_json' => [],
            'seo_json' => [],
            'updated_by' => $this->admin->id_user,
        ]);

        return [$page, $draft];
    }

    // ===== AUTHORIZATION =====

    /** @test */
    public function guest_cannot_publish()
    {
        $this->postJson(route('landing-page-builder.publish'))
            ->assertStatus(401);
    }

    /** @test */
    public function non_admin_cannot_publish()
    {
        $this->actingAs($this->user)
            ->postJson(route('landing-page-builder.publish'))
            ->assertStatus(403);
    }

    /** @test */
    public function guest_cannot_list_revisions()
    {
        $this->getJson(route('landing-page-builder.revisions.list'))
            ->assertStatus(401);
    }

    /** @test */
    public function non_admin_cannot_list_revisions()
    {
        $this->actingAs($this->user)
            ->getJson(route('landing-page-builder.revisions.list'))
            ->assertStatus(403);
    }

    // ===== VERSION HISTORY =====

    /** @test */
    public function admin_can_list_revisions()
    {
        $this->createPageWithDraft();

        $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.revisions.list'))
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'revisions' => [
                    '*' => ['id', 'revision_number', 'created_by', 'created_at', 'is_published', 'sections_count'],
                ],
            ]);
    }

    /** @test */
    public function admin_can_get_revision_detail()
    {
        $this->createPageWithDraft();

        // First publish to create a revision
        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 1])
            ->assertStatus(200);

        $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.revisions.show', ['revisionNumber' => 1]))
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'revision' => ['id', 'revision_number', 'created_by', 'created_at', 'is_published', 'content_json'],
            ]);
    }

    /** @test */
    public function revision_not_found_returns_404()
    {
        $this->createPageWithDraft();

        $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.revisions.show', ['revisionNumber' => 999]))
            ->assertStatus(404);
    }

    // ===== PUBLISH =====

    /** @test */
    public function admin_can_publish()
    {
        [$page, $draft] = $this->createPageWithDraft();

        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 1])
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'published_revision_id', 'revision_number', 'published_at']);

        $this->assertDatabaseHas('landing_page_revisions', [
            'landing_page_id' => $page->id,
            'revision_number' => 1,
        ]);

        $this->assertDatabaseHas('landing_pages', [
            'id' => $page->id,
            'status' => 'published',
        ]);
    }

    /** @test */
    public function publish_sets_published_revision_id()
    {
        [$page, $draft] = $this->createPageWithDraft();

        $response = $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 1])
            ->assertStatus(200);

        $revisionId = $response->json('published_revision_id');

        $this->assertDatabaseHas('landing_pages', [
            'id' => $page->id,
            'published_revision_id' => $revisionId,
        ]);
    }

    /** @test */
    public function published_revision_is_immutable()
    {
        [$page, $draft] = $this->createPageWithDraft();

        // Publish
        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 1])
            ->assertStatus(200);

        $revision = LandingPageRevision::where('landing_page_id', $page->id)->first();
        $originalContent = $revision->content_json;

        // Modify draft and publish again
        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => [
                    ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Updated', 'visible' => true],
                ],
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(200);

        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 2])
            ->assertStatus(200);

        // Original revision should be unchanged
        $revision->refresh();
        $this->assertEquals($originalContent, $revision->content_json);
    }

    /** @test */
    public function publish_stale_version_returns_409()
    {
        [$page, $draft] = $this->createPageWithDraft();

        // First publish: version 1
        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 1])
            ->assertStatus(200);

        // Save draft to increment version
        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => [
                    ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Updated', 'visible' => true],
                ],
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(200);

        // Try publish with stale version 1
        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 1])
            ->assertStatus(409);
    }

    /** @test */
    public function publish_parallel_safety()
    {
        // Note: PHPUnit runs tests serially, so true parallel requests cannot be
        // simulated within a single test. This test verifies that two sequential
        // publishes with the same base_version result in one success and one conflict
        // when the draft version has been incremented between them.
        [$page, $draft] = $this->createPageWithDraft();

        // First publish: version 1 -> success
        $responseA = $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 1]);
        $this->assertEquals(200, $responseA->status());

        // Save draft to increment version
        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => [
                    ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Updated', 'visible' => true],
                ],
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(200);

        // Second publish with stale version 1 -> conflict
        $responseB = $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 1]);
        $this->assertEquals(409, $responseB->status());
    }

    /** @test */
    public function publish_creates_audit_log()
    {
        [$page, $draft] = $this->createPageWithDraft();

        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 1])
            ->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => 'LandingPage',
            'auditable_id' => $page->id,
            'event' => 'published',
        ]);
    }

    /** @test */
    public function publish_increments_revision_number()
    {
        [$page, $draft] = $this->createPageWithDraft();

        // First publish
        $response1 = $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 1])
            ->assertStatus(200);
        $this->assertEquals(1, $response1->json('revision_number'));

        // Save draft again (version 1 -> 2)
        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => [
                    ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Updated', 'visible' => true],
                ],
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(200);

        // Second publish
        $response2 = $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 2])
            ->assertStatus(200);
        $this->assertEquals(2, $response2->json('revision_number'));
    }

    /** @test */
    public function publish_without_changes_creates_new_revision()
    {
        [$page, $draft] = $this->createPageWithDraft();

        // Publish twice (with save in between)
        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 1])
            ->assertStatus(200);

        // Save draft dengan konten yang SAMA.
        // Sejak perbaikan P0.1 (contentFingerprint), penyimpanan tanpa perubahan
        // sengaja TIDAK menaikkan versi — itu perilaku yang diinginkan, bukan bug.
        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => [
                    ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Hero', 'visible' => true],
                ],
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(200)
            ->assertJson(['unchanged' => true]);

        // Versi draft tetap 1 karena tidak ada perubahan konten.
        $this->assertEquals(1, LandingPageDraft::where('landing_page_id', $page->id)->value('version'));

        // Publish again — base_version tetap 1
        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 1])
            ->assertStatus(200);

        // Should have 2 revisions
        $this->assertEquals(2, LandingPageRevision::where('landing_page_id', $page->id)->count());
    }

    // ===== RESTORE =====

    /** @test */
    public function admin_can_restore_revision()
    {
        [$page, $draft] = $this->createPageWithDraft();

        // Publish first
        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 1])
            ->assertStatus(200);

        // Modify draft (version 1 -> 2)
        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => [
                    ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Modified', 'visible' => true],
                ],
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(200);

        // Restore revision 1
        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.revisions.restore', ['revisionNumber' => 1]), ['base_version' => 2])
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'new_draft_version', 'restored_from_revision']);
    }

    /** @test */
    public function restore_does_not_change_published_pointer()
    {
        [$page, $draft] = $this->createPageWithDraft();

        // Publish
        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 1])
            ->assertStatus(200);

        // Refresh to get the published_revision_id set by publish
        $page->refresh();
        $publishedId = $page->published_revision_id;
        $this->assertNotNull($publishedId);

        // Modify draft (version 1 -> 2)
        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => [
                    ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Modified', 'visible' => true],
                ],
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(200);

        // Restore
        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.revisions.restore', ['revisionNumber' => 1]), ['base_version' => 2])
            ->assertStatus(200);

        $page->refresh();
        $this->assertEquals($publishedId, $page->published_revision_id);
    }

    /** @test */
    public function restore_increments_draft_version()
    {
        [$page, $draft] = $this->createPageWithDraft();

        // Publish
        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 1])
            ->assertStatus(200);

        // Modify draft (version 1 -> 2)
        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => [
                    ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Modified', 'visible' => true],
                ],
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(200);

        // Restore
        $response = $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.revisions.restore', ['revisionNumber' => 1]), ['base_version' => 2])
            ->assertStatus(200);

        $this->assertEquals(3, $response->json('new_draft_version'));
    }

    /** @test */
    public function restore_stale_version_returns_409()
    {
        [$page, $draft] = $this->createPageWithDraft();

        // Publish
        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 1])
            ->assertStatus(200);

        // Save draft to increment version (1 -> 2)
        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => [
                    ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Updated', 'visible' => true],
                ],
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(200);

        // Try restore with stale version 1
        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.revisions.restore', ['revisionNumber' => 1]), ['base_version' => 1])
            ->assertStatus(409);
    }

    /** @test */
    public function restore_nonexistent_revision_returns_404()
    {
        [$page, $draft] = $this->createPageWithDraft();

        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.revisions.restore', ['revisionNumber' => 999]), ['base_version' => 1])
            ->assertStatus(404);
    }

    /** @test */
    public function restore_creates_audit_log()
    {
        [$page, $draft] = $this->createPageWithDraft();

        // Publish
        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 1])
            ->assertStatus(200);

        // Modify draft (version 1 -> 2)
        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => [
                    ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Modified', 'visible' => true],
                ],
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(200);

        // Restore
        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.revisions.restore', ['revisionNumber' => 1]), ['base_version' => 2])
            ->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => 'LandingPage',
            'auditable_id' => $page->id,
            'event' => 'revision_restored',
        ]);
    }

    /** @test */
    public function restore_does_not_delete_old_revision()
    {
        [$page, $draft] = $this->createPageWithDraft();

        // Publish
        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 1])
            ->assertStatus(200);

        // Modify draft (version 1 -> 2)
        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => [
                    ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Modified', 'visible' => true],
                ],
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(200);

        // Restore
        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.revisions.restore', ['revisionNumber' => 1]), ['base_version' => 2])
            ->assertStatus(200);

        // Old revision should still exist
        $this->assertDatabaseHas('landing_page_revisions', [
            'landing_page_id' => $page->id,
            'revision_number' => 1,
        ]);
    }

    // ===== EXISTING DRAFT SAFETY =====

    /** @test */
    public function publish_does_not_delete_draft()
    {
        [$page, $draft] = $this->createPageWithDraft();

        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 1])
            ->assertStatus(200);

        $this->assertDatabaseHas('landing_page_drafts', [
            'landing_page_id' => $page->id,
        ]);
    }

    /** @test */
    public function publish_creates_revision_from_draft_content()
    {
        [$page, $draft] = $this->createPageWithDraft();

        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 1])
            ->assertStatus(200);

        $revision = LandingPageRevision::where('landing_page_id', $page->id)->first();
        $this->assertEquals('Hero', $revision->content_json[0]['title']);
    }
}
