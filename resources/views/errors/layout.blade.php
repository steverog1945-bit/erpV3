<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex">
    <title>@yield('code') · Orion Grafika</title>
    <style>
        /* Error pages Orion Grafika — mandiri total: tanpa aset eksternal,
           tanpa JS, tanpa database. Token disamakan dengan Command Center
           (#E9EEFA/#FFFFFF/#E3E9F6/#0F1E3E/#2563EB, radius 16/10px). */
        :root {
            --bg: #E9EEFA;
            --card: #FFFFFF;
            --border: #E3E9F6;
            --border-2: #CBD6EE;
            --text: #0F1E3E;
            --text-2: #3E4C74;
            --text-3: #5D6A85;
            --text-4: #8896B8;
            --accent: #2563EB;
            --accent-hover: #1D4ED8;
            --accent-light: #EFF4FF;
            --accent-border: #C9D8FF;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #141A29;
                --card: #1C2436;
                --border: #2A3450;
                --border-2: #3A4767;
                --text: #E9EEFA;
                --text-2: #B9C3DD;
                --text-3: #8896B8;
                --text-4: #5D6A85;
                --accent: #7FA6F5;
                --accent-hover: #5B8DEF;
                --accent-light: #22304F;
                --accent-border: #31456F;
            }
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background:
                radial-gradient(900px 560px at 88% -18%, rgba(37, 99, 235, .09) 0%, transparent 55%),
                radial-gradient(720px 560px at -12% 118%, rgba(37, 99, 235, .07) 0%, transparent 55%),
                var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 18px;
            -webkit-font-smoothing: antialiased;
            font-feature-settings: "cv11", "ss01";
        }
        .kartu {
            width: 100%;
            max-width: 430px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 26px 28px 18px;
            box-shadow: 0 1px 2px rgba(15, 30, 62, .04), 0 18px 48px rgba(15, 30, 62, .10);
        }
        /* Brand row */
        .brand {
            display: flex;
            align-items: center;
            gap: 11px;
            padding-bottom: 18px;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border);
        }
        .brand-nama {
            font-size: 14px;
            font-weight: 600;
            letter-spacing: -0.02em;
            color: var(--text);
            text-align: left;
            line-height: 1.15;
        }
        .brand-sub {
            display: block;
            font-size: 8px;
            font-weight: 600;
            letter-spacing: .24em;
            color: var(--text-4);
            margin-top: 3px;
            text-transform: uppercase;
        }
        /* Isi tengah */
        .kode {
            font-size: 64px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: var(--text);
            font-variant-numeric: tabular-nums;
            margin-top: 4px;
        }
        h1 {
            font-size: 15.5px;
            font-weight: 600;
            letter-spacing: -0.01em;
            color: var(--text);
            margin: 10px 0 6px;
        }
        .pesan {
            font-size: 12.5px;
            font-weight: 400;
            color: var(--text-3);
            line-height: 1.65;
            max-width: 340px;
        }
        .hint {
            margin: 14px 0 0;
            background: var(--accent-light);
            border: 1px solid var(--accent-border);
            border-radius: 10px;
            padding: 9px 12px;
            font-size: 11px;
            line-height: 1.6;
            color: var(--accent-hover);
            display: flex;
            gap: 8px;
            align-items: flex-start;
        }
        .hint svg { flex: 0 0 auto; margin-top: 1px; }
        /* Tombol */
        .aksi {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 40px;
            padding: 0 18px;
            border-radius: 10px;
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 600;
            letter-spacing: .01em;
            text-decoration: none;
            border: 1px solid transparent;
            cursor: pointer;
            transition: background-color .15s ease, border-color .15s ease, color .15s ease, transform .15s ease;
        }
        .btn:hover { transform: translateY(-1px); }
        .btn:active { transform: none; }
        .btn:focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
        }
        .btn-primary {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }
        .btn-primary:hover { background: var(--accent-hover); border-color: var(--accent-hover); }
        .ikon-lingkar {
            width: 84px;
            height: 84px;
            border-radius: 999px;
            background: var(--accent-light);
            border: 1px solid var(--accent-border);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 2px auto 14px;
        }
        .btn-secondary {
            background: transparent;
            border-color: var(--border-2);
            color: var(--text-2);
        }
        .btn-secondary:hover { border-color: var(--text-3); color: var(--text); }
        .kaki {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            border-top: 1px solid var(--border);
            margin-top: 20px;
            padding-top: 13px;
            font-size: 9.5px;
            font-weight: 600;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--text-4);
        }
        @media (max-width: 480px) {
            .kartu { padding: 20px 18px 14px; border-radius: 16px; }
            .kode { font-size: 52px; }
            .aksi .btn { flex: 1 1 auto; justify-content: center; }
        }
        @media (prefers-reduced-motion: reduce) {
            .btn { transition: none; }
            .btn:hover { transform: none; }
        }
    </style>
</head>
<body>
    <main class="kartu" role="main" aria-labelledby="judul-error">
        <div class="brand">
            {{-- Marka Orion: tiga titik di atas ubin biru (identitas aplikasi) --}}
            <svg width="34" height="34" viewBox="0 0 36 36" aria-hidden="true"><rect width="36" height="36" rx="9" fill="#1D4ED8"/><circle cx="13.4" cy="15.6" r="3.4" fill="#22D3EE"/><circle cx="18" cy="11" r="3.4" fill="#F43F5E"/><circle cx="22.6" cy="15.6" r="3.4" fill="#FACC15"/></svg>
            <span class="brand-nama">Orion Grafika<span class="brand-sub">ERP Percetakan</span></span>
        </div>

        <div class="ikon-lingkar" aria-hidden="true">@yield('ikon')</div>
        <div class="kode" aria-hidden="true">@yield('code')</div>
        <h1 id="judul-error">@yield('title')</h1>
        <p class="pesan">@yield('message')</p>
        @hasSection('hint')
            <div class="hint">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/><path d="M12 8h.01M12 12v4"/>
                </svg>
                <span>@yield('hint')</span>
            </div>
        @endif
        <div class="aksi">
            @hasSection('primary')
                @yield('primary')
            @endif
            @hasSection('secondary')
                @yield('secondary')
            @endif
        </div>
        <div class="kaki">
            <span>Orion Grafika ERP</span>
            <span>Jika berulang, hubungi Administrator</span>
        </div>
    </main>
</body>
</html>
