<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- Preview SELALU noindex. URL preview tidak boleh terindeks. --}}
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>{{ $seo['title'] ?? $page->title }} — Preview</title>
    @if(!empty($seo['description']))
    <meta name="description" content="{{ $seo['description'] }}">
    @endif
    @if(!empty($seo['og_title']))
    <meta property="og:title" content="{{ $seo['og_title'] }}">
    @endif
    @if(!empty($seo['og_description']))
    <meta property="og:description" content="{{ $seo['og_description'] }}">
    @endif
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
        .preview-banner {
            background-color: var(--tinta);
            color: var(--orion-neutral-100, #FFFFFF);
            text-align: center;
            padding: 8px 16px;
            font-size: 13px;
            letter-spacing: 0.5px;
        }
        .preview-banner strong { color: var(--orion-cyan-light, #B8EEFF); }
        /* ---- Responsive switcher (preview only) ---- */
        .preview-switcher {
            display: inline-flex; gap: 4px; margin-left: 16px;
            background: rgba(255,255,255,.1); border-radius: 8px; padding: 3px;
        }
        .preview-switcher button {
            background: transparent; border: 0; color: var(--orion-neutral-700, #64748B);
            font-size: 12px; font-weight: 600; padding: 5px 12px;
            border-radius: 6px; cursor: pointer; font-family: inherit;
        }
        .preview-switcher button.is-active { background: var(--aksen); color: var(--kontras-aksen); }
        .preview-switcher button:hover:not(.is-active) { color: #FFFFFF; }
        .preview-switcher button:focus-visible { outline: 2px solid var(--orion-cyan-light, #B8EEFF); outline-offset: 2px; }
        /* Iframe container */
        .preview-iframe-wrapper {
            display: block; width: 100%; height: calc(100vh - 52px); min-height: 600px;
            border: 0; background: #fff; border-radius: 0 0 12px 12px;
            box-shadow: 0 4px 24px rgba(21,20,63,.08);
            transition: width 200ms ease, height 200ms ease;
            overflow: hidden; /* mencegah konten melebarkan iframe */
        }
        .preview-iframe-wrapper.lpb-mode-desktop { width: 100%; max-width: none; height: calc(100vh - 52px); }
        .preview-iframe-wrapper.lpb-mode-tablet { width: 768px; max-width: 768px; height: calc(100vh - 52px); margin: 0 auto; }
        .preview-iframe-wrapper.lpb-mode-mobile { width: 375px; max-width: 375px; height: calc(100vh - 52px); margin: 0 auto; }
    </style>
</head>
<body>
    <div class="preview-banner">
        <strong>PREVIEW MODE</strong> — {{ ucfirst($previewMode) }} | Not indexed | Admin only
        <span class="preview-switcher" role="group" aria-label="Lebar preview">
            <button type="button" data-preview-width="desktop" class="is-active" aria-pressed="true">Desktop</button>
            <button type="button" data-preview-width="tablet" aria-pressed="false">Tablet</button>
            <button type="button" data-preview-width="mobile" aria-pressed="false">Mobile</button>
        </span>
    </div>

    {{-- Src HARUS mengikuti mode preview.
         Kalau selalu tanpa parameter, maka "Published Testing" justru
         menampilkan isi DRAFT — operator menyetujui sesuatu yang
         belum pernah dipublish. --}}
    @php
        $iframeRoute = route('landing-page-builder.preview.draft.iframe');
        $previewSrc = match ($previewMode) {
            'published' => $iframeRoute . '?mode=published',
            'revision'  => $iframeRoute . '?mode=revision&rev=' . (int) ($revisionNumber ?? 1),
            default     => $iframeRoute . '?mode=draft',
        };
    @endphp
    <iframe class="preview-iframe-wrapper lpb-mode-desktop"
            src="{{ $previewSrc }}"
            title="Preview Website"
            sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox">
    </iframe>

    <script>
        // Responsive switcher via iframe. Mengubah ukuran iframe, BUKAN container width.
        // Iframe memiliki viewport sendiri sehingga @media query BERFUNGSI SEBENARNYA.
        (function () {
            var switcher = document.querySelector('.preview-switcher');
            var iframe = document.querySelector('.preview-iframe-wrapper');
            if (!switcher || !iframe) return;

            var UKURAN = {
                desktop: { width: '100%', height: 'calc(100vh - 52px)', margin: '0' },
                tablet:  { width: '768px',  height: 'calc(100vh - 52px)', margin: '0 auto' },
                mobile:  { width: '375px',  height: 'calc(100vh - 52px)', margin: '0 auto' }
            };

            function terapkan(mode) {
                iframe.classList.remove('lpb-mode-desktop', 'lpb-mode-tablet', 'lpb-mode-mobile');
                iframe.classList.add('lpb-mode-' + mode);
                var sz = UKURAN[mode] || UKURAN.desktop;
                iframe.style.width = sz.width;
                iframe.style.height = sz.height;
                iframe.style.margin = sz.margin;
            }

            switcher.addEventListener('click', function (e) {
                var btn = e.target.closest('button[data-preview-width]');
                if (!btn) return;
                switcher.querySelectorAll('button').forEach(function (b) {
                    b.classList.remove('is-active');
                    b.setAttribute('aria-pressed', 'false');
                });
                btn.classList.add('is-active');
                btn.setAttribute('aria-pressed', 'true');
                terapkan(btn.getAttribute('data-preview-width'));
            });
        })();
    </script>
</body>
</html>