<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\LandingPage;
use App\Models\LandingPageDraft;
use App\Models\LandingPageRevision;
use App\Models\LandingPageMedia;
use App\Models\AuditLog;

/**
 * Landing Page Builder — Draft Persistence API.
 * Admin-only via hanya.admin middleware on routes.
 */
class LandingPageBuilderController extends Controller
{
    /**
     * Display the visual editor.
     */
    public function index(Request $request)
    {
        return view('pengaturan.landing-page-builder');
    }

    /**
     * Load the current draft for editing.
     * GET /setting/landing-page-builder/draft
     */
    public function showDraft(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->assertAdministrator($user);

        // Get or create the default landing page for this system
        $page = $this->getOrCreateDefaultPage($user);

        $draft = $page->draft;

        if (!$draft) {
            // Create initial draft if not exists
            $draft = LandingPageDraft::create([
                'landing_page_id' => $page->id,
                'version' => 1,
                'content_json' => [],
                'theme_json' => [],
                'seo_json' => [],
                'updated_by' => $user->id_user,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'page_id' => $page->id,
                'slug' => $page->slug,
                'title' => $page->title,
                'status' => $page->status,
                'draft' => [
                    'version' => $draft->version,
                    'content_json' => $draft->content_json ?? [],
                    'theme_json' => $draft->theme_json ?? [],
                    'seo_json' => $draft->seo_json ?? [],
                ],
                'published_revision' => $page->publishedRevision ? [
                    'id' => $page->publishedRevision->id,
                    'revision_number' => $page->publishedRevision->revision_number,
                    'content_json' => $page->publishedRevision->content_json ?? [],
                    'created_at' => $page->publishedRevision->created_at,
                ] : null,
                'updated_at' => $draft->updated_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Save draft with concurrency protection.
     * PUT /setting/landing-page-builder/draft
     */
    public function saveDraft(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->assertAdministrator($user);

        // Validate request
        $validator = Validator::make($request->all(), [
            'content_json' => 'required|array',
            'theme_json' => 'nullable|array',
            'seo_json' => 'nullable|array',
            'base_version' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $contentJson = $request->input('content_json');
        $themeJson = $request->input('theme_json', []);
        $seoJson = $request->input('seo_json', []);
        $baseVersion = (int) $request->input('base_version');

        // Validate content structure
        $validationError = $this->validateContentJson($contentJson);
        if ($validationError) {
            return response()->json([
                'success' => false,
                'message' => $validationError,
            ], 422);
        }

        // Validate payload size (max 1MB)
        $payloadSize = strlen(json_encode($contentJson)) + strlen(json_encode($themeJson)) + strlen(json_encode($seoJson));
        if ($payloadSize > 1048576) {
            return response()->json([
                'success' => false,
                'message' => 'Payload too large (max 1MB)',
            ], 422);
        }

        // Get or create the default landing page
        $page = $this->getOrCreateDefaultPage($user);

        // Concurrency protection with SELECT FOR UPDATE
        try {
            $result = DB::transaction(function () use ($page, $user, $contentJson, $themeJson, $seoJson, $baseVersion) {
                // Lock the draft row
                $draft = LandingPageDraft::where('landing_page_id', $page->id)
                    ->lockForUpdate()
                    ->first();

                if (!$draft) {
                    // Create if not exists (should not happen after showDraft)
                    $draft = LandingPageDraft::create([
                        'landing_page_id' => $page->id,
                        'version' => 1,
                        'content_json' => [],
                        'theme_json' => [],
                        'seo_json' => [],
                        'updated_by' => $user->id_user,
                    ]);
                }

                // Concurrency check — SELALU dijalankan lebih dulu agar
                // base_version basi tetap ditolak dengan 409, apa pun isi
                // payload-nya.
                if ((int) $draft->version !== $baseVersion) {
                    abort(409, 'Draft was modified by another admin. Current version: ' . $draft->version);
                }

                // ---------------------------------------------------------------
                // P0.1 — Gerbang perubahan semantik.
                //
                // Opening the builder normalizes every section (adds default
                // fields) purely so the renderer can read a predictable shape.
                // Those defaults are NOT a content change. Previously the
                // autosave path bypassed the isDirty check, so a single stray
                // autosave rewrote the whole draft and bumped `version` even
                // though nothing visible had changed (observed v108 -> v126).
                //
                // Here both sides are reduced to a content fingerprint that
                // ignores normalization-only fields. If the fingerprint is
                // unchanged, the row is left completely untouched: version,
                // updated_at and updated_by all stay identical.
                // ---------------------------------------------------------------
                $storedContent = is_array($draft->content_json) ? $draft->content_json : (json_decode((string) $draft->content_json, true) ?: []);
                if ($this->contentFingerprint($storedContent) === $this->contentFingerprint($contentJson)) {
                    return [
                        'success' => true,
                        'unchanged' => true,
                        'version' => (int) $draft->version,
                        'updated_at' => $draft->updated_at?->toISOString(),
                    ];
                }

                // Update draft
                $draft->content_json = $contentJson;
                $draft->theme_json = $themeJson;
                $draft->seo_json = $seoJson;
                $draft->version = $draft->version + 1;
                $draft->updated_by = $user->id_user;
                $draft->save();

                // Audit log
                $this->auditLog($page->id, 'draft_saved', [
                    'version' => $draft->version,
                    'sections_count' => count($contentJson),
                    'saved_by' => $user->id_user,
                ]);

                return [
                    'success' => true,
                    'version' => $draft->version,
                    'updated_at' => $draft->updated_at?->toISOString(),
                ];
            });

            return response()->json($result);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            if ($e->getStatusCode() === 409) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error' => 'conflict',
                ], 409);
            }
            throw $e;
        }
    }

    /**
     * List revision history.
     * GET /setting/landing-page-builder/revisions
     */
    public function listRevisions(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->assertAdministrator($user);

        $page = $this->getOrCreateDefaultPage($user);

        $revisions = LandingPageRevision::where('landing_page_id', $page->id)
            ->orderByDesc('revision_number')
            ->limit(50)
            ->get();

        $result = $revisions->map(function ($rev) use ($page) {
            $creator = $rev->creator;
            return [
                'id' => $rev->id,
                'revision_number' => $rev->revision_number,
                'created_by' => $creator ? $creator->username : 'Unknown',
                'created_at' => $rev->created_at,
                'schema_version' => $rev->schema_version,
                'is_published' => $page->published_revision_id === $rev->id,
                'sections_count' => count($rev->content_json ?? []),
            ];
        });

        return response()->json([
            'success' => true,
            'revisions' => $result,
        ]);
    }

    /**
     * Get revision detail.
     * GET /setting/landing-page-builder/revisions/{revisionNumber}
     */
    public function showRevision(Request $request, int $revisionNumber): JsonResponse
    {
        $user = $request->user();
        $this->assertAdministrator($user);

        $page = $this->getOrCreateDefaultPage($user);

        $revision = LandingPageRevision::where('landing_page_id', $page->id)
            ->where('revision_number', $revisionNumber)
            ->first();

        if (!$revision) {
            return response()->json([
                'success' => false,
                'message' => 'Revision not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'revision' => [
                'id' => $revision->id,
                'revision_number' => $revision->revision_number,
                'created_by' => $revision->creator ? $revision->creator->username : 'Unknown',
                'created_at' => $revision->created_at,
                'schema_version' => $revision->schema_version,
                'is_published' => $page->published_revision_id === $revision->id,
                'content_json' => $revision->content_json ?? [],
                'theme_json' => $revision->theme_json ?? [],
                'seo_json' => $revision->seo_json ?? [],
            ],
        ]);
    }

    /**
     * Atomic Publish — creates immutable revision and updates published pointer.
     * POST /setting/landing-page-builder/publish
     */
    public function publish(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->assertAdministrator($user);

        $validator = Validator::make($request->all(), [
            'base_version' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $baseVersion = (int) $request->input('base_version');
        $page = $this->getOrCreateDefaultPage($user);

        try {
            $result = DB::transaction(function () use ($page, $user, $baseVersion) {
                // Lock page first, then draft (consistent ordering prevents deadlock)
                $page = LandingPage::where('id', $page->id)->lockForUpdate()->first();
                $draft = LandingPageDraft::where('landing_page_id', $page->id)
                    ->lockForUpdate()
                    ->first();

                if (!$draft) {
                    abort(422, 'No draft available to publish');
                }

                // Concurrency check
                if ((int) $draft->version !== $baseVersion) {
                    abort(409, 'Draft was modified. Current version: ' . $draft->version);
                }

                // Validate content before publishing
                $contentJson = $draft->content_json;
                $validationError = $this->validateContentJson($contentJson);
                if ($validationError) {
                    abort(422, $validationError);
                }

                // Determine next revision number safely
                $lastRevision = LandingPageRevision::where('landing_page_id', $page->id)
                    ->lockForUpdate()
                    ->orderByDesc('revision_number')
                    ->first();

                $nextRevisionNumber = $lastRevision ? $lastRevision->revision_number + 1 : 1;

                // Create immutable revision snapshot
                $revision = LandingPageRevision::create([
                    'landing_page_id' => $page->id,
                    'revision_number' => $nextRevisionNumber,
                    'schema_version' => '1.0.0',
                    'content_json' => $contentJson,
                    'theme_json' => $draft->theme_json,
                    'seo_json' => $draft->seo_json,
                    'created_by' => $user->id_user,
                ]);

                // Update published pointer and status
                $page->published_revision_id = $revision->id;
                $page->status = 'published';
                $page->updated_by = $user->id_user;
                $page->save();

                // Audit log
                $this->auditLog($page->id, 'published', [
                    'revision_id' => $revision->id,
                    'revision_number' => $nextRevisionNumber,
                    'sections_count' => count($contentJson),
                    'published_by' => $user->id_user,
                ]);

                return [
                    'success' => true,
                    'published_revision_id' => $revision->id,
                    'revision_number' => $nextRevisionNumber,
                    'published_at' => now()->toISOString(),
                ];
            });

            return response()->json($result);

        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $status = $e->getStatusCode();
            if (in_array($status, [409, 422])) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error' => $status === 409 ? 'conflict' : 'validation',
                ], $status);
            }
            throw $e;
        }
    }

    /**
     * Restore Revision — copies old revision snapshot to working draft.
     * POST /setting/landing-page-builder/revisions/{revisionNumber}/restore
     */
    public function restoreRevision(Request $request, int $revisionNumber): JsonResponse
    {
        $user = $request->user();
        $this->assertAdministrator($user);

        $validator = Validator::make($request->all(), [
            'base_version' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $baseVersion = (int) $request->input('base_version');
        $page = $this->getOrCreateDefaultPage($user);

        try {
            $result = DB::transaction(function () use ($page, $user, $revisionNumber, $baseVersion) {
                // Lock page first, then draft
                $page = LandingPage::where('id', $page->id)->lockForUpdate()->first();
                $draft = LandingPageDraft::where('landing_page_id', $page->id)
                    ->lockForUpdate()
                    ->first();

                if (!$draft) {
                    abort(422, 'No draft available');
                }

                // Concurrency check
                if ((int) $draft->version !== $baseVersion) {
                    abort(409, 'Draft was modified. Current version: ' . $draft->version);
                }

                // Find the revision to restore (must belong to this page)
                $revision = LandingPageRevision::where('landing_page_id', $page->id)
                    ->where('revision_number', $revisionNumber)
                    ->lockForUpdate()
                    ->first();

                if (!$revision) {
                    abort(404, 'Revision not found or belongs to another page');
                }

                // Copy revision snapshot to working draft
                $draft->content_json = $revision->content_json;
                $draft->theme_json = $revision->theme_json;
                $draft->seo_json = $revision->seo_json;
                $draft->version = $draft->version + 1;
                $draft->updated_by = $user->id_user;
                $draft->save();

                // Audit log
                $this->auditLog($page->id, 'revision_restored', [
                    'restored_from_revision' => $revisionNumber,
                    'new_draft_version' => $draft->version,
                    'restored_by' => $user->id_user,
                ]);

                return [
                    'success' => true,
                    'new_draft_version' => $draft->version,
                    'restored_from_revision' => $revisionNumber,
                    'updated_at' => $draft->updated_at?->toISOString(),
                ];
            });

            return response()->json($result);

        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $status = $e->getStatusCode();
            if (in_array($status, [404, 409, 422])) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error' => $status === 409 ? 'conflict' : ($status === 404 ? 'not_found' : 'validation'),
                ], $status);
            }
            throw $e;
        }
    }

    /**
     * Content fingerprint used by the P0.1 semantic save gate.
     *
     * Only fields that carry visible or editorial meaning are included.
     * Fields that exist solely because `normalizeSection()` filled a default
     * in the browser (headerCta, postCategory, alt_text, sticky, showImage,
     * showDate, button2Style defaults, ...) are deliberately excluded, so a
     * draft that merely got opened is never treated as edited.
     *
     * A field that IS user-facing (buttonUrl, hero fields, layout, style,
     * items) is compared type-tolerantly: JSON null and "" are the same
     * thing, "3" and 3 are the same thing. That mirrors how the renderer
     * reads these values.
     */
    private function contentFingerprint(array $content): string
    {
        $str = static function ($v): string {
            return is_scalar($v) ? (string) $v : '';
        };
        $bool = static function ($v, bool $default): int {
            if ($v === null) {
                return $default ? 1 : 0;
            }
            return $v === false || $v === 0 || $v === '0' ? 0 : 1;
        };
        $int = static function ($v): int {
            return is_numeric($v) ? (int) $v : -1;
        };

        $parts = [];
        foreach ($content as $section) {
            if (!is_array($section)) {
                $section = [];
            }
            $parts[] = json_encode([
                'id' => is_string($section['id'] ?? null) ? $section['id'] : '',
                'type' => $str($section['type'] ?? ''),
                'title' => $str($section['title'] ?? ''),
                'subtitle' => $str($section['subtitle'] ?? ''),
                'desc' => $str($section['desc'] ?? ''),
                'image' => $str($section['image'] ?? ''),
                'alt_text' => $str($section['alt_text'] ?? ''),
                'visible' => $bool($section['visible'] ?? null, true),
                // Layout & style
                'layoutWidth' => $str($section['layoutWidth'] ?? ''),
                'layoutAlign' => $str($section['layoutAlign'] ?? ''),
                'layoutColumns' => $int($section['layoutColumns'] ?? null),
                'layoutSpacing' => $str($section['layoutSpacing'] ?? ''),
                'styleBg' => $str($section['styleBg'] ?? ''),
                'styleColor' => $str($section['styleColor'] ?? ''),
                'styleRadius' => $str($section['styleRadius'] ?? ''),
                'motion' => $str($section['motion'] ?? ''),
                'fontSize' => $int($section['fontSize'] ?? null),
                'paddingTop' => $int($section['paddingTop'] ?? null),
                'paddingBottom' => $int($section['paddingBottom'] ?? null),
                'marginTop' => $int($section['marginTop'] ?? null),
                'marginBottom' => $int($section['marginBottom'] ?? null),
                // Tombol
                'buttonLabel' => $str($section['buttonLabel'] ?? ''),
                'buttonUrl' => $str($section['buttonUrl'] ?? ''),
                'buttonLabel2' => $str($section['buttonLabel2'] ?? ''),
                'buttonUrl2' => $str($section['buttonUrl2'] ?? ''),
                'button2Style' => $str($section['button2Style'] ?? ''),
                // Hero (Phase ini)
                'heroTheme' => $str($section['heroTheme'] ?? ''),
                'heroObjectPosition' => $str($section['heroObjectPosition'] ?? ''),
                'heroImageScale' => $int($section['heroImageScale'] ?? null),
                'heroBleed' => $bool($section['heroBleed'] ?? null, false),
                // Header / posts — hanya bila nilainya benar-benar diisi.
                'headerCta' => $str($section['headerCta'] ?? ''),
                'sticky' => $bool($section['sticky'] ?? null, false),
                'postLimit' => $int($section['postLimit'] ?? null),
                'postCategory' => $str($section['postCategory'] ?? ''),
                'showImage' => $bool($section['showImage'] ?? null, true),
                'showDate' => $bool($section['showDate'] ?? null, true),
                'items' => $this->normalizeItemsForCompare($section['items'] ?? []),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return hash('sha256', implode('|', $parts));
    }

    /**
     * Sort item arrays deterministically so key/element ordering coming from
     * the browser cannot masquerade as a content change, while any real
     * difference in values still produces a different fingerprint.
     */
    private function normalizeItemsForCompare($items): string
    {
        if (!is_array($items)) {
            return '[]';
        }

        $clean = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            ksort($item);
            $clean[] = $item;
        }

        $encoded = array_map(static function ($i) {
            return json_encode($i, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $clean);
        sort($encoded);

        return '[' . implode(',', $encoded) . ']';
    }

    /**
     * Get or create the default landing page (single page system for now).
     */
    private function getOrCreateDefaultPage($user): LandingPage
    {
        $page = LandingPage::first();

        if (!$page) {
            $page = LandingPage::create([
                'slug' => 'home',
                'title' => 'Halaman Utama',
                'status' => 'draft',
                'created_by' => $user->id_user,
                'updated_by' => $user->id_user,
            ]);
        }

        return $page;
    }

    /**
     * Assert user is Administrator.
     */
    private function assertAdministrator($user): void
    {
        $roleName = optional($user->role)->nama_role ?? '';
        if ($roleName !== 'Administrator') {
            abort(403, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
        }
    }

    /**
     * Validate content_json structure.
     */
    private function validateContentJson(array $sections): ?string
    {
        $allowedTypes = [
            'hero', 'about', 'services', 'product-grid', 'portfolio',
            'testimonials', 'statistics', 'faq', 'cta', 'contact', 'footer',
            // Fase 2: navbar yang bisa diedit + Kabar & Panduan dari
            //<Post::publik()>. Keduanya read-only terhadap data ERP.
            'header', 'posts'
        ];

        $allowedLayoutWidth = ['full', 'container', 'narrow'];
        $allowedLayoutAlign = ['left', 'center', 'right'];
        $allowedLayoutSpacing = ['small', 'medium', 'large'];
        $allowedMotion = ['none', 'fade', 'fade-up', 'slide'];
        // 'indigo' sengaja dipertahankan supaya draft lama yang sudah
        // tersimpan tetap bisa di-load. 'orion' adalah warna brand resmi.
        $allowedStyleBg = ['white', 'gray', 'night', 'orion', 'indigo'];
        $allowedStyleColor = ['black', 'gray', 'white', 'orion', 'indigo'];
        $allowedStyleRadius = ['none', 'small', 'medium', 'large'];

        $seenIds = [];

        foreach ($sections as $index => $section) {
            // Required fields
            if (empty($section['id'])) {
                return "Section at index $index: missing 'id'";
            }
            if (empty($section['type'])) {
                return "Section at index $index: missing 'type'";
            }
            if (empty($section['title'])) {
                return "Section at index $index: missing 'title'";
            }

            // Unique ID check
            if (in_array($section['id'], $seenIds)) {
                return "Duplicate section ID: {$section['id']}";
            }
            $seenIds[] = $section['id'];

            // ID format check
            if (!preg_match('/^s-[a-z0-9]{9}$/', $section['id'])) {
                return "Section ID {$section['id']} has invalid format (expected: s-{9chars})";
            }

            // Type check
            if (!in_array($section['type'], $allowedTypes)) {
                return "Section {$section['id']}: unsupported type '{$section['type']}'";
            }

            // String length limits
            if (strlen($section['title'] ?? '') > 200) {
                return "Section {$section['id']}: title exceeds 200 characters";
            }
            if (strlen($section['subtitle'] ?? '') > 300) {
                return "Section {$section['id']}: subtitle exceeds 300 characters";
            }
            if (strlen($section['desc'] ?? '') > 1000) {
                return "Section {$section['id']}: description exceeds 1000 characters";
            }
            if (strlen($section['buttonLabel'] ?? '') > 50) {
                return "Section {$section['id']}: button label exceeds 50 characters";
            }

            // --- Hero: tombol kedua (Fase 2 Final) --------------------------
            // Disimpan sebagai data builder biasa di dalam content_json,
            // jadi tidak ada perubahan skema database. Semua batas mirroring
            // tombol pertama supaya perilakunya identik.
            $lpBtn2Label = $section['buttonLabel2'] ?? '';
            $lpBtn2Url   = $section['buttonUrl2'] ?? '';
            $lpBtn2Style = $section['button2Style'] ?? 'outline';

            if ($lpBtn2Label !== null && ! is_string($lpBtn2Label)) {
                return "Section {$section['id']}: buttonLabel2 must be a string";
            }
            if (strlen((string) $lpBtn2Label) > 50) {
                return "Section {$section['id']}: buttonLabel2 exceeds 50 characters";
            }
            if (! in_array($lpBtn2Style, ['primary', 'dark', 'outline', 'link'], true)) {
                return "Section {$section['id']}: invalid button2Style";
            }
            if ($lpBtn2Url !== null && $lpBtn2Url !== '' && ! is_string($lpBtn2Url)) {
                return "Section {$section['id']}: buttonUrl2 must be a string";
            }
            if (strlen((string) $lpBtn2Url) > 2000) {
                return "Section {$section['id']}: buttonUrl2 exceeds 2000 characters";
            }

            // Button URL validation
            //
            // Path relatif (dimulai dengan /) dan anchor internal (dimulai dengan #)
            // DITERIMA karena dipakai untuk navigasi internal homepage, termasuk
            // route login ERP (route('login') = /masuk) pada tombol "Masuk Staf".
            // Renderer menerapkan aturan yang sama lewat $lpSafeUrl, jadi
            // server dan renderer tidak boleh berbeda aturan.
            //
            // Skema berbahaya (javascript:, data:, vbscript:, file:, about:)
            // tetap ditolak oleh filter_var + daftar putih skema di bawah.
            if (!empty($section['buttonUrl'])) {
                $rawUrl = trim((string) $section['buttonUrl']);

                if ($rawUrl !== '' && $rawUrl[0] === '#') {
                    // Anchor internal (#tentang, #faq). Hanya nama anchor
                    // yang sah: huruf, angka, tanda hubung, garis bawah.
                    if (!preg_match('/^#[A-Za-z0-9_-]+$/', $rawUrl)) {
                        return "Section {$section['id']}: invalid anchor in button URL";
                    }
                } elseif (str_starts_with($rawUrl, '/') && ! str_starts_with($rawUrl, '//')) {
                    // Path internal: tolak yang mencoba keluar dari root,
                    // misalnya "/../" atau yang berisi backslash.
                    if (str_contains($rawUrl, '..') || str_contains($rawUrl, '\\')) {
                        return "Section {$section['id']}: button URL must not contain path traversal";
                    }
                } else {
                    if (!filter_var($rawUrl, FILTER_VALIDATE_URL)) {
                        return "Section {$section['id']}: invalid button URL";
                    }
                    $scheme = parse_url($rawUrl, PHP_URL_SCHEME);
                    if (!in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
                        return "Section {$section['id']}: button URL must use http, https, an anchor, or a path starting with /";
                    }
                }
            }

            // URL tombol kedua memakai aturan yang PERSIS sama dengan tombol
            // pertama (lihat blok validasi buttonUrl di atas): http/https
            // absolut, path internal (/) atau anchor (#). Skema berbahaya
            // dan path traversal ditolak.
            $lpBtn2Raw = trim((string) ($section['buttonUrl2'] ?? ''));
            if ($lpBtn2Raw !== '') {
                if ($lpBtn2Raw[0] === '#') {
                    if (! preg_match('/^#[A-Za-z0-9_-]+$/', $lpBtn2Raw)) {
                        return "Section {$section['id']}: invalid anchor in buttonUrl2";
                    }
                } elseif (str_starts_with($lpBtn2Raw, '/') && ! str_starts_with($lpBtn2Raw, '//')) {
                    if (str_contains($lpBtn2Raw, '..') || str_contains($lpBtn2Raw, '\\')) {
                        return "Section {$section['id']}: buttonUrl2 must not contain path traversal";
                    }
                } else {
                    if (! filter_var($lpBtn2Raw, FILTER_VALIDATE_URL)) {
                        return "Section {$section['id']}: invalid buttonUrl2";
                    }
                    $lpBtn2Scheme = parse_url($lpBtn2Raw, PHP_URL_SCHEME);
                    if (! in_array(strtolower((string) $lpBtn2Scheme), ['http', 'https'], true)) {
                        return "Section {$section['id']}: buttonUrl2 must use http, https, an anchor, or a path starting with /";
                    }
                }
            }

            // Layout presets
            if (!empty($section['layoutWidth']) && !in_array($section['layoutWidth'], $allowedLayoutWidth)) {
                return "Section {$section['id']}: invalid layoutWidth";
            }
            if (!empty($section['layoutAlign']) && !in_array($section['layoutAlign'], $allowedLayoutAlign)) {
                return "Section {$section['id']}: invalid layoutAlign";
            }
            if (!empty($section['layoutSpacing']) && !in_array($section['layoutSpacing'], $allowedLayoutSpacing)) {
                return "Section {$section['id']}: invalid layoutSpacing";
            }
            if (!empty($section['layoutColumns']) && (!is_int($section['layoutColumns']) || $section['layoutColumns'] < 1 || $section['layoutColumns'] > 4)) {
                return "Section {$section['id']}: invalid layoutColumns (1-4)";
            }

            // Motion preset
            if (!empty($section['motion']) && !in_array($section['motion'], $allowedMotion)) {
                return "Section {$section['id']}: invalid motion preset";
            }

            // Style presets
            if (!empty($section['styleBg']) && !in_array($section['styleBg'], $allowedStyleBg)) {
                return "Section {$section['id']}: invalid styleBg";
            }
            if (!empty($section['styleColor']) && !in_array($section['styleColor'], $allowedStyleColor)) {
                return "Section {$section['id']}: invalid styleColor";
            }
            if (!empty($section['styleRadius']) && !in_array($section['styleRadius'], $allowedStyleRadius)) {
                return "Section {$section['id']}: invalid styleRadius";
            }

            // --- Spasi & ukuran font --------------------------------------
            // Renderer menyuntik nilai ini ke style inline, jadi di sini
            // dipaksa menjadi bilangan bulat dalam rentang. Tanpa aturan ini
            // klien bisa mengirim string bebas yang lolos ke atribut style.
            $numericRules = [
                'paddingTop' => [0, 200],
                'paddingBottom' => [0, 200],
                'marginTop' => [-200, 200],
                'marginBottom' => [-200, 200],
                'fontSize' => [12, 96],
            ];
            foreach ($numericRules as $key => $range) {
                if (! array_key_exists($key, $section) || $section[$key] === null) {
                    continue;
                }
                if (! is_numeric($section[$key])) {
                    return "Section {$section['id']}: {$key} must be a number";
                }
                if ((float) $section[$key] != (int) $section[$key]) {
                    return "Section {$section['id']}: {$key} must be a whole number";
                }
                $n = (int) $section[$key];
                if ($n < $range[0] || $n > $range[1]) {
                    return "Section {$section['id']}: {$key} must be between {$range[0]} and {$range[1]}";
                }
            }

            // --- Gambar ----------------------------------------------------
            // Hanya boleh path dari media manager internal atau URL http(s).
            // Scheme berbahaya ditolak di sini, bukan hanya saat render.
            $imageError = $this->validateImageReference($section['image'] ?? null);
            if ($imageError !== null) {
                return "Section {$section['id']}: {$imageError}";
            }

            if (isset($section['alt_text']) && is_string($section['alt_text']) && strlen($section['alt_text']) > 255) {
                return "Section {$section['id']}: alt_text exceeds 255 characters";
            }

            // --- Field khusus header (Fase 2) ------------------------------
            // 'sticky' hanya boolean; menyalakan ini tidak mengubah apa pun
            // di luar section ini.
            if (array_key_exists('sticky', $section) && $section['sticky'] !== null
                && ! is_bool($section['sticky'])) {
                return "Section {$section['id']}: sticky must be boolean";
            }

            // --- Field khusus posts (Fase 2) --------------------------------
            // postLimit dibatasi 1..12 supaya satu halaman tidak pernah
            // mengambil ribuan artikel.
            if (array_key_exists('postLimit', $section) && $section['postLimit'] !== null) {
                if (! is_numeric($section['postLimit']) || (int) $section['postLimit'] < 1 || (int) $section['postLimit'] > 12) {
                    return "Section {$section['id']}: postLimit must be between 1 and 12";
                }
            }
            foreach (['showDate', 'showImage'] as $flag) {
                if (array_key_exists($flag, $section) && $section[$flag] !== null && ! is_bool($section[$flag])) {
                    return "Section {$section['id']}: {$flag} must be boolean";
                }
            }
            if (! empty($section['postCategory']) && ! is_string($section['postCategory'])) {
                return "Section {$section['id']}: postCategory must be a string";
            }
            if (! empty($section['postCategory']) && strlen($section['postCategory']) > 120) {
                return "Section {$section['id']}: postCategory exceeds 120 characters";
            }

            // --- Repeater items ---------------------------------------------
            // Batas jumlah dan panjang per field. Tanpa ini satu section bisa
            // menyimpan array tak terbatas dan membanjiri halaman publik.
            if (array_key_exists('items', $section) && $section['items'] !== null) {
                if (! is_array($section['items'])) {
                    return "Section {$section['id']}: items must be an array";
                }
                if (! array_is_list($section['items'])) {
                    return "Section {$section['id']}: items must be a sequential list";
                }
                if (count($section['items']) > 50) {
                    return "Section {$section['id']}: maximum 50 items per section";
                }
                $itemLimits = [
                    'title' => 200, 'desc' => 500, 'quote' => 1000, 'author' => 120,
                    'value' => 60, 'label' => 200, 'question' => 300, 'answer' => 2000,
                    'alt_text' => 255, 'image' => 500,
                    // Fase Final: kategori portfolio untuk filter.
                    'category' => 120,
                ];
                foreach ($section['items'] as $itemIndex => $item) {
                    if (! is_array($item)) {
                        return "Section {$section['id']}: item {$itemIndex} must be an object";
                    }
                    foreach ($item as $itemKey => $itemValue) {
                        if (! array_key_exists($itemKey, $itemLimits)) {
                            return "Section {$section['id']}: item {$itemIndex} has unknown field '{$itemKey}'";
                        }
                        if ($itemValue === null) {
                            continue;
                        }
                        if (! is_string($itemValue)) {
                            return "Section {$section['id']}: item {$itemIndex} field '{$itemKey}' must be a string";
                        }
                        if (strlen($itemValue) > $itemLimits[$itemKey]) {
                            return "Section {$section['id']}: item {$itemIndex} field '{$itemKey}' exceeds {$itemLimits[$itemKey]} characters";
                        }
                    }
                    $itemImageError = $this->validateImageReference($item['image'] ?? null);
                    if ($itemImageError !== null) {
                        return "Section {$section['id']}: item {$itemIndex} {$itemImageError}";
                    }
                }
            }

            // Visibility
            if (isset($section['visible']) && !is_bool($section['visible'])) {
                return "Section {$section['id']}: visibility must be boolean";
            }
        }

        // Max sections
        if (count($sections) > 100) {
            return 'Maximum 100 sections allowed';
        }

        return null;
    }

    /**
     * Validasi satu referensi gambar.
     *
     * Mengembalikan pesan error, atau null bila nilai ini aman.
     *
     * Aturan:
     *  - kosong / null -> aman (gambar opsional)
     *  - harus string
     *  - scheme javascript:/data:/vbscript:/file:/about: ditolak
     *  - path internal hanya boleh berawalan landing-media/ dan tidak boleh
     *    mengandung '..' (path traversal keluar dari folder media)
     *  - selebihnya harus URL http(s) yang valid
     */
    private function validateImageReference($image): ?string
    {
        if ($image === null || $image === '') {
            return null;
        }

        if (! is_string($image)) {
            return 'image must be a string';
        }

        if (preg_match('/^(javascript|data|vbscript|file|about):/i', $image)) {
            return 'image URL uses a forbidden scheme';
        }

        if (str_starts_with($image, 'landing-media/')) {
            if (str_contains($image, '..')) {
                return "image path must not contain '..'";
            }
            return null;
        }

        if (! filter_var($image, FILTER_VALIDATE_URL)) {
            return 'invalid image URL';
        }

        $scheme = strtolower((string) parse_url($image, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return 'image URL must use http or https';
        }

        return null;
    }

    // ===== MEDIA MANAGER =====

    /**
     * Upload media (image only).
     * POST /setting/landing-page-builder/media
     */
    public function uploadMedia(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->assertAdministrator($user);

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('file');

        // MIME validation
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file->getRealPath());

        if (!in_array($mime, $allowedMimes)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file type. Allowed: JPEG, PNG, GIF, WebP',
            ], 415);
        }

        // File signature validation (magic bytes)
        $handle = fopen($file->getRealPath(), 'rb');
        $bytes = fread($handle, 12);
        fclose($handle);

        $validSignatures = [
            "\xFF\xD8\xFF", // JPEG
            "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A", // PNG
            "GIF87a", "GIF89a", // GIF
            "RIFF", // WebP (starts with RIFF)
        ];

        $valid = false;
        foreach ($validSignatures as $sig) {
            if (strpos($bytes, $sig) === 0) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            return response()->json([
                'success' => false,
                'message' => 'File signature does not match allowed image types',
            ], 415);
        }

        // Get image dimensions
        $dimensions = @getimagesize($file->getRealPath());
        if (!$dimensions) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid image file',
            ], 422);
        }

        [$width, $height] = $dimensions;

        // Max dimensions: 4096x4096
        if ($width > 4096 || $height > 4096) {
            return response()->json([
                'success' => false,
                'message' => 'Image dimensions too large (max 4096x4096)',
            ], 422);
        }

        // Safe filename
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'bin',
        };

        $filename = 'lm_' . time() . '_' . Str::random(10) . '.' . $extension;
        $path = 'landing-media/' . $filename;

        // Store file
        $file->storeAs('landing-media', $filename, 'public');

        // Get or create page
        $page = $this->getOrCreateDefaultPage($user);

        // Create media record
        $media = LandingPageMedia::create([
            'landing_page_id' => $page->id,
            'disk' => 'public',
            'path' => $path,
            'mime_type' => $mime,
            'size' => $file->getSize(),
            'metadata' => [
                'alt_text' => $request->input('alt_text', ''),
                'caption' => $request->input('caption', ''),
                'width' => $width,
                'height' => $height,
            ],
            'uploaded_by' => $user->id_user,
        ]);

        // Audit log
        $this->auditLog($page->id, 'media_uploaded', [
            'media_id' => $media->id,
            'mime_type' => $mime,
            'size' => $file->getSize(),
        ]);

        return response()->json([
            'success' => true,
            'media' => [
                'id' => $media->id,
                'path' => $media->path,
                'url' => asset('storage/' . $media->path),
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'metadata' => $media->metadata,
            ],
        ]);
    }

