@php
    // Diambil sekali di sini (bukan per-blok) supaya title tab, brand
    // sidebar, dan topbar mobile semuanya konsisten dari satu sumber -
    // sebelumnya ketiganya hardcode terpisah dengan nilai yang berbeda-beda.
    $appSetting = \App\Models\AppSetting::current();
    $brandTheme = app(\App\Services\BrandThemeService::class)->current();
    $brandThemeStyle = collect($brandTheme['variables'])
        ->map(fn ($value, $name) => $name . ':' . $value)
        ->implode(';');
@endphp
<!DOCTYPE html>
<html lang="id" data-brand-theme="{{ $brandTheme['id'] }}" style="{{ $brandThemeStyle }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}"> {{-- Tambahkan Meta CSRF --}}
    <title>{{ $appSetting->nama_aplikasi }}</title>

    {{-- PWA: manifest agar aplikasi bisa dipasang di layar utama, ikon sentuh
         untuk iOS, dan theme-color supaya bilah browser mengikuti tema. Dua
         theme-color (gelap/terang) mencakup kedua mode. --}}
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <meta name="theme-color" content="{{ $brandTheme['surfaces']['dark_primary'] }}" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="{{ $brandTheme['surfaces']['page'] }}" media="(prefers-color-scheme: light)">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ $appSetting->nama_aplikasi }}">
    <script>
        // Pasang service worker (hanya di konteks aman: localhost/https).
        // Fungsinya ada di PwaController::serviceWorker(); halaman & API tetap
        // network-first supaya data keuangan tidak pernah dilayani dari cache.
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('{{ route('pwa.sw') }}').catch(function () {});
            });
        }
    </script>
    <script>
        // Tema terang/gelap: pilihan tersimpan (localStorage 'tema'), bila belum ada ikut pengaturan perangkat.
        (function () {
            try {
                var t = localStorage.getItem('tema');
                if (!t) t = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
                if (t === 'dark') document.documentElement.classList.add('dark');
                // Mode sidebar: "dark" (selalu gelap, default) atau "light" (seperti referensi Copilot).
                // Default light hanya untuk tema Copilot Light; tema lain default dark.
                var sm = localStorage.getItem('sidebarMode');
                if (!sm) {
                    var bt = document.documentElement.getAttribute('data-brand-theme');
                    sm = (bt === 'copilot-light') ? 'light' : 'dark';
                }
                if (sm === 'light') document.documentElement.setAttribute('data-sidebar-mode', 'light');
            } catch (e) {}
        })();
    </script>
    
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/palette.jsx'])

    {{-- Aset pihak ketiga dilayani sendiri, bukan dari CDN.
         Alasannya bukan sekadar kecepatan: setiap permintaan ke domain
         ketiga adalah permintaan render-blocking yang tidak bisa kita
         ukur, tidak bisa kita cache sendiri, dan tetap menggagalkan
         render meski server kita baik. Kalau domainnya lambat atau
         diblokir jaringan kantor, halaman kosong.

         Yang diganti:
           Inter         -> public/vendor/fonts (font variable, 2 file
                            latin + latin-ext, mencakup bobot 100-900)
           Select2       -> public/vendor/select2
           Font Awesome  -> public/vendor/fontawesome (woff2 saja)
           jQuery        -> public/vendor/jquery
           SweetAlert2   -> public/vendor/sweetalert2 (catatan terpisah
                            di bawah, soal urutannya) --}}
    <link rel="stylesheet" href="{{ asset('vendor/fonts/inter.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/select2/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/all.min.css') }}">

    {{-- SweetAlert2 WAJIB skrip klasik di <head>, bukan di-bundle ke app.js.

         Alasannya urutan waktu, bukan selera. Layout ini memanggil
         Swal.mixin() dan Swal.fire() di skrip inline pada badgean
         document body, untuk menampilkan flash message session. Skrip
         inline badgean berjalan SAAT dokumen di-parse.

         Sementara app.js adalah <script type="module">, yang sifatnya
         selalu deferred: baru dieksekusi setelah dokumen selesai
         di-parse. Jadi kalau Swal hanya datang dari app.js, setiap
         pemanggilan Swal di badgean akan melihat "Swal is not defined"
         - persis yang terjadi ketika tag CDN-nya dihapus.

         Script klasik di head memblokir parse sampai selesai dimuat,
         jadi window.Swal sudah ada sebelum badgean dieksekusi. Karena
         itu SweetAlert2 juga dikeluarkan dari resources/js/app.js:
         tanpa itu pustakanya diunduh dua kali, dan bundle JS jadi
         sekitar 50KB lebih besar tanpa alasan. --}}
    {{--
        CSS SweetAlert2. Selama ini HANYA JS yang dimuat - CSS-nya tidak
        pernah ditambahkan. Akibatnya setiap dialog konfirmasi render
        tanpa gaya sama sekali: container-nya position:static (bukan
        fixed), display:block (bukan flex), dan dialog-nya jatuh ke
        bawah halaman selebar layar. Yang terlihat cuma ikon tanda seru
        di pojok bawah.

        File-nya diunduh dari CDN dan disimpan di public/vendor supaya
        tidak ada permintaan eksternal saat runtime - konsisten dengan
        kebijakan self-host yang sudah berlaku untuk aset lain.
    --}}
    <link rel="stylesheet" href="{{ asset('vendor/sweetalert2/sweetalert2.min.css') }}">
    <script src="{{ asset('vendor/sweetalert2/sweetalert2.min.js') }}"></script>
    <script>
        // Default semua dialog dan notifikasi (lihat ui-refresh.css bagian 24): tombol tutup bulat di pojok,
        // urutan Batal lalu aksi utama di kanan, dan gaya tombol dari CSS aplikasi (bukan warna bawaan SweetAlert).
        window.Swal = Swal.mixin({ buttonsStyling: false, showCloseButton: true, reverseButtons: true, closeButtonHtml: '&times;', closeButtonAriaLabel: 'Tutup' });
    </script>
    
    <style>
    /* =====================================================
       VISUAL REFRESH V3 (2026-11) - Command Center shell
       Sumber kebenaran tunggal untuk shell. App-wide utilities
       (card, tabel, dll) tetap di app.css.
       ===================================================== */
    :root {
        --pro-bg: #E9EEFA;
        --pro-surface: #FFFFFF;
        --pro-border: #E3E9F6;
        --pro-border-2: #CBD6EE;
        --pro-ink: #0F1E3E;
        --pro-ink-2: #3E4C74;
        --pro-ink-3: #5D6A85;
        --pro-accent: #2563EB;
        --pro-accent-hover: #1D4ED8;
        --pro-accent-soft: #E3EBFF;
        --pro-accent-ring: rgba(37, 99, 235, .22);
        --pro-radius: 16px;
        --pro-radius-md: 12px;
        --pro-radius-sm: 6px;
        --pro-shadow-sm: 0 1px 2px rgba(15, 30, 62, .04);
        --pro-shadow-md: 0 4px 12px rgba(15, 30, 62, .06), 0 1px 2px rgba(15, 30, 62, .04);
        --pro-shadow-lg: 0 10px 28px rgba(15, 30, 62, .08), 0 2px 5px rgba(15, 30, 62, .05);
        --pro-shadow-xl: 0 18px 44px rgba(15, 30, 62, .10), 0 2px 7px rgba(15, 30, 62, .06);
        --bar1-h: 62px;
        --bar2-h: 48px;
    }
    html.dark { --pro-bg: #141A29; --pro-surface: #1C2436; --pro-border: #2A3450; --pro-border-2: #3A4767; --pro-ink: #E8ECF8; --pro-ink-2: #B9C3DD; --pro-ink-3: #8A96B8; --pro-accent: #5B8DEF; --pro-accent-hover: #7FA6F5; --pro-accent-soft: #22304F; --pro-accent-ring: rgba(91, 141, 239, .25); }

    html { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; text-rendering: optimizeLegibility; }
    body {
        font-family: var(--brand-font-family, 'Inter'), sans-serif;
        font-feature-settings: "cv11", "ss01";
        color: var(--pro-ink-2);
        overflow-x: hidden;
        background:
            radial-gradient(1200px 700px at 88% -12%, rgba(37, 99, 235, .055) 0%, transparent 55%),
            radial-gradient(1000px 640px at -12% 112%, rgba(37, 99, 235, .045) 0%, transparent 55%),
            var(--pro-bg);
    }
    h1, h2 { letter-spacing: -0.02em; }
    .tnum, .font-num { font-variant-numeric: tabular-nums; }

    #sidebarOverlay {
        display: none; position: fixed; inset: 0; z-index: 38;
        background: rgba(9, 15, 30, .32);
    }
    #sidebarOverlay.terbuka { display: block; }
    /* Desktop: overlay tidak dipakai mega menu (tanpa dim ala Canva).
       Kalau tetap tampil, ia menutupi tombol topbar (topbar z-30 < overlay
       z-38) sehingga kursor kehilangan hover dan panel berkedip. Tutup
       lewat klik-di-luar (lihat toggleMenuPanel). */
    @media (min-width: 1024px) {
        #sidebarOverlay, #sidebarOverlay.terbuka { display: none !important; }
    }

    /* ===== MENU PANEL (mega menu) ===== */
    @media (min-width: 1024px) {
        #mainSidebar {
            position: fixed; z-index: 40; background: var(--pro-surface);
            top: calc(var(--bar1-h) + var(--bar2-h) + 12px); left: 50%;
            transform: translate(-50%, 0);
            max-width: 1240px; width: calc(100% - 32px);
            max-height: 78vh; overflow-y: auto; border: 1px solid var(--pro-border-2);
            border-radius: var(--pro-radius); box-shadow: var(--pro-shadow-xl);
            padding: 16px 20px 14px; display: none; columns: 236px; column-gap: 14px;
        }
        #mainSidebar.terbuka { display: block; animation: proPanelIn .22s cubic-bezier(.22,1,.36,1) both; }
        #mainSidebar nav { display: contents; }
        /* Semua grup tampil; accordion dimatikan di desktop. */
        #mainSidebar .menu-group-items { display: block !important; break-inside: avoid; }
        #mainSidebar .menu-group-toggle {
            pointer-events: none; break-after: avoid; break-inside: avoid;
            text-align: left; display: flex; align-items: center; gap: 8px;
            padding: 11px 6px 7px; border: 0; border-bottom: 1px solid #EDF2F9;
            background: transparent; margin: 12px 0 4px;
            font-size: 12.5px; font-weight: 600; letter-spacing: 0; text-transform: none; color: var(--pro-ink);
        }
        #mainSidebar .menu-group-toggle:first-of-type { margin-top: 0; }
        #mainSidebar .menu-group-toggle > i.fa-chevron-down { display: none; }
        #mainSidebar .menu-group-toggle .notif-badge { margin-left: auto; }
        #mainSidebar .notif-grup, #mainSidebar .menu-panel-foot kbd { letter-spacing: 0; }
        #mainSidebar .notif-grup { display: inline-flex; align-items: center; justify-content: center; min-width: 20px; height: 18px; padding: 0 6px; border-radius: 999px; background: var(--pro-accent); color: #fff; font-size: 9px; font-weight: 700; margin-left: auto; }
        #mainSidebar a {
            display: flex; align-items: center; gap: 11px; padding: 8px 10px; border-radius: 10px;
            font-size: 13px; font-weight: 400; color: var(--pro-ink-2); text-decoration: none;
            break-inside: avoid; border-left: 0; transition: background .12s ease, color .12s ease;
        }
        #mainSidebar a svg { width: 18px; height: 18px; flex: 0 0 auto; opacity: .8; }
        #mainSidebar a:hover { background: #F4F8FF; color: var(--pro-accent-hover); }
        #mainSidebar a:hover svg { opacity: 1; color: var(--pro-accent-hover); }
        #mainSidebar a.sb-active, #mainSidebar a.group.sb-active { background: var(--pro-accent-soft); color: var(--pro-accent-hover); font-weight: 600; box-shadow: inset 0 0 0 1px #C9D9FB; }
        #mainSidebar a.sb-active svg { color: var(--pro-accent-hover); opacity: 1; }
        #sidebarBrandHeader { display: flex; align-items: center; gap: 12px; padding: 2px 6px 12px; border-bottom: 1px solid var(--pro-border); margin-bottom: 14px; column-span: all; }
        .sidebar-brandchip { display: flex; align-items: center; gap: 10px; text-decoration: none; min-width: 0; }
        .brandchip-logo { display: block; height: 30px; width: auto; max-width: 200px; max-height: 30px; object-fit: contain; }
        #sidebarBrandHeader .lg\:hidden { display: flex; }
        .sidebar-kas-status { display: flex; align-items: center; gap: 12px; border: 1px solid var(--pro-border); border-radius: var(--pro-radius-md); padding: 10px 12px; margin: 0 0 14px; background: var(--pro-bg); column-span: all; }
        .sidebar-kas-status * { color: var(--pro-ink-2) !important; }
        .menu-panel-foot { display: flex; justify-content: space-between; align-items: center; gap: 10px; border-top: 1px solid var(--pro-border); margin-top: 12px; padding: 10px 6px 0; font-size: 9.5px; color: var(--pro-ink-3); letter-spacing: .02em; column-span: all; }
        .menu-panel-foot kbd { background: var(--pro-bg); border: 1px solid var(--pro-border-2); border-radius: 4px; padding: 1px 6px; font-size: 9px; color: var(--pro-ink-2); font-family: inherit; }
    }
    @keyframes proPanelIn { from { opacity: 0; transform: translate(-50%, -12px) scale(.985); } to { opacity: 1; transform: translate(-50%, 0) scale(1); } }

    /* Mobile & tablet: drawer kiri, accordion tetap aktif */
    @media (max-width: 1023px) {
        #mainSidebar {
            position: fixed; z-index: 40; top: 0; left: 0; right: auto; bottom: 0;
            width: min(320px, 88vw); height: 100dvh; background: var(--pro-surface);
            border: 0; border-right: 1px solid var(--pro-border-2);
            overflow-y: auto; display: none; padding: 14px 14px 18px;
            animation: proDrawerIn .22s cubic-bezier(.22,1,.36,1);
        }
        #mainSidebar.terbuka { display: block; }
        @keyframes proDrawerIn { from { transform: translateX(-100%); } to { transform: none; } }
        #mainSidebar .lg\:hidden { display: flex; }
        #mainSidebar a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; font-size: 13px; color: var(--pro-ink-2); text-decoration: none; border-left: 3px solid transparent; }
        #mainSidebar a:hover { background: var(--pro-accent-soft); color: var(--pro-accent-hover); }
        #mainSidebar a.sb-active, #mainSidebar a.group.sb-active { background: var(--pro-accent-soft); color: var(--pro-accent-hover); border-left-color: var(--pro-accent); font-weight: 600; }
        #mainSidebar .menu-group-toggle { display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 12px 12px 7px; border: 0; background: transparent; font-size: 11px; letter-spacing: .12em; text-transform: uppercase; color: var(--pro-ink-3); font-weight: 600; }
        #mainSidebar .menu-group-toggle > i.fa-chevron-down { margin-left: auto; transition: transform .16s ease; }
        #mainSidebar .menu-group-items.hidden { display: none; }
        #sidebarBrandHeader { display: flex; align-items: center; gap: 12px; padding: 4px 8px 12px; border-bottom: 1px solid var(--pro-border); margin-bottom: 10px; }
        .sidebar-brandchip { display: flex; align-items: center; gap: 10px; text-decoration: none; min-width: 0; }
        .brandchip-logo { display: block; height: 28px; width: auto; max-width: 170px; max-height: 28px; object-fit: contain; }
        .sidebar-kas-status { display: flex; align-items: center; gap: 12px; border: 1px solid var(--pro-border); border-radius: var(--pro-radius-md); padding: 10px 12px; margin: 0 0 10px; background: var(--pro-bg); }
        .sidebar-kas-status * { color: var(--pro-ink-2) !important; }
    }

    /* ===== MEGA MENU (ala Canva) — desktop >=1024px ===== */
    #proMega { display: none; }
    @media (min-width: 1024px) {
        /* Panel lama (#mainSidebar) digantikan di desktop oleh #proMega;
           #mainSidebar tetap hidup untuk Ctrl+K (palette) & RBAC, hanya
           tidak ditampilkan. */
        #mainSidebar.terbuka { display: none !important; }
        #proMega {
            position: fixed; z-index: 40;
            top: calc(var(--bar1-h) + var(--bar2-h) + 12px);
            left: 50%; transform: translate(-50%, 0);
            width: min(1240px, calc(100% - 32px));
            max-height: min(76vh, 680px);
            display: none; flex-direction: column; overflow: hidden;
            background: var(--pro-surface); border: 1px solid var(--pro-border-2);
            border-radius: var(--pro-radius); box-shadow: var(--pro-shadow-xl);
        }
        #proMega.terbuka { display: flex; animation: proMegaIn .3s cubic-bezier(.3,1.28,.38,1) both; }
        @keyframes proMegaIn {
            from { opacity: 0; transform: translate(-50%, -16px) scale(.965); }
            to { opacity: 1; transform: translate(-50%, 0) scale(1); }
        }
        .promega-body { flex: 1; min-height: 0; display: grid; grid-template-columns: 320px minmax(0, 1fr); }

        /* Rail kiri: kartu "featured" */
        .promega-rail { background: var(--pro-bg); border-right: 1px solid var(--pro-border); padding: 16px; display: flex; flex-direction: column; gap: 10px; overflow-y: auto; }
        .promega-rail-label { font-size: 10px; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--pro-ink-3); padding: 2px 4px 4px; }
        .promega-card {
            position: relative; display: flex; align-items: center; gap: 13px;
            padding: 14px; border-radius: 14px; text-decoration: none; color: #fff;
            transition: transform .2s cubic-bezier(.3,1.2,.4,1), box-shadow .2s ease; overflow: hidden;
        }
        .promega-card:hover { transform: translateY(-2px); box-shadow: var(--pro-shadow-md); }
        .promega-card:hover .promega-card-arrow { transform: translateX(4px); opacity: 1; }
        .promega-card:active { transform: none; }
        .promega-card.tone-blue { background: #2563EB; }
        .promega-card.tone-navy { background: #152347; }
        .promega-card.tone-emerald { background: #0E9F6E; }
        .promega-card::after {
            content: ""; position: absolute; right: -34px; top: -34px; width: 120px; height: 120px;
            border-radius: 999px; background: rgba(255,255,255,.12); pointer-events: none;
        }
        .promega-card-icon { flex: 0 0 46px; width: 46px; height: 46px; border-radius: 12px; background: rgba(255,255,255,.17); display: grid; place-items: center; }
        .promega-card-icon svg { width: 23px; height: 23px; stroke: #fff; fill: none; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
        .promega-card-text { min-width: 0; }
        .promega-card-title { display: block; font-size: 14.5px; font-weight: 600; letter-spacing: -0.01em; color: #fff; }
        .promega-card-desc { display: block; margin-top: 2px; font-size: 10.5px; color: rgba(255,255,255,.8); line-height: 1.35; }
        .promega-card-arrow { flex: 0 0 auto; margin-left: auto; opacity: .75; transition: transform .2s cubic-bezier(.3,1.2,.4,1), opacity .2s ease; }

        /* Kolom tautan kanan (gaya Canva: grid rata, teks polos) */
        .promega-content { overflow-y: auto; padding: 16px 22px 14px; }
        .promega-grid { columns: 175px; column-gap: 24px; }
        .promega-group { break-inside: avoid; margin-bottom: 18px; min-width: 0; }
        .promega-group-title {
            display: flex; align-items: center; gap: 8px;
            font-size: 10px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase;
            color: var(--pro-ink-3); border-bottom: 1px solid var(--pro-border);
            padding: 3px 2px 9px; margin-bottom: 8px;
        }
        .promega-group-title .notif-badge { margin-left: auto; }
        .promega-links { display: flex; flex-direction: column; gap: 3px; }
        .promega-link {
            display: flex; align-items: center; gap: 8px;
            padding: 7px 10px; margin: 0 -10px; border-radius: 9px;
            font-size: 13px; line-height: 1.35; color: var(--pro-ink-2);
            text-decoration: none; transition: background .15s ease, color .15s ease;
            overflow-wrap: anywhere;
        }
        .promega-link .notif-badge { margin-left: auto; flex: 0 0 auto; }
        /* Tautan polos ala Canva: ikon sidebar disembunyikan. */
        .promega-link svg { display: none; }
        .promega-link:hover { background: #F4F8FF; color: var(--pro-accent-hover); }
        .promega-link.is-active { background: var(--pro-accent-soft); color: var(--pro-accent-hover); font-weight: 600; }

        .promega-foot {
            flex: 0 0 auto; display: flex; justify-content: space-between; align-items: center; gap: 12px;
            border-top: 1px solid var(--pro-border); padding: 9px 20px;
            font-size: 9px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--pro-ink-3);
        }
        .promega-foot kbd { background: var(--pro-bg); border: 1px solid var(--pro-border-2); border-radius: 4px; padding: 1px 6px; font-size: 9px; color: var(--pro-ink-2); font-family: inherit; }
        html.dark .promega-link:hover { background: #22304F; color: var(--pro-accent-hover); }
        html.dark .promega-card.tone-navy { background: #22304F; border: 1px solid #3A4767; }
    }
    @media (prefers-reduced-motion: reduce) {
        #proMega.terbuka, #mainSidebar.terbuka, .content-wrapper { animation: none !important; }
    }

    /* ===== MAIN + TOPBAR ===== */
    main#mainContent {
        display: flex; flex-direction: column; width: 100%; min-width: 0; min-height: 100vh;
        background: transparent;
    }
    .content-wrapper {
        width: 100%; flex: 1;
        animation: proContentIn .34s cubic-bezier(.22,1,.36,1) both;
    }
    /* Tanpa transform pada elemen ini: transform menjadikan ancestor "containing
       block" bagi position:fixed, sehingga modal (x-modal/swal) melayang
       relatif ke konten, bukan ke viewport. Fade saja, tanpa geser. */
    @keyframes proContentIn { from { opacity: 0; } to { opacity: 1; } }
    body.pv2-leaving main#mainContent, body.pv2-leaving .content-wrapper {
        opacity: 0;
        transition: opacity .18s cubic-bezier(.4,0,.2,1);
    }

    .pro-topbar {
        position: sticky; top: 0; z-index: 30; background: var(--pro-surface);
        border-bottom: 1px solid var(--pro-border);
    }
    .pro-bar1 { display: flex; align-items: center; gap: 14px; height: var(--bar1-h); padding: 0 18px; }
    .pro-bar2 { display: flex; align-items: center; gap: 6px; height: var(--bar2-h); padding: 0 18px; overflow-x: auto; scrollbar-width: none; }
    .pro-bar2::-webkit-scrollbar { display: none; }
    @media (max-width: 1023.98px) {
        /* Tab aktif tidak terpotong tanpa jejak: fade di tepi + scrollbar tipis */
        .pro-bar2 {
            scrollbar-width: thin; scrollbar-color: #CBD6EE transparent;
            -webkit-mask-image: linear-gradient(90deg, transparent 0, #000 22px, #000 calc(100% - 26px), transparent 100%);
            mask-image: linear-gradient(90deg, transparent 0, #000 22px, #000 calc(100% - 26px), transparent 100%);
        }
        .pro-bar2::-webkit-scrollbar { display: block; height: 5px; }
        .pro-bar2::-webkit-scrollbar-thumb { background: #CBD6EE; border-radius: 999px; }
        .pro-bar2::-webkit-scrollbar-track { background: transparent; }
    }

    .pro-logo { display: flex; align-items: center; gap: 11px; text-decoration: none; flex: 0 0 auto; min-width: 0; }
    .pro-wordmark { font-size: 15px; font-weight: 600; letter-spacing: -0.02em; color: var(--pro-ink); line-height: 1.05; white-space: nowrap; }
    .pro-wordmark small { display: block; font-size: 8px; letter-spacing: .22em; color: var(--pro-ink-3); font-weight: 600; margin-top: 3px; white-space: nowrap; }
    @media (max-width: 560px) {
        .pro-logo { gap: 9px; }
        .pro-wordmark { font-size: 14px; }
        .pro-wordmark small { font-size: 6.5px; letter-spacing: .18em; margin-top: 2px; }
        .pro-bar1 { gap: 8px; padding: 0 10px; }
        .pro-premium { height: 34px; padding: 0; width: 34px; justify-content: center; }
    }

    .pro-search { display: flex; align-items: center; gap: 9px; height: 36px; border: 1px solid var(--pro-border-2); background: #F7FAFF; border-radius: 10px; padding: 0 11px; color: var(--pro-ink-3); flex: 1; max-width: 460px; font-size: 12px; transition: border-color .15s, box-shadow .15s; }
    .pro-search:hover { border-color: var(--pro-accent); }
    .pro-search:focus-within { border-color: var(--pro-accent); box-shadow: 0 0 0 3px var(--pro-accent-ring); }
    .pro-search kbd { margin-left: auto; background: var(--pro-surface); border: 1px solid var(--pro-border-2); border-radius: 5px; font-size: 9px; padding: 2px 6px; color: var(--pro-ink-2); }

    .pro-icon-btn { width: 38px; height: 38px; border: 1px solid var(--pro-border); background: var(--pro-surface); border-radius: 10px; display: grid; place-items: center; color: var(--pro-ink-2); transition: all .15s; position: relative; }
    .pro-icon-btn:hover { border-color: var(--pro-border-2); color: var(--pro-ink); transform: translateY(-1px); }
    .pro-icon-btn .notif-total { position: absolute; top: -5px; right: -5px; min-width: 17px; height: 17px; padding: 0 4px; border-radius: 999px; background: #DC2626; color: #fff; font-size: 8px; font-weight: 700; display: grid; place-items: center; border: 2px solid var(--pro-surface); }

    .pro-tab { display: inline-flex; align-items: center; gap: 7px; height: 32px; padding: 0 16px; border-radius: 8px; border: 1px solid transparent; background: transparent; font-size: 12px; font-weight: 500; color: var(--pro-ink-2); white-space: nowrap; text-decoration: none; transition: all .15s; }
    .pro-tab:hover { background: #F2F6FF; color: var(--pro-accent-hover); }
    .pro-tab.aktif { background: var(--pro-accent-soft); border-color: #C9D8FF; color: var(--pro-accent-hover); font-weight: 600; }
    html.dark .pro-tab.aktif { border-color: #2F3E66; }
    .pro-tab svg { opacity: .85; }

    .pro-menu-btn { display: inline-flex; align-items: center; gap: 8px; height: 38px; padding: 0 16px; border-radius: 10px; border: 1px solid var(--pro-border-2); background: var(--pro-surface); color: var(--pro-ink-2); font-size: 12px; font-weight: 600; margin-left: auto; white-space: nowrap; transition: all .15s; }
    .pro-menu-btn:hover { border-color: var(--pro-accent); color: var(--pro-accent-hover); background: #F7FAFF; }
    .pro-premium { display: inline-flex; align-items: center; gap: 7px; height: 38px; padding: 0 14px; border-radius: 10px; border: 1px solid #E5B85B; background: linear-gradient(150deg, #F7CD6B 0%, #E3A83B 55%, #C98A1B 100%); color: #422A05; font-size: 12px; font-weight: 700; letter-spacing: .02em; text-decoration: none; box-shadow: 0 4px 12px rgba(201, 138, 27, .35), inset 0 1px 0 rgba(255,255,255,.55); transition: transform .15s, box-shadow .15s, filter .15s; white-space: nowrap; }
    .pro-premium:hover { transform: translateY(-1px); filter: brightness(1.03); box-shadow: 0 8px 20px rgba(201, 138, 27, .4), inset 0 1px 0 rgba(255,255,255,.55); }
    .pro-premium:active { transform: none; }
    .pro-premium svg { flex: 0 0 auto; }
    @media (max-width: 767.98px) { .pro-premium-teks { display: none; } .pro-premium { padding: 0; width: 38px; justify-content: center; } }
    .pro-meta { font-size: 9.5px; letter-spacing: .1em; color: var(--pro-ink-3); white-space: nowrap; padding: 0 4px; text-transform: none; }
    .pro-live { display: inline-flex; align-items: center; gap: 7px; height: 26px; padding: 0 11px; border: 1px solid var(--pro-border); background: var(--pro-surface); border-radius: 999px; font-size: 10px; font-weight: 500; color: var(--pro-ink-2); white-space: nowrap; }
    .pro-live-dot { display: block; width: 6px; height: 6px; border-radius: 999px; background: #0E9F6E; animation: proLive 2.4s ease-in-out infinite; }
    @keyframes proLive { 0%, 100% { box-shadow: 0 0 0 0 rgba(14, 159, 110, .45); } 50% { box-shadow: 0 0 0 4px rgba(14, 159, 110, 0); } }
    @media (max-width: 860px) { .pro-search { display: none; } .pro-meta { display: none; } }

    /* Footer aplikasi */
    .pro-footer { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 10px 18px; border-top: 1px solid var(--pro-border); background: var(--pro-surface); font-size: 9.5px; letter-spacing: .08em; color: var(--pro-ink-3); }
    .pro-footer a { color: var(--pro-ink-2); text-decoration: none; transition: color .15s; }
    .pro-footer a:hover { color: var(--pro-accent-hover); text-decoration: underline; }
    .pro-footer .render-ms { font-variant-numeric: tabular-nums; letter-spacing: .12em; text-transform: uppercase; font-weight: 600; }
    /* Footer tidak tampil di PWA (layar HP) maupun cetak */
    @media screen and (max-width: 767.98px) { .pro-footer { display: none; } }
    @media print { .pro-footer { display: none !important; } }

    .notif-badge { --tw-shadow: none; }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; }
        .content-wrapper { animation: none; }
        body.pv2-leaving main#mainContent, body.pv2-leaving .content-wrapper { transition: none; opacity: 1; transform: none; }
    }
    </style>

    
</head>
<body class="text-slate-900">
    {{-- Visual Refresh v2: sidebar dihapus, navigasi di topbar. State
         "sidebar-collapsed" lama tidak dipakai lagi, jadi skrip anti-kedip
         dihapus pula. --}}

    <div id="sidebarOverlay" onclick="toggleMenuPanel()" aria-hidden="true"></div>

    <div class="flex min-h-screen">
        <aside id="mainSidebar" aria-hidden="true" aria-label="Menu utama">
            <div id="sidebarBrandHeader" class="relative">
                @php
                    $urlLogo = $appSetting->logo_path ? asset('storage/' . $appSetting->logo_path) : null;
                @endphp
                <a href="{{ url('/dashboard') }}" class="sidebar-brandchip" title="{{ $appSetting->nama_aplikasi }}">
                    @if($urlLogo)
                        <img src="{{ $urlLogo }}" alt="Logo {{ $appSetting->nama_aplikasi }}" width="600" height="238" class="brandchip-logo" decoding="async" fetchpriority="high">
                    @else
                        <svg width="30" height="30" viewBox="0 0 36 36" aria-hidden="true"><rect width="36" height="36" rx="7" fill="#1D4ED8"/><circle cx="13.4" cy="15.6" r="3.4" fill="#22D3EE"/><circle cx="18" cy="11" r="3.4" fill="#F43F5E"/><circle cx="22.6" cy="15.6" r="3.4" fill="#FACC15"/></svg>
                    @endif
                </a>
                <button onclick="toggleMenuPanel()" class="lg:hidden absolute top-3 right-3 w-9 h-9 rounded-full hover:bg-slate-100 text-slate-500 transition-colors" aria-label="Tutup menu">
                    <i class="fas fa-times"></i>
                </button>
            </div>
{{-- Status Kas: hanya untuk Administrator, kasir pembuka saldo, dan role kasir (menu Kasir) - operator lain tidak melihatnya. --}}
@php
    $adminSb = optional(Auth::user()->role)->nama_role === 'Administrator';
    $pembukaSb = $kasAktif && (int) $kasAktif->id_user === (int) Auth::id();
    $punyaKasirSb = in_array(16, \App\Support\Izin::menuTerlihat(Auth::user()), true);
    $tampilKasSb = $adminSb || $pembukaSb || $punyaKasirSb;
@endphp
@if($tampilKasSb)
<div class="mt-5 px-4">
    @if($kasAktif)
        @php
            $mulaiSb = \App\Services\KasSesiService::mulai($kasAktif);
            $umurSb = \App\Services\KasSesiService::umurJam($kasAktif);
            $pemilikSb = $pembukaSb ? 'Anda' : (DB::table('users')->leftJoin('karyawan', 'users.id_karyawan', '=', 'karyawan.id_karyawan')->where('users.id_user', $kasAktif->id_user)->value(DB::raw('COALESCE(karyawan.nama_karyawan, users.username)')) ?? '-');
            $umurTeks = $umurSb >= 1 ? floor($umurSb) . ' jam' : 'baru dibuka';
        @endphp
        <div class="sidebar-kas-status group rounded-2xl px-3 py-2.5 flex items-center gap-3 border {{ $kasBasi ? 'bg-amber-500/15 border-amber-400/40' : 'bg-emerald-500/15 border-emerald-400/25' }}"
             title="{{ $kasBasi ? 'Kasir Open lebih dari 24 jam - belum di-closing' : 'Kasir: Open' }}">
            <span class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $kasBasi ? 'bg-amber-400/20 text-amber-300' : 'bg-emerald-400/20 text-emerald-300' }}">
                <i class="fas {{ $kasBasi ? 'fa-triangle-exclamation' : 'fa-cash-register' }} text-sm"></i>
                <span class="absolute -top-0.5 -right-0.5 flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $kasBasi ? 'bg-amber-400' : 'bg-emerald-400' }} opacity-70"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full {{ $kasBasi ? 'bg-amber-400' : 'bg-emerald-400' }} ring-2 ring-slate-900"></span>
                </span>
            </span>
            <span class="min-w-0 leading-tight sidebar-text-label">
                <span class="block text-[11px] font-medium uppercase tracking-wider {{ $kasBasi ? 'text-amber-200' : 'text-emerald-200' }}">{{ $kasBasi ? 'Belum di-closing' : 'Kasir Open' }}</span>
                <span class="block text-[10px] font-medium text-or-teks truncate">{{ $mulaiSb ? $mulaiSb->format('d/m H:i') : '' }} &middot; {{ $umurTeks }} &middot; {{ $pemilikSb }}</span>
            </span>
        </div>
    @else
        <div class="sidebar-kas-status rounded-2xl px-3 py-2.5 flex items-center gap-3 border border-white/10 bg-white/5" title="Kasir: Standby">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/10 text-or-teks/80"><i class="fas fa-moon text-sm"></i></span>
            <span class="min-w-0 leading-tight sidebar-text-label">
                <span class="block text-[11px] font-medium uppercase tracking-wider text-or-teks">Kasir Standby</span>
                <span class="block text-[10px] font-medium text-or-teks/60">Belum ada saldo dibuka</span>
            </span>
        </div>
    @endif
</div>
@endif
            <nav class="mt-5 px-4 pb-24 space-y-1.5">
    @php
        $userAkses = \App\Support\Izin::menuTerlihat(Auth::user());
        // Menu yang dinonaktifkan lewat Pengaturan Menu disembunyikan dari sidebar untuk semua role.
        $menuNonaktif = DB::table('menus')->where('aktif', false)->pluck('id_menu')->map(fn ($i) => (int) $i)->all();

        // Administrator selalu lolos di sini juga, sama seperti bypass di
        // CheckMenuAccess middleware. Tanpa ini, menu sidebar Administrator
        // bisa ikut hilang kalau grant-nya di role_permissions tidak sengaja
        // tercabut/kosong (mis. dari halaman Pengaturan Akses) - padahal
        // rute-rutenya sendiri tetap bisa diakses karena middleware sudah
        // bypass duluan, jadi menu yang "hilang" itu cuma ilusi UI.
        $isAdministrator = optional(Auth::user()->role)->nama_role === 'Administrator';

        // Menu yang isinya harga/nilai uang disembunyikan untuk role tanpa hak lihat harga
        // (Finishing Produk = 21, Penawaran = 58), walau menunya tercentang di Hak Akses.
        $menuBerharga = [21, 58];
        $bolehLihatHargaSidebar = \App\Support\AksesHarga::bolehLihat();

        $cekAkses = function($idMenu) use ($userAkses, $isAdministrator, $menuBerharga, $bolehLihatHargaSidebar, $menuNonaktif) {
            if (in_array($idMenu, $menuNonaktif, true)) {
                return false;
            }

            if (!$bolehLihatHargaSidebar && in_array($idMenu, $menuBerharga, true)) {
                return false;
            }

            return $isAdministrator || in_array($idMenu, $userAkses);
        };

        // Helper untuk styling menu - teks terang di atas latar gelap sidebar.
        $menuClass = "flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] font-medium transition-colors group relative ";
        $activeClass = "sb-active bg-white/10 text-white";
        $nonActiveClass = "hover:bg-white/5";

        $iconClass = "h-[18px] w-[18px] shrink-0 transition-colors ";
        $iconActive = "text-or-aksen";
        $iconNonActive = "text-or-teks opacity-70 group-hover:opacity-100";
    @endphp

    {{-- DASHBOARD --}}
    <a href="{{ url('/dashboard') }}" 
       class="{{ $menuClass }} {{ Request::is('dashboard', 'dashboard/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('dashboard', 'dashboard/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
        </svg>
        <span class="text-sm font-medium">Dashboard</span>
    </a>

    @if($isAdministrator || \App\Support\Izin::boleh(Auth::user(), [67], 'add') || \App\Support\Izin::boleh(Auth::user(), [67], 'edit'))
    <a href="{{ route('postingan.index') }}" title="Tulis dan kelola postingan Info &amp; Panduan" class="{{ $menuClass }} {{ Request::is('postingan', 'postingan/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('postingan', 'postingan/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
        </svg>
        <span class="text-sm font-medium">Kelola Postingan</span>
    </a>
    @endif

    @if($isAdministrator || $cekAkses(60))
    <a href="{{ route('eksekutif.dashboard') }}" class="{{ $menuClass }} {{ Request::is('eksekutif', 'eksekutif/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('eksekutif', 'eksekutif/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
        </svg>
        <span class="text-sm font-medium">Dashboard Eksekutif</span>
    </a>
    @endif
@if($isAdministrator || $cekAkses(60))
    <a href="{{ route('audit-owner.index') }}" class="{{ $menuClass }} {{ Request::is('audit-owner', 'audit-owner/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('audit-owner', 'audit-owner/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
        </svg>
        <span class="text-sm font-medium">Audit Bulanan</span>
    </a>
    @endif

    @php
        $grpProduksiVisible = $cekAkses(1) || $cekAkses(20) || $cekAkses(17) || $cekAkses(22) || $cekAkses(23) || $cekAkses(24) || $cekAkses(15) || $cekAkses(31) || $cekAkses(30) || $cekAkses(10) || $cekAkses(27) || $cekAkses(33) || $cekAkses(53) || $cekAkses(54) || $cekAkses(55) || $cekAkses(56) || $cekAkses(57);
        $grpProduksiAktif = Request::is('bahan-baku', 'bahan-baku/*') || Request::is('kartu-stok', 'kartu-stok/*') || Request::is('stock-opname', 'stock-opname/*')
            || Request::is('pembelian-bahan', 'pembelian-bahan/*') || Request::is('kedatangan-bahan', 'kedatangan-bahan/*')
            || Request::is('purchase-request', 'purchase-request/*') || Request::is('purchase-order', 'purchase-order/*') || Request::is('qc', 'qc/*') || Request::is('waste', 'waste/*')
            || request()->routeIs('spk.*') || request()->routeIs('produksi.*') || request()->routeIs('collecting.*')
            || request()->routeIs('kategori.*') || Request::is('jenis-bahan', 'jenis-bahan/*') || Request::is('merk', 'merk/*')
            || Request::is('satuan', 'satuan/*') || Request::is('mesin', 'mesin/*') || Request::is('gudang', 'gudang/*');
    @endphp
    @if($grpProduksiVisible)
    <button type="button" onclick="toggleMenuGroup(this)" class="menu-group-toggle w-full flex items-center justify-between pt-4 pb-2 px-3 text-[11px] font-medium text-or-teks/70 uppercase tracking-wider transition-colors hover:text-or-teks">
        <span>Produksi & Logistik</span>
        <i class="fas fa-chevron-down text-[10px] transition-transform {{ $grpProduksiAktif ? 'rotate-180' : '' }}"></i>
    </button>
    <div class="menu-group-items space-y-0.5 {{ $grpProduksiAktif ? '' : 'hidden' }}">
    @endif

    @if($cekAkses(1))
    <a href="{{ url('/bahan-baku') }}" class="{{ $menuClass }} {{ Request::is('bahan-baku', 'bahan-baku/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('bahan-baku', 'bahan-baku/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
        </svg>
        <span class="text-sm font-medium">Bahan Baku</span>
    </a>
    <a href="{{ url('/kartu-stok') }}" class="{{ $menuClass }} {{ Request::is('kartu-stok', 'kartu-stok/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('kartu-stok', 'kartu-stok/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 014-4h4m0 0l-3-3m3 3l-3 3M5 7v10a2 2 0 002 2h10a2 2 0 002-2V9a2 2 0 00-2-2h-4l-2-2H7a2 2 0 00-2 2z" />
        </svg>
        <span class="text-sm font-medium">Kartu Stok</span>
    </a>
    @endif
    @if($cekAkses(1) || $cekAkses(53))
    <a href="{{ route('stock-opname.index') }}" class="{{ $menuClass }} {{ request()->routeIs('stock-opname.*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ request()->routeIs('stock-opname.*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-7 9l2 2 4-4" />
        </svg>
        <span class="text-sm font-medium">Stok Opname</span>
    </a>
    @endif
    @if($cekAkses(20))
    <a href="{{ route('pembelian-bahan.index') }}"
    class="{{ $menuClass }} {{ Request::is('pembelian-bahan', 'pembelian-bahan/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg"
            class="{{ $iconClass }} {{ Request::is('pembelian-bahan', 'pembelian-bahan/*') ? $iconActive : $iconNonActive }}"
            fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 14l6-6m0 0l-6-6m6 6H3" />
        </svg>
        <span class="text-sm font-medium">Pembelian Bahan</span>
    </a>
    @endif
    @if($cekAkses(1) || $cekAkses(20) || $cekAkses(54))
    <a href="{{ url('/purchase-request') }}" class="{{ $menuClass }} {{ Request::is('purchase-request', 'purchase-request/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('purchase-request', 'purchase-request/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2M9 12h6m-6 4h4" />
        </svg>
        <span class="text-sm font-medium">Permintaan Pembelian</span>
    </a>
    @endif
    @if($cekAkses(20) || $cekAkses(55))
    <a href="{{ url('/purchase-order') }}" class="{{ $menuClass }} {{ Request::is('purchase-order', 'purchase-order/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('purchase-order', 'purchase-order/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <span class="text-sm font-medium">Pesanan Pembelian</span>
    </a>
    @endif
    @if($cekAkses(17))
<a href="{{ url('/kedatangan-bahan') }}" 
   id="menu-kedatangan-bahan"
   class="{{ $menuClass }} {{ Request::is('kedatangan-bahan', 'kedatangan-bahan/*') ? $activeClass : $nonActiveClass }}">

    <svg xmlns="http://www.w3.org/2000/svg"
         class="{{ $iconClass }} {{ Request::is('kedatangan-bahan', 'kedatangan-bahan/*') ? $iconActive : $iconNonActive }}"
         fill="none"
         viewBox="0 0 24 24"
         stroke="currentColor">

        <path stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M8 17l4 4 4-4m0-5l-4-4-4 4m-4 5h16" />
    </svg>

    <span class="text-sm font-medium">
        Kedatangan Bahan
    </span>
</a>
@endif

    @if($cekAkses(22))
    <a href="{{ route('spk.index') }}" class="{{ $menuClass }} {{ request()->routeIs('spk.*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ request()->routeIs('spk.*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <span class="text-sm font-medium">SPK</span>
    </a>
    @endif

    @if($cekAkses(23))
    <a href="{{ route('produksi.index') }}" class="{{ $menuClass }} {{ request()->routeIs('produksi.*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ request()->routeIs('produksi.*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V8l-6-6H6a2 2 0 00-2 2v16a2 2 0 002 2h11a2 2 0 002-2zM8 12h8m-8 4h5" />
        </svg>
        <span class="text-sm font-medium">Produksi</span>
    </a>
    @endif


    @if($cekAkses(23) || $cekAkses(56))
    <a href="{{ url('/qc') }}" class="{{ $menuClass }} {{ Request::is('qc', 'qc/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('qc', 'qc/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
        <span class="text-sm font-medium">QC & Reparasi</span>
    </a>
    @endif

    @if($cekAkses(1) || $cekAkses(23) || $cekAkses(57))
    <a href="{{ url('/waste') }}" class="{{ $menuClass }} {{ Request::is('waste', 'waste/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('waste', 'waste/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        </svg>
        <span class="text-sm font-medium">Pengelolaan Limbah</span>
    </a>
    @endif

    @if($cekAkses(24))
    <a href="{{ route('collecting.index') }}" class="{{ $menuClass }} {{ request()->routeIs('collecting.*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ request()->routeIs('collecting.*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <span class="text-sm font-medium">Pengambilan</span>
    </a>
    @endif

    @if($cekAkses(15))
    <a href="{{ route('kategori.index') }}" class="{{ $menuClass }} {{ request()->routeIs('kategori.*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ request()->routeIs('kategori.*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
        </svg>
        <span class="text-sm font-medium">Kategori Bahan</span>
    </a>
    @endif

    @if($cekAkses(31))
    <a href="{{ route('jenis-bahan.index') }}" class="{{ $menuClass }} {{ Request::is('jenis-bahan', 'jenis-bahan/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('jenis-bahan', 'jenis-bahan/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
        </svg>
        <span class="text-sm font-medium">Jenis Bahan</span>
    </a>
    @endif

    @if($cekAkses(30))
    <a href="{{ route('merk.index') }}" class="{{ $menuClass }} {{ Request::is('merk', 'merk/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('merk', 'merk/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
        </svg>
        <span class="text-sm font-medium">Merk</span>
    </a>
    @endif

    @if($cekAkses(10))
    <a href="{{ url('/satuan') }}" class="{{ $menuClass }} {{ Request::is('satuan', 'satuan/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('satuan', 'satuan/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
        </svg>
        <span class="text-sm font-medium">Satuan & Konversi</span>
    </a>
    @endif

    @if($cekAkses(27))
    <a href="{{ url('/mesin') }}" class="{{ $menuClass }} {{ Request::is('mesin', 'mesin/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('mesin', 'mesin/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <span class="text-sm font-medium">Aset Mesin</span>
    </a>
    @endif

    @if($cekAkses(33))
    <a href="{{ route('gudang.index') }}" class="{{ $menuClass }} {{ Request::is('gudang', 'gudang/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('gudang', 'gudang/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
        <span class="text-sm font-medium">Gudang</span>
    </a>
    @endif
    @if($grpProduksiVisible)
    </div>
    @endif

    @php
        
        $grpRelasiVisible = $cekAkses(7) || $cekAkses(34) || $cekAkses(18) || $cekAkses(13) || $cekAkses(21) || $cekAkses(32) || $cekAkses(14) || $cekAkses(28) || $cekAkses(9) || $cekAkses(16)
            
            // 62 Platform Belanja Online, 68 Jasa Jahit Borongan, dan 58
            // Penawaran ikut dihitung. Tanpa ketiganya, role yang MEMILIKI
            // izin tersebut tetap tidak melihat link-nya: header grup
            // disembunyikan sehingga isinya tidak pernah dirender, jadi
            // menu yang sudah di-grant itu tetap tidak bisa dipakai.
            
|| $cekAkses(62) || $cekAkses(68) || $cekAkses(58)
            // 12 Golongan Konsumen ikut dihitung. Menu ini dipegang role 1,
            // 2, dan 3, sedangkan syarat grup ini tadinya hanya menguji id 18
            // yang dipegang role 1 saja. Akibatnya Operator Desain dan Kasir
            // yang sah memegang izin 12 tetap kehilangan seluruh grup
            // "Relasi Bisnis" - header-nya tidak pernah dirender.
            || $cekAkses(12);
        $grpRelasiAktif = Request::is('konsumen', 'konsumen/*') || Request::is('golongan', 'golongan/*') || Request::is('produk', 'produk/*')
            || Request::is('finishing', 'finishing/*') || Request::is('jasa', 'jasa/*') || Request::is('jasa-jahit', 'jasa-jahit/*') || Request::is('setting-harga', 'setting-harga/*') || Request::is('harga-siap-cetak', 'harga-siap-cetak/*')
            || Request::is('harga-promo', 'harga-promo/*') || Request::is('supplier', 'supplier/*') || Request::is('platform-belanja', 'platform-belanja/*') || Request::is('kasir/monitoring', 'kasir/monitoring/*')
            || Request::is('kasir/kelola-kas', 'kasir/kelola-kas/*')
            // Setoran Konsumen tadinya TIDAK ada di daftar ini, padahal
            // menu-nya ada di grup ini. Akibatnya begitu halaman deposit
            // dibuka tidak ada grup yang terflag aktif, sehingga semua
            // grup accordion ikut tertutup - berbeda dari menu lain di
            // grup yang sama yang tetap terbuka.
            || Request::is('konsumen-deposit', 'konsumen-deposit/*');
    @endphp
    @if($grpRelasiVisible)
    <button type="button" onclick="toggleMenuGroup(this)" class="menu-group-toggle w-full flex items-center justify-between pt-4 pb-2 px-3 text-[11px] font-medium text-or-teks/70 uppercase tracking-wider transition-colors hover:text-or-teks">
        <span>Relasi Bisnis</span>
        <i class="fas fa-chevron-down text-[10px] transition-transform {{ $grpRelasiAktif ? 'rotate-180' : '' }}"></i>
    </button>
    <div class="menu-group-items space-y-0.5 {{ $grpRelasiAktif ? '' : 'hidden' }}">
    @endif

    @if($cekAkses(7))
    <a href="{{ url('/konsumen') }}" class="{{ $menuClass }} {{ Request::is('konsumen', 'konsumen/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('konsumen', 'konsumen/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
        <span class="text-sm font-medium">Data Konsumen</span>
    </a>
    @endif

    @if($cekAkses(34))
    <a href="{{ route('konsumen-deposit.index') }}" class="{{ $menuClass }} {{ Request::is('konsumen-deposit', 'konsumen-deposit/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('konsumen-deposit', 'konsumen-deposit/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3v-1m-3 1v-5m11-4V6a2 2 0 00-2-2H5a2 2 0 00-2 2v9a2 2 0 002 2h1" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 17a5 5 0 100-10 5 5 0 000 10z" />
        </svg>
        <span class="text-sm font-medium">Setoran Konsumen</span>
    </a>
    @endif

    
    {{-- Golongan.

         Syaratnya dibuat sama dengan peta akses di
         config/menu_access.php baris 65: 'golongan' => [8, 12, 18].
         Sebelumnya hanya id 18 yang diuji, padahal id 12 "Golongan
         Konsumen" justru yang dipegang role 2 dan 3 - mereka boleh membuka
         /golongan (route mengizinkan 8, 12, atau 18) tapi tidak pernah
         melihat link-nya. GolonganController hanya melayani tabel
         golongan_konsumen, jadi id 12 adalah nama yang tepat untuk route ini.

         Perbaikan ini tidak menambah akses baru: route sudah menerima ketiganya
         sejak dulu. Yang hilang cuma tautannya di sidebar. --}}
    @if($cekAkses(8) || $cekAkses(12) || $cekAkses(18))
<a href="{{ route('golongan.index') }}"
   class="{{ $menuClass }} {{ Request::is('golongan', 'golongan/*') ? $activeClass : $nonActiveClass }}">
    <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('golongan', 'golongan/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
    </svg>
    <span class="text-sm font-medium">Golongan</span>
</a>
@endif

    @if($cekAkses(13))
    <a href="{{ url('/produk') }}" class="{{ $menuClass }} {{ Request::is('produk', 'produk/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('produk', 'produk/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
        </svg>
        <span class="text-sm font-medium">Produk & BOM</span>
    </a>
    @endif

    @if($cekAkses(21))
    <a href="{{ url('/finishing') }}" class="{{ $menuClass }} {{ Request::is('finishing', 'finishing/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('finishing', 'finishing/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42" />
        </svg>
        <span class="text-sm font-medium">Finishing Produk</span>
    </a>
    @endif

    @if($cekAkses(32))
    <a href="{{ route('jasa.index') }}" class="{{ $menuClass }} {{ Request::is('jasa', 'jasa/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('jasa', 'jasa/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
        </svg>
        <span class="text-sm font-medium">Jasa</span>
    </a>
    @endif

    {{-- Jasa Jahit Borongan (PENGELUARAN). Disebelahan "Jasa" karena
         keduanya pasangan: Jasa pendapatan, Jahit pengeluaran. Yang ini
         cek aksesnya ke id menu 68, sama seperti cekAkses() di bawahnya
         cek id menu lain - kalau nomornya salah, menunya tidak akan
         muncul bagi siapa pun. --}}
    @if($cekAkses(68))
    <a href="{{ route('jasa-jahit.index') }}" class="{{ $menuClass }} {{ Request::is('jasa-jahit', 'jasa-jahit/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('jasa-jahit', 'jasa-jahit/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            {{-- Gunting. Ikon lain di sidebar memakai stroke outline yang
                 sama, jadi bentuknya konsisten dengan sisa menu. --}}
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6l12 12M18 6L6 18M6.5 6.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5zM6.5 22.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5zM17.5 8.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" />
        </svg>
        <span class="text-sm font-medium">Jasa Jahit Borongan</span>
    </a>
    @endif

    @if($cekAkses(14))
    <a href="{{ url('/setting-harga') }}" class="{{ $menuClass }} {{ Request::is('setting-harga', 'setting-harga/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('setting-harga', 'setting-harga/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
        </svg>
        <span class="text-sm font-medium">Daftar Harga & Tingkat</span>
    </a>
    <a href="{{ route('harga-siap-cetak.index') }}" title="Harga khusus order dengan file siap cetak dari pelanggan" class="{{ $menuClass }} {{ Request::is('harga-siap-cetak', 'harga-siap-cetak/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('harga-siap-cetak', 'harga-siap-cetak/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
        </svg>
        <span class="text-sm font-medium">Harga File Siap Cetak</span>
    </a>
    @endif

    @if($cekAkses(28))
    <a href="{{ url('/harga-promo') }}" class="{{ $menuClass }} {{ Request::is('harga-promo', 'harga-promo/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('harga-promo', 'harga-promo/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5.586a1 1 0 01.707.293l7.414 7.414a1 1 0 010 1.414l-7.586 7.586a1 1 0 01-1.414 0L4.293 12.293A1 1 0 014 11.586V6a3 3 0 013-3z" />
        </svg>
        <span class="text-sm font-medium">Harga Promo</span>
    </a>
    @endif

    @if($cekAkses(9))
    <a href="{{ url('/supplier') }}" class="{{ $menuClass }} {{ Request::is('supplier', 'supplier/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('supplier', 'supplier/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
        </svg>
        <span class="text-sm font-medium">Pemasok</span>
    </a>
    @endif
    @if($cekAkses(9) || $cekAkses(62))
    <a href="{{ route('platform-belanja.index') }}" class="{{ $menuClass }} {{ Request::is('platform-belanja', 'platform-belanja/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('platform-belanja', 'platform-belanja/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
        <span class="text-sm font-medium">Platform Belanja Online</span>
    </a>
    @endif
@if($cekAkses(16))
<a href="{{ url('/kasir/monitoring') }}" 

   class="{{ $menuClass }} {{ Request::is('kasir/monitoring', 'kasir/monitoring/*') ? 'bg-emerald-500/10 text-emerald-300' : $nonActiveClass }} {{ !$kasAktif ? 'opacity-50' : '' }}">
    <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('kasir/monitoring', 'kasir/monitoring/*') ? 'text-emerald-400' : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
    </svg>
    <span class="text-sm font-medium">Monitoring Kasir</span>
    <span id="badge-notif" class="hidden ml-auto bg-rose-500 text-white text-[10px] px-2 py-0.5 rounded-full animate-bounce">0</span>
</a>

<a href="{{ url('/kasir/kelola-kas') }}" 
   class="{{ $menuClass }} {{ Request::is('kasir/kelola-kas', 'kasir/kelola-kas/*') ? $activeClass : $nonActiveClass }}">
    <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('kasir/kelola-kas', 'kasir/kelola-kas/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
    </svg>
    <span class="text-sm font-medium">Buka/Tutup Kas</span>
</a>
@endif
    @if($grpRelasiVisible)
    </div>
    @endif

    @php
        
        // 61 Riwayat Penjualan dan 58 Penawaran ikut dihitung. Tanpa
        // keduanya, role yang memegang izin itu tidak pernah melihat link-nya
        // karena header grupnya tetap tersembunyi.
        $grpFinanceVisible = $cekAkses(3) || $cekAkses(41) || $cekAkses(39) || $cekAkses(45) || $cekAkses(11) || $cekAkses(5) || $cekAkses(48)
            || $cekAkses(61) || $cekAkses(58);
        $grpFinanceAktif = Request::is('quotation', 'quotation/*') || Request::is('penjualan/kasir', 'penjualan/kasir/*') || Request::is('penjualan-barang', 'penjualan-barang/*') || Request::is('penjualan/riwayat', 'penjualan/riwayat/*')
            || request()->routeIs('kas-kecil.*') || request()->routeIs('kategori-kas-kecil.*')
            || Request::is('bank', 'bank/*') || Request::is('karyawan', 'karyawan/*');
    @endphp
    @if($grpFinanceVisible)
    <button type="button" onclick="toggleMenuGroup(this)" class="menu-group-toggle w-full flex items-center justify-between pt-5 pb-2 px-3 text-[11px] font-medium text-or-teks/70 uppercase tracking-[0.16em] hover:text-or-teks transition-colors">
        <span>Keuangan & SDM</span>
        <i class="fas fa-chevron-down text-[10px] transition-transform {{ $grpFinanceAktif ? 'rotate-180' : '' }}"></i>
    </button>
    <div class="menu-group-items space-y-1 {{ $grpFinanceAktif ? '' : 'hidden' }}">
    @endif

    @if($cekAkses(3) || $cekAkses(41))
    <a href="{{ route('penjualan.create') }}"
       class="{{ $menuClass }} {{ Request::is('penjualan/kasir', 'penjualan/kasir/*') ? 'bg-emerald-500/10 text-emerald-300' : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('penjualan/kasir', 'penjualan/kasir/*') ? 'text-emerald-400' : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
        <span class="text-sm font-medium">Input Pesanan</span>
    </a>
    @endif

    {{-- Penawaran memuat harga: disembunyikan untuk role tanpa hak lihat harga (Operator Desain/Produksi) --}}
    @if(($cekAkses(3) || $cekAkses(41) || $cekAkses(58)) && $bolehLihatHargaSidebar)
    <a href="{{ url('/quotation') }}" class="{{ $menuClass }} {{ Request::is('quotation', 'quotation/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('quotation', 'quotation/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <span class="text-sm font-medium">Penawaran</span>
    </a>
    @endif

    @if($cekAkses(48))
    <a href="{{ route('penjualan-barang.create') }}"
       class="{{ $menuClass }} {{ Request::is('penjualan-barang', 'penjualan-barang/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('penjualan-barang', 'penjualan-barang/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
        </svg>
        <span class="text-sm font-medium">Penjualan dan Jasa</span>
    </a>
    @endif

    @if($cekAkses(3) || $cekAkses(61))
    <a href="{{ route('penjualan.riwayat') }}"
       class="{{ $menuClass }} {{ Request::is('penjualan/riwayat', 'penjualan/riwayat/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('penjualan/riwayat', 'penjualan/riwayat/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="text-sm font-medium">Riwayat Penjualan</span>
    </a>
    @endif

    @if($cekAkses(39))
    <a href="{{ route('kas-kecil.index') }}" class="{{ $menuClass }} {{ request()->routeIs('kas-kecil.*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ request()->routeIs('kas-kecil.*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m9-8a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="text-sm font-medium">Kas Kecil</span>
    </a>
    @endif

    @if($cekAkses(45))
    <a href="{{ route('kategori-kas-kecil.index') }}" class="{{ $menuClass }} {{ request()->routeIs('kategori-kas-kecil.*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ request()->routeIs('kategori-kas-kecil.*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 9V4a1 1 0 011-1z" />
        </svg>
        <span class="text-sm font-medium">Kategori Kas Kecil</span>
    </a>
    @endif

    @if($cekAkses(11))
    <a href="{{ url('/bank') }}" class="{{ $menuClass }} {{ Request::is('bank', 'bank/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('bank', 'bank/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
        </svg>
        <span class="text-sm font-medium">Bank Perusahaan</span>
    </a>
    @endif

    @if($cekAkses(5))
    <a href="{{ url('/karyawan') }}" class="{{ $menuClass }} {{ Request::is('karyawan', 'karyawan/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('karyawan', 'karyawan/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
        </svg>
        <span class="text-sm font-medium">Data Karyawan</span>
    </a>
    @endif
    @if($grpFinanceVisible)
    </div>
    @endif

    @php
        // cekAkses(47) (Laporan HPP & Margin) sebelumnya TIDAK ikut kondisi
        // ini - kalau suatu role cuma dikasih akses id_menu 47 saja, header
        // "Laporan" tidak pernah muncul padahal link HPP-nya sendiri tetap
        // render (yatim tanpa header). Ditambahkan di sini.
        $grpLaporanVisible = $cekAkses(25) || $cekAkses(26) || $cekAkses(29) || $cekAkses(40) || $cekAkses(43) || $cekAkses(44) || $cekAkses(46) || $cekAkses(47) || $cekAkses(65);
        $grpLaporanAktif = request()->routeIs('laporan.order') || request()->routeIs('laporan.produksi')
            || request()->routeIs('laporan.kas') || request()->routeIs('laporan.belanja-bahan') || request()->routeIs('laporan.hutang')
            || request()->routeIs('laporan.order-jersey') || request()->routeIs('laporan.piutang')
            || request()->routeIs('laporan.stok-bahan') || request()->routeIs('laporan.hpp') || request()->routeIs('laporan.job-costing') || request()->routeIs('laporan.profitabilitas') || request()->routeIs('laporan.pemakaian-bahan') || request()->routeIs('laporan.biaya-tinta') || request()->routeIs('laporan.evaluasi-master') || request()->routeIs('laporan.biaya-desain*');
    @endphp
    @if($grpLaporanVisible)
    <button type="button" onclick="toggleMenuGroup(this)" class="menu-group-toggle w-full flex items-center justify-between pt-5 pb-2 px-3 text-[11px] font-medium text-or-teks/70 uppercase tracking-[0.16em] hover:text-or-teks transition-colors">
        <span>Laporan</span>
        <i class="fas fa-chevron-down text-[10px] transition-transform {{ $grpLaporanAktif ? 'rotate-180' : '' }}"></i>
    </button>
    <div class="menu-group-items space-y-1 {{ $grpLaporanAktif ? '' : 'hidden' }}">
    @endif

    @if($cekAkses(25))
    <a href="{{ route('laporan.order') }}" class="{{ $menuClass }} {{ request()->routeIs('laporan.order') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ request()->routeIs('laporan.order') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
        </svg>
        <span class="text-sm font-medium">Laporan Pesanan</span>
    </a>
    @endif

    @if($cekAkses(26))
    <a href="{{ route('laporan.produksi') }}" class="{{ $menuClass }} {{ request()->routeIs('laporan.produksi') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ request()->routeIs('laporan.produksi') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
        </svg>
        <span class="text-sm font-medium">Laporan Produksi</span>
    </a>
    @endif

    @if($cekAkses(29))
    <a href="{{ route('laporan.kas') }}" class="{{ $menuClass }} {{ request()->routeIs('laporan.kas') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ request()->routeIs('laporan.kas') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
        <span class="text-sm font-medium">Laporan Kas Utama</span>
    </a>
    @endif

    @if($cekAkses(40))
    <a href="{{ route('laporan.belanja-bahan') }}" class="{{ $menuClass }} {{ request()->routeIs('laporan.belanja-bahan') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ request()->routeIs('laporan.belanja-bahan') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M4 7h16M5 7v11a2 2 0 002 2h10a2 2 0 002-2V7M9 7v11m6-11v11" />
        </svg>
        <span class="text-sm font-medium">Laporan Belanja Bahan</span>
    </a>
    <a href="{{ route('laporan.hutang') }}" class="{{ $menuClass }} {{ request()->routeIs('laporan.hutang') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ request()->routeIs('laporan.hutang') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m9-8a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="text-sm font-medium">Laporan Hutang Pemasok</span>
    </a>
    @endif

    @if($cekAkses(43))
    <a href="{{ route('laporan.order-jersey') }}" class="{{ $menuClass }} {{ request()->routeIs('laporan.order-jersey') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ request()->routeIs('laporan.order-jersey') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M4 7h16M5 7v11a2 2 0 002 2h10a2 2 0 002-2V7M9 7v11m6-11v11" />
        </svg>
        <span class="text-sm font-medium">Laporan Pesanan Jersey</span>
    </a>
    @endif

    @if($cekAkses(44))
    <a href="{{ route('laporan.piutang') }}" class="{{ $menuClass }} {{ request()->routeIs('laporan.piutang') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ request()->routeIs('laporan.piutang') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m9-8a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="text-sm font-medium">Laporan Piutang</span>
    </a>
    @endif

    @if($cekAkses(46))
    <a href="{{ route('laporan.stok-bahan') }}" class="{{ $menuClass }} {{ request()->routeIs('laporan.stok-bahan') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ request()->routeIs('laporan.stok-bahan') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
        </svg>
        <span class="text-sm font-medium">Laporan Stok Bahan Baku</span>
    </a>
    <a href="{{ route('laporan.pemakaian-bahan') }}" class="{{ $menuClass }} {{ request()->routeIs('laporan.pemakaian-bahan') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ request()->routeIs('laporan.pemakaian-bahan') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
        </svg>
        <span class="text-sm font-medium">Analisis Pemakaian Bahan</span>
    </a>
    @endif

    @if($cekAkses(47))
    <a href="{{ route('laporan.evaluasi-master') }}" class="{{ $menuClass }} {{ request()->routeIs('laporan.evaluasi-master') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ request()->routeIs('laporan.evaluasi-master') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
        </svg>
        <span class="text-sm font-medium">Lembar Evaluasi Master</span>
    </a>
    <a href="{{ route('laporan.biaya-tinta') }}" class="{{ $menuClass }} {{ request()->routeIs('laporan.biaya-tinta') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ request()->routeIs('laporan.biaya-tinta') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3c3.5 4.2 6 7 6 10a6 6 0 11-12 0c0-3 2.5-5.8 6-10z" />
        </svg>
        <span class="text-sm font-medium">Biaya Tinta</span>
    </a>
    <a href="{{ route('laporan.hpp') }}" class="{{ $menuClass }} {{ request()->routeIs('laporan.hpp') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ request()->routeIs('laporan.hpp') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 8h6m-5 4h4m-2-8a9 9 0 100 18 9 9 0 000-18zM9.5 15.5c.5 1 1.5 1.5 2.5 1.5s2-.5 2.5-1.5M9.5 9c.5-1 1.5-1.5 2.5-1.5s2 .5 2.5 1.5" />
        </svg>
        <span class="text-sm font-medium">Laporan HPP & Margin</span>
    </a>
    <a href="{{ route('laporan.job-costing') }}" class="{{ $menuClass }} {{ request()->routeIs('laporan.job-costing') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ request()->routeIs('laporan.job-costing') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
        </svg>
        <span class="text-sm font-medium">Laporan Biaya Pekerjaan</span>
    </a>
    <a href="{{ route('laporan.profitabilitas') }}" class="{{ $menuClass }} {{ request()->routeIs('laporan.profitabilitas') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ request()->routeIs('laporan.profitabilitas') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
        <span class="text-sm font-medium">Laporan Profitabilitas</span>
    </a>
    @endif

    @if($cekAkses(65))
    <a href="{{ route('laporan.biaya-desain') }}" title="Biaya desain per operator desain beserta komisi/bagi hasilnya" class="{{ $menuClass }} {{ request()->routeIs('laporan.biaya-desain*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ request()->routeIs('laporan.biaya-desain*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 112.828 2.828L11.828 15.83a4 4 0 01-1.414.94L7 17l.23-3.414a4 4 0 01.94-1.414z" />
        </svg>
        <span class="text-sm font-medium">Biaya &amp; Komisi Desain</span>
    </a>
    @endif

    @if($grpLaporanVisible)
    </div>
    @endif

    @php
        $menuKustom = DB::table('menus')->where('jenis', 'kustom')->where('aktif', true)->orderBy('urutan')->orderBy('id_menu')->get()
            ->filter(fn ($m) => $cekAkses((int) $m->id_menu));
    @endphp
    @if($menuKustom->isNotEmpty())
    <div class="pt-5 pb-2 px-3 text-[11px] font-medium uppercase tracking-widest text-or-teks/70">Menu Tambahan</div>
    <div class="space-y-1">
        @foreach($menuKustom as $mk)
        @php $luar = preg_match('#^https?://#i', $mk->url_link); @endphp
        <a href="{{ $luar ? $mk->url_link : url($mk->url_link) }}" @if($luar) target="_blank" rel="noopener" @endif class="{{ $menuClass }} {{ $nonActiveClass }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
            <span class="text-sm font-medium">{{ $mk->label_kustom ?: $mk->nama_menu }}</span>
        </a>
        @endforeach
    </div>
    @endif

    @php
        // Periode Akuntansi cuma bisa diakses Administrator (menu_access.php
        // 'accounting-period' => [] - tidak ada id_menu yang bisa
        // menggrant-nya lewat role_permissions), jadi dicek $isAdministrator
        // langsung, bukan $cekAkses(N).
        $grpAkuntansiVisible = $cekAkses(35) || $cekAkses(36) || $cekAkses(51) || $isAdministrator;
        $grpAkuntansiAktif = Request::is('akun', 'akun/*') || Request::is('akuntansi', 'akuntansi/*') || Request::is('accounting-period', 'accounting-period/*');
    @endphp
    @if($grpAkuntansiVisible)
    <button type="button" onclick="toggleMenuGroup(this)" class="menu-group-toggle w-full flex items-center justify-between pt-5 pb-2 px-3 text-[11px] font-medium text-or-teks/70 uppercase tracking-[0.16em] hover:text-or-teks transition-colors">
        <span>Akuntansi</span>
        <i class="fas fa-chevron-down text-[10px] transition-transform {{ $grpAkuntansiAktif ? 'rotate-180' : '' }}"></i>
    </button>
    <div class="menu-group-items space-y-1 {{ $grpAkuntansiAktif ? '' : 'hidden' }}">
    @endif

    @if($cekAkses(35))
    <a href="{{ route('akun.index') }}" class="{{ $menuClass }} {{ Request::is('akun', 'akun/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('akun', 'akun/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
        </svg>
        <span class="text-sm font-medium">Daftar Akun</span>
    </a>
    @endif

    @if($cekAkses(36))
    <a href="{{ route('akuntansi.jurnal') }}" class="{{ $menuClass }} {{ Request::is('akuntansi', 'akuntansi/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('akuntansi', 'akuntansi/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
        </svg>
        <span class="text-sm font-medium">Jurnal Umum</span>
    </a>
    @endif

    @if($isAdministrator || $cekAkses(51))
    <a href="{{ route('accounting-period.index') }}" class="{{ $menuClass }} {{ Request::is('accounting-period', 'accounting-period/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('accounting-period', 'accounting-period/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <span class="text-sm font-medium">Periode Akuntansi</span>
    </a>
    @endif
    @if($grpAkuntansiVisible)
    </div>
    @endif

    @php $grpJerseyAktif = Request::is('jersey', 'jersey/*'); @endphp
    @if($cekAkses(38) || $cekAkses(42))
    <button type="button" onclick="toggleMenuGroup(this)" class="menu-group-toggle w-full flex items-center justify-between pt-5 pb-2 px-3 text-[11px] font-medium text-or-teks/70 uppercase tracking-[0.16em] hover:text-or-teks transition-colors">
        <span>Pesanan Jersey</span>
        <i class="fas fa-chevron-down text-[10px] transition-transform {{ $grpJerseyAktif ? 'rotate-180' : '' }}"></i>
    </button>
    <div class="menu-group-items space-y-1 {{ $grpJerseyAktif ? '' : 'hidden' }}">
    @if($cekAkses(38))
    <a href="{{ url('/jersey') }}" class="{{ $menuClass }} {{ (Request::is('jersey', 'jersey/*') && !Request::is('jersey/master', 'jersey/master/*') && !Request::is('jersey/keuangan', 'jersey/keuangan/*') && !Request::is('jersey/create')) ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ (Request::is('jersey', 'jersey/*') && !Request::is('jersey/master', 'jersey/master/*') && !Request::is('jersey/keuangan', 'jersey/keuangan/*') && !Request::is('jersey/create')) ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M4 7h16M5 7v11a2 2 0 002 2h10a2 2 0 002-2V7M9 7v11m6-11v11" />
        </svg>
        <span class="text-sm font-medium">Daftar Pesanan</span>
    </a>
    @endif
    @if(!$cekAkses(38) && $cekAkses(42))
    <a href="{{ route('jersey.orders.create') }}" class="{{ $menuClass }} {{ Request::is('jersey/create') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('jersey/create') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M4 7h16M5 7v11a2 2 0 002 2h10a2 2 0 002-2V7M9 7v11m6-11v11" />
        </svg>
        <span class="text-sm font-medium">Pesanan Jersey Baru</span>
    </a>
    @endif
    @if($cekAkses(38))
    <a href="{{ url('/jersey/master') }}" class="{{ $menuClass }} {{ Request::is('jersey/master') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('jersey/master') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <span class="text-sm font-medium">Data Master Jersey</span>
    </a>
    <a href="{{ url('/jersey/keuangan') }}" class="{{ $menuClass }} {{ Request::is('jersey/keuangan', 'jersey/keuangan/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('jersey/keuangan', 'jersey/keuangan/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
        <span class="text-sm font-medium">Keuangan Jersey</span>
    </a>
    @endif
    </div>
    @endif

    @php
        // Alur Persetujuan, Log Aktivitas, Cadangan & Pemulihan cuma bisa
        // diakses Administrator (menu_access.php rule-nya [] kosong - tidak
        // ada id_menu yang bisa menggrant lewat role_permissions), jadi
        // dicek $isAdministrator langsung, sama seperti Periode Akuntansi.
        $grpSystemVisible = $cekAkses(6) || $cekAkses(4) || $cekAkses(37) || $cekAkses(63) || $cekAkses(66) || $cekAkses(49) || $cekAkses(50) || $cekAkses(52) || $isAdministrator;
        $grpSystemAktif = Request::is('users', 'users/*') || Request::is('setting-akses', 'setting-akses/*') || Request::is('pengaturan-aplikasi', 'pengaturan-aplikasi/*') || Request::is('pengaturan-jam-kerja', 'pengaturan-jam-kerja/*') || Request::is('pengaturan-desainer', 'pengaturan-desainer/*') || Request::is('menu-editor', 'menu-editor/*')
            || Request::is('approval', 'approval/*') || Request::is('log-aktivitas', 'log-aktivitas/*') || Request::is('backup', 'backup/*');
    @endphp
    @if($grpSystemVisible)
    <button type="button" onclick="toggleMenuGroup(this)" class="menu-group-toggle w-full flex items-center justify-between pt-5 pb-2 px-3 text-[11px] font-medium text-or-teks/70 uppercase tracking-[0.16em] hover:text-or-teks transition-colors">
        <span>Pengaturan Sistem</span>
        <i class="fas fa-chevron-down text-[10px] transition-transform {{ $grpSystemAktif ? 'rotate-180' : '' }}"></i>
    </button>
    <div class="menu-group-items space-y-1 {{ $grpSystemAktif ? '' : 'hidden' }}">
    @endif

    @if($cekAkses(6))
    <a href="{{ url('/users') }}" class="{{ $menuClass }} {{ Request::is('users', 'users/*') ? $activeClass : $nonActiveClass }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('users', 'users/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
        <span class="text-sm font-medium">Manajemen Pengguna</span>
    </a>
    @endif
    
@if($isAdministrator)
<a href="{{ url('/setting-akses') }}" 
   class="{{ $menuClass }} {{ Request::is('setting-akses', 'setting-akses/*') ? $activeClass : $nonActiveClass }}">
    <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('setting-akses', 'setting-akses/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
    </svg>
    <span class="text-sm font-medium">Pengaturan Akses</span>
</a>
@endif

@if($cekAkses(37))
<a href="{{ url('/pengaturan-aplikasi') }}"
   class="{{ $menuClass }} {{ Request::is('pengaturan-aplikasi', 'pengaturan-aplikasi/*') ? $activeClass : $nonActiveClass }}">
    <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('pengaturan-aplikasi', 'pengaturan-aplikasi/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>
    <span class="text-sm font-medium">Pengaturan Aplikasi</span>
</a>
@if(str_starts_with((string) (optional(auth()->user()?->role)->nama_role ?? ''), 'Administrator'))
<a href="{{ route('pengaturan-aplikasi.tema.index') }}"
   class="{{ $menuClass }} pl-8 {{ Request::is('pengaturan-aplikasi/tema', 'pengaturan-aplikasi/tema/*') ? $activeClass : $nonActiveClass }}">
    <i class="fas fa-palette w-5 text-center {{ Request::is('pengaturan-aplikasi/tema', 'pengaturan-aplikasi/tema/*') ? $iconActive : $iconNonActive }}" aria-hidden="true"></i>
    <span class="text-sm font-medium">Tema &amp; Tampilan</span>
</a>
@endif

@if($cekAkses(66))
<a href="{{ route('desainer.pengaturan') }}" title="Kelas desainer dan persen bagi hasil komisi desain" class="{{ $menuClass }} {{ Request::is('pengaturan-desainer', 'pengaturan-desainer/*') ? $activeClass : $nonActiveClass }}">
    <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('pengaturan-desainer', 'pengaturan-desainer/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>
    <span class="text-sm font-medium">Bagi Hasil Desainer</span>
</a>
@endif

@if($cekAkses(37) || $cekAkses(63))
<a href="{{ route('jam-kerja.index') }}" class="{{ $menuClass }} {{ Request::is('pengaturan-jam-kerja', 'pengaturan-jam-kerja/*') ? $activeClass : $nonActiveClass }}">
    <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('pengaturan-jam-kerja', 'pengaturan-jam-kerja/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    <span class="text-sm font-medium">Jam Kerja</span>
</a>
@endif

@if($isAdministrator)
<a href="{{ route('menu-editor.index') }}" class="{{ $menuClass }} {{ Request::is('menu-editor', 'menu-editor/*') ? $activeClass : $nonActiveClass }}">
    <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('menu-editor', 'menu-editor/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
    </svg>
    <span class="text-sm font-medium">Pengaturan Menu</span>
</a>
@endif

@if($isAdministrator)
<a href="{{ route('landing-page-builder.index') }}" class="{{ $menuClass }} {{ Request::is('landing-page-builder', 'landing-page-builder/*') ? $activeClass : $nonActiveClass }}">
    <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('landing-page-builder', 'landing-page-builder/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
    </svg>
    <span class="text-sm font-medium">Landing Page Builder</span>
</a>
@endif
@endif

@if($isAdministrator || $cekAkses(50))
<a href="{{ route('approval.index') }}" class="{{ $menuClass }} {{ Request::is('approval', 'approval/*') ? $activeClass : $nonActiveClass }}">
    <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('approval', 'approval/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    <span class="text-sm font-medium">Alur Persetujuan</span>
</a>
@endif

{{-- Log Aktivitas. Satu-satunya tempat menu ini dirender.

     Dua blok pernah ada: satu di dalam blok "Audit Bulanan" (syarat
     $cekAkses(60) dan hanya untuk non-Administrator), satu lagi di sini
     (syarat $cekAkses(49)). Role yang memegang KEDUA izin 60 dan 49
     karena itu melihat entri "Log Aktivitas" dua kali.

     Sekarang hanya blok ini, dan syaratnya Prosecuan dengan peta akses:
     config/menu_access.php memetakan 'log-aktivitas' => [60, 49]. Jadi
     sidebar selalu sama dengan route - tidak mungkin linknya muncul
     sementara route menolak, atau sebaliknya. --}}
@if($isAdministrator || $cekAkses(60) || $cekAkses(49))
<a href="{{ route('audit-log.index') }}" class="{{ $menuClass }} {{ Request::is('log-aktivitas', 'log-aktivitas/*') ? $activeClass : $nonActiveClass }}">
    <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('log-aktivitas', 'log-aktivitas/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    <span class="text-sm font-medium">Log Aktivitas</span>
</a>
@endif
@if($isAdministrator || $cekAkses(52))
<a href="{{ route('backup.index') }}" class="{{ $menuClass }} {{ Request::is('backup', 'backup/*') ? $activeClass : $nonActiveClass }}">
    <svg xmlns="http://www.w3.org/2000/svg" class="{{ $iconClass }} {{ Request::is('backup', 'backup/*') ? $iconActive : $iconNonActive }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7c0 1.657 3.582 3 8 3s8-1.343 8-3m-16 0c0-1.657 3.582-3 8-3s8 1.343 8 3m-16 0v10c0 1.657 3.582 3 8 3s8-1.343 8-3V7" />
    </svg>
    <span class="text-sm font-medium">Cadangan & Pemulihan</span>
</a>
@endif
@if($grpSystemVisible)
</div>
@endif
</nav>
        <div class="menu-panel-foot">
            <span>Orion Grafika ERP &middot; Pusat Operasi</span>
            <span><kbd>Esc</kbd> untuk menutup</span>
        </div>
        </aside>

        {{-- Mega menu desktop ala Canva: rail featured kiri + kolom tautan
             polos kanan. Isi dirakit dari #mainSidebar (RBAC satu sumber).
             Di <1024px elemen ini tidak pernah tampil (lihat CSS); yang
             dipakai drawer #mainSidebar. --}}
        <div id="proMega" role="dialog" aria-modal="false" aria-label="Menu utama" aria-hidden="true">
            <div class="promega-body">
                <div class="promega-rail" id="proMegaRail">
                    <span class="promega-rail-label">Akses Cepat</span>
                    <a class="promega-card tone-blue" href="{{ url('/penjualan/kasir') }}" data-href-suffix="/penjualan/kasir">
                        <span class="promega-card-icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V7z"/><path d="M12 9v.01M12 15v.01M9 12v.01M15 12v.01"/></svg>
                        </span>
                        <span class="promega-card-text">
                            <span class="promega-card-title">Kasir</span>
                            <span class="promega-card-desc">Buka transaksi penjualan baru</span>
                        </span>
                        <svg class="promega-card-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                    </a>
                    <a class="promega-card tone-navy" href="{{ url('/kasir/monitoring') }}" data-href-suffix="/kasir/monitoring">
                        <span class="promega-card-icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="4" width="19" height="12.5" rx="2"/><path d="M9 20.5h6M12 16.5v4"/><path d="M7 12l2.5-2.5 2.5 2.5 4.5-4.5"/></svg>
                        </span>
                        <span class="promega-card-text">
                            <span class="promega-card-title">Monitoring Kasir</span>
                            <span class="promega-card-desc">Pantau transaksi &amp; kas hari ini</span>
                        </span>
                        <svg class="promega-card-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                    </a>
                    <a class="promega-card tone-emerald" href="{{ url('/spk') }}" data-href-suffix="/spk">
                        <span class="promega-card-icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="4.5" width="14" height="17" rx="2"/><path d="M9 4.5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9.5 11h5M9.5 15h5M9.5 19h3"/></svg>
                        </span>
                        <span class="promega-card-text">
                            <span class="promega-card-title">Surat Perintah Kerja</span>
                            <span class="promega-card-desc">Kelola perintah produksi &amp; prioritas</span>
                        </span>
                        <svg class="promega-card-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                    </a>
                </div>
                <div class="promega-content">
                    <div class="promega-grid" id="proMegaGrid"></div>
                </div>
            </div>
            <div class="promega-foot">
                <span>Orion Grafika ERP &middot; Pusat Operasi</span>
                <span><kbd>Esc</kbd> menutup</span>
            </div>
        </div>

        <main id="mainContent">
    <header class="pro-topbar">
        <div class="pro-bar1">
            <a href="{{ url('/dashboard') }}" class="pro-logo" title="{{ $appSetting->nama_aplikasi }}">
                <svg width="34" height="34" viewBox="0 0 36 36" aria-hidden="true"><defs><linearGradient id="proCube" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#3B82F6"/><stop offset="1" stop-color="#1D4ED8"/></linearGradient></defs><rect width="36" height="36" rx="8" fill="url(#proCube)"/><circle cx="13.4" cy="15.6" r="3.4" fill="#22D3EE"/><circle cx="18" cy="11" r="3.4" fill="#F43F5E"/><circle cx="22.6" cy="15.6" r="3.4" fill="#FACC15"/></svg>
                <span class="pro-wordmark">Orion Grafika<small>ERP PERCETAKAN</small></span>
            </a>

            <button type="button" class="pro-search" onclick="bukaPalette()" aria-label="Cari menu, Ctrl K">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                <span>Cari menu, nota, bahan...</span>
                <kbd>Ctrl K</kbd>
            </button>

            <div style="margin-left:auto; display:flex; align-items:center; gap:8px">
                {{-- Orion Premium: mahkota emas, selalu terlihat --}}
                <a href="{{ route('premium.index') }}" class="pro-premium" title="Orion Premium - fitur eksklusif" aria-label="Orion Premium">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 8l4.5 3.5L12 4l4.5 7.5L21 8l-1.6 9.2a1.2 1.2 0 0 1-1.18 1.02H5.78A1.2 1.2 0 0 1 4.6 17.2L3 8z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" fill="rgba(255,255,255,.18)" stroke-linecap="round"/></svg>
                    <span class="pro-premium-teks">Premium</span>
                </a>
                {{-- Dark mode: hanya Administrator --}}
                @if(str_starts_with((string) (optional(Auth::user()->role)->nama_role ?? ''), 'Administrator'))
                <button type="button" id="tema-toggle" onclick="ubahTema()" class="pro-icon-btn" title="Ganti ke mode gelap" aria-label="Ganti mode gelap atau terang">
                    <i class="fas fa-moon" id="tema-ikon"></i>
                </button>
                @endif
                {{-- Notifikasi & pekerjaan masuk --}}
                @php $notifAwal = \App\Services\NotifikasiService::untuk(Auth::user()); @endphp
                <div style="position:relative">
                    <button type="button" id="notif-btn" onclick="toggleNotif(event)" class="pro-icon-btn" title="Notifikasi & pekerjaan masuk" aria-label="Notifikasi">
                        <i class="fas fa-bell"></i>
                        <span id="notif-total" class="notif-total hidden"></span>
                    </button>
                    <div id="notif-panel" class="hidden absolute right-0 mt-2 w-80 max-w-[90vw] bg-white rounded-xl shadow-lg border border-slate-200 z-50 overflow-hidden" style="box-shadow:0 18px 44px rgba(15,30,62,.14)">
                        <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
                            <span class="text-[11px] font-semibold tracking-widest text-slate-500 uppercase">Pekerjaan masuk</span>
                            <span id="notif-total-teks" class="text-[11px] font-medium text-slate-400"></span>
                        </div>
                        <div id="notif-list" class="max-h-[60vh] overflow-y-auto divide-y divide-slate-100"></div>
                    </div>
                </div>
                {{-- Profil --}}
                @php $namaTampil = optional(Auth::user()->karyawan)->nama_karyawan ?? Auth::user()->username; @endphp
                <div class="relative sm:block" id="profil-menu-wrap">
                    <button type="button" onclick="toggleProfilMenu()" class="flex items-center gap-2 pl-1.5 pr-2.5 h-10 rounded-lg border border-slate-200 bg-white hover:border-slate-300 transition-all" aria-haspopup="true" aria-expanded="false" id="profil-menu-btn" title="Profil &amp; Keluar">
                        @if(Auth::user()->foto_profil)
                            <img id="profil-pill-avatar" src="{{ url('/storage/profil/' . Auth::user()->foto_profil) }}" class="w-8 h-8 rounded-md object-cover shrink-0" alt="Foto profil">
                        @else
                            <div id="profil-pill-avatar" class="w-8 h-8 rounded-md flex items-center justify-center font-semibold text-[11px] shrink-0" style="background:#E3EBFF;color:#1D4ED8">{{ strtoupper(substr($namaTampil, 0, 1)) }}</div>
                        @endif
                        <span class="text-[12px] font-medium text-slate-700 max-w-[9rem] truncate hidden xs:inline sm:inline">{{ $namaTampil }}</span>
                        <i class="fas fa-chevron-down text-[9px] text-slate-400"></i>
                    </button>
                    <div id="profil-menu-panel" class="hidden absolute right-0 mt-2 w-64 rounded-xl bg-white border border-slate-200 z-50 overflow-hidden" style="box-shadow:0 18px 44px rgba(15,30,62,.14)">
                        <div class="px-4 py-4 border-b border-slate-100 flex items-center gap-3">
                            <div class="relative shrink-0">
                                @if(Auth::user()->foto_profil)
                                    <img id="profil-avatar-img" src="{{ url('/storage/profil/' . Auth::user()->foto_profil) }}" alt="Foto profil" class="w-12 h-12 rounded-md object-cover">
                                @else
                                    <div id="profil-avatar-inisial" class="w-12 h-12 rounded-md flex items-center justify-center font-semibold text-base" style="background:#E3EBFF;color:#1D4ED8">{{ strtoupper(substr($namaTampil, 0, 1)) }}</div>
                                    <img id="profil-avatar-img" src="" alt="Foto profil" class="w-12 h-12 rounded-md object-cover hidden">
                                @endif
                                <label for="profil-foto-input" class="absolute -bottom-1 -right-1 w-6 h-6 rounded-full flex items-center justify-center text-[10px] cursor-pointer text-white" style="background:#2563EB" title="Ganti foto">
                                    <i class="fas fa-camera"></i>
                                </label>
                                <input type="file" id="profil-foto-input" accept="image/jpeg,image/png,image/webp" class="hidden">
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-[12px] font-semibold text-slate-800 truncate">{{ $namaTampil }}</div>
                                <div class="text-[9.5px] font-medium text-slate-400 uppercase tracking-wide truncate">{{ optional(Auth::user()->role)->nama_role }}</div>
                                <div id="profil-foto-status" class="text-[9.5px] text-slate-400 mt-1">Klik ikon kamera untuk ganti foto.</div>
                            </div>
                        </div>
                        <form action="{{ url('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-3 px-4 py-3 text-[13px] text-rose-700 hover:bg-rose-50 transition-colors">
                                <i class="fas fa-sign-out-alt w-4"></i> Keluar Sistem
                            </button>
                        </form>
                    </div>
                    <script>
                    (function () {
                        var input = document.getElementById('profil-foto-input');
                        var status = document.getElementById('profil-foto-status');
                        if (!input) return;
                        input.addEventListener('change', function () {
                            var file = input.files && input.files[0];
                            if (!file) return;
                            var tipeOk = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'].indexOf(file.type) !== -1;
                            if (file.size > 2 * 1024 * 1024) { status.textContent = 'Foto terlalu besar (' + Math.round(file.size / 1024 / 1024 * 10) / 10 + ' MB). Maksimal 2 MB.'; return; }
                            if (!tipeOk) { status.textContent = 'Format foto harus JPG, PNG, atau WebP.'; return; }
                            var fd = new FormData();
                            fd.append('foto', file);
                            fd.append('_token', (document.querySelector('meta[name="csrf-token"]') || {}).content || '');
                            status.textContent = 'Mengunggah...';
                            if (!fd.get('_token')) {
                                var t = document.querySelector('input[name="_token"]');
                                if (t) fd.set('_token', t.value);
                            }
                            fetch('{{ route("profil.foto.upload") }}', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                                .then(function (r) {
                                    if (r.ok) return r.json();
                                    if (r.status === 419 || r.status === 401) { throw new Error('Sesi berakhir. Muat ulang halaman (F5), masuk kembali, lalu coba lagi.'); }
                                    return r.json().then(function (d) {
                                        var pesan = (d && d.errors && d.errors.foto && d.errors.foto[0]) || (d && d.message);
                                        if (!pesan) pesan = 'Upload gagal (kode ' + r.status + ').';
                                        throw new Error(pesan);
                                    }, function () {
                                        throw new Error('Upload gagal (kode ' + r.status + '). Foto harus format jpg/png/webp, ukuran maksimal 2 MB.');
                                    });
                                })
                                .then(function (d) {
                                    if (!d || !d.ok) { status.textContent = (d && d.message) ? d.message : 'Upload tidak berhasil. Coba lagi atau muat ulang halaman.'; return; }
                                    var img = document.getElementById('profil-avatar-img');
                                    var ini = document.getElementById('profil-avatar-inisial');
                                    if (img) { img.src = d.url + '?t=' + Date.now(); img.classList.remove('hidden'); }
                                    if (ini) ini.classList.add('hidden');
                                    var pillAvatar = document.getElementById('profil-pill-avatar');
                                    if (pillAvatar) {
                                        if (pillAvatar.tagName === 'DIV') {
                                            var img2 = document.createElement('img');
                                            img2.id = 'profil-pill-avatar';
                                            img2.className = 'w-8 h-8 rounded-md object-cover shrink-0';
                                            img2.alt = 'Foto profil';
                                            img2.src = d.url + '?t=' + Date.now();
                                            pillAvatar.parentNode.replaceChild(img2, pillAvatar);
                                        } else {
                                            pillAvatar.src = d.url + '?t=' + Date.now();
                                        }
                                    }
                                    status.textContent = 'Foto diperbarui.';
                                    input.value = '';
                                })
                                .catch(function (e) { status.textContent = (e && e.message) ? e.message : 'Upload tidak berhasil. Periksa koneksi lalu coba lagi.'; });
                        });
                    })();
                    </script>
                </div>
            </div>
        </div>

        <div class="pro-bar2" role="tablist" aria-label="Modul">
            @php
                // Tab modul kini bisa diatur dari Pengaturan → Topbar (label, urutan,
                // url, ikon, tampil/sembunyi). Kalau tabel topbar_tabs kosong,
                // TopbarTab::aktif() mengembalikan bawaan — tampilan identik
                // sebelum fitur ini ada.
                $proTabs = \App\Models\TopbarTab::aktif();
                foreach ($proTabs as &$__tab) {
                    $__tab['aktif_baris'] = \Illuminate\Support\Facades\Request::is(
                        ltrim($__tab['url'], '/'),
                        ltrim($__tab['url'], '/') . '/*'
                    );
                }
                unset($__tab);
            @endphp
            @foreach($proTabs as $tab)
                <a href="{{ url($tab['url']) }}" role="tab" aria-selected="{{ $tab['aktif_baris'] ? 'true' : 'false' }}" class="pro-tab {{ $tab['aktif_baris'] ? 'aktif' : '' }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $tab['icon'] }}"/></svg>
                    {{ $tab['label'] }}
                </a>
            @endforeach
            <span class="pro-live"><i class="pro-live-dot" aria-hidden="true"></i>Data langsung</span>
            <button type="button" class="pro-menu-btn" id="menuPanelBtn" onclick="toggleMenuPanel()" aria-expanded="false" aria-controls="proMega mainSidebar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                Semua Menu
            </button>
        </div>
    </header>
    @include('partials.command-palette')

    <div class="content-wrapper">
         @yield('content')
    </div>

    @php
        $navItems = [
            ['url' => url('/dashboard'), 'label' => 'Beranda', 'icon' => 'home', 'active' => Request::is('dashboard', 'dashboard/*'), 'boleh' => true],
            ['url' => route('penjualan.create'), 'label' => 'Transaksi', 'icon' => 'transaction', 'active' => request()->routeIs('penjualan.create'), 'boleh' => \App\Support\JalurLanjut::boleh(route('penjualan.create'))],
            ['url' => url('/bahan-baku'), 'label' => 'Master', 'icon' => 'master', 'active' => Request::is('bahan-baku', 'bahan-baku/*'), 'boleh' => \App\Support\JalurLanjut::boleh(url('/bahan-baku'))],
        ];
    @endphp
    <nav class="mobile-bottom-nav" aria-label="Navigasi bawah">
        @foreach($navItems as $item)
            @if($item['boleh'])
                <a href="{{ $item['url'] }}" class="mobile-nav-item {{ $item['active'] ? 'mobile-nav-active' : '' }}" aria-current="{{ $item['active'] ? 'page' : 'false' }}">
                    @if($item['icon'] === 'home')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m3 10 9-7 9 7"></path><path d="M5 9v12h14V9M9 21v-7h6v7"></path>
                        </svg>
                    @elseif($item['icon'] === 'transaction')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 10h16v10H4zM6 10V6h12v4M8 6V4h8v2"></path><path d="M8 14h.01M12 14h.01M16 14h.01M8 17h8"></path>
                        </svg>
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m12 3 9 5-9 5-9-5 9-5Z"></path><path d="m3 8 9 5 9-5M3 8v9l9 5 9-5V8M12 13v9"></path>
                        </svg>
                    @endif
                    <span class="mobile-nav-label">{{ $item['label'] }}</span>
                </a>
            @endif
        @endforeach
        {{-- Lainnya: perilakunya benar-benar "menu lainnya" - membuka drawer
             menu samping yang SUDAH ADA (sidebar dengan izin Blade sendiri).
             Tanpa rute baru, tanpa permission baru, tanpa salinan menu. --}}
        <button type="button" class="mobile-nav-item" onclick="toggleMobileMenu()" aria-haspopup="dialog" aria-controls="mainSidebar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="5" cy="12" r="1"></circle><circle cx="12" cy="12" r="1"></circle><circle cx="19" cy="12" r="1"></circle>
            </svg>
            <span class="mobile-nav-label">Lainnya</span>
        </button>
    </nav>
    @php $renderMs = round((microtime(true) - LARAVEL_START) * 1000); @endphp
    <footer class="pro-footer"><span>Dikembangkan oleh <a href="http://www.obiyshinichiart.com" target="_blank" rel="noopener noreferrer">www.obiyshinichiart.com</a></span><span class="render-ms">Render {{ $renderMs }} ms</span></footer>

<script>
/* Fade out sebelum navigasi antarhalaman (bukan form submit / bukan aksi JS).
   Hanya navigasi GET same-origin; modifier keys & download & hash dihormati;
   prefers-reduced-motion dihormati; jika JS gagal, tautan jalan normal. */
(function () {
    var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce) return;
    var navTimer = null;
    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0) return;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        var a = e.target.closest('a[href]');
        if (!a) return;
        var url;
        try { url = new URL(a.href, location.href); } catch (_) { return; }
        if (url.origin !== location.origin) return;
        if (url.hash && url.pathname === location.pathname) return;
        if (a.target && a.target !== '_self') return;
        if (a.hasAttribute('download')) return;
        if (a.hasAttribute('data-no-fade')) return;
        if (/\.(pdf|png|jpe?g|webp|gif|zip|xlsx?|docx?|pptx?)$/i.test(url.pathname)) return;
        e.preventDefault();
        document.body.classList.add('pv2-leaving');
        clearTimeout(navTimer);
        navTimer = window.setTimeout(function () { location.href = url.href; }, 180);
    }, true);
    // Kalau pengguna menekan back/forward sebelum 180ms habis, batalkan pindah halaman.
    window.addEventListener('pageshow', function (e) {
        if (e.persisted && navTimer) { clearTimeout(navTimer); navTimer = null; }
    });
})();
    // Saat halaman dibuka lagi dari tombol kembali/maju (bfcache), pastikan
    // body tidak menyisakan kelas fade-keluar dari navigasi sebelumnya -
    // kalau menyisakan, isi halaman tetap opacity 0 dan tampak KOSONG.
    // Sekalian tutup SEMUA panel yang mungkin sedang terbuka (mega menu,
    // drawer, overlay, notifikasi, profil) - tanpa ini, menu yang sedang
    // terbuka ikut "tersimpan" di bfcache dan muncul lagi setelah kembali.
    window.addEventListener('pageshow', function () {
        document.body.classList.remove('pv2-leaving');
        ['proMega', 'mainSidebar'].forEach(function (id) {
            var p = document.getElementById(id);
            if (p) { p.classList.remove('terbuka'); p.setAttribute('aria-hidden', 'true'); }
        });
        var ov = document.getElementById('sidebarOverlay');
        if (ov) { ov.classList.remove('terbuka'); ov.setAttribute('aria-hidden', 'true'); }
        var np = document.getElementById('notif-panel');
        if (np) np.classList.add('hidden');
        var pp = document.getElementById('profil-menu-panel');
        if (pp) pp.classList.add('hidden');
        var mb = document.getElementById('menuPanelBtn');
        if (mb) mb.setAttribute('aria-expanded', 'false');
        var pb = document.getElementById('profil-menu-btn');
        if (pb) pb.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('menu-panel-open');
    });
</script>
</main>
    </div>
    {{-- jQuery dan Select2 dilayani sendiri (lihat catatan aset di <head>).
         Keduanya tidak bisa dipindah ke <head> dengan sembarangan: Select2
         mencari jQuery di window saat dimuat, dan banyak initialization
         di halaman ini berjalan sebagai script inline yang ikut dieksekusi
         berurutan di blok ini. Memindahkan tagnya saja tanpa menggeser
         urutan pemanggilnya akan membuat .select2() dipanggil sebelum
         pustakanya ada. --}}
    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/select2/select2.min.js') }}"></script>
    <script>
        // Refresh opsi select2 saat dibuka - opsi umumnya dirender server
        // sekali saat halaman dimuat, jadi data yang baru ditambah di halaman
        // lain (konsumen, bahan, supplier, dst) tidak muncul tanpa reload.
        // Cara kerja: saat select2 dibuka, ambil ulang halaman yang sama
        // (satu fetch dibagi semua select, di-throttle 4 detik), cari
        // <select> yang sama lewat id/name, dan kalau daftar opsinya
        // berbeda, ganti opsi (pilihan terpilih dipertahankan) lalu buka
        // ulang dropdown. Lewati select yang opsinya dibangun client
        // (ajax/data-no-refresh) atau tidak ditemukan di halaman segar.
        (function () {
            let lastFetch = 0, pending = null, refreshing = false;

            function fetchFreshDoc() {
                const now = Date.now();
                if (pending && now - lastFetch < 4000) return pending;
                lastFetch = now;
                pending = fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then(r => r.ok ? r.text() : null)
                    .then(html => html ? new DOMParser().parseFromString(html, 'text/html') : null)
                    .catch(() => null);
                return pending;
            }

            function findFresh(doc, el) {
                if (el.id) return doc.getElementById(el.id);
                if (el.name) return doc.querySelector('select[name="' + el.name.replace(/"/g, '\\"') + '"]');
                return null;
            }

            $(document).on('select2:opening', 'select', function () {
                if (refreshing || this.hasAttribute('data-no-refresh') || $(this).data('select2')?.options?.options?.ajax) return;
                const el = this, $el = $(this);
                if (!el.id && !el.name) return;

                fetchFreshDoc().then(function (doc) {
                    if (!doc) return;
                    const fresh = findFresh(doc, el);
                    if (!fresh) return;

                    const sig = s => Array.from(s.options).map(o => o.value + '|' + o.textContent.trim()).join('\n');
                    if (sig(fresh) === sig(el)) return;

                    const selected = $el.val();
                    const kataCari = ($el.data('select2') && $el.data('select2').dropdown && $el.data('select2').dropdown.$search)
                        ? $el.data('select2').dropdown.$search.val() : '';
                    el.innerHTML = fresh.innerHTML;
                    $el.val(selected);
                    refreshing = true;
                    $el.trigger('change.select2');
                    if ($el.data('select2') && $el.data('select2').isOpen()) {
                        $el.select2('close');
                        $el.select2('open');
                        // Kembalikan kata yang sudah diketik user: dropdown dibuka
                        // ulang, jadi teks pencariannya ikut hilang. Tanpa ini
                        // user harus mengetik ulang dari awal.
                        const cari = $el.data('select2').dropdown && $el.data('select2').dropdown.$search;
                        if (cari && kataCari) {
                            $(cari).val(kataCari).trigger('input.select2');
                            cari.focus();
                        }
                    }
                    refreshing = false;
                });
            });
        })();
    </script>
    <script>
        // Select2: fokus langsung ke kotak pencarian, supaya begitu dropdown
        // terbuka user bisa langsung mengetik tanpa klik tambahan.
        //
        // Dua pemicu:
        // (1) select2:open  -> dropdown baru saja dibuka.
        // (2) focusin di .select2-selection -> fokus mendarat di container
        //     select2 (bukan di <select>). Ini yang terjadi saat user menekan
        //     Tab. Tanpa itu, perpindahan field berhenti di kotak select2 dan
        //     user harus klik dulu sebelum bisa mengetik.
        //
        // Input pencarian select2 tidak selalu hidup: saat dropdown dibuka ulang
        // karena daftar opsi di-refresh, elemen input yang lama dilepas dan yang
        // baru dibuat. Karena itu pencarian dilakukan di DOM (input yang
        // terlihat), bukan lewat instance select2.
        if (window.jQuery) {
            // Input pencarian select2 baru ada di DOM setelah dropdown selesai
            // dirender, jadi satu percobaan sering terlambat. Fokus dicari
            // beberapa kali dengan jeda singkat.
            //
            // Fokus TIDAK direbut kalau user sudah pindah ke field lain
            // (mis. menekan Tab/Escape saat menunggu) - itu akan mengubah
            // urutan ketikan yang sedang dikerjakan.
            function fokusPencarian(el) {
                var $f = jQuery('.select2-search__field').filter(function () {
                    return this.offsetParent !== null && !this.disabled;
                });
                if (!$f.length) return false;

                // Fokus TIDAK direbut kalau user sudah pindah ke field lain
                // (mis. menekan Tab/Escape saat menunggu) - itu akan mengubah
                // urutan ketikan yang sedang dikerjakan. Yang boleh hanya
                // fokus yang masih di dalam select2 (container atau dropdown).
                var ae = document.activeElement;
                if (ae && ae !== document.body) {
                    var diSelect2 = (ae.closest && ae.closest('.select2-container')) || (ae.closest && ae.closest('.select2-dropdown'));
                    if (!diSelect2) return false;
                }
                try { $f[0].focus(); return true; } catch (err) { return false; }
            }

            function fokusPencarianTunda(el, sisa) {
                if (fokusPencarian(el)) return;
                if (sisa <= 0) return;
                setTimeout(function () { fokusPencarianTunda(el, sisa - 1); }, 40);
            }

            jQuery(document).on('select2:open', function (e) {
                fokusPencarianTunda(e.target, 6);
            });

            // Penutup dropdown mengembalikan fokus ke container select2 yang
            // sama. Tanpa penanda, fokus balik itu akan langsung membuka lagi
            // dropdownnya. Penanda disimpan per select, jadi select2 BERIKUTNYA
            // tetap bisa dibuka otomatis saat fokus berpindah ke sana.
            var baruTutup = new WeakMap();
            jQuery(document).on('select2:close', 'select', function () {
                baruTutup.set(this, Date.now());
            });

            document.addEventListener('focusin', function (e) {
                var t = e.target;
                if (!t || !t.classList || !t.classList.contains('select2-selection')) return;
                var container = t.closest('.select2-container');
                if (!container) return;
                var sel = container.previousElementSibling;
                if (!sel || sel.tagName !== 'SELECT') return;
                var $el = jQuery(sel);
                if (!$el.data('select2') || sel.disabled) return;
                if ($el.closest('[data-no-autofocus]').length) return;
                var tutup = baruTutup.get(sel);
                if (tutup && (Date.now() - tutup) < 400) return; // fokus balik dari menutup -> jangan buka lagi
                var inst = $el.data('select2');
                if (inst && typeof inst.isOpen === 'function' && inst.isOpen()) {
                    // Dropdown-nya sudah terbuka (mis. dibuka lalu difokuskan
                    // kembali) -> cukup arahkan fokus ke kotak pencarian.
                    fokusPencarianTunda(sel, 6);
                    return;
                }
                setTimeout(function () {
                    try { if (!$el.data('select2').isOpen()) $el.select2('open'); } catch (err) {}
                }, 0);
            });

            if (!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches)) {
                jQuery(document).on('focus', 'select', function () {
                    var $el = jQuery(this);
                    if (!$el.data('select2') || this.disabled) return;
                    if ($el.closest('[data-no-autofocus]').length) return;
                    var inst = $el.data('select2');
                    if (inst && typeof inst.isOpen === 'function' && inst.isOpen()) return;
                    setTimeout(function () {
                        try { if (!$el.data('select2').isOpen()) $el.select2('open'); } catch (e) {}
                    }, 0);
                });
            }
        }
    </script>
    @include('partials.rupiah-format-script')
    @include('partials.quick-picker')
    @include('partials.tooltip-script')
    <script>
        // Filter konsisten di SELURUH aplikasi: memilih nilai pada dropdown di dalam form GET (filter/pencarian)
        // langsung menerapkan filter - tidak perlu klik tombol atau memilih dua kali. Termasuk dropdown select2
        // (memakai event jQuery). Nonaktifkan per form/dropdown dengan atribut data-no-autosubmit.
        $(document).on('change', 'select', function () {
            const form = this.form;
            if (!form || (form.getAttribute('method') || '').toLowerCase() !== 'get') return;
            if (form.hasAttribute('data-no-autosubmit') || this.hasAttribute('data-no-autosubmit')) return;
            if (form.__terkirim) return;
            form.__terkirim = true;
            setTimeout(() => { form.requestSubmit ? form.requestSubmit() : form.submit(); }, 0);
        });
    </script>
    <script>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });

        @if(session('success'))
            @php $lanjut = session('lanjut'); @endphp
            @if($lanjut)
                {{-- Jalan pintas: beri tahu ke mana proses berlanjut setelah aksi ini. --}}
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success', timer: 9000, timerProgressBar: true,
                    title: @json(session('success')),
                    text: @json($lanjut['teks'] ?? ''),
                    showConfirmButton: {{ !empty($lanjut['url']) ? 'true' : 'false' }},
                    confirmButtonText: @json($lanjut['tombol'] ?? 'Lanjut'),
                    confirmButtonColor: '#2563EB', showCloseButton: true
                }).then(r => { if (r.isConfirmed) window.location.href = @json($lanjut['url'] ?? '#'); });
            @else
                Toast.fire({ icon: 'success', title: "{{ session('success') }}" });
            @endif
        @endif
        @if(session('error'))
            Swal.fire({ icon: 'error', title: 'Tidak berhasil', text: @json(session('error')), customClass: { popup: 'rounded-2xl' } });
        @endif
        @if(session('warning'))
            {{-- Ditampilkan setelah toast sukses (bila ada) supaya peringatannya tidak tertutup. --}}
            setTimeout(() => Swal.fire({ icon: 'warning', title: 'Perhatian', text: @json(session('warning')), customClass: { popup: 'rounded-2xl' } }), {{ session('success') ? 600 : 0 }});
        @endif
        @if($errors->any())
            {{-- Validation error: tampilkan pesan pertama agar operator tahu kolom mana yang kurang / salah.
                Sebelumnya error ini disimpan di $errors tapi tidak pernah dirender, jadi operator melihat
                "tidak terjadi apa-apa" padahal validasi menolak. --}}
            {{-- Sekitar belasan halaman (jersey, kas kecil, PO, quotation, dst) sudah merender ringkasan
                $errors sendiri di atas form. Popup hanya dimunculkan bila pesan itu belum terlihat di
                halaman, supaya error tidak tampil dobel. --}}
            (function () {
                const pesan = @json($errors->first());
                if (document.body.innerText.indexOf(pesan) !== -1) return;
                Swal.fire({ icon: 'error', title: 'Periksa kembali isian', text: pesan, customClass: { popup: 'rounded-2xl' } });
            })();
        @endif
    </script>
    <script>
    let currentTransactionCount = null;

    function checkTransactions() {
        let gagalPolling = 0;
        fetch("{{ url('/kasir/check-pembayaran') }}", { headers: { 'Accept': 'application/json' } })
            .then(async response => {
                const tipe = response.headers.get('Content-Type') || '';
                if (!response.ok || !tipe.includes('application/json')) {
                    // Role tanpa akses (403 HTML) / server error -> berhenti
                    // polling, jangan spam toast/error di console tiap 10 detik.
                    throw new Error('skip-polling');
                }
                return response.json();
            })
            .then(data => {
                const badge = document.getElementById('badge-notif');
                if(data.total > 0) {
                    badge.innerText = data.total;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
                // Jika ini pengecekan pertama, set jumlah awal
                if (currentTransactionCount === null) {
                    currentTransactionCount = data.total;
                    return;
                }

                // Jika jumlah transaksi bertambah dari sebelumnya
                if (data.total > currentTransactionCount) {
                    currentTransactionCount = data.total;
                    
                    // Mainkan suara notifikasi (Opsional)
                    // let audio = new Audio('https://assets.mixkit.co/sfx/preview/mixkit-software-interface-start-2574.mp3');
                    // audio.play();

                    Swal.fire({
                        title: 'NOTA BARU!',
                        text: `Ada ${data.total} transaksi menunggu divalidasi.`,
                        icon: 'info',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: true,
                        confirmButtonText: 'Lihat List',
                        confirmButtonColor: '#2563EB',
                        timer: 10000,
                        timerProgressBar: true,
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = "{{ route('kasir.index') }}";
                        }
                    });
                } else {
                    currentTransactionCount = data.total;
                }
            })
            .catch(error => {
                if (error && error.message === 'skip-polling') {
                    // Stop polling selamanya; cek is kasir saat halaman dimuat ulang akan mengecek ulang.
                    clearInterval(checkIntervalId);
                    return;
                }
                console.warn('Periksa pembayaran gagal:', (error && error.message) || error);
            });
    }

    // Jalankan pengecekan setiap 10 detik - HANYA untuk role yang benar-benar
    // punya akses ke endpoint-nya sendiri. /kasir/check-pembayaran ada di
    // bawah prefix "kasir" yang di config/menu_access.php dijaga id_menu 16
    // (BUKAN 3/41 - itu gate untuk prefix "penjualan"/Input Pesanan, beda
    // modul). Sebelumnya jalan untuk SEMUA user login tanpa
    // syarat, jadi role tanpa id_menu 16 (termasuk Operator Desain yang
    // cuma punya akses input-order) tetap polling endpoint ini tiap 10
    // detik selamanya dan SELALU dapat 403 (dijaga CheckMenuAccess) -
    // percuma membebani server & memenuhi console dengan error "Unexpected
    // token '<'" (403 balikannya HTML, bukan JSON).
    @if(auth()->check() && $cekAkses(16))
        const checkIntervalId = setInterval(checkTransactions, 10000);
    @endif
    </script>
    <script>
    // Alur input cepat: (1) saat halaman/modal form dibuka, kursor langsung ke isian pertama;
    // (2) setelah memilih satu opsi dropdown, kursor pindah ke isian berikutnya. Dinonaktifkan di layar sentuh
    // (agar keyboard tidak muncul sendiri) dan bisa dimatikan per elemen/form dengan atribut data-no-autofocus.
    (function () {
        if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) return;

        const bisaDifokus = el => el && !el.disabled && !el.readOnly && el.type !== 'hidden' && el.type !== 'file'
            && !el.hasAttribute('data-no-autofocus') && !el.closest('[data-no-autofocus]') && !el.closest('.hidden')
            && (el.offsetParent !== null || el.tagName === 'SELECT') && el.getClientRects().length > 0;
        const isian = 'input:not([type=hidden]):not([type=checkbox]):not([type=radio]):not([type=submit]):not([type=button]):not([type=file]), textarea, select';

        function fokus(el) {
            if (!el) return;
            if (el.tagName === 'SELECT' && window.jQuery && jQuery(el).data('select2')) { jQuery(el).select2('open'); return; }
            el.focus({ preventScroll: false });
            if (/^(text|number|search|tel|email)$/.test(el.type || '') && el.value !== '') { try { el.select(); } catch (e) {} }
        }
        function daftar(akar) { return [...akar.querySelectorAll(isian)].filter(bisaDifokus); }

        // (1) Kursor ke isian pertama: form data (bukan filter GET) pertama di halaman.
        function fokusAwal(akar, formData) {
            const form = formData
                ? [...akar.querySelectorAll('form')].find(f => (f.getAttribute('method') || 'get').toLowerCase() !== 'get' && daftar(f).length)
                : akar;
            if (!form) return;
            const first = daftar(form)[0];
            if (first && first.tagName !== 'SELECT') fokus(first);
            else if (first) fokus(first);
        }
        window.addEventListener('load', () => {
            if (document.querySelector('[autofocus]') || document.activeElement?.matches?.(isian)) return;
            setTimeout(() => fokusAwal(document.querySelector('.content-wrapper') || document, true), 150);
        });

        // Modal yang baru terbuka (kelas 'hidden' dilepas) -> isian pertama di dalamnya.
        new MutationObserver(muts => muts.forEach(m => {
            const el = m.target;
            if (m.attributeName !== 'class' || !(el instanceof HTMLElement)) return;
            const sebelum = (m.oldValue || '').split(/\s+/).includes('hidden');
            if (sebelum && !el.classList.contains('hidden') && (el.id?.startsWith('modal-') || /\bfixed\b/.test(el.className) && /inset-0/.test(el.className))) {
                setTimeout(() => { const f = daftar(el)[0]; if (f) fokus(f); }, 80);
            }
        })).observe(document.body, { attributes: true, attributeOldValue: true, subtree: true, attributeFilter: ['class'] });

        // (2) Pindah ke isian berikutnya setelah memilih dropdown.
        function berikutnya(sel) {
            const form = sel.closest('form') || sel.closest('.content-wrapper') || document;
            if ((form.getAttribute?.('method') || 'post').toLowerCase() === 'get') return null; // filter GET: langsung memuat ulang
            const semua = daftar(form.tagName ? form : document);
            const i = semua.indexOf(sel);
            for (let k = i + 1; k < semua.length; k++) {
                const el = semua[k];
                if (el.closest('.select2-container')) continue;
                return el;
            }
            return null;
        }
        // select2 (dropdown dengan pencarian): 'select2:select' hanya menyala saat pengguna memilih.
        if (window.jQuery) {
            jQuery(document).on('select2:select', 'select', function () {
                if (this.hasAttribute('data-no-advance') || this.closest('[data-no-autofocus]')) return;
                const nx = berikutnya(this); if (nx) setTimeout(() => fokus(nx), 30);
            });
        }
        // Dropdown biasa: hanya bila dipilih dengan mouse (panah keyboard tidak melompat).
        let cara = new WeakMap();
        document.addEventListener('pointerdown', e => { if (e.target.tagName === 'SELECT') cara.set(e.target, 'pointer'); }, true);
        document.addEventListener('keydown', e => { if (e.target.tagName === 'SELECT' && !['Tab', 'Enter'].includes(e.key)) cara.set(e.target, 'key'); }, true);
        document.addEventListener('change', e => {
            const el = e.target;
            if (el.tagName !== 'SELECT' || el.hasAttribute('data-no-advance') || el.closest('[data-no-autofocus]')) return;
            if (window.jQuery && jQuery(el).data('select2')) return; // ditangani select2:select
            if (cara.get(el) !== 'pointer') return;
            const nx = berikutnya(el); if (nx) setTimeout(() => fokus(nx), 30);
        });
    })();
    </script>
    <script>
    // Notifikasi: lonceng di topbar + badge angka di menu sidebar (per role, dari /sinyal).
    function renderNotif(d) {
        const items = d.items || [];
        const tot = document.getElementById('notif-total');
        if (tot) { tot.textContent = d.total > 99 ? '99+' : d.total; tot.classList.toggle('hidden', !(d.total > 0)); tot.classList.toggle('inline-flex', d.total > 0); }
        const tt = document.getElementById('notif-total-teks');
        if (tt) tt.textContent = d.total > 0 ? d.total + ' tugas' : '';
        const warna = { rose: 'bg-rose-500', amber: 'bg-amber-500', indigo: 'bg-indigo-500' }; // sisa pemetaan lama; warÂ­na badge kini dari token CSS
        void warna;
        const list = document.getElementById('notif-list');
        if (list) {
            list.innerHTML = items.length ? items.map(i => `<a href="${i.url}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-all">
                <span class="notif-badge ${i.tone}" style="margin-left:0">${i.count}</span>
                <span class="text-[13px] font-medium text-slate-600 leading-snug">${i.label}</span></a>`).join('')
                : '<div class="px-4 py-8 text-center text-sm text-slate-400"><i class="fas fa-check-circle text-emerald-400 text-2xl mb-2 block"></i>Tidak ada pekerjaan yang menunggu.</div>';
        }
        // Badge di menu sidebar: cocokkan lewat alamat tujuan (gabungkan bila beberapa notifikasi ke halaman yang sama).
        document.querySelectorAll('#mainSidebar .notif-badge').forEach(el => el.remove());
        const perUrl = {};
        items.forEach(i => { const k = new URL(i.url, location.origin).pathname; (perUrl[k] = perUrl[k] || { n: 0, tone: i.tone }).n += i.count; if (i.tone === 'rose') perUrl[k].tone = 'rose'; });
        document.querySelectorAll('#mainSidebar a[href]').forEach(a => {
            let k; try { k = new URL(a.href, location.origin).pathname; } catch (e) { return; }
            if (perUrl[k]) { const b = document.createElement('span'); b.className = 'notif-badge ' + perUrl[k].tone; b.textContent = perUrl[k].n > 99 ? '99+' : perUrl[k].n; a.appendChild(b); }
        });
        badgeGrup();
        syncProMegaBadges();
    }
    // #proMega (mega menu desktop) dibangun SEKALI dari #mainSidebar, jadi
    // badge di dalamnya tidak ikut berubah saat polling /sinyal. Sinkronkan
    // ulang: hapus badge panel, kloning badge sidebar terbaru per href,
    // lalu jumlahkan ulang badge judul grup.
    function syncProMegaBadges() {
        if (!window.__proMegaBuilt) return;
        var grid = document.getElementById('proMegaGrid');
        if (!grid) return;
        grid.querySelectorAll('.notif-badge').forEach(function (b) { b.remove(); });
        var sidebarLinks = Array.prototype.slice.call(document.querySelectorAll('#mainSidebar a[href]'));
        var peta = {};
        sidebarLinks.forEach(function (a) {
            var k; try { k = new URL(a.href, location.origin).pathname; } catch (e) { return; }
            (peta[k] = peta[k] || []).push(a);
        });
        grid.querySelectorAll('.promega-link').forEach(function (link) {
            var k; try { k = new URL(link.href, location.origin).pathname; } catch (e) { return; }
            var src = (peta[k] || []).map(function (a) { return a.querySelector('.notif-badge'); })
                .filter(Boolean)[0];
            if (src) link.appendChild(src.cloneNode(true));
        });
        grid.querySelectorAll('.promega-group').forEach(function (grup) {
            var n = 0;
            grup.querySelectorAll('.promega-link .notif-badge').forEach(function (b) { n += parseInt(b.textContent, 10) || 0; });
            var title = grup.querySelector('.promega-group-title');
            if (!title) return;
            var lama = title.querySelector('.notif-badge'); if (lama) lama.remove();
            if (n > 0) {
                var b = document.createElement('span');
                b.className = 'notif-badge notif-grup indigo';
                b.textContent = n > 99 ? '99+' : n;
                title.appendChild(b);
            }
        });
    }
    // Ringkasan di judul grup menu (berguna saat grup sedang dilipat).
    function badgeGrup() {
        document.querySelectorAll('#mainSidebar .notif-grup').forEach(el => el.remove());
        document.querySelectorAll('#mainSidebar .menu-group-items').forEach(g => {
            const n = [...g.querySelectorAll('.notif-badge')].reduce((t, b) => t + (parseInt(b.textContent) || 0), 0);
            const h = g.previousElementSibling;
            if (n > 0 && h) { const b = document.createElement('span'); b.className = 'notif-badge notif-grup indigo'; b.textContent = n > 99 ? '99+' : n; h.insertBefore(b, h.querySelector('i.fa-chevron-down')); }
        });
    }
    // Role dengan sedikit menu: tampilkan menu langsung tanpa harus membuka grup. Berlaku bila total menu <= 8,
    // atau untuk grup yang isinya cuma satu menu. Administrator (banyak menu) tetap memakai grup lipat.
    function ratakanSidebar() {
        const nav = document.querySelector('#mainSidebar nav'); if (!nav) return;
        const grup = [...nav.querySelectorAll('.menu-group-items')];
        const hitung = g => g.querySelectorAll('a[href]').length;
        const dalamGrup = grup.reduce((t, g) => t + hitung(g), 0);
        const luarGrup = [...nav.querySelectorAll('a[href]')].filter(a => !a.closest('.menu-group-items')).length;
        const sedikit = (dalamGrup + luarGrup) <= 8;
        grup.forEach(g => {
            const h = g.previousElementSibling;
            if (!h || !h.classList.contains('menu-group-toggle')) return;
            if (sedikit || hitung(g) === 1) { g.classList.add('grup-datar'); h.classList.add('grup-datar-header'); }
        });
    }
    // Sidebar semua role memakai grup menu yang sama seperti Administrator (tidak diratakan).
    // Mode gelap / terang
    function terapkanIkonTema() {
        const gelap = document.documentElement.classList.contains('dark');
        const ikon = document.getElementById('tema-ikon'), btn = document.getElementById('tema-toggle');
        if (ikon) ikon.className = 'fas ' + (gelap ? 'fa-sun' : 'fa-moon');
        if (btn) { btn.title = gelap ? 'Ganti ke mode terang' : 'Ganti ke mode gelap'; }
    }
    function ubahTema() {
        const gelap = document.documentElement.classList.toggle('dark');
        try { localStorage.setItem('tema', gelap ? 'dark' : 'light'); } catch (e) {}
        terapkanIkonTema();
    }

    // Garis tipis card: pilihan user (localStorage 'garisCard', default tampil).
    // data-card-lines="off" menjadi tanda anchor untuk aturan CSS di app.css.
    function terapkanGarisCard() {
        let off = false;
        try { off = localStorage.getItem('garisCard') === 'off'; } catch (e) {}
        document.documentElement.setAttribute('data-card-lines', off ? 'off' : 'on');
        const ikon = document.getElementById('gariscard-ikon');
        if (ikon) ikon.className = 'fas ' + (off ? 'fa-border-none' : 'fa-border-all');
        const btn = document.getElementById('gariscard-toggle');
        if (btn) btn.title = off ? 'Tampilkan garis card' : 'Sembunyikan garis card (tampilan lebih bersih)';
    }
    function ubahGarisCard() {
        let off = false;
        try { off = localStorage.getItem('garisCard') === 'off'; } catch (e) {}
        try { localStorage.setItem('garisCard', off ? 'on' : 'off'); } catch (e) {}
        terapkanGarisCard();
    }

    // Topbar mobile: buka/tutup panel "lainnya". Panel ditutup otomatis
    // kalau pengguna mengklik di luar panel, supaya tidak tertinggal
    // terbuka saat layar diputar atau digulir.
    function toggleTopbarMobile() {
        const panel = document.getElementById('topbar-mobile-isi');
        const tombol = document.getElementById('topbar-mobile-btn');
        if (!panel) return;
        const terbuka = panel.classList.toggle('terbuka');
        if (tombol) tombol.setAttribute('aria-expanded', String(terbuka));
    }

    document.addEventListener('click', function (e) {
        const panel = document.getElementById('topbar-mobile-isi');
        if (!panel || !panel.classList.contains('terbuka')) return;
        if (panel.contains(e.target)) return;
        if (e.target.closest('#topbar-mobile-btn')) return;
        panel.classList.remove('terbuka');
    });
    terapkanIkonTema();
    terapkanGarisCard();
    // Cetak selalu terang.
    (function () {
        let tadiGelap = false;
        window.addEventListener('beforeprint', () => { tadiGelap = document.documentElement.classList.contains('dark'); document.documentElement.classList.remove('dark'); });
        window.addEventListener('afterprint', () => { if (tadiGelap) document.documentElement.classList.add('dark'); });
    })();
    function toggleNotif(e) { if (e) e.stopPropagation(); document.getElementById('notif-panel').classList.toggle('hidden'); }
    document.addEventListener('click', e => { const p = document.getElementById('notif-panel'); if (p && !p.contains(e.target)) p.classList.add('hidden'); });

    // Menu profil (foto + Keluar). Sebelumnya onclick memanggil fungsi yang
    // tidak pernah didefinisikan, jadi panel tidak pernah terbuka dan tombol
    // Keluar tidak bisa diakses.
    function toggleProfilMenu(e) {
        if (e) e.stopPropagation();
        var p = document.getElementById('profil-menu-panel');
        var btn = document.getElementById('profil-menu-btn');
        if (!p) return;
        var buka = p.classList.contains('hidden');
        p.classList.toggle('hidden');
        if (btn) btn.setAttribute('aria-expanded', buka ? 'true' : 'false');
    }
    document.addEventListener('click', function (e) {
        var p = document.getElementById('profil-menu-panel');
        if (p && !p.contains(e.target) && !e.target.closest('#profil-menu-btn')) {
            p.classList.add('hidden');
            var btn = document.getElementById('profil-menu-btn'); if (btn) btn.setAttribute('aria-expanded', 'false');
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var p = document.getElementById('profil-menu-panel');
        if (p && !p.classList.contains('hidden')) {
            p.classList.add('hidden');
            var btn = document.getElementById('profil-menu-btn'); if (btn) btn.setAttribute('aria-expanded', 'false');
        }
    });
    renderNotif(@json($notifAwal));
    </script>
    <script>
    // Refresh otomatis ringan: cek "sidik jari" perubahan data tiap 30 detik, HANYA saat tab terlihat
    // (tab latar tidak membebani server). Bila berubah: halaman daftar-pantau (daftar putih) yang idle dimuat ulang sendiri; bila user
    // sedang mengetik / modal terbuka / halaman form, cukup munculkan tombol "Muat ulang".
    (function () {
        let basis = null, sibuk = false, kotor = false, jeda = 30000, timer = null;
        // DAFTAR PUTIH: hanya halaman pantau/daftar (tanpa input) yang boleh dimuat ulang otomatis.
        // Semua halaman lain (input nota, jersey, kasir bayar, master, dst) TIDAK PERNAH di-reload
        // otomatis - hanya menampilkan tombol - agar keranjang/isian tidak hilang.
        const bolehOtomatis = /^\/(dashboard|kasir\/monitoring|spk|produksi|approval)\/?$/.test(location.pathname.replace(/^\/percetakan-app\/public/, ''));
        document.addEventListener('input', () => { kotor = true; }, true);
        document.addEventListener('change', () => { kotor = true; }, true);
        function aman() {
            if (!bolehOtomatis || kotor) return false;
            // Lapis kedua: ada field isian terlihat di form non-GET (mis. modal terbuka) = jangan reload.
            const fieldTerlihat = [...document.querySelectorAll('form:not([method=get]):not([method=GET]) :is(input:not([type=hidden]):not([type=checkbox]),textarea,select)')]
                .some(el => el.offsetParent !== null);
            if (fieldTerlihat) return false;
            if (document.querySelector('.swal2-popup:not(.swal2-toast), dialog[open]')) return false;
            const a = document.activeElement;
            return !(a && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName));
        }
        function pill() {
            if (document.getElementById('pill-segarkan')) return;
            const b = document.createElement('button');
            b.id = 'pill-segarkan'; b.type = 'button';
            b.className = 'fixed bottom-5 left-1/2 -translate-x-1/2 z-[9999] px-5 py-2.5 rounded-full bg-indigo-600 text-white text-sm font-medium shadow-xl hover:bg-indigo-700';
            b.textContent = 'Data diperbarui di tab/pengguna lain. Muat ulang';
            b.onclick = () => location.reload();
            document.body.appendChild(b);
        }
        async function cek() {
            if (document.hidden || sibuk) return;
            sibuk = true;
            try {
                const r = await fetch(@json(url('/sinyal')), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                if (!r.ok) throw new Error(r.status);
                const d = await r.json();
                jeda = 30000;
                if (d.notif) renderNotif(d.notif);
                if (basis === null) basis = d.v;
                else if (d.v !== basis) { aman() ? location.reload() : pill(); }
            } catch (e) { jeda = Math.min(jeda * 2, 300000); }
            finally { sibuk = false; jadwal(); }
        }
        function jadwal() { clearTimeout(timer); timer = setTimeout(cek, jeda); }
        document.addEventListener('visibilitychange', () => { if (!document.hidden) cek(); });
        cek();
    })();
    </script>
    <script>
        // Panel menu ala Canva (#proMega) dibangun dari #mainSidebar supaya
        // RBAC tetap satu sumber kebenaran (palette Ctrl+K & drawer mobile
        // juga membaca dari sana). Dipanggil sekali saja.
        function buildProMega() {
            if (window.__proMegaBuilt) return;
            window.__proMegaBuilt = true;

            var grid = document.getElementById('proMegaGrid');
            var nav = document.querySelector('#mainSidebar nav');
            if (!grid || !nav) return;

            function buatGrup(judul, links, badgeToggle) {
                if (!links.length) return;
                var grup = document.createElement('div');
                grup.className = 'promega-group';
                var h = document.createElement('div');
                h.className = 'promega-group-title';
                h.textContent = judul;
                // Pindahkan badge notifikasi grup (bila ada) - dulu hilang
                // saat panel dirakit ulang di sini.
                if (badgeToggle) {
                    var b = badgeToggle.querySelector('.notif-badge');
                    if (b) { var c = b.cloneNode(true); c.classList.add('notif-grup'); h.appendChild(c); }
                }
                var body = document.createElement('div');
                body.className = 'promega-links';
                links.forEach(function (a) {
                    var c = a.cloneNode(true);
                    c.removeAttribute('class');
                    c.className = 'promega-link' + (a.classList.contains('sb-active') ? ' is-active' : '');
                    c.removeAttribute('title');
                    body.appendChild(c);
                });
                grup.appendChild(h);
                grup.appendChild(body);
                grid.appendChild(grup);
            }

            // Tautan di luar grup (Dashboard, Kelola Postingan, Eksekutif, Audit)
            var luar = [];
            for (var n = nav.firstElementChild; n && !(n.classList && n.classList.contains('menu-group-toggle')); n = n.nextElementSibling) {
                if (n.tagName === 'A') luar.push(n);
            }
            buatGrup('Beranda', luar);

            nav.querySelectorAll('.menu-group-toggle').forEach(function (t) {
                var span = t.querySelector('span');
                var judul = (span ? span.textContent : t.textContent).trim();
                var items = t.nextElementSibling;
                if (!items || !items.classList.contains('menu-group-items')) return;
                var links = Array.prototype.slice.call(items.querySelectorAll('a[href]'));
                buatGrup(judul, links, t);
            });

            // Featured rail: kartu hanya tampil bila rutenya ada di sidebar
            // (hanya ada bila role punya hak akses).
            var rail = document.getElementById('proMegaRail');
            if (rail) {
                rail.querySelectorAll('[data-href-suffix]').forEach(function (card) {
                    var suffix = card.getAttribute('data-href-suffix');
                    if (!nav.querySelector('a[href$="' + suffix + '"]')) card.remove();
                });
                if (!rail.querySelector('.promega-card')) rail.style.display = 'none';
            }
        }

        function toggleMenuPanel() {
            if (window.matchMedia('(min-width: 1024px)').matches) buildProMega();

            const desktop = window.matchMedia('(min-width: 1024px)').matches;
            const utama = document.getElementById(desktop ? 'proMega' : 'mainSidebar');
            const lain  = document.getElementById(desktop ? 'mainSidebar' : 'proMega');
            const overlay = document.getElementById('sidebarOverlay');
            const btn = document.getElementById('menuPanelBtn');

            // Tutup panel mana pun yang sedang terbuka (bukan sekadar toggle
            // menurut breakpoint saat ini), lalu buka panel yang sesuai bila
            // memang belum ada yang terbuka.
            const sedangTerbuka = [utama, lain].find(p => p && p.classList.contains('terbuka'));
            const buka = !sedangTerbuka;
            const panel = sedangTerbuka || utama;
            if (!panel) return;

            if (lain && lain !== panel && lain.classList.contains('terbuka')) {
                lain.classList.remove('terbuka');
                lain.setAttribute('aria-hidden', 'true');
            }

            if (buka) {
                panel.classList.add('terbuka');
                panel.setAttribute('aria-hidden', 'false');
                overlay.classList.add('terbuka');
                overlay.setAttribute('aria-hidden', 'false');
                document.body.classList.add('menu-panel-open');
                if (btn) btn.setAttribute('aria-expanded', 'true');
                const fokus = panel.querySelector('a[href], button');
                if (fokus) fokus.focus();
            } else {
                panel.classList.remove('terbuka');
                panel.setAttribute('aria-hidden', 'true');
                overlay.classList.remove('terbuka');
                overlay.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('menu-panel-open');
                if (btn) btn.setAttribute('aria-expanded', 'false');
            }
        }

        // Kompatibilitas: pemanggil lama (mobile-bottom-nav "Lainnya",
        // tombol tutup di sidebar) tetap bekerja.
        function toggleMobileMenu() { toggleMenuPanel(); }
        function toggleSidebar() { toggleMenuPanel(); }
        function toggleSidebarMode() { return; }

        // Esc menutup panel (sama seperti modal).
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            const terbuka = ['proMega', 'mainSidebar'].some(function (id) {
                const p = document.getElementById(id);
                return p && p.classList.contains('terbuka');
            });
            if (terbuka) toggleMenuPanel();
        });

        // Klik di luar mega menu (desktop) menutup panel - pengganti overlay
        // di desktop (overlay dim hanya dipakai drawer mobile).
        document.addEventListener('mousedown', function (e) {
            if (!window.matchMedia('(min-width: 1024px)').matches) return;
            var p = document.getElementById('proMega');
            if (!p || !p.classList.contains('terbuka')) return;
            var btn = document.getElementById('menuPanelBtn');
            if (p.contains(e.target) || (btn && btn.contains(e.target))) return;
            toggleMenuPanel();
        });

        // Hover-to-open (desktop pointer halus saja): mengarahkan kursor ke
        // "Semua Menu" membuka mega menu tanpa klik - seperti Canva. Klik
        // tetap toggle untuk keyboard/layar sentuh; ada jeda kecil supaya
        // tidak kebuka hanya karena tikus melintas. Panel harus ikut
        // disentuh hover-nya supaya bisa dipindahkan dari tombol ke panel.
        (function () {
            var btn = document.getElementById('menuPanelBtn');
            if (!btn) return;
            var halus = window.matchMedia('(hover: hover) and (pointer: fine)');
            var desktop = window.matchMedia('(min-width: 1024px)');
            var timerMasuk = null, timerKeluar = null;

            function panel() { return document.getElementById('proMega'); }
            function buka() {
                clearTimeout(timerKeluar);
                if (!halus.matches || !desktop.matches) return;
                var p = panel();
                if (!p || p.classList.contains('terbuka')) return;
                clearTimeout(timerMasuk);
                timerMasuk = setTimeout(function () {
                    var p2 = panel();
                    if (p2 && !p2.classList.contains('terbuka')) toggleMenuPanel();
                }, 140);
            }
            function keluar() {
                clearTimeout(timerMasuk);
                clearTimeout(timerKeluar);
                timerKeluar = setTimeout(function () {
                    var p = panel();
                    if (p && p.classList.contains('terbuka') && desktop.matches) toggleMenuPanel();
                }, 260);
            }

            btn.addEventListener('mouseenter', buka);
            btn.addEventListener('mouseleave', keluar);
            var p = panel();
            if (p) {
                p.addEventListener('mouseenter', function () { clearTimeout(timerKeluar); clearTimeout(timerMasuk); });
                p.addEventListener('mouseleave', keluar);
            }
        })();

        // Tooltip native (title) di tiap link menu - satu-satunya cara tahu
        // nama menu saat ikon-only tanpa mengetik ulang ratusan baris label
        // secara manual di semua menu yang sudah ada (state collapse sendiri
        // sudah dipasang lebih awal, lihat script tepat setelah <body>).
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('#mainSidebar nav a').forEach(function(a) {
                const span = a.querySelector('span');
                if (span && span.textContent.trim()) {
                    a.setAttribute('title', span.textContent.trim());
                }
            });
            // Di layar kecil, tab yang sedang aktif dimajukan supaya tidak
            // terpotong di tepi kanan tanpa terlihat sama sekali.
            if (window.innerWidth < 1024) {
                const aktif = document.querySelector('.pro-bar2 .pro-tab.aktif');
                if (aktif) aktif.scrollIntoView({ block: 'nearest', inline: 'center' });
            }
        });

        // Sembunyikan sidebar otomatis jika layar diresize ke kecil
        window.addEventListener('resize', function() {
            if (window.innerWidth < 1024) {
                document.body.classList.remove('sidebar-collapsed');
            } else if (document.getElementById('mainSidebar')?.classList.contains('mobile-open')) {
                toggleMobileMenu();
            }
        });

        /* Accordion grup menu sidebar.

        Yang berlaku sekarang: satu grup terbuka pada satu waktu, dan
        grup yang memuat halaman aktif terbuka otomatis. Dua aturan itu
        menggantikan model lama wherein setiap grup berdiri sendiri dan
        state-nya disimpan per grup - yang membuat sidebar bisa menumpuk
        lima grup terbuka sekaligus.

        sessionStorage masih dipakai, tapi hanya untuk kasus tanpa grup
        yang cocok dengan URL, dan hanya menyimpan satu grup (yang
        terakhir). localStorage sengaja tidak dipakai supaya pilihan
        user tidak bertahan berhari-hari di komputer bersama.
        */
        const GRUP_KEY = 'sidebarGrup';

        function grupId(btn) {
            return Array.from(document.querySelectorAll('#mainSidebar .menu-group-toggle'))
                .indexOf(btn);
        }

        function simpanGrup() {
            try {
                const state = {};
                document.querySelectorAll('#mainSidebar .menu-group-toggle').forEach((b, i) => {
                    const items = b.nextElementSibling;
                    if (items && items.classList.contains('menu-group-items')) {
                        state[i] = !items.classList.contains('hidden');
                    }
                });
                sessionStorage.setItem(GRUP_KEY, JSON.stringify(state));
            } catch (e) { /* storage penuh atau dinonaktifkan: abaikan */ }
        }

        /* Grup yang cocok dengan URL halaman sekarang.

        Dicocokkan dari href menu di dalam grup, bukan dari nama grup.
        Dua halaman bisa punya URL yang sama-sama mengandung "/kasir"
        (mis. /kasir/monitoring dan /kasir/waiting-list) dan keduanya
        memang berada di grup yang sama, jadi pencocokan longest-prefix
        dipakai: URL halaman dicocokkan ke href menu yang paling panjang
        yang cocok, lalu grup dari menu itulah yang dibuka.
        */
        function grupUntukHalamanAktif() {
            const path = location.pathname.replace(/\/+$/, '') || '/';
            let terbaik = null;
            let panjangTerpanjang = -1;

            document.querySelectorAll('#mainSidebar .menu-group-toggle').forEach((b) => {
                const items = b.nextElementSibling;
                if (!items || !items.classList.contains('menu-group-items')) return;

                items.querySelectorAll('a[href]').forEach((a) => {
                    let url;
                    try { url = new URL(a.getAttribute('href'), location.origin); } catch (e) { return; }
                    const href = url.pathname.replace(/\/+$/, '') || '/';
                    if (path !== href && !path.startsWith(href + '/')) return;
                    if (href.length > panjangTerpanjang) {
                        panjangTerpanjang = href.length;
                        terbaik = b;
                    }
                });
            });

            return terbaik;
        }

        function pulihkanGrup() {
            // Grup yang memuat halaman aktif selalu dibuka - inilah yang
            // membuat user tidak perlu mencari menunya setelah pindah
            // halaman. State lama dari sessionStorage hanya dipakai
            // kalau tidak ada grup yang cocok (mis. halaman tanpa menu
            // di sidebar seperti /akuntansi/neraca yang diteruskan).
            const grupAktif = grupUntukHalamanAktif();

            if (grupAktif) {
                tutupGrupLain(grupAktif);
                const items = grupAktif.nextElementSibling;
                items.classList.remove('hidden');
                const icon = grupAktif.querySelector('i.fa-chevron-down');
                if (icon) icon.classList.add('rotate-180');
                grupAktif.setAttribute('aria-expanded', 'true');
                simpanGrup();
                return;
            }

            try {
                const raw = sessionStorage.getItem(GRUP_KEY);
                if (!raw) return;
                const state = JSON.parse(raw);
                let jumlahBuka = 0;
                let terakhir = null;

                document.querySelectorAll('#mainSidebar .menu-group-toggle').forEach((b, i) => {
                    const items = b.nextElementSibling;
                    // Hanya grup yang state-nya benar-benar tersimpan yang
                    // diubah. Grup baru / baru dapat hak akses dibiarkan
                    // mengikuti keputusan server.
                    if (!(i in state) || !items || !items.classList.contains('menu-group-items')) return;
                    if (state[i] === true) { jumlahBuka++; terakhir = b; }
                });

                // Kalau state lama menyisakan lebih dari satu grup terbuka
                // (karena dulu tidak ada aturan ini), tinggalkan yang
                // terakhir saja. Tanpa baris ini, sidebar bisa sampai lima
                // grup terbuka sekaligus.
                if (jumlahBuka > 1 && terakhir) {
                    tutupGrupLain(terakhir);
                    simpanGrup();
                    return;
                }

                document.querySelectorAll('#mainSidebar .menu-group-toggle').forEach((b, i) => {
                    const items = b.nextElementSibling;
                    if (!(i in state) || !items || !items.classList.contains('menu-group-items')) return;
                    const buka = state[i] === true;
                    items.classList.toggle('hidden', !buka);
                    const icon = b.querySelector('i.fa-chevron-down');
                    if (icon) icon.classList.toggle('rotate-180', buka);
                    b.setAttribute('aria-expanded', String(buka));
                });
            } catch (e) { /* data rusak: pakai state server */ }
        }

        /* Hanya satu grup boleh terbuka.

        Sebelumnya setiap grup berdiri sendiri: membuka satu grup tidak
        menutup yang lain, dan state-nya disimpan per grup di
        sessionStorage. Akibatnya setelah beberapa kali berpindah halaman,
        sidebar bisa punya lima grup terbuka sekaligus dan daftar menu jadi
        hummedCLOSED penuh. Itu yang dilaporkan sebagai "accordion terus
        terbuka meskipun sedang memilih menu yang lain".

        Dua aturan yang sekarang berlaku:
        1. Membuka satu grup menutup semua grup lain. Ini perilaku accordion
           yang sebenarnya, dan membuat panjang sidebar selalu sama.
        2. Hanya grup yang MEMANG berisi halaman aktif yang boleh terbuka
           saat halaman dimuat. Jadi begitu kasir menekan "Antrean Kasir",
           grup Penjualan terbuka dan semua yang lain tertutup - tanpa
           perlu user menutup apa pun.

        Aturan 2 yang penting untukTransition: tanpa itu, user yang sedang
        mengerjakan satu bagian lalu pindah ke bagian lain akan mendapati
        grup lamanya masih terbuka di halaman berikutnya, dan harus
        mencari di mana menu yang dia cari berada.
        */
        function tutupGrupLain(kecualiBtn) {
            document.querySelectorAll('#mainSidebar .menu-group-toggle').forEach((b) => {
                if (b === kecualiBtn) return;
                const items = b.nextElementSibling;
                if (!items || !items.classList.contains('menu-group-items')) return;
                items.classList.add('hidden');
                const ic = b.querySelector('i.fa-chevron-down');
                if (ic) ic.classList.remove('rotate-180');
                b.setAttribute('aria-expanded', 'false');
            });
        }

        function toggleMenuGroup(btn) {
            // Desktop: semua grup tampil permanen (mega menu), accordion mati.
            if (window.matchMedia('(min-width:1024px)').matches) return;
            // Rel ringkas (sidebar dilipat): grup membuka flyout, bukan accordion.
            if (window.railAktif && window.railAktif()) { window.railBukaFlyout(btn, true); return; }
            const items = btn.nextElementSibling;
            const akanBuka = items.classList.contains('hidden');
            if (akanBuka) tutupGrupLain(btn);
            items.classList.toggle('hidden', !akanBuka);
            const icon = btn.querySelector('i.fa-chevron-down');
            if (icon) icon.classList.toggle('rotate-180', akanBuka);
            btn.setAttribute('aria-expanded', String(akanBuka));
            simpanGrup();
        }

        // Terapkan kembali state setelah DOM siap, lalu delegasi klik grup
        // ke toggleMenuGroup di atas supaya setiap perubahan ikut tersimpan.
        pulihkanGrup();

        // ===== Rel ikon (sidebar ringkas): ikon grup + flyout + tooltip =====
        (function () {
            const ikonGrup = [
                [/produksi|logistik/i, 'fa-industry'], [/relasi|bisnis/i, 'fa-handshake'], [/finance|hr|keuangan/i, 'fa-wallet'],
                [/laporan/i, 'fa-chart-line'], [/akuntansi/i, 'fa-scale-balanced'], [/jersey/i, 'fa-shirt'], [/system|sistem/i, 'fa-gear'],
                [/penjualan|kasir/i, 'fa-cash-register'], [/master/i, 'fa-database'],
            ];
            const pilihIkon = (teks) => (ikonGrup.find(([re]) => re.test(teks)) || [null, 'fa-layer-group'])[1];
            const rail = () => document.body.classList.contains('sidebar-collapsed') && window.innerWidth >= 1024;
            window.railAktif = rail;

            let fly, tip, tutupTimer, tombolAktif = null;
            const buat = () => {
                fly = document.createElement('div'); fly.id = 'railFlyout';
                tip = document.createElement('div'); tip.id = 'railTip';
                document.body.append(fly, tip);
                fly.addEventListener('mouseenter', () => clearTimeout(tutupTimer));
                fly.addEventListener('mouseleave', () => tutupFlyout());
            };
            const tutupFlyout = (segera) => {
                clearTimeout(tutupTimer);
                const tutup = () => { fly.style.display = 'none'; if (tombolAktif) tombolAktif.classList.remove('flyout-open'); tombolAktif = null; };
                segera ? tutup() : (tutupTimer = setTimeout(tutup, 180));
            };
            window.railBukaFlyout = (btn, klik) => {
                if (!rail()) return;
                clearTimeout(tutupTimer);
                tip.style.display = 'none';
                                if (tombolAktif) tombolAktif.classList.remove('flyout-open');
                tombolAktif = btn; btn.classList.add('flyout-open');

                const wadah = btn.nextElementSibling;
                const judul = (btn.dataset.label || '').replace(/</g, '&lt;');
                fly.innerHTML = '<div class="fly-title">' + judul + '</div>';
                wadah.querySelectorAll('a').forEach((a) => {
                    const item = document.createElement('a');
                    item.href = a.href;
                    if (a.classList.contains('sb-active')) item.classList.add('fly-active');
                    const svg = a.querySelector('svg');
                    if (svg) { const c = svg.cloneNode(true); c.removeAttribute('class'); item.appendChild(c); }
                    const label = document.createElement('span');
                    label.textContent = (a.querySelector('span') || a).textContent.trim();
                    item.appendChild(label);
                    fly.appendChild(item);
                });
                fly.style.display = 'block';
                const r = btn.getBoundingClientRect();
                const tinggi = fly.offsetHeight;
                fly.style.left = (r.right + 10) + 'px';
                fly.style.top = Math.max(12, Math.min(r.top, window.innerHeight - tinggi - 12)) + 'px';
            };
            const tampilTip = (el, teks) => {
                if (!rail()) return;
                tip.textContent = teks; tip.style.display = 'block';
                const r = el.getBoundingClientRect();
                tip.style.left = (r.right + 12) + 'px';
                tip.style.top = (r.top + r.height / 2 - tip.offsetHeight / 2) + 'px';
            };

            document.addEventListener('DOMContentLoaded', () => {
                buat();
                document.querySelectorAll('#mainSidebar .menu-group-toggle').forEach((btn) => {
                    const label = (btn.querySelector('span') || btn).textContent.trim();
                    btn.dataset.label = label;
                    const ikon = document.createElement('i');
                    ikon.className = 'rail-icon fas ' + pilihIkon(label);
                    btn.insertBefore(ikon, btn.firstChild);
                    if (btn.nextElementSibling && btn.nextElementSibling.querySelector('.sb-active')) btn.classList.add('has-active');
                    btn.addEventListener('mouseenter', () => window.railBukaFlyout(btn, false));
                    btn.addEventListener('mouseleave', () => { if (rail()) tutupFlyout(); });
                });
                document.querySelectorAll('#mainSidebar nav > a').forEach((a) => {
                    const label = (a.querySelector('span') || a).textContent.trim();
                    a.addEventListener('mouseenter', () => tampilTip(a, label));
                    a.addEventListener('mouseleave', () => { tip.style.display = 'none'; });
                });
                document.addEventListener('click', (e) => { if (fly && !fly.contains(e.target) && !e.target.closest('.menu-group-toggle')) tutupFlyout(true); });
                document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && fly) tutupFlyout(true); });
                window.addEventListener('resize', () => { if (!rail() && fly) tutupFlyout(true); });
            });
        })();
    </script>
    {{-- Angka desimal DB (mis. 226.000) ditampilkan rapi (226) di SEMUA input number, termasuk saat modal edit
         diisi lewat JS (input.value = ...), baris yang ditambah dinamis, dan value bawaan server. Nilai numerik
         tidak berubah; hanya nol di belakang koma yang dibuang. --}}
    <script>
    (function () {
        const rapikan = (v) => (typeof v === 'string' && /^-?\d+\.\d+$/.test(v))
            ? v.replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '')
            : v;

        const desc = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
        Object.defineProperty(HTMLInputElement.prototype, 'value', {
            configurable: true,
            get() { return desc.get.call(this); },
            set(v) {
                if (this.type === 'number' && v !== null && v !== undefined) v = rapikan(String(v));
                desc.set.call(this, v);
            },
        });

        const perbaiki = (el) => {
            const awal = el.getAttribute('value');
            if (awal === null) return;
            const baru = rapikan(awal);
            if (baru !== awal) el.setAttribute('value', baru);
        };
        const semua = (root) => root.querySelectorAll('input[type="number"]').forEach(perbaiki);

        document.addEventListener('DOMContentLoaded', () => semua(document));
        new MutationObserver((daftar) => daftar.forEach((m) => m.addedNodes.forEach((n) => {
            if (n.nodeType !== 1) return;
            if (n.matches && n.matches('input[type="number"]')) perbaiki(n);
            if (n.querySelectorAll) semua(n);
        }))).observe(document.documentElement, { childList: true, subtree: true });
    })();
    </script>
    <script>
    // Konfirmasi seragam: form atau tombol ber-atribut data-konfirmasi="pesan" memakai dialog SweetAlert,
    // bukan kotak confirm() bawaan peramban (tampilannya berbeda-beda dan tidak mengikuti tema aplikasi).
    (function () {
        function tampil(pesan, lanjut) {
            // Bentuk seperti contoh pemilik: kalimat pertama jadi judul ("Hapus platform ini?"), sisanya keterangan,
            // tombol aksi memakai kata kerjanya sendiri ("Hapus", "Batalkan"), merah bila merusak.
            const kata = (pesan.match(/^(hapus|batalkan|reset|terbitkan|jalankan|proses|buka)/i) || [])[0];
            const merusak = /^(hapus|batalkan|reset)/i.test(pesan);
            const pecah = pesan.match(/^(.*?\?)\s*(.*)$/s);
            Swal.fire({
                icon: merusak ? 'warning' : 'info',
                title: pecah ? pecah[1] : pesan,
                text: pecah ? pecah[2] : '',
                showCancelButton: true,
                confirmButtonText: kata ? kata.charAt(0).toUpperCase() + kata.slice(1).toLowerCase() : 'Lanjutkan',
                cancelButtonText: 'Batal',
                focusCancel: merusak,
                customClass: { confirmButton: merusak ? 'tombol-bahaya' : '' }
            }).then(function (r) { if (r.isConfirmed) lanjut(); });
        }
        document.addEventListener('submit', function (e) {
            const form = e.target;
            if (!(form instanceof HTMLFormElement) || !form.dataset.konfirmasi || form.dataset.sudahKonfirmasi === '1') return;
            e.preventDefault();
            e.stopImmediatePropagation();
            tampil(form.dataset.konfirmasi, function () { form.dataset.sudahKonfirmasi = '1'; form.requestSubmit(); });
        }, true);
        document.addEventListener('click', function (e) {
            const tombol = e.target.closest ? e.target.closest('button[data-konfirmasi], a[data-konfirmasi]') : null;
            if (!tombol || tombol.dataset.sudahKonfirmasi === '1') return;
            e.preventDefault();
            e.stopImmediatePropagation();
            tampil(tombol.dataset.konfirmasi, function () { tombol.dataset.sudahKonfirmasi = '1'; tombol.click(); });
        }, true);
    })();
    </script>
    <script>
    // Setiap form yang dikirim langsung oleh browser membawa submit_id. Server (SubmitSekaliJalan) memproses satu
    // submit_id sekali saja, jadi klik ganda, coba ulang setelah jaringan putus, atau dua tab tidak menggandakan
    // transaksi. Form yang dikirim lewat skrip (defaultPrevented) tidak diberi id: skripnya bisa mengirim ulang
    // dengan sah tanpa memuat ulang halaman.
    // Teks dari data (nama master, nomor nota, ringkasan pengajuan) yang disisipkan ke dialog ber-html WAJIB lewat
    // fungsi ini: tanpa itu nama berisi <img onerror=...> dijalankan sebagai skrip di sesi orang yang membuka dialog.
    window.escHtml = function (v) {
        return String(v ?? '').replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };
    function beriSubmitId(form) {
        if (!(form instanceof HTMLFormElement) || (form.getAttribute('method') || 'get').toLowerCase() === 'get') return;
        let isian = form.querySelector('input[name="submit_id"]');
        if (!isian) {
            isian = document.createElement('input');
            isian.type = 'hidden';
            isian.name = 'submit_id';
            form.appendChild(isian);
        }
        if (!isian.value) isian.value = Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
    }
    document.addEventListener('submit', function (e) {
        if (!e.defaultPrevented) beriSubmitId(e.target);
    }, false);
    // Banyak form memeriksa isian lebih dulu (preventDefault) lalu mengirim dengan form.submit(), yang TIDAK memicu
    // event submit. Tanpa ini form seperti order dan Kelola Barang Jersey terkirim tanpa submit_id, sehingga klik
    // ganda di sana tidak tertahan. Kiriman lewat form.submit() selalu meninggalkan halaman, jadi aman diberi id.
    const kirimFormAsli = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        beriSubmitId(this);
        return kirimFormAsli.call(this);
    };
    // Halaman yang dibuka lagi dari tombol kembali memakai id baru untuk kiriman berikutnya.
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) document.querySelectorAll('form input[name="submit_id"]').forEach(function (i) { if (!i.closest('[data-submit-id-tetap]')) i.value = ''; });
    });

    // Klik ganda pada tombol simpan tidak boleh mengirim form dua kali (kasir di LAN sering menekan lagi
    // saat halaman terasa lambat). Server tetap penjaga utama (kunci baris, submit_id); ini hanya mencegah
    // pesan "sudah diproses" yang membingungkan. Tombol aktif lagi bila halaman tidak berpindah (mis. unduhan).
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (e.defaultPrevented || !(form instanceof HTMLFormElement) || form.dataset.bolehKirimUlang !== undefined) return;
        if (form.dataset.sedangKirim === '1') { e.preventDefault(); return; }
        form.dataset.sedangKirim = '1';
        const tombol = form.querySelectorAll('button[type=submit], input[type=submit], button:not([type])');
        setTimeout(function () { tombol.forEach(function (t) { t.disabled = true; t.classList.add('opacity-60'); }); }, 0);
        setTimeout(function () {
            form.dataset.sedangKirim = '0';
            tombol.forEach(function (t) { t.disabled = false; t.classList.remove('opacity-60'); });
        }, 8000);
    }, false);
    // Saat halaman di-reload, browser memulihkan isian terakhir (radio, centang, pilihan dropdown) TANPA
    // memicu event change. Tampilan yang diatur skrip halaman lalu tidak sinkron dengan isian yang terlihat,
    // mis. radio "Jasa" tercentang tetapi blok Bahan Baku yang tampil. Setelah semua skrip halaman siap,
    // isian yang nilainya berbeda dari bawaan HTML diberi event change agar tampilan mengikuti isian.
    window.sinkronkanIsianPulih = function (akar) {
        (akar || document).querySelectorAll('input[type=radio], input[type=checkbox], select').forEach(function (el) {
            if (el.closest('[data-no-pulih]')) return;
            let berubah = false;
            if (el.tagName === 'SELECT') {
                berubah = Array.prototype.some.call(el.options, function (o) { return o.selected !== o.defaultSelected; })
                    && !(el.selectedIndex === 0 && !Array.prototype.some.call(el.options, function (o) { return o.defaultSelected; }));
            } else if (el.type === 'radio') {
                berubah = el.checked && !el.defaultChecked;
            } else {
                berubah = el.checked !== el.defaultChecked;
            }
            if (berubah) el.dispatchEvent(new Event('change', { bubbles: true }));
        });
    };
    window.addEventListener('load', function () {
        // Hanya saat reload atau kembali/maju: pada kunjungan biasa browser tidak memulihkan apa pun, dan
        // isian yang diisi skrip halaman (mis. form ubah) tidak boleh memicu change kedua kalinya.
        const nav = (performance.getEntriesByType && performance.getEntriesByType('navigation')[0]) || {};
        if (nav.type !== 'reload' && nav.type !== 'back_forward') return;
        setTimeout(function () { window.sinkronkanIsianPulih(); }, 0);
    });
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        document.querySelectorAll('form[data-sedang-kirim="1"]').forEach(function (f) { f.dataset.sedangKirim = '0'; });
        document.querySelectorAll('button:disabled.opacity-60').forEach(function (t) { t.disabled = false; t.classList.remove('opacity-60'); });
    });
    </script>
    @auth
    <script>
    // Sembunyikan tombol aksi yang pasti ditolak server untuk pengguna ini (aturan sama dengan CheckMenuAccess):
    // form baris (hapus, aktif/nonaktif) menurut alamat tujuannya, dan tombol "Ubah" pembuka modal menurut hak ubah
    // modul halaman ini. Hanya kerapian tampilan; server tetap menolak bila dipaksa.
    (function () {
        const hak = @json(\App\Support\Izin::petaUntukTampilan(Auth::user()));
        if (hak.admin) return;
        const dasar = new URL(@json(url('/'))).pathname.replace(/\/$/, '');
        const AKSI = { view: 0, add: 1, edit: 2, delete: 3 };

        function jalur(url) {
            try { return decodeURIComponent(new URL(url, location.href).pathname).replace(dasar, '').replace(/^\/+|\/+$/g, ''); } catch (e) { return null; }
        }
        function cocok(p, pola) { return p === pola || p.startsWith(pola + '/'); }
        function boleh(p, aksi) {
            if (p === null) return true;
            if (hak.bebas.some(b => cocok(p, b))) return true;
            if (aksi !== 'view' && hak.bacaSaja) return false;
            if (hak.hargaTertutup.some(h => cocok(p, h) || (h.includes('*') && new RegExp('^' + h.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '[^/]*') + '$').test(p)))) return false;
            if (aksi === 'add' && hak.postBaca.includes(p)) aksi = 'view';
            let pilih = null;
            for (const pre in hak.aturan) if (cocok(p, pre) && (pilih === null || pre.length > pilih.length)) pilih = pre;
            if (pilih === null) return false;
            const bit = hak.aturan[pilih];
            return bit[0] === '1' && bit[AKSI[aksi]] === '1';
        }
        function aksiForm(f) {
            const m = (f.querySelector('input[name="_method"]')?.value || f.getAttribute('method') || 'GET').toUpperCase();
            return m === 'GET' ? 'view' : m === 'POST' ? 'add' : m === 'DELETE' ? 'delete' : 'edit';
        }
        // Form baris = form tanpa isian yang terlihat (hanya tombol + input tersembunyi).
        function formBaris(f) {
            return !f.querySelector('input:not([type=hidden]):not([type=submit]):not([type=button]), select, textarea');
        }
        function rapikan(akar) {
            akar.querySelectorAll('form').forEach(f => {
                if (f.dataset.hakDiperiksa) return;
                f.dataset.hakDiperiksa = '1';
                const aksi = aksiForm(f);
                if (aksi !== 'view' && formBaris(f) && !boleh(jalur(f.action), aksi)) f.style.display = 'none';
            });
            // Tombol "Ubah" pembuka modal: alamat form-nya diisi saat diklik, jadi pakai hak ubah modul halaman ini.
            if (!boleh(jalur(location.href), 'edit')) {
                akar.querySelectorAll('[aria-label="Ubah"], [title="Ubah"], [onclick^="openEdit"], [onclick^="edit"]').forEach(b => {
                    if (b.closest('form') && !formBaris(b.closest('form'))) return;
                    b.style.display = 'none';
                });
            }
            // Tombol pembuka modal: diperiksa terhadap form di dalam modal yang dibukanya (aksi + alamat tujuan).
            akar.querySelectorAll('[onclick*="openModal("], [onclick*="toggleModal("]').forEach(b => {
                const m = (b.getAttribute('onclick') || '').match(/(?:openModal|toggleModal)\(\s*['"]([^'"]+)['"]/);
                const form = m && document.querySelector('#modal-' + CSS.escape(m[1]) + ' form');
                if (!form) return;
                const aksi = aksiForm(form);
                if (aksi !== 'view' && !boleh(jalur(form.getAttribute('action') ? form.action : location.href), aksi)) b.style.display = 'none';
            });
            // Tautan ke halaman ubah/buat milik modul lain atau modul ini.
            akar.querySelectorAll('a[href]').forEach(a => {
                const p = jalur(a.href);
                if (!p || a.dataset.hakDiperiksa) return;
                a.dataset.hakDiperiksa = '1';
                const m = p.match(/(^|\/)(create|edit)(\/|$)/);
                if (m && !boleh(p, m[2] === 'create' ? 'add' : 'edit')) a.style.display = 'none';
            });
        }
        document.addEventListener('DOMContentLoaded', () => rapikan(document));
        window.rapikanTombolHak = rapikan;
    })();
    </script>
    @endauth
    @stack('scripts')
</body>
</html>
