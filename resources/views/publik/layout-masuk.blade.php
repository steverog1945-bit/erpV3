{{--
    LAYOUT KHUSUS LOGIN ERP (/masuk).

    Dipisah dari publik/layout.blade.php (Landing Page lama) atas keputusan
    operator: "/masuk adalah halaman LOGIN ERP KHUSUS KARYAWAN". Form tidak
    lagi diletakkan di atas Hero, daftar layanan, atau section Landing Page
    lama.

    Yang tetap dipertahankan:
      - Form POST ke url('login') dengan @csrf
      - Nama field 'username' dan 'password' (dipakai controller login)
      - Name / deskripsi / tombol lihat password
      - Tampilan error validasi dari $errors
      - Pesan rate limit (429) yang dilempar controller

    Yang TIDAK ada di sini: hero, daftar layanan, cara pesan, daftar
    tulisan, kontak, dan footer template lama. Hanya identitas Orion
    Grafika + panel login.

    Template lama TIDAK dihapus - publik/layout.blade.php tetap dipakai
    sebagai fallback homepage saat Gate B OFF.
--}}
@php
    $appSetting = \App\Models\AppSetting::current();
    $urlLogo = $appSetting->logo_path ? asset('storage/' . $appSetting->logo_path) : null;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('judul', 'Masuk - ' . $appSetting->nama_aplikasi)</title>

    {{-- PWA: manifest + ikon agar app bisa dipasang dari halaman login juga. --}}
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <meta name="theme-color" content="#15143F" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#F7F7FD" media="(prefers-color-scheme: light)">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('{{ route('pwa.sw') }}').catch(function () {});
            });
        }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('vendor/fonts/inter.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/all.min.css') }}">

    <style>
        :root {
            --masuk-night: #15143F;   /* DEEP NIGHT */
            --masuk-indigo: #2E318E; /* ROYAL INDIGO */
            --masuk-violet: #4D55F8; /* ELECTRIC VIOLET */
            --masuk-keluar: #E8E9FE;
            --masuk-kertas: #F7F7FD;
            --masuk-teks: #DCDCFB;
            --masup-aman: #FFFFFF;
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--masuk-night);
            color: var(--masuk-teks);
            -webkit-font-smoothing: antialiased;
        }

        /* ---- Latar: gradasi indigo, dekoratif, tidak mengganggu form ---- */
        .mk-latar {
            position: relative;
            min-height: 100vh;
            min-height: 100dvh;
            display: grid;
            grid-template-rows: auto 1fr auto;
            gap: 24px;
            padding: 28px 20px 24px;
            overflow: hidden;
            background:
                radial-gradient(1100px 520px at 12% -8%, rgba(77, 85, 248, .38), transparent 62%),
                radial-gradient(900px 480px at 92% 108%, rgba(46, 49, 142, .55), transparent 60%),
                var(--masuk-night);
        }
        .mk-kepala { display: flex; align-items: center; justify-content: space-between; gap: 16px; max-width: 1120px; width: 100%; margin: 0 auto; }
        .mk-merek { display: inline-flex; align-items: center; gap: 10px; color: var(--masup-aman); text-decoration: none; font-weight: 750; letter-spacing: -.01em; }
        .mk-merek img { height: 34px; width: auto; object-fit: contain; }
        .mk-merek-mark {
            display: inline-flex; align-items: center; justify-content: center;
            width: 34px; height: 34px; border-radius: 10px;
            background: var(--masuk-violet); color: #FFFFFF; font-size: 13px; font-weight: 800;
        }
        .mk-ke-beranda { color: var(--masup-aman); font-size: 14px; text-decoration: none; opacity: .82; padding: 8px 14px; border: 1px solid rgba(255, 255, 255, .22); border-radius: 999px; transition: opacity .18s ease, border-color .18s ease; }
        .mk-ke-beranda:hover { opacity: 1; border-color: #FFFFFF; }

        /* ---- Panel login ---- */
        .mk-panel {
            align-self: center;
            justify-self: center;
            width: 100%;
            max-width: 424px;
            background: #FFFFFF;
            color: var(--masuk-night);
            border-radius: 18px;
            padding: 30px 28px 28px;
            box-shadow: 0 26px 70px rgba(0, 0, 0, .38);
        }
        .mk-judul { margin: 0 0 6px; font-size: 22px; line-height: 1.2; font-weight: 750; letter-spacing: -.02em; }
        .mk-sub { margin: 0 0 22px; font-size: 14px; color: #55516F; }
        .mk-label { display: block; margin: 0 0 6px; font-size: 13px; font-weight: 600; color: #3A3760; }
        .mk-grup { margin-bottom: 16px; }
        .mk-input {
            width: 100%;
            height: 46px;
            padding: 0 14px;
            font-size: 15px;
            font-family: inherit;
            color: var(--masuk-night);
            background: #FFFFFF;
            border: 1px solid #D6D6E8;
            border-radius: 10px;
            transition: border-color .16s ease, box-shadow .16s ease;
        }
        .mk-input:focus { outline: none; border-color: var(--masuk-violet); box-shadow: 0 0 0 3px rgba(77, 85, 248, .18); }
        .mk-sandi { position: relative; }
        .mk-sandi .mk-input { padding-right: 46px; }
        .mk-lihat {
            position: absolute; top: 50%; right: 6px; transform: translateY(-50%);
            width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center;
            border: 0; border-radius: 8px; background: transparent; color: #6A6790; cursor: pointer;
        }
        .mk-lihat:hover { background: var(--masuk-keluar); color: var(--masuk-night); }
        .mk-tombol {
            width: 100%; height: 48px; margin-top: 6px;
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            border: 0; border-radius: 10px; cursor: pointer;
            background: var(--masuk-violet); color: #FFFFFF;
            font-size: 15px; font-weight: 650; font-family: inherit;
            transition: background-color .16s ease, transform .16s ease;
        }
        .mk-tombol:hover { background: #3B41D6; }
        .mk-tombol:active { transform: translateY(1px); }
        .mk-tombol:focus-visible { outline: 3px solid var(--masuk-night); outline-offset: 2px; }
        .mk-galat {
            display: flex; align-items: flex-start; gap: 10px;
            margin: 0 0 18px; padding: 11px 13px;
            font-size: 13.5px; line-height: 1.45;
            color: #8A1C2B; background: #FDECEF; border: 1px solid #F4C3CB; border-radius: 10px;
        }
        .mk-kaki { text-align: center; font-size: 12.5px; color: rgba(220, 220, 251, .72); }
        .mk-kaki a { color: #FFFFFF; text-decoration: underline; text-underline-offset: 3px; }

        @media (max-width: 560px) {
            .mk-latar { padding: 18px 14px 18px; }
            .mk-panel { padding: 24px 18px 22px; border-radius: 16px; }
            .mk-ke-beranda { padding: 7px 11px; font-size: 13px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .mk-tombol, .mk-input, .mk-ke-beranda { transition: none; }
        }
    </style>
    @stack('gaya')
</head>
<body>
    <div class="mk-latar">
        <div class="mk-kepala">
            <a class="mk-merek" href="{{ route('beranda') }}">
                @if($urlLogo)
                    <img src="{{ $urlLogo }}" alt="{{ $appSetting->nama_aplikasi }}">
                @else
                    <span class="mk-merek-mark" aria-hidden="true">OG</span>
                @endif
                <span>{{ $appSetting->nama_aplikasi }}</span>
            </a>
            <a class="mk-ke-beranda" href="{{ route('beranda') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali ke situs</a>
        </div>

        <main class="mk-panel">
            @yield('isi')
        </main>

        <p class="mk-kaki">
            &copy; {{ date('Y') }} {{ $appSetting->nama_aplikasi }} · Akses khusus karyawan
        </p>
    </div>

    @stack('skrip')
</body>
</html>