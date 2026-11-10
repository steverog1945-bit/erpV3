@extends('layouts.app')

@php
    /**
     * CHECKPOINT 5D - Ikon builder.
     *
     * Sengaja memakai SVG inline, BUKAN Font Awesome: folder
     * public/vendor tidak dijamin tersedia di setiap environment, dan
     * loader stylesheet eksternal yang gagal membuat seluruh toolbar
     * editor kehilangan ikonnya sekaligus. SVG inline tidak menambah satu
     * pun permintaan jaringan dan tetap tampil di semua kondisi.
     */
    $lpbIkon = [
        'brand' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>',
        'search' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>',
        'hero' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="4" rx="1"/><rect x="3" y="12" width="8" height="8" rx="1"/><rect x="15" y="12" width="6" height="8" rx="1"/></svg>',
        'about' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>',
        'services' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 7h-8"/><path d="M4 7h4"/><rect x="14" y="3" width="7" height="8" rx="1.5"/><rect x="3" y="13" width="7" height="8" rx="1.5"/><path d="M14 17h7"/></svg>',
        'product' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>',
        'portfolio' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>',
        'quote' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 7H5a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h2"/><path d="M9 7v10"/><path d="M19 7h-4a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h2"/><path d="M19 7v10"/></svg>',
        'chart' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v18h18"/><path d="M7 16V9"/><path d="M12 16v-5"/><path d="M17 16V5"/></svg>',
        'faq' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.6 2.2c-.7.4-1.1 1-1.1 1.8"/><path d="M12 17h.01"/></svg>',
        'cta' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 3 4 14h7v7l9-11h-7Z"/></svg>',
        'contact' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
        'footer' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h10"/></svg>',
        'grip' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="9" cy="6" r="1.4"/><circle cx="15" cy="6" r="1.4"/><circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/><circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="18" r="1.4"/></svg>',
        'duplicate' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
        'trash' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="M19 6l-.8 13a2 2 0 0 1-2 1.9H7.8a2 2 0 0 1-2-1.9L5 6"/><path d="M10 11v5"/><path d="M14 11v5"/></svg>',
        'arrowUp' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>',
        'arrowDown' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>',
        'eye' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>',
        'eyeOff' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.7 5.1A9.9 9.9 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-2.4 3.3"/><path d="M6.6 6.6A17.4 17.4 0 0 0 2 12s3.5 7 10 7a9.7 9.7 0 0 0 4.5-1.1"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="m3 3 18 18"/></svg>',
        'close' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>',
        'check' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" stroke-width="2.2"><path d="m5 13 4 4L19 7"/></svg>',
        'spinner' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" stroke-width="2.4" class="lpb-spin"><path d="M12 3a9 9 0 1 0 9 9"/></svg>',
        'warn' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
        'error' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>',
        'alert' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
        'desktop' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/></svg>',
        'tablet' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>',
        'mobile' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M12 18h.01"/></svg>',
        'undo' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10h10a5 5 0 0 1 5 5v2"/><path d="m7 6-4 4 4 4"/></svg>',
        'redo' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10H11a5 5 0 0 0-5 5v2"/><path d="m17 6 4 4-4 4"/></svg>',
        'history' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v4h4"/><path d="M12 8v4l3 2"/></svg>',
        'save' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/></svg>',
        'publish' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg>',
    ];
@endphp

@section('content')

