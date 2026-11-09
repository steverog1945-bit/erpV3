<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PWA: manifest, service worker, ikon, dan halaman offline.
 *
 * Yang dijaga:
 *  - Keduanya PUBLIK (200 tanpa login) karena diambil browser saat halaman login
 *    pun dibuka - kalau terproteksi auth, app tidak akan pernah bisa dipasang.
 *  - Manifest lengkap & valid supaya layak pasang (name, start_url, ikon square).
 *  - Service worker network-first untuk halaman/API: tidak pernah menyetor data
 *    transaksi/keuangan ke cache (angka harus selalu segar saat online).
 */
class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_publik_dan_valid_untuk_pasang(): void
    {
        $res = $this->get(route('pwa.manifest')); // tanpa actingAs = tamu
        $res->assertOk();

        $this->assertStringContainsString(
            'application/manifest+json',
            (string) $res->headers->get('Content-Type')
        );

        $m = json_decode($res->getContent(), true);
        $this->assertIsArray($m, 'Manifest harus berupa JSON valid');
        $this->assertSame('ORION GRAFIKA', $m['name']);
        $this->assertSame('standalone', $m['display']);
        $this->assertStringEndsWith('/dashboard', $m['start_url']);
        $this->assertNotEmpty($m['scope']);

        // Ikon square wajib ada: minimal 192 dan 512 untuk kriteria pasang.
        $ukuran = array_column($m['icons'], 'sizes');
        $this->assertContains('192x192', $ukuran);
        $this->assertContains('512x512', $ukuran);
        foreach ($m['icons'] as $ikon) {
            $this->assertFileExists(public_path(str_replace(asset('/'), '', $ikon['src'])));
        }
    }

    public function test_service_worker_publik_dan_jaringan_dulu(): void
    {
        $res = $this->get(route('pwa.sw')); // tanpa actingAs = tamu
        $res->assertOk();
        $this->assertStringContainsString('text/javascript', (string) $res->headers->get('Content-Type'));

        $js = $res->getContent();
        // Halaman: network-first (fallback ke halaman offline saat putus).
        $this->assertStringContainsString("req.mode === 'navigate'", $js);
        $this->assertStringContainsString('fetch(req)', $js);
        $this->assertStringContainsString('OFFLINE_URL', $js);
        // Halaman offline ikut di-precache saat pemasangan.
        $this->assertStringContainsString('cache.addAll', $js);
        // Aset statis pakai cache-first (kecepatan), tapi API tak di-cache:
        // blok terakhir sengaja tidak memanggil respondWith untuk non-aset.
        $this->assertStringContainsString('isAsetStatis', $js);
        $this->assertStringContainsString("url.pathname.indexOf('/storage/') !== -1", $js);
        $this->assertStringContainsString('orion-pwa-v2', $js);
        $this->assertStringContainsString("k.startsWith('orion-pwa-')", $js);
        $this->assertStringContainsString("res.headers.get('Cache-Control')", $js);
    }

    public function test_berkas_ikon_dan_halaman_offline_ada(): void
    {
        foreach (['icon-192.png', 'icon-512.png', 'apple-touch-icon.png', 'icon-maskable-512.png'] as $f) {
            $this->assertFileExists(public_path('icons/' . $f));
            $this->assertGreaterThan(0, filesize(public_path('icons/' . $f)), "Ikon $f tidak boleh kosong");
        }
        $this->assertFileExists(public_path('offline.html'));
        $this->assertStringContainsString('Offline', (string) file_get_contents(public_path('offline.html')));
    }

    public function test_layout_memasang_meta_dan_pendaftaran_sw(): void
    {
        // Halaman login (publik) juga harus memasang meta PWA agar app bisa
        // dipasang bahkan sebelum masuk.
        $res = $this->get('/masuk');
        $res->assertOk();
        $res->assertSee('rel="manifest"', false)
            ->assertSee('name="theme-color"', false)
            ->assertSee('apple-touch-icon', false)
            ->assertSee('serviceWorker.register', false);
    }
}
