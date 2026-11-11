{{--
    Layout artikel publik yang terintegrasi penuh dengan Landing Page Builder.
    Menggunakan komponen Header (lp-header), Footer (lp-footer), dan token CSS Builder.
    Hanya dirender saat Gate B OFF (fallback) atau oleh Preview Builder.
--}}
@php
    $appSetting = \App\Models\AppSetting::current();
    $urlLogo = $appSetting->logo_path ? asset('storage/' . $appSetting->logo_path) : null;
    
    // Selalu ambil published revision untuk Header/Hero/Footer artikel
    // (tidak bergantung pada Gate B, karena artikel harus konsisten dengan desain publik)
    $lpSections = [];
    try {
        $page = \App\Models\LandingPage::where('slug', 'home')->first();
        if ($page && $page->status === 'published' && $page->published_revision_id) {
            $rev = \App\Models\LandingPageRevision::find($page->published_revision_id);
            if ($rev && in_array((string)$rev->schema_version, ['1.0.0'], true)) {
                $content = $rev->content_json;
                $lpSections = isset($content['sections']) && is_array($content['sections']) 
                    ? array_values($content['sections']) 
                    : (is_array($content) ? array_values($content) : []);
            }
        }
    } catch (\Throwable $e) {
        $lpSections = [];
    }
    
    // Filter header, hero, & footer section dari published revision
    $lpHeaderSection = null;
    $lpHeroSection = null;
    $lpFooterSection = null;
    foreach ($lpSections as $lpSec) {
        if (($lpSec['type'] ?? '') === 'header') $lpHeaderSection = $lpSec;
        if (($lpSec['type'] ?? '') === 'hero') $lpHeroSection = $lpSec;
        if (($lpSec['type'] ?? '') === 'footer') $lpFooterSection = $lpSec;
    }
    
    // Fallback data jika tidak ada published revision
    $lpBrand = $lpHeaderSection['title'] ?? $appSetting->nama_aplikasi;
    $lpBrandSub = $lpHeaderSection['subtitle'] ?? '';
    $lpLogo = ($lpHeaderSection && isset($lpHeaderSection['image']) && $lpHeaderSection['image']) ? asset('storage/' . $lpHeaderSection['image']) : ($urlLogo ?? null);
    $lpNavItems = [];
    if ($lpHeaderSection && !empty($lpHeaderSection['items']) && is_array($lpHeaderSection['items'])) {
        foreach ($lpHeaderSection['items'] as $item) {
            if (!is_array($item)) continue;
            $label = trim((string)($item['label'] ?? ''));
            $href  = trim((string)($item['value'] ?? ''));
            if ($label !== '' && $href !== '') $lpNavItems[] = ['label' => $label, 'href' => $href];
        }
    }
    // Path internal -> URL APLIKASI.
    //
    // Config Builder menyimpan "/masuk". Jika dipakai mentah, browser
    // resolve ke http://localhost/masuk - ROOT SERVER XAMPP, bukan aplikasi
    // di /grafikaorion/public. Anchor (#...) dan URL absolut dibiarkan.
    $lpAppUrl = function ($url) {
        $url = trim((string) $url);
        if ($url === '') return '';
        if ($url[0] === '#') return $url;
        if (preg_match('#^https?://#i', $url)) return $url;
        if (! str_starts_with($url, '/')) return $url;
        return url($url);
    };

    // BASIS URL WEBSITE PUBLIK - SEMUA NAVIGASI ARTIKEL.
    //
    // Dipetakan ke route NAMED 'landing-page.public', BUKAN url('/')+'#anchor'.
    //
    // Mengapa tidak url('/'):
    //   Gate B ON  : '/' memang menampilkan Builder, tapi belum tentu sama
    //                untuk seluruh peran. Pada fallback (revisi belum valid)
    //                '/' bisa jadi halaman lain dan section Anchor hilang.
    //   Gate B OFF : '/' memakai homepage existing yang TIDAK punya section
    //                Builder (sudah diverifikasi: hanya #layanan & #kontak).
    //                Klik menu artikel berujung di halaman yang anchor-nya
    //                tidak ada.
    //
    // route('landing-page.public') = /landing-page selalu menampilkan
    // snapshot PUBLISHED, jadi tujuan valid untuk guest DAN karyawan,
    // untuk Gate B ON maupun OFF. Tidak hardcode 'localhost'.
    //
    // Parameter ini diturunkan ke footer-render.blade.php supaya header dan
    // footer memakai tujuan yang sama persis.
    $lpBerandaPublik = route('landing-page.public');

    // Alias fragment: nama section Builder untuk daftar tulisan.
    // (Builder memberi section 'posts' anchor 'artikel'; homepage fallback
    // lama memakai 'tulisan' - tetapi untuk navigasi dari artikel kita selalu
    // menuju route landing-page.public, jadi 'artikel' selalu benar.)
    $lpBerandaTulisan = $lpBerandaPublik . '#artikel';

    // CTA dari Hero section (utama), fallback ke Header section
    $lpHeroCtaText = $lpHeroSection ? trim((string)($lpHeroSection['buttonLabel'] ?? '')) : '';
    $lpHeroCtaUrl = $lpHeroSection ? $lpAppUrl($lpHeroSection['buttonUrl'] ?? '') : '';
    $lpHeaderCtaText = $lpHeaderSection ? trim((string)($lpHeaderSection['headerCta'] ?? '')) : '';
    $lpHeaderCtaUrl = $lpHeaderSection ? $lpAppUrl($lpHeaderSection['buttonUrl'] ?? '') : '';
    $lpStaffText = $lpHeaderSection ? trim((string)($lpHeaderSection['buttonLabel'] ?? '')) : '';
    $lpStaffUrl = $lpHeaderSection ? $lpAppUrl($lpHeaderSection['buttonUrl'] ?? '') : '';
    
    // Gunakan Hero CTA sebagai utama, fallback ke Header CTA
    $lpCtaText = $lpHeroCtaText ?: $lpHeaderCtaText;
    $lpCtaUrl = $lpHeroCtaUrl ?: $lpHeaderCtaUrl;
    
    // Footer data
    $lpFootBrand = $lpFooterSection['title'] ?? $appSetting->nama_aplikasi;
    $lpFootSub = $lpFooterSection['subtitle'] ?? '';
    $lpFootDesc = $lpFooterSection['desc'] ?? '';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>@yield('judul', $appSetting->nama_aplikasi)</title>
    @hasSection('deskripsi')<meta name="description" content="@yield('deskripsi')">@endif

    {{-- Font & Ikon Builder --}}
    <link rel="stylesheet" href="{{ asset('vendor/fonts/inter.css') }}">
    @stack('font')
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/all.min.css') }}">

    {{-- Builder Renderer Styles (termasuk Header, Footer, Button, Motion, Responsive) --}}
    @include('pengaturan.partials.renderer-styles')

    {{-- Article-specific styles --}}
    <style>
        /* Article layout */
        .lp-article { max-width: 760px; margin: 0 auto; padding: 48px 24px 80px; }
        .lp-article header { margin-bottom: 32px; }
        .lp-article .lp-back { display: inline-flex; align-items: center; gap: 8px; font-size: 14px; color: var(--orion-brand-muted); text-decoration: none; margin-bottom: 16px; }
        .lp-article .lp-back:hover { color: var(--orion-brand-navy); }
        .lp-article .lp-back i { font-size: 12px; transition: transform 180ms var(--orion-ease-out); }
        .lp-article .lp-back:hover i { transform: translateX(-4px); }
        .lp-article .lp-meta { display: flex; flex-wrap: wrap; gap: 8px 16px; margin: 16px 0 24px; font-size: 13px; color: var(--orion-brand-muted); }
        .lp-article .lp-meta b { color: var(--orion-brand-navy); font-weight: 600; }
        .lp-article h1 { font-weight: 800; font-size: clamp(2rem, 4.5vw, 3.25rem); line-height: 1.1; letter-spacing: -0.025em; margin: 0 0 16px; color: var(--orion-deep-night); text-wrap: balance; }
        .lp-article .lp-dek { margin: 0 0 24px; font-size: 1.125rem; line-height: 1.6; color: var(--orion-brand-muted); }
        .lp-article .lp-cover { max-width: 100%; margin: 32px 0; border-radius: 12px; overflow: hidden; background: var(--orion-brand-light); }
        .lp-article .lp-cover img { width: 100%; aspect-ratio: 16/9; object-fit: cover; display: block; }
        .lp-article .lp-body { max-width: 100%; }
        .lp-article .lp-body .prose-post { color: var(--orion-brand-navy); line-height: 1.8; font-size: 16px; }
        .lp-article .lp-body .prose-post h2 { font-weight: 800; font-size: 1.75rem; margin: 32px 0 12px; color: var(--orion-deep-night); }
        .lp-article .lp-body .prose-post h3 { font-weight: 700; font-size: 1.375rem; margin: 28px 0 10px; color: var(--orion-deep-night); }
        .lp-article .lp-body .prose-post p { margin: 16px 0; }
        .lp-article .lp-body .prose-post img { border-radius: 8px; height: auto; max-width: 100%; margin: 24px 0; }
        .lp-article .lp-body .prose-post ul, .lp-article .lp-body .prose-post ol { margin: 16px 0; padding-left: 24px; }
        .lp-article .lp-body .prose-post li { margin: 8px 0; }
        .lp-article .lp-body .prose-post blockquote { border-left: 4px solid var(--orion-electric-violet); padding: 12px 20px; margin: 24px 0; background: var(--orion-brand-light); border-radius: 0 8px 8px 0; color: var(--orion-deep-night); font-style: italic; }
        .lp-article .lp-tags { display: flex; flex-wrap: wrap; gap: 8px; margin: 32px 0; }
        .lp-article .lp-tag { display: inline-flex; align-items: center; padding: 6px 12px; background: var(--orion-brand-light); color: var(--orion-deep-night); font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; border-radius: 999px; border: 1px solid var(--orion-brand-border); }
        .lp-article .lp-share { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--orion-brand-border); }
        .lp-article .lp-share-btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: var(--orion-brand-light); color: var(--orion-deep-night); font-weight: 600; font-size: 13px; border-radius: 999px; border: 1px solid var(--orion-brand-border); text-decoration: none; transition: background-color .18s ease, border-color .18s ease; }
        .lp-article .lp-share-btn:hover { background: var(--orion-electric-violet); border-color: var(--orion-electric-violet); color: #FFFFFF; }
        .lp-article .lp-related { margin-top: 64px; padding-top: 32px; border-top: 1px solid var(--orion-brand-border); }
        .lp-article .lp-related h2 { font-weight: 800; font-size: 1.75rem; margin: 0 0 24px; color: var(--orion-deep-night); }
        .lp-article .lp-related-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .lp-article .lp-related-card { background: var(--orion-neutral-100); border: 1px solid var(--orion-brand-border); border-radius: 12px; overflow: hidden; transition: box-shadow .18s ease, transform .18s ease, border-color .18s ease; }
        .lp-article .lp-related-card:hover { box-shadow: 0 8px 24px var(--orion-shadow-soft); transform: translateY(-2px); border-color: var(--orion-electric-violet); }
        .lp-article .lp-related-media { aspect-ratio: 16/9; background: var(--orion-brand-light); overflow: hidden; }
        .lp-article .lp-related-media img { width: 100%; height: 100%; object-fit: cover; transition: transform .3s ease; }
        .lp-article .lp-related-card:hover .lp-related-media img { transform: scale(1.03); }
        .lp-article .lp-related-body { padding: 16px; }
        .lp-article .lp-related-kategori { display: inline-block; font-size: 10px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--orion-deep-night); background: var(--orion-brand-light); padding: 3px 8px; border-radius: 3px; border: 1px solid var(--orion-brand-border); margin-bottom: 8px; }
        .lp-article .lp-related-title { font-weight: 700; font-size: 15px; margin: 8px 0 4px; color: var(--orion-deep-night); line-height: 1.4; }
        .lp-article .lp-related-meta { font-size: 11px; color: var(--orion-brand-muted); }
        @media (max-width: 900px) { .lp-article { padding: 32px 16px 64px; } .lp-article .lp-related-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 640px) { .lp-article .lp-related-grid { grid-template-columns: 1fr; } }

        /* Scroll progress bar (masuk ke lp-header via JS) */
        .lp-progress { position: absolute; bottom: 0; left: 0; right: 0; height: 2px; background: var(--orion-electric-violet); transform: scaleX(0); transform-origin: left; z-index: 5; transition: transform 120ms linear; }

        /* Pastikan lp-header sticky di atas semua */
        .lp-header { position: sticky; top: 0; z-index: 50; }
        .lp-header-sticky { position: sticky; top: 0; z-index: 50; }
        
        /* Pastikan content tidak overlap header */
        main { position: relative; z-index: 1; }

        {{-- @yield('gaya') HARUS DI DALAM <style>.

             Kalau section 'gaya' dari halaman artikel (lihat
             publik/baca.blade.php) ditulis sebagai CSS mentah tanpa
             pembungkus <style>, HTML parser akan encountering karakter
             non-whitespace di dalam <head> lalu KELUAR dari mode "in head".
             Teks CSS tersebut kemudian dibuang ke dalam <body> sebagai
             TEKS, bukan gaya - hasilnya CSS mentah tampil sebagai tulisan
             di atas artikel. --}}
        @yield('gaya')
    </style>
