{{--
    PUBLIC Landing Page Renderer.

    Hanya dirender ketika:
    1. Feature flag LANDING_PAGE_BUILDER_ENABLED = true
    2. Published revision valid tersedia

    Semua konten sudah divalidasi server-side oleh LandingPageHomepageResolver.
    Preview URL tidak pernah menjadi canonical.
    Draft tidak pernah diekspos publik.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- Canonical: selalu ke homepage, JANGAN pernah ke URL preview. --}}
    @php $lpCanonical = url('/'); @endphp
    <link rel="canonical" href="{{ $lpCanonical }}">

    <title>{{ !empty($seo['title']) ? $seo['title'] : $set->nama_aplikasi }}</title>

    @if(!empty($seo['description']))
        <meta name="description" content="{{ $seo['description'] }}">
    @else
        <meta name="description" content="Percetakan {{ $set->nama_aplikasi }}: layanan, cara pesan, kabar, dan kontak.">
    @endif

    {{-- Open Graph --}}
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $lpCanonical }}">
    <meta property="og:title" content="{{ !empty($seo['og_title']) ? $seo['og_title'] : $set->nama_aplikasi }}">
    <meta property="og:description" content="{{ !empty($seo['og_description']) ? $seo['og_description'] : 'Percetakan ' . $set->nama_aplikasi }}">
    @if(!empty($seo['og_image']))
        <meta property="og:image" content="{{ $seo['og_image'] }}">
    @endif

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ !empty($seo['og_title']) ? $seo['og_title'] : $set->nama_aplikasi }}">
    <meta name="twitter:description" content="{{ !empty($seo['og_description']) ? $seo['og_description'] : 'Percetakan ' . $set->nama_aplikasi }}">

    {{-- Indexing: boleh diindeks karena ini konten publik yang sah. --}}
    <meta name="robots" content="index, follow">

    @include('pengaturan.partials.renderer-styles')

    <style>
        :root {
            /* Palet resmi ORION — ROYAL INDIGO */
            --kertas: #F7F7FD;   /* permukaan netral (violet tint) */
            --tinta: #15143F;    /* DEEP NIGHT */
            --aksen: #4D55F8;    /* ELECTRIC VIOLET */
            --keluar: #E8E9FE;   /* tint violet muda */
            --kontras-aksen: #FFFFFF;  /* teks di atas aksen — 5.29:1 AA */
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--kertas);
            color: var(--tinta);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        h1, h2, h3, h4, h5, h6 { font-weight: 700; line-height: 1.2; margin-bottom: 16px; }
        h1 { font-size: 3rem; }
        h2 { font-size: 2.25rem; }
        h3 { font-size: 1.5rem; }
        p { margin-bottom: 16px; opacity: 0.85; }
        .btn {
            display: inline-block;
            padding: 14px 32px;
            background: var(--aksen);
            color: var(--kontras-aksen);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(77, 85, 248, 0.3); }
        .btn:focus-visible { outline: 3px solid var(--aksen); outline-offset: 3px; }

        /* ---- Pintu masuk staf (Checkpoint 4E.1) ----
           Sekarang hanya satu: di dalam Header Builder.
           Form dan kredensial TIDAK pernah dirender di halaman publik. --*/

        /* Pintu masuk sistem di dalam baris Header. Sengaja dibuat paling
           redup: Header Builder milik operator (CTA "Hubungi Kami" dan
           "Masuk Staf") tetap menjadi elemen utama. */
        .lp-header-karyawan {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-left: 14px;
            padding-left: 14px;
            border-left: 1px solid rgba(21, 20, 63, 0.16);
            color: #5A5E9E;
            text-decoration: none;
            font-size: 0.8125rem;
            font-weight: 500;
            white-space: nowrap;
            transition: color 150ms ease;
        }
        .lp-header-karyawan:hover { color: var(--tinta); }
        .lp-header-karyawan:focus-visible { outline: 3px solid var(--aksen); outline-offset: 3px; }
        @media (max-width: 900px) {
            /* Di mobile navbar disembunyikan; pintu masuk tetap harus terjangkau. */
            .lp-header-karyawan { margin-left: 8px; padding-left: 8px; }
        }
        .lp-kaki {
            border-top: 1px solid rgba(21, 20, 63, 0.12);
            padding: 28px 24px;
        }
        .lp-kaki-dalam {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            font-size: 0.875rem;
            color: #5A5E9E;
        }
        @media (max-width: 768px) {
            .lp-kaki-dalam { padding: 0 16px; }
        }
        .hero-title { font-size: 3.5rem; margin-bottom: 24px; }
        .hero-subtitle { font-size: 1.25rem; opacity: 0.7; margin-bottom: 32px; }
        a:focus-visible { outline: 3px solid var(--aksen); outline-offset: 3px; }
        @media (max-width: 768px) {
            h1 { font-size: 2rem; }
            h2 { font-size: 1.5rem; }
            .hero-title { font-size: 2.5rem; }
        }
    </style>
