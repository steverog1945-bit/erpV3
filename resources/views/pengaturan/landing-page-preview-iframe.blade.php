<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Preview</title>
    @include('pengaturan.partials.renderer-styles')
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            width: 100%; max-width: 100%;
            /* WAJIB 'clip', BUKAN 'hidden'.
               overflow-x:hidden membuat overflow-y ikut dihitung menjadi 'auto',
               sehingga <body> berubah jadi SCROLL CONTAINER. position:sticky
               bergerak relatif terhadap scroll container terdekat - kalau body
               yang menjadi container itu (padahal tidak pernah digulir),
               header sticky di dalam preview TIDAK AKAN menempel sama sekali
               dan ikut tergulir seperti elemen biasa.
               overflow-x:clip memotong horizontal tanpa membuat scroll
               container sama sekali. */
            overflow-x: clip;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--orion-brand-lighter, #F5F5FF);
            color: var(--orion-brand-navy, #15143F);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        /* Pastikan section-inner tidak melebihi viewport iframe */
        .section-inner { max-width: 100%; }
    </style>
</head>
<body>
    @include('pengaturan.partials.section-render', ['sections' => $sections])

    {{-- Anchor nav memakai offset header sticky yang dinamis, sama persis
         dengan halaman publik (partial bersama). --}}
    @include('pengaturan.partials.smooth-scroll')

    {{--
        Scroll reveal yang sama dengan halaman publik. Preview harus
        menunjukkan persis apa yang akan dilihat pengunjung.
    --}}
    @include('pengaturan.partials.scroll-reveal')
</body>
</html>