<div class="w-full px-3 sm:px-4 lg:px-6 py-4" id="lpb-root">

    
    <style>
        #lpb-root {
            --lpb-surface: #ffffff;
            --lpb-surface-raised: #ffffff;
            --lpb-workspace: #F1F5F1;
            --lpb-canvas-bg: #ffffff;
            --lpb-border: #E2EAE4;
            --lpb-border-strong: #C9D6CE;
            --lpb-text: #1A231F;
            --lpb-text-secondary: #3F4F47;
            --lpb-text-tertiary: #4A5B53;
            --lpb-text-muted: #94a3b8;
            --lpb-accent: #2563EB;
            --lpb-accent-hover: #1D4ED8;
            --lpb-accent-subtle: #EFF4FF;
            --lpb-accent-border: #DDE9FF;
            /* Palet resmi ORION — ROYAL INDIGO (token lokal builder) */
            --orion-deep-night: #15143F;
            --orion-royal-indigo: #2E318E;
            --orion-electric-violet: #4D55F8;
            --orion-cta-orange: #FF6B17;
            --orion-cyan-light: #B8EEFF;
            --orion-on-violet: #FFFFFF;      /* 5.29:1 AA di atas #4D55F8 */
            --orion-on-orange: #15143F;      /* 8.53:1 AAA di atas #FF6B17 */
            --orion-success: #059669;
            --orion-success-subtle: #ecfdf5;
            --orion-warning: #d97706;
            --orion-warning-subtle: #FEF6EA;
            --orion-danger: #93481A;
            --orion-danger-subtle: #fff1f2;
            --lpb-radius-sm: 6px;
            --lpb-radius-md: 8px;
            --lpb-radius-lg: 12px;
            --lpb-radius-xl: 16px;
            --lpb-shadow-sm: 0 1px 2px rgba(15, 23, 42, .05);
            --lpb-shadow-md: 0 4px 6px -1px rgba(15, 23, 42, .06), 0 2px 4px -2px rgba(15, 23, 42, .05);
            --lpb-shadow-lg: 0 10px 15px -3px rgba(15, 23, 42, .07);
            --lpb-control-h: 36px;
            --lpb-control-h-sm: 30px;
            --lpb-icon-size: 16px;
            --lpb-transition: 160ms cubic-bezier(.23, 1, .32, 1);
        }
        html.dark #lpb-root {
            --lpb-surface: #1A231F;
            --lpb-surface-raised: #2A3731;
            --lpb-workspace: #0b1220;
            --lpb-canvas-bg: #2A3731;
            --lpb-border: #3F4F47;
            --lpb-border-strong: #3F4F47;
            --lpb-text: #F1F5F1;
            --lpb-text-secondary: #C9D6CE;
            --lpb-text-tertiary: #94a3b8;
            --lpb-text-muted: #4A5B53;
            --lpb-accent-subtle: #012228;
            --lpb-accent-border: #01434D;
            --lpb-success-subtle: rgba(5, 150, 105, .14);
            --lpb-warning-subtle: rgba(217, 119, 6, .14);
            --lpb-danger-subtle: rgba(225, 29, 72, .14);
            --lpb-shadow-sm: 0 1px 2px rgba(0, 0, 0, .3);
            --lpb-shadow-md: 0 4px 6px -1px rgba(0, 0, 0, .35);
            --lpb-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, .4);
        }
        @media (prefers-reduced-motion: reduce) {
            #lpb-root * { transition: none !important; animation: none !important; }
        }

        /* Ukuran ikon SVG. Tanpa aturan ini, <svg> yang hanya punya
           viewBox (tanpa width/height) melaruk ke ukuran container di
           dalam flexbox - itu yang dulu membuat tombol komponen setinggi
           ratusan piksel. Batas bawah dari atribut width/height pada ikon,
           batas atas dari aturan kontekstual di bawah. */
        #lpb-root svg { flex: 0 0 auto; }
        #lpb-root svg:not([width]) { width: 1em; height: 1em; }
        #lpb-root .lpb-brand-icon svg,
        #lpb-root span[style*="width:18px"] svg { width: 18px; height: 18px; }
        #lpb-root .lpb-hdr-btn svg { width: 16px; height: 16px; }
        #lpb-root .lp-preview-btn svg { width: 14px; height: 14px; flex: none; }
        /* Tombol lebar pratinjau: ikon dan teks harus satu baris serta sejajar
           tengah. Tanpa ini, <svg> memakai display:block sehingga ikon melompat
           ke baris sendiri dan tidak center dengan tulisannya. */
        #lpb-root .lp-preview-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            white-space: nowrap;
        }
        #lpb-root .component-item svg { width: 16px; height: 16px; }
        #lpb-root .lp-icon-btn svg { width: 14px; height: 14px; }
        #lpb-root .lp-drag-handle svg { width: 14px; height: 14px; }
        #lpb-root .lp-type-badge svg { width: 12px; height: 12px; }
        #lpb-root .lpb-canvas-empty svg { width: 40px; height: 40px; }
        #lpb-root #save-status svg { width: 13px; height: 13px; }
        #lpb-root .lp-item-btn svg { width: 12px; height: 12px; }

        /* Indikator "menyimpan" berputar */
        @keyframes lpb-spin-kf { to { transform: rotate(360deg); } }
        #lpb-root .lpb-spin { animation: lpb-spin-kf .8s linear infinite; transform-origin: center; }
        @media (prefers-reduced-motion: reduce) {
            #lpb-root .lpb-spin { animation: none !important; }
        }
        #lpb-root { font-family: 'Inter', 'Segoe UI', sans-serif; }
        #prop-scroll { max-height: calc(100vh - 260px); overflow-y: auto; }
        #prop-scroll::-webkit-scrollbar { width: 6px; }
        #prop-scroll::-webkit-scrollbar-track { background: transparent; }
        #prop-scroll::-webkit-scrollbar-thumb { background: var(--lpb-border-strong); border-radius: 999px; }
        .lp-preview-btn { color: var(--lpb-text-tertiary); }
        .lp-preview-btn:hover { color: var(--lpb-text-secondary); }
        .lp-preview-btn.is-active { background: var(--lpb-surface-raised); color: var(--lpb-accent); box-shadow: var(--lpb-shadow-sm); }
        .lp-preview-btn:focus-visible { outline: 2px solid var(--lpb-accent); outline-offset: 1px; }
        .lp-drag-over { outline: 2px dashed var(--lpb-accent) !important; outline-offset: 2px !important; }
        #prop-scroll::-webkit-scrollbar { width: 6px; }
        #prop-scroll::-webkit-scrollbar-track { background: transparent; }
        #prop-scroll::-webkit-scrollbar-thumb { background: var(--lpb-border-strong); border-radius: 999px; }

        /* ===== GRID 3 PANEL ===== */
        .lpb-grid { display: grid; grid-template-columns: 260px minmax(0, 1fr) 320px; gap: 14px; align-items: start; }

        /* ===== FIX BUG #1 — INSPECTOR STICKY =====
           'align-items: start' membuat setiap grid item hanya setinggi
           kontennya sendiri. Akibatnya blok pembungkus panel (`.order-3`)
           tingginya PAS sama dengan panel (1049px), jadi `position: sticky`
           tidak punya ruang untuk bergerak — panel tetap ikut ter-scroll
           hilang walaupun properti sticky sudah benar.

           Solusi terkecil: rentangkan (stretch) saja grid item pembungkus
           panel agar setinggi baris grid, sehingga panel sticky punya ruang
           geser. Wrapper tidak punya latar/border, jadi tidak mengubah
           tampilan visual. */
        .lpb-grid > .order-2,
        .lpb-grid > .order-3 { align-self: stretch; min-height: 0; }

        .lpb-panel {
            background: var(--lpb-surface);
            border: 1px solid var(--lpb-border);
            border-radius: var(--lpb-radius-lg);
            box-shadow: var(--lpb-shadow-sm);
            display: flex; flex-direction: column; overflow: hidden;
            position: sticky; top: 16px; max-height: calc(100vh - 32px);
        }
        .lpb-panel-head { padding: 12px 14px 10px; border-bottom: 1px solid var(--lpb-border); flex: 0 0 auto; }
        .lpb-panel-title { font-size: 11px; font-weight: 600; color: var(--lpb-text); letter-spacing: .04em; text-transform: uppercase; }
        .lpb-panel-hint { font-size: 11px; color: var(--lpb-text-tertiary); margin-top: 2px; }
        .lpb-panel-body { padding: 12px 14px; overflow-y: auto; flex: 1 1 auto; min-height: 0; }
        .lpb-panel-body::-webkit-scrollbar { width: 6px; }
        .lpb-panel-body::-webkit-scrollbar-track { background: transparent; }
        .lpb-panel-body::-webkit-scrollbar-thumb { background: var(--lpb-border-strong); border-radius: 999px; }
        .lpb-panel-body::-webkit-scrollbar-thumb:hover { background: var(--lpb-text-muted); }

        /* ===== KOMPONEN ===== */
        .lpb-search-wrap { position: relative; margin-bottom: 10px; }
        .lpb-search-wrap > i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 12px; color: var(--lpb-text-muted); pointer-events: none; }
        .lpb-search {
            width: 100%; height: var(--lpb-control-h); padding: 0 12px 0 32px;
            font-size: 12.5px; color: var(--lpb-text);
            background: var(--lpb-surface-raised);
            border: 1px solid var(--lpb-border); border-radius: var(--lpb-radius-md);
            transition: border-color var(--lpb-transition), box-shadow var(--lpb-transition);
        }
        .lpb-search::placeholder { color: var(--lpb-text-muted); }
        .lpb-search:focus { outline: none; border-color: var(--lpb-accent); box-shadow: 0 0 0 3px var(--lpb-accent-subtle); }

        .lpb-component-list { display: flex; flex-direction: column; gap: 6px; }
        .component-item {
            /* Sengaja TANPA !important pada display. Kotak pencarian menulis
               item.style.display = 'none' secara inline; aturan stylesheet
               ber-!important akan menimpanya sehingga pencarian tidak pernah
               menyembunyikan apa pun. */
            display: flex; align-items: center; gap: 10px;
            width: 100%; padding: 8px 10px;
            font-size: 12.5px; font-weight: 500; color: var(--lpb-text-secondary);
            background: var(--lpb-surface-raised);
            border: 1px solid var(--lpb-border); border-radius: var(--lpb-radius-md);
            cursor: pointer; text-align: left;
            transition: background-color var(--lpb-transition), border-color var(--lpb-transition), color var(--lpb-transition);
        }
        .component-item:hover { background: var(--lpb-accent-subtle); border-color: var(--lpb-accent-border); color: var(--lpb-accent); }
        .component-item:focus-visible { outline: 2px solid var(--lpb-accent); outline-offset: 2px; }
        .component-item i { width: 16px; font-size: 13px; text-align: center; color: var(--lpb-text-muted); transition: color var(--lpb-transition); }
        .component-item:hover i { color: var(--lpb-accent); }
        .lpb-search-empty {
            padding: 14px 10px; text-align: center;
            font-size: 12.5px; color: var(--lpb-text-tertiary);
        }
        .lpb-search-empty[hidden] { display: none; }

        /* ---- Template preset bar (Orion Grafika Premium Default) ---- */
        .lpb-template-bar {
            margin-bottom: 10px; padding-bottom: 10px;
            border-bottom: 1px dashed var(--lpb-border);
        }
        .lpb-template-bar[hidden] { display: none; }
        #lpb-root .lpb-template-btn {
            display: flex; align-items: center; gap: 10px;
            width: 100%; padding: 10px 12px; text-align: left;
            background: linear-gradient(135deg, var(--lpb-accent-subtle), var(--lpb-surface-raised));
            border: 1.5px solid var(--lpb-accent-border); border-radius: var(--lpb-radius-lg);
            cursor: pointer; font-family: inherit;
            transition: background-color var(--lpb-transition), border-color var(--lpb-transition), box-shadow var(--lpb-transition);
        }
        #lpb-root .lpb-template-btn:hover:not(:disabled) {
            background: var(--lpb-accent-subtle);
            border-color: var(--lpb-accent);
            box-shadow: 0 2px 8px rgba(38, 120, 142, .25);
        }
        #lpb-root .lpb-template-btn:focus-visible { outline: 2px solid var(--lpb-accent); outline-offset: 2px; }
        #lpb-root .lpb-template-btn:disabled { opacity: .5; cursor: not-allowed; }
        #lpb-root .lpb-template-btn svg { width: 20px; height: 20px; flex: none; color: var(--lpb-accent); }
        .lpb-template-btn-text { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
        .lpb-template-btn-text strong { font-size: 11px; font-weight: 700; color: var(--lpb-text); letter-spacing: .02em; }
        .lpb-template-btn-text small { font-size: 10.5px; color: var(--lpb-text-tertiary); }

        /* ===== CANVAS ===== */
        .lpb-canvas-panel {
            background: var(--lpb-surface);
            border: 1px solid var(--lpb-border);
            border-radius: var(--lpb-radius-lg);
            box-shadow: var(--lpb-shadow-sm);
            display: flex; flex-direction: column; overflow: hidden;
            min-height: calc(100vh - 100px);
        }
        .lpb-canvas-toolbar {
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
            padding: 10px 14px; border-bottom: 1px solid var(--lpb-border); flex-wrap: wrap; flex: 0 0 auto;
        }
        .lpb-canvas-label { font-size: 11.5px; color: var(--lpb-text-tertiary); font-weight: 500; }
        .lpb-canvas-scroll {
            flex: 1 1 auto; overflow-y: auto; padding: 24px;
            background: var(--lpb-workspace);
        }
        .lpb-canvas-scroll::-webkit-scrollbar { width: 8px; }
        .lpb-canvas-scroll::-webkit-scrollbar-track { background: transparent; }
        .lpb-canvas-scroll::-webkit-scrollbar-thumb { background: var(--lpb-border-strong); border-radius: 999px; }
        .lpb-canvas {
            margin: 0 auto; max-width: 100%; min-height: 480px;
            background: var(--lpb-canvas-bg);
            border: 1px solid var(--lpb-border);
            border-radius: var(--lpb-radius-lg);
            box-shadow: var(--lpb-shadow-md);
            padding: 20px;
            transition: max-width var(--lpb-transition), box-shadow var(--lpb-transition);
        }
        .lpb-canvas:focus-visible { outline: 2px solid var(--lpb-accent); outline-offset: 2px; }
        .lpb-canvas-empty {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 10px; padding: 80px 20px; color: var(--lpb-text-muted); text-align: center;
        }
        .lpb-canvas-empty i { font-size: 36px; opacity: .5; }
        .lpb-canvas-empty p { font-size: 13px; margin: 0; }

        /* Section card di canvas */
        .lp-section-card {
            position: relative;
            border: 1px solid var(--lpb-border);
            border-radius: var(--lpb-radius-md);
            background: var(--lpb-surface-raised);
            margin-bottom: 10px;
            transition: box-shadow var(--lpb-transition), border-color var(--lpb-transition);
        }
        .lp-section-card:hover { border-color: var(--lpb-border-strong); }
        .lp-section-card.is-selected { border-color: var(--lpb-accent); box-shadow: 0 0 0 3px var(--lpb-accent-subtle); }
        .lp-section-card.is-hidden-section { opacity: .55; }
        .lp-card-head {
            display: flex; align-items: center; gap: 8px;
            padding: 7px 10px;
            border-bottom: 1px solid var(--lpb-border);
            border-radius: var(--lpb-radius-md) var(--lpb-radius-md) 0 0;
            background: var(--lpb-workspace);
        }
        .lp-drag-handle { cursor: grab; color: var(--lpb-text-muted); padding: 4px 2px; border: 0; background: transparent; border-radius: 4px; flex: 0 0 auto; }
        .lp-drag-handle:hover { color: var(--lpb-text-secondary); }
        .lp-drag-handle:active { cursor: grabbing; }
        .lp-type-badge { display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 600; color: var(--lpb-text-tertiary); text-transform: uppercase; letter-spacing: .04em; flex: 0 0 auto; }
        .lp-type-badge i { font-size: 11px; }
        .lp-card-title { font-size: 12px; font-weight: 500; color: var(--lpb-text); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1 1 auto; min-width: 0; }
        .lp-card-actions { display: flex; align-items: center; gap: 2px; flex: 0 0 auto; }
        .lp-icon-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 28px; height: 28px; padding: 0;
            border: 0; border-radius: 6px; background: transparent;
            color: var(--lpb-text-muted); cursor: pointer;
            transition: background-color var(--lpb-transition), color var(--lpb-transition);
        }
        .lp-icon-btn:hover { background: var(--lpb-surface); color: var(--lpb-text-secondary); }
        .lp-icon-btn:focus-visible { outline: 2px solid var(--lpb-accent); outline-offset: 1px; }
        .lp-icon-btn i { font-size: 12px; }
        .lp-icon-btn--danger:hover { background: var(--lpb-danger-subtle); color: var(--lpb-danger); }
        .lp-icon-btn--active { background: var(--lpb-accent-subtle); color: var(--lpb-accent); }
        .lp-card-body { padding: 12px 14px; font-size: 12px; color: var(--lpb-text-secondary); }

        /* ===== PROPERTY INSPECTOR ===== */
        .prop-tab {
            padding: 10px 12px; font-size: 12px; font-weight: 500;
            color: var(--lpb-text-tertiary); background: transparent;
            border: 0; border-bottom: 2px solid transparent;
            cursor: pointer; white-space: nowrap;
            transition: color var(--lpb-transition), border-color var(--lpb-transition);
        }
        .prop-tab:hover { color: var(--lpb-text-secondary); }
        .prop-tab.border-indigo-500 { color: var(--lpb-accent); border-bottom-color: var(--lpb-accent); }
        .prop-tab:focus-visible { outline: 2px solid var(--lpb-accent); outline-offset: -2px; }
        .lpb-field { margin-bottom: 12px; }
        .lpb-label { display: block; font-size: 11px; font-weight: 500; color: var(--lpb-text-secondary); margin-bottom: 5px; }
        .lpb-label .req { color: var(--lpb-danger); }
        .lpb-hint { font-size: 10.5px; color: var(--lpb-text-muted); margin-top: 4px; }

        /* Repeater item */
        .lp-item-row { border: 1px solid var(--lpb-border); border-radius: var(--lpb-radius-md); padding: 10px; margin-bottom: 8px; background: var(--lpb-surface-raised); }
        .lp-item-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
        .lp-item-title { font-size: 11px; font-weight: 600; color: var(--lpb-text-secondary); }
        .lp-item-actions { display: flex; gap: 4px; }
        .lp-item-btn {
            display: inline-flex; align-items: center; justify-content: center;
            height: 24px; padding: 0 6px;
            font-size: 10.5px; font-weight: 500;
            border: 0; border-radius: 5px;
            background: var(--lpb-workspace); color: var(--lpb-text-tertiary);
            cursor: pointer; transition: background-color var(--lpb-transition), color var(--lpb-transition);
        }
        .lp-item-btn:hover { background: var(--lpb-surface); color: var(--lpb-text-secondary); }
        .lp-item-btn--danger:hover { background: var(--lpb-danger-subtle); color: var(--lpb-danger); }
        .lpb-input, .lpb-select, .lpb-textarea {
            width: 100%; min-height: var(--lpb-control-h); padding: 0 10px;
            font-size: 12.5px; color: var(--lpb-text);
            background: var(--lpb-surface-raised);
            border: 1px solid var(--lpb-border); border-radius: var(--lpb-radius-md);
            transition: border-color var(--lpb-transition), box-shadow var(--lpb-transition);
        }
        .lpb-input::placeholder, .lpb-textarea::placeholder { color: var(--lpb-text-muted); }
        .lpb-input:focus, .lpb-select:focus, .lpb-textarea:focus { outline: none; border-color: var(--lpb-accent); box-shadow: 0 0 0 3px var(--lpb-accent-subtle); }
        .lpb-textarea { min-height: 72px; padding: 8px 10px; resize: vertical; }
        .lpb-prop-group { padding: 12px 14px; border-bottom: 1px solid var(--lpb-border); }
        .lpb-prop-group:last-child { border-bottom: 0; }
        .lpb-prop-group-title { font-size: 10px; font-weight: 600; color: var(--lpb-text-muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; }
        /* Bug #6 fix: sebelumnya `width:100%; object-fit:cover` memotong logo
           (terlihat besar & terpotong). Sekarang satu bingkai seragam 112px
           dengan `object-fit: contain` sehingga APA PUN (logo panjang, banner
           lebar) tampil utuh tanpa terpotong. */
        .lpb-image-preview { display: block; width: 100%; height: 112px; object-fit: contain; background: var(--lpb-surface-raised); border: 1px solid var(--lpb-border); border-radius: var(--lpb-radius-md); padding: 4px; margin-top: 6px; }
        .lpb-btn-row { display: flex; gap: 8px; margin-bottom: 8px; }

        /* ===== MODAL ===== */
        .lpb-modal { position: fixed; inset: 0; z-index: 9998; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(15, 23, 42, .45); }
        .lpb-modal-dialog { display: flex; flex-direction: column; width: 100%; max-width: 720px; max-height: 80vh; background: var(--lpb-surface); border: 1px solid var(--lpb-border); border-radius: var(--lpb-radius-xl); box-shadow: var(--lpb-shadow-lg); overflow: hidden; }
        .lpb-modal-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 18px; border-bottom: 1px solid var(--lpb-border); flex: 0 0 auto; }
        .lpb-modal-title { font-size: 14px; font-weight: 600; color: var(--lpb-text); margin: 0; }
        .lpb-modal-body { padding: 16px 18px; overflow-y: auto; flex: 1 1 auto; min-height: 0; }
        .lpb-modal-body::-webkit-scrollbar { width: 6px; }
        .lpb-modal-body::-webkit-scrollbar-thumb { background: var(--lpb-border-strong); border-radius: 999px; }
        .lpb-media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; }
        .lpb-media-card { border: 1px solid var(--lpb-border); border-radius: var(--lpb-radius-md); padding: 6px; background: var(--lpb-surface-raised); }
        .lpb-media-card:hover { border-color: var(--lpb-accent-border); }
        .lpb-media-thumb { width: 100%; height: 96px; object-fit: contain; border-radius: var(--lpb-radius-sm); background: var(--lpb-workspace); }
        .lpb-media-path { font-size: 10px; color: var(--lpb-text-muted); margin: 6px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .lpb-media-use { width: 100%; height: 28px; font-size: 11px; font-weight: 500; border: 0; border-radius: var(--lpb-radius-sm); background: var(--lpb-accent); color: #fff; cursor: pointer; transition: background-color var(--lpb-transition); }
        .lpb-media-use:hover { background: var(--lpb-accent-hover); }
        .lpb-media-empty { text-align: center; padding: 40px 16px; color: var(--lpb-text-muted); font-size: 12.5px; }
        .lpb-revision-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 12px; border: 1px solid var(--lpb-border); border-radius: var(--lpb-radius-md); margin-bottom: 8px; background: var(--lpb-surface-raised); }
        .lpb-revision-row.is-published { border-color: transparent; background: var(--lpb-success-subtle); }
        .lpb-revision-num { font-size: 12.5px; font-weight: 600; color: var(--lpb-text); display: inline-flex; align-items: center; gap: 8px; }
        .lpb-revision-badge { font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 999px; background: var(--lpb-success); color: #fff; text-transform: uppercase; letter-spacing: .04em; }
        .lpb-revision-meta { font-size: 11px; color: var(--lpb-text-tertiary); margin-top: 3px; }

        /* ===== SWITCHER PREVIEW DI HEADER =====
           Sengaja TIDAK memakai utility "hidden lg:flex": pada kaskade
           Tailwind v4, .hidden ternyata menang atas .lg\:flex sehingga
           switcher tidak pernah muncul. Aturan eksplisit di bawah ini
           menentukan visibilitas sendiri, jadi tidak bergantung urutan
           utility. Switcher hanya dipasang dari 1200px ke atas karena
           butuh ~210px; di bawah itu digantikan switcher ikon di toolbar
           canvas (kelas .lp-preview-btn yang sama, handler yang sama). */
        #lpb-root .lpb-hdr-preview { display: none; }
        @media (min-width: 1200px) {
            #lpb-root .lpb-hdr-preview { display: inline-flex; }
        }
        /* Switcher cadangan di toolbar canvas: kebalikan dari header. */
        #lpb-root .lpb-canvas-switch { display: inline-flex; }
        @media (min-width: 1200px) {
            #lpb-root .lpb-canvas-switch { display: none; }
        }
        #lpb-root .lpb-canvas-switch .lp-preview-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 34px; height: 30px; padding: 0;
            border: 1px solid var(--lpb-border); border-radius: var(--lpb-radius-md);
            background: var(--lpb-surface-raised); color: var(--lpb-text-tertiary);
            cursor: pointer; transition: background-color var(--lpb-transition), color var(--lpb-transition), border-color var(--lpb-transition);
        }
        #lpb-root .lpb-canvas-switch .lp-preview-btn:hover { border-color: var(--lpb-accent-border); color: var(--lpb-accent); }
        #lpb-root .lpb-canvas-switch .lp-preview-btn.is-active {
            background: var(--lpb-accent-subtle); border-color: var(--lpb-accent); color: var(--lpb-accent);
        }
        #lpb-root .lpb-canvas-switch .lp-preview-btn:focus-visible { outline: 2px solid var(--lpb-accent); outline-offset: 2px; }
        #lpb-root .lpb-canvas-switch .lp-preview-btn svg { width: 14px; height: 14px; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1280px) { .lpb-grid { grid-template-columns: 240px minmax(0, 1fr) 300px; } }
        @media (max-width: 1024px) {
            .lpb-grid { grid-template-columns: minmax(0, 1fr); }
            .lpb-panel { position: static; max-height: none; }
            .lpb-canvas-panel { min-height: 60vh; }
        }

        /* Subtitle header & petunjuk keyboard: tidak berguna di layar sentuh,
           dan keduanya ikut merebut lebar yang membuat judul terpotong. */
        @media (max-width: 767px) {
            #lpb-root .lpb-hdr-sub,
            #lpb-root .lpb-hdr-hints { display: none !important; }
        }

        /* Mobile: header dibiarkan membungkus DUA BARIS.
           Baris 1 = identitas + status, baris 2 = semua tombol aksi.
           Tanpa ini, status + 5 tombol menekan judul sampai tersisa "L..". */
        @media (max-width: 640px) {
            #lpb-root .lpb-hdr { flex-wrap: wrap; row-gap: 8px; padding: 10px; }
            #lpb-root .lpb-hdr-brand  { order: 1; flex: 1 1 auto; min-width: 0; }
            #lpb-root .lpb-hdr-status { order: 2; flex: 0 0 auto; max-width: 46%; }
            #lpb-root .lpb-hdr-status-text {
                display: block; max-width: 100%;
                overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            }
            #lpb-root .lpb-hdr-actions { order: 3; flex: 1 1 100%; justify-content: space-between; }
            #lpb-root .lpb-canvas-scroll { padding: 14px; }
            #lpb-root .lpb-canvas { padding: 14px; }
            #lpb-root .lpb-panel-head { padding: 10px 12px 8px; }
            #lpb-root .lpb-panel-body { padding: 10px 12px; }
        }
        #lpb-root ::-webkit-scrollbar { width: 8px; height: 8px; }
        #lpb-root ::-webkit-scrollbar-track { background: transparent; }
        #lpb-root ::-webkit-scrollbar-thumb { background: var(--lpb-border-strong); border-radius: 999px; }
    </style>

    
    <div class="lpb-hdr flex items-center gap-3 mb-4 p-3 rounded-xl border" style="background:var(--lpb-surface);border-color:var(--lpb-border)">

        <div class="lpb-hdr-brand flex items-center gap-2 min-w-0 flex-1">
            <span class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" style="background:var(--lpb-accent-subtle);color:var(--lpb-accent)">
                <span style="display:block;width:18px;height:18px">{!! $lpbIkon['brand'] !!}</span>
            </span>
            <div class="min-w-0">
                <h1 class="text-[13px] font-semibold truncate" style="color:var(--lpb-text)">Landing Page Builder</h1>
                <p class="lpb-hdr-sub text-[11px] truncate" style="color:var(--lpb-text-tertiary)">Draft tersimpan otomatis ke database</p>
            </div>
        </div>


        <div class="lpb-hdr-preview items-center gap-1 p-1 rounded-lg shrink-0" style="background:var(--lpb-workspace);border:1px solid var(--lpb-border)" role="group" aria-label="Lebar preview">
            <button type="button" data-width="desktop" class="lp-preview-btn is-active px-3 py-1.5 rounded-md text-xs font-medium transition-colors" title="Preview desktop" aria-pressed="true">
                {!! $lpbIkon["desktop"] !!}<span class="ml-1.5">Desktop</span>
            </button>
            <button type="button" data-width="tablet" class="lp-preview-btn px-3 py-1.5 rounded-md text-xs font-medium transition-colors" title="Preview tablet" aria-pressed="false">
                {!! $lpbIkon["tablet"] !!}<span class="ml-1.5">Tablet</span>
            </button>
            <button type="button" data-width="mobile" class="lp-preview-btn px-3 py-1.5 rounded-md text-xs font-medium transition-colors" title="Preview mobile" aria-pressed="false">
                {!! $lpbIkon["mobile"] !!}<span class="ml-1.5">Mobile</span>
            </button>
        </div>

        
        <div class="lpb-hdr-actions flex items-center gap-2 shrink-0">
            <button id="undo-btn" type="button" class="lpb-hdr-btn" title="Undo (Ctrl+Z)" disabled aria-label="Undo">{!! $lpbIkon['undo'] !!}</button>
            <button id="redo-btn" type="button" class="lpb-hdr-btn" title="Redo (Ctrl+Y)" disabled aria-label="Redo">{!! $lpbIkon['redo'] !!}</button>
            <button id="version-history-btn" type="button" class="lpb-hdr-btn" title="Riwayat revisi">{!! $lpbIkon['history'] !!}</button>
            <button id="save-draft-btn" type="button" class="lpb-hdr-btn lpb-hdr-btn--primary" title="Simpan draft (Ctrl+S)">
                {!! $lpbIkon['save'] !!}<span class="hidden sm:inline">Simpan Draft</span>
            </button>
            <button id="publish-btn" type="button" class="lpb-hdr-btn lpb-hdr-btn--success" title="Publikasikan draft">
                {!! $lpbIkon['publish'] !!}<span class="hidden sm:inline">Publish</span>
            </button>
            <button id="preview-website-btn" type="button" class="lpb-hdr-btn lpb-hdr-btn--preview" title="Preview Website (buka di tab baru)">
                {!! $lpbIkon['eye'] !!}<span class="hidden sm:inline">Preview Website</span>
            </button>
        </div>
    </div>

    <style>
        .lpb-hdr-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            height: 36px; min-width: 36px; padding: 0 10px;
            font-size: 12.5px; font-weight: 500;
            border: 1px solid var(--lpb-border); border-radius: var(--lpb-radius-md);
            background: var(--lpb-surface-raised); color: var(--lpb-text-secondary);
            cursor: pointer; white-space: nowrap;
            transition: background-color var(--lpb-transition), border-color var(--lpb-transition), color var(--lpb-transition);
        }
        .lpb-hdr-btn:hover:not(:disabled) { background: var(--lpb-accent-subtle); border-color: var(--lpb-accent-border); color: var(--lpb-accent); }
        .lpb-hdr-btn:disabled { opacity: .45; cursor: not-allowed; }
        .lpb-hdr-btn:focus-visible { outline: 2px solid var(--lpb-accent); outline-offset: 2px; }
        .lpb-hdr-btn--primary { background: var(--orion-electric-violet); border-color: var(--orion-electric-violet); color: var(--orion-on-violet); }
        .lpb-hdr-btn--primary:hover:not(:disabled) { background: #3D45E0; border-color: #3D45E0; color: var(--orion-on-violet); }
        .lpb-hdr-btn--success { background: var(--orion-cta-orange); border-color: var(--orion-cta-orange); color: var(--orion-on-orange); }
        .lpb-hdr-btn--success:hover:not(:disabled) { background: #E85C05; border-color: #E85C05; color: var(--orion-on-orange); }
        /* Preview Website: Electric Violet dengan teks putih (5.29:1 AA).
           CATATAN: klaim lama "Kontras 7.56:1 AAA" untuk #26788E di atas #304C64
           adalah SALAH — kontras sebenarnya hanya 1.81:1 (GAGAL WCAG).
           Root cause Bug #5: latar gelap + teks gelap. */
        .lpb-hdr-btn--preview { background: var(--orion-electric-violet); border-color: var(--orion-electric-violet); color: var(--orion-on-violet); }
        .lpb-hdr-btn--preview:hover:not(:disabled) { background: #3D45E0; border-color: #3D45E0; color: var(--orion-on-violet); }
    </style>

    
    <div class="mb-4 flex items-center gap-2">
        <span id="save-status" class="lpb-hdr-status inline-flex items-center gap-1.5 h-7 px-3 rounded-full text-[11.5px] font-medium border" style="background:var(--lpb-surface-raised);border-color:var(--lpb-border);color:var(--lpb-text-tertiary)" role="status" aria-live="polite">
            <span id="save-status-icon" aria-hidden="true" style="display:none;width:13px;height:13px"></span>
            <span id="save-status-text" class="lpb-hdr-status-text">Siap</span>
        </span>
        <span class="lpb-hdr-hints text-[11px]" style="color:var(--lpb-text-muted)">Ctrl+Z undo &middot; Ctrl+Y redo &middot; Ctrl+S simpan</span>
    </div>

    <div class="lpb-grid">
        
        <div class="order-2 xl:order-1">
            <div class="lpb-panel">
                <div class="lpb-panel-head">
                    <h2 class="lpb-panel-title">Komponen</h2>
                    <p class="lpb-panel-hint">Klik untuk menambahkan section ke canvas.</p>
                </div>
                <div class="lpb-panel-body">
                    <div class="lpb-template-bar" id="lpb-template-bar">
                        <button type="button" id="lpb-apply-template-btn" class="lpb-template-btn" title="Terapkan template Orion Grafika Premium Default ke draft">
                            {!! $lpbIkon['hero'] !!}
                            <span class="lpb-template-btn-text">
                                <strong>ORION GRAFIKA — PREMIUM DEFAULT</strong>
                                <small>Terapkan template ke draft</small>
                            </span>
                        </button>
                    </div>
                    <div class="lpb-search-wrap">
                        {!! $lpbIkon['search'] !!}
                        <input type="text" id="search-component" placeholder="Cari komponen..." class="lpb-search" aria-label="Cari komponen">
                    </div>
                    <div class="lpb-component-list" id="component-list">
                        <button type="button" data-type="hero" class="component-item" title="Hero — judul besar, subjudul, tombol, gambar">{!! $lpbIkon['hero'] !!}Hero</button>
                        <button type="button" data-type="about" class="component-item" title="About — deskripsi perusahaan">{!! $lpbIkon['about'] !!}About</button>
                        <button type="button" data-type="services" class="component-item" title="Services — daftar layanan">{!! $lpbIkon['services'] !!}Services</button>
                        <button type="button" data-type="product-grid" class="component-item" title="Product Grid — kisi produk">{!! $lpbIkon['product'] !!}Product Grid</button>
                        <button type="button" data-type="portfolio" class="component-item" title="Portfolio — galeri karya">{!! $lpbIkon['portfolio'] !!}Portfolio</button>
                        <button type="button" data-type="testimonials" class="component-item" title="Testimonials — kutipan klien">{!! $lpbIkon['quote'] !!}Testimonials</button>
                        <button type="button" data-type="statistics" class="component-item" title="Statistics — angka & pencapaian">{!! $lpbIkon['chart'] !!}Statistics</button>
                        <button type="button" data-type="faq" class="component-item" title="FAQ — pertanyaan & jawaban">{!! $lpbIkon['faq'] !!}FAQ</button>
                        <button type="button" data-type="cta" class="component-item" title="CTA — ajakan bertindak">{!! $lpbIkon['cta'] !!}CTA</button>
                        <button type="button" data-type="contact" class="component-item" title="Contact — formulir kontak">{!! $lpbIkon['contact'] !!}Contact</button>
                        <button type="button" data-type="footer" class="component-item" title="Footer — kaki halaman">{!! $lpbIkon['footer'] !!}Footer</button>
                        <button type="button" data-type="header" class="component-item" title="Header/Navbar — logo, menu, tombol staf">{!! $lpbIkon['hero'] !!}Header/Navbar</button>
                        <button type="button" data-type="posts" class="component-item" title="Kabar &amp; Panduan — artikel publik ERP">{!! $lpbIkon['news'] ?? $lpbIkon['quote'] !!}Kabar &amp; Panduan</button>
                        <p id="component-search-empty" class="lpb-search-empty" hidden>Tidak ada komponen yang cocok.</p>
                    </div>
                </div>
            </div>
        </div>

        
        <div class="order-1 xl:order-2">
            <div class="lpb-canvas-panel">
                <div class="lpb-canvas-toolbar">
                    <div class="lpb-canvas-switch" role="group" aria-label="Lebar preview">
                        <button type="button" data-width="desktop" class="lp-preview-btn is-active" title="Preview desktop" aria-pressed="true">{!! $lpbIkon['desktop'] !!}</button>
                        <button type="button" data-width="tablet" class="lp-preview-btn" title="Preview tablet" aria-pressed="false">{!! $lpbIkon['tablet'] !!}</button>
                        <button type="button" data-width="mobile" class="lp-preview-btn" title="Preview mobile" aria-pressed="false">{!! $lpbIkon['mobile'] !!}</button>
                    </div>
                    <span class="lpb-canvas-label" id="preview-size-label">Lebar: desktop</span>
                    <span class="lpb-canvas-label" id="canvas-count-label">0 section</span>
                </div>
                <div class="lpb-canvas-scroll">
                    <div id="canvas" class="lpb-canvas" tabindex="-1" aria-label="Area canvas landing page">
                        <div class="lpb-canvas-empty" id="canvas-empty-msg">
                            {!! $lpbIkon['brand'] !!}
                            <p>Klik komponen di panel kiri untuk menambahkan section.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        
        <div class="order-3">
            <div class="lpb-panel">
                <div class="lpb-panel-head">
                    <h2 class="lpb-panel-title">Properti</h2>
                    <p class="lpb-panel-hint">Pilih section di canvas untuk mengedit.</p>
                </div>

                <div class="flex gap-2 mb-2 border-b" style="border-color:var(--lpb-border)" role="tablist">
                    <button type="button" data-tab="content" class="prop-tab" style="color:var(--orion-deep-night); border-color:var(--orion-deep-night);">Content</button>
                    <button type="button" data-tab="layout" class="prop-tab" style="color:var(--lpb-text-tertiary); border-color:transparent;">Layout</button>
                    <button type="button" data-tab="style" class="prop-tab" style="color:var(--lpb-text-tertiary); border-color:transparent;">Style</button>
                    <button type="button" data-tab="motion" class="prop-tab" style="color:var(--lpb-text-tertiary); border-color:transparent;">Motion</button>
                </div>

                <div class="lpb-panel-body" id="prop-scroll">
                    <div id="prop-empty-msg" class="text-xs text-center py-6" style="color:var(--lpb-text-muted)">
                        Pilih satu section di canvas untuk mengedit propertinya.
                    </div>

                    <div id="prop-fields" class="hidden">
                        
                        <div id="tab-content" class="prop-panel">
                            <div class="lpb-prop-group">
                                <div class="lpb-prop-group-title">Tipe</div>
                                <p id="prop-type-readonly" class="lpb-hint" style="margin-top:0"></p>
                            </div>

                            <div class="lpb-prop-group">
                                <div class="lpb-prop-group-title">Konten</div>
                                <div class="lpb-field">
                                    <label class="lpb-label" for="prop-title">Judul <span class="req">*</span></label>
                                    <input type="text" id="prop-title" maxlength="200" class="lpb-input" placeholder="Judul section">
                                </div>
                                <div class="lpb-field">
                                    <label class="lpb-label" for="prop-subtitle">Subjudul</label>
                                    <input type="text" id="prop-subtitle" maxlength="300" class="lpb-input" placeholder="Subjudul section">
                                </div>
                                <div class="lpb-field" style="margin-bottom:0">
                                    <label class="lpb-label" for="prop-desc">Deskripsi</label>
                                    <textarea id="prop-desc" maxlength="1000" rows="3" class="lpb-textarea" placeholder="Deskripsi section"></textarea>
                                </div>
                            </div>

                            <div class="lpb-prop-group" id="prop-image-block">
                                <div class="lpb-prop-group-title">Gambar</div>
                                <div class="lpb-btn-row">
                                    <button type="button" id="prop-image-pick" class="lpb-hdr-btn" style="flex:1">Pilih dari Media</button>
                                    <button type="button" id="prop-image-clear" class="lpb-hdr-btn" style="flex:0 0 auto;color:var(--lpb-danger)">Hapus</button>
                                </div>
                                <div class="lpb-field">
                                    <label class="lpb-label" for="prop-image-url">URL Gambar</label>
                                    <input type="text" id="prop-image-url" class="lpb-input" placeholder="landing-media/... atau https://...">
                                    <p class="lpb-hint">Hanya path <code>landing-media/</code> atau URL http/https.</p>
                                </div>
                                <img id="prop-image-preview" alt="" class="lpb-image-preview" style="display:none" loading="eager">
                                <div class="lpb-field" style="margin-bottom:0">
                                    <label class="lpb-label" for="prop-alt-text">Alt Text</label>
                                    <input type="text" id="prop-alt-text" maxlength="255" class="lpb-input" placeholder="Deskripsi gambar untuk aksesibilitas">
                                </div>
                            </div>

                            <div class="lpb-prop-group" id="prop-button-block">
                                <div class="lpb-prop-group-title">Tombol</div>
                                <div class="lpb-field">
                                    <label class="lpb-label" for="prop-button-label">Label Tombol</label>
                                    <input type="text" id="prop-button-label" maxlength="50" class="lpb-input" placeholder="Contoh: Baca Selengkapnya">
                                </div>
                                <div class="lpb-field" style="margin-bottom:0">
                                    <label class="lpb-label" for="prop-button-url">URL Tombol</label>
                                    <input type="text" id="prop-button-url" class="lpb-input" placeholder="https:// atau /halaman">
                                </div>
                            </div>

                            {{-- Tombol kedua Hero (Fase 2 Final). Hanya tampil untuk
                                 tipe hero. Data ini disimpan sebagai content_json biasa,
                                 jadi tetap bisa diedit, di-undo, dan di-reload. --}}
                            <div class="lpb-prop-group" id="prop-hero2-block" style="display:none">
                                <div class="lpb-prop-group-title">Tombol Kedua (Hero)</div>
                                <div class="lpb-field">
                                    <label class="lpb-label" for="prop-button2-label">Label Tombol Kedua</label>
                                    <input type="text" id="prop-button2-label" maxlength="50" class="lpb-input" placeholder="Contoh: Jelajahi Layanan">
                                </div>
                                <div class="lpb-field">
                                    <label class="lpb-label" for="prop-button2-url">URL Tombol Kedua</label>
                                    <input type="text" id="prop-button2-url" class="lpb-input" placeholder="https://, /halaman, atau #anchor">
                                </div>
                                <div class="lpb-field">
                                    <label class="lpb-label" for="prop-button2-style">Gaya Tombol Kedua</label>
                                    <select id="prop-button2-style" class="lpb-select">
                                        <option value="primary">Primer (Electric Violet)</option>
                                        <option value="dark">Gelap</option>
                                        <option value="outline">Outline</option>
                                        <option value="link">Teks Link</option>
                                    </select>
                                </div>
                                <p class="lpb-hint" style="margin:0">
                                    Alignment mengikuti baris tombol Hero dan diatur di tab Layout.
                                </p>
                            </div>
                            {{-- P0 HERO — kontrol komposisi visual (gambar02) --}}
                            <div class="lpb-prop-group" id="prop-hero-visual-block" style="display:none">
                                <div class="lpb-prop-group-title">Visual Hero (Gambar)</div>
                                <div class="lpb-field">
                                    <label class="lpb-label" for="prop-hero-object-position">Posisi Objek</label>
                                    <select id="prop-hero-object-position" class="lpb-select">
                                        <option value="center">Tengah</option>
                                        <option value="top">Atas</option>
                                        <option value="bottom">Bawah</option>
                                        <option value="left">Kiri</option>
                                        <option value="right">Kanan</option>
                                    </select>
                                </div>
                                <div class="lpb-field">
                                    <label class="lpb-label" for="prop-hero-image-scale">Skala Gambar (%)</label>
                                    <input type="number" id="prop-hero-image-scale" class="lpb-input" min="60" max="180" step="5" value="100">
                                </div>
                                <div class="lpb-field" style="display:flex;align-items:center;gap:8px">
                                    <input type="checkbox" id="prop-hero-bleed" style="width:16px;height:16px">
                                    <label class="lpb-label" for="prop-hero-bleed" style="margin:0">Bleed ke tepi kanan</label>
                                </div>
                                <p class="lpb-hint" style="margin:0">
                                    Gunakan PNG/WebP transparan agar visual menyatu dengan background (tidak dipotong persegi).
                                    Masukkan aset lewat tab Content &rarr; field Gambar.
                                </p>
                            </div>

                            {{-- Panel khusus section 'header' (Fase 2). Hanya tampil
                                 untuk tipe header; disimpan sebagai data biasa
                                 sehingga tetap bisa diedit & di-undo. --}}
                            <div class="lpb-prop-group" id="prop-header-block" style="display:none">
                                <div class="lpb-prop-group-title">Pengaturan Header</div>
                                <div class="lpb-field">
                                    <label class="lpb-label" for="prop-header-cta">Label Tombol Hubungi</label>
                                    <input type="text" id="prop-header-cta" maxlength="50" class="lpb-input" placeholder="Contoh: Hubungi Kami">
                                </div>
                                <div class="lpb-field">
                                    <label class="lpb-label" for="prop-header-sticky">Header sticky saat scroll?</label>
                                    <label class="flex items-center gap-2 text-xs" style="cursor:pointer">
                                        <input type="checkbox" id="prop-header-sticky" class="rounded">
                                        <span>Tempel di atas halaman</span>
                                    </label>
                                </div>
                                <p class="lpb-hint" style="margin:0">
                                    URL tombol "Masuk Staf" diisi di blok Tombol di atas.
                                    Gunakan URL halaman masuk resmi ERP. Renderer tidak
                                    pernah menebak alamat login.
                                </p>
                            </div>

                            {{-- Panel khusus section 'posts' (Fase 2). Artikel
                                 diambil otomatis dari tulisan ERP yang berstatus
                                 terbit DAN ditandai tampil_publik. --}}
                            <div class="lpb-prop-group" id="prop-posts-block" style="display:none">
                                <div class="lpb-prop-group-title">Kabar &amp; Panduan</div>
                                <div class="lpb-field">
                                    <label class="lpb-label" for="prop-posts-limit">Jumlah Artikel</label>
                                    <input type="number" id="prop-posts-limit" min="1" max="12" step="1" class="lpb-input" value="6">
                                </div>
                                <div class="lpb-field">
                                    <label class="lpb-label" for="prop-posts-category">Slug Kategori (opsional)</label>
                                    <input type="text" id="prop-posts-category" maxlength="120" class="lpb-input" placeholder="Contoh: promo, panduan">
                                    <p class="lpb-hint" style="margin:4px 0 0">
                                        Kosongkan untuk menampilkan semua kategori publik.
                                    </p>
                                </div>
                                <div class="lpb-field">
                                    <label class="flex items-center gap-2 text-xs" style="cursor:pointer">
                                        <input type="checkbox" id="prop-posts-showimage" class="rounded" checked>
                                        <span>Tampilkan gambar sampul</span>
                                    </label>
                                </div>
                                <div class="lpb-field" style="margin-bottom:0">
                                    <label class="flex items-center gap-2 text-xs" style="cursor:pointer">
                                        <input type="checkbox" id="prop-posts-showdate" class="rounded" checked>
                                        <span>Tampilkan tanggal terbit</span>
                                    </label>
                                </div>
                                <p class="lpb-hint" style="margin:8px 0 0">
                                    Hanya tulisan berstatus <strong>terbit</strong>, sudah melewati
                                    waktu publikasi, dan ditandai <strong>tampil publik</strong> yang
                                    dimuat. Tulisan internal, draft, terjadwal, dan yang
                                    dihapus tidak pernah muncul di sini.
                                </p>
                            </div>

                            <div class="lpb-prop-group" id="prop-items-block" style="display:none">
                                <div class="lpb-prop-group-title">
                                    Item
                                    <button type="button" id="prop-item-add" class="lpb-hdr-btn" style="height:26px;padding:0 10px;font-size:11px">+ Tambah</button>
                                </div>
                                <p class="lpb-hint" style="margin-bottom:10px">Maksimal 50 item per section.</p>
                                <div id="prop-items-list"></div>
                            </div>
                        </div>

                        
                        <div id="tab-layout" class="prop-panel hidden">
                            <div class="lpb-prop-group">
                                <div class="lpb-prop-group-title">Container</div>
                                <div class="lpb-field">
                                    <label class="lpb-label" for="prop-layout-width">Lebar</label>
                                    <select id="prop-layout-width" class="lpb-select">
                                        <option value="full">Full width</option>
                                        <option value="container">Container</option>
                                        <option value="narrow">Narrow</option>
                                    </select>
                                </div>
                                <div class="lpb-field">
                                    <label class="lpb-label">Perataan</label>
                                    <div class="flex gap-2">
                                        <label class="flex-1 text-xs"><input type="radio" name="prop-layout-align" value="left" class="rounded"> Kiri</label>
                                        <label class="flex-1 text-xs"><input type="radio" name="prop-layout-align" value="center" class="rounded"> Tengah</label>
                                        <label class="flex-1 text-xs"><input type="radio" name="prop-layout-align" value="right" class="rounded"> Kanan</label>
                                    </div>
                                </div>
                                <div class="lpb-field">
                                    <label class="lpb-label" for="prop-layout-columns">Kolom</label>
                                    <select id="prop-layout-columns" class="lpb-select">
                                        <option value="1">1 Kolom</option>
                                        <option value="2">2 Kolom</option>
                                        <option value="3">3 Kolom</option>
                                        <option value="4">4 Kolom</option>
                                    </select>
                                </div>
                                <div class="lpb-field" style="margin-bottom:0">
                                    <label class="lpb-label" for="prop-layout-spacing">Spasi Item</label>
                                    <select id="prop-layout-spacing" class="lpb-select">
                                        <option value="small">Kecil</option>
                                        <option value="medium">Sedang</option>
                                        <option value="large">Besar</option>
                                    </select>
                                </div>
                            </div>

                            <div class="lpb-prop-group">
                                <div class="lpb-prop-group-title">Padding (px)</div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="lpb-field">
                                        <label class="lpb-label" for="prop-padding-top">Atas</label>
                                        <input type="number" id="prop-padding-top" min="0" max="200" step="4" class="lpb-input">
                                    </div>
                                    <div class="lpb-field" style="margin-bottom:0">
                                        <label class="lpb-label" for="prop-padding-bottom">Bawah</label>
                                        <input type="number" id="prop-padding-bottom" min="0" max="200" step="4" class="lpb-input">
                                    </div>
                                </div>
                            </div>

                            <div class="lpb-prop-group">
                                <div class="lpb-prop-group-title">Margin (px)</div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="lpb-field">
                                        <label class="lpb-label" for="prop-margin-top">Atas</label>
                                        <input type="number" id="prop-margin-top" min="-200" max="200" step="4" class="lpb-input">
                                    </div>
                                    <div class="lpb-field" style="margin-bottom:0">
                                        <label class="lpb-label" for="prop-margin-bottom">Bawah</label>
                                        <input type="number" id="prop-margin-bottom" min="-200" max="200" step="4" class="lpb-input">
                                    </div>
                                </div>
                            </div>
                        </div>

                        
                        <div id="tab-style" class="prop-panel hidden">
                            <div class="lpb-prop-group">
                                <div class="lpb-prop-group-title">Warna</div>
                                <div class="lpb-field">
                                    <label class="lpb-label" for="prop-style-bg">Background</label>
                                    <select id="prop-style-bg" class="lpb-select">
                                        <option value="white">Putih</option>
                                        <option value="gray">Abu-abu</option>
                                        <option value="night">Deep Night (Hero)</option>
                                        <option value="orion">Brand Orion</option>
                                        <option value="indigo">Indigo (lama)</option>
                                    </select>
                                </div>
                                <div class="lpb-field">
                                    <label class="lpb-label" for="prop-style-color">Warna Teks</label>
                                    <select id="prop-style-color" class="lpb-select">
                                        <option value="black">Hitam</option>
                                        <option value="gray">Abu-abu</option>
                                        <option value="white">Putih</option>
                                        <option value="orion">Brand Orion</option>
                                        <option value="indigo">Indigo (lama)</option>
                                    </select>
                                </div>
                                <div class="lpb-field">
                                    <label class="lpb-label" for="prop-style-radius">Radius</label>
                                    <select id="prop-style-radius" class="lpb-select">
                                        <option value="none">Tanpa radius</option>
                                        <option value="small">Kecil</option>
                                        <option value="medium">Sedang</option>
                                        <option value="large">Besar</option>
                                    </select>
                                </div>
                                <div class="lpb-field" style="margin-bottom:0">
                                    <label class="lpb-label" for="prop-font-size">Ukuran Font (px)</label>
                                    <input type="number" id="prop-font-size" min="12" max="96" step="1" class="lpb-input">
                                    <p class="lpb-hint">Rentang 12&ndash;96.</p>
                                </div>
                            </div>
                        </div>

                        
                        <div id="tab-motion" class="prop-panel hidden">
                            <div class="lpb-prop-group">
                                <div class="lpb-prop-group-title">Animasi</div>
                                <div class="lpb-field" style="margin-bottom:0">
                                    <label class="lpb-label" for="prop-motion">Motion</label>
                                    <select id="prop-motion" class="lpb-select">
                                        <option value="none">Tanpa animasi</option>
                                        <option value="fade">Fade</option>
                                        <option value="fade-up">Fade-up</option>
                                        <option value="slide">Slide halus</option>
                                    </select>
                                    <p class="lpb-hint">Dihormati juga oleh prefers-reduced-motion pengguna.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<div id="version-history-modal" class="lpb-modal" style="display:none" role="dialog" aria-modal="true" aria-labelledby="vh-title">
    <div class="lpb-modal-dialog">
        <div class="lpb-modal-head">
            <h3 class="lpb-modal-title" id="vh-title">Version History</h3>
            <button type="button" id="close-version-history" class="lpb-icon-btn" aria-label="Tutup">{!! $lpbIkon['close'] !!}</button>
        </div>
        <div id="version-history-list" class="lpb-modal-body">
            <p class="lpb-media-empty">Memuat...</p>
        </div>
    </div>
</div>


<div id="media-modal" class="lpb-modal" style="display:none" role="dialog" aria-modal="true" aria-labelledby="mm-title">
    <div class="lpb-modal-dialog" style="max-width:820px">
        <div class="lpb-modal-head">
            <h3 class="lpb-modal-title" id="mm-title">Pilih Gambar</h3>
            <button type="button" id="close-media-modal" class="lpb-icon-btn" aria-label="Tutup">{!! $lpbIkon['close'] !!}</button>
        </div>
        <div class="lpb-modal-body">
            <div style="margin-bottom:14px">
                <label class="lpb-label" for="media-file-input">Upload gambar baru</label>
                <div class="flex gap-2">
                    <input type="file" id="media-file-input" accept="image/jpeg,image/png,image/gif,image/webp" class="lpb-input" style="flex:1">
                    <button type="button" id="media-upload-btn" class="lpb-hdr-btn lpb-hdr-btn--primary" style="flex:0 0 auto">Upload</button>
                </div>
                <p class="lpb-hint">JPEG, PNG, GIF, WebP. Maks 10 MB dan 4096&times;4096.</p>
            </div>
            <div id="media-grid" class="lpb-media-grid">
                <p class="lpb-media-empty" style="grid-column:1/-1">Memuat...</p>
            </div>
        </div>
    </div>
</div>

@push('scripts')

<script>
(function () {
    'use strict';

    // ================================================================
    //  Konfigurasi
    // ================================================================
    // Ikon builder: satu sumber dengan markup Blade ($lpbIkon). SVG inline,
    // jadi tidak bergantung pada stylesheet eksternal yang bisa gagal muat.
    var ICON = @json($lpbIkon);

    // URL dasar storage: dibuat dinamis dari Blade supaya benar baik di
    // testing (root) maupun saat fitur diaktifkan di ERP (sub-folder).
    var STORAGE_BASE = @json(url('storage'));

    var ROUTES = {
        draftShow: @json(route('landing-page-builder.draft.show')),
        draftSave: @json(route('landing-page-builder.draft.save')),
        publish:   @json(route('landing-page-builder.publish')),
        previewDraft: @json(route('landing-page-builder.preview.draft')),
        revisions: @json(route('landing-page-builder.revisions.list')),
        restore:   @json(route('landing-page-builder.revisions.restore', ['revisionNumber' => '__REV__'])),
        mediaList: @json(route('landing-page-builder.media.list')),
        mediaUpload: @json(route('landing-page-builder.media.upload')),
        mediaDelete: @json(route('landing-page-builder.media.delete', ['id' => 0])),
        csrf: @json(csrf_token())
    };

    var AUTOSAVE_DEBOUNCE = 2000;
    var MAX_RETRIES = 3;
    var MAX_ITEMS = 50;

    // Bentuk item per tipe section. Menentukan field apa yang bisa diisi
    // dan berapa panjang maksimum-nya (dicocokkan dengan validasi server).
    var ITEM_SCHEMA = {
        'hero':         [],
        'about':        [],
        'cta':          [],
        'footer':       [],
        'services':     [
            { key: 'title', label: 'Judul', kind: 'text', max: 200 },
            { key: 'desc',  label: 'Deskripsi', kind: 'textarea', max: 500 }
        ],
        'product-grid': [
            { key: 'title', label: 'Judul', kind: 'text', max: 200 },
            { key: 'desc',  label: 'Deskripsi', kind: 'textarea', max: 500 },
            { key: 'image', label: 'Gambar', kind: 'image', max: 500 },
            { key: 'alt_text', label: 'Alt Text', kind: 'text', max: 255 }
        ],
        'portfolio': [
            { key: 'title', label: 'Judul', kind: 'text', max: 200 },
            { key: 'image', label: 'Gambar', kind: 'image', max: 500 },
            { key: 'alt_text', label: 'Alt Text', kind: 'text', max: 255 },
            { key: 'category', label: 'Kategori', kind: 'text', max: 120 }
        ],
        'testimonials': [
            { key: 'quote',  label: 'Kutipan', kind: 'textarea', max: 1000 },
            { key: 'author', label: 'Penulis', kind: 'text', max: 120 }
        ],
        'statistics': [
            { key: 'value', label: 'Angka', kind: 'text', max: 60 },
            { key: 'label', label: 'Label', kind: 'text', max: 200 }
        ],
        'faq': [
            { key: 'question', label: 'Pertanyaan', kind: 'textarea', max: 300 },
            { key: 'answer',   label: 'Jawaban', kind: 'textarea', max: 2000 }
        ],
        'contact': [
            { key: 'label', label: 'Label', kind: 'text', max: 200 },
            { key: 'value', label: 'Nilai', kind: 'text', max: 200 }
        ],
        // Menu navbar: label = teks yang tampil, value = URL tujuan.
        'header': [
            { key: 'label', label: 'Label Menu', kind: 'text', max: 200 },
            { key: 'value', label: 'URL Menu', kind: 'text', max: 200 }
        ],
        // 'posts' sengaja tanpa ITEM_SCHEMA: isinya diambil dari
        // Post::publik() di server, bukan dari repeater manual.
        'posts': []
    };

    // Tipe yang bisa punya tombol.
    var BUTTON_TYPES = { 'hero': true, 'cta': true, 'header': true };
    // Tipe yang punya blok gambar utama.
    var IMAGE_TYPES = { 'hero': true, 'about': true, 'services': true, 'cta': true, 'portfolio': true, 'header': true };

    // ================================================================
    //  TEMPLATE PRESET: ORION GRAFIKA - PREMIUM DEFAULT
    // ------------------------------------------------------------
    //  Data di bawah adalah DATA DRAFT BIASA, bukan HTML terpisah.
    //  Setiap section di sini tetap bisa diedit penuh lewat inspector,
    //  karena strukturnya sama persis dengan section manual.
    //
    //  Aturan yang dijaga:
    //  - ID memakai format s-[a-z0-9]{9} yang diminta server.
    //  - Slot gambar sengaja KOSONG. Tidak ada gambar rekaan yang
    //    disamarakan sebagai dokumentasi pelanggan.
    //  - Tidak ada nomor telepon / WhatsApp / email contoh. Field
    //    kontak diisi Administrator dari AppSetting ERP.
    //  - Testimonial sengaja KOSONG (tidak mengarang ulasan).
    //  - Diterapkan lewat jalur draft yang sama, sehingga optimistic
    //    locking, revisi, dan undo/redo tetap berlaku.
    // ================================================================
        var ORION_TEMPLATE = {
        key: 'orion-grafika-premium-default',
        nama: 'ORION GRAFIKA \u2014 PREMIUM DEFAULT',
        versi: 2,
        sections: [
            {
                type: 'header',
                title: 'ORION GRAFIKA',
                subtitle: 'Percetakan \u00b7 Sampang',
                headerCta: 'Hubungi Kami',
                buttonLabel: 'Masuk Staf',
                buttonUrl: '/masuk',
                image: '',
                alt_text: 'Logo Orion Grafika',
                sticky: true,
                items: [
                    { label: 'Beranda', value: '/' },
                    { label: 'Tentang', value: '#tentang' },
                    { label: 'Layanan', value: '#layanan' },
                    { label: 'Produk', value: '#produk' },
                    { label: 'Portofolio', value: '#portofolio' },
                    { label: 'Kontak', value: '#kontak' },
                    { label: 'FAQ', value: '#faq' }
                ],
                layoutWidth: 'full', layoutAlign: 'left', layoutColumns: 1,
                styleBg: 'white', styleColor: 'black', styleRadius: 'none',
                paddingTop: 0, paddingBottom: 0, fontSize: 16,
                motion: 'none', visible: true
            },
            {
                type: 'hero',
                title: 'Ide Besar, Hasil Cetak Berkelas.',
                subtitle: 'PERCETAKAN DIGITAL SAMPANG',
                desc: 'Spanduk usaha, jersey tim, atau materi promosi Orion Grafika siap disesuaikan dengan desain, bahan, dan finishing terbaik untuk tampilan yang lebih tajam.',
                buttonLabel: 'Konsultasi Cetak',
                buttonUrl: '',
                buttonLabel2: 'Jelajahi Layanan',
                buttonUrl2: '#layanan',
                button2Style: 'outline',
                image: '',
                alt_text: 'Contoh hasil cetak Orion Grafika',
                layoutWidth: 'container', layoutAlign: 'left', layoutColumns: 2,
                styleBg: 'white', styleColor: 'black', styleRadius: 'none',
                paddingTop: 0, paddingBottom: 0, fontSize: 16,
                motion: 'fade-up', visible: true
            },
            {
                type: 'services',
                title: 'Bekerja smarter, bukan lebih mahal.',
                subtitle: 'Keunggulan Orion Grafika',
                desc: '',
                buttonLabel: '', buttonUrl: '',
                image: '', alt_text: '',
                items: [
                    { title: 'Konsultasi Desain', desc: 'Rekomendasi layout dan bahan sesuai kebutuhan Anda.' },
                    { title: 'Beragam Bahan', desc: 'Pilihan vinyl dan media yang cocok untuk setiap proyek.' },
                    { title: 'Ukuran Custom', desc: 'Ukuran menyesuaikan kebutuhan branding dan acara Anda.' },
                    { title: 'Produksi Cepat', desc: 'Proses cetak dan finishing dengan waktu yang disepakati.' }
                ],
                layoutWidth: 'container', layoutAlign: 'left', layoutColumns: 4,
                styleBg: 'white', styleColor: 'black', styleRadius: 'none',
                paddingTop: 0, paddingBottom: 0, fontSize: 16,
                motion: 'fade', visible: true
            },
            {
                type: 'about',
                title: 'Lebih dari sekadar mencetak. Kami membantu ide menjadi nyata.',
                subtitle: 'TENTANG KAMI',
                desc: 'Orion Grafika adalah usaha percetakan di Sampang yang membantu realize kebutuhan cetak Anda dengan desain, bahan, dan finishing yang tepat.\n\n01 - Desain dan Persiapan\n02 - Produksi\n03 - Finishing\n04 - Pengambilan',
                buttonLabel: '', buttonUrl: '',
                image: '',
                alt_text: 'Proses Orion Grafika',
                layoutWidth: 'container', layoutAlign: 'left', layoutColumns: 2,
                styleBg: 'white', styleColor: 'black', styleRadius: 'none',
                paddingTop: 0, paddingBottom: 0, fontSize: 16,
                motion: 'fade-up', visible: true
            },
            {
                type: 'product-grid',
                title: 'Semua kebutuhan cetak, satu tempat.',
                subtitle: 'KATEGORI LAYANAN',
                desc: 'Contoh kategori layanan yang lazim dipesan. Ganti gambar dan deskripsi sesuai layanan yang benar-benar tersedia.',
                buttonLabel: 'Konsultasi',
                buttonUrl: '#kontak',
                image: '', alt_text: '',
                items: [
                    { title: 'Spanduk dan Banner', desc: 'Ukuran besar atau kecil, untuk event maupun brand Anda.', image: '', alt_text: '' },
                    { title: 'Jersey Custom Tim', desc: 'Jersey printing dan sublimasi untuk tim dan komunitas.', image: '', alt_text: '' },
                    { title: 'Stiker dan Label', desc: 'Stiker vinyl dan chromo, potong sesuai bentuk desain.', image: '', alt_text: '' },
                    { title: 'DTF dan Sublimasi', desc: 'Transfer ke berbagai jenis kain dengan warna kuat.', image: '', alt_text: '' },
                    { title: 'Brosur dan Kartu Nama', desc: 'Materi promosi dan identitas usaha dengan desain rapi.', image: '', alt_text: '' },
                    { title: 'Cetak Foto', desc: 'Cetak foto berbagai ukuran dan bahan kertas.', image: '', alt_text: '' },
                    { title: 'Undangan Custom', desc: 'Undangan cetak untuk acara dan undangan digital.', image: '', alt_text: '' },
                    { title: 'ID Card dan Lainnya', desc: 'ID card, totem, dan kebutuhan cetak usaha lainnya.', image: '', alt_text: '' }
                ],
                layoutWidth: 'container', layoutAlign: 'left', layoutColumns: 4,
                styleBg: 'gray', styleColor: 'black', styleRadius: 'none',
                paddingTop: 0, paddingBottom: 0, fontSize: 16,
                motion: 'fade', visible: true
            },
            {
                type: 'portfolio',
                title: 'Karya cetak yang berbicara.',
                subtitle: 'PORTOFOLIO',
                desc: 'Tata letak galeri yang fleksibel. Isi hanya foto hasil kerja yang memang boleh ditampilkan publik.',
                buttonLabel: '', buttonUrl: '',
                image: '', alt_text: '',
                items: [
                    { title: 'Backdrop dan Banner', desc: 'Backdrop printing untuk acara dan panggung.', image: '', alt_text: '' },
                    { title: 'Jersey Sublimasi Tim', desc: 'Jersey tim dengan warna tahan lama.', image: '', alt_text: '' },
                    { title: 'Stiker Brand', desc: 'Stiker kustom untuk kemasan produk.', image: '', alt_text: '' },
                    { title: 'Brosur dan Katalog', desc: 'Brosur dan katalog promosi usaha.', image: '', alt_text: '' },
                    { title: 'Spanduk dan Banner', desc: 'Spanduk usaha dan event dengan bahan tahan cuaca.', image: '', alt_text: '' },
                    { title: 'Undangan Custom', desc: 'Undangan cetak untuk acara spesial.', image: '', alt_text: '' }
                ],
                layoutWidth: 'container', layoutAlign: 'left', layoutColumns: 3,
                styleBg: 'white', styleColor: 'black', styleRadius: 'none',
                paddingTop: 0, paddingBottom: 0, fontSize: 16,
                motion: 'fade', visible: true
            },
            {
                type: 'posts',
                title: 'Kabar dan Inspirasi Cetak.',
                subtitle: 'TINDAKAN DAN INSPIRASI',
                desc: 'Bacaan dan informativa regarding update terbaru dan inspirational posting dari Orion Grafika. Tips dan berita Printing.',
                buttonLabel: 'Baca Artikel Lengkap',
                buttonUrl: '',
                image: '', alt_text: '',
                items: [],
                postLimit: 6,
                postCategory: '',
                showImage: true,
                showDate: true,
                layoutWidth: 'container', layoutAlign: 'left', layoutColumns: 3,
                styleBg: 'gray', styleColor: 'black', styleRadius: 'none',
                paddingTop: 0, paddingBottom: 0, fontSize: 16,
                motion: 'fade', visible: true
            },
            {
                type: 'testimonials',
                title: 'Kepuasan pelanggan adalah-Atlantic dan pengalaman nyata.',
                subtitle: 'TESTIMONI',
                desc: 'Bagikan pengalaman mereka bersama Orion Grafika untuk membantu Anda dan tim Anda.',
                buttonLabel: '', buttonUrl: '',
                image: '', alt_text: '',
                items: [],
                layoutWidth: 'container', layoutAlign: 'left', layoutColumns: 2,
                styleBg: 'white', styleColor: 'black', styleRadius: 'none',
                paddingTop: 0, paddingBottom: 0, fontSize: 16,
                motion: 'fade', visible: true
            },
            {
                type: 'faq',
                title: 'Pertanyaan yang sering ditanyakan.',
                subtitle: 'FAQ',
                desc: 'Semua pertanyaan yang sering muncul. Jawaban masih draft dan perlu diverifikasi operator sebelum dipakai sebagai kebijakan resmi.',
                buttonLabel: '', buttonUrl: '',
                image: '', alt_text: '',
                items: [
                    { question: 'Apakah menerima ukuran custom?', answer: 'Layanan apa yang tersedia untuk ukuran, bahan, dan event Anda. Silakan pilih kategori layanan yang sesuai atau hubungi tim kami untuk rekomendasi.' },
                    { question: 'Bagaimana proses pemesanan?', answer: 'Konsultasikan kebutuhan Anda, pilih desain dan bahan, lalu tim kami mengonfirmasi jadwal produksi sebelum finishing.' },
                    { question: 'Berapa lama pesanan selesai?', answer: 'Tergantung jenis produk, jumlah, dan tingkat kompleksitas. Estimasi waktu diberikan setelah file desain Anda diterima dan disetujui.' },
                    { question: 'Bagaimana cara pembayaran?', answer: 'Pembayaran dapat dilakukan melalui transfer bank atau tunai. Ketentuan DP untuk pesanan dengan desain khusus perlu dikonfirmasi ke operator.' },
                    { question: 'Apakah ada revisi desain?', answer: 'Revisi minor dapat dilakukan sebelum masuk produksi. Perubahan besar dihitung sebagai desain baru dengan estimasi waktu tambahan.' }
                ],
                layoutWidth: 'container', layoutAlign: 'left', layoutColumns: 1,
                styleBg: 'white', styleColor: 'black', styleRadius: 'none',
                paddingTop: 0, paddingBottom: 0, fontSize: 16,
                motion: 'fade', visible: true
            },
            {
                type: 'cta',
                title: 'Sudah punya ide? Mari wujudkan dalam cetakan.',
                subtitle: '',
                desc: 'Konsultasikan desain, bahan, dan opsi printing bersama tim kami.',
                buttonLabel: 'Hubungi Orion Grafika',
                buttonUrl: '',
                image: '', alt_text: '',
                layoutWidth: 'container', layoutAlign: 'center', layoutColumns: 1,
                styleBg: 'white', styleColor: 'black', styleRadius: 'large',
                paddingTop: 0, paddingBottom: 0, fontSize: 16,
                motion: 'fade-up', visible: true
            },
            {
                type: 'contact',
                title: 'Kami siap memenuhi kebutuhan cetak Anda.',
                subtitle: 'Ruang untuk berkonsultasi',
                desc: 'Tanyakan tips dan rekomendasi opsi cetak dan paket yang sesuai. Kami siap membantu.',
                buttonLabel: 'Chat via WhatsApp',
                buttonUrl: '',
                image: '', alt_text: '',
                items: [
                    { label: 'WhatsApp', value: 'Belum diisi' },
                    { label: 'Alamat Lokasi', value: 'Sampang, Jawa Timur' },
                    { label: 'Email', value: 'Belum diisi' }
                ],
                layoutWidth: 'container', layoutAlign: 'left', layoutColumns: 2,
                styleBg: 'gray', styleColor: 'black', styleRadius: 'none',
                paddingTop: 0, paddingBottom: 0, fontSize: 16,
                motion: 'fade', visible: true
            },
            {
                type: 'footer',
                title: 'ORION GRAFIKA',
                subtitle: 'Percetakan \u00b7 Sampang',
                desc: 'Mitra cetak digital untuk bisnis, event, dan komunitas di Sampang dan sekitarnya.',
                buttonLabel: '', buttonUrl: '',
                image: '', alt_text: '',
                items: [
                    { label: 'Navigasi', title: 'Beranda', value: '/' },
                    { label: 'Informasi', title: 'Tentang Orion', value: '#tentang' },
                    { label: 'Kontak', title: 'Hubungi Kami', value: '#kontak' }
                ],
                layoutWidth: 'container', layoutAlign: 'left', layoutColumns: 1,
                styleBg: 'gray', styleColor: 'black', styleRadius: 'none',
                paddingTop: 0, paddingBottom: 0, fontSize: 16,
                motion: 'none', visible: true
            }
        ]
    };

    // ================================================================
    //  State
    // ================================================================
    var sections = [];
    var selectedIndex = -1;
    var currentDraftVersion = 0;
    var isDirty = false;
    var isSaving = false;

    // ================================================================
    //  FIX P0 — PEMISahan HYDRATE vs USER-EDIT vs PERSIST
    // ================================================================
    // isHydrating = true selama inspector diisi programatis (memilih
    // section, berpindah tab, undo/redo, apply template). Selama flag
    // ini aktif, markDirty() tidak boleh-fire: mengisi `<input>.value`
    // secara programatis BUKAN tindakan edit pengguna dan tidak boleh
    // menghasilkan satu pun request simpan.
    var isHydrating = false;

    // Memori runtime: index section -> nilai URL tombol terakhir yang valid.
    // Sengaja DI LUAR model section supaya tidak pernah ikut ter-serialize
    // ke content_json (P0.1: draft harus tetap bersih).
    var buttonUrlLastValid = {};
    var buttonUrl2LastValid = {};

    function beginHydrate() {
        isHydrating = true;
        try {
            $('prop-fields').classList.toggle('hidden', !hasSelection());
            $('prop-empty-msg').classList.toggle('hidden', hasSelection());
            if (!hasSelection()) return false;
            var s = sections[selectedIndex];
            $('prop-type-readonly').textContent = s.type + ' — id ' + s.id;
            $('prop-title').value      = s.title || '';
            $('prop-subtitle').value   = s.subtitle || '';
            $('prop-desc').value       = s.desc || '';
            $('prop-button-label').value = s.buttonLabel || '';
            $('prop-button-url').value   = s.buttonUrl || '';
            $('prop-image-url').value    = s.image || '';
            $('prop-alt-text').value     = s.alt_text || '';
            $('prop-layout-width').value = s.layoutWidth;
            document.querySelectorAll('input[name="prop-layout-align"]').forEach(function (r) {
                r.checked = (r.value === s.layoutAlign);
            });
            $('prop-layout-columns').value = String(s.layoutColumns);
            $('prop-layout-spacing').value = s.layoutSpacing;
            $('prop-padding-top').value    = s.paddingTop;
            $('prop-padding-bottom').value = s.paddingBottom;
            $('prop-margin-top').value     = s.marginTop;
            $('prop-margin-bottom').value  = s.marginBottom;
            $('prop-style-bg').value      = s.styleBg;
            $('prop-style-color').value   = s.styleColor;
            $('prop-style-radius').value  = s.styleRadius;
            $('prop-font-size').value     = s.fontSize;
            $('prop-motion').value        = s.motion;
            return true;
        } finally {
            isHydrating = false;
        }
    }

    function hasSelection() {
        return selectedIndex >= 0 && selectedIndex < sections.length;
    }
    var saveTimer = null;
    var retryCount = 0;
    var pendingImageTarget = null;   // { sectionIndex, itemIndex|null }
      var isPublishing = false;       // pengaman publish ganda
      var publishDialogOpen = false;  // pengaman dialog publish dobel

      /* ================================================================
       *  Dialog - SweetAlert2 (standar ERP) dengan fallback aman.
       *
       *  SweetAlert2 sudah menjadi dependency ERP dan dimuat oleh layout,
       *  jadi tidak ada pustaka baru di sini.
       *
       *  Aturan penting:
       *  - konfirmasi() HANYA resolve true setelah pengguna menekan
       *    tombol_setuju. Tidak pernah ada aksi destruktif yang berjalan
       *    otomatis, baik di jalur SweetAlert2 maupun jalur fallback.
       *  - Semua teks yang berasal dari data pengguna, judul section, URL,
       *    nomor revisi, atau pesan dari server SELALU di-escape lebih dulu
       *    supaya tidak ada HTML mentah yang ikut dieksekusi.
       *  - Bila SweetAlert2 tidak tersedia, dialog turun ke confirm/alert
       *    bawaan browser. Aksi tetap menunggu keputusan pengguna.
       * ================================================================ */
      var DIALOG = {
          tersedia: function () {
              return !!(window.Swal && typeof window.Swal.fire === 'function');
          },
          esc: function (t) {
              if (t === null || t === undefined) return '';
              return String(t)
                  .replace(/&/g, '&amp;')
                  .replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;')
                  .replace(/"/g, '&quot;')
                  .replace(/'/g, '&#39;');
          },
          // Teks aman: escape dulu, baru baris baru jadi <br>.
          teks: function (t) {
              return DIALOG.esc(t).replace(/\r?\n/g, '<br>');
          },
          // Mengembalikan Promise<boolean>.
          konfirmasi: function (o) {
              o = o || {};
              var judul = DIALOG.teks(o.judul || 'Konfirmasi');
              var pesan = DIALOG.teks(o.pesan || '');
              if (!DIALOG.tersedia()) {
                  // Fallback: tetap butuh keputusan eksplisit pengguna.
                  return new Promise(function (resolve) {
                      var tanya = judul + (pesan ? '\n\n' + String(o.pesan || '') : '');
                      resolve(window.confirm(tanya) === true);
                  });
              }
              return window.Swal.fire({
                  title: judul,
                  html: pesan,
                  icon: o.ikon || 'warning',
                  showCancelButton: true,
                  confirmButtonColor: o.bahaya === false ? '#2563EB' : '#93481A',
                  confirmButtonText: o.setuju || 'Ya, Lanjutkan',
                  cancelButtonColor: '#F1F5F1',
                  cancelButtonText: '<span style="color:#4A5B53">Batal</span>',
                  reverseButtons: true,
                  focusCancel: true,
                  allowOutsideClick: false,
                  allowEscapeKey: true
              }).then(function (r) { return r && r.isConfirmed === true; });
          },
          // Peringatan/informasi. Mengembalikan Promise (selesai saat ditutup).
          info: function (o) {
              o = o || {};
              var judul = DIALOG.teks(o.judul || 'Informasi');
              var pesan = DIALOG.teks(o.pesan || '');
              if (!DIALOG.tersedia()) {
                  return new Promise(function (resolve) {
                      window.alert(judul + (pesan ? '\n\n' + String(o.pesan || '') : ''));
                      resolve();
                  });
              }
              return window.Swal.fire({
                  title: judul,
                  html: pesan,
                  icon: o.ikon || 'info',
                  confirmButtonColor: '#2563EB',
                  confirmButtonText: o.setuju || 'Mengerti',
                  allowOutsideClick: true
              }).then(function () {});
          }
      };

    // Undo/redo
    var history = [];
    var historyIndex = -1;
    var MAX_HISTORY = 60;
    var lastCoalesceKey = null;
    var lastCoalesceAt = 0;

    // ================================================================
    //  Utilitas DOM
    // ================================================================
    function $(id) { return document.getElementById(id); }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = String(text);
        return node;
    }

    function generateId() {
        var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        var out = '';
        for (var i = 0; i < 9; i++) {
            out += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return 's-' + out;
    }

    // Normalisasi section agar setiap field punya bentuk yang diharapkan.
    // Dipakai saat memuat draft supaya konten lama tidak membuat editor crash.
    function normalizeSection(raw) {
        var s = (raw && typeof raw === 'object') ? raw : {};
        var out = {
            id: typeof s.id === 'string' && /^s-[a-z0-9]{9}$/.test(s.id) ? s.id : generateId(),
            type: typeof s.type === 'string' ? s.type : 'hero',
            title: typeof s.title === 'string' ? s.title : '',
            subtitle: typeof s.subtitle === 'string' ? s.subtitle : '',
            desc: typeof s.desc === 'string' ? s.desc : '',
            buttonLabel: typeof s.buttonLabel === 'string' ? s.buttonLabel : '',
            buttonUrl: typeof s.buttonUrl === 'string' ? s.buttonUrl : '',
            image: typeof s.image === 'string' ? s.image : '',
            alt_text: typeof s.alt_text === 'string' ? s.alt_text : '',
            visible: s.visible !== false,
            layoutWidth: typeof s.layoutWidth === 'string' ? s.layoutWidth : 'container',
            layoutAlign: typeof s.layoutAlign === 'string' ? s.layoutAlign : 'left',
            // WAJIB integer. Nilai lama yang berupa string ("3") dikonversi.
            layoutColumns: clampInt(s.layoutColumns, 1, 4, 2),
            layoutSpacing: typeof s.layoutSpacing === 'string' ? s.layoutSpacing : 'medium',
            paddingTop: clampInt(s.paddingTop, 0, 200, 0),
            paddingBottom: clampInt(s.paddingBottom, 0, 200, 0),
            marginTop: clampInt(s.marginTop, -200, 200, 0),
            marginBottom: clampInt(s.marginBottom, -200, 200, 0),
            fontSize: clampInt(s.fontSize, 12, 96, 16),
            motion: typeof s.motion === 'string' ? s.motion : 'none',
            styleBg: typeof s.styleBg === 'string' ? s.styleBg : 'white',
            styleColor: typeof s.styleColor === 'string' ? s.styleColor : 'black',
            styleRadius: typeof s.styleRadius === 'string' ? s.styleRadius : 'medium',
            // Fase 2: field section header & posts.
            // Defaultnya dipilih supaya section baru langsung punya nilai
            // yang masuk akal; section lama tidak terpengaruh karena
            // Normalisasi selalu menyetel ulang ke default bila absen.
            headerCta: typeof s.headerCta === 'string' ? s.headerCta.slice(0, 50) : '',
            sticky: s.sticky === true,
            postLimit: clampInt(s.postLimit, 1, 12, 6),
            postCategory: typeof s.postCategory === 'string' ? s.postCategory.slice(0, 120) : '',
            showImage: s.showImage !== false,
            showDate: s.showDate !== false,
            // Fase 2 Final: tombol kedua Hero.
            // Selalu ada bentuknya supaya renderer tidak perlu cek null,
            // dan supaya undo/redo tidak mengubah bentuk data.
            buttonLabel2: typeof s.buttonLabel2 === 'string' ? s.buttonLabel2.slice(0, 50) : '',
            buttonUrl2: typeof s.buttonUrl2 === 'string' ? s.buttonUrl2.slice(0, 2000) : '',
            button2Style: ['primary', 'dark', 'outline', 'link'].indexOf(s.button2Style) !== -1
                ? s.button2Style : 'outline',
            // Hero: tema, posisi, skala, dan bleed visual 3D.
            // WAJIB ada di sini — kalau tidak, setiap kali draft dimuat
            // field ini dibuang oleh normalisasi dan pengaturan operator
            // selalu kembali ke default.
            heroTheme: typeof s.heroTheme === 'string' ? s.heroTheme : 'light',
            heroObjectPosition: ['left', 'center', 'right', 'top', 'bottom'].indexOf(s.heroObjectPosition) !== -1
                ? s.heroObjectPosition : 'center',
            heroImageScale: clampInt(s.heroImageScale, 60, 180, 100),
            heroBleed: s.heroBleed === true,
            items: []
        };

        if (Array.isArray(s.items)) {
            var schema = ITEM_SCHEMA[out.type] || [];
            var allowed = {};
            schema.forEach(function (f) { allowed[f.key] = f; });
            s.items.forEach(function (item) {
                if (!item || typeof item !== 'object') return;
                var clean = {};
                Object.keys(allowed).forEach(function (k) {
                    if (typeof item[k] === 'string') {
                        clean[k] = item[k].slice(0, allowed[k].max);
                    } else {
                        clean[k] = '';
                    }
                });
                out.items.push(clean);
            });
        }
        return out;
    }

    function clampInt(value, min, max, fallback) {
        var n = parseInt(value, 10);
        if (isNaN(n)) return fallback;
        if (n < min) return min;
        if (n > max) return max;
        return n;
    }

    // ================================================================
    //  Indikator status (badge terpadu di header editor)
    // ================================================================
    var SAVE_STATUS_STYLE = {
        saved:   { color: '#059669', background: '#ecfdf5', border: 'transparent', icon: 'check' },
        saving:  { color: '#d97706', background: '#FEF6EA', border: 'transparent', icon: 'spinner' },
        unsaved: { color: '#d97706', background: '#ffffff', border: '#E2EAE4',  icon: 'warn' },
        error:   { color: '#93481A', background: '#fff1f2', border: 'transparent', icon: 'error' }
    };
    var SAVE_STATUS_TEXT = {
        saved: 'Tersimpan',
        saving: 'Menyimpan...',
        unsaved: 'Perubahan belum disimpan',
        error: 'Gagal menyimpan'
    };

    function showSaveStatus(status, message) {
        var badge = $('save-status');
        var text = $('save-status-text');
        var icon = $('save-status-icon');
        if (!badge || !text || !icon) return;

        var cfg = SAVE_STATUS_STYLE[status];
        if (!cfg) { badge.style.background = ''; badge.style.borderColor = ''; badge.style.color = ''; text.textContent = 'Siap'; icon.innerHTML = ''; return; }

        badge.style.color = cfg.color;
        badge.style.background = cfg.background;
        badge.style.borderColor = cfg.border;
        text.textContent = message || SAVE_STATUS_TEXT[status];
        icon.innerHTML = ICON[cfg.icon] || '';
    }

    // ================================================================
    //  Draft API
    // ================================================================
    function loadDraft() {
        showSaveStatus('saving');
        return fetch(ROUTES.draftShow, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (json) {
                if (!json.success) throw new Error('draft tidak tersedia');
                var draft = json.data.draft;
                currentDraftVersion = draft.version || 0;

                var incoming = Array.isArray(draft.content_json) ? draft.content_json : [];
                sections = incoming.map(normalizeSection);

                retryCount = 0;
                isDirty = false;
                // P0.1 — baseline pembanding diambil SETELAH normalisasi
                // (bentuk yang sama dengan yang akan dikirim), sehingga
                // penambahan field default TIDAK pernah terhitung sebagai
                // perubahan isi.
                lastSavedSignature = semanticSignature();
                resetHistory();
                renderCanvas();
                showSaveStatus('saved', 'Draft dimuat — versi ' + currentDraftVersion);
            })
            .catch(function (e) {
                // Draft belum pernah dibuat: ini kondisi normal untuk admin pertama.
                if (e && e.message === 'HTTP 404') {
                    sections = [];
                    lastSavedSignature = semanticSignature();
                    resetHistory();
                    renderCanvas();
                    showSaveStatus('saved', 'Belum ada draft');
                    return;
                }
                showSaveStatus('error', 'Gagal memuat draft');
            });
    }

    /**
     * P0.1 — Normalisasi untuk RENDERING dipisah dari perubahan KONTEN.
     *
     * `normalizeSection()` menambah ~10 field default pada setiap section
     * ketika draft dimuat. Kalau snapshot "baseline" diambil dari model
     * SUDAH ternormalisasi, bentuk default ikut ter-ordinal dan tidak
     * pernah terlihat sebagai perubahan. Sebaliknya, kalau baseline diambil
     * dari JSON mentah server, bentuk default akan selalu terlihat beda
     * dan setiap pembukaan editor menulis ulang draft.
     *
     * Solusi: normalisasi TIDAK pernah menjadi alasan untuk menyimpan.
     * Yang menentukan adalah apakah konten yang dirender benar-benar
     * berbeda dari bentuk yang sudah tersimpan.
     *
     * `serializedPayload()` membandingkan payload yang DIJALANKAN dengan
     * snapshot terakhir yang diketahui benar-benar tersimpan di server.
     */
    function semanticSignature() {
        // Hanya field yang punya arti visual/editorial yang dihitung.
        // Field yang hanya terisi karena normalisasi (headerCta,
        // postCategory, alt_text, sticky, dll.) TIDAK ikut dihitung,
        // sehingga membuka Builder tidak pernah terlihat sebagai edit.
        //
        // WAJIB selaras dengan contentFingerprint() di controller —
        // kalau JS punya field lebih sedikit, JS bisa menahan simpanan
        // yang sebenarnya sah.
        var sig = sections.map(function (s) {
            var items = Array.isArray(s.items) ? s.items : [];
            var encodedItems = items.map(function (it) {
                if (!it || typeof it !== 'object') return '';
                var keys = Object.keys(it).sort();
                var pairs = keys.map(function (k) { return JSON.stringify(k) + ':' + JSON.stringify(it[k]); });
                return '{' + pairs.join(',') + '}';
            }).sort();
            return JSON.stringify([
                s.id || '', s.type || '',
                s.title || '', s.subtitle || '', s.desc || '',
                s.image || '', s.alt_text || '',
                s.visible !== false ? 1 : 0,
                // Layout & gaya
                s.layoutWidth || '', s.layoutAlign || '', s.layoutColumns || '', s.layoutSpacing || '',
                s.styleBg || '', s.styleColor || '', s.styleRadius || '',
                s.motion || '', s.fontSize || '',
                s.paddingTop, s.paddingBottom, s.marginTop, s.marginBottom,
                // Tombol
                s.buttonLabel || '', s.buttonUrl || '',
                s.buttonLabel2 || '', s.buttonUrl2 || '', s.button2Style || '',
                // Hero
                s.heroTheme || '', s.heroObjectPosition || '', s.heroImageScale || '',
                s.heroBleed === true ? 1 : 0,
                // Header / posts
                s.headerCta || '', s.sticky === true ? 1 : 0,
                s.postLimit || '', s.postCategory || '',
                s.showImage !== false ? 1 : 0, s.showDate !== false ? 1 : 0,
                encodedItems
            ]);
        });
        return JSON.stringify(sig);
    }

    var lastSavedSignature = null;

    function saveDraft(isAutosave) {
        if (isSaving) return Promise.resolve();
        if (!isDirty && !isAutosave) return Promise.resolve();

        // P0.1 — gerbang semantik: jangan pernah PUT kalau isi yang akan
        // disimpan identik dengan isi yang sudah tersimpan. Ini menutup jalur
        // autosave yang sebelumnya melewati isDirty (penyebab draft naik
        // versi tanpa perubahan nyata).
        // Bertingkat ganda: controller juga memverifikasi ulang di server.
        if (typeof lastSavedSignature === 'string') {
            var sigNow = semanticSignature();
            if (sigNow === lastSavedSignature) {
                isDirty = false;
                showSaveStatus('saved', 'Tidak ada perubahan');
                return Promise.resolve();
            }
        }

        isSaving = true;
        showSaveStatus('saving');

        var payload = {
            content_json: sections,
            theme_json: {},
            seo_json: {},
            base_version: currentDraftVersion
        };

        return fetch(ROUTES.draftSave, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': ROUTES.csrf
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        })
            .then(function (res) { return res.json().then(function (j) { return { status: res.status, json: j }; }); })
            .then(function (r) {
                if (r.status === 409) {
                    isDirty = true;
                    showSaveStatus('error', 'Konflik versi');
                    showConflictDialog(r.json.message || 'Draft telah diperbarui oleh Administrator lain.');
                    return;
                }
                if (r.status === 422) {
                    showSaveStatus('error', firstValidationError(r.json) || 'Validasi gagal');
                    return;
                }
                if (r.status === 403) {
                    showSaveStatus('error', 'Akses ditolak');
                    return;
                }
                if (r.json && r.json.success) {
                    currentDraftVersion = r.json.version;
                    retryCount = 0;
                    isDirty = false;
                    // P0.1 — baseline pembanding diperbarui setelah server
                    // mengonfirmasi isi tersimpan. Save berikutnya hanya
                    // terjadi bila isi benar-benar berbeda.
                    lastSavedSignature = semanticSignature();
                    showSaveStatus('saved', 'Tersimpan — versi ' + currentDraftVersion);
                } else {
                    throw new Error('server menolak penyimpanan');
                }
            })
            .catch(function (e) {
                if (isAutosave && retryCount < MAX_RETRIES) {
                    retryCount++;
                    saveTimer = setTimeout(function () { saveDraft(true); }, retryCount * 2000);
                    return;
                }
                showSaveStatus('error', 'Gagal menyimpan — coba lagi');
            })
            .then(function () { isSaving = false; });
    }

    function firstValidationError(json) {
        if (!json) return null;
        if (json.errors && typeof json.errors === 'object') {
            var keys = Object.keys(json.errors);
            if (keys.length) {
                var first = json.errors[keys[0]];
                if (Array.isArray(first) && first.length) return first[0];
                if (typeof first === 'string') return first;
            }
        }
        return json.message || null;
    }

    function scheduleAutosave() {
        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(function () {
            if (isDirty && !isSaving) saveDraft(true);
        }, AUTOSAVE_DEBOUNCE);
    }

    function markDirty() {
        // FIX P0: jangan tandai dirty saat inspector sedang di-hydrate
        // (pemilihan section, perpindahan tab, undo/redo, apply template).
        // Tindakan-tindakan itu READ-ONLY dan tidak boleh mengubah data.
        if (isHydrating) return;
        isDirty = true;
        showSaveStatus('unsaved');
        scheduleAutosave();
    }

    function showConflictDialog(message) {
        var existing = $('conflict-dialog');
        if (existing) existing.remove();

        var overlay = el('div', 'lpb-modal');
        overlay.id = 'conflict-dialog';

        var box = el('div', 'lpb-modal-dialog');
        box.style.maxWidth = '440px';

        var body = el('div', 'lpb-modal-body');
        body.style.textAlign = 'center';

        var iconWrap = el('div', '');
        iconWrap.style.cssText = 'width:44px;height:44px;margin:0 auto 12px;border-radius:999px;display:flex;align-items:center;justify-content:center;background:var(--lpb-warning-subtle);color:var(--lpb-warning)';
        iconWrap.innerHTML = ICON['alert'] || ICON['warn'] || '';
        body.appendChild(iconWrap);

        body.appendChild(el('h3', 'lpb-modal-title', 'Konflik Draft'));
        body.style.textAlign = 'left';
        body.lastChild.style.textAlign = 'center';

        // Pesan disisipkan sebagai TEKS, bukan HTML.
        body.appendChild(el('p', '', message || 'Draft telah diperbarui oleh Administrator lain.'));
        body.lastChild.style.fontSize = '12.5px';
        body.lastChild.style.color = 'var(--lpb-text-secondary)';
        body.lastChild.style.margin = '8px 0 18px';

        var actions = el('div', 'flex gap-2 justify-end');
        var keepBtn = el('button', 'lpb-hdr-btn', 'Pertahankan Perubahan Lokal');
        var reloadBtn = el('button', 'lpb-hdr-btn lpb-hdr-btn--primary', 'Muat Draft Terbaru');
        actions.appendChild(keepBtn);
        actions.appendChild(reloadBtn);
        body.appendChild(actions);
        box.appendChild(body);
        overlay.appendChild(box);
        document.body.appendChild(overlay);

        reloadBtn.addEventListener('click', function () { overlay.remove(); loadDraft(); });
        keepBtn.addEventListener('click', function () {
            overlay.remove();
            showSaveStatus('unsaved', 'Perubahan lokal dipertahankan');
        });
    }

    function alertIcon() {
        var ns = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('class', 'w-5 h-5 text-amber-600');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('stroke', 'currentColor');
        var path = document.createElementNS(ns, 'path');
        path.setAttribute('stroke-linecap', 'round');
        path.setAttribute('stroke-linejoin', 'round');
        path.setAttribute('stroke-width', '2');
        path.setAttribute('d', 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z');
        svg.appendChild(path);
        return svg;
    }

    // ================================================================
    //  Undo / Redo
    // ================================================================
    function resetHistory() {
        history = [JSON.stringify(sections)];
        historyIndex = 0;
        lastCoalesceKey = null;
        lastCoalesceAt = 0;
        updateUndoRedo();
    }

    /**
     * Simpan state untuk undo.
     *
     * `coalesceKey` menggabungkan beberapa perubahan beruntun jadi satu
     * langkah undo. Tanpa ini, mengetik satu judul 20 karakter akan
     * mengisi 20 langkah history dan undo hampir tidak berguna.
     */
    function saveState(opts) {
        opts = opts || {};

        // FIX P0: saat inspector di-hydrate, jangan dorong history baru
        // dan jangan tandai dirty.(read-only: pilih section / pindah tab)
        if (isHydrating) return;

        var serialized = JSON.stringify(sections);

        if (historyIndex >= 0 && history[historyIndex] === serialized) {
            updateUndoRedo();
            return;
        }

        var now = Date.now();
        var key = opts.coalesceKey || null;

        if (key && key === lastCoalesceKey && (now - lastCoalesceAt) < 700 && historyIndex >= 0) {
            history[historyIndex] = serialized;
        } else {
            history = history.slice(0, historyIndex + 1);
            history.push(serialized);
            if (history.length > MAX_HISTORY) history.shift();
            historyIndex = history.length - 1;
        }

        lastCoalesceKey = key;
        lastCoalesceAt = now;
        updateUndoRedo();
        markDirty();
    }

    function updateUndoRedo() {
        $('undo-btn').disabled = historyIndex <= 0;
        $('redo-btn').disabled = historyIndex < 0 || historyIndex >= history.length - 1;
    }

    function undo() {
        if (historyIndex <= 0) return;
        historyIndex--;
        applyHistory();
    }

    function redo() {
        if (historyIndex < 0 || historyIndex >= history.length - 1) return;
        historyIndex++;
        applyHistory();
    }

    function applyHistory() {
        var restored = JSON.parse(history[historyIndex]);
        sections = restored.map(normalizeSection);
        if (selectedIndex >= sections.length) selectedIndex = sections.length - 1;
        if (selectedIndex < 0 && sections.length > 0) selectedIndex = 0;
        renderCanvas();
        syncPropertiesFromSelection();
        lastCoalesceKey = null;
        updateUndoRedo();
        markDirty();
    }

    // ================================================================
    //  Canvas
    // ================================================================
    function renderCanvas() {
        var canvas = $('canvas');
        canvas.innerHTML = '';

        if (sections.length === 0) {
            canvas.appendChild(el('div', 'lpb-canvas-empty',
                null));
            canvas.firstChild.appendChild(el('span', '', null));
            canvas.firstChild.lastChild.innerHTML = ICON.empty;
            canvas.firstChild.appendChild(el('p', null, 'Klik komponen di panel kiri untuk menambahkan section.'));
            updateCanvasCount();
            return;
        }

        sections.forEach(function (section, index) {
            canvas.appendChild(buildSectionCard(section, index));
        });
        updateCanvasCount();
    }

    function updateCanvasCount() {
        var label = $('canvas-count-label');
        if (label) label.textContent = sections.length + ' section';
    }

    function buildSectionCard(section, index) {
        var card = el('div',
            'lp-section-card' +
            (index === selectedIndex ? ' is-selected' : '') +
            (section.visible === false ? ' is-hidden-section' : ''));

        card.dataset.index = String(index);
        card.draggable = true;

        // --- Toolbar ---
        var head = el('div', 'lp-card-head');

        var drag = el('button', 'lp-drag-handle');
        drag.type = 'button';
        drag.title = 'Seret untuk mengurutkan';
        drag.setAttribute('aria-label', 'Seret untuk mengurutkan');
        drag.innerHTML = ICON['grip'] || '';
        head.appendChild(drag);

        var typeWrap = el('div', 'flex items-center gap-2 min-w-0 flex-1');
        var typeLabel = el('span', 'lp-type-badge', section.type);
        typeLabel.title = 'Tipe: ' + section.type;
        typeWrap.appendChild(typeLabel);
        var title = el('p', 'lp-card-title', section.title || 'Judul section');
        typeWrap.appendChild(title);
        head.appendChild(typeWrap);

        var actions = el('div', 'lp-card-actions');
        actions.appendChild(iconActionBtn('duplicate', index, 'Gandakan', 'duplicate'));
        actions.appendChild(iconActionBtn('delete', index, 'Hapus', 'trash', 'lp-icon-btn--danger'));
        actions.appendChild(iconActionBtn('up', index, 'Naikkan', 'arrowUp'));
        actions.appendChild(iconActionBtn('down', index, 'Turunkan', 'arrowDown'));
        actions.appendChild(iconActionBtn('toggle-vis', index,
            section.visible === false ? 'Tampilkan' : 'Sembunyikan',
            section.visible === false ? 'eye' : 'eyeOff',
            section.visible === false ? 'lp-icon-btn--active' : ''));
        head.appendChild(actions);
        card.appendChild(head);

        // --- Isi kartu ---
        var body = el('div', 'lp-card-body');

        if (section.visible === false) {
            body.appendChild(el('p', 'text-xs mb-1', 'Section ini disembunyikan dari halaman publik.'));
        }

        // --- Konten: dibangun dengan textContent, TIDAK pernah innerHTML ---
        if (section.image) {
            var img = document.createElement('img');
            img.className = 'lpb-image-preview';
            img.alt = section.alt_text || '';
            img.loading = 'lazy';
            img.src = resolveImageSrc(section.image);
            img.addEventListener('error', function () {
                img.replaceWith(el('p', 'lpb-hint', 'Gambar tidak dapat dimuat: ' + section.image));
            });
            body.appendChild(img);
        }

        body.appendChild(el('h4', 'lpb-label', section.title || 'Judul section'));

        if (section.subtitle) body.appendChild(el('p', 'text-sm mt-1 break-words', section.subtitle));
        if (section.desc) body.appendChild(el('p', 'text-xs mt-1 break-words', section.desc));

        if (section.items && section.items.length) {
            var itemsWrap = el('ul', 'mt-2 space-y-1 text-xs list-disc list-inside');
            section.items.slice(0, 6).forEach(function (item) {
                var label = item.title || item.question || item.label || item.author || item.value || item.quote || '(item kosong)';
                itemsWrap.appendChild(el('li', 'truncate', label));
            });
            if (section.items.length > 6) {
                itemsWrap.appendChild(el('li', '', '+' + (section.items.length - 6) + ' item lain'));
            }
            body.appendChild(itemsWrap);
        }

        if (section.buttonLabel) {
            body.appendChild(el('p', 'lpb-hint',
                '[tombol] ' + section.buttonLabel + (section.buttonUrl ? ' -> ' + section.buttonUrl : '')));
        }

        var meta = [];
        meta.push('kolom ' + section.layoutColumns);
        meta.push('align ' + section.layoutAlign);
        if (section.fontSize !== 16) meta.push('font ' + section.fontSize + 'px');
        if (section.paddingTop || section.paddingBottom) meta.push('pad ' + section.paddingTop + '/' + section.paddingBottom);
        if (section.marginTop || section.marginBottom) meta.push('margin ' + section.marginTop + '/' + section.marginBottom);
        body.appendChild(el('p', 'lpb-hint mt-2 pt-2', meta.join(' | ')));

        card.appendChild(body);

        // --- Interaksi ---
        card.addEventListener('click', function (e) {
            if (e.target.closest('button')) return;
            selectSection(index);
        });
        attachDragHandlers(card, index);

        return card;
    }

    function iconActionBtn(action, index, title, iconClass, extraClass) {
        var btn = el('button', 'lp-icon-btn' + (extraClass ? ' ' + extraClass : ''));
        btn.type = 'button';
        btn.title = title;
        btn.setAttribute('aria-label', title);
        btn.dataset.action = action;
        btn.dataset.index = String(index);
        btn.innerHTML = ICON[iconClass] || '';
        return btn;
    }

    function resolveImageSrc(reference) {
        if (!reference) return '';
        if (reference.indexOf('landing-media/') === 0) {
            return STORAGE_BASE + '/' + reference;
        }
        return reference;
    }

    function selectSection(index) {
        selectedIndex = index;
        renderCanvas();
        syncPropertiesFromSelection();
    }

    // --- Drag & drop untuk urutan section ---
    var dragFrom = null;

    function attachDragHandlers(card, index) {
        card.addEventListener('dragstart', function (e) {
            dragFrom = index;
            card.classList.add('opacity-50');
            e.dataTransfer.effectAllowed = 'move';
            // Firefox memerlukan dataTransfer berisi data.
            try { e.dataTransfer.setData('text/plain', String(index)); } catch (err) { /* diabaikan */ }
        });
        card.addEventListener('dragend', function () {
            dragFrom = null;
            card.classList.remove('opacity-50');
            document.querySelectorAll('.lp-drag-over').forEach(function (n) { n.classList.remove('lp-drag-over'); });
        });
        card.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            card.classList.add('lp-drag-over');
        });
        card.addEventListener('dragleave', function () { card.classList.remove('lp-drag-over'); });
        card.addEventListener('drop', function (e) {
            e.preventDefault();
            card.classList.remove('lp-drag-over');
            if (dragFrom === null || dragFrom === index) return;
            var moved = sections.splice(dragFrom, 1)[0];
            sections.splice(index, 0, moved);
            if (selectedIndex === dragFrom) selectedIndex = index;
            saveState();
            renderCanvas();
            syncPropertiesFromSelection();
        });
    }

    // --- Aksi per section (didelegasikan, jadi tetap hidup setelah render ulang) ---
    $('canvas').addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-action]');
        if (!btn) return;
        e.stopPropagation();

        var action = btn.dataset.action;
        var index = parseInt(btn.dataset.index, 10);
        if (isNaN(index) || index < 0 || index >= sections.length) return;

        if (action === 'delete') {
            // Konfirmasi penghapusan (Checkpoint 5A butir 2).
            // Aksi hanya berjalan SETELAH pengguna menyetujui.
            var label = sections[index].title || '(tanpa judul)';
            DIALOG.konfirmasi({
                judul: 'Hapus section?',
                pesan: 'Hapus section "' + label + '"?\n\nPerubahan ini bisa dibatalkan dengan Undo.',
                ikon: 'warning',
                setuju: 'Ya, Hapus'
            }).then(function (ya) {
                if (!ya) return;
                sections.splice(index, 1);
                if (selectedIndex === index) selectedIndex = -1;
                else if (selectedIndex > index) selectedIndex--;
                lastCoalesceKey = null;
                saveState();
                renderCanvas();
                syncPropertiesFromSelection();
            });
            return;
        }

        if (action === 'duplicate') {
            var copy = JSON.parse(JSON.stringify(sections[index]));
            copy.id = generateId();
            sections.splice(index + 1, 0, copy);
            selectedIndex = index + 1;
            lastCoalesceKey = null;
            saveState();
            renderCanvas();
            syncPropertiesFromSelection();
            return;
        }

        if (action === 'up' && index > 0) {
            var tmp = sections[index - 1];
            sections[index - 1] = sections[index];
            sections[index] = tmp;
            if (selectedIndex === index) selectedIndex--;
            else if (selectedIndex === index - 1) selectedIndex++;
            lastCoalesceKey = null;
            saveState();
            renderCanvas();
            syncPropertiesFromSelection();
            return;
        }

        if (action === 'down' && index < sections.length - 1) {
            var tmp2 = sections[index + 1];
            sections[index + 1] = sections[index];
            sections[index] = tmp2;
            if (selectedIndex === index) selectedIndex++;
            else if (selectedIndex === index + 1) selectedIndex--;
            lastCoalesceKey = null;
            saveState();
            renderCanvas();
            syncPropertiesFromSelection();
            return;
        }

        if (action === 'toggle-vis') {
            sections[index].visible = sections[index].visible === false;
            lastCoalesceKey = null;
            saveState();
            renderCanvas();
            syncPropertiesFromSelection();
        }
    });

    // ================================================================
    //  Panel Properti
    // ================================================================
    function syncPropertiesFromSelection() {
        // FIX P0: seluruh pengisian field inspector di bawah adalah READ-ONLY.
        // Bungkus dengan beginHydrate() supaya tidak ada satupun handler
        // input/change yang bisa menandai draft dirty selama proses ini.
        isHydrating = true;
        try {
            var has = selectedIndex >= 0 && selectedIndex < sections.length;
            $('prop-fields').classList.toggle('hidden', !has);
            $('prop-empty-msg').classList.toggle('hidden', has);
            if (!has) return;

            var s = sections[selectedIndex];

            $('prop-type-readonly').textContent = s.type + ' — id ' + s.id;

            $('prop-title').value = s.title || '';
            $('prop-subtitle').value = s.subtitle || '';
            $('prop-desc').value = s.desc || '';
            $('prop-button-label').value = s.buttonLabel || '';
            $('prop-button-url').value = s.buttonUrl || '';
            $('prop-image-url').value = s.image || '';
            $('prop-alt-text').value = s.alt_text || '';

        $('prop-layout-width').value = s.layoutWidth;
        document.querySelectorAll('input[name="prop-layout-align"]').forEach(function (r) {
            r.checked = (r.value === s.layoutAlign);
        });
        $('prop-layout-columns').value = String(s.layoutColumns);
        $('prop-layout-spacing').value = s.layoutSpacing;
        $('prop-padding-top').value = s.paddingTop;
        $('prop-padding-bottom').value = s.paddingBottom;
        $('prop-margin-top').value = s.marginTop;
        $('prop-margin-bottom').value = s.marginBottom;
        $('prop-style-bg').value = s.styleBg;
        $('prop-style-color').value = s.styleColor;
        $('prop-style-radius').value = s.styleRadius;
        $('prop-font-size').value = s.fontSize;
        $('prop-motion').value = s.motion;

        // Blok yang hanya relevan untuk sebagian tipe.
        $('prop-button-block').style.display = BUTTON_TYPES[s.type] ? '' : 'none';
        $('prop-image-block').style.display = IMAGE_TYPES[s.type] ? '' : 'none';
            $('prop-header-block').style.display = s.type === 'header' ? '' : 'none';
            $('prop-posts-block').style.display  = s.type === 'posts' ? '' : 'none';
            $('prop-hero2-block').style.display  = s.type === 'hero' ? '' : 'none';
            $('prop-hero-visual-block').style.display = s.type === 'hero' ? '' : 'none';
            $('prop-hero-object-position').value = s.heroObjectPosition || 'center';
            $('prop-hero-image-scale').value    = s.heroImageScale || 100;
            $('prop-hero-bleed').checked        = !!s.heroBleed;

            $('prop-button2-label').value = s.buttonLabel2 || '';
            $('prop-button2-url').value   = s.buttonUrl2 || '';
            $('prop-button2-style').value = s.button2Style || 'outline';

            // Simpan nilai valid terakhir agar blur yang menolak nilai dapat
            // memulihkan input tanpa menghapus data section.
            // PENTING: disimpan di map terpisah, BUKAN sebagai properti
            // section — kalau menempel di section, field ini ikut
            // ter-serialize ke content_json dan mencemari draft.
            buttonUrlLastValid[selectedIndex]  = lastValidUrl(s, 'buttonUrl');
            buttonUrl2LastValid[selectedIndex] = lastValidUrl(s, 'buttonUrl2');

            // Nilai field khusus header/posts disalin dari section terpilih.
            // Field yang tidak ada pada section lama diisi default yang aman.
            $('prop-header-cta').value       = s.headerCta || '';
            $('prop-header-sticky').checked  = s.sticky === true;
            $('prop-posts-limit').value      = s.postLimit || 6;
            $('prop-posts-category').value   = s.postCategory || '';
            $('prop-posts-showimage').checked = s.showImage !== false;
            $('prop-posts-showdate').checked  = s.showDate !== false;

            updateImagePreview();
            renderItemsPanel();
        } finally {
            // Selalu tutup flag hydrasi, bahkan bila ada error di tengah.
            isHydrating = false;
        }
    }

    // Helper: nilai URL terakhir yang valid, dipakai untuk memulihkan field
    // tanpa menghapus data saat URL ditolak.
    function lastValidUrl(section, key) {
        var v = section && section[key];
        if (typeof v === 'string') {
            var t = v.trim();
            if (t === '' || t.charAt(0) === '/' || /^https?:\/\//i.test(t)) return t;
        }
        return '';
    }

    function updateImagePreview() {
        var img = $('prop-image-preview');
        var value = $('prop-image-url').value.trim();
        if (!value) {
            img.style.display = 'none';
            img.removeAttribute('src');
            return;
        }
        img.src = resolveImageSrc(value);
        img.alt = $('prop-alt-text').value || '';
        img.style.display = '';
    }

    // --- Repeater item ---
    function renderItemsPanel() {
        var block = $('prop-items-block');
        var list = $('prop-items-list');

        if (selectedIndex < 0) { block.style.display = 'none'; return; }
        var section = sections[selectedIndex];
        var schema = ITEM_SCHEMA[section.type] || [];

        if (schema.length === 0) {
            block.style.display = 'none';
            list.innerHTML = '';
            return;
        }
        block.style.display = '';
        list.innerHTML = '';

        if (section.items.length === 0) {
            list.appendChild(el('p', 'lpb-hint', 'Belum ada item. Klik "+ Tambah Item".'));
            return;
        }

        section.items.forEach(function (item, itemIndex) {
            var row = el('div', 'lp-item-row');

            var head = el('div', 'lp-item-head');
            head.appendChild(el('span', 'lp-item-title', 'Item ' + (itemIndex + 1)));

            var rowActions = el('div', 'lp-item-actions');
            rowActions.appendChild(itemActionBtn('item-up', itemIndex, 'Naik', 'arrowUp', ''));
            rowActions.appendChild(itemActionBtn('item-down', itemIndex, 'Turun', 'arrowDown', ''));
            rowActions.appendChild(itemActionBtn('item-delete', itemIndex, 'Hapus item', 'trash', 'lp-item-btn--danger'));
            head.appendChild(rowActions);
            row.appendChild(head);

            schema.forEach(function (field) {
                var label = el('label', 'lpb-label', field.label);
                row.appendChild(label);

                if (field.kind === 'textarea') {
                    var ta = document.createElement('textarea');
                    ta.rows = 2;
                    ta.maxLength = field.max;
                    ta.className = 'lpb-textarea';
                    ta.value = item[field.key] || '';
                    ta.addEventListener('input', function (e) {
                        sections[selectedIndex].items[itemIndex][field.key] = e.target.value;
                        saveState({ coalesceKey: 'item:' + itemIndex + ':' + field.key });
                        refreshCardSummary(selectedIndex);
                    });
                    row.appendChild(ta);
                } else {
                    var input = document.createElement('input');
                    input.type = 'text';
                    input.maxLength = field.max;
                    input.className = 'lpb-input';
                    input.value = item[field.key] || '';
                    input.addEventListener('input', function (e) {
                        sections[selectedIndex].items[itemIndex][field.key] = e.target.value;
                        saveState({ coalesceKey: 'item:' + itemIndex + ':' + field.key });
                        refreshCardSummary(selectedIndex);
                    });
                    row.appendChild(input);

                    if (field.kind === 'image') {
                        var preview = document.createElement('img');
                        preview.className = 'lpb-image-preview';
                        preview.style.display = 'none';
                        preview.alt = '';
                        preview.loading = 'lazy';
                        var applyPreview = function () {
                            var v = input.value.trim();
                            if (!v) { preview.style.display = 'none'; preview.removeAttribute('src'); return; }
                            preview.src = resolveImageSrc(v);
                            preview.style.display = '';
                        };
                        input.addEventListener('input', applyPreview);
                        applyPreview();

                        var pick = el('button', 'lpb-hdr-btn', 'Pilih dari Media');
                        pick.type = 'button';
                        pick.style.marginTop = '6px';
                        pick.addEventListener('click', function () {
                            pendingImageTarget = { sectionIndex: selectedIndex, itemIndex: itemIndex };
                            openMediaModal();
                        });
                        row.appendChild(pick);
                        row.appendChild(preview);
                    }
                }
            });

            list.appendChild(row);
        });
    }

    function itemActionBtn(action, index, title, iconClass, extraClass) {
        var btn = el('button', 'lp-item-btn' + (extraClass ? ' ' + extraClass : ''));
        btn.type = 'button';
        btn.title = title;
        btn.setAttribute('aria-label', title);
        btn.dataset.action = action;
        btn.dataset.index = String(index);
        btn.innerHTML = ICON[iconClass] || '';
        return btn;
    }

    // Perbarui hanya ringkasan satu kartu, tanpa menggambar ulang seluruh canvas
    // (supaya fokus pada input tidak hilang).
    function refreshCardSummary(index) {
        var card = document.querySelector('.lp-section-card[data-index="' + index + '"]');
        if (!card || index !== selectedIndex) return;
        var replacement = buildSectionCard(sections[index], index);
        card.parentNode.replaceChild(replacement, card);
    }

    $('prop-items-list').addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-action]');
        if (!btn || selectedIndex < 0) return;
        e.preventDefault();

        var itemIndex = parseInt(btn.dataset.index, 10);
        var items = sections[selectedIndex].items;
        if (isNaN(itemIndex) || itemIndex < 0 || itemIndex >= items.length) return;
        var action = btn.dataset.action;

        if (action === 'item-delete') {
            // Jalur destruktif: tunggu persetujuan dulu, baru memotong.
            DIALOG.konfirmasi({
                judul: 'Hapus item?',
                pesan: 'Hapus item ' + (itemIndex + 1) + '?',
                ikon: 'warning',
                setuju: 'Ya, Hapus'
            }).then(function (ya) {
                if (!ya) return;
                items.splice(itemIndex, 1);
                lastCoalesceKey = null;
                saveState();
                renderCanvas();
                renderItemsPanel();
            });
            return;
        } else if (action === 'item-up' && itemIndex > 0) {
            var t = items[itemIndex - 1];
            items[itemIndex - 1] = items[itemIndex];
            items[itemIndex] = t;
        } else if (action === 'item-down' && itemIndex < items.length - 1) {
            var t2 = items[itemIndex + 1];
            items[itemIndex + 1] = items[itemIndex];
            items[itemIndex] = t2;
        } else {
            return;
        }
        lastCoalesceKey = null;
        saveState();
        renderCanvas();
        renderItemsPanel();
    });

    $('prop-item-add').addEventListener('click', function () {
        if (selectedIndex < 0) return;
        var section = sections[selectedIndex];
        var schema = ITEM_SCHEMA[section.type] || [];
        if (schema.length === 0) return;
        if (section.items.length >= MAX_ITEMS) {
            DIALOG.info({
                judul: 'Batas item tercapai',
                pesan: 'Maksimal ' + MAX_ITEMS + ' item per section.',
                ikon: 'info'
            });
            return;
        }
        var blank = {};
        schema.forEach(function (f) { blank[f.key] = ''; });
        section.items.push(blank);
        lastCoalesceKey = null;
        saveState();
        renderCanvas();
        renderItemsPanel();
    });

    // ================================================================
    //  Pengikatan kontrol properti
    // ================================================================
    function currentSection() {
        return (selectedIndex >= 0 && selectedIndex < sections.length) ? sections[selectedIndex] : null;
    }

    // Field teks: coalesce per field agar mengetik tidak memakan history.
    function bindText(id, key, maxLen) {
        $(id).addEventListener('input', function (e) {
            var s = currentSection();
            if (!s) return;
            var value = e.target.value;
            if (maxLen) value = value.slice(0, maxLen);
            s[key] = value;
            saveState({ coalesceKey: key });
            refreshCardSummary(selectedIndex);
        });
    }

    bindText('prop-title', 'title', 200);
    bindText('prop-subtitle', 'subtitle', 300);
    bindText('prop-desc', 'desc', 1000);
    bindText('prop-button-label', 'buttonLabel', 50);
    bindText('prop-alt-text', 'alt_text', 255);

    // URL tombol: tidak di-coalesce supaya tiap ketikan bisa di-undo,
    // tapi tetap divalidasi ringan di sisi klien.
    $('prop-button-url').addEventListener('input', function (e) {
        if (isHydrating) return;
        var s = currentSection();
        if (!s) return;
        s.buttonUrl = e.target.value;
        // Ingat nilai valid terakhir berdasarkan ISI INPUT, bukan nilai
        // yang sudah tersimpan. Kalau operator mengetik URL baru lalu
        // blur tanpa memvalidasi, pemulihkan harus kembali ke ketikan
        // terakhir itu — bukan ke nilai lama di database.
        var typed = String(e.target.value).trim();
        if (typed === '' || typed.charAt(0) === '/' || /^https?:\/\//i.test(typed)) {
            buttonUrlLastValid[selectedIndex] = typed;
        }
        saveState({ coalesceKey: 'buttonUrl' });
        refreshCardSummary(selectedIndex);
    });
    $('prop-button-url').addEventListener('blur', function () {
        if (isHydrating) return;
        var s = currentSection();
        if (!s || !s.buttonUrl) return;
        var url = String(s.buttonUrl).trim();
        if (url === '') return;                            // dikosongkan user: biarkan kosong
        if (url.indexOf('/') === 0) return;                 // path internal, boleh
        if (/^https?:\/\//i.test(url)) return;               // http/https, boleh

        // FIX P0 (data loss): nilai TIDAK lagi dihapus jadi string kosong.
        // Blur bisa terjadi karena aksi READ-ONLY (pindah tab, klik kartu
        // lain, ganti device) — menghapus nilai di sini pernah membuat
        // hero.buttonUrl jadi null dan tombol CTA utama hilang.
        // Sekarang: kembalikan input ke nilai terakhir yang tersimpan di
        // model, lalu beri tahu operator. Data tidak pernah dihancurkan.
        var restore = (buttonUrlLastValid[selectedIndex] !== undefined)
            ? buttonUrlLastValid[selectedIndex] : '';
        s.buttonUrl = restore;
        $('prop-button-url').value = restore;
        saveState({ coalesceKey: 'buttonUrl' });
        refreshCardSummary(selectedIndex);
        DIALOG.info({
            judul: 'URL tombol tidak valid',
            pesan: 'URL tombol harus diawali http:// , https:// atau / (path internal).\n\nNilai dikembalikan ke: ' + (restore || '(kosong)') + '\n\nData tidak dihapus.',
            ikon: 'error'
        });
    });

    // ---- Tombol kedua (Hero) ---------------------------------------------
    // Ketiga field ini sebelumnya TIDAK punya listener sama sekali, sehingga
    // operator bisa melihat nilainya di Inspector tetapi mengetiknya tidak
    // pernah tersimpan. Akibatnya tombol kedua Hero (mis. "Jelajahi
    // Layanan") selalu tertinggal di URL lama / kosong.
    $('prop-button2-label').addEventListener('input', function (e) {
        if (isHydrating) return;
        var s = currentSection(); if (!s) return;
        s.buttonLabel2 = e.target.value;
        saveState({ coalesceKey: 'button2' });
        refreshCardSummary(selectedIndex);
    });
    $('prop-button2-url').addEventListener('input', function (e) {
        if (isHydrating) return;
        var s = currentSection(); if (!s) return;
        s.buttonUrl2 = e.target.value;
        var typed = String(e.target.value).trim();
        if (typed === '' || typed.charAt(0) === '/' || /^https?:\/\//i.test(typed)) {
            buttonUrl2LastValid[selectedIndex] = typed;
        }
        saveState({ coalesceKey: 'button2' });
        refreshCardSummary(selectedIndex);
    });
    $('prop-button2-url').addEventListener('blur', function () {
        if (isHydrating) return;
        var s = currentSection(); if (!s || !s.buttonUrl2) return;
        var url = String(s.buttonUrl2).trim();
        if (url === '') return;
        if (url.charAt(0) === '/') return;
        if (/^https?:\/\//i.test(url)) return;

        var restore = (buttonUrl2LastValid[selectedIndex] !== undefined)
            ? buttonUrl2LastValid[selectedIndex] : '';
        s.buttonUrl2 = restore;
        $('prop-button2-url').value = restore;
        saveState({ coalesceKey: 'button2' });
        refreshCardSummary(selectedIndex);
        DIALOG.info({
            judul: 'URL tombol kedua tidak valid',
            pesan: 'URL harus diawali http:// , https:// atau / (path internal).\n\nNilai dikembalikan ke: ' + (restore || '(kosong)') + '\n\nData tidak dihapus.',
            ikon: 'error'
        });
    });
    $('prop-button2-style').addEventListener('change', function (e) {
        if (isHydrating) return;
        var s = currentSection(); if (!s) return;
        s.button2Style = e.target.value;
        saveState({ coalesceKey: 'button2' });
        refreshCardSummary(selectedIndex);
    });

    $('prop-image-url').addEventListener('input', function (e) {
        var s = currentSection();
        if (!s) return;
        s.image = e.target.value;
        updateImagePreview();
        saveState({ coalesceKey: 'image' });
        refreshCardSummary(selectedIndex);
    });
    $('prop-image-url').addEventListener('blur', function () {
        var s = currentSection();
        if (!s || !s.image) return;
        var v = s.image.trim();
        var ok = v.indexOf('landing-media/') === 0 || /^https?:\/\//i.test(v);
        if (!ok) {
            // Nilai ditolak: bersihkan dulu (keputusan sudah pasti), lalu dialog.
            s.image = '';
            $('prop-image-url').value = '';
            updateImagePreview();
            saveState();
            refreshCardSummary(selectedIndex);
            DIALOG.info({
                judul: 'Alamat gambar tidak valid',
                pesan: 'Gambar harus berupa path "landing-media/..." atau URL http/https.',
                ikon: 'error'
            });
        }
    });

    $('prop-image-clear').addEventListener('click', function () {
        var s = currentSection();
        if (!s) return;
        s.image = '';
        $('prop-image-url').value = '';
        updateImagePreview();
        lastCoalesceKey = null;
        saveState();
        renderCanvas();
        syncPropertiesFromSelection();
    });

    $('prop-image-pick').addEventListener('click', function () {
        if (!currentSection()) return;
        pendingImageTarget = { sectionIndex: selectedIndex, itemIndex: null };
        openMediaModal();
    });

    // Select & radio
    $('prop-layout-width').addEventListener('change', function (e) {
        var s = currentSection(); if (!s) return;
        s.layoutWidth = e.target.value;
        saveState(); refreshCardSummary(selectedIndex);
    });
    $('prop-layout-columns').addEventListener('change', function (e) {
        var s = currentSection(); if (!s) return;
        // WAJIB integer: server menolak nilai string (is_int check).
        s.layoutColumns = clampInt(e.target.value, 1, 4, 2);
        e.target.value = String(s.layoutColumns);
        saveState(); refreshCardSummary(selectedIndex);
    });
    $('prop-layout-spacing').addEventListener('change', function (e) {
        var s = currentSection(); if (!s) return;
        s.layoutSpacing = e.target.value;
        saveState(); refreshCardSummary(selectedIndex);
    });
    $('prop-style-bg').addEventListener('change', function (e) {
        var s = currentSection(); if (!s) return;
        s.styleBg = e.target.value;
        saveState(); refreshCardSummary(selectedIndex);
    });
    $('prop-style-color').addEventListener('change', function (e) {
        var s = currentSection(); if (!s) return;
        s.styleColor = e.target.value;
        saveState(); refreshCardSummary(selectedIndex);
    });
    $('prop-style-radius').addEventListener('change', function (e) {
        var s = currentSection(); if (!s) return;
        s.styleRadius = e.target.value;
        saveState(); refreshCardSummary(selectedIndex);
    });
    $('prop-motion').addEventListener('change', function (e) {
        var s = currentSection(); if (!s) return;
        s.motion = e.target.value;
        saveState(); refreshCardSummary(selectedIndex);
    });
    document.querySelectorAll('input[name="prop-layout-align"]').forEach(function (radio) {
        radio.addEventListener('change', function (e) {
            var s = currentSection(); if (!s) return;
            s.layoutAlign = e.target.value;
            saveState(); refreshCardSummary(selectedIndex);
        });
    });

    // Field numerik: dikunci ke rentang server agar 422 tidak pernah terjadi
    // karena alasan yang bisa dicegah di sisi klien.
    function bindNumber(id, key, min, max) {
        var node = $(id);
        node.addEventListener('input', function (e) {
            var s = currentSection();
            if (!s) return;
            if (e.target.value === '') {
                s[key] = min === -200 ? 0 : min;
                return;
            }
            s[key] = clampInt(e.target.value, min, max, min === -200 ? 0 : min);
            saveState({ coalesceKey: key });
            refreshCardSummary(selectedIndex);
        });
        node.addEventListener('blur', function (e) {
            var s = currentSection();
            if (!s) return;
            s[key] = clampInt(e.target.value, min, max, min === -200 ? 0 : min);
            e.target.value = String(s[key]);
            saveState({ coalesceKey: key });
            refreshCardSummary(selectedIndex);
        });
    }

    bindNumber('prop-padding-top', 'paddingTop', 0, 200);
    bindNumber('prop-padding-bottom', 'paddingBottom', 0, 200);
    bindNumber('prop-margin-top', 'marginTop', -200, 200);
    bindNumber('prop-margin-bottom', 'marginBottom', -200, 200);
    bindNumber('prop-font-size', 'fontSize', 12, 96);

    // Tab Properti
    document.querySelectorAll('.prop-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.prop-tab').forEach(function (t) {
                t.style.color = 'var(--lpb-text-tertiary)';
                t.style.borderColor = 'transparent';
                t.style.borderBottomWidth = '2px';
                t.style.borderBottomStyle = 'solid';
            });
            tab.style.color = 'var(--orion-deep-night)';
            tab.style.borderColor = 'var(--orion-deep-night)';
            document.querySelectorAll('.prop-panel').forEach(function (p) { p.classList.add('hidden'); });
            var panel = $('tab-' + tab.dataset.tab);
            if (panel) panel.classList.remove('hidden');
        });
    });
    var contentTab = document.querySelector('.prop-tab[data-tab="content"]');
    if (contentTab) {
        contentTab.style.color = 'var(--orion-deep-night)';
        contentTab.style.borderColor = 'var(--orion-deep-night)';
    }

    // ================================================================
    //  Preview responsif
    // ================================================================
    var PREVIEW_SIZES = { desktop: '100%', tablet: '768px', mobile: '375px' };

    function setActivePreviewBtn(activeBtn) {
        // Ada dua switcher (header & toolbar canvas) - hanya satu terlihat
        // pada satu waktu, jadi state aktif disamakan berdasarkan mode-nya
        // supaya keduanya tidak pernah berbeda.
        var size = activeBtn.dataset.width;
        document.querySelectorAll('.lp-preview-btn').forEach(function (b) {
            var on = b.dataset.width === size;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    document.querySelectorAll('.lp-preview-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var size = btn.dataset.width;
            var canvas = $('canvas');
            canvas.style.maxWidth = PREVIEW_SIZES[size];
            canvas.style.margin = '0 auto';
            $('preview-size-label').textContent = 'Lebar: ' + size + (size === 'desktop' ? '' : ' (' + PREVIEW_SIZES[size] + ')');
            setActivePreviewBtn(btn);
        });
    });
    var firstPreviewBtn = document.querySelector('.lp-preview-btn[data-width="desktop"]');
    if (firstPreviewBtn) setActivePreviewBtn(firstPreviewBtn);

    // ================================================================
    //  Pustaka komponen & pencarian
    // ================================================================
    var TYPE_LABELS = {
        'hero': 'Hero', 'about': 'About', 'services': 'Services',
        'product-grid': 'Product Grid', 'portfolio': 'Portfolio',
        'testimonials': 'Testimonials', 'statistics': 'Statistics',
        'faq': 'FAQ', 'cta': 'CTA', 'contact': 'Contact', 'footer': 'Footer',
        'header': 'Header/Navbar', 'posts': 'Kabar & Panduan'
    };

    document.querySelectorAll('.component-item').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var type = btn.dataset.type;
            var section = normalizeSection({
                id: generateId(),
                type: type,
                // Judul diisi default supaya section bisa langsung disimpan
                // (server mewajibkan title tidak kosong).
                title: TYPE_LABELS[type] || type,
                items: []
            });
            sections.push(section);
            selectedIndex = sections.length - 1;
            lastCoalesceKey = null;
            saveState();
            renderCanvas();
            syncPropertiesFromSelection();
            var card = document.querySelector('.lp-section-card[data-index="' + selectedIndex + '"]');
            if (card) card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    });


    // ================================================================
    //  Terapkan Template: ORION GRAFIKA - PREMIUM DEFAULT
    // ------------------------------------------------------------
    //  Aturan yang dijaga (sesuai spesifikasi):
    //  1. Konfirmasi dulu bila draft sudah berisi section.
    //  2. Ada opsi batal (tombol Batal / ESC / klik luar).
    //  3. Memakai jalur draft yang sama -> optimistic locking tetap jalan.
    //  4. Tidak menghapus revisi historis.
    //  5. Tidak menyentuh published revision / halaman publik.
    //  6. Idempoten: menerapkan dua kali tidak menghasilkan duplikat
    //     (tombol dinonaktifkan setelah berhasil, jadi operasi ulang
    //     dilakukan lewat tombol, bukan tombol dobel tanpa proteksi).
    //  7. Tidak menjalankan publish otomatis.
    //  8. Tidak mengaktifkan feature flag produksi.
    // ================================================================
    var orionTemplateTerapkan = false;

    function applyOrionTemplate() {
        if (orionTemplateTerapkan) return;

        var draftSudahAda = Array.isArray(sections) && sections.length > 0;

        // Langkah 1: konfirmasi bila draft sudah berisi konten.
        if (draftSudahAda) {
            DIALOG.konfirmasi({
                judul: 'Terapkan template Orion Grafika?',
                pesan: 'Draft saat ini berisi ' + sections.length + ' section dan akan DITIMPA oleh template Orion Grafika - Premium Default (10 section).\n\nTindakan ini tidak menghapus riwayat revisi dan tidak mem-publish halaman publik.\n\nLanjutkan?',
                ikon: 'warning',
                setuju: 'Ya, Terapkan Template'
            }).then(function (ya) {
                if (ya) doApplyOrionTemplate();
            });
            return;
        }

        // Draft kosong: langsung terapkan (tidak perlu konfirmasi).
        doApplyOrionTemplate();
    }

    function doApplyOrionTemplate() {
        if (orionTemplateTerapkan) return;
        orionTemplateTerapkan = true;

        var btn = $('lpb-apply-template-btn');
        if (btn) btn.disabled = true;
        showSaveStatus('saving');

        // Bangun section dari preset, normalisasi, dan pakai ID baru.
        var baru = ORION_TEMPLATE.sections.map(function (raw) {
            var salinan = {};
            Object.keys(raw).forEach(function (k) { salinan[k] = raw[k]; });
            if (Array.isArray(raw.items)) {
                // Salin item supaya preset asli tidak ikut berubah.
                salinan.items = raw.items.map(function (it) {
                    var o = {};
                    Object.keys(it).forEach(function (k) { o[k] = it[k]; });
                    return o;
                });
            }
            salinan.id = generateId();
            return normalizeSection(salinan);
        });

        sections = baru;
        selectedIndex = 0;
        lastCoalesceKey = null;
        isDirty = true;

        // Tulis ke history supaya Undo bisa mengembalikan draft lama.
        saveState();
        renderCanvas();
        syncPropertiesFromSelection();

        // Simpan ke server lewat jalur draft yang sama (optimistic locking).
        saveDraft(false).then(function () {
            showSaveStatus('saved', 'Template diterapkan — versi ' + currentDraftVersion);
        });

        // Fokus ke section pertama agar operator langsung bisa mengedit.
        var card = document.querySelector('.lp-section-card[data-index="0"]');
        if (card) card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    $('lpb-apply-template-btn').addEventListener('click', applyOrionTemplate);

    // ---- Tombol kedua Hero (Fase 2 Final) ------------------------------
    ['prop-button2-label', 'buttonLabel2', function (n) { return n.value.slice(0, 50); }]
        .forEach(function (cfg) {
            var node = $(cfg[0]);
            if (!node) return;
            node.addEventListener('input', function () {
                if (selectedIndex < 0) return;
                sections[selectedIndex][cfg[1]] = cfg[2](node);
                markDirty();
                saveState();
                renderCanvas();
            });
        });

    var btn2Style = $('prop-button2-style');
    if (btn2Style) {
        btn2Style.addEventListener('change', function () {
            if (selectedIndex < 0) return;
            sections[selectedIndex].button2Style = btn2Style.value;
            markDirty();
            saveState();
            renderCanvas();
        });
    }

    // ---- Field komposisi visual Hero (gambar02) ----------------------
    var heroPosEl = $('prop-hero-object-position');
    if (heroPosEl) {
        heroPosEl.addEventListener('change', function () {
            if (isHydrating || selectedIndex < 0) return;
            sections[selectedIndex].heroObjectPosition = heroPosEl.value;
            markDirty();
            saveState();
            renderCanvas();
        });
    }
    var heroScaleEl = $('prop-hero-image-scale');
    if (heroScaleEl) {
        heroScaleEl.addEventListener('input', function () {
            if (isHydrating || selectedIndex < 0) return;
            var n = parseInt(heroScaleEl.value, 10);
            if (isNaN(n)) return;
            n = Math.max(60, Math.min(180, n));
            sections[selectedIndex].heroImageScale = n;
            markDirty();
            saveState({ coalesceKey: 'heroImageScale' });
            renderCanvas();
        });
    }
    var heroBleedEl = $('prop-hero-bleed');
    if (heroBleedEl) {
        heroBleedEl.addEventListener('change', function () {
            if (isHydrating || selectedIndex < 0) return;
            sections[selectedIndex].heroBleed = heroBleedEl.checked;
            markDirty();
            saveState();
            renderCanvas();
        });
    }

    // ---- Field khusus section header (Fase 2) --------------------------
    function bindHeaderField(inputId, key, tipe) {
        var node = $(inputId);
        if (!node) return;
        node.addEventListener('input', function () {
            if (selectedIndex < 0) return;
            sections[selectedIndex][key] = node.value;
            markDirty();
            saveState();
            renderCanvas();
        });
    }
    bindHeaderField('prop-header-cta', 'headerCta');

    var stickyBox = $('prop-header-sticky');
    if (stickyBox) {
        stickyBox.addEventListener('change', function () {
            if (selectedIndex < 0) return;
            sections[selectedIndex].sticky = stickyBox.checked;
            markDirty();
            saveState();
            renderCanvas();
        });
    }

    // ---- Field khusus section posts (Fase 2) ---------------------------
    function bindPostsField(inputId, key, read) {
        var node = $(inputId);
        if (!node) return;
        node.addEventListener('input', function () {
            if (selectedIndex < 0) return;
            sections[selectedIndex][key] = read(node);
            markDirty();
            saveState();
            renderCanvas();
        });
    }
    bindPostsField('prop-posts-limit', 'postLimit', function (n) {
        return clampInt(n.value, 1, 12, 6);
    });
    bindPostsField('prop-posts-category', 'postCategory', function (n) {
        return n.value.trim().slice(0, 120);
    });

    var showImageBox = $('prop-posts-showimage');
    if (showImageBox) {
        showImageBox.addEventListener('change', function () {
            if (selectedIndex < 0) return;
            sections[selectedIndex].showImage = showImageBox.checked;
            markDirty();
            saveState();
        });
    }
    var showDateBox = $('prop-posts-showdate');
    if (showDateBox) {
        showDateBox.addEventListener('change', function () {
            if (selectedIndex < 0) return;
            sections[selectedIndex].showDate = showDateBox.checked;
            markDirty();
            saveState();
        });
    }

    $('search-component').addEventListener('input', function (e) {
        var q = e.target.value.trim().toLowerCase();
        var cocok = 0;
        document.querySelectorAll('.component-item').forEach(function (item) {
            // Hanya komponen pustaka yang disaring. Kartu section yang sudah
            // terpasang di kanvas tidak ikut tersembunyi.
            var cocokTeks = (q === '' || item.textContent.toLowerCase().indexOf(q) !== -1);
            item.style.display = cocokTeks ? '' : 'none';
            if (cocokTeks) cocok++;
        });
        var kosong = $('component-search-empty');
        if (kosong) kosong.hidden = (cocok > 0);
    });

    // ================================================================
    //  Media Manager
    // ================================================================
    function openMediaModal() {
        $('media-modal').style.display = 'flex';
        loadMediaList();
    }

    function loadMediaList() {
        var grid = $('media-grid');
        grid.innerHTML = '';
        grid.appendChild(el('p', 'lpb-media-empty', 'Memuat...'));

        fetch(ROUTES.mediaList, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                grid.innerHTML = '';

                // PERBAIKAN: endpoint media membalas dengan kunci "media",
                // bukan "data". Kode lama membaca json.data sehingga daftar
                // media SELALU tampil kosong walau file sudah terupload -
                // inilah penyebab "upload gambar tidak tampil" di picker.
                // "data" tetap dibaca sebagai cadangan supaya aman kalau
                // bentuk respons server berubah lagi.
                var items = [];
                if (json && Array.isArray(json.media)) items = json.media;
                else if (json && Array.isArray(json.data)) items = json.data;

                if (!items.length) {
                    grid.appendChild(el('p', 'lpb-media-empty', 'Belum ada media. Upload gambar di atas.'));
                    return;
                }
                items.forEach(function (item) {
                    var card = el('div', 'lpb-media-card');
                    var thumb = document.createElement('img');
                    thumb.className = 'lpb-media-thumb';
                    // Sengaja BUKAN loading="lazy": modal ini hanya terbuka
                    // karena user menekan "Pilih dari Media", sehingga
                    // thumbnail selalu di dalam tampilan. Dengan lazy,
                    // browser menunda muat saat area berada di luar layar
                    // (mis. halaman belum di-scroll) dan picker tampak kosong
                    // beberapa detik - pernah terbaca sebagai "gambar gagal
                    // dimuat" pada viewport sempit.
                    thumb.loading = 'eager';
                    // alt_text ada di metadata; caption kadang ikut di sana.
                    var meta = item.metadata || {};
                    thumb.alt = meta.alt_text || '';
                    // Prioritkan url absolut dari server; fallback ke path.
                    thumb.src = item.url || (STORAGE_BASE + '/' + item.path);
                    thumb.addEventListener('error', function () { thumb.replaceWith(el('p', 'lpb-hint', 'Gagal muat')); });
                    card.appendChild(thumb);
                    card.appendChild(el('p', 'lpb-media-path', item.path));

                    var use = el('button', 'lpb-media-use', 'Pakai');
                    use.type = 'button';
                    // alt text berada di metadata pada respons media saat ini.
                    use.addEventListener('click', function () { applyMedia(item.path, meta.alt_text || item.alt_text || ''); });
                    card.appendChild(use);
                    grid.appendChild(card);
                });
            })
            .catch(function () {
                grid.innerHTML = '';
                grid.appendChild(el('p', 'lpb-media-empty', 'Gagal memuat media'));
            });
    }

    function applyMedia(path, altText) {
        if (!pendingImageTarget) return;
        var s = sections[pendingImageTarget.sectionIndex];
        if (!s) return;

        if (pendingImageTarget.itemIndex !== null && pendingImageTarget.itemIndex !== undefined) {
            var item = s.items[pendingImageTarget.itemIndex];
            if (!item) return;
            item.image = path;
            if (altText) item.alt_text = altText;
        } else {
            s.image = path;
            if (altText) s.alt_text = altText;
            $('prop-image-url').value = path;
            $('prop-alt-text').value = s.alt_text;
            updateImagePreview();
        }

        pendingImageTarget = null;
        $('media-modal').style.display = 'none';
        lastCoalesceKey = null;
        saveState();
        renderCanvas();
        renderItemsPanel();
    }

    $('close-media-modal').addEventListener('click', function () {
        $('media-modal').style.display = 'none';
        pendingImageTarget = null;
    });

    $('media-upload-btn').addEventListener('click', function () {
        var input = $('media-file-input');
        if (!input.files || !input.files[0]) {
            DIALOG.info({
                judul: 'Belum ada berkas',
                pesan: 'Pilih file gambar terlebih dahulu.',
                ikon: 'info'
            });
            return;
        }
        var form = new FormData();
        form.append('file', input.files[0]);

        $('media-upload-btn').disabled = true;
        fetch(ROUTES.mediaUpload, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': ROUTES.csrf },
            credentials: 'same-origin',
            body: form
        })
            .then(function (res) { return res.json().then(function (j) { return { status: res.status, json: j }; }); })
            .then(function (r) {
                $('media-upload-btn').disabled = false;
                input.value = '';
                if (r.json && r.json.success) {
                    loadMediaList();
                } else {
                    var msg = (r.json && r.json.message) || 'Upload gagal';
                    if (r.json && r.json.errors) {
                        var keys = Object.keys(r.json.errors);
                        if (keys.length) {
                            var first = r.json.errors[keys[0]];
                            if (Array.isArray(first) && first.length) msg = first[0];
                        }
                    }
                    DIALOG.info({
                          judul: 'Unggah ditolak',
                          pesan: msg,
                          ikon: 'error'
                      });
                }
            })
            .catch(function () {
                $('media-upload-btn').disabled = false;
                DIALOG.info({
                      judul: 'Unggah gagal',
                      pesan: 'Periksa koneksi.',
                      ikon: 'error'
                  });
            });
    });

    // ================================================================
    //  Publish
    // ================================================================
    function publishDraft() {
        // Pengaman: tidak ada publish ganda maupun publish saat menyimpan.
        if (isSaving || isPublishing) return;
        if (!isDirty) {
            doPublish();
            return;
        }
        DIALOG.konfirmasi({
            judul: 'Perubahan belum disimpan',
            pesan: 'Simpan draft dulu sebelum publish?',
            ikon: 'warning',
            setuju: 'Simpan lalu Publish'
        }).then(function (ya) {
            if (!ya) return;
            saveDraft(false).then(function () {
                if (isDirty) {
                    DIALOG.info({
                        judul: 'Penyimpanan draft gagal',
                        pesan: 'Publish dibatalkan.',
                        ikon: 'error'
                    });
                    return;
                }
                doPublish();
            });
        });
    }

    function doPublish() {
        if (isPublishing || publishDialogOpen) return;
        publishDialogOpen = true;
        DIALOG.konfirmasi({
            judul: 'Publish draft?',
            pesan: 'Perubahan akan terlihat di halaman publik.',
            ikon: 'warning',
            setuju: 'Ya, Publish'
        }).then(function (ya) {
            publishDialogOpen = false;
            if (!ya) return;
            if (isPublishing) return;
            isPublishing = true;
            showSaveStatus('saving');
            kirimPublish();
        });
    }

    function kirimPublish() {
        fetch(ROUTES.publish, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': ROUTES.csrf
            },
            credentials: 'same-origin',
            body: JSON.stringify({ base_version: currentDraftVersion })
        })
            .then(function (res) { return res.json().then(function (j) { return { status: res.status, json: j }; }); })
            .then(function (r) {
                if (r.status === 409) {
                    showSaveStatus('error', 'Konflik versi');
                    showConflictDialog(r.json.message || 'Draft telah diperbarui.');
                    return;
                }
                if (r.status === 422) {
                    showSaveStatus('error', firstValidationError(r.json) || 'Validasi gagal');
                    return;
                }
                if (r.json && r.json.success) {
                    showSaveStatus('saved', 'Published — revisi #' + r.json.revision_number);
                    return loadDraft();
                }
                showSaveStatus('error', 'Publish gagal');
            })
            .catch(function () { showSaveStatus('error', 'Gagal mempublikasikan'); });
    }

    // ================================================================
    //  Version History
    // ================================================================
    function loadVersionHistory() {
        var modal = $('version-history-modal');
        var list = $('version-history-list');
        modal.style.display = 'flex';
        list.innerHTML = '';
        list.appendChild(el('p', 'lpb-media-empty', 'Memuat...'));

        fetch(ROUTES.revisions, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                list.innerHTML = '';
                var revisions = (json && Array.isArray(json.revisions)) ? json.revisions : [];
                if (!revisions.length) {
                    list.appendChild(el('p', 'lpb-media-empty', 'Belum ada revisi. Publish draft untuk membuat revisi pertama.'));
                    return;
                }
                revisions.forEach(function (rev) {
                    var row = el('div', 'lpb-revision-row' + (rev.is_published ? ' is-published' : ''));

                    var info = el('div', 'lpb-revision-info');
                    var head = el('div', 'lpb-revision-num');
                    head.appendChild(el('span', null, 'Revisi #' + rev.revision_number));
                    if (rev.is_published) {
                        head.appendChild(el('span', 'lpb-revision-badge', 'Published'));
                    }
                    info.appendChild(head);
                    // Semua nilai dari server disisipkan sebagai TEKS.
                    info.appendChild(el('p', 'lpb-revision-meta',
                        String(rev.created_at || '') + ' • ' + String(rev.created_by || '') + ' • ' + String(rev.sections_count || 0) + ' sections'));
                    row.appendChild(info);

                    var restore = el('button', 'lpb-hdr-btn', 'Restore');
                    restore.type = 'button';
                    restore.dataset.revision = String(rev.revision_number);
                    restore.addEventListener('click', function () {
                        DIALOG.konfirmasi({
                            judul: 'Restore revisi?',
                            pesan: 'Restore revisi #' + rev.revision_number + '?\n\nDraft saat ini akan ditimpa (belum dipublish).',
                            ikon: 'warning',
                            setuju: 'Ya, Restore'
                        }).then(function (ya) {
                            if (ya) restoreRevision(rev.revision_number);
                        });
                    });
                    row.appendChild(restore);
                    list.appendChild(row);
                });
            })
            .catch(function () {
                list.innerHTML = '';
                list.appendChild(el('p', 'text-sm text-center py-8', 'Gagal memuat revisi'));
            });
    }

    function restoreRevision(revisionNumber) {
        showSaveStatus('saving');
        var url = ROUTES.restore.replace('__REV__', encodeURIComponent(String(revisionNumber)));

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': ROUTES.csrf
            },
            credentials: 'same-origin',
            body: JSON.stringify({ base_version: currentDraftVersion })
        })
            .then(function (res) { return res.json().then(function (j) { return { status: res.status, json: j }; }); })
            .then(function (r) {
                if (r.status === 409) {
                    showSaveStatus('error', 'Konflik versi');
                    showConflictDialog(r.json.message || 'Draft telah diperbarui.');
                    return;
                }
                if (r.status === 404) { showSaveStatus('error', 'Revisi tidak ditemukan'); return; }
                if (r.status === 422) { showSaveStatus('error', firstValidationError(r.json) || 'Validasi gagal'); return; }
                if (r.json && r.json.success) {
                    $('version-history-modal').style.display = 'none';
                    selectedIndex = -1;
                    return loadDraft().then(function () {
                        showSaveStatus('saved', 'Direstore dari revisi #' + revisionNumber);
                    });
                }
                showSaveStatus('error', 'Restore gagal');
            })
            .catch(function () { showSaveStatus('error', 'Gagal merestorasi'); });
    }

    // ================================================================
    //  Shortcut & tombol
    // ================================================================
    $('undo-btn').addEventListener('click', undo);
    $('redo-btn').addEventListener('click', redo);
    $('save-draft-btn').addEventListener('click', function () {
        retryCount = 0;
        saveDraft(false);
    });
    $('publish-btn').addEventListener('click', publishDraft);

    // ---- Preview Website ----------------------------------------------
    // Buka full-page preview draft di tab baru. TIDAK melakukan publish,
    // TIDAK menambah revisi, TIDAK mengubah versi draft.
    //
    // Kebijakan perubahan belum disimpan: jangan dibuang diam-diam. Kita
    // tanya Administrator dulu. Bila setuju, draft disimpan lewat jalur
    // autosave yang sudah disetujui, lalu preview dibuka. Bila tidak,
    // preview tetap dibuka dengan draft yang tersimpan di server (nilai
    // terakhir yang tersimpan) — perubahan lokal yang belum disimpan tidak
    // ikut, dan hal itu diinformasikan lewat dialog.
    $('preview-website-btn').addEventListener('click', function () {
        var bukaPreview = function () {
            window.open(ROUTES.previewDraft, '_blank', 'noopener');
        };

        if (!isDirty) {
            bukaPreview();
            return;
        }

        DIALOG.konfirmasi({
            judul: 'Perubahan belum disimpan',
            pesan: 'Ada perubahan yang belum disimpan ke draft.\n\nSimpan dulu sebelum membuka preview?',
            ikon: 'warning',
            setuju: 'Simpan & Preview'
        }).then(function (ya) {
            if (ya) {
                // Simpan lewat jalur yang sama dengan tombol Simpan Draft.
                saveDraft(false).then(function () {
                    bukaPreview();
                });
            } else {
                // Batal: buka preview dengan draft tersimpan di server.
                bukaPreview();
            }
        });
    });
    $('version-history-btn').addEventListener('click', loadVersionHistory);
    $('close-version-history').addEventListener('click', function () {
        $('version-history-modal').style.display = 'none';
    });

    document.addEventListener('keydown', function (e) {
        var typing = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName);

        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z' && !e.shiftKey) {
            e.preventDefault(); undo(); return;
        }
        if ((e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 'y' || (e.shiftKey && e.key.toLowerCase() === 'z'))) {
            e.preventDefault(); redo(); return;
        }
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
            e.preventDefault();
            retryCount = 0;
            saveDraft(false);
            return;
        }
        if (e.key === 'Escape') {
            $('media-modal').style.display = 'none';
            $('version-history-modal').style.display = 'none';
            pendingImageTarget = null;
            return;
        }
        // Hapus section terpilih dengan Delete saat tidak mengetik.
        if ((e.key === 'Delete' || e.key === 'Backspace') && !typing && selectedIndex >= 0) {
            e.preventDefault();
            var btn = document.querySelector('.lp-section-card[data-index="' + selectedIndex + '"] button[data-action="delete"]');
            if (btn) btn.click();
        }
    });

    // Peringatan saat meninggalkan dengan perubahan belum tersimpan.
    window.addEventListener('beforeunload', function (e) {
        if (!isDirty) return;
        e.preventDefault();
        e.returnValue = '';
    });

    // ================================================================
    //  Inisialisasi
    // ================================================================
    resetHistory();
    syncPropertiesFromSelection();
    loadDraft();
})();
</script>

@endpush
@endsection
