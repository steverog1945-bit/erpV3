<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use App\Services\BrandThemeService;

/**
 * Berkas PWA (manifest + service worker) yang dilayani lewat rute, bukan file
 * statis. Alasannya: aplikasi ini disajikan dari folder proyek sehingga aset
 * berada di sub-path `/public/` sedangkan route berada di root. Dengan memakai
 * helper url()/asset() URL di dalamnya ikut menyesuaikan bila aplikasi dipindah
 * ke domain atau path lain, tanpa perlu mengubah berkas.
 */
class PwaController extends Controller
{
    public function manifest(): Response
    {
        $theme = app(BrandThemeService::class)->current();
        $manifest = [
            'name'            => 'ORION GRAFIKA',
            'short_name'      => 'ORION',
            'description'     => 'Aplikasi manajemen percetakan ORION GRAFIKA',
            'lang'            => 'id',
            'dir'             => 'ltr',
            'start_url'       => url('/dashboard'),
            'scope'           => url('/'),
            'display'         => 'standalone',
            'orientation'     => 'portrait-primary',
            'background_color' => $theme['surfaces']['page'],
            'theme_color'     => $theme['accent']['light'],
            'icons' => [
                [
                    'src'    => asset('icons/icon-192.png'),
                    'sizes'  => '192x192',
                    'type'   => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src'    => asset('icons/icon-512.png'),
                    'sizes'  => '512x512',
                    'type'   => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src'    => asset('icons/icon-maskable-512.png'),
                    'sizes'  => '512x512',
                    'type'   => 'image/png',
                    'purpose' => 'maskable',
                ],
                [
                    'src'    => asset('icons/apple-touch-icon.png'),
                    'sizes'  => '180x180',
                    'type'   => 'image/png',
                    'purpose' => 'any',
                ],
            ],
        ];

        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return response($json, 200, [
            'Content-Type'  => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function serviceWorker(): Response
    {
        // Daftar aset yang di-cache saat pemasangan. Hanya aset statis (CSS/JS/
        // gambar/font) dan halaman offline - TIDAK PERNAH data transaksi atau
        // endpoint API, karena angka keuangan harus selalu segar dari server.
        $offlineUrl = asset('offline.html');
        $sw = <<<'JS'
/* Service worker ORION GRAFIKA.
 *
 * Kebijakan cache yang dijaga:
 *  - Navigasi (HTML) dan API: diambil dari jaringan (network-first / jaringan
 *    murni). Angka piutang, KPI, dan data transaksi TIDAK PERNAH dilayani dari
 *    cache, supaya tidak pernah basi saat online.
 *  - Aset statis (CSS/JS/gambar/font): cache-first karena berkas build sudah
 *    berversi hash dan berkas vendor jarang berubah - ini sumber kecepatan PWA.
 *  - Offline: halaman navigasi diganti halaman offline yang sudah di-cache.
 */
const CACHE = '__CACHE_KEY__';
const OFFLINE_URL = '__OFFLINE_URL__';

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE)
      .then((cache) => cache.addAll([OFFLINE_URL]))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k.startsWith('orion-pwa-') && k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

function isAsetStatis(url) {
  // Dua bentuk URL privat: /storage/... dan /public/storage/...
  // Tidak boleh disimpan di CacheStorage walaupun berakhiran gambar.
  if (url.pathname.indexOf('/storage/') !== -1) return false;
  return /\.(?:css|js|mjs|woff2?|ttf|otf|eot|png|jpe?g|svg|gif|webp|ico)$/i.test(url.pathname)
    || url.pathname.indexOf('/build/') !== -1;
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;                    // tulis (POST/PATCH) biar ke server
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;     // permintaan lintas domain tak disentuh

  // Halaman: network-first, offline -> halaman offline (bukan data basi).
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).catch(() => caches.match(OFFLINE_URL))
    );
    return;
  }

  // Aset statis: cache-first.
  if (isAsetStatis(url)) {
    event.respondWith(
      caches.match(req).then((hit) => {
        if (hit) return hit;
        return fetch(req).then((res) => {
          if (res && res.ok && !/\b(?:private|no-store)\b/i.test(res.headers.get('Cache-Control') || '')) {
            const salinan = res.clone();
            caches.open(CACHE).then((cache) => cache.put(req, salinan));
          }
          return res;
        });
      })
    );
    return;
  }

  // Sisanya (API, KPI, JSON): jangan di-cache sama sekali - biar jaringan.
});

JS;

        $sw = str_replace('__CACHE_KEY__', 'orion-pwa-v3', $sw);
        $sw = str_replace('__OFFLINE_URL__', $offlineUrl, $sw);

        return response($sw, 200, [
            'Content-Type'              => 'text/javascript; charset=utf-8',
            'Service-Worker-Allowed'    => '/',
            'Cache-Control'             => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
