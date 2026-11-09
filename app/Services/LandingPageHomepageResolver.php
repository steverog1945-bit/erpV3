<?php

namespace App\Services;

use App\Models\LandingPage;
use App\Models\LandingPageRevision;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Resolver untuk menentukan homepage mana yang harus dirender.
 *
 * Ketika feature flag OFF: gunakan homepage existing (login view).
 * Ketika feature flag ON: gunakan published landing page yang valid,
 * atau fallback ke homepage existing jika published revision tidak valid.
 */
class LandingPageHomepageResolver
{
    /**
     * Versi schema yang boleh dirender publik.
     *
     * PENTING: nilai ini harus sama persis dengan yang ditulis publish flow
     * (LandingPageBuilderController::publish menulis '1.0.0') dan default
     * kolom di migration. Perbandingan dilakukan sebagai string, bukan
     * (int), supaya '1.0.0' tidak diam-diam jadi 1 dan '1.10' tidak jadi 1.
     */
    public const SUPPORTED_SCHEMA_VERSIONS = ['1.0.0'];

    /**
     * Memutuskan homepage mana yang dirender.
     *
     * @return array{mode: string, view: string, data: array}
     */
    public function resolve(Request $request): array
    {
        // Flag OFF: selalu gunakan homepage existing.
        if (!config('landing_page_builder.enabled', false)) {
            return [
                'mode' => 'existing',
                'view' => 'login',
                'data' => [],
            ];
        }

        // Flag ON: cari published landing page yang valid.
        try {
            $revision = $this->findValidPublishedRevision();
        } catch (\Throwable $e) {
            Log::warning('Landing page resolver: read failure, fallback to existing', [
                'error' => $e->getMessage(),
            ]);

            return $this->existingHomepage();
        }

        if ($revision === null) {
            return $this->existingHomepage();
        }

        return [
            'mode' => 'builder',
            'view' => 'landing-page-public',
            'data' => [
                // Dinormalisasi ke daftar section datar. Renderer mengiterasi
                // daftar section, jadi bentuk {sections: [...]} harus dilepas
                // di sini, bukan diteruskan apa adanya.
                'sections' => $this->normalizeSections($revision->content_json),
                'theme' => $revision->theme_json,
                'seo' => $revision->seo_json,
            ],
        ];
    }

    /**
     * Ubah content_json menjadi daftar section datar.
     *
     * Dua bentuk yang diterima:
     *   [ {...}, {...} ]         -> sudah datar
     *   { 'sections': [ ... ] }  -> dibungkus
     *
     * @param  mixed  $content
     * @return array<int, mixed>
     */
    private function normalizeSections(mixed $content): array
    {
        if (isset($content['sections']) && is_array($content['sections'])) {
            return array_values($content['sections']);
        }

        if (is_array($content)) {
            return array_values($content);
        }

        return [];
    }

    /**
     * Homepage existing (login view) sebagai fallback.
     *
     * @return array{mode: string, view: string, data: array}
     */
    private function existingHomepage(): array
    {
        return [
            'mode' => 'existing',
            'view' => 'login',
            'data' => [],
        ];
    }

    /**
     * Cari published revision yang valid untuk slug yang dikonfigurasi.
     *
     * @return LandingPageRevision|null
     */
    private function findValidPublishedRevision(): ?LandingPageRevision
    {
        $slug = (string) config('landing_page_builder.published_slug', 'home');

        $page = LandingPage::where('slug', $slug)->first();

        if ($page === null) {
            return null;
        }

        // Pastikan status published.
        if ($page->status !== 'published') {
            return null;
        }

        // Ambil revision yang ditunjuk oleh published_revision_id.
        $revisionId = $page->published_revision_id;

        if ($revisionId === null) {
            return null;
        }

        $revision = LandingPageRevision::where('id', $revisionId)
            ->where('landing_page_id', $page->id)
            ->first();

        if ($revision === null) {
            return null;
        }

        // Validasi schema version (kolom sendiri di tabel, bukan di dalam content_json).
        // Dibandingkan sebagai string terhadap allowlist - bukan lewat (int),
        // supaya '1.0.0' tidak diam-diam berubah jadi 1.
        $schemaVersion = trim((string) $revision->schema_version);

        if (!in_array($schemaVersion, self::SUPPORTED_SCHEMA_VERSIONS, true)) {
            Log::warning('Landing page resolver: unsupported schema version', [
                'revision_id' => $revision->id,
                'schema_version' => $schemaVersion,
            ]);

            return null;
        }

        // Validasi struktur konten.
        if (!$this->isValidContentStructure($revision->content_json)) {
            Log::warning('Landing page resolver: invalid content structure', [
                'revision_id' => $revision->id,
            ]);

            return null;
        }

        return $revision;
    }

    /**
     * Validasi struktur content_json.
     *
     * @param  mixed  $content
     * @return bool
     */
    private function isValidContentStructure(mixed $content): bool
    {
        if (!is_array($content)) {
            return false;
        }

        // Jika formatnya adalah array sections langsung.
        if (isset($content[0]) && is_array($content[0])) {
            return $this->isValidSectionsArray($content);
        }

        // Jika formatnya adalah object dengan key sections.
        if (isset($content['sections']) && is_array($content['sections'])) {
            return $this->isValidSectionsArray($content['sections']);
        }

        return false;
    }

    /**
     * Validasi array of sections.
     *
     * @param  array  $sections
     * @return bool
     */
    private function isValidSectionsArray(array $sections): bool
    {
        foreach ($sections as $section) {
            if (!is_array($section)) {
                return false;
            }

            // Setiap section harus punya id, type, dan visible.
            if (empty($section['id']) || empty($section['type'])) {
                return false;
            }

            if (!isset($section['visible'])) {
                return false;
            }
        }

        return true;
    }
}