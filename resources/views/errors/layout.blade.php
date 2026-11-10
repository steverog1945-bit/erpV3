<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex">
    <title>@yield('code') ┬╖ Orion Grafika</title>
    <style>
        /* Error pages Orion Grafika ΓÇö mandiri total: tanpa aset eksternal,
           tanpa JS, tanpa database. Token disamakan dengan ui-refresh.css
           (Inter, #E9EEFA/#FFFFFF/#E3E9F6/#0F1E3E/#2563EB, radius 10/16px). */
        :root {
            --bg: #E9EEFA;
            --card: #FFFFFF;
            --border: #E3E9F6;
            --text: #0F1E3E;
            --text-2: #3E4C74;
            --text-3: #5D6A85;
            --text-4: #8896B8;
            --accent: #2563EB;
            --accent-hover: #1D4ED8;
            --accent-light: #EFF4FF;
            --accent-border: #C9D8FF;
            --radius-card: 16px;
            --radius-btn: 10px;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #141A29;
                --card: #1C2436;
                --border: #2A3450;
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
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            -webkit-font-smoothing: antialiased;
        }
        .kartu {
            width: 100%;
            max-width: 420px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius-card);
            padding: 28px 28px 20px;
            box-shadow: 0 1px 2px rgba(16, 24, 20, .05), 0 20px 44px rgba(16, 24, 20, .08);
            text-align: center;
        }
        /* Brand row */
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 18px;
            margin-bottom: 22px;
            border-bottom: 1px solid var(--border);
        }
        .brand-mark {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
        }
        .brand-nama {
            font-size: 12px;
            font-weight: 500;
            letter-spacing: .16em;
            color: var(--text-2);
            text-align: left;
            line-height: 1.25;
        }
        .brand-sub {
            display: block;
            font-size: 9px;
            font-weight: 500;
            letter-spacing: .22em;
            color: var(--text-4);
            margin-top: 2px;
        }
        /* Ikon besar */
        .ikon-lingkar {
            width: 88px;
            height: 88px;
            border-radius: 999px;
            background: var(--accent-light);
            border: 1px solid var(--accent-border);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 2px auto 14px;
        }
        .kode {
            font-size: 46px;
            line-height: 1;
            font-weight: 500;
            letter-spacing: -0.02em;
            color: var(--accent);
            font-variant-numeric: tabular-nums;
        }
        h1 {
            font-size: 17px;
            font-weight: 500;
            color: var(--text);
            margin: 10px 0 8px;
            letter-spacing: 0;
        }
        .pesan {
            font-size: 13px;
            font-weight: 400;
            color: var(--text-3);
            line-height: 1.65;
            max-width: 340px;
            margin: 0 auto;
        }
        .hint {
            margin: 14px auto 0;
            max-width: 340px;
            background: var(--accent-light);
            border: 1px solid var(--accent-border);
            border-radius: var(--radius-btn);
            padding: 9px 12px;
            font-size: 11.5px;
            line-height: 1.55;
            color: var(--accent-hover);
            display: flex;
            gap: 8px;
            align-items: flex-start;
            text-align: left;
        }
        .hint svg { flex: 0 0 auto; margin-top: 1px; }
        /* Tombol */
        .aksi {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 22px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 38px;
            padding: 0 16px;
            border-radius: var(--radius-btn);
            font-family: inherit;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            border: 1px solid transparent;
            cursor: pointer;
            transition: background-color .15s ease, border-color .15s ease, color .15s ease;
        }
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
        .btn-secondary {
            background: transparent;
            border-color: var(--border);
            color: var(--text-2);
        }
        .btn-secondary:hover { border-color: var(--text-3); color: var(--text); }
        /* Kaki */
        .kaki {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            border-top: 1px solid var(--border);
            margin-top: 22px;
            padding-top: 14px;
            font-size: 10.5px;
            letter-spacing: .04em;
            color: var(--text-4);
            text-align: left;
        }
        .kaki span:last-child { text-align: right; }
    </style>
</head>
<body>
    <main class="kartu" role="main" aria-labelledby="judul-error">
        <div class="brand">
            <span class="brand-mark" aria-hidden="true">
                {{-- Glyph printer: mewakili percetakan/ERP produksi --}}
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M7 8V3h10v5"/>
                    <rect x="3" y="8" width="18" height="9" rx="2"/>
                    <path d="M7 13h10v8H7z"/>
                    <circle cx="17.5" cy="11" r="0.5" fill="#fff" stroke="none"/>
                </svg>
            </span>
            <span class="brand-nama">ORION GRAFIKA<span class="brand-sub">ERP PERCETAKAN</span></span>
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
            <span>ORION GRAFIKA ┬╖ ERP PERCETAKAN</span>
            <span>Jika berulang, hubungi Administrator</span>
        </div>
    </main>
</body>
</html>
