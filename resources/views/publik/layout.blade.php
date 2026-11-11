@php
    $appSetting = \App\Models\AppSetting::current();
    $urlLogo = $appSetting->logo_path ? asset('storage/' . $appSetting->logo_path) : null;
    // Gate B (LANDING_PAGE_BUILDER_ENABLED) status:
    // - OFF: menampilkan tautan login saja, tanpa form/konten ERP
    // - ON: menampilkan halaman landing page Builder (dilayani landing-page-public.blade.php)
    $lpEnabled = config('landing_page_builder.enabled', false);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>@yield('judul', $appSetting->nama_aplikasi)</title>
    @hasSection('deskripsi')<meta name="description" content="@yield('deskripsi')">@endif

    {{-- Vite: CSS/JS utama. Builder public menggunakan vendor-own fonts & icons. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Font & Ikon: dilayani dari vendor server LAN. --}}
    <link rel="stylesheet" href="{{ asset('vendor/fonts/inter.css') }}">
    @stack('font')
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/all.min.css') }}">

    {{-- Gaya Brand ORION (Royal Indigo) — konsisten dengan Landing Page Builder --}}
    <style>
        :root {
            --kertas: #faf9f7;
            --putih: #ffffff;
            --garis: #e9e5df;
            --garis-kuat: #d6d0c8;
            --tinta: #1b1a18;
            --tinta-2: #55514b;
            --tinta-3: #6e6962;
            --deep-night: #15143F;       /* DEEP NIGHT */
            --royal-indigo: #2E318E;     /* ROYAL INDIGO */
            --electric-violet: #4D55F8;  /* ELECTRIC VIOLET */
            --cta-orange: #FF6B17;       /* CTA ORANGE */
            --cyan-light: #B8EEFF;       /* CYAN LIGHT */
            --sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
            --keluar: cubic-bezier(0.23, 1, 0.32, 1);
        }

        /* ============ BASE RESET & TYPOGRAPHY ============ */
        * { box-sizing: border-backface; }
        body {
            margin: 0;
            background: var(--kertas);
            color: var(--tinta);
            font-family: var(--sans);
            font-size: 16px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; text-decoration: none; }
        img { display: block; max-width: 100%; }
        .bl-lebar { max-width: 1120px; margin: 0 auto; padding-left: max(1.25rem, env(safe-area-inset-left)); padding-right: max(1.25rem, env(safe-area-inset-right)); }

        /* Focus ring */
        .bl:focus-visible { outline: 2px solid var(--royal-indigo); outline-offset: 3px; border-radius: 4px; }

        /* ============ STICKY HEADER ============ */
        .bl-kepala {
            position: sticky;
            top: 0;
            z-index: 100;
            background: var(--kertas);
            border-bottom: 1px solid var(--garis-kuat);
            /* Background transparan saat hero section night mode aktif di atasnya */
            @media (prefers-reduced-motion: reduce) {
                transition: background-color 0s !important;
            }
        }
        .bl-kepala .bl-lebar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 64px;
            gap: 1rem;
        }
        .bl-merek {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
            letter-spacing: -0.01em;
        }
        .bl-merek img { height: 32px; width: auto; }
        .bl-kepala nav {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            font-size: 0.9375rem;
        }
        .bl-taut { color: var(--tinta-2); transition: color 150ms ease; }
        @media (max-width: 900px) { .bl-nav-lebar { display: none; } }

        /* Tombol */
        .bl-tombol {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            height: 40px;
            padding: 0 1.125rem;
            background: var(--royal-indigo);
            color: #fff !important;
            border: 0;
            font: 500 0.9375rem var(--sans);
            cursor: pointer;
            transition: transform 140ms var(--keluar), background-color 150ms ease;
        }
        .bl-tombol:active { transform: scale(0.97); }
        .bl-tombol-garis { background: transparent; color: var(--tinta) !important; border: 1px solid var(--garis-kuat); }
        @media (hover: hover) {
            .bl-taut:hover { color: var(--tinta); }
            .bl-tombol:hover { background: var(--royal-indigo); }
            .bl-tombol-garis:hover { background: putih; border-color: var(--tinta); }
        }

        /* Tombol CTA oranye */
        .bl-tombol-cta-oranye {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            height: 40px;
            padding: 0 1.125rem;
            background: var(--cta-orange);
            color: var(--deep-night) !important;
            border: 0;
            font: 500 0.9375rem var(--sans);
            cursor: pointer;
            transition: transform 140ms var(--keluar), background-color 150ms ease;
        }
        .bl-tombol-cta-oranye:active { transform: scale(0.97); }
        .bl-tombol-cta-oranye:hover { background: #E85C05; }

        /* ============ KAKI ============ */
        .bl-kaki {
            margin-top: 1rem;
            border-top: 1px solid var(--garis);
            padding: 2rem 0 max(2rem, env(safe-area-inset-bottom));
            font-size: 0.875rem;
            color: var(--tinta-3);
        }
        .bl-kaki .bl-lebar { display: flex; flex-wrap: wrap; gap: 0.5rem 2rem; justify-content: space-between; }
        .bl-kaki b { color: var(--tinta); font-weight: 600; }

        /* ============ DIALOG MASUK (modal form login) ============ */
        .bl-dialog { position: fixed; inset: 0; margin: auto; height: fit-content; width: min(400px, calc(100vw - 2rem)); padding: 0; border: 1px solid var(--garis); border-radius: 10px; background: var(--putih); color: var(--tinta); }
        .bl-dialog::backdrop { background: rgba(27, 26, 24, 0.45); }
        .bl-dialog[open] { animation: bl-masuk 220ms var(--keluar); }
        .bl-dialog[open]::backdrop { animation: bl-pudar 220ms ease-out; }
        @keyframes bl-masuk { from { opacity: 0; transform: scale(0.96) translateY(6px); } }
        @keyframes bl-pudar { from { opacity: 0; } }
        .bl-dialog-isi { padding: 1.75rem; }
        .bl-dialog h2 { font-size: 1.375rem; font-weight: 650; margin: 0 0 0.25rem; letter-spacing: -0.02em; }
        .bl-dialog p.ket { margin: 0 0 1.25rem; font-size: 0.875rem; color: var(--tinta-3); }
        .login-label { display: block; font-size: 0.8125rem; font-weight: 500; color: var(--tinta-2); margin-bottom: 0.375rem; }
        .login-input { width: 100%; padding: 0 0.75rem; background: #fff; border: 1px solid var(--garis-kuat); font-size: 0.9375rem; font-family: inherit; color: var(--tinta); outline: none; transition: border-color 140ms ease, box-shadow 140ms ease; }
        .login-input:focus { border-color: var(--royal-indigo); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15) !important; }
        .login-galat { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; border-radius: 6px; padding: 0.625rem 0.75rem; font-size: 0.8125rem; display: flex; gap: 0.5rem; align-items: flex-start; }
        .bl-tutup { position: absolute; top: 0.75rem; right: 0.75rem; width: 32px; height: 32px; border: 0; background: transparent; color: var(--tinta-3); cursor: pointer; }

        /* ============ REDUCED MOTION ============ */
        @media (prefers-reduced-motion: reduce) {
            .bl *, .bl *::before, .bl *::after, .bl-dialog, .bl-dialog::backdrop { animation: none !important; transition: none !important; }
        }

        @yield('gaya')
    </style>
</head>
<body>
    {{-- Gate B OFF: Beranda hanya menampilkan pintu masuk, TIDAK memuat form/kredensial.
         Gate B ON: halaman dirender oleh landing-page-public, layout ini
         hanya jadi penampung. --}}
    @if (! $lpEnabled)
        <script>document.body.classList.add('tanpa-js');</script>
    @endif

    {{-- HEADER UTAMA (sticky) --}}
    <header class="bl-kepala">
        <div class="bl-lebar">
            {{-- Merek/Logo --}}
            <a class="bl-merek" href="{{ url('/') }}">
                @if($urlLogo)<img src="{{ $urlLogo }}" alt="">@endif
                <span>{{ $appSetting->nama_aplikasi }}</span>
            </a>

            {{-- Navigasi Desktop --}}
            <nav aria-label="Utama">
                <a class="bl-taut" href="{{ url('/') }}#layanan">Layanan</a>
                <a class="bl-taut" href="{{ url('/') }}#tentang">Tentang</a>
                <a class="bl-taut" href="{{ url('/') }}#produk">Produk</a>
                <a class="bl-taut" href="{{ url('/') }}#portofolio">Portofolio</a>
                <a class="bl-taut" href="{{ url('/') }}#kontak">Kontak</a>
                <a class="bl-taut" href="{{ url('/') }}#faq">FAQ</a>

                {{-- Tombol login (hanya tautan, tanpa form) --}}
                <button type="button" class="bl-tombol" data-buka-masuk>Masuk Karyawan</button>
            </nav>
        </div>
    </header>

    {{-- MAIN CONTENT --}}
    <main>
        @yield('isi')
    </main>

    {{-- KAKI --}}
    <footer class="bl-kaki">
        <div class="bl-lebar">
            <span><b>{{ $appSetting->nama_aplikasi }}</b></span>
            @if($appSetting->alamat)<span>{{ $appSetting->alamat }}</span>@endif
            @if($appSetting->telepon)<span>{{ $appSetting->telepon }}</span>@endif
        </div>
    </footer>

    {{-- DIALOG MASUK (hanya muncul bila diklik, JANGKAH form login) --}}
    <dialog class="bl-dialog" id="dialog-masuk" aria-labelledby="judul-masuk">
        <div class="bl-dialog-isi">
            <button type="button" class="bl-tutup" data-tutup-masuk aria-label="Tutup"><i class="fas fa-xmark"></i></button>
            <h2 id="judul-masuk">Masuk ke sistem</h2>
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
                <button type="submit" class="bl-tombol" style="width:100%">Masuk</button>
            </form>
        </div>
    </dialog>

    {{-- SKRIP UTAMA (hanya jika Gate B OFF) --}}
    @if (! $lpEnabled)
    <script>
        (function () {
            var dialog = document.getElementById('dialog-masuk');
            function buka() { if (!dialog.open) { dialog.showModal(); document.getElementById('login-username').focus(); } }
            document.querySelectorAll('[data-buka-masuk]').forEach(function (t) { t.addEventListener('click', buka); });
            dialog.querySelector('[data-tutup-masuk]').addEventListener('click', function () { dialog.close(); });
            // Klik di luar kotak menutup dialog.
            dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
            @if(isset($bukaMasuk) && $bukaMasuk) buka(); @endif

            var input = document.getElementById('login-password'), tombol = document.getElementById('login-toggle');
            tombol.addEventListener('click', function () {
                var tampil = input.type === 'password';
                input.type = tampil ? 'text' : 'password';
                tombol.setAttribute('aria-pressed', String(tampil));
                tombol.setAttribute('aria-label', tampil ? 'Sembunyikan password' : 'Tampilkan password');
                tombol.innerHTML = '<i class="fas ' + (tampil ? 'fa-eye-slash' : 'fa-eye') + ' text-sm"></i>';
                input.focus();
            });
        })();
    </script>
    @endif

    {{-- SKRIP SECTION-REVEAL & SMOOTH SCROLL (dijalankan selama Gate B OFF; saat ON, builder otonom) --}}
    @if (! $lpEnabled)
    <script>
        (function () {
            'use strict';

            /* -- Fade-up untuk section (IntersectionObserver) -- */
            var revealTriggers = document.querySelectorAll('.section, .lp-hero, .lp-product, .lp-portfolio-card, .lp-posts-card, .lp-about-visual, .lp-cta > div, .lp-contact-text');
            var revealObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        revealObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1, rootMargin: '0px 0px -60px 0px' });

            revealTriggers.forEach(function (el) { revealObserver.observe(el); });

            /* Smooth scroll untuk anchor header ditangani partial bersama
               (pengaturan.partials.smooth-scroll): offset mengikuti tinggi
               header sticky sebenarnya, idempoten, dan menghormati
               prefers-reduced-motion. */
        })();
    </script>
    @endif

    @include('pengaturan.partials.smooth-scroll')

    {{-- STYLING ANIMASI MASUK (opacity/transform) --}}
    <style>
        /* Section reveal animation */
        .section, .lp-hero, .lp-product, .lp-portfolio-card, .lp-posts-card, .lp-about-visual, .lp-cta > div, .lp-contact-text {
            opacity: 0;
            transform: translateX(48px);
            transition: opacity 650ms cubic-bezier(0.22, 1, 0.36, 1), transform 650ms cubic-bezier(0.22, 1, 0.36, 1);
            will-change: transform, opacity;
        }
        .section.is-visible, .lp-hero.is-visible, .lp-product.is-visible, .lp-portfolio-card.is-visible, .lp-posts-card.is-visible, .lp-about-visual.is-visible, .lp-cta > div.is-visible, .lp-contact-text.is-visible {
            opacity: 1;
            transform: translateX(0);
        }
        @media (prefers-reduced-motion: reduce) {
            .section, .lp-hero, .lp-product, .lp-portfolio-card, .lp-posts-card, .lp-about-visual, .lp-cta > div, .lp-contact-text {
                transition: none;
                opacity: 1;
                transform: none;
            }
            .section.is-visible, .lp-hero.is-visible, .lp-product.is-visible, .lp-portfolio-card.is-visible, .lp-posts-card.is-visible, .lp-about-visual.is-visible, .lp-cta > div.is-visible, .lp-contact-text.is-visible {
                /* tetap terlihat meski animasi tidak jalan */
            }
        }

        /* Hover lift minimal pada card */
        .lp-product:hover, .lp-portfolio-card:hover, .lp-posts-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(77, 85, 248, 0.15); transition: transform 150ms ease, box-shadow 150ms ease; }
        @media (prefers-reduced-motion: reduce) {
            .lp-product:hover, .lp-portfolio-card:hover, .lp-posts-card:hover { transform: none; box-shadow: none; transition: none; }
        }
    </style>
    @include('partials.asisten-publik')
</body>
</html>