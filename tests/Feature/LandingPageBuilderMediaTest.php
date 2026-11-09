<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use App\Models\LandingPage;
use App\Models\LandingPageDraft;
use App\Models\LandingPageRevision;
use App\Models\LandingPageMedia;
use App\Models\AuditLog;

/**
 * Landing Page Builder — Media Manager & Preview Renderer Tests.
 */
class LandingPageBuilderMediaTest extends TestCase
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

        Storage::fake('public');
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

    private function makeJpegFile(string $name = 'test.jpg'): UploadedFile
    {
        // Create a valid JPEG using GD (required for testing)
        if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension required for media tests');
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'jpg');
        $img = imagecreatetruecolor(100, 100);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);
        imagejpeg($img, $tmpPath, 90);
        imagedestroy($img);

        return new UploadedFile($tmpPath, $name, 'image/jpeg', null, true);
    }

    // ===== AUTHORIZATION =====

    /** @test */
    public function guest_cannot_upload_media()
    {
        $file = $this->makeJpegFile('test.jpg');
        $this->post(route('landing-page-builder.media.upload'), ['file' => $file])
            ->assertStatus(302);
    }

    /** @test */
    public function non_admin_cannot_upload_media()
    {
        $file = $this->makeJpegFile('test.jpg');
        $this->actingAs($this->user)
            ->post(route('landing-page-builder.media.upload'), ['file' => $file])
            ->assertStatus(403);
    }

    /** @test */
    public function guest_cannot_list_media()
    {
        $this->getJson(route('landing-page-builder.media.list'))
            ->assertStatus(401);
    }

    /** @test */
    public function guest_cannot_delete_media()
    {
        // deleteJson sends AJAX request, which gets 401 instead of redirect
        $this->deleteJson(route('landing-page-builder.media.delete', ['id' => 1]))
            ->assertStatus(401);
    }

    // ===== UPLOAD =====

    /** @test */
    public function admin_can_upload_image()
    {
        $file = $this->makeJpegFile('hero.jpg');

        $response = $this->actingAs($this->admin)
            ->post(route('landing-page-builder.media.upload'), [
                'file' => $file,
                'alt_text' => 'Hero image',
            ])
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'media' => ['id', 'path', 'url', 'mime_type', 'size', 'metadata'],
            ]);

        $this->assertDatabaseHas('landing_page_media', [
            'mime_type' => 'image/jpeg',
        ]);

        Storage::disk('public')->assertExists('landing-media/' . basename($response->json('media.path')));
    }

    /** @test */
    public function upload_creates_audit_log()
    {
        $file = $this->makeJpegFile('test.jpg');

        $this->actingAs($this->admin)
            ->post(route('landing-page-builder.media.upload'), ['file' => $file])
            ->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => 'LandingPage',
            'event' => 'media_uploaded',
        ]);
    }

    /** @test */
    public function invalid_mime_rejected()
    {
        $file = UploadedFile::fake()->create('malicious.php', 100, 'text/php');

        $this->actingAs($this->admin)
            ->post(route('landing-page-builder.media.upload'), ['file' => $file])
            ->assertStatus(415);
    }

    /** @test */
    public function forged_extension_rejected()
    {
        // Create a file with PHP content but .jpg extension
        $file = UploadedFile::fake()->create('fake.jpg', 100, 'text/php');

        $this->actingAs($this->admin)
            ->post(route('landing-page-builder.media.upload'), ['file' => $file])
            ->assertStatus(415);
    }

    /** @test */
    public function oversized_image_rejected()
    {
        // Create a fake file larger than 10MB
        $file = UploadedFile::fake()->create('large.jpg', 11000); // ~11MB

        $this->actingAs($this->admin)
            ->post(route('landing-page-builder.media.upload'), ['file' => $file])
            ->assertStatus(422);
    }

    /** @test */
    public function missing_file_rejected()
    {
        $this->actingAs($this->admin)
            ->post(route('landing-page-builder.media.upload'), [])
            ->assertStatus(422);
    }

    // ===== MEDIA LIST =====

    /** @test */
    public function admin_can_list_media()
    {
        $this->createPageWithDraft();

        $file = $this->makeJpegFile('test.jpg');
        $this->actingAs($this->admin)
            ->post(route('landing-page-builder.media.upload'), ['file' => $file])
            ->assertStatus(200);

        $this->actingAs($this->admin)
            ->getJson(route('landing-page-builder.media.list'))
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'media' => [
                    '*' => ['id', 'path', 'url', 'mime_type', 'size', 'metadata', 'created_at'],
                ],
            ]);
    }

    // ===== MEDIA DELETE =====

    /** @test */
    public function admin_can_delete_unused_media()
    {
        [$page, $draft] = $this->createPageWithDraft();

        $file = $this->makeJpegFile('unused.jpg');
        $response = $this->actingAs($this->admin)
            ->post(route('landing-page-builder.media.upload'), ['file' => $file])
            ->assertStatus(200);

        $mediaId = $response->json('media.id');

        $this->actingAs($this->admin)
            ->deleteJson(route('landing-page-builder.media.delete', ['id' => $mediaId]))
            ->assertStatus(200);

        $this->assertDatabaseMissing('landing_page_media', ['id' => $mediaId]);
    }

    /** @test */
    public function referenced_media_deletion_blocked()
    {
        [$page, $draft] = $this->createPageWithDraft();

        // Upload media
        $file = $this->makeJpegFile('referenced.jpg');
        $response = $this->actingAs($this->admin)
            ->post(route('landing-page-builder.media.upload'), ['file' => $file])
            ->assertStatus(200);

        $mediaId = $response->json('media.id');
        $mediaPath = $response->json('media.path');

        // Reference it in draft
        $content = [
            ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Hero', 'visible' => true, 'image' => $mediaPath],
        ];

        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => $content,
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(200);

        // Try to delete — should be blocked
        $this->actingAs($this->admin)
            ->deleteJson(route('landing-page-builder.media.delete', ['id' => $mediaId]))
            ->assertStatus(422);

        // Media should still exist
        $this->assertDatabaseHas('landing_page_media', ['id' => $mediaId]);
    }

    /** @test */
    public function delete_nonexistent_media_returns_404()
    {
        $this->createPageWithDraft();

        $this->actingAs($this->admin)
            ->deleteJson(route('landing-page-builder.media.delete', ['id' => 999]))
            ->assertStatus(404);
    }

    // ===== PREVIEW RENDERER =====

    /** @test */
    public function admin_can_preview_draft()
    {
        $this->createPageWithDraft();

        $this->actingAs($this->admin)
            ->get(route('landing-page-builder.preview.draft'))
            ->assertStatus(200)
            ->assertSee('Hero')
            ->assertSee('PREVIEW MODE');
    }

    /** @test */
    public function guest_cannot_preview_draft()
    {
        // Guest is redirected to login (302) by auth middleware
        $this->get(route('landing-page-builder.preview.draft'))
            ->assertStatus(302);
    }

    /** @test */
    public function non_admin_cannot_preview_draft()
    {
        $this->actingAs($this->user)
            ->get(route('landing-page-builder.preview.draft'))
            ->assertStatus(403);
    }

    /** @test */
    public function admin_can_preview_published()
    {
        [$page, $draft] = $this->createPageWithDraft();

        // Publish first
        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 1])
            ->assertStatus(200);

        $this->actingAs($this->admin)
            ->get(route('landing-page-builder.preview.published'))
            ->assertStatus(200)
            ->assertSee('Hero');
    }

    /** @test */
    public function preview_published_404_when_no_published_revision()
    {
        $this->createPageWithDraft();

        $this->actingAs($this->admin)
            ->get(route('landing-page-builder.preview.published'))
            ->assertStatus(404);
    }

    /** @test */
    public function admin_can_preview_historical_revision()
    {
        [$page, $draft] = $this->createPageWithDraft();

        // Publish
        $this->actingAs($this->admin)
            ->postJson(route('landing-page-builder.publish'), ['base_version' => 1])
            ->assertStatus(200);

        $this->actingAs($this->admin)
            ->get(route('landing-page-builder.preview.revision', ['revisionNumber' => 1]))
            ->assertStatus(200)
            ->assertSee('Hero');
    }

    /** @test */
    public function preview_revision_404_when_not_found()
    {
        $this->createPageWithDraft();

        $this->actingAs($this->admin)
            ->get(route('landing-page-builder.preview.revision', ['revisionNumber' => 999]))
            ->assertStatus(404);
    }

    /** @test */
    public function preview_has_noindex_meta()
    {
        $this->createPageWithDraft();

        $this->actingAs($this->admin)
            ->get(route('landing-page-builder.preview.draft'))
            ->assertSee('noindex');
    }

    /** @test */
    public function preview_escapes_xss_in_title()
    {
        [$page, $draft] = $this->createPageWithDraft();

        // Save draft with XSS payload
        $content = [
            ['id' => 's-abc123456', 'type' => 'hero', 'title' => '<script>alert("xss")</script>', 'visible' => true],
        ];

        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => $content,
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(200);

        $response = $this->actingAs($this->admin)
            ->get(route('landing-page-builder.preview.draft'));

        // Should NOT contain unescaped script tag
        $response->assertDontSee('<script>alert("xss")</script>', false);
    }

    /** @test */
    public function preview_hides_invisible_sections()
    {
        [$page, $draft] = $this->createPageWithDraft();

        $content = [
            ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Visible', 'visible' => true],
            ['id' => 's-def789012', 'type' => 'about', 'title' => 'Hidden Section', 'visible' => false],
        ];

        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => $content,
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(200);

        // Konten section dirender oleh endpoint iframe, bukan oleh wrapper.
        // Wrapper hanya berisi shell + <iframe src=".../iframe?mode=draft">.
        $response = $this->actingAs($this->admin)
            ->get('/setting/landing-page-builder/preview/draft/iframe?mode=draft');

        $response->assertStatus(200);
        $response->assertSee('Visible');
        $response->assertDontSee('Hidden Section');
    }

    /** @test */
    public function preview_wrapper_contains_iframe_with_correct_mode()
    {
        [$page, $draft] = $this->createPageWithDraft();

        $content = [
            ['id' => 's-abc123456', 'type' => 'hero', 'title' => 'Wrapper Check', 'visible' => true],
        ];

        $this->actingAs($this->admin)
            ->putJson(route('landing-page-builder.draft.save'), [
                'content_json' => $content,
                'theme_json' => [],
                'seo_json' => [],
                'base_version' => 1,
            ])
            ->assertStatus(200);

        $response = $this->actingAs($this->admin)
            ->get(route('landing-page-builder.preview.draft'));

        $response->assertStatus(200);
        $response->assertSee('PREVIEW MODE');
        // Wrapper harus memuat iframe yang menunjuk ke endpoint draft
        $response->assertSee('landing-page-builder/preview/draft/iframe?mode=draft');
    }

    // ===== PUBLIC ROUTES UNCHANGED =====

    /** @test */
    public function public_routes_not_affected()
    {
        // Verify existing public routes still work
        $this->get('/')->assertStatus(200);
    }
}