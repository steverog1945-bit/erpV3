<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Faktur Pembelian {{ $pembelian->no_faktur }}</title>
    @include('partials.print-style')
    <style>
        {{-- Ukuran kertas mengikuti pilihan: A4 (210x297mm) atau Continuous Form 9.5x11 inci (kertas sambung dot-matrix) --}}
        @page { size: {{ $format === 'continuous' ? '241mm 279mm' : 'A4' }}; margin: {{ $format === 'continuous' ? '8mm 10mm' : '12mm' }}; }
        .sheet { max-width: {{ $format === 'continuous' ? '221mm' : '186mm' }}; }
        body { padding: 0 {{ $format === 'continuous' ? '0' : '4px' }}; }
        @if($format === 'continuous')
        {{-- Continuous form = printer dot-matrix: hitam pekat, font monospace, tanpa warna/latar --}}
        body, .sheet, table, th, td { font-family: 'Courier New', Courier, monospace !important; color: #000 !important; background: #fff !important; }
        th { border-bottom: 1px solid #000 !important; }
        .print-header h1, .print-header .company-info, .print-header .doc-title { color: #000 !important; }
        .divider { border-top: 1px dashed #000 !important; }
        @endif
        .info td { padding: 2px 4px; }
        .info td.label { width: 22%; color: #4A5B53; }
        .info td.sep { width: 2%; }
        .item-table th, .item-table td { padding: 5px 6px; border-bottom: 1px solid #E2EAE4; }
        .item-table tfoot td { border-bottom: none; }
        .ringkas { width: 46%; margin-left: auto; }
        .ringkas td { padding: 2px 4px; }
        .toolbar { text-align: center; margin-bottom: 18px; padding: 12px; background: #FAFBF9; border: 1px solid #E2EAE4; border-radius: 12px; }
        .toolbar .pilih { display: inline-flex; gap: 6px; margin-right: 12px; vertical-align: middle; }
        .toolbar .pilih a { padding: 8px 16px; border-radius: 10px; font-size: 12px; font-weight: 700; text-decoration: none; color: #3F4F47; background: #fff; border: 1px solid #E2EAE4; }
        .toolbar .pilih a.aktif { background: #EFF4FF; color: #2563EB; border-color: #DDE9FF; }
        .toolbar .info-kertas { display: block; margin-top: 8px; font-size: 11px; color: #94a3b8; }
        @media print { .toolbar { display: none !important; } }
    </style>
</head>
<body>
    {{-- Pilihan format cetak (tidak ikut tercetak) --}}
    <div class="toolbar no-print">
        <span class="pilih">
            <a href="{{ route('pembelian-bahan.cetak', [$pembelian->id_pembelian, 'a4']) }}" class="{{ $format === 'a4' ? 'aktif' : '' }}" title="Kertas A4 biasa (210 x 297 mm)">Kertas A4</a>
            <a href="{{ route('pembelian-bahan.cetak', [$pembelian->id_pembelian, 'continuous']) }}" class="{{ $format === 'continuous' ? 'aktif' : '' }}" title="Kertas sambung dot-matrix (9,5 x 11 inci)">Continuous Form</a>
        </span>
        <button onclick="window.print()" style="margin:0"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px" aria-hidden="true"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z"/></svg> Cetak</button>
        <span class="info-kertas">Format terpilih: <b>{{ $format === 'continuous' ? 'Continuous Form 9,5 x 11 inci' : 'A4 (210 x 297 mm)' }}</b> - atur juga ukuran kertas yang sama di dialog cetak browser.</span>
    </div>

    <div class="sheet">
        @include('partials.print-header', ['docTitle' => 'Faktur Pembelian Bahan'])

        <table class="info">
            <tr><td class="label">No. Faktur</td><td class="sep">:</td><td><b>{{ $pembelian->no_faktur }}</b></td><td class="label">Tanggal</td><td class="sep">:</td><td>{{ \Carbon\Carbon::parse($pembelian->tgl_transaksi)->format('d/m/Y') }}</td></tr>
            <tr><td class="label">Pemasok</td><td class="sep">:</td><td>{{ $pembelian->kedatangan->supplier->nama_supplier ?? 'Umum' }}</td><td class="label">Ref. Surat Jalan</td><td class="sep">:</td><td>{{ $pembelian->kedatangan->no_surat_jalan ?? '-' }}</td></tr>
            <tr>
                <td class="label">Metode Bayar</td><td class="sep">:</td><td>{{ $pembelian->tipe_pembelian === 'Tempo' ? 'Tempo / Kredit' : 'Cash / Tunai' }}</td>
                <td class="label">{{ $pembelian->tipe_pembelian === 'Tempo' ? 'Jatuh Tempo' : 'Status' }}</td><td class="sep">:</td>
                <td>{{ $pembelian->tipe_pembelian === 'Tempo' ? ($pembelian->jatuh_tempo_tgl ? \Carbon\Carbon::parse($pembelian->jatuh_tempo_tgl)->format('d/m/Y') : '-') : ($pembelian->status_pembayaran ?? 'Lunas') }}</td>
            </tr>
        </table>

        <div class="divider"></div>

        <table class="item-table">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th>Nama Bahan</th>
                    <th class="right" style="width: 12%;">Qty</th>
                    <th class="right" style="width: 15%;">Harga</th>
                    <th class="right" style="width: 12%;">Diskon</th>
                    <th class="right" style="width: 12%;">Potongan</th>
                    <th class="right" style="width: 16%;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pembelian->detail as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->bahan->nama_bahan ?? '-' }} <span style="color:#94a3b8">({{ $item->bahan->kode_bahan ?? '-' }})</span></td>
                    <td class="right">{{ angka($item->qty, 2) }} {{ $item->bahan->satuan_stok ?? '' }}</td>
                    <td class="right">{{ number_format($item->harga, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format($item->diskon, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format($item->potongan, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="divider"></div>

        @php
            $subtotalItem = (float) $pembelian->detail->sum('subtotal');
            $ppn = $pembelian->include_pajak ? round($subtotalItem * 0.11, 2) : 0;
        @endphp
        <table class="ringkas">
            <tr><td>Subtotal item</td><td class="right">{{ number_format($subtotalItem, 0, ',', '.') }}</td></tr>
            @if($ppn > 0)<tr><td>PPN 11%</td><td class="right">{{ number_format($ppn, 0, ',', '.') }}</td></tr>@endif
            @if((float) $pembelian->ongkir > 0)<tr><td>Ongkir</td><td class="right">{{ number_format($pembelian->ongkir, 0, ',', '.') }}</td></tr>@endif
            @if((float) $pembelian->biaya_layanan > 0)<tr><td>Biaya layanan</td><td class="right">{{ number_format($pembelian->biaya_layanan, 0, ',', '.') }}</td></tr>@endif
            @if((float) $pembelian->voucher > 0)<tr><td>Voucher / diskon platform</td><td class="right">-{{ number_format($pembelian->voucher, 0, ',', '.') }}</td></tr>@endif
            <tr class="total-row"><td>Grand Total</td><td class="right">{{ number_format($pembelian->grand_total, 0, ',', '.') }}</td></tr>
            @if($pembelian->tipe_pembelian === 'Tempo')
            <tr><td>Sisa hutang</td><td class="right">{{ number_format($sisa, 0, ',', '.') }}</td></tr>
            @endif
        </table>

        <div class="signature">
            <div><div class="line">Dibuat oleh</div></div>
            <div><div class="line">Disetujui</div></div>
        </div>

        <p class="footer-note">Dicetak {{ now()->format('d/m/Y H:i') }} - {{ \App\Models\AppSetting::current()->nama_aplikasi }}</p>
    </div>
</body>
</html>
