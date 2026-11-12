<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota {{ $transaksi->nomor_nota }}</title>
    @include('partials.print-style')
    <style>
        /* Tata letak mengikuti contoh struk dari pemilik: label dan titik dua sejajar, item dua baris
           (nama, lalu harga satuan dengan total di kanan), ringkasan rata kanan, garis pemisah penuh.
           Huruf tetap dari print-style (tidak diubah).

           Keterbacaan cetak: printer thermal hanya mengenal hitam atau putih. Teks abu-abu dicetak sebagai
           pola titik (dithering) sehingga terlihat putus-putus. Karena itu SEMUA teks nota hitam pekat,
           garis pemisah hitam solid (bukan putus-putus), dan ukuran huruf thermal dinaikkan satu tingkat. */
        .nota, .nota * { color: #000 !important; }
        .nota { font-size: {{ $format === 'thermal' ? '12px' : '13px' }}; line-height: 1.4; }
        .nota .print-header .company-info, .nota .print-header .doc-title { font-size: {{ $format === 'thermal' ? '12px' : '13px' }}; }
        .nota .garis { border-top: 1px solid #000; margin: {{ $format === 'thermal' ? '5px' : '7px' }} 0; }
        .nota table { width: 100%; border-collapse: collapse; font-size: inherit; }
        .nota td, .nota th { padding: 1px 0; vertical-align: top; }

        /* Informasi nota: label | : | isi */
        .nota .info td.l { width: 30%; white-space: nowrap; }
        .nota .info td.t { width: 12px; }

        /* Item */
        .nota .item th { font-size: inherit; font-weight: 500; text-align: left; border-bottom: 1px solid #000; padding-bottom: 2px; }
        .nota .item th.r, .nota .item td.r { text-align: right; }
        .nota .item td.qty { width: 14%; white-space: nowrap; font-weight: 500; }
        .nota .item td.nama { font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 1px; }
        .nota .item td.rinci { padding-bottom: 3px; }
        .nota .item { table-layout: fixed; }

        /* Ringkasan: label rata kanan, titik dua, nilai rata kanan */
        .nota .ringkas td.l { text-align: right; width: 62%; }
        .nota .ringkas td.t { width: 12px; text-align: center; }
        .nota .ringkas td.v { text-align: right; white-space: nowrap; }
        .nota .ringkas tr.tebal td { font-weight: 500; }

        .nota .deposit td.v { text-align: right; white-space: nowrap; font-weight: 500; }

        /* Status & rincian uang diterima: diberi jarak dari blok ringkasan
           supaya tidak terlihat sebagai satu tabel panjang tanpa judul. */
        .nota .status-nota { margin-top: 6px; }
        .nota .diterima { margin-top: 4px; }
        .nota .diterima td.l { font-size: 11px; }
        .nota .penutup { text-align: center; margin-top: 6px; }
        .nota .footer-note { color: #000 !important; font-size: inherit; }
    </style>
</head>
<body>
    <div class="sheet nota">
        @include('partials.print-header', ['docTitle' => 'Nota Penjualan'])

        <div class="garis"></div>

        <table class="info">
            <tr><td class="l">No. Nota</td><td class="t">:</td><td>{{ $transaksi->nomor_nota }}</td></tr>
            <tr><td class="l">Waktu</td><td class="t">:</td><td>{{ \Carbon\Carbon::parse($transaksi->tgl_order)->format('d/m/Y H:i:s') }}</td></tr>
            <tr><td class="l">Konsumen</td><td class="t">:</td><td>{{ $transaksi->nama_konsumen }}</td></tr>
            <tr><td class="l">Telp</td><td class="t">:</td><td>{{ $transaksi->telepon ?: '-' }}</td></tr>
            {{-- Label "Operator", bukan "Kasir". Yang mencatat order ini
                 adalah operator - struk yang ditulis "Kasir" sering
                 dikira sebagai nama orang yang menerima uang, padahal
                 belum tentu orang yang sama. --}}
            <tr><td class="l">Operator</td><td class="t">:</td><td>{{ $transaksi->nama_kasir ?? '-' }}</td></tr>
        </table>

        <div class="garis"></div>

        <table class="item">
            <thead>
                <tr><th style="width:14%">Qty</th><th>Produk</th><th class="r" style="width:32%">Total</th></tr>
            </thead>
            <tbody>
                {{-- UKURAN TIDAK DICETAK untuk produk ber-metode hitung 'ukuran'
                     (roll/banner). Ukuran yang diketik operator mengikuti ukuran
                     bahan, jadi bisa BERBEDA dari ukuran file asli - memprintingnya
                     membuat konsumen salah paham dan sempat dikomplain.

                     Hanya berlaku pada produk ber-metode 'ukuran' (product jadi
                     satuannya, operator yang menentukan Panjang/Lebar). Produk per
                     lembar (A3, foto, dll) tidak punya ukuran sama sekali di form,
                     jadi tidak ada yang perlu disembunyikan.

                     PENTING: jangan tulis teks "@" diikuti "php" di dalam
                     komentar Blade. Blade menyimpan blok PHP mentah SEBELUM
                     komentar dibuang, jadi teks "@"+"php" di sini ikut menelan
                     blok PHP asli yang ada di bawahnya - termasuk seluruh isi
                     loop dan penutup tabel. --}}
                @foreach($items as $item)
                    @php
                        $hargaSatuan = (float) $item->qty > 0 ? (float) $item->subtotal / (float) $item->qty : (float) $item->subtotal;
                        $ukuran = ($item->metode_hitung_produk ?? null) !== 'ukuran'
                            && $item->p_ukuran > 0 && $item->l_ukuran > 0
                            ? ' / ' . angka($item->p_ukuran, 1) . 'x' . angka($item->l_ukuran, 1) . ' cm'
                            : '';
                    @endphp
                    <tr>
                        {{-- Mode HYBRID minimum luas: penanda is_minimum_order
                             kini berada di baris ITEM (selisih digulung ke
                             subtotal baris terbesar kelompok), jadi qty tetap
                             qty aktual item - tidak ada lagi baris minimum
                             dengan qty 0/“—” di nota. --}}
                        <td class="qty">{{ angka($item->qty, 2) . 'x' }}</td>
                        <td class="nama" colspan="2" title="{{ $item->nama_file }}">{{ $item->nama_file }}</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td class="rinci">[ {{ number_format($hargaSatuan, 0, ',', '.') }}{{ $ukuran }} ]</td>
                        <td class="rinci r">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="garis"></div>

        {{-- Komponen biaya hanya yang lebih dari 0; baris Rp 0 hanya memanjangkan nota. --}}
        <table class="ringkas">
            @if($totalCetak > 0)
            <tr><td class="l">Total Cetak</td><td class="t">:</td><td class="v">{{ number_format($totalCetak, 0, ',', '.') }}</td></tr>
            @endif
            @if($totalDesain > 0)
            <tr><td class="l">Total Desain</td><td class="t">:</td><td class="v">{{ number_format($totalDesain, 0, ',', '.') }}</td></tr>
            @endif
            @if($totalFinishing > 0)
            <tr><td class="l">Total Finishing</td><td class="t">:</td><td class="v">{{ number_format($totalFinishing, 0, ',', '.') }}</td></tr>
            @endif
            {{-- Diskon tingkat nota (transaksi.diskon) — tampil hanya bila ada,
                 supaya nota rekonsiliasi: Cetak + Desain + Finishing − Diskon = Grand Total. --}}
            @if((float) ($transaksi->diskon ?? 0) > 0)
            <tr><td class="l">Diskon</td><td class="t">:</td><td class="v">-{{ number_format((float) $transaksi->diskon, 0, ',', '.') }}</td></tr>
            @endif
            <tr class="tebal"><td class="l">Grand Total</td><td class="t">:</td><td class="v">{{ number_format($totalTagihanAsli, 0, ',', '.') }}</td></tr>
            {{-- Pembulatan hanya tampil bila ada selisih; murni tampilan nota (lihat AppSetting::bulatkan()). --}}
            @if($pembulatan != 0)
            <tr><td class="l">Pembulatan</td><td class="t">:</td><td class="v">{{ ($pembulatan > 0 ? '+' : '-') . number_format(abs($pembulatan), 0, ',', '.') }}</td></tr>
            <tr class="tebal"><td class="l">Total Tagihan</td><td class="t">:</td><td class="v">{{ number_format($totalTagihanBulat, 0, ',', '.') }}</td></tr>
            @endif
            <tr><td class="l">Uang Diterima</td><td class="t">:</td><td class="v">{{ number_format($totalUangDiterima ?? $totalTerbayar, 0, ',', '.') }}</td></tr>
            <tr><td class="l">Krg. Bayar</td><td class="t">:</td><td class="v">{{ number_format($kurangBayar, 0, ',', '.') }}</td></tr>
            <tr><td class="l">Kembali</td><td class="t">:</td><td class="v">{{ number_format($kembali, 0, ',', '.') }}</td></tr>
        </table>

        {{-- STATUS PEMBAYARAN - dicetak eksplisit karena nota yang belum
             dibayar sama sekali sebelumnya tidak terlihat dari nominal:
             "Total Bayar 0" saja tidak menjelaskan apakah itu karena belum
             bayar atau karena tagihannya memang 0. --}}
        <table class="ringkas status-nota">
            {{-- Dicetak kapital: printer thermal huruf kecil mudah pecah, dan
                 "LUNAS" / "BELUM BAYAR" jauh lebih cepat terbaca sekilas
                 dibandingkan huruf campuran - status justru hal pertama
                 yang dicari pembaca nota. --}}
            <tr class="tebal"><td class="l">Status Bayar</td><td class="t">:</td><td class="v">{{ \Illuminate\Support\Str::upper($statusPembayaranNota) }}</td></tr>
            <tr><td class="l">Metode Bayar</td><td class="t">:</td><td class="v">{{ $metodePembayaranNota !== '' ? $metodePembayaranNota : '-' }}</td></tr>
        </table>

        {{-- RINCIAN UANG DITERIMA - satu baris per pembayaran yang benar-benar
             tercatat, nominal dan metodenya apa adanya. Baris "Total Bayar" di
             atas hanya menjumlahkannya, jadi tanpa rincian ini tidak ada yang
             bisa mencocokkan nominal nota dengan uang yang benar-benar masuk
             ke laci. --}}
        @if($penerimaanNota->isNotEmpty())
        <table class="ringkas diterima">
            <tr class="tebal"><td class="l">Uang Diterima</td><td class="t">:</td><td class="v"></td></tr>
            @foreach($penerimaanNota as $p)
            <tr>
                <td class="l">{{ \Carbon\Carbon::parse($p->tgl_bayar)->format('d/m/Y H:i') }} - {{ $p->metode }}</td>
                <td class="t">:</td>
                <td class="v">{{ number_format($p->uang_diterima ?? $p->nominal, 0, ',', '.') }}@if(($p->kembalian ?? 0) > 0) <span class="text-[9px]">(kembali {{ number_format($p->kembalian, 0, ',', '.') }})</span>@endif</td>
            </tr>
            @endforeach
        </table>
        @endif

        <div class="garis"></div>

        {{-- Saldo deposit selalu dicetak: pelanggan bisa melihat berapa yang terpakai di nota ini dan sisanya. --}}
        <table class="deposit">
            <tr><td>Penggunaan deposit pada trans ini</td><td class="v">: Rp {{ number_format($depositTerpakaiTransaksiIni, 0, ',', '.') }}</td></tr>
            <tr><td>Saldo deposit anda saat ini</td><td class="v">: Rp {{ number_format($saldoDepositSaatIni, 0, ',', '.') }}</td></tr>
        </table>

        {{-- nota_footer_text sudah disaring saat disimpan (lihat SettingAplikasiController::sanitizeFooterHtml());
             $footerNotaOverride khusus pratinjau di Pengaturan Aplikasi. --}}
        @php($footerNota = $footerNotaOverride ?? \App\Models\AppSetting::current()->nota_footer_text)
        @if($footerNota)
            <div class="garis"></div>
            <div class="footer-note" style="text-align: left;">{!! \App\Support\HtmlBersih::bersihkan($footerNota) !!}</div>
        @endif

        <div class="garis"></div>
        <p class="penutup">===== Terima Kasih =====</p>
    </div>

    <div class="no-print" style="display:flex;gap:8px;justify-content:center;margin-top:12px;">
        <button onclick="window.print()"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px" aria-hidden="true"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z"/></svg> Cetak</button>
        <a href="{{ route('kasir.kelola') }}" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;background:#64748b;color:#fff;font-size:13px;text-decoration:none;">← Kembali ke Monitoring Kasir</a>
    </div>
</body>
</html>