    /**
     * List media for the page.
     * GET /setting/landing-page-builder/media
     */
    public function listMedia(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->assertAdministrator($user);

        $page = $this->getOrCreateDefaultPage($user);

        $media = LandingPageMedia::where('landing_page_id', $page->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($m) {
                return [
                    'id' => $m->id,
                    'path' => $m->path,
                    'url' => asset('storage/' . $m->path),
                    'mime_type' => $m->mime_type,
                    'size' => $m->size,
                    'metadata' => $m->metadata,
                    'created_at' => $m->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'media' => $media,
        ]);
    }

    /**
     * Delete media (safe — checks references first).
     * DELETE /setting/landing-page-builder/media/{id}
     */
    public function deleteMedia(Request $request, int $mediaId): JsonResponse
    {
        $user = $request->user();
        $this->assertAdministrator($user);

        $page = $this->getOrCreateDefaultPage($user);

        $media = LandingPageMedia::where('landing_page_id', $page->id)
            ->where('id', $mediaId)
            ->first();

        if (!$media) {
            return response()->json([
                'success' => false,
                'message' => 'Media not found',
            ], 404);
        }

        // Check references in draft
        $draft = LandingPageDraft::where('landing_page_id', $page->id)->first();
        if ($draft) {
            $content = json_encode($draft->content_json);
            // Check for media path (JSON encoding escapes / as \/, so check both)
            $path = $media->path;
            $escapedPath = str_replace('/', '\/', $path);
            if (str_contains($content, $path) || str_contains($content, $escapedPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Media is referenced in current draft. Remove from sections first.',
                ], 422);
            }
        }

        // Check references in published revision
        $publishedRevision = LandingPageRevision::where('id', $page->published_revision_id)->first();
        if ($publishedRevision) {
            $content = json_encode($publishedRevision->content_json);
            $path = $media->path;
            $escapedPath = str_replace('/', '\/', $path);
            if (str_contains($content, $path) || str_contains($content, $escapedPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Media is referenced in published revision. Cannot delete.',
                ], 422);
            }
        }

