<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Penawaran {{ $quotation->nomor }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/fonts/inter.css') }}">
    <style>
        body { font-family: 'Inter', Arial, Helvetica, sans-serif; color: #1E2A4A; margin: 32px; font-size: 13px; line-height: 1.5; }
        h1 { font-size: 20px; margin: 0 0 4px; } .muted { color: #5D6A85; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border-bottom: 1px solid #B9C3DD; padding: 8px 6px; text-align: left; vertical-align: top; }
        th { background: #F5F8FF; font-size: 13px; font-weight: 600; }
        .r { text-align: right; } .total td { font-weight: bold; border-bottom: 0; }
        .kop { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1E2A4A; padding-bottom: 12px; }
        .surat p { margin: 12px 0 0; }
        .perihal { margin-top: 16px; } .perihal td { border: 0; padding: 1px 12px 1px 0; }
        .ketentuan { margin: 8px 0 0; padding-left: 18px; } .ketentuan li { margin-bottom: 3px; }
        .ttd { display: flex; justify-content: space-between; gap: 40px; margin-top: 36px; page-break-inside: avoid; }
        .ttd > div { width: 45%; } .ttd .ruang { height: 64px; } .ttd .garis { border-top: 1px solid #1E2A4A; padding-top: 4px; }
        .aksi { margin-bottom: 16px; } @media print { .aksi { display: none; } body { margin: 12mm; } }
    </style>
</head>
<body>
    @php $app = \App\Models\AppSetting::current(); @endphp
    <div class="aksi"><button onclick="window.print()">Cetak / Simpan PDF</button></div>

    <div class="kop">
        <div>
            <h1>{{ $app->nama_aplikasi }}</h1>
            <div class="muted">{{ $app->alamat }}<br>{{ $app->telepon }}{{ $app->email ? ' | ' . $app->email : '' }}</div>
        </div>
        <div style="text-align:right">
            <h1>PENAWARAN</h1>
            <div><b>{{ $quotation->nomor }}</b></div>
            <div class="muted">Tanggal {{ $quotation->tanggal->locale('id')->translatedFormat('j F Y') }}<br>Berlaku sampai {{ $quotation->berlaku_sampai->locale('id')->translatedFormat('j F Y') }}</div>
        </div>
    </div>

    @php
        // Kalimat surat disusun dari isi penawaran ini sendiri (jumlah item, jenis, masa berlaku),
        // bukan teks tetap, supaya yang tercetak selalu cocok dengan dokumennya.
        $jumlahItem = $quotation->items->count();
        $adaProduk = $quotation->items->contains('tipe', 'Produk');
        $adaJasa = $quotation->items->contains('tipe', 'Jasa');
        $jenis = $adaProduk && $adaJasa ? 'pekerjaan cetak dan jasa' : ($adaJasa ? 'jasa' : 'pekerjaan cetak');
        $berlakuHari = (int) $quotation->tanggal->diffInDays($quotation->berlaku_sampai);
        $adaUkuran = $quotation->items->contains(fn ($it) => $it->p_ukuran > 0);
        $adaDesain = $quotation->items->contains(fn ($it) => $it->biaya_desain > 0);
        $pembuat = optional(optional($quotation->pembuat)->karyawan)->nama_karyawan ?: optional($quotation->pembuat)->username;
        $tgl = fn ($d) => $d->locale('id')->translatedFormat('j F Y');
    @endphp

    <table class="perihal">
        <tr><td>Nomor</td><td>: {{ $quotation->nomor }}</td></tr>
        <tr><td>Perihal</td><td>: Penawaran harga {{ $jenis }}</td></tr>
    </table>

    <div class="surat">
        <p>Kepada Yth.<br><b>{{ $quotation->nama_pelanggan }}</b>{{ $quotation->telepon ? ' (' . $quotation->telepon . ')' : '' }}<br>di tempat</p>
        <p>Dengan hormat,</p>
        <p>Menindaklanjuti permintaan Bapak/Ibu, bersama surat ini kami sampaikan penawaran harga untuk {{ $jumlahItem }} item {{ $jenis }} dengan rincian sebagai berikut.</p>
    </div>

    <table>
        <thead>
            <tr><th>#</th><th>Item</th><th class="r">Qty</th><th class="r">Harga</th><th class="r">Finishing</th><th class="r">Desain</th><th class="r">Subtotal</th></tr>
        </thead>
        <tbody>
            @foreach($quotation->items as $i => $it)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td><b>{{ $it->nama_item }}</b>@if($it->tipe === 'Produk' && $it->p_ukuran > 0) <span class="muted">{{ rtrim(rtrim($it->p_ukuran, '0'), '.') }} x {{ rtrim(rtrim($it->l_ukuran, '0'), '.') }} cm</span>@endif
                    @if($it->catatan)<div class="muted">{{ $it->catatan }}</div>@endif</td>
                <td class="r">{{ angka($it->qty, 2) }}</td>
                <td class="r">{{ number_format($it->harga_satuan, 0, ',', '.') }}</td>
                <td class="r">{{ $it->biaya_finishing > 0 ? number_format($it->biaya_finishing, 0, ',', '.') : '-' }}</td>
                <td class="r">{{ $it->biaya_desain > 0 ? number_format($it->biaya_desain, 0, ',', '.') : '-' }}</td>
                <td class="r">{{ number_format($it->subtotal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="6" class="r">Subtotal</td><td class="r">{{ number_format($quotation->subtotal, 0, ',', '.') }}</td></tr>
            @if($quotation->diskon > 0)<tr><td colspan="6" class="r">Diskon</td><td class="r">- {{ number_format($quotation->diskon, 0, ',', '.') }}</td></tr>@endif
            <tr class="total"><td colspan="6" class="r">TOTAL</td><td class="r">Rp {{ number_format($quotation->total, 0, ',', '.') }}</td></tr>
        </tfoot>
    </table>

    <p style="margin-top:12px"><i>Terbilang: {{ \App\Support\Terbilang::rupiah($quotation->total) }}.</i></p>

    @if($quotation->catatan)<p style="margin-top:16px"><b>Catatan:</b><br>{!! nl2br(e($quotation->catatan)) !!}</p>@endif

    <div class="surat">
        <p><b>Ketentuan</b></p>
        <ol class="ketentuan">
            <li>Penawaran ini berlaku sampai {{ $tgl($quotation->berlaku_sampai) }}{{ $berlakuHari > 0 ? ' (' . $berlakuHari . ' hari sejak tanggal surat)' : '' }}. Setelah tanggal itu harga dapat berubah.</li>
            <li>Harga dihitung untuk {{ $adaUkuran ? 'ukuran, jumlah, dan bahan' : 'jumlah dan bahan' }} yang tertulis di atas. Perubahan pada salah satunya mengubah harga.</li>
            @if($adaProduk)<li>{{ $adaDesain ? 'Biaya desain sudah tercantum pada rincian.' : 'Harga belum termasuk biaya desain; berkas siap cetak disediakan pemesan.' }}</li>@endif
            <li>Pengerjaan dimulai setelah penawaran ini disetujui dan nota pesanan diterbitkan.</li>
        </ol>
        <p>Demikian penawaran ini kami sampaikan. Untuk persetujuan atau penyesuaian, Bapak/Ibu dapat menghubungi kami di {{ $app->telepon ?: 'alamat di atas' }}.</p>
    </div>

    <div class="ttd">
        <div>
            <div>{{ \Illuminate\Support\Str::afterLast((string) $app->alamat, ', ') ?: '' }}{{ $app->alamat ? ', ' : '' }}{{ $tgl($quotation->tanggal) }}<br>Hormat kami,</div>
            <div class="ruang"></div>
            <div class="garis"><b>{{ $pembuat ?: $app->nama_aplikasi }}</b><br><span class="muted">{{ $app->nama_aplikasi }}</span></div>
        </div>
        <div>
            <div><br>Disetujui oleh,</div>
            <div class="ruang"></div>
            <div class="garis"><b>{{ $quotation->nama_pelanggan }}</b><br><span class="muted">Tanda tangan dan tanggal</span></div>
        </div>
    </div>
</body>
</html>