</head>
<body class="lp-root">
    {{-- Header Builder. id="beranda" ikut dipindah ke sini dari wrapper
         <section> supaya item nav "Beranda" (href="#beranda") punya tujuan. --}}
    <header id="beranda" class="lp-header lp-header-sticky">
        <div class="lp-header-inner">
            {{-- Merek menuju WEBSITE PUBLIK (route bernama, bukan path mentah).

                 Gate B OFF -> route('landing-page.public') = /landing-page
                 Gate B ON  -> route('beranda')            = /

                 url('/') tidak dipakai lagi: dari /baca/{slug} path relatif
                 root akan resolve ke http://localhost/ (halaman "Welcome to
                 XAMPP"), dan saat Gate B OFF beranda '/' juga bukan halaman
                 yang memuat section Landing Page. --}}
            <a class="lp-header-brand" href="{{ $lpBerandaPublik }}">
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
            {{-- Hamburger button (mobile only) --}}
            <button type="button" class="lp-header-hamburger" aria-label="Buka menu" aria-expanded="false" aria-controls="lp-header-nav">
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
            </button>
            @if(!empty($lpNavItems))
                <nav id="lp-header-nav" class="lp-header-nav" aria-label="Navigasi utama">
                    @foreach($lpNavItems as $lpNav)
                        {{-- Anchor (#tentang, #faq, ...) milik WEBSITE PUBLIK, bukan
                             halaman artikel ini. Ditulis ulang menjadi
                             route bernama + anchor, jadi benar untuk Gate B
                             OFF (route landing-page.public) maupun Gate B ON
                             (route beranda). --}}
                        @php
                            $lpNavHref = $lpNav['href'] ?? '';
                            if (is_string($lpNavHref) && $lpNavHref !== '' && $lpNavHref[0] === '#') {
                                $lpNavHref = $lpBerandaPublik . $lpNavHref;
                            }
                            $lpNavExternal = is_string($lpNavHref) && preg_match('#^https?://#i', $lpNavHref);
                        @endphp
                        <a href="{{ $lpNavHref }}" class="lp-header-link" {!! $lpNavExternal ? 'target="_blank" rel="noopener noreferrer"' : '' !!}>{{ $lpNav['label'] }}</a>
                    @endforeach
                    @if($lpStaffText !== '' && $lpStaffUrl)
                        <a href="{{ $lpStaffUrl }}" class="lp-header-link lp-header-link--staff">{{ $lpStaffText }}</a>
                    @endif
                </nav>
            @endif
            <div class="lp-header-actions">
                @if($lpCtaText !== '' && $lpCtaUrl)
                    <a href="{{ $lpCtaUrl }}" class="lp-header-cta">{{ $lpCtaText }}</a>
                @endif
                @if($lpStaffText !== '' && $lpStaffUrl)
                    <a href="{{ $lpStaffUrl }}" class="lp-header-staff">{{ $lpStaffText }}</a>
                @endif
            </div>
        </div>
        {{-- Scroll progress bar --}}
        <div class="lp-progress" id="lp-progress" aria-hidden="true"></div>
    </header>

    {{-- Main Content --}}
    <main>
        @yield('isi')
    </main>

    {{-- Footer: partial yang SAMA dengan Preview Builder dan Landing Page publik,
         jadi tidak ada lagi footer berbeda di tiap halaman.
         Anchor seperti #tentang / #faq ditulis sebagai URL lengkap
         "{beranda}#tentang" oleh partial - di halaman artikel anchor lokal
         tidak punya tujuan karena section itu tidak ada di sini. --}}
    @include('pengaturan.partials.footer-render', [
        'lpFooterSection'  => $lpFooterSection,
        'lpFooterNavItems' => $lpNavItems,
        'lpFooterSections' => $lpSections,
        'lpFooterSet'      => $appSetting,
        'lpFooterAnchorBase' => $lpBerandaPublik,
    ])

    {{-- Dialog Masuk Staf (hanya tautan ke /masuk) --}}
    <dialog class="lp-dialog" id="lp-dialog-masuk" aria-labelledby="lp-judul-masuk">
        <div class="lp-dialog-isi">
            <button type="button" class="lp-tutup" data-tutup-masuk aria-label="Tutup"><i class="fas fa-xmark"></i></button>
            <h2 id="lp-judul-masuk">Masuk ke sistem</h2>
            <p class="ket">Untuk staf. Akun dibuat oleh Administrator.</p>
            <form action="{{ url('login') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="login-label" for="login-username">Username</label>
                    <input type="text" id="login-username" name="username" class="login-input" placeholder="Nama pengguna" required autocomplete="username" autocapitalize="none" spellcheck="false">
                </div>
                <div>
                    <label class="login-label" for="login-password">Password</label>
                    <div class="relative">
                        <input type="password" id="login-password" name="password" class="login-input pr-10" placeholder="Kata sandi" required autocomplete="current-password">
                        <button type="button" id="login-toggle" class="absolute right-1 top-1/2 -translate-y-1/2 w-8 h-8 flex items-center justify-center text-slate-500"
                            aria-label="Tampilkan password" aria-pressed="false"><i class="fas fa-eye text-sm"></i></button>
                    </div>
                </div>
                @if($errors->any())
                    <div class="login-galat" role="alert"><i class="fas fa-circle-exclamation mt-0.5 shrink-0"></i><span>{{ $errors->first() }}</span></div>
                @endif
                <button type="submit" class="btn" style="width:100%">Masuk</button>
            </form>
        </div>
    </dialog>

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
        // (pengaturan.partials.smooth-scroll): offset mengikuti tinggi
        // header sticky sebenarnya, bukan angka tetap.

        // --- Scroll progress bar ---
        var progress = document.getElementById('lp-progress');
        var header = document.querySelector('.lp-header');
        function updateProgress() {
            if (!progress) return;
            var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            var docHeight = document.documentElement.scrollHeight - window.innerHeight;
            var progressPercent = docHeight > 0 ? scrollTop / docHeight : 0;
            progress.style.transform = 'scaleX(' + Math.min(1, Math.max(0, progressPercent)) + ')';
        }
        window.addEventListener('scroll', updateProgress, { passive: true });
        window.addEventListener('resize', updateProgress);
        updateProgress();

        // --- Login Dialog ---
        var dialog = document.getElementById('lp-dialog-masuk');
        function bukaDialog() { if (dialog && !dialog.open) { dialog.showModal(); document.getElementById('login-username').focus(); } }
        document.querySelectorAll('[data-buka-masuk], .lp-header-link--staff, .lp-header-staff, .lp-footer a[href*="login"]').forEach(function (t) { t.addEventListener('click', function (e) { if (this.tagName === 'A' && this.href && this.href.includes('/masuk')) return; e.preventDefault(); bukaDialog(); }); });
        if (dialog) {
            dialog.querySelector('[data-tutup-masuk]').addEventListener('click', function () { dialog.close(); });
            dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
        }
        var input = document.getElementById('login-password'), tombol = document.getElementById('login-toggle');
        if (tombol && input) {
            tombol.addEventListener('click', function () {
                var tampil = input.type === 'password';
                input.type = tampil ? 'text' : 'password';
                tombol.setAttribute('aria-pressed', String(tampil));
                tombol.setAttribute('aria-label', tampil ? 'Sembunyikan password' : 'Tampilkan password');
                tombol.innerHTML = '<i class="fas ' + (tampil ? 'fa-eye-slash' : 'fa-eye') + ' text-sm"></i>';
                input.focus();
            });
        }
    })();
    </script>

    @include('pengaturan.partials.smooth-scroll')

    @stack('skrip')
    @include('partials.asisten-publik')
</body>
</html>