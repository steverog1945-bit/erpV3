<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>SPK #{{ $detail->id_detail }} - {{ $detail->nama_produk }}</title>
    @php $appSettingSpk = \App\Models\AppSetting::current(); @endphp
    @include('partials.spk-print-style')
</head>
<body>
    <div class="sheet">
        <div class="topbar">
            <div class="brand">
                @if($appSettingSpk->logo_path)
                    <img src="{{ asset('storage/' . $appSettingSpk->logo_path) }}" alt="Logo">
                @endif
                <div>
                    <strong>{{ $appSettingSpk->nama_aplikasi }}</strong>
                    <span>{{ $detail->divisi ?? 'Produksi' }} &middot; {{ \Carbon\Carbon::parse($detail->tgl_order)->translatedFormat('F Y') }}</span>
                </div>
            </div>
            <div class="doc-badge">
                <span class="doc-label">Surat Perintah Kerja</span>
                <span class="doc-code">SPK #{{ $detail->id_detail }} &middot; {{ $detail->nomor_nota }}</span>
            </div>
        </div>

        <div class="hero">
            <div class="hero-thumb">
                @if($detail->thumbnail)
                    @if(\App\Support\BerkasDesain::bisaDitampilkan($detail->thumbnail))
                        <img src="{{ asset('storage/' . $detail->thumbnail) }}" alt="Gambar desain">
                    @else
                        {{-- Berkas desain yang bukan gambar (CDR, AI, EPS):
                             peramban tidak bisa menampilkannya, dan server
                             tidak punya konverter (tidak ada ImageMagick/
                             Ghostscript/LibreOffice di sini), jadi yang
                             dicetak ke SPK adalah format berkasnya. Sumber
                             filenya tetap tersimpan di server untuk dibuka
                             tim produksi - nama desainnya sudah tercetak di
                             judul SPK di sebelah kanan.

                             PDF tidak masuk sini: PDF sudah dirender jadi
                             gambar di peramban (pdf.js) sebelum diunggah. --}}
                        <img src="{{ asset('storage/default-thumbnail.png') }}" alt="Berkas desain ({{ strtoupper(\App\Support\BerkasDesain::ekstensi($detail->thumbnail)) }})" style="max-width: 200px; max-height: 200px; opacity: 0.55;">
                        <span class="placeholder" style="padding: 4px 0 0;">
                            <strong>{{ strtoupper(\App\Support\BerkasDesain::ekstensi($detail->thumbnail)) }}</strong><br>
                            <small>Berkas desain - sumber file ada di server, dibuka tim produksi</small>
                        </span>
                    @endif
                @else
                    <span class="placeholder">Belum ada gambar desain</span>
                @endif
            </div>
            <div class="hero-info">
                <h1>{{ $detail->nama_file }}</h1>
                <p class="sub">{{ $detail->nama_produk }}@if($detail->status_urgent) &middot; URGENT @endif</p>
                <div class="info-grid">
                    <div class="item"><span>Pemesan</span><strong>{{ $detail->nama_konsumen }}</strong></div>
                    <div class="item"><span>No. Telepon</span><strong>{{ $detail->telepon_konsumen ?: '-' }}</strong></div>
                    <div class="item"><span>Tanggal Masuk</span><strong>{{ \Carbon\Carbon::parse($detail->tgl_order)->format('d/m/Y H:i') }}</strong></div>
                    <div class="item"><span>Desainer / Petugas Input</span><strong>{{ $detail->pembuat_nota ?? '-' }}@if($detail->role_pembuat) <small class="info-catatan">({{ $detail->role_pembuat }})</small>@endif</strong></div>
                    @if($detail->metode_hitung === 'ukuran')
                    <div class="item"><span>Ukuran Cetak</span><strong>{{ angka($detail->p_ukuran, 2) }} x {{ angka($detail->l_ukuran, 2) }} cm</strong></div>
                    @endif
                    <div class="item"><span>Qty Order</span><strong class="accent">{{ angka($detail->qty, 4) }}</strong></div>
                    <div class="item"><span>Qty Cetak</span><strong>{{ $detail->qty_cetak ?? '-' }}</strong></div>
                    <div class="item"><span>Urgensi</span><strong>{{ $detail->status_urgensi ?? 'Normal' }}</strong></div>
                </div>
            </div>
        </div>

        @if($detail->catatan_produksi)
        <div class="keterangan-box">
            <p>Catatan Produksi</p>
            <pre>{{ $detail->catatan_produksi }}</pre>
        </div>
        @endif

        <div class="section">
            <div class="section-title">Kebutuhan Bahan <span class="note">(dihitung otomatis dari BOM produk, bukan hasil timbang riil)</span></div>
            <table class="material">
                <thead><tr><th>Bahan</th><th class="num">Pemakaian</th><th class="num">Perkiraan Panjang Bahan</th></tr></thead>
                <tbody>
                    @forelse($bahan as $b)
                    <tr>
                        <td>{{ $b->nama }}</td>
                        <td class="num">{{ angka($b->qty, 3) }} {{ $b->satuan }}</td>
                        <td class="num">{{ $b->panjang_m !== null ? angka($b->panjang_m, 2) . ' m' : '-' }}</td>
                    </tr>
                    @empty
                    <tr class="empty-row"><td colspan="3" class="empty-note">Belum ada BOM bahan untuk produk ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Finishing</div>
            <table class="material">
                <thead><tr><th>Finishing</th><th>Spek</th><th class="num">Jumlah Pakai</th><th>Catatan</th></tr></thead>
                <tbody>
                    @forelse($finishingItems as $fin)
                    <tr>
                        <td>{{ $fin->nama_finishing }}</td>
                        <td>{{ $fin->status_finishing }}</td>
                        <td class="num">{{ $fin->metode_hitung === 'quantity' && $fin->qty_pakai ? angka((float) $fin->qty_pakai, 2) : '-' }}</td>
                        <td>{{ $fin->catatan ?: '-' }}</td>
                    </tr>
                    @empty
                    <tr class="empty-row"><td colspan="4" class="empty-note">Tanpa finishing.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="kode-blok"><div id="qr-spk" class="kode-qr"></div><div class="kode-trx">TRX{{ $detail->id_transaksi }}</div><div class="scan-caption">Scan dengan kamera HP untuk membuka pembayaran</div></div>

        <div class="footer-strip">
            <span class="dot"></span>
            <span>Wajib tanda tangan di setiap tahap proses dan SPK wajib dikembalikan bersama barangnya.</span>
        </div>

        <div class="signature">
            <div><p class="role">Desainer</p><div class="line"></div></div>
            <div><p class="role">Produksi</p><div class="line"></div></div>
            <div><p class="role">Pemeriksa / QC</p><div class="line"></div></div>
        </div>
    </div>

    <div class="no-print">
        <button onclick="window.print()">Cetak SPK</button>
    </div>

    <script src="{{ asset('vendor/qrcode-generator/qrcode.min.js') }}"></script>
    <script>
        // QR berisi ALAMAT halaman pembayaran nota: dipindai kamera HP biasa langsung membuka form bayar (login dulu bila belum).
        (function () {
            const qr = qrcode(0, 'M');
            qr.addData(@json(\App\Models\AppSetting::urlAkses() . '/kasir/bayar-form/' . $detail->id_transaksi));
            qr.make();
            document.getElementById('qr-spk').innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
            const svg = document.querySelector('#qr-spk svg'); if (svg) { svg.style.width = '150px'; svg.style.height = '150px'; }
        })();
    </script>
</body>
</html>
