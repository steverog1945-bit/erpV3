{{--
    Shared section renderer untuk Landing Page Builder.
    Dipakai oleh Preview (admin) dan Public homepage agar output identik.

    Aturan keamanan:
    - Semua output teks di-escape dengan {{ }} (Blade auto-escape).
    - URL divalidasi: hanya http/https. Scheme berbahaya ditolak.
    - Gambar hanya dari media yang tercatat (path storage internal).
    - Tidak ada {!! !!}, tidak ada raw HTML dari konten user.
    - Artikel Kabar & Panduan SELALU dari Post::publik() - tidak pernah
      dari Post::all(), sehingga tulisan internal/draft/terjadwal tidak bocor.

    Variable yang diharapkan:
    - $sections : array of sections
--}}

@php
    // Helper lokal: URL aman. Menerima http/https absolut, path internal
    // (dimulai dengan /) dan anchor internal (dimulai dengan #).
    // Aturan ini sama persis dengan validator di LandingPageBuilderController
    // supaya server dan renderer tidak berbeda dalam menilai sebuah URL.
    $lpSafeUrl = function ($url) {
        $url = trim((string) $url);
        if ($url === '') return null;

        // Anchor internal (#tentang, #faq).
        if ($url[0] === '#') {
            return preg_match('/^#[A-Za-z0-9_-]+$/', $url) ? $url : null;
        }

        // Tolak scheme berbahaya lebih dulu.
        if (preg_match('/^(javascript|data|vbscript|file|about):/i', $url)) {
            return null;
        }

        // Path internal: tolak yang mencoba keluar dari root.
        if (str_starts_with($url, '/')) {
            if (str_starts_with($url, '//')) {
                return null; // protocol-relative: smscan disamarkan sebagai host lain
            }
            if (str_contains($url, '..') || str_contains($url, '\\')) {
                return null; // path traversal
            }
            // Ubah path internal menjadi URL APLIKASI.
            //
            // Tanpa ini, "/masuk" yang dikonfigurasi operator akan
            // resolve ke http://localhost/masuk - ROOT SERVER XAMPP, bukan
            // aplikasi yang berada di /grafikaorion/public. url()
            // menambahkan prefix subdirektori dengan benar dan aman juga di
            // instalasi root domain.
            return url($url);
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
            return $url;
        }
        return null;
    };

    // Helper lokal: gambar hanya boleh dari storage landing-media.
    $lpSafeImage = function ($image) {
        $image = trim((string) $image);
        if ($image === '') return null;
        // Path internal media manager.
        if (str_starts_with($image, 'landing-media/')) {
            return asset('storage/' . $image);
        }
        // URL eksternal http/https saja.
        if (str_starts_with($image, 'https://') || str_starts_with($image, 'http://')) {
            return $lpSafeUrl($image);
        }
        return null;
    };

    // Angka px yang sudah divalidasi rentangnya.
    $lpPx = function ($value, int $min, int $max, int $fallback) {
        if (!is_numeric($value)) {
            return $fallback;
        }
        $n = (int) $value;
        return max($min, min($max, $n));
    };

    // Lebar kontainer per layoutWidth.
    $lpWidthMap = [
        'full'      => '100%',
        'container' => '1180px',
        'narrow'    => '860px',
    ];

    // Background class allowlist.
    $lpBgAllow = ['white', 'gray', 'night', 'orion', 'indigo'];

    // Item navigasi milik operator (section 'header'), dibaca SEKALI di sini
    // supaya footer bisa memakainya tanpa menduplikasi logika parsing.
    $lpFooterNavItems = [];
    foreach ((array) $sections as $lpNavSection) {
        if (($lpNavSection['type'] ?? '') !== 'header') continue;
        foreach ((array) ($lpNavSection['items'] ?? []) as $lpNavItem) {
            if (! is_array($lpNavItem)) continue;
            $lpNavLabel = trim((string) ($lpNavItem['label'] ?? ''));
            $lpNavHref  = $lpSafeUrl($lpNavItem['value'] ?? '');
            if ($lpNavLabel !== '' && $lpNavHref !== null) {
                $lpFooterNavItems[] = ['label' => $lpNavLabel, 'href' => $lpNavHref];
            }
        }
        break;
    }
@endphp

{{--
    JS filter kategori & lightbox portfolio.
    Dipasang sekali di halaman (bukan per section). Tanpa library eksternal,
    tanpa CDN. Semua interaksi memakai atribut data-* yang sudah ada di
    markup renderer di atas.
--}}

