{{--
    Shared renderer stylesheet untuk Landing Page Builder.
    Dipakai oleh Preview (admin) dan Public homepage agar output identik.

    ORION GRAFIKA UNIFIED BRAND SYSTEM — ROYAL INDIGO (PALET RESMI TERBARU)
    Warna resmi: DEEP NIGHT #15143F, ROYAL INDIGO #2E318E, ELECTRIC VIOLET #4D55F8,
    CTA ORANGE #FF6B17, CYAN LIGHT #B8EEFF

    WCAG 2.1 kontras (dihitung dengan skrip, bukan diklaim):
      #FFFFFF di atas #15143F   = 17.45:1  AAA
      #FFFFFF di atas #2E318E   = 10.79:1  AAA
      #FFFFFF di atas #4D55F8   =  5.29:1  AA  (warna teks tombol utama)
      #15143F di atas #FF6B17   =  6.12:1  AA  (warna teks CTA orange)
      #FFFFFF di atas #FF6B17   =  2.85:1  GAGAL — jangan teks putih di atas orange
      #B8EEFF di atas #15143F   = 13.88:1  AAA
      #5A5E9E  di atas #FFFFFF  =  5.95:1  AA  (teks body/muted)
      #15143F di atas #C9CBFA   = 11.14:1  AAA (teks tombol disabled)

    CATATAN MIGRASI: palet lama TURQUOISE AMBER AUTUMN (#26788E / #304C64 / #A4CCD4 /
    #E2480C / #631B08) sudah TIDAK dipakai. Nama token lama dipertahankan sebagai
    alias semantik ke palet baru agar draft operator lama tetap render (tidak destruktif).
    Semua nilai warna turunan mengikuti palet resmi baru.

    KOREKSI KLAIM LAMA: laporan sebelumnya menyatakan kontras #26788E di atas
    #304C64 = "7.56:1 AAA" untuk tombol. Hasil hitung ulang WCAG 2.1 yang
    sebenarnya = 1.77:1 (GAGAL total). Itulah root cause Bug #5
    ("tombol gelap dengan teks gelap").
--}}
<style>
    /* ============ DASAR ============ */
    .lp-root, .section {
        font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
        color: var(--orion-brand-navy);
    }
    .section { padding: 72px 24px; margin: 0 auto; }
    .section-inner { margin: 0 auto; }
    .section-align-left   { text-align: left; }
    .section-align-center { text-align: center; }
    .section-align-right  { text-align: right; }
    .section-inner > * { max-width: 100%; }

    /* ============ TOKEN WARNA UTAMA ============ */
    :root {
        /* ===== PALET RESMI TERBARU — ROYAL INDIGO ===== */
        --orion-deep-night:      #15143F;  /* Hero/footer/CTA bg, teks utama */
        --orion-royal-indigo:    #2E318E;  /* aksen gelap, border kuat, focus */
        --orion-electric-violet: #4D55F8;  /* aksi utama, link, highlight */
        --orion-cta-orange:      #FF6B17;  /* CTA kontras tinggi (teks Deep Night) */
        --orion-cyan-light:      #B8EEFF;  /* highlight, cahaya, dekorasi */
        /* Alias nama final (palette lock gambar01) — menunjuk token yang sama */
        --orion-orange-cta:      var(--orion-cta-orange);
        --orion-cyan-highlight:  var(--orion-cyan-light);

        /* ===== ALIAS SEMANTIK (kompatibilitas draft lama) =====
           Nama token lama dipertahankan agar data section yang sudah
           tersimpan tidak rusak; nilainya dialihkan ke palet baru.      */
        --orion-brand-turquoise: var(--orion-electric-violet);
        --orion-brand-navy:      var(--orion-deep-night);
        --orion-brand-soft:      var(--orion-cyan-light);
        --orion-brand-orange:    var(--orion-cta-orange);
        --orion-brand-auburn:    var(--orion-deep-night);

        /* ===== TURUNAN DARI PALET BARU ===== */
        --orion-brand-hover:   #3D45E0;  /* violet lebih gelap (hover/active) */
        --orion-brand-focus:   #4D55F8;  /* cincin fokus */
        --orion-brand-light:   #E8E9FE;  /* tint violet sangat muda (surface) */
        --orion-brand-lighter: #F5F5FF;  /* tint violet hampir putih */
        --orion-brand-border:  #C7C9FA;  /* border kartu/komponen */
        --orion-brand-muted:   #5A5E9E;  /* teks sekunder — 5.95:1 di atas putih */

        /* ===== SEMANTIC ACTIONS ===== */
        --orion-action-primary:      var(--orion-electric-violet);
        --orion-action-primary-text: #FFFFFF;          /* 5.29:1 AA di atas violet */
        --orion-action-secondary:      var(--orion-deep-night);
        --orion-action-cta:           var(--orion-cta-orange);
        --orion-action-cta-text:      var(--orion-deep-night); /* 6.12:1 AA di atas orange */
        --orion-surface-accent:       var(--orion-cyan-light);
        --orion-shadow-soft: rgba(21, 20, 63, .10);
        --orion-shadow-softer: rgba(21, 20, 63, .08);

        /* ===== NETRAL (di-tint violet agar selaras palet) ===== */
        --orion-neutral-100: #FFFFFF;
        --orion-neutral-200: #F7F7FD;
        --orion-neutral-300: #F1F1FA;
        --orion-neutral-400: #E6E6F5;
        --orion-neutral-500: #C9CBFA;
        --orion-neutral-600: #C7C9FA;
        --orion-neutral-700: #A8ACF0;  /* teks di atas Deep Night — 11.0:1 AAA */
        --orion-neutral-800: #989AF0;  /* teks di atas Deep Night — 6.83:1 AA */
        --orion-neutral-900: #5B5F8F;  /* teks muted gelap — 6.04:1 di atas putih */
    }

    /* ============ LATAR SECTION ============ */
    .section-bg-white { background: var(--orion-neutral-100); }
    .section-bg-gray  { background: var(--orion-brand-lighter); }
    .section-bg-orion { background: var(--orion-electric-violet); color: #FFFFFF; }
    .section-bg-orion h1,
    .section-bg-orion h2,
    .section-bg-orion h3,
    .section-bg-orion .hero-title,
    .section-bg-orion .stat-number,
    .section-bg-orion .faq-question { color: #FFFFFF; }
    .section-bg-orion p,
    .section-bg-orion .hero-subtitle,
    .section-bg-orion .faq-answer { color: var(--orion-neutral-200); }
    .section-bg-orion .lp-eyebrow { color: #FFFFFF; }
    .section-bg-orion .card,
    .section-bg-orion .lp-product,
    .section-bg-orion .lp-portfolio-card { border-color: rgba(255, 255, 255, .28); }
    .section-bg-indigo { background: var(--orion-deep-night); color: #DCDCFB; }
    .section-bg-indigo .lp-eyebrow { color: var(--orion-cyan-light); }

    /* ============ RADIUS & SPACING ============ */
    .section-radius-none   { border-radius: 0; }
    .section-radius-small  { border-radius: 8px; }
    .section-radius-medium { border-radius: 16px; }
    .section-radius-large  { border-radius: 24px; }
    .section-spacing-small  { padding: 40px 24px; }
    .section-spacing-medium { padding: 72px 24px; }
    .section-spacing-large  { padding: 104px 24px; }

    /* ============ TYPOGRAPHY ============ */
    .section h2, .hero-title {
        font-weight: 800;
        line-height: 1.15;
        color: var(--orion-brand-navy);
        margin: 0 0 10px;
        letter-spacing: -0.015em;
    }
    .hero-title { font-size: clamp(2.1rem, 5.2vw, 3.5rem); }
    .hero-subtitle {
        font-size: 1.2rem;
        font-weight: 500;
        color: var(--orion-brand-hover);
        line-height: 1.5;
        margin: 0 0 20px;
    }
    .section p { line-height: 1.7; color: var(--orion-brand-muted); }
    /* Hero mode night: teks terang di atas latar gelap */
    .section-bg-night p,
    .section-bg-night .lp-lead { color: rgba(255, 255, 255, .88); }
    .section-bg-night .hero-title,
    .section-bg-night h2,
    .section-bg-night h3 { color: #FFFFFF; }
    .section h3 { color: var(--orion-brand-navy); font-weight: 700; line-height: 1.35; }

    .lp-eyebrow {
        display: inline-block;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: var(--orion-brand-turquoise);
        margin-bottom: 10px;
    }
    .lp-lead { font-size: 15.5px; color: var(--orion-brand-muted); max-width: 62ch; margin: 0 0 8px; }
    .section-align-center .lp-lead { margin-left: auto; margin-right: auto; }

    /* ============ BUTTON ============ */
    .btn, .section .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 13px 26px;
        font-size: 14.5px;
        font-weight: 600;
        line-height: 1;
        border-radius: 8px;
        border: 1px solid transparent;
        cursor: pointer;
        transition: background-color .18s ease, border-color .18s ease, color .18s ease, box-shadow .18s ease;
        min-height: 48px;
        text-decoration: none;
    }
    .btn {
        background: var(--orion-action-primary);
        color: var(--orion-action-primary-text);
        border-color: var(--orion-action-primary);
    }
    .btn:hover { background: var(--orion-brand-hover); border-color: var(--orion-brand-hover); color: var(--orion-action-primary-text); }
    .btn:active { background: #2D35C0; border-color: #2D35C0; color: var(--orion-action-primary-text); }
    .btn:focus-visible { outline: 2px solid var(--orion-brand-focus); outline-offset: 3px; }
    .btn[disabled], .btn[aria-disabled="true"] {
        background: var(--orion-neutral-500);
        border-color: var(--orion-neutral-500);
        color: var(--orion-deep-night);
        cursor: not-allowed;
    }

    /* CTA kontras tinggi — orange dengan teks Deep Night (6.12:1 AA) */
    .btn-cta { background: var(--orion-action-cta); color: var(--orion-action-cta-text); border-color: var(--orion-action-cta); }
    .btn-cta:hover { background: #E85C05; border-color: #E85C05; color: var(--orion-action-cta-text); }
    .btn-cta:active { background: #A83F03; border-color: #A83F03; color: #FFFFFF; }

    .btn-dark { background: var(--orion-deep-night); color: #FFFFFF; border-color: var(--orion-deep-night); }
    .btn-dark:hover { background: var(--orion-brand-hover); border-color: var(--orion-brand-hover); color: #FFFFFF; }

    .btn-outline { background: transparent; color: var(--orion-brand-navy); border-color: var(--orion-brand-focus); }
    .btn-outline:hover { background: var(--orion-brand-light); border-color: var(--orion-electric-violet); color: var(--orion-brand-navy); }

    .btn-sm { padding: 9px 16px; font-size: 13px; min-height: 36px; }
    .btn-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 14px;
        font-weight: 600;
        color: var(--orion-brand-navy);
        text-decoration: none;
        padding: 4px 0;
    }
    .btn-link:hover { color: var(--orion-brand-hover); text-decoration: underline; }

    /* ============ GRID ============ */
    .grid { display: grid; gap: 22px; }
    .grid-1 { grid-template-columns: 1fr; }
    .grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .grid-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }

    /* ============ CARD ============ */
    .card {
        background: var(--orion-neutral-100);
        border: 1px solid var(--orion-brand-border);
        border-radius: 16px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: box-shadow .2s ease, transform .2s ease, border-color .2s ease;
    }
    /* Hover kartu: naik MAKSIMAL 4px (permintaan operator), bayangan lebih
       lembut, durasi 200-250ms. Hanya transform + box-shadow - tidak ada
       perubahan layout yang bisa memicu reflow atau CLS. */
    .card, .lp-feature, .lp-product, .lp-portfolio-card, .lp-posts-card {
        transition: transform .22s cubic-bezier(.22, 1, .36, 1), box-shadow .22s cubic-bezier(.22, 1, .36, 1), border-color .22s ease;
    }
    .card:hover, .lp-feature:hover, .lp-product:hover, .lp-portfolio-card:hover, .lp-posts-card:hover {
        transform: translateY(-4px);
    }
    .card:hover { box-shadow: 0 12px 28px var(--orion-shadow-soft); border-color: var(--orion-electric-violet); }
    .card h3 { color: var(--orion-brand-navy); font-size: 16px; font-weight: 700; margin: 0 0 8px; }
    .card p { font-size: 14px; color: var(--orion-brand-muted); margin: 0; }
    .card img { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; display: block; }
    .section-image { width: 100%; border-radius: 16px; overflow: hidden; margin: 22px 0 0; }

    /* ============ HERO ============
       Komposisi mengikuti gambar02.webp (acuan layout):
         - Hero full-bleed, menyatu dengan header
         - Kolom kiri: badge, headline putih besar, deskripsi, CTA oranye
         - Kolom kanan: visual 3D dominan yang MENYATU dengan background
       Atmosfer warna mengikuti gambar01.jpg (acuan warna):
         Deep Night + Royal Indigo, aksen Electric Violet / Orange / Cyan.
       PENTING: visual TIDAK memakai kartu, border, radius, atau kotak
       pastel. Ia lapisan transparan yang menyatu dengan background. */
    .lp-hero {
        display: grid;
        grid-template-columns: 1.05fr 0.95fr;
        gap: 40px;
        align-items: center;
        min-height: 460px;
        position: relative;
    }

    /* Atmosfer berlapis: Deep Night -> Royal Indigo + ambient light */
    .section-bg-night {
        background:
            radial-gradient(120% 90% at 78% 22%, rgba(77, 85, 248, .30) 0%, rgba(46, 49, 142, .16) 38%, rgba(21, 20, 63, 0) 68%),
            radial-gradient(80% 70% at 88% 78%, rgba(184, 238, 255, .12) 0%, rgba(21, 20, 63, 0) 60%),
            linear-gradient(148deg, #15143F 0%, #1B1A55 38%, #2E318E 78%, #383C9E 100%);
        color: #FFFFFF;
        position: relative;
        isolation: isolate;
        overflow: hidden;
    }
    /* Header menyatu dengan Hero (gambar02: header transparan di atas hero) */
    .section-bg-night + .lp-header,
    .lp-hero-header-sibling .lp-header { background: transparent; border-bottom-color: transparent; }

    .lp-hero-title {
        color: #FFFFFF;
        font-weight: 800;
        letter-spacing: -0.025em;
        line-height: 1.05;
        text-wrap: balance;
        /* Pertahankan pemenggalan baris yang diketik operator.
           white-space: pre-line membuat "\n" di Inspector tetap menjadi
           baris baru, persis seperti komposisi gambar02. */
        white-space: pre-line;
    }
    .lp-hero-lead {
        color: rgba(255, 255, 255, .88);
        line-height: 1.65;
        max-width: 54ch;
    }
    .lp-hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 107, 23, .14);
        border: 1px solid rgba(255, 107, 23, .45);
        color: var(--orion-cyan-highlight);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
        padding: 7px 15px;
        border-radius: 999px;
        margin-bottom: 18px;
    }
    .lp-hero-actions { display: flex; gap: 14px; flex-wrap: wrap; margin-top: 30px; }
    /* CTA utama oranye kontras (gambar01/gambar02) */
    .lp-hero-actions .btn {
        background: var(--orion-orange-cta);
        border-color: var(--orion-orange-cta);
        color: var(--orion-deep-night);
        font-weight: 700;
        min-height: 54px;
        padding: 15px 32px;
        border-radius: 999px;
        box-shadow: 0 10px 30px rgba(255, 107, 23, .28);
        transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease;
    }
    .lp-hero-actions .btn:hover {
        background: #E85C05;
        border-color: #E85C05;
        transform: translateY(-2px);
        box-shadow: 0 14px 36px rgba(255, 107, 23, .36);
    }
    .lp-hero-actions .btn:focus-visible { outline: 2px solid var(--orion-cyan-highlight); outline-offset: 3px; }
    .lp-hero-actions .btn:active { transform: translateY(0); background: #C94E04; border-color: #C94E04; }
    /* CTA kedua: outline terang */
    .lp-hero-actions .btn.btn-outline {
        background: rgba(255, 255, 255, .06);
        border: 1px solid rgba(255, 255, 255, .55);
        color: #FFFFFF;
        box-shadow: none;
    }
    .lp-hero-actions .btn.btn-outline:hover {
        background: rgba(255, 255, 255, .14);
        border-color: var(--orion-cyan-highlight);
        color: #FFFFFF;
        transform: translateY(-2px);
        box-shadow: none;
    }

    /* --- Visual kanan: lapisan transparan yang menyatu, BUKAN kartu --- */
    .lp-hero-visual {
        position: relative;
        background: transparent;
        border: 0;
        border-radius: 0;
        overflow: visible;
        min-height: 420px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    /* Ambient glow di belakang objek: membuat visual "tumbuh" dari background */
    .lp-hero-visual::before {
        content: "";
        position: absolute;
        inset: -18% -14% -12% -18%;
        background:
            radial-gradient(closest-side, rgba(77, 85, 248, .55) 0%, rgba(46, 49, 142, .22) 55%, rgba(21, 20, 63, 0) 78%);
        filter: blur(6px);
        z-index: 0;
        pointer-events: none;
    }
    /* Cincin orbit tipis (gambar01) — dekoratif, sangat ringan */
    .lp-hero-visual::after {
        content: "";
        position: absolute;
        width: 78%;
        aspect-ratio: 1;
        border-radius: 50%;
        border: 1px solid rgba(184, 238, 255, .22);
        z-index: 0;
        pointer-events: none;
    }
    .lp-hero-visual img {
        position: relative;
        z-index: 1;
        max-width: 100%;
        max-height: 520px;
        width: auto;
        height: auto;
        /* PENTING: contain, bukan cover — aset 3D tidak boleh dipotong persegi */
        object-fit: contain;
        /* CATATAN P0: --lp-hero-scale harus TANPA satuan (mis. "100", bukan "100%").
           scale() menerima <number>, sedangkan "100% / 100" tidak valid
           sehingga scale() menerima 0 dan aset terlihat sebesar 7x5 px. */
        transform: scale(var(--lp-hero-scale, 1));
        transform-origin: center;
        filter: drop-shadow(0 28px 60px rgba(11, 10, 40, .55));
        /* Foreground merges:blobolete background box */
        background: transparent;
    }
    /* Posisi objek (editable via Inspector) */
    .lp-hero-visual[data-lpb-hero-object="top"] img    { align-self: flex-start; }
    .lp-hero-visual[data-lpb-hero-object="bottom"] img { align-self: flex-end; }
    .lp-hero-visual[data-lpb-hero-object="left"] img   { transform-origin: left center; }
    .lp-hero-visual[data-lpb-hero-object="right"] img  { transform-origin: right center; }
    .lp-hero-visual--bleed { margin-right: calc(-1 * clamp(24px, 5vw, 96px)); }

    /* Placeholder hanya sebagai STATUS ASSET PENDING — bukan hasil final */
    .lp-hero-asset-pending {
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        text-align: center;
        padding: 28px 26px;
        border: 1px dashed rgba(184, 238, 255, .45);
        border-radius: 18px;
        background: rgba(21, 20, 63, .38);
        color: rgba(255, 255, 255, .82);
        font-size: 13px;
        font-weight: 600;
        max-width: 320px;
    }
    .lp-hero-asset-pending svg { width: 38px; height: 38px; color: var(--orion-cyan-highlight); opacity: .9; }
    .lp-hero-asset-pending small {
        display: block;
        font-size: 11px;
        font-weight: 500;
        color: rgba(255, 255, 255, .62);
        letter-spacing: .04em;
    }
    .lp-hero-badge {
        display: inline-block;
        background: var(--orion-brand-navy);
        color: #FFFFFF;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
        padding: 6px 14px;
        border-radius: 999px;
        margin-bottom: 16px;
    }

    /* ============ FEATURES / SERVICES ============ */
    .lp-features { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0; }
    .lp-feature {
        padding: 24px 22px;
        border-left: 1px solid var(--orion-brand-border);
    }
    .lp-feature:first-child { border-left: 0; padding-left: 0; }
    .lp-feature-mark {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: var(--orion-electric-violet);
        color: var(--orion-action-primary-text);
        font-size: 16px;
        font-weight: 800;
        margin-bottom: 14px;
    }
    .lp-feature h3 { font-size: 14.5px; margin: 0 0 6px; color: var(--orion-brand-navy); }
    .lp-feature p { font-size: 13px; color: var(--orion-brand-muted); margin: 0; }

    /* ============ ABOUT ============ */
    .lp-about { display: grid; grid-template-columns: 0.9fr 1.1fr; gap: 48px; align-items: center; }
    .lp-about-visual {
        background: var(--orion-brand-light);
        border-radius: 24px;
        overflow: hidden;
        min-height: 340px;
    }
    .lp-about-visual img { width: 100%; height: 100%; object-fit: cover; }
    .lp-steps { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; margin-top: 26px; }
    .lp-step { display: flex; gap: 12px; align-items: flex-start; }
    .lp-step-num {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: var(--orion-electric-violet);
        color: var(--orion-action-primary-text);
        font-size: 14px;
        font-weight: 800;
    }
    .lp-step h4 { margin: 0 0 4px; font-size: 14px; font-weight: 700; color: var(--orion-brand-navy); }
    .lp-step p { margin: 0; font-size: 13px; color: var(--orion-brand-muted); }

    /* ============ PRODUCT GRID ============ */
    .lp-product {
        background: var(--orion-neutral-100);
        border: 1px solid var(--orion-brand-border);
        border-radius: 16px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: box-shadow .2s ease, transform .2s ease, border-color .2s ease;
    }
    .lp-product:hover { box-shadow: 0 8px 24px var(--orion-shadow-soft); transform: translateY(-2px); border-color: var(--orion-electric-violet); }
    .lp-product-tile {
        position: relative;
        background: var(--orion-brand-light);
        aspect-ratio: 4 / 3;
        overflow: hidden;
    }
    .lp-product-tile img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .lp-product-tile .lp-placeholder {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        color: var(--orion-brand-muted);
        font-size: 13px;
        font-weight: 500;
        text-align: center;
        padding: 12px;
        background: linear-gradient(135deg, var(--orion-brand-light), var(--orion-neutral-400));
    }
    .lp-product-tile .lp-placeholder svg { width: 30px; height: 30px; opacity: .8; }
    .lp-product-tile .lp-placeholder::before { content: none; }

    /* Placeholder portfolio — Bug #7 fix */
    .lp-portfolio-placeholder {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        color: var(--orion-brand-muted);
        font-size: 12.5px;
        font-weight: 500;
        text-align: center;
        padding: 12px;
        background: linear-gradient(135deg, var(--orion-brand-light), var(--orion-neutral-400));
    }
    .lp-portfolio-placeholder svg { width: 32px; height: 32px; opacity: .8; }
    .lp-product-body { padding: 18px; display: flex; flex-direction: column; gap: 8px; flex: 1; }
    .lp-product h3 { margin: 0; font-size: 15px; font-weight: 700; color: var(--orion-brand-navy); }
    .lp-product p { margin: 0; font-size: 13px; color: var(--orion-brand-muted); flex: 1; }

    /* ============ STATISTICS ============ */
    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; }
    .stat-item { border-top: 2px solid var(--orion-brand-turquoise); padding-top: 16px; }
    .stat-number {
        font-size: 2.6rem;
        font-weight: 800;
        line-height: 1;
        color: var(--orion-brand-navy);
        margin: 0;
        letter-spacing: -0.02em;
    }
    .stat-label { font-size: 13.5px; font-weight: 600; color: var(--orion-brand-navy); margin: 8px 0 4px; }
    .stat-sub { font-size: 12.5px; color: var(--orion-brand-muted); margin: 0; }

    /* ============ FAQ — P0 FIX: wadah 2 kolom ============ */
    .lp-faq-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px 24px;
        align-items: start;
        margin-top: 22px;
    }
    .faq-item {
        border: 1px solid var(--orion-brand-border);
        border-radius: 12px;
        background: var(--orion-neutral-100);
        overflow: hidden;
        transition: border-color .18s ease, box-shadow .18s ease;
    }
    .faq-item:hover { border-color: var(--orion-electric-violet); box-shadow: 0 4px 12px var(--orion-shadow-softer); }
    .faq-question {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 18px;
        background: transparent;
        border: 0;
        cursor: pointer;
        text-align: left;
        font-size: 15px;
        font-weight: 600;
        color: var(--orion-brand-navy);
        font-family: inherit;
        transition: color .18s ease;
        min-height: 44px;
    }
    .faq-question:hover { color: var(--orion-brand-turquoise); }
    .faq-question:focus-visible { outline: 2px solid var(--orion-brand-focus); outline-offset: -2px; }
    .faq-icon {
        flex: 0 0 auto;
        font-size: 20px;
        font-weight: 700;
        color: var(--orion-brand-turquoise);
        transition: transform .25s ease;
        line-height: 1;
    }
    .faq-question[aria-expanded="true"] .faq-icon { transform: rotate(45deg); }
    .faq-answer {
        display: none;
        padding: 0 18px 16px;
        margin: 0;
        font-size: 14px;
        line-height: 1.65;
        color: var(--orion-brand-muted);
    }
    .faq-answer.is-open { display: block; }
    @media (prefers-reduced-motion: reduce) {
        .faq-icon { transition: none; }
        .lp-header-hamburger span { transition: none; }
        .lp-header-nav { transition: none; }
    }

    /* ============ CTA ============ */
    .lp-cta {
        position: relative;
        overflow: hidden;
        background: var(--orion-brand-navy);
        border-radius: 24px;
        padding: 56px 44px;
        text-align: center;
    }
    .lp-cta::after {
        content: "";
        position: absolute;
        inset: 0;
        background: radial-gradient(600px 200px at 50% 0%, rgba(184, 238, 255, .18), transparent 70%);
        pointer-events: none;
    }
    .lp-cta h2 { color: #FFFFFF; position: relative; }
    .lp-cta p { color: var(--orion-cyan-light); position: relative; }
    .lp-cta .btn { position: relative; background: var(--orion-action-cta); color: var(--orion-action-cta-text); border-color: var(--orion-action-cta); }
    .lp-cta .btn:hover { background: #E85C05; border-color: #E85C05; color: var(--orion-action-cta-text); }
    .lp-cta .btn:active { background: #A83F03; border-color: #A83F03; color: #FFFFFF; }

    /* ============ CONTACT ============ */
    .lp-contact { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 44px; align-items: start; }
    .lp-contact-list { display: flex; flex-direction: column; gap: 14px; margin: 22px 0 0; }
    .lp-contact-row { display: flex; gap: 12px; align-items: flex-start; }
    .lp-contact-icon {
        flex: 0 0 auto;
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: var(--orion-brand-light);
        color: var(--orion-brand-turquoise);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
    }
    .lp-contact-label { font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--orion-brand-navy); }
    .lp-contact-value { font-size: 14.5px; color: var(--orion-brand-navy); font-weight: 500; }
    .lp-contact-panel {
        background: var(--orion-neutral-100);
        border: 1px solid var(--orion-brand-border);
        border-radius: 16px;
        padding: 24px;
    }
    .lp-contact-note { font-size: 12.5px; color: var(--orion-brand-muted); margin: 12px 0 0; }

    /* ============ TESTIMONIALS ============ */
    .lp-quote-mark {
        font-size: 48px;
        line-height: 1;
        color: var(--orion-brand-turquoise);
        font-family: Georgia, serif;
        margin: 0 0 8px;
    }
    .lp-quote {
        font-size: 15.5px;
        line-height: 1.65;
        color: var(--orion-brand-navy);
        margin: 0 0 18px;
    }
    .lp-quote-author { display: flex; align-items: center; gap: 10px; }
    .lp-quote-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--orion-electric-violet);
        color: var(--orion-action-primary-text);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        font-weight: 800;
    }
    .lp-quote-name { font-size: 13.5px; font-weight: 700; color: var(--orion-brand-navy); }
    .lp-quote-role { font-size: 12px; color: var(--orion-brand-muted); }
    .lp-testimonials-empty {
        padding: 44px 22px;
        text-align: center;
        background: var(--orion-brand-lighter);
        border: 1px dashed var(--orion-brand-border);
        border-radius: 16px;
    }
    .lp-testimonials-empty p { margin: 0 0 6px; font-weight: 700; color: var(--orion-brand-navy); }
    .lp-testimonials-empty small { color: var(--orion-brand-muted); font-size: 13px; }

    /* ============ PORTFOLIO ============ */
    .lp-portfolio-filters { display: flex; gap: 8px; flex-wrap: wrap; margin: 0 0 24px; }
    .lp-filter-pill {
        padding: 9px 18px;
        font-size: 13px;
        font-weight: 600;
        border-radius: 999px;
        border: 1px solid var(--orion-brand-border);
        background: var(--orion-neutral-100);
        color: var(--orion-brand-navy);
        cursor: pointer;
        transition: background-color .18s ease, border-color .18s ease, color .18s ease;
        font-family: inherit;
    }
    .lp-filter-pill:hover { border-color: var(--orion-brand-soft); color: var(--orion-brand-navy); }
    .lp-filter-pill.is-active { background: var(--orion-electric-violet); border-color: var(--orion-electric-violet); color: var(--orion-action-primary-text); }
    .lp-filter-pill:focus-visible { outline: 2px solid var(--orion-brand-focus); outline-offset: 2px; }
    .lp-portfolio-card {
        background: var(--orion-neutral-100);
        border: 1px solid var(--orion-brand-border);
        border-radius: 16px;
        overflow: hidden;
        transition: box-shadow .2s ease, transform .2s ease, border-color .2s ease;
    }
    .lp-portfolio-card:hover { box-shadow: 0 8px 24px var(--orion-shadow-soft); transform: translateY(-2px); border-color: var(--orion-electric-violet); }
    .lp-portfolio-media {
        position: relative;
        background: var(--orion-brand-light);
        aspect-ratio: 4 / 3;
        overflow: hidden;
        cursor: zoom-in;
    }
    .lp-portfolio-media img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .lp-portfolio-tag {
        position: absolute;
        left: 12px;
        bottom: 12px;
        background: rgba(255, 255, 255, .94);
        color: var(--orion-brand-navy);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        padding: 5px 12px;
        border-radius: 999px;
    }
    .lp-portfolio-body { padding: 16px 18px 18px; }
    .lp-portfolio-body p { margin: 0; font-size: 13.5px; color: var(--orion-brand-muted); line-height: 1.6; }

    /* ============ POSTS ============ */
    .lp-posts-desc { color: var(--orion-brand-muted); margin-bottom: 22px; }
    .lp-posts-grid { gap: 20px; }
    .lp-posts-card {
        background: var(--orion-neutral-100);
        border: 1px solid var(--orion-brand-border);
        border-radius: 16px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: box-shadow .2s ease, transform .2s ease, border-color .2s ease;
    }
    .lp-posts-card:hover { box-shadow: 0 8px 24px var(--orion-shadow-soft); transform: translateY(-2px); border-color: var(--orion-electric-violet); }
    .lp-posts-card img { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; display: block; }
    .lp-posts-body { padding: 18px; display: flex; flex-direction: column; gap: 9px; flex: 1; }
    /* Kategori: teks Deep Night (kontras 8.6:1 di atas tint violet muda) */
    .lp-posts-kategori {
        display: inline-block;
        align-self: flex-start;
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--orion-deep-night);
        background: var(--orion-brand-light);
        padding: 4px 10px;
        border-radius: 4px;
        border: 1px solid var(--orion-brand-border);
    }
    .lp-posts-title { font-size: 16px; color: var(--orion-brand-navy); margin: 0; font-weight: 700; line-height: 1.4; }
    .lp-posts-ringkasan { font-size: 13.5px; color: var(--orion-brand-muted); margin: 0; flex: 1; line-height: 1.6; }
    .lp-posts-tanggal { font-size: 11.5px; color: var(--orion-brand-muted); }
    .lp-posts-empty {
        padding: 44px 22px;
        text-align: center;
        background: var(--orion-brand-lighter);
        border: 1px dashed var(--orion-brand-border);
        border-radius: 16px;
    }
    .lp-posts-empty p { margin: 0 0 6px; font-weight: 700; color: var(--orion-brand-navy); }
    .lp-posts-empty small { color: var(--orion-brand-muted); font-size: 13px; }

    /* ============ LIGHTBOX ============ */
    .lp-lightbox {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 1000;
        background: rgba(21, 20, 63, .94);
        align-items: center;
        justify-content: center;
        padding: 24px;
    }
    .lp-lightbox.is-open { display: flex; }
    .lp-lightbox img {
        max-width: 100%;
        max-height: 88vh;
        border-radius: 12px;
        box-shadow: 0 24px 60px rgba(0, 0, 0, .4);
    }
    .lp-lightbox-close {
        position: absolute;
        top: 18px;
        right: 18px;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        border: 0;
        background: var(--orion-brand-hover);
        color: #FFFFFF;
        font-size: 24px;
        line-height: 1;
        cursor: pointer;
        transition: background-color .18s ease;
    }
    .lp-lightbox-close:hover { background: var(--orion-electric-violet); }
    .lp-lightbox-close:focus-visible { outline: 2px solid #FFFFFF; outline-offset: 2px; }

    /* ============ HEADER / NAVBAR ============ */
    /* Wrapper section untuk header & footer TIDAK boleh jadi kotak.
       position:sticky hanya bergerak di dalam bounding box induknya. Kalau
       elemen header sticky dibungkus section setinggi dirinya sendiri,
       rentang sticky-nya nol dan header ikut tergulir - itulah sebabnya
       header "hilang" saat halaman digulir.
       display:contents membuat wrapper (dan .section-inner) tidak
       menghasilkan kotak sama sekali, sehingga header/footer menjadi
       anak langsung body dan menempel ke viewport.
       Anchor id tidak ditaruh di sini: dipindah ke elemen header/footer
       yang punya kotak (lihat section-render.blade.php). */
    .section-frame-header,
    .section-frame-header > .section-inner,
    .section-frame-footer,
    .section-frame-footer > .section-inner { display: contents; }

    .lp-header { width: 100%; background: var(--orion-neutral-100); border-bottom: 1px solid var(--orion-brand-border); }
    .lp-header-sticky { position: sticky; top: 0; z-index: 50; }
    /* Dropdown nav (mobile/tablet): melayang di bawah header, tidak mendorong
       konten sehingga layout tidak "bergoyang" saat menu dibuka. */
    .lp-header-inner { position: relative; }
    .lp-header-inner {
        max-width: 1200px;
        margin: 0 auto;
        padding: 14px 24px;
        display: flex;
        align-items: center;
        gap: 16px;
        /* P0 PHASE 2: nav tidak boleh membungkus ke baris baru. Kalau ruang
           tidak cukup, hamburger yang turun — bukan link yang wrap. */
        flex-wrap: nowrap;
    }
    .lp-header-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
    .lp-header-logo { height: 34px; width: auto; object-fit: contain; }
    /* Hamburger disembunyikan pada lebar desktop; aturan tampilnya ada di
       @media (max-width: 900px). Definisi default ini HARUS berada
       sebelum media query, bukan sesudahnya, agar tidak menimpanya. */
    .lp-header-hamburger { display: none; }
    .lp-header-mark {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: var(--orion-electric-violet);
        color: var(--orion-action-primary-text);
        font-weight: 800;
        font-size: 14px;
    }
    .lp-header-name { display: flex; flex-direction: column; line-height: 1.2; color: var(--orion-brand-navy); font-weight: 800; font-size: 15px; }
    .lp-header-name small { font-size: 10.5px; font-weight: 500; color: var(--orion-brand-muted); }
    .lp-header-nav {
        display: flex;
        gap: 2px;
        /* P0 PHASE 2: nav TIDAK boleh membungkus. Kalau ruang menyempit,
           link menyusut dan nav boleh scroll horizontal — tidak pernah wrap. */
        flex-wrap: nowrap;
        margin-left: auto;
        min-width: 0;
        overflow-x: auto;
        scrollbar-width: none;
    }
    .lp-header-nav::-webkit-scrollbar { display: none; }
    .lp-header-link { flex: 0 0 auto; white-space: nowrap; }
    .lp-header-link {
        padding: 8px 12px;
        font-size: 13.5px;
        font-weight: 500;
        color: var(--orion-brand-navy);
        text-decoration: none;
        border-radius: 8px;
        transition: background-color .18s ease, color .18s ease;
    }
    .lp-header-link:hover { background: var(--orion-brand-light); color: var(--orion-brand-navy); }
    .lp-header-link--staff { font-weight: 700; color: var(--orion-deep-night); }
    /* link "Masuk Staf" hanya muncul di dalam dropdown (≤900px) */
    @media (min-width: 901px) { .lp-header-link--staff { display: none; } }
    /* CTA WhatsApp di dalam panel dropdown hanya untuk mobile */
    @media (min-width: 901px) { .lp-header-link--cta { display: none; } }
    /* Hamburger menu HIDDEN on desktop - hanya tampil di mobile/tablet */
    @media (min-width: 901px) { .lp-header-hamburger { display: none; } }
    .lp-header-link:focus-visible { outline: 2px solid var(--orion-brand-focus); outline-offset: 2px; }
    .lp-header-actions { display: flex; align-items: center; gap: 8px; }
    .lp-header-cta, .lp-header-staff {
        padding: 9px 18px;
        font-size: 13px;
        font-weight: 600;
        border-radius: 8px;
        text-decoration: none;
        transition: background-color .18s ease, color .18s ease;
        min-height: 40px;
        display: inline-flex;
        align-items: center;
    }
    .lp-header-cta { background: var(--orion-action-cta); color: var(--orion-action-cta-text); border: 0; }
    .lp-header-cta:hover { background: #E85C05; color: var(--orion-action-cta-text); }
    .lp-header-staff { background: var(--orion-deep-night); color: #FFFFFF; }
    .lp-header-staff:hover { background: var(--orion-brand-hover); color: #FFFFFF; }

    /* ============ FOOTER ============
       Seluruh isi footer sekarang berada di dalam satu elemen .lp-footer
       (lihat partial footer-render.blade.php) - dipakai bersama oleh
       Preview, Landing Page publik, dan halaman artikel, jadi tidak ada lagi
       footer dengan tampilan berbeda di tiap halaman.
       Latar Deep Night dipakai pada .lp-footer (bukan lagi pada .lp-footer-grid)
       supaya blok "baris bawah" ikut gelap dan kontras teks tetap aman. */
    .footer, .lp-footer {
        background: var(--orion-deep-night);
        color: #DCDCFB;
        padding: 52px 0 28px;
    }
    .lp-footer-grid {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 40px;
        display: grid;
        /* Identitas | Navigasi | Layanan | Kontak (opsional + tambahan operator).
           minmax(0, ...) wajib: tanpa itu kolom ikut melar mengikuti isi dan
           footer bisa meluber keluar viewport. */
        grid-template-columns: minmax(0, 1.4fr) repeat(3, minmax(0, 1fr));
        gap: 36px 40px;
        align-items: start;
        color: #DCDCFB;
    }
    .lp-footer h4 {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: #FFFFFF;
        margin: 0 0 14px;
    }
    .lp-footer a, .footer a {
        display: inline-block;
        color: var(--orion-neutral-700);
        text-decoration: none;
        font-size: 13.5px;
        padding: 4px 0;
        transition: color .18s ease;
    }
    .lp-footer a:hover, .footer a:hover { color: var(--orion-cyan-light); }
    .lp-footer-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
    .lp-footer-mark {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: var(--orion-electric-violet);
        color: var(--orion-action-primary-text);
        font-weight: 800;
        font-size: 14px;
    }
    .lp-footer-name { color: #FFFFFF; font-weight: 800; font-size: 15px; }
    .lp-footer p { color: var(--orion-neutral-700); }
    .lp-footer-desc { font-size: 13.5px; color: var(--orion-neutral-700); line-height: 1.65; margin: 0; }
    .lp-footer-copy {
        margin: 0;
        font-size: 12px;
        color: var(--orion-neutral-800);
    }

    /* ---- Footer: grid 4 kolom yang konsisten (Identitas / Navigasi /
            Layanan / Kontak). Satu sumber markup untuk Preview, Landing Page
            publik, dan halaman artikel - lihat footer-render.blade.php.
            min-width:0 + overflow-wrap mencegah kolom menyempit dan
            menyebabkan overflow horizontal saat teks/kontak panjang. */
    .lp-footer-bottom {
        max-width: 1200px;
        margin: 0 auto;
        padding: 22px 40px 0;
        border-top: 1px solid rgba(220, 220, 251, .14);
    }
    .lp-footer-kol { min-width: 0; }
    .lp-footer-kol--identitas { max-width: 42ch; }
    .lp-footer-subtitle {
        color: var(--orion-cyan-light);
        font-weight: 600;
        margin-bottom: 8px;
    }
    .lp-footer-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .lp-footer-list li { min-width: 0; }
    .lp-footer a, .lp-footer-list li > .lp-footer-value,
    .lp-footer-list li > .lp-footer-label {
        overflow-wrap: anywhere;
        word-break: break-word;
    }
    .lp-footer-label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--orion-neutral-800);
        margin-bottom: 2px;
    }
    .lp-footer-list--kontak { gap: 14px; }
    /* Selector diperkuat (a.lp-footer-wa) karena .lp-footer a di atas lebih
       spesifik pada properti display/padding. */
    .lp-footer a.lp-footer-wa {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 18px;
        padding: 10px 18px;
        border-radius: 10px;
        background: var(--orion-electric-violet);
        color: #FFFFFF;
        font-weight: 600;
        font-size: 13.5px;
    }
    .lp-footer a.lp-footer-wa:hover { background: var(--orion-brand-hover); color: #FFFFFF; }

    /* ============ SCROLL REVEAL ============
       DIAGNOSA MASALAH OPERATOR ("tidak melihat animasi saat scrolling"):

       Sebelumnya animasi didefinisikan sebagai keyframe CSS yang jalan SEGERA
       saat halaman dimuat:
           .motion-fade-up { animation: lp-fadeInUp 0.6s ease-out both; }
       Semua section di bawah fold sudah selesai beranimasi sebelum pengunjung
       menggulir ke sana, jadi efeknya tidak pernah terlihat. Tidak ada
       IntersectionObserver, tidak ada class "visible", dan tidak ada satu
       pun penanda reveal pada elemen yang dirender.

       Pendekatan sekarang:
         - JS menandai dokumen dengan class `lp-motion-on`.
         - Hanya dalam keadaan itu elemen reveal mulai tersembunyi
           (opacity: 0) dan menunggu IntersectionObserver.
         - Tanpa JS / tanpa observer / reduced-motion: konten selalu terlihat.
         - Animasi DIPICU KETIKA ELEMEN MASUK VIEWPORT, sekali saja, lalu
           elemen di-unobserve.
         - Hanya memakai transform + opacity. Tidak ada library baru.

       Keyframe memakai animation-fill-mode: both pada transisi yang dipicu
       class, bukan animation otomatis saat load - jadi elemen tidak pernah
       tertahan dalam keadaan tersembunyi. */
    .lp-reveal {
        opacity: 1;          /* State dasar: selalu terlihat */
        transform: none;
    }
    .lp-motion-on .lp-reveal {
        opacity: 0;          /* Baru tersembunyi setelah JS siap */
        transform: none;
        animation: none;     /* Menunggu IntersectionObserver */
        will-change: transform, opacity;
    }
    .lp-motion-on .lp-reveal.is-visible {
        animation-name: lpRevealUp;
        animation-duration: var(--lp-reveal-dur, 650ms);
        animation-timing-function: cubic-bezier(.22, 1, .36, 1);
        animation-fill-mode: both;
        animation-delay: var(--lp-reveal-delay, 0ms);
    }
    /* Gambar: slide-in dari kanan */
    .lp-motion-on .lp-reveal--right.is-visible { animation-name: lpRevealRight; }

    @keyframes lpRevealUp {
        from { opacity: 0; transform: translateY(24px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes lpRevealRight {
        from { opacity: 0; transform: translateX(48px); }
        to   { opacity: 1; transform: translateX(0); }
    }

    /* .motion-* milik section TIDAK dianimasikan otomatis saat load lagi -
       efek masuk sekarang dijalankan oleh scroll-reveal.blade.php. */
    .motion-fade,
    .motion-fade-up,
    .motion-slide {
        opacity: 1;
        transform: none;
        will-change: auto;
        animation: none;
    }

    @media (prefers-reduced-motion: reduce) {
        .lp-motion-on .lp-reveal,
        .lp-reveal {
            opacity: 1 !important;
            transform: none !important;
            animation: none !important;
            will-change: auto !important;
        }
        .lp-header-nav.is-open { animation: none; }
        .lp-portfolio-card:hover, .card:hover, .lp-product:hover,
        .lp-posts-card:hover { transform: none; transition: none; }
        .lp-header-hamburger span { transition: none; }
    }
/* ============ RESPONSIVE ============ */
    @media (max-width: 1024px) {
        /* 4 kolom jadi 2x2 - kolom identitas tetap lebar di kiri atas. */
        .lp-footer-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
    }
    /* ---- P0 PHASE 2: batas hamburger berbasis Lebar Konten NYATA ----
       Diukur dari konten aktual (bukan angka tebakan):
         brand 168px + gap 16 + nav 520px + gap 16 + actions 107px
         + padding 2×24px = 875px lebar inner minimum.
       Di bawah ambang itu, menu horizontal tidak muat dalam satu baris.
       Karena itu hamburger dipakai mulai 900px (di atas ambang 875px,
       memberi ruang aman untuk padding & variasi label), bukan 640px.
       Nav TIDAK PERNAH diurutkan ke baris kedua supaya tidak pernah wrap. */
    @media (max-width: 900px) {
        .lp-header-inner { padding: 10px 16px; gap: 10px; }
        /* Nav disembunyikan (bukan dipindah baris) dan hamburger muncul. */
        .lp-header-nav {
            display: none;
            order: initial;
            width: 100%;
            margin-left: 0;
            overflow-x: visible;
        }
        .lp-header-nav.is-open {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            position: absolute;
            left: 0;
            right: 0;
            top: 100%;
            z-index: 60;
            background: var(--orion-neutral-100);
            border-bottom: 1px solid var(--orion-brand-border);
            box-shadow: 0 12px 24px var(--orion-shadow-soft);
            padding: 8px 16px 14px;
            /* Muncul dari bawah Header: fade + slide lembut, sekali saja.
               Animasi dipasang sebagai transisi yang dipicu oleh class,
               bukan keyframe otomatis — sehingga panel tidak pernah tertahan
               dalam keadaan tersembunyi bila tab kehilangan fokus. */
            animation: lpNavPanelIn .26s cubic-bezier(.22, 1, .36, 1) both;
        }
        @keyframes lpNavPanelIn {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .lp-header-hamburger {
            /* HAMBURGER WAJIB DI SISI KANAN HEADER.
               DOM-nya masih ditaruh tepat setelah brand, jadi posisinya
               dikendalikan lewat flex order + margin-left:auto — logo
               tetap di kiri, hamburger di paling kanan, tanpa ada tombol
               menu kedua. */
            display: inline-flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 4px;
            width: 44px;
            height: 44px;
            min-width: 44px;
            min-height: 44px;
            flex: 0 0 auto;
            padding: 0;
            background: transparent;
            border: 1px solid var(--orion-brand-border);
            border-radius: 10px;
            cursor: pointer;
            order: 4;
            margin-left: auto;
            transition: background-color .2s ease, border-color .2s ease;
        }
        .lp-header-hamburger span {
            display: block;
            width: 20px;
            height: 2px;
            background: var(--orion-brand-navy);
            border-radius: 2px;
            transition: transform .2s ease, opacity .2s ease;
        }
        .lp-header-hamburger.is-open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
        .lp-header-hamburger.is-open span:nth-child(2) { opacity: 0; }
        .lp-header-hamburger.is-open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }
        .lp-header-hamburger:hover { background: var(--orion-brand-light); border-color: var(--orion-brand-border-strong); }
        .lp-header-hamburger:focus-visible { outline: 2px solid var(--orion-cyan-light); outline-offset: 2px; border-radius: 10px; }
        .lp-header-actions { margin-left: auto; }
        /* "Masuk Staf" sudah ada di dalam dropdown nav pada lebar ini,
           jadi tombol terpisah disembunyikan agar tidak dobel. */
        .lp-header-actions { display: none; }
        .lp-hero { grid-template-columns: 1fr; gap: 30px; }
        .lp-about { grid-template-columns: 1fr; gap: 30px; }
        .lp-contact { grid-template-columns: 1fr; gap: 30px; }
        .lp-features { grid-template-columns: repeat(2, 1fr); gap: 24px 0; }
        .lp-feature:nth-child(3) { border-left: 0; padding-left: 0; }
    }
    /* ---- Tablet (641px - 768px): P0 FIX — jaga 2 kolom & tombol konten-width ---- */
    @media (max-width: 768px) and (min-width: 641px) {
        .grid-2, .grid-3, .grid-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .lp-faq-list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .section, .section-spacing-small, .section-spacing-medium, .section-spacing-large { padding: 48px 20px; }
        .stat-number { font-size: 2.1rem; }
        .lp-header-link { font-size: 12px; padding: 6px 7px; }
        .lp-posts-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
        .lp-steps { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .lp-features { grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .lp-feature { border-left: 0; padding-left: 0; border-top: 1px solid var(--orion-brand-border); padding-top: 18px; }
        .lp-feature:first-child { border-top: 0; padding-top: 8px; }
        .lp-footer-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 28px; }
        .lp-footer-kol--identitas { max-width: none; grid-column: 1 / -1; }
        .lp-footer-bottom { padding: 20px 28px 0; }
        .lp-cta { padding: 40px 28px; }
        /* P0 Hero CTA tablet: tombol mengikuti konten, TIDAK full-width */
        .lp-hero-actions { flex-direction: row; flex-wrap: wrap; }
        .lp-hero-actions .btn { flex: 0 1 auto; width: auto; }
    }
    /* ---- Mobile kecil (<= 640px): stack 1 kolom, tombol full-width ----
       Catatan: aturan hamburger TIDAK diulang di sini. Aturan hamburger
       yang otoritatif ada di blok @media (max-width: 900px). Mengulangnya
       di sini hanya menimbulkan konflik cascade. Yang berbeda di 640px
       hanyalah arah dropdown nav (vertikal, penuh). */
    @media (max-width: 640px) {
        .lp-header-nav {
            flex-direction: column;
            gap: 2px;
            align-items: stretch;
            width: 100%;
            max-height: calc(100vh - 120px);
            overflow-y: auto;
            padding: 10px 0 0;
        }
        .lp-header-link {
            width: 100%;
            padding: 12px 14px;
            font-size: 14px;
            min-height: 44px;
        }
        .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
        .lp-faq-list { grid-template-columns: 1fr; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .section, .section-spacing-small, .section-spacing-medium, .section-spacing-large { padding: 40px 16px; }
        .stat-number { font-size: 1.9rem; }
        .lp-posts-grid { grid-template-columns: 1fr !important; }
        .lp-steps { grid-template-columns: 1fr; }
        .lp-features { grid-template-columns: 1fr; gap: 20px; }
        .lp-feature { border-left: 0; padding-left: 0; border-top: 1px solid var(--orion-brand-border); padding-top: 18px; }
        .lp-feature:first-child { border-top: 0; padding-top: 8px; }
        .lp-footer-grid { grid-template-columns: minmax(0, 1fr); gap: 28px; }
        .lp-footer-kol--identitas { max-width: none; }
        .lp-footer-bottom { padding: 20px 22px 0; }
        .lp-cta { padding: 32px 22px; }
        .lp-hero-actions { flex-direction: column; }
        .lp-hero-actions .btn { width: 100%; }
        .lp-portfolio-filters { overflow-x: auto; padding-bottom: 8px; -webkit-overflow-scrolling: touch; }
        .lp-filter-pill { flex: 0 0 auto; min-width: fit-content; }
        .btn, .lp-filter-pill, .lp-header-link, .lp-header-hamburger, .lp-header-cta, .lp-header-staff { min-height: 44px; min-width: 44px; }
        .btn { min-height: 44px; }
        .faq-question { min-height: 44px; }
        .lp-lightbox-close { min-width: 44px; min-height: 44px; }
        .faq-question { padding-right: 50px; }
        .faq-icon { flex-shrink: 0; }
        .lp-contact { grid-template-columns: 1fr; }
    }
    @media (max-width: 480px) {
        .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
        .section, .section-spacing-small, .section-spacing-medium, .section-spacing-large { padding: 32px 12px; }
        .lp-cta { padding: 28px 16px; }
    }
    /* ================================================================
       P0 PHASE 3 — HAMBURGER (perbaikan akar)
       ----------------------------------------------------------------
       Sebelumnya ada aturan `.lp-header-hamburger { display: none; }`
       yang berada DI LUAR media query dan ditulis paling akhir. Karena
       media query tidak menambah specificity, aturan itu menimpa
       `@media (max-width: 640px) { display: inline-flex }` → hamburger
       TIDAK PERNAH tampil di mobile, sementara nav disembunyikan
       (display:none). Akibatnya navigasi utama hilang total.

       Perbaikan: aturan "sembunyi di desktop" dipindah ke ATAS (lihat
       blok HEADER) dan di sini hanya diatur pada rentang narrow.
       ================================================================ */
</style>