<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0F1E3E">
    <meta name="robots" content="noindex, nofollow">
    <title>Lacak Pesanan {{ $t?->nomor_nota ?? '' }} - Orion Grafika</title>
    <link rel="icon" type="image/png" href="{{ asset('icons/icon-192.png') }}">
    <style>
        * { box-sizing: border-box; margin: 0; font-family: 'Inter', system-ui, -apple-system, sans-serif; -webkit-font-smoothing: antialiased; }
        body { min-height: 100vh; background: radial-gradient(900px 600px at 85% -10%, rgba(37,99,235,.16), transparent 55%), radial-gradient(700px 500px at -10% 110%, rgba(37,99,235,.10), transparent 55%), #0F1E3E; color: #E8ECF8; display: flex; flex-direction: column; align-items: center; padding: 28px 16px 40px; }
        .wrap { width: 100%; max-width: 520px; }
        .brand { display: flex; align-items: center; gap: 10px; margin-bottom: 22px; }
        .brand svg { flex: 0 0 auto; }
        .brand b { font-size: 15px; letter-spacing: -0.01em; color: #E8ECF8; font-weight: 600; }
        .brand small { display:block; font-size: 8.5px; letter-spacing: .24em; color: #8A96B8; font-weight: 600; margin-top: 2px; }
        .kartu { background: #1C2436; border: 1px solid #2A3450; border-radius: 20px; padding: 26px; box-shadow: 0 24px 60px rgba(5,10,25,.45); }
        .eyebrow { font-size: 9.5px; letter-spacing: .18em; text-transform: uppercase; color: #8A96B8; font-weight: 600; }
        h1 { font-size: 24px; letter-spacing: -0.02em; font-weight: 600; color: #FFFFFF; margin-top: 6px; }
        .meta { margin-top: 6px; font-size: 12px; color: #B9C3DD; }
        .progress { margin: 22px 0 6px; height: 10px; border-radius: 999px; background: #141A29; overflow: hidden; }
        .progress > div { height: 100%; background: linear-gradient(90deg,#2563EB,#5B8DEF); border-radius: 999px; transition: width .6s cubic-bezier(.32,.72,0,1); }
        .progress-teks { display: flex; justify-content: space-between; font-size: 10px; color: #8A96B8; letter-spacing: .06em; }
        .chips { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 18px; }
        .chip { background: #141A29; border: 1px solid #2A3450; border-radius: 14px; padding: 12px 8px; text-align: center; }
        .chip .n { font-size: 22px; font-weight: 700; letter-spacing: -0.02em; color: #E8ECF8; font-variant-numeric: tabular-nums; }
        .chip .l { font-size: 9px; letter-spacing: .1em; text-transform: uppercase; color: #8A96B8; margin-top: 3px; }
        .baris { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 18px; padding: 12px 14px; border-radius: 12px; background: #141A29; border: 1px solid #2A3450; font-size: 12px; }
        .pill { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 4px 11px; font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .pill.lunas { background: rgba(14,159,110,.16); color: #34D399; border: 1px solid rgba(14,159,110,.4); }
        .pill.dp { background: rgba(232,137,29,.14); color: #F2B565; border: 1px solid rgba(232,137,29,.4); }
        .pill.belum { background: rgba(228,85,85,.14); color: #F28B8B; border: 1px solid rgba(228,85,85,.4); }
        .pill.ambil { background: rgba(91,141,239,.16); color: #8FB4F8; border: 1px solid rgba(91,141,239,.4); }
        .footer { margin-top: 20px; text-align: center; font-size: 10px; letter-spacing: .1em; text-transform: uppercase; color: #5D6A85; }
        @media (prefers-reduced-motion: reduce) { .progress > div { transition: none; } }
    </style>
</head>
<body>
    <main class="wrap">
        <div class="brand">
            <svg width="34" height="34" viewBox="0 0 36 36" aria-hidden="true"><rect width="36" height="36" rx="8" fill="#1E3A8A"/><circle cx="13.4" cy="15.6" r="3.4" fill="#22D3EE"/><circle cx="18" cy="11" r="3.4" fill="#F43F5E"/><circle cx="22.6" cy="15.6" r="3.4" fill="#FACC15"/></svg>
            <div><b>orion grafika</b><small>PUSAT OPERASI</small></div>
        </div>

        @if(!$t)
            <div class="kartu" style="text-align:center; padding:40px 26px">
                <h1 style="font-size:20px">Pesanan tidak ditemukan</h1>
                <p class="meta" style="margin-top:10px">Tautan mungkin kedaluwarsa atau nota sudah dibatalkan.<br>Hubungi kasir Orion Grafika untuk tautan baru.</p>
            </div>
        @else
            @php
                $total = (int) ($agg->total ?? 0);
                $selesai = (int) ($agg->selesai ?? 0);
                $batal = (int) ($agg->batal ?? 0);
                $aktif = max(1, $total - $batal);
                $persen = (int) round($selesai / $aktif * 100);
                $bayar = $t->status_pembayaran;
                $pillBayar = $bayar === 'Lunas' ? 'lunas' : ($bayar === 'DP' ? 'dp' : 'belum');
            @endphp
            <div class="kartu">
                <span class="eyebrow">Status Pesanan Anda</span>
                <h1>{{ $t->nomor_nota }}</h1>
                <p class="meta">{{ $t->nama }} &middot; {{ \Carbon\Carbon::parse($t->tgl_order)->translatedFormat('d F Y') }}</p>

                <div class="progress" role="img" aria-label="Progres produksi {{ $persen }} persen"><div style="width: {{ $persen }}%"></div></div>
                <div class="progress-teks"><span>{{ $selesai }} dari {{ $aktif }} item selesai</span><span>{{ $persen }}%</span></div>

                <div class="chips">
                    <div class="chip"><div class="n">{{ (int)($agg->antre ?? 0) }}</div><div class="l">Antre</div></div>
                    <div class="chip"><div class="n">{{ (int)($agg->proses ?? 0) }}</div><div class="l">Proses</div></div>
                    <div class="chip"><div class="n">{{ (int)($agg->cetak ?? 0) }}</div><div class="l">Cetak</div></div>
                    <div class="chip"><div class="n">{{ (int)($agg->selesai ?? 0) }}</div><div class="l">Selesai</div></div>
                </div>

                <div class="baris">
                    <span style="color:#B9C3DD">Pembayaran</span>
                    <span class="pill {{ $pillBayar }}">{{ $bayar }}</span>
                </div>
                <div class="baris" style="margin-top:10px">
                    <span style="color:#B9C3DD">Pengambilan barang</span>
                    <span class="pill ambil">{{ $t->status_collecting }}</span>
                </div>
            </div>
            <p class="footer">Orion Grafika ERP &middot; Pusat Operasi</p>
        @endif
    </main>
</body>
</html>