@foreach($sections as $lpSection)
    @continue(($lpSection['visible'] ?? true) === false)

    @php
        // Catatan: JANGAN skip header/footer di sini.
        //
        // Section header dan footer memang diekstrak dan dirender di level
        // halaman pada landing-page-public.blade.php - TAPI preview iframe
        // (landing-page-preview-iframe.blade.php) memanggil partial ini
        // dengan daftar section UTUH. Kalau partial ini ikut me-skip
        // header/footer, Preview Draft kehilangan header, navigasi, dan
        // footer sama sekali.
        //
        // Tidak ada risiko dobel render: landing-page-public.blade.php sudah
        // memfilter header/footer sebelum memanggil partial ini, jadi section
        // itu tidak pernah sampai ke sini dari jalur tersebut.
        $lpType = $lpSection['type'] ?? '';

        $lpBgAllow     = ['white', 'gray', 'night', 'orion', 'indigo'];
        $lpBgClass     = 'section-bg-' . (in_array($lpSection['styleBg'] ?? 'white', $lpBgAllow, true) ? ($lpSection['styleBg'] ?? 'white') : 'white');
        $lpRadiusClass = 'section-radius-' . (in_array($lpSection['styleRadius'] ?? 'medium', ['none', 'small', 'medium', 'large'], true) ? ($lpSection['styleRadius'] ?? 'medium') : 'medium');
        $lpSpacing     = in_array($lpSection['layoutSpacing'] ?? 'medium', ['small', 'medium', 'large'], true) ? ($lpSection['layoutSpacing'] ?? 'medium') : 'medium';
        $lpAlignClass  = 'section-align-' . (in_array($lpSection['layoutAlign'] ?? 'left', ['left', 'center', 'right'], true) ? ($lpSection['layoutAlign'] ?? 'left') : 'left');
        $lpMotion      = in_array($lpSection['motion'] ?? 'none', ['none', 'fade', 'fade-up', 'slide'], true) ? ($lpSection['motion'] ?? 'none') : 'none';
        $lpColumns     = in_array((int) ($lpSection['layoutColumns'] ?? 2), [1, 2, 3, 4], true) ? (int) ($lpSection['layoutColumns'] ?? 2) : 2;
        $lpWidth       = $lpWidthMap[$lpSection['layoutWidth'] ?? 'container'] ?? '1180px';
        $lpType        = $lpSection['type'] ?? '';
        $lpMotionClass = $lpMotion !== 'none' ? 'motion-' . $lpMotion : '';

        // Spasi & ukuran font: hanya bilangan bulat dalam rentang aman, disuntik
        // sebagai nilai px lewat style inline. Tidak ada teks bebas user yang
        // sampai ke sini, jadi tidak ada jalur injeksi lewat style.
        $lpPadTop    = $lpPx($lpSection['paddingTop'] ?? null, 0, 200, 0);
        $lpPadBottom = $lpPx($lpSection['paddingBottom'] ?? null, 0, 200, 0);
        $lpMarTop    = $lpPx($lpSection['marginTop'] ?? null, -200, 200, 0);
        $lpMarBottom = $lpPx($lpSection['marginBottom'] ?? null, -200, 200, 0);
        $lpFontSize  = $lpPx($lpSection['fontSize'] ?? null, 12, 96, 16);

        $lpSectionStyle = sprintf(
            'padding-top:%dpx;padding-bottom:%dpx;margin-top:%dpx;margin-bottom:%dpx;font-size:%dpx;',
            $lpPadTop,
            $lpPadBottom,
            $lpMarTop,
            $lpMarBottom,
            $lpFontSize
        );

        $lpBtnUrl  = $lpSafeUrl($lpSection['buttonUrl'] ?? '');
        $lpBtnText = trim((string) ($lpSection['buttonLabel'] ?? ''));
        // Helper: apakah URL eksternal (http/https) agar dibuka di tab baru
        $lpIsExternal = function ($url) {
            return $url && preg_match('/^https?:\/\//i', $url);
        };

        // Label kategori coaster: dipakai di hero (badge) dan sebagai eyebrow.
        $lpBadge = trim((string) ($lpSection['subtitle'] ?? ''));

        // Semantic anchor ID mapping untuk navigasi header & smooth scroll.
        // Hanya section yang sichtbar & punya tipe terdaftar yang mendapat ID semantik.
        // Header mendapat 'beranda' (navigasi), Hero TIDAK mendapat ID duplikat.
        $lpAnchorMap = [
            'header'       => 'beranda',
            'hero'         => '',
            'services'     => 'layanan',
            'about'        => 'tentang',
            'product-grid' => 'produk',
            'portfolio'    => 'portofolio',
            'posts'        => 'artikel',
            'testimonials' => 'testimoni',
            'faq'          => 'faq',
            'cta'          => 'cta',
            'contact'      => 'kontak',
            'footer'       => 'footer',
        ];
        $lpAnchorId = $lpAnchorMap[$lpType] ?? '';

        // Header & footer dibungkus section yang TIDAK boleh menyempit.
        //
        // Alasannya: position:sticky hanya bergerak di dalam bounding box
        // induknya. Kalau <header class="lp-header-sticky"> dibungkus
        // <section> yang tingginya sama dengan dirinya sendiri, rentang
        // sticky-nya nol - header terlihat "hilang" begitu digulir.
        // Karena itu wrapper section (dan .section-inner di dalamnya)
        // dibuat display:contents (lihat .section-frame-* di
        // pengaturan/partials/renderer-styles.blade.php), sehingga header
        // menjadi anak langsung <body> dan benar-benar menempel ke viewport.
        //
        // Karena wrapper tidak lagi jadi kotak, anchor id ikut dipindah ke
        // elemen <header>/<footer> yang benar-benar punya kotak - kalau
        // tetap di <section>, navigasi "#beranda" tidak punya tujuan.
        $lpIsFrame    = in_array($lpType, ['header', 'footer'], true);
        $lpFrameClass = $lpIsFrame ? ' section-frame-' . $lpType : '';
    @endphp

    <section class="section {{ $lpBgClass }} {{ $lpRadiusClass }} section-spacing-{{ $lpSpacing }} {{ $lpAlignClass }} {{ $lpMotionClass }}{{ $lpFrameClass }}"
             style="{{ $lpSectionStyle }}"
             data-section-type="{{ $lpType }}"
             data-section-id="{{ $lpSection['id'] ?? '' }}"
             @if($lpAnchorId !== '' && ! $lpIsFrame) id="{{ $lpAnchorId }}" @endif>
        <div class="section-inner" style="max-width: {{ $lpWidth }};">

            @switch($lpType)
                @case('header')
                    @php
                        $lpNavItems = [];
                        if (! empty($lpSection['items']) && is_array($lpSection['items'])) {
                            foreach ($lpSection['items'] as $lpItem) {
                                if (! is_array($lpItem)) continue;
                                $lpLabel = trim((string) ($lpItem['label'] ?? ''));
                                $lpHref  = $lpSafeUrl($lpItem['value'] ?? '');
                                if ($lpLabel === '' || $lpHref === null) continue;
                                $lpNavItems[] = ['label' => $lpLabel, 'href' => $lpHref];
                            }
                        }
                        $lpSticky = (($lpSection['sticky'] ?? false) === true) ? ' lp-header-sticky' : '';
                        $lpLogo   = $lpSafeImage($lpSection['image'] ?? '');
                        $lpBrand  = $lpSection['title'] ?? 'ORION GRAFIKA';
                        $lpBrandSub = trim((string) ($lpSection['subtitle'] ?? ''));
                        $lpCta    = trim((string) ($lpSection['headerCta'] ?? ''));
                        $lpBtnText = trim((string) ($lpSection['buttonLabel'] ?? ''));
                        $lpBtnUrl  = $lpSafeUrl($lpSection['buttonUrl'] ?? '');
                    @endphp
                    <header @if($lpAnchorId !== '') id="{{ $lpAnchorId }}" @endif class="lp-header{{ $lpSticky }}" data-lp-frame-anchor="header">
                        <div class="lp-header-inner">
                            {{-- Merek menuju root APLIKASI, bukan root SERVER.
                                 href="/" akan resolving ke http://localhost/ (root
                                 server XAMPP) karena preview dirender di dalam
                                 <iframe> pada URL yang lebih dalam seperti
                                 /grafikaorion/public/setting/... - dan memuat
                                 "Welcome to XAMPP" di dalam preview.
                                 url('/') selalu menghasilkan root aplikasi yang
                                 benar, baik di root domain maupun subdirektori. --}}
                            <a class="lp-header-brand" href="{{ url('/') }}">
                                @if($lpLogo)
                                    <img src="{{ $lpLogo }}" alt="{{ $lpBrand }}" loading="lazy" onerror="this.style.display='none'" class="lp-header-logo">
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
                                    {{-- CTA utama (WhatsApp) juga masuk panel supaya
                                         tetap terjangkau di mobile. Sebelumnya CTA
                                         berada di .lp-header-actions yang disembunyikan
                                         pada lebar ini. Di desktop aturan
                                         .lp-header-link--cta tidak tampil (duplikat
                                         dengan tombol CTA di baris Header). --}}
                                    @if($lpCta !== '' && $lpBtnUrl && $lpIsExternal($lpBtnUrl))
                                        <a href="{{ $lpBtnUrl }}" class="lp-header-link lp-header-link--cta" target="_blank" rel="noopener noreferrer">{{ $lpCta }}</a>
                                    @endif
                                </nav>
                            @endif
                            <div class="lp-header-actions">
                                @if($lpCta !== '' && $lpBtnUrl)
                                    <a href="{{ $lpBtnUrl }}" class="lp-header-cta" {!! $lpIsExternal($lpBtnUrl) ? 'target="_blank" rel="noopener noreferrer"' : '' !!}>{{ $lpCta }}</a>
                                @endif
                                @if($lpBtnText !== '' && $lpBtnUrl)
                                    <a href="{{ $lpBtnUrl }}" class="lp-header-staff" {!! $lpIsExternal($lpBtnUrl) ? 'target="_blank" rel="noopener noreferrer"' : '' !!}>{{ $lpBtnText }}</a>
                                @endif
                            </div>
                        </div>
                    </header>
                    @php
                        if (! empty($lpNavItems)) {
                            // P0 PHASE 3 — hamburger & dropdown mobile.
                            // Daftaran menu TIDAK di-hardcode di sini: diambil
                            // dari $lpNavItems (konfigurasi Header Builder),
                            // sehingga selalu identik dengan Inspector.
                            // Script idempoten agar aman bila dirender ulang.
                            echo '<script>(function(){'
                                . 'if(window.__lpHeaderMenuBound){return;}'
                                . 'window.__lpHeaderMenuBound=true;'
                                . 'function init(){'
                                . 'var btn=document.querySelector(".lp-header-hamburger");'
                                . 'var nav=document.getElementById("lp-header-nav");'
                                . 'if(!btn||!nav){return;}'
                                . 'function isOpen(){return btn.getAttribute("aria-expanded")==="true";}'
                                . 'function setOpen(open){'
                                . 'btn.setAttribute("aria-expanded",open?"true":"false");'
                                . 'btn.setAttribute("aria-label",open?"Tutup menu":"Buka menu");'
                                . 'nav.classList.toggle("is-open",open);'
                                . '}'
                                . 'btn.addEventListener("click",function(e){e.preventDefault();setOpen(!isOpen());});'
                                // Enter / Space (button native, tapi dijamin eksplisit)
                                . 'btn.addEventListener("keydown",function(e){'
                                . 'if(e.key==="Enter"||e.key===" "||e.key==="Spacebar"){e.preventDefault();setOpen(!isOpen());}'
                                . '});'
                                // Escape menutup
                                . 'document.addEventListener("keydown",function(e){'
                                . 'if(e.key==="Escape"&&isOpen()){setOpen(false);btn.focus();}'
                                . '});'
                                // Klik di luar menutup (dengar di fase bubble agar tetap jalan
                                // walau event dihentikan oleh handler lain)
                                . 'document.addEventListener("click",function(e){'
                                . 'if(!isOpen()){return;}'
                                . 'var t=e.target;'
                                . 'if(t&&(btn.contains(t)||nav.contains(t))){return;}'
                                . 'setOpen(false);'
                                . '},false);'
                                // Fallback: klik di area halaman mana pun
                                . 'document.addEventListener("pointerdown",function(e){'
                                . 'if(!isOpen()){return;}'
                                . 'var t=e.target;'
                                . 'if(t&&(btn.contains(t)||nav.contains(t))){return;}'
                                . 'setOpen(false);'
                                . '},true);'
                                // Klik anchor menutup
                                . 'nav.querySelectorAll("a").forEach(function(a){'
                                . 'a.addEventListener("click",function(){setOpen(false);});'
                                . '});'
                                // Escape di dalam nav juga menutup
                                . 'nav.addEventListener("keydown",function(e){'
                                . 'if(e.key==="Escape"){setOpen(false);btn.focus();}'
                                . '});'
                                . 'setOpen(false);'
                                . '}'
                                . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",init);}else{init();}'
                                . '})();</script>';
                        }
                    @endphp
                    @break

                @case('hero')
                    @php
                        $lpHeroImage = $lpSafeImage($lpSection['image'] ?? '');
                        $lpHeroDesc  = trim((string) ($lpSection['desc'] ?? ''));
                        // Mode night: komposisi gambar02 (hero gelap menyatu dengan header).
                        // OPT-IN: draft lama yang belum pernah memilih
                        // 'night' tetap tampil light, jadi membuka Builder
                        // tidak mengubah tampilan yang sudah disetujui.
                        $lpHeroTheme = ($lpSection['heroTheme'] ?? 'light') === 'night' ? 'night' : 'light';
                        // Kontrol visual (editable via Inspector)
                        $lpHeroPos = (string) ($lpSection['heroObjectPosition'] ?? 'center');
                        if (! in_array($lpHeroPos, ['center', 'top', 'bottom', 'left', 'right'], true)) {
                            $lpHeroPos = 'center';
                        }
                        // PENTING: nilai TANPA satuan. CSS scale() menerima
                        // <number>; kalau diberi "100%" hasilnya 0 dan aset
                        // hanya tampak sebagai kotak kecil.
                        $lpHeroScale = 1.0;
                        if (isset($lpSection['heroImageScale']) && is_numeric($lpSection['heroImageScale'])) {
                            $lpHeroScale = max(60, min(180, (int) $lpSection['heroImageScale'])) / 100;
                        }
                        $lpHeroBleed = ! empty($lpSection['heroBleed']);
                        $lpHeroTitle = trim((string) ($lpSection['title'] ?? ''));
                    @endphp
                    <div class="lp-hero{{ $lpHeroTheme === 'night' ? ' lp-hero--night' : '' }}">
                        <div class="lp-hero-text">
                            @if($lpBadge !== '')
                                <span class="lp-hero-badge">{{ $lpBadge }}</span>
                            @endif
                            <h1 class="hero-title {{ $lpHeroTheme === 'night' ? 'lp-hero-title' : '' }}">{{ $lpHeroTitle }}</h1>
                            @if($lpHeroDesc !== '')
                                <p class="lp-lead {{ $lpHeroTheme === 'night' ? 'lp-hero-lead' : '' }}">{{ $lpHeroDesc }}</p>
                            @endif
                            @php
                                // Tombol kedua Hero. Gaya berasal dari data builder
                                // (button2Style), bukan dari tebakan renderer.
                                $lpBtn2Text  = trim((string) ($lpSection['buttonLabel2'] ?? ''));
                                $lpBtn2Url   = $lpSafeUrl($lpSection['buttonUrl2'] ?? '');
                                $lpBtn2Style = in_array($lpSection['button2Style'] ?? 'outline',
                                    ['primary', 'dark', 'outline', 'link'], true)
                                    ? ($lpSection['button2Style'] ?? 'outline')
                                    : 'outline';
                                $lpBtn2Class = $lpBtn2Style === 'primary' ? 'btn'
                                    : ($lpBtn2Style === 'dark' ? 'btn btn-dark'
                                    : ($lpBtn2Style === 'link' ? 'btn-link' : 'btn btn-outline'));
                            @endphp
                            <div class="lp-hero-actions">
                                @if($lpBtnText !== '' && $lpBtnUrl)
                                    <a href="{{ $lpBtnUrl }}" class="btn" {!! $lpIsExternal($lpBtnUrl) ? 'target="_blank" rel="noopener noreferrer"' : '' !!}>{{ $lpBtnText }}</a>
                                @endif
                                @if($lpBtn2Text !== '' && $lpBtn2Url)
                                    <a href="{{ $lpBtn2Url }}" class="{{ $lpBtn2Class }}" {!! $lpIsExternal($lpBtn2Url) ? 'target="_blank" rel="noopener noreferrer"' : '' !!}>{{ $lpBtn2Text }}</a>
                                @endif
                            </div>
                        </div>
                        {{-- Kolom kanan: lapisan foreground transparan.
                             Tanpa kartu, tanpa border, tanpa kotak pastel.
                             Bleed ke kanan mengikuti komposisi gambar02. --}}
                        <div class="lp-hero-visual{{ $lpHeroBleed ? ' lp-hero-visual--bleed' : '' }}"
                             data-lpb-hero-object="{{ $lpHeroPos }}"
                             style="--lp-hero-scale: {{ $lpHeroScale }};">
                            @if($lpHeroImage)
                                <img src="{{ $lpHeroImage }}" alt="{{ $lpSection['alt_text'] ?? '' }}"
                                     loading="eager" fetchpriority="high" decoding="async">
                            @else
                                {{-- STATUS: ASSET PENDING.
                                     Ini placeholder kerja yang TERLIHAT sebagai
                                     kondisi belum-final — sengaja bukan kotak
                                     pastel polos dan bukan klaim hasil desain. --}}
                                <div class="lp-hero-asset-pending">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                        <path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3z"/>
                                        <path d="M18.5 15.5l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8.8-2.2z"/>
                                    </svg>
                                    <span>Visual 3D Hero</span>
                                    <small>ASSET PENDING — masukkan aset final lewat Media Library &rarr; Inspector &rarr; Hero &rarr; Gambar</small>
                                </div>
                            @endif
                        </div>
                    </div>
                    @break

                @case('services')
                    @php
                        $lpSvcItems = (is_array($lpSection['items'] ?? null)) ? $lpSection['items'] : [];
                    @endphp
                    @if($lpBadge !== '')
                        <span class="lp-eyebrow">{{ $lpBadge }}</span>
                    @endif
                    <h2>{{ $lpSection['title'] ?? '' }}</h2>
                    @if(! empty($lpSection['desc']))
                        <p class="lp-lead">{{ $lpSection['desc'] }}</p>
                    @endif
                    @if(! empty($lpSvcItems))
                        <div class="lp-features">
                            @foreach($lpSvcItems as $i => $lpItem)
                                @continue(! is_array($lpItem))
                                <div class="lp-feature">
                                    <span class="lp-feature-mark" aria-hidden="true">{{ $i + 1 }}</span>
                                    <h3>{{ $lpItem['title'] ?? '' }}</h3>
                                    @if(! empty($lpItem['desc']))
                                        <p>{{ $lpItem['desc'] }}</p>
                                    @endif
                                </div>
                            @endforeach

                        </div>
                    @else
                        {{-- Empty state elegan --}}
                        <div class="lp-testimonials-empty">
                            <p>Belum ada layanan.</p>
                            <small>Layanan ditambahkan melalui builder akan muncul di sini.</small>
                        </div>
                    @endif
                    @break

                @case('about')
                    @php
                        $lpAboutImage = $lpSafeImage($lpSection['image'] ?? '');
                        // Baris "01 - Desain dan Persiapan" pada desc dipakai
                        // sebagai langkah proses, ditampilkan sebagai kartu.
                        $lpAboutDesc  = trim((string) ($lpSection['desc'] ?? ''));
                        $lpAboutParts = preg_split('/\n\s*\n/', $lpAboutDesc, 2) ?: [];
                        $lpAboutIntro = trim($lpAboutParts[0] ?? '');
                        $lpAboutSteps = [];
                        foreach (preg_split('/\n/', $lpAboutParts[1] ?? '') as $lpLine) {
                            $lpLine = trim($lpLine);
                            if ($lpLine === '') continue;
                            if (preg_match('/^(\d{2})\s*[-.]+\s*(.+)$/', $lpLine, $m)) {
                                $lpAboutSteps[] = ['num' => $m[1], 'text' => $m[2]];
                            }
                        }
                    @endphp
                    <div class="lp-about">
                        <div class="lp-about-visual">
                            @if($lpAboutImage)
                                <img src="{{ $lpAboutImage }}" alt="{{ $lpSection['alt_text'] ?? '' }}" loading="lazy" onerror="this.style.display='none'" decoding="async">
                            @else
                                <span>{{ ($lpSection['alt_text'] ?? '') !== '' ? $lpSection['alt_text'] : 'Proses Orion Grafika' }}</span>
                            @endif
                        </div>
                        <div class="lp-about-text">
                            @if($lpBadge !== '')
                                <span class="lp-eyebrow">{{ $lpBadge }}</span>
                            @endif
                            <h2>{{ $lpSection['title'] ?? '' }}</h2>
                            @if(! empty($lpSection['subtitle']))
                                <p class="hero-subtitle">{{ $lpSection['subtitle'] }}</p>
                            @endif
                            @if($lpAboutIntro !== '')
                                <p class="lp-lead">{{ $lpAboutIntro }}</p>
                            @endif
                            @if(! empty($lpAboutSteps))
                                <div class="lp-steps">
                                    @foreach($lpAboutSteps as $lpStep)
                                        <div class="lp-step">
                                            <span class="lp-step-num" aria-hidden="true">{{ $lpStep['num'] }}</span>
                                            <div>
                                                <h4>{{ $lpStep['text'] }}</h4>
                                            </div>
                                        </div>
                                    @endforeach

                                </div>
                            @endif
                        </div>
                    </div>
                    @break

                @case('product-grid')
                    @php
                        $lpProdItems = (is_array($lpSection['items'] ?? null)) ? $lpSection['items'] : [];
                    @endphp
                    @if($lpBadge !== '')
                        <span class="lp-eyebrow">{{ $lpBadge }}</span>
                    @endif
                    <h2>{{ $lpSection['title'] ?? '' }}</h2>
                    @if(! empty($lpSection['desc']))
                        <p class="lp-lead">{{ $lpSection['desc'] }}</p>
                    @endif
                    @if(! empty($lpProdItems))
                        <div class="grid grid-{{ $lpColumns }}">
                            @foreach($lpProdItems as $lpItem)
                                @continue(! is_array($lpItem))
                                @php $lpItemImage = $lpSafeImage($lpItem['image'] ?? ''); @endphp
                                <article class="lp-product">
                                    <div class="lp-product-tile">
                                        @if($lpItemImage)
                                            <img src="{{ $lpItemImage }}" alt="{{ $lpItem['alt_text'] ?? '' }}" loading="lazy" onerror="this.style.display='none'" decoding="async">
                                        @else
                                            <div class="lp-placeholder" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                                    <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                                    <path d="M21 15l-5-5L5 21"></path>
                                                </svg>
                                                <span>Gambar produk</span>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="lp-product-body">
                                        <h3>{{ $lpItem['title'] ?? '' }}</h3>
                                        @if(! empty($lpItem['desc']))
                                            <p>{{ $lpItem['desc'] }}</p>
                                        @endif
                                        @if($lpBtnText !== '' && $lpBtnUrl)
                                            <a href="{{ $lpBtnUrl }}" class="btn-link">Konsultasi <span aria-hidden="true">&rarr;</span></a>
                                        @else
                                            <a href="#kontak" class="btn-link">Konsultasi <span aria-hidden="true">&rarr;</span></a>
                                        @endif
                                    </div>
                                </article>
                            @endforeach


                        </div>
                    @else
                        {{-- Empty state elegan --}}
                        <div class="lp-testimonials-empty">
                            <p>Belum ada produk.</p>
                            <small>Produk ditambahkan melalui builder akan muncul di sini.</small>
                        </div>
                    @endif
                    @break

                @case('portfolio')
                    @php
                        $lpPortItems = (is_array($lpSection['items'] ?? null)) ? $lpSection['items'] : [];
                    @endphp
                    @if($lpBadge !== '')
                        <span class="lp-eyebrow">{{ $lpBadge }}</span>
                    @endif
                    <h2>{{ $lpSection['title'] ?? '' }}</h2>
                    @if(! empty($lpSection['desc']))
                        <p class="lp-lead">{{ $lpSection['desc'] }}</p>
                    @endif
                    @if(! empty($lpPortItems))
                        @php
                            // Kategori unik dari data portfolio. Filter dibangun
                            // dari data yang benar-benar ada, bukan dari daftar
                            // yang di-hardcode.
                            $lpPortCats = [];
                            foreach ($lpPortItems as $lpIt) {
                                if (! is_array($lpIt)) continue;
                                $lpCat = trim((string) ($lpIt['category'] ?? ''));
                                if ($lpCat !== '' && ! in_array($lpCat, $lpPortCats, true)) {
                                    $lpPortCats[] = $lpCat;
                                }
                            }
                            sort($lpPortCats);
                        @endphp
                        <div class="lp-portfolio-filters" data-lpb-portfolio-filter>
                            <button type="button" class="lp-filter-pill is-active" data-lpb-filter="*">Semua</button>
                            @foreach($lpPortCats as $lpCat)
                                <button type="button" class="lp-filter-pill" data-lpb-filter="{{ $lpCat }}">{{ $lpCat }}</button>
                            @endforeach


                        </div>
                        <div class="grid grid-{{ $lpColumns }}" data-lpb-portfolio-grid>
                            @foreach($lpPortItems as $lpItem)
                                @continue(! is_array($lpItem))
                                @php
                                    $lpItemImage = $lpSafeImage($lpItem['image'] ?? '');
                                    $lpItemCat   = trim((string) ($lpItem['category'] ?? ''));
                                    // Escape untuk atribut HTML. htmlspecialchars dengan
                                    // double_encode=false supaya tanda kutip tidak
                                    // berubah menjadi &quot; di nilai atribut.
                                    $lpItemCatEsc = htmlspecialchars($lpItemCat, ENT_QUOTES, 'UTF-8', false);
                                    $lpItemData   = $lpItemCatEsc !== '' ? ' data-lpb-kategori="' . $lpItemCatEsc . '"' : '';
                                @endphp
                                <article class="lp-portfolio-card"{{ $lpItemData }}>
                                    <div class="lp-portfolio-media">
                                        @if($lpItemImage)
                                            <img src="{{ $lpItemImage }}" alt="{{ $lpItem['alt_text'] ?? '' }}" loading="lazy" onerror="this.style.display='none'" decoding="async" data-lpb-lightbox>
                                        @else
                                            {{-- Bug #7 fix: placeholder berlabel jelas dengan ikon SVG,
                                                 bukan glyph unicode mentah yang tampak rusak. --}}
                                            <div class="lp-portfolio-placeholder" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                                    <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                                    <path d="M21 15l-5-5L5 21"></path>
                                                </svg>
                                                <span>Gambar portfolio</span>
                                            </div>
                                        @endif
                                        @if(! empty($lpItem['title']))
                                            <span class="lp-portfolio-tag">{{ $lpItem['title'] }}</span>
                                        @endif
                                    </div>
                                    <div class="lp-portfolio-body">
                                        @if(! empty($lpItem['desc']))
                                            <p>{{ $lpItem['desc'] }}</p>
                                        @endif
                                    </div>
                                </article>
                            @endforeach


                        </div>
                    @else
                        {{-- Empty state elegan --}}
                        <div class="lp-testimonials-empty">
                            <p>Belum ada karya portfolio.</p>
                            <small>Karya ditambahkan melalui builder akan muncul di sini.</small>
                        </div>
                    @endif
                    @break

                @case('posts')
                    @php
                        // Kabar & Panduan. Sumber data WAJIB Post::publik()
                        // supaya tiga lapis filter aman ikut berlaku:
                        // status terbit + waktu terbit sudah lewat +
                        // tampil_publik = true. Soft-deleted otomatis
                        // tersaring oleh global scope SoftDeletes.
                        // Tidak ada Post::all() di halaman publik.
                        $lpPostLimit  = (int) ($lpSection['postLimit'] ?? 6);
                        if ($lpPostLimit < 1) $lpPostLimit = 6;
                        if ($lpPostLimit > 12) $lpPostLimit = 12;
                        $lpShowDate   = ($lpSection['showDate'] ?? true) !== false;
                        $lpShowImage  = ($lpSection['showImage'] ?? true) !== false;
                        $lpCatSlug    = trim((string) ($lpSection['postCategory'] ?? ''));

                        $lpPosts = \App\Models\Post::with('kategori')
                            ->publik()
                            ->when($lpCatSlug !== '', fn ($q) => $q->whereHas(
                                'kategori',
                                fn ($k) => $k->where('slug', $lpCatSlug)
                            ))
                            ->orderByDesc('disematkan')
                            ->orderByDesc('terbit_at')
                            ->orderByDesc('id')
                            ->limit($lpPostLimit)
                            ->get();
                    @endphp
                    @if($lpBadge !== '')
                        <span class="lp-eyebrow">{{ $lpBadge }}</span>
                    @endif
                    <h2>{{ $lpSection['title'] ?? 'KABAR & PANDUAN' }}</h2>
                    @if(! empty($lpSection['desc']))
                        <p class="lp-lead lp-posts-desc">{{ $lpSection['desc'] }}</p>
                    @endif

                    @if($lpPosts->isEmpty())
                        <div class="lp-posts-empty">
                            <p>Belum ada kabar atau panduan yang dipublikasikan.</p>
                            <small>Tulisan yang sudah terbit dan ditandai publik akan muncul di sini secara otomatis.</small>
                        </div>
                    @else
                        <div class="grid grid-{{ $lpColumns }} lp-posts-grid">
                            @foreach($lpPosts as $lpPost)
                                <article class="lp-posts-card">
                                    @if($lpShowImage && $lpPost->url_sampul)
                                        <img src="{{ $lpPost->url_sampul }}" alt="{{ $lpPost->judul }}" loading="lazy" onerror="this.style.display='none'" decoding="async">
                                    @endif
                                    <div class="lp-posts-body">
                                        @if($lpPost->kategori)
                                            <span class="lp-posts-kategori">{{ $lpPost->kategori->nama }}</span>
                                        @endif
                                        <h3 class="lp-posts-title">{{ $lpPost->judul }}</h3>
                                        @if(! empty($lpPost->ringkasan))
                                            <p class="lp-posts-ringkasan">{{ $lpPost->ringkasan }}</p>
                                        @endif
                                        @if($lpShowDate && $lpPost->terbit_at)
                                            <time class="lp-posts-tanggal" datetime="{{ $lpPost->terbit_at->toDateString() }}">
                                                {{ $lpPost->terbit_at->translatedFormat('d M Y') }}
                                            </time>
                                        @endif
                                        <a href="{{ route('publik.baca', $lpPost->slug) }}" class="btn-link">
                                            {{ $lpBtnText !== '' ? $lpBtnText : 'Baca Selengkapnya' }}
                                            <span aria-hidden="true">&rarr;</span>
                                        </a>
                                    </div>
                                </article>
                            @endforeach


                        </div>
                    @endif
                    @break

                @case('testimonials')
                    @php
                        $lpTestiItems = (is_array($lpSection['items'] ?? null)) ? $lpSection['items'] : [];
                    @endphp
                    @if($lpBadge !== '')
                        <span class="lp-eyebrow">{{ $lpBadge }}</span>
                    @endif
                    <h2>{{ $lpSection['title'] ?? '' }}</h2>
                    @if(! empty($lpSection['desc']))
                        <p class="lp-lead">{{ $lpSection['desc'] }}</p>
                    @endif
                    @if(! empty($lpTestiItems))
                        <div class="grid grid-{{ $lpColumns }}">
                            @foreach($lpTestiItems as $lpItem)
                                @continue(! is_array($lpItem))
                                @php
                                    // Inisial untuk avatar: huruf pertama nama.
                                    $lpNama = trim((string) ($lpItem['author'] ?? ''));
                                    $lpInit = $lpNama !== ''
                                        ? mb_strtoupper(mb_substr($lpNama, 0, 1))
                                        : '';
                                @endphp
                                <div class="card">
                                    <div class="lp-quote-mark" aria-hidden="true">&ldquo;</div>
                                    @if(! empty($lpItem['quote']))
                                        <p class="lp-quote">{{ $lpItem['quote'] }}</p>
                                    @endif
                                    @if($lpNama !== '')
                                        <div class="lp-quote-author">
                                            <span class="lp-quote-avatar" aria-hidden="true">{{ $lpInit }}</span>
                                            <span>
                                                <span class="lp-quote-name">{{ $lpNama }}</span>
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            @endforeach


                        </div>
                    @else
                        {{-- Empty state elegan --}}
                        <div class="lp-testimonials-empty">
                            <p>Belum ada testimoni klien.</p>
                            <small>Testimoni yang ditambahkan melalui builder akan muncul di sini.</small>
                        </div>
                    @endif
                    @break

                @case('statistics')
                    @php
                        $lpStatItems = (is_array($lpSection['items'] ?? null)) ? $lpSection['items'] : [];
                    @endphp
                    @if($lpBadge !== '')
                        <span class="lp-eyebrow">{{ $lpBadge }}</span>
                    @endif
                    <h2>{{ $lpSection['title'] ?? '' }}</h2>
                    @if(! empty($lpSection['desc']))
                        <p class="lp-lead">{{ $lpSection['desc'] }}</p>
                    @endif
                    @if(! empty($lpStatItems))
                        <div class="stats-grid">
                            @foreach($lpStatItems as $lpItem)
                                @continue(! is_array($lpItem))
                                <div class="stat-item">
                                    <div class="stat-number">{{ $lpItem['value'] ?? '' }}</div>
                                    @if(! empty($lpItem['label']))
                                        <p class="stat-label">{{ $lpItem['label'] }}</p>
                                    @endif
                                </div>
                            @endforeach


                        </div>
                    @else
                        {{-- Empty state elegan --}}
                        <div class="lp-testimonials-empty">
                            <p>Belum ada data statistik.</p>
                            <small>Angka &amp; pencapaian ditambahkan melalui builder akan muncul di sini.</small>
                        </div>
                    @endif
                    @break

                @case('faq')
                    @php
                        $lpFaqItems = (is_array($lpSection['items'] ?? null)) ? $lpSection['items'] : [];
                    @endphp
                    @if($lpBadge !== '')
                        <span class="lp-eyebrow">{{ $lpBadge }}</span>
                    @endif
                    <h2>{{ $lpSection['title'] ?? '' }}</h2>
                    @if(! empty($lpSection['desc']))
                        <p class="lp-lead">{{ $lpSection['desc'] }}</p>
                    @endif
                    @if(! empty($lpFaqItems))
                        <div class="lp-faq-list">
                            @foreach($lpFaqItems as $lpIndex => $lpItem)
                                @continue(! is_array($lpItem))
                                <div class="faq-item">
                                    <button type="button" class="faq-question" aria-expanded="false" aria-controls="faq-ans-{{ $lpSection['id'] ?? 'x' }}-{{ $lpIndex }}">
                                        {{ $lpItem['question'] ?? '' }}
                                        <span class="faq-icon" aria-hidden="true">+</span>
                                    </button>
                                    @if(! empty($lpItem['answer']))
                                        <p id="faq-ans-{{ $lpSection['id'] ?? 'x' }}-{{ $lpIndex }}" class="faq-answer" role="region" aria-hidden="true">{{ $lpItem['answer'] }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <script>
                        (function () {
                            'use strict';
                            // FAQ Accordion - aksesibel, satu item terbuka pada satu waktu
                            document.querySelectorAll('.lp-faq-list').forEach(function (list) {
                                var buttons = list.querySelectorAll('.faq-question');
                                buttons.forEach(function (btn) {
                                    btn.addEventListener('click', function () {
                                        var expanded = btn.getAttribute('aria-expanded') === 'true';
                                        buttons.forEach(function (b) {
                                            if (b !== btn) {
                                                b.setAttribute('aria-expanded', 'false');
                                                var panelId = b.getAttribute('aria-controls');
                                                var panel = panelId ? document.getElementById(panelId) : null;
                                                if (panel) { panel.classList.remove('is-open'); panel.setAttribute('aria-hidden', 'true'); }
                                            }
                                        });
                                        var panelId = btn.getAttribute('aria-controls');
                                        var panel = panelId ? document.getElementById(panelId) : null;
                                        if (panel) {
                                            var nowOpen = !expanded;
                                            btn.setAttribute('aria-expanded', nowOpen ? 'true' : 'false');
                                            panel.classList.toggle('is-open', nowOpen);
                                            panel.setAttribute('aria-hidden', nowOpen ? 'false' : 'true');
                                        }
                                    });
                                });
                            });
                        })();
                        </script>
                    @else
                        {{-- Empty state elegan --}}
                        <div class="lp-testimonials-empty">
                            <p>Belum ada pertanyaan umum.</p>
                            <small>FAQ ditambahkan melalui builder akan muncul di sini.</small>
                        </div>
                    @endif
                    @break

                @case('cta')
                    @php
                        $lpCtaImage = $lpSafeImage($lpSection['image'] ?? '');
                    @endphp
                    <div class="lp-cta">
                        @if($lpBadge !== '')
                            <span class="lp-eyebrow" style="color:var(--orion-brand-turquoise, #4D55F8)">{{ $lpBadge }}</span>
                        @endif
                        <h2>{{ $lpSection['title'] ?? '' }}</h2>
                        @if(! empty($lpSection['desc']))
                            <p>{{ $lpSection['desc'] }}</p>
                        @endif
                        @if($lpBtnText !== '' && $lpBtnUrl)
                            <a href="{{ $lpBtnUrl }}" class="btn" style="margin-top:18px" {!! $lpIsExternal($lpBtnUrl) ? 'target="_blank" rel="noopener noreferrer"' : '' !!}>{{ $lpBtnText }}</a>
                        @endif
                    </div>
                    @break

                @case('contact')
                    @php
                        $lpContactItems = (is_array($lpSection['items'] ?? null)) ? $lpSection['items'] : [];
                        // Fase 4: data usaha dibaca READ-ONLY dari AppSetting.
                        // Tidak ada tabel kontak baru, tidak ada penulisan.
                        // Bila field kosong, baris tidak ditampilkan sama sekali
                        // (fallback aman, bukan teks rekaan).
                        $lpSetting = \App\Models\AppSetting::current();
                        $lpSettingMap = [
                            'nama'      => $lpSetting->nama_aplikasi ?: '',
                            'alamat'    => $lpSetting->alamat ?: '',
                            'telepon'   => $lpSetting->telepon ?: '',
                            'whatsapp'  => $lpSetting->whatsapp ?: '',
                            'email'     => $lpSetting->email ?: '',
                            'website'   => $lpSetting->website ?: '',
                        ];
                    @endphp
                    <div class="lp-contact">
                        <div class="lp-contact-text">
                            @if($lpBadge !== '')
                                <span class="lp-eyebrow">{{ $lpBadge }}</span>
                            @endif
                            <h2>{{ $lpSection['title'] ?? '' }}</h2>
                            @if(! empty($lpSection['desc']))
                                <p class="lp-lead">{{ $lpSection['desc'] }}</p>
                            @endif
                            @if(! empty($lpContactItems))
                                <div class="lp-contact-list">
                                    @foreach($lpContactItems as $lpItem)
                                        @continue(! is_array($lpItem) || empty($lpItem['label']))
                                        @php
                                            // Bila nilai item kosong, coba ambil dari
                                            // AppSetting (read-only). Label dipakai
                                            // sebagai kunci (case-insensitive).
                                            $lpVal = trim((string) ($lpItem['value'] ?? ''));
                                            if ($lpVal === '') {
                                                $lpKey = strtolower(trim((string) $lpItem['label']));
                                                foreach ($lpSettingMap as $lpSKey => $lpSVal) {
                                                    if ($lpKey === $lpSKey || str_contains($lpKey, $lpSKey)) {
                                                        $lpVal = $lpSVal;
                                                        break;
                                                    }
                                                }
                                            }
                                        @endphp
                                        @continue($lpVal === '')
                                        <div class="lp-contact-row">
                                            <span class="lp-contact-icon" aria-hidden="true">&bull;</span>
                                            <span>
                                                <span class="lp-contact-label">{{ $lpItem['label'] }}</span><br>
                                                <span class="lp-contact-value">{{ $lpVal }}</span>
                                            </span>
                                        </div>
                                    @endforeach


                                </div>
                            @endif
                        </div>
                        <aside class="lp-contact-panel">
                            @if(! empty($lpSection['subtitle']))
                                <h3>{{ $lpSection['subtitle'] }}</h3>
                            @endif
                            <p>Tanyakan kebutuhan cetak Anda. Tim Orion Grafika siap membantu.</p>
                            @if($lpBtnText !== '' && $lpBtnUrl)
                                <a href="{{ $lpBtnUrl }}" class="btn btn-dark">Konsultasi</a>
                            @endif
                            <div class="lp-contact-note">
                                Data kontak diambil dari Pengaturan ERP agar selalu sinkron.
                            </div>
                        </aside>
                    </div>
                    @break

                @case('footer')
                    {{-- FOOTER: satu-satu sumber markup (footer-render.blade.php).
                         Dipakai juga oleh landing-page-public.blade.php dan
                         publik/layout-artikel.blade.php, sehingga ketiga
                         halaman tidak lagi punya footer yang berbeda. --}}
                    @if($lpAnchorId !== '')
                        <div id="{{ $lpAnchorId }}" data-lp-frame-anchor="footer" data-lp-footer-anchor>
                            @include('pengaturan.partials.footer-render', [
                                'lpFooterSection'  => $lpSection,
                                'lpFooterNavItems' => $lpFooterNavItems ?? [],
                                'lpFooterSections' => $sections,
                            ])
                        </div>
                    @else
                        @include('pengaturan.partials.footer-render', [
                            'lpFooterSection'  => $lpSection,
                            'lpFooterNavItems' => $lpFooterNavItems ?? [],
                            'lpFooterSections' => $sections,
                        ])
                    @endif


                    @break

                @default
                    {{-- Tipe tidak dikenal: render minimal yang aman, tanpa konten mentah. --}}
                    <h2>{{ $lpSection['title'] ?? '' }}</h2>
                    @if(! empty($lpSection['desc']))
                        <p>{{ $lpSection['desc'] }}</p>
                    @endif
            @endswitch
        </div>
    </section>
@endforeach








@if(count($sections) > 0)
<script>
(function () {
    'use strict';

    // Guard: boleh terikat sekali per halaman walau partial dirender ulang.
    if (window.__lpLightboxBound) { return; }
    window.__lpLightboxBound = true;

    // ---- Filter kategori portfolio ------------------------------------
    document.querySelectorAll('[data-lpb-portfolio-filter]').forEach(function (wrap) {
        var grid = wrap.parentElement.querySelector('[data-lpb-portfolio-grid]');
        if (!grid) return;
        var cards = Array.prototype.slice.call(grid.querySelectorAll('.lp-portfolio-card'));
        var pills = Array.prototype.slice.call(wrap.querySelectorAll('.lp-filter-pill'));

        wrap.addEventListener('click', function (e) {
            var btn = e.target.closest('.lp-filter-pill');
            if (!btn) return;
            pills.forEach(function (p) { p.classList.remove('is-active'); });
            btn.classList.add('is-active');
            var aktif = btn.getAttribute('data-lpb-filter');
            cards.forEach(function (c) {
                // Strip quotes dari attribute (htmlspecialchars bisa menambah ")
                var kat = (c.getAttribute('data-lpb-kategori') || '').replace(/^"|"$/g, '').replace(/^"|"$/g, '');
                var tampil = aktif === '*' || kat === aktif;
                c.style.display = tampil ? '' : 'none';
            });
        });
    });

    // ---- Lightbox -----------------------------------------------------
    // Overlay dibuat sekali, dipakai bersama oleh semua gambar.
    var overlay = document.createElement('div');
    overlay.className = 'lp-lightbox';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', 'Perbesar gambar');
    overlay.innerHTML =
        '<button type="button" class="lp-lightbox-close" aria-label="Tutup">&times;</button>' +
        '<img alt="" loading="lazy" onerror="this.style.display=\'none\'">';
    document.body.appendChild(overlay);

    var lbImg = overlay.querySelector('img');

    function tutupLightbox() {
        overlay.classList.remove('is-open');
        lbImg.removeAttribute('src');
        lbImg.alt = '';
        document.body.classList.remove('overflow-hidden');
    }

    function bukaLightbox(src, alt) {
        lbImg.src = src;
        lbImg.alt = alt || '';
        overlay.classList.add('is-open');
        document.body.classList.add('overflow-hidden');
        overlay.querySelector('.lp-lightbox-close').focus();
    }

    document.addEventListener('click', function (e) {
        var pic = e.target.closest('img[data-lpb-lightbox]');
        if (pic) { bukaLightbox(pic.src, pic.alt); return; }
        if (e.target === overlay) tutupLightbox();
    });
    overlay.querySelector('.lp-lightbox-close').addEventListener('click', tutupLightbox);

    // Keyboard: Esc menutup, tab tidak keluar dari overlay.
    document.addEventListener('keydown', function (e) {
        if (!overlay.classList.contains('is-open')) return;
        if (e.key === 'Escape') { e.preventDefault(); tutupLightbox(); return; }
        if (e.key === 'Tab') {
            var fokus = overlay.querySelector('.lp-lightbox-close');
            if (fokus) { e.preventDefault(); fokus.focus(); }
        }
    });
})();
</script>
@endif