        // Safe to delete
        Storage::disk('public')->delete($media->path);
        $media->delete();

        // Audit log
        $this->auditLog($page->id, 'media_deleted', [
            'media_id' => $mediaId,
            'path' => $media->path,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Media deleted',
        ]);
    }

    // ===== PREVIEW RENDERER =====

    /**
     * Preview draft.
     * GET /setting/landing-page-builder/preview/draft
     */
    public function previewDraft(Request $request)
    {
        $user = $request->user();
        $this->assertAdministrator($user);

        $page = $this->getOrCreateDefaultPage($user);
        $draft = $page->draft;

        $content = $draft ? ($draft->content_json ?? []) : [];
        $theme = $draft ? ($draft->theme_json ?? []) : [];
        $seo = $draft ? ($draft->seo_json ?? []) : [];

        // no-store: draft TIDAK BOLEH disimpan sebagai cache publik oleh
        // browser maupun proxy LAN. Draft adalah kerja internal Administrator
        // dan tidak boleh bocor lewat cache bersama.
        return response()->view('pengaturan.landing-page-preview', [
            'sections' => $content,
            'theme' => $theme,
            'seo' => $seo,
            'page' => $page,
            'previewMode' => 'draft',
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Preview draft via iframe (for responsive device switcher).
     * GET /setting/landing-page-builder/preview/draft/iframe
     * Returns full HTML with CSS and viewport meta for proper responsive rendering.
     */
    public function previewDraftIframe(Request $request)
    {
        $user = $request->user();
        $this->assertAdministrator($user);

        $page = $this->getOrCreateDefaultPage($user);

        // Wrapper preview (draft/published/revision) memakai SATU iframe
        // supaya styling & device switcher identik. Isi iframe ditentukan
        // oleh ?mode=, bukan selalu draft — kalau selalu draft, maka
        // "Published Testing" menampilkan konten yang belum dipublish.
        $mode = (string) $request->query('mode', 'draft');
        $revisionNumber = (int) $request->query('rev', 1);

        if ($mode === 'published') {
            $revision = $page->publishedRevision;
            $content = $revision ? ($revision->content_json ?? []) : [];
            $theme = $revision ? ($revision->theme_json ?? []) : [];
            $seo = $revision ? ($revision->seo_json ?? []) : [];
        } elseif ($mode === 'revision') {
            $revision = $page->revisions()->where('revision_number', $revisionNumber)->first();
            $content = $revision ? ($revision->content_json ?? []) : [];
            $theme = $revision ? ($revision->theme_json ?? []) : [];
            $seo = $revision ? ($revision->seo_json ?? []) : [];
        } else {
            $draft = $page->draft;
            $content = $draft ? ($draft->content_json ?? []) : [];
            $theme = $draft ? ($draft->theme_json ?? []) : [];
            $seo = $draft ? ($draft->seo_json ?? []) : [];
        }

        // Render full iframe page with CSS and viewport meta
        return response()->view('pengaturan.landing-page-preview-iframe', [
            'sections' => $content,
            'theme' => $theme,
            'seo' => $seo,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0')
          ->header('X-Frame-Options', 'SAMEORIGIN');
    }

    /**
     * Preview published revision.
     * GET /setting/landing-page-builder/preview/published
     */
    public function previewPublished(Request $request)
    {
        $user = $request->user();
        $this->assertAdministrator($user);

        $page = $this->getOrCreateDefaultPage($user);
        $revision = $page->publishedRevision;

        if (!$revision) {
            abort(404, 'No published revision available');
        }

        return view('pengaturan.landing-page-preview', [
            'sections' => $revision->content_json ?? [],
            'theme' => $revision->theme_json ?? [],
            'seo' => $revision->seo_json ?? [],
            'page' => $page,
            'previewMode' => 'published',
        ]);
    }

    /**
     * Preview historical revision.
     * GET /setting/landing-page-builder/preview/revision/{revisionNumber}
     */
    public function previewRevision(Request $request, int $revisionNumber)
    {
        $user = $request->user();
        $this->assertAdministrator($user);

        $page = $this->getOrCreateDefaultPage($user);

        $revision = LandingPageRevision::where('landing_page_id', $page->id)
            ->where('revision_number', $revisionNumber)
            ->first();

        if (!$revision) {
            abort(404, 'Revision not found');
        }

        return view('pengaturan.landing-page-preview', [
            'sections' => $revision->content_json ?? [],
            'theme' => $revision->theme_json ?? [],
            'seo' => $revision->seo_json ?? [],
            'page' => $page,
            'previewMode' => 'revision',
            // WAJIB diteruskan ke view. Tanpa ini, wrapper preview memakai
            // fallback rev=1 sehingga setiap revisi menampilkan revisi #1.
            'revisionNumber' => $revisionNumber,
        ]);
    }

    /**
     * Public landing page - accessible without authentication.
     * Shows published revision if available, otherwise 404.
     * GET /landing-page
     */
    public function publicLandingPage(Request $request)
    {
        $page = \App\Models\LandingPage::where('slug', 'home')->first();

        if (!$page || $page->status !== 'published' || !$page->published_revision_id) {
            abort(404, 'Landing page not published');
        }

        $revision = \App\Models\LandingPageRevision::find($page->published_revision_id);

        if (!$revision) {
            abort(404, 'Published revision not found');
        }

        // Validasi schema version
        $schemaVersion = trim((string) $revision->schema_version);
        if (!in_array($schemaVersion, ['1.0.0'], true)) {
            abort(404, 'Unsupported schema version');
        }

        // Validasi struktur konten
        $content = $revision->content_json;
        if (isset($content['sections']) && is_array($content['sections'])) {
            $sections = array_values($content['sections']);
        } elseif (is_array($content)) {
            $sections = array_values($content);
        } else {
            $sections = [];
        }

        if (empty($sections)) {
            abort(404, 'No content available');
        }

        return response()->view('landing-page-public', [
            'sections' => $sections,
            'theme' => $revision->theme_json ?? [],
            'seo' => $revision->seo_json ?? [],
            'set' => \App\Models\AppSetting::current(),
        ])->header('Cache-Control', 'public, max-age=60');
    }

    /**
     * Write audit log using existing audit_logs table.
     */
    private function auditLog(int $pageId, string $event, array $data): void
    {
        try {
            AuditLog::create([
                'user_id' => auth()->id() ? auth()->id() : null,
                'user_label' => auth()->user()?->username,
                'event' => $event,
                'auditable_type' => 'LandingPage',
                'auditable_id' => $pageId,
                'keterangan' => 'Draft saved via Landing Page Builder',
                'new_values' => $data,
                'url' => url()->current(),
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Audit log failure should not break the main operation
            \Log::warning('Landing Page audit log failed', [
                'page_id' => $pageId,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }
}