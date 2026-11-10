<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Order Jersey {{ $order->order_number }}</title>
    @include('partials.print-style')
    @if($format !== 'thermal')
    <style>.sheet { max-width: 680px; }</style>
    @endif
    {{-- Teks finishing jahit lebih kecil dari baris barang utama,
         menyusul kasir nota (baris finishing mengikuti barangnya). --}}
    <style>
        .finishing-item { font-size: 9px; color: #64748b; }
    </style>
    {{--
        Rapatkan margin/spasi di SEMUA bagian ringkasan KECUALI tabel
        rincian barang - permintaan langsung user, tabel rincian barang
        sengaja dibiarkan pakai padding baris default (lebih lega) supaya
        tetap mudah dibaca per barang.
    --}}
    <style>
        .ringkasan-jersey .print-header { margin-bottom: 8px; }
        .ringkasan-jersey .signature { margin-top: 20px; }

        {{-- Info order rapat: label & nilai duduk BERDAMPINGAN dalam satu
             aliran baca kiri ("Pelanggan: Budi"), bukan label nempel kiri
             + nilai terdorong ke tepi kanan yang memaksa mata bolak-balik -
             permintaan langsung user supaya mudah dibaca. --}}
        .info-rapat td { padding: 1.5px 4px; vertical-align: top; }
        .info-rapat .lbl { white-space: nowrap; font-weight: 500; padding-right: 6px; }

        /* Sama dengan nota kasir: printer thermal hanya hitam/putih, teks abu-abu tercetak sebagai titik-titik.
           Semua teks hitam pekat, garis pemisah solid hitam, ukuran thermal naik satu tingkat. Huruf tidak diubah. */
        .ringkasan-jersey, .ringkasan-jersey * { color: #000 !important; }
        .ringkasan-jersey { font-size: {{ $format === 'thermal' ? '12px' : '13px' }}; }
        .ringkasan-jersey table, .ringkasan-jersey th { font-size: inherit; }
        .ringkasan-jersey .print-header .company-info, .ringkasan-jersey .print-header .doc-title { font-size: {{ $format === 'thermal' ? '12px' : '13px' }}; }
        .ringkasan-jersey .divider { border-top: 1px solid #000; }
        .ringkasan-jersey th { border-bottom: 1px solid #000; font-weight: 500; }
        .ringkasan-jersey .signature .line { border-top-color: #000; font-size: inherit; }
        .ringkas-jersey td.l { text-align: right; width: 62%; }
        .ringkas-jersey td.t { width: 12px; text-align: center; }
        .ringkas-jersey td.v { text-align: right; white-space: nowrap; }
        .ringkas-jersey tr.tebal td { font-weight: 500; }
    </style>
</head>
<body>
    <div class="sheet ringkasan-jersey">
        @include('partials.print-header', ['docTitle' => 'Ringkasan Pesanan Jersey'])

        {{-- Info order rapat kiri: pelanggan paling atas (paling sering
             dicari), label & nilainya bersebelahan supaya terbaca sekali
             jalan. Tetap satu kolom sempit, muat di A4. --}}
        <table class="table-compact info-rapat">
            <tr>
                <td class="lbl">Pelanggan</td><td>:</td>
                <td><strong>{{ $order->konsumen->nama_konsumen ?? $order->customer_name }}</strong>@if($order->konsumen?->telepon) &middot; {{ $order->konsumen->telepon }}@endif</td>
            </tr>
            <tr><td class="lbl">No. Order</td><td>:</td><td>{{ $order->order_number }}</td></tr>
            <tr><td class="lbl">Tanggal</td><td>:</td><td>{{ \Carbon\Carbon::parse($order->order_date)->format('d/m/Y') }}</td></tr>
            <tr><td class="lbl">Tim</td><td>:</td><td>{{ $order->team_name ?? '-' }}</td></tr>
            <tr><td class="lbl">Desainer</td><td>:</td><td>{{ $order->designer_name ?? '-' }}</td></tr>
            <tr><td class="lbl">Deadline</td><td>:</td><td>{{ $order->deadline ? \Carbon\Carbon::parse($order->deadline)->format('d/m/Y') : '-' }}</td></tr>
        </table>

        <div class="divider divider-tight"></div>

        <table>
            <thead><tr><th>Barang</th><th>Tipe/Kategori</th><th class="right">Qty</th><th class="right">Subtotal</th></tr></thead>
            <tbody>
                @foreach($order->lines as $line)
                <tr>
                    <td>{{ $line->nama_barang }}</td>
                    <td>{{ $line->productType->name ?? '-' }} / {{ $line->category->name ?? '-' }}</td>
                    <td class="right">{{ angka($line->qty, 4) }}</td>
                    <td class="right">{{ number_format($line->subtotal, 0, ',', '.') }}</td>
                </tr>
                @foreach($line->finishings as $finishing)
                <tr>
                    <td colspan="3" class="finishing-item">&nbsp;&nbsp;<i></i>Jahit: {{ $finishing->finishing->nama_finishing ?? '-' }}{{ $finishing->qty_pakai ? ' (' . angka($finishing->qty_pakai, 4) . ' m)' : '' }}{{ $finishing->keping ? ' @ ' . $finishing->keping . ' keping' : '' }}</td>
                    <td class="right finishing-item">{{ number_format($finishing->biaya, 0, ',', '.') }}</td>
                </tr>
                @endforeach
                @endforeach
            </tbody>
        </table>

        {{-- Estimasi kebutuhan bahan SENGAJA tidak dicetak di nota: nota dipegang pelanggan, sedangkan
             pemakaian bahan adalah data internal perusahaan. Estimasi hanya ada di SPK (dokumen produksi). --}}

        @php

            $totalUkuranOrder = [];
            foreach ($order->lines as $line) {
                foreach ($line->sizeQuantities as $sq) {
                    $nama = $sq->size->name ?? '-';
                    $sort = $sq->size->sort_order ?? 0;
                    if (!isset($totalUkuranOrder[$nama])) {
                        $totalUkuranOrder[$nama] = ['qty' => 0, 'sort' => $sort];
                    }
                    $totalUkuranOrder[$nama]['qty'] += $sq->quantity;
                }
            }
            uasort($totalUkuranOrder, fn ($a, $b) => $a['sort'] <=> $b['sort']);
        @endphp
        @if(!empty($totalUkuranOrder))
        <div class="divider divider-tight"></div>
        <table class="table-compact">
            @if(!empty($totalUkuranOrder))
            <tr><td>Total Ukuran</td><td class="right">{{ collect($totalUkuranOrder)->map(fn ($v, $k) => "{$k}: {$v['qty']}")->implode(', ') }}</td></tr>
            @endif
        </table>
        @endif

        <div class="divider divider-tight"></div>

        @php
            // Uang yang diserahkan pelanggan dan kembaliannya tersimpan per pembayaran; nominal pembayaran
            // (Deposit/DP) tetap sebesar yang masuk kas.
            $kembaliJersey = (float) $order->payments()->sum('kembalian');
            $uangDiterimaJersey = (float) $order->payments()->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(uang_diterima, jumlah)'));
            $sisaJersey = max(0, (float) $order->total - (float) $order->deposit);
        @endphp
        <table class="table-compact ringkas-jersey">
            <tr><td class="l">Diskon</td><td class="t">:</td><td class="v">Rp {{ number_format($order->discount, 0, ',', '.') }}</td></tr>
            <tr><td class="l">Biaya Tambahan</td><td class="t">:</td><td class="v">Rp {{ number_format($order->additional_charge, 0, ',', '.') }}</td></tr>
            <tr class="tebal"><td class="l">TOTAL</td><td class="t">:</td><td class="v">Rp {{ number_format($order->total, 0, ',', '.') }}</td></tr>
            <tr><td class="l">Deposit/DP</td><td class="t">:</td><td class="v">Rp {{ number_format($order->deposit, 0, ',', '.') }}</td></tr>
            @if($kembaliJersey > 0)
            <tr><td class="l">Uang Diterima</td><td class="t">:</td><td class="v">Rp {{ number_format($uangDiterimaJersey, 0, ',', '.') }}</td></tr>
            <tr><td class="l">Kembali</td><td class="t">:</td><td class="v">Rp {{ number_format($kembaliJersey, 0, ',', '.') }}</td></tr>
            @endif
            <tr class="tebal"><td class="l">Sisa Tagihan</td><td class="t">:</td><td class="v">Rp {{ number_format($sisaJersey, 0, ',', '.') }}</td></tr>
        </table>

        @if($order->notes)
        <div class="divider divider-tight"></div>
        <div class="catatan">{{ $order->notes }}</div>
        @endif

        <div class="signature">
            <div><div class="line">Pemesan</div></div>
            <div><div class="line">Admin</div></div>
        </div>
    </div>

    <div class="no-print">
        <button onclick="window.print()"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px" aria-hidden="true"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z"/></svg> Cetak</button>
    </div>
</body>
</html>