</head>
<body>
    @php
        // Helper: URL aman untuk link eksternal/internal
        $lpSafeUrl = function ($url) {
            $url = trim((string) $url);
            if ($url === '') return null;
            if ($url[0] === '#') {
                return preg_match('/^#[A-Za-z0-9_-]+$/', $url) ? $url : null;
            }
            if (preg_match('/^(javascript|data|vbscript|file|about):/i', $url)) {
                return null;
            }
            if (str_starts_with($url, '/')) {
                if (str_starts_with($url, '//')) return null;
                if (str_contains($url, '..') || str_contains($url, '\\')) return null;
                // Path internal -> URL APLIKASI. Tanpa ini "/masuk" resolve ke
                // http://localhost/masuk (root server XAMPP), bukan aplikasi di
                // /grafikaorion/public.
                return url($url);
            }
            $scheme = parse_url($url, PHP_URL_SCHEME);
            if (in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
                return $url;
            }
            return null;
        };
        
        // Helper: gambar hanya boleh dari storage landing-media
        $lpSafeImage = function ($image) {
            $image = trim((string) $image);
            if ($image === '') return null;
            if (str_starts_with($image, 'landing-media/')) {
                return asset('storage/' . $image);
            }
            if (str_starts_with($image, 'https://') || str_starts_with($image, 'http://')) {
                return $lpSafeUrl($image);
            }
            return null;
        };

        // Helper: apakah URL eksternal (http/https) agar dibuka di tab baru
        $lpIsExternal = function ($url) {
            return $url && preg_match('/^https?:\/\//i', $url);
        };

        // Pisahkan header dan footer dari sections untuk rendering di level page
        // agar sticky header berfungsi dengan benar (di luar wrapper section)
        $lpHeaderSection = null;
        $lpFooterSection = null;
        $lpContentSections = [];
        foreach ($sections as $lpSec) {
            if (($lpSec['type'] ?? '') === 'header') {
                $lpHeaderSection = $lpSec;
            } elseif (($lpSec['type'] ?? '') === 'footer') {
                $lpFooterSection = $lpSec;
            } else {
                $lpContentSections[] = $lpSec;
            }
        }

        // Item navigasi milik operator (dari section 'header'), dibaca di sini
        // supaya dipakai baik oleh header maupun footer. Dihitung di luar blok
        // @if($lpHeaderSection) supaya tetap ada walau section header absen.
        $lpNavItems = [];
        if ($lpHeaderSection && is_array($lpHeaderSection['items'] ?? null)) {
            foreach ($lpHeaderSection['items'] as $lpItem) {
                if (! is_array($lpItem)) continue;
                $lpLabel = trim((string) ($lpItem['label'] ?? ''));
                $lpHref  = $lpSafeUrl($lpItem['value'] ?? '');
                if ($lpLabel !== '' && $lpHref !== null) {
                    $lpNavItems[] = ['label' => $lpLabel, 'href' => $lpHref];
                }
            }
        }
    @endphp

    {{-- TOP BAR ALAMAT DIHAPUS (keputusan operator).
         Sebelumnya ada strip `.lp-kepala` berisi nama aplikasi + alamat di atas
         Header Builder, dan itu mengganggu tampilan premium Header.
         Data alamat TIDAK dihapus: masih tampil di section Kontak dan di
         Footer, keduanya bersumber dari section `contact` milik operator.
         Tidak ada ruang kosong yang tertinggal karena strip ini dihilang
         seluruhnya (bukan disembunyikan lewat CSS). --}}

    {{-- Header Builder (sticky, di luar main agar sticky berfungsi) --}}
    @if($lpHeaderSection)
        @php
            $lpSection = $lpHeaderSection;
            // $lpNavItems sudah dihitung di blok @php paling atas (dipakai header & footer).
            $lpSticky = (($lpHeaderSection['sticky'] ?? false) === true) ? ' lp-header-sticky' : '';
            $lpLogo   = $lpSafeImage($lpHeaderSection['image'] ?? '');
            $lpBrand  = $lpHeaderSection['title'] ?? 'ORION GRAFIKA';
            $lpBrandSub = trim((string) ($lpHeaderSection['subtitle'] ?? ''));
            $lpCta    = trim((string) ($lpHeaderSection['headerCta'] ?? ''));
            $lpBtnText = trim((string) ($lpHeaderSection['buttonLabel'] ?? ''));
            $lpBtnUrl  = $lpSafeUrl($lpHeaderSection['buttonUrl'] ?? '');
            $lpStaffText = trim((string) ($lpHeaderSection['buttonLabel'] ?? ''));
            $lpStaffUrl  = $lpSafeUrl($lpHeaderSection['buttonUrl'] ?? '');
        @endphp
        {{-- Header diekstrak dari <section> wrapper supaya sticky punya konteks
                 dokumen penuh. Karena itu anchor id section yang hilang ikut
                 dipindah ke sini - tanpa ini, item nav "Beranda"
                 (href="#beranda") tidak punya tujuan dan mati. --}}
        <header id="beranda" class="lp-header{{ $lpSticky }}">
            <div class="lp-header-inner">
                {{-- Merek menuju root APLIKASI, bukan root SERVER.
                 href="/" akan resolving ke http://localhost/ (root server
                 XAMPP) begitu halaman ini dirender di dalam <iframe> preview
                 pada URL yang lebih dalam. url('/') selalu menghasilkan root
                 aplikasi yang benar, baik di root domain maupun subdirektori. --}}
                <a class="lp-header-brand" href="{{ url('/') }}">
                    @if($lpLogo)
                        <img src="{{ $lpLogo }}" alt="{{ $lpBrand }}" loading="lazy" class="lp-header-logo">
                    @else
                        <span class="lp-header-mark" aria-hidden="true">OG</span>
                    @endif
                    <span class="lp-header-name">
                        {{ $lpBrand }}
                        @if($lpBrandSub !== '' && count($lpNavItems) === 0)
                            <small>{{ $lpBrandSub }}</small>
                        @endif
                    </span>
                </a>
                {{-- Hamburger button (mobile only, hidden on desktop via CSS) --}}
                <button type="button" class="lp-header-hamburger" aria-label="Buka menu" aria-expanded="false" aria-controls="lp-header-nav">
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                </button>
                @if(! empty($lpNavItems))
                    <nav id="lp-header-nav" class="lp-header-nav" aria-label="Navigasi utama">
                        @foreach($lpNavItems as $lpNav)
                            <a href="{{ $lpNav['href'] }}" class="lp-header-link" {!! $lpIsExternal($lpNav['href']) ? 'target="_blank" rel="noopener noreferrer"' : '' !!}>{{ $lpNav['label'] }}</a>
                        @endforeach
                        {{-- P0 PHASE 3: tombol Masuk Staf ikut masuk ke dropdown
                             mobile/tablet agar tetap terjangkau. Memakai data
                             yang sama dari Header Builder (bukan hardcode)
                             dan HANYA dirender bila URL-nya valid. --}}
                        @if($lpBtnText !== '' && $lpBtnUrl)
                            <a href="{{ $lpBtnUrl }}" class="lp-header-link lp-header-link--staff" {!! $lpIsExternal($lpBtnUrl) ? 'target="_blank" rel="noopener noreferrer"' : '' !!}>{{ $lpBtnText }}</a>
                        @endif
                    </nav>
                @endif
                <div class="lp-header-actions">
                    @if($lpCta !== '' && $lpBtnUrl)
                        <a href="{{ $lpBtnUrl }}" class="lp-header-cta" {!! $lpIsExternal($lpBtnUrl) ? 'target="_blank" rel="noopener noreferrer"' : '' !!}>{{ $lpCta }}</a>
                    @endif
                    @if($lpStaffText !== '' && $lpStaffUrl)
                        <a href="{{ $lpStaffUrl }}" class="lp-header-staff" {!! $lpIsExternal($lpStaffUrl) ? 'target="_blank" rel="noopener noreferrer"' : '' !!}>{{ $lpStaffText }}</a>
                    @endif
                    {{-- Pintu masuk staf tingkat sistem.
                         Dipindah ke dalam Header setelah top bar alamat dihapus,
                         supaya Header tetap elemen navigasi paling atas tanpa
                         kehilangan pintu masuk karyawan. Tetap TAUTAN SAJA
                         ke route('login') (/masuk) - tidak ada form/kredensial
                         di halaman publik. --}}
                    <a class="lp-header-karyawan" href="{{ route('login') }}" rel="nofollow">Masuk Karyawan</a>
                </div>
            </div>
        </header>
    @endif

    {{-- Fallback: Landing Page tanpa section 'header' (hanya terjadi bila
         Published memang tidak punya header). Header tetap dirender supaya
         pintu masuk staf tidak ikut hilang - Builder belum pernah
         menyimpan landing page tanpa header, jadi tidak muncul di produksi.
         Yang penting: tidak ada top bar dan tidak ada ruang kosong. --}}
    @unless($lpHeaderSection)
        <header id="beranda" class="lp-header">
            <div class="lp-header-inner">
                <div class="lp-header-actions" style="margin-left:auto">
                    <a class="lp-header-karyawan" href="{{ route('login') }}" rel="nofollow">Masuk Karyawan</a>
                </div>
            </div>
        </header>
    @endunless

    {{-- MAIN CONTENT --}}
    <main>
        @include('pengaturan.partials.section-render', ['sections' => $lpContentSections])
    </main>

    {{-- Footer: SATU-SATUNYA sumber markup (sama dengan Preview Builder dan
         halaman artikel). Fallback saat tidak ada section 'footer' ditangani
         di dalam partial, jadi blok @else yang dulu menduplikasi markup ini
         tidak lagi diperlukan. --}}
    @include('pengaturan.partials.footer-render', [
        'lpFooterSection'  => $lpFooterSection,
        'lpFooterNavItems' => $lpNavItems,
        'lpFooterSections' => $sections,
        'lpFooterSet'      => $set,
    ])

    {{-- Pintu masuk staf.

         HANYA TAUTAN ke route('login') (/masuk).

         Versi sebelumnya memuat <dialog> berisi <form> username/password
         plus token CSRF langsung di halaman publik. Itu melanggar kontrak:
         halaman publik tidak boleh pernah memuat permukaan kredensial
         (LandingPageLoginAccessTest::skenario_16b dan
          LandingPageLoginRouteAuditTest::builder_homepage_tidak_pernah_membocorkan_form_kredensial).
         Form login milik endpoint /masuk; halaman ini cukup mengarahkan
         staf ke sana. --}}

    {{-- Scripts Builder (Hamburger, Dropdown, Smooth Scroll, Progress) --}}
    <script>
    (function () {
        'use strict';

        // --- Hamburger & Dropdown Mobile ---
        var btn = document.querySelector('.lp-header-hamburger');
        var nav = document.getElementById('lp-header-nav');
        function isOpen() { return btn && btn.getAttribute('aria-expanded') === 'true'; }
        function setOpen(open) {
            if (!btn || !nav) return;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
            nav.classList.toggle('is-open', open);
        }
        if (btn && nav) {
            btn.addEventListener('click', function (e) { e.preventDefault(); setOpen(!isOpen()); });
            btn.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') { e.preventDefault(); setOpen(!isOpen()); } });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && isOpen()) { setOpen(false); btn.focus(); } });
            document.addEventListener('click', function (e) { if (!isOpen()) return; var t = e.target; if (t && (btn.contains(t) || nav.contains(t))) return; setOpen(false); }, false);
            nav.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', function () { setOpen(false); }); });
            nav.addEventListener('keydown', function (e) { if (e.key === 'Escape') { setOpen(false); btn.focus(); } });
        }

        // Anchor/smooth scroll ditangani partial bersama
        // (pengaturan.partials.smooth-scroll) supaya offset mengikuti tinggi
        // header sticky yang sebenarnya, bukan angka tetap.

        // Catatan: tidak ada lagi blok "Login Dialog" di sini.
        // Halaman publik hanya punya tautan ke route('login'); form login
        // hidup di endpoint /masuk (lihat resources/views/login.blade.php).
    })();
    </script>

    @include('pengaturan.partials.smooth-scroll')

    {{--
        Scroll reveal. Dipisah dari smooth scroll karena pemicunya berbeda:
        smooth scroll menangani klik anchor, reveal menangani elemen yang
        masuk viewport. Keduanya idempoten dan tidak saling menimpa.
    --}}
    @include('pengaturan.partials.scroll-reveal')

    @stack('skrip')
</body>
</html>