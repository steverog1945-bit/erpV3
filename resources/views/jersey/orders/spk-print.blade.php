<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>SPK Jersey {{ $order->order_number }} - {{ $line->nama_barang }}</title>
    @php
        $appSettingSpk = \App\Models\AppSetting::current();
        $sizeMap = $line->sizeQuantities
            ->filter(fn ($sq) => $sq->quantity > 0)
            ->sortBy(fn ($sq) => $sq->size->sort_order ?? 0)
            ->mapWithKeys(fn ($sq) => [$sq->size->name ?? '-' => $sq->quantity]);
    @endphp
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
                    <span>Periode {{ \Carbon\Carbon::parse($order->order_date)->translatedFormat('F Y') }}</span>
                </div>
            </div>
            <div class="doc-badge">
                <span class="doc-label">Surat Perintah Kerja</span>
                <span class="doc-code">{{ $order->order_number }}-{{ $line->id }}</span>
            </div>
        </div>

        <div class="hero">
            <div class="hero-thumb">
                @if($line->thumbnail)
                    @if(\App\Support\BerkasDesain::bisaDitampilkan($line->thumbnail))
                        <img src="{{ asset('storage/' . $line->thumbnail) }}" alt="Thumbnail Desain">
                    @else
                        {{-- Berkas desain yang bukan gambar (CDR, AI, EPS):
                             peramban tidak bisa menampilkannya dan server
                             tidak punya konverter, jadi yang dicetak ke SPK
                             adalah format berkasnya. PDF tidak masuk sini -
                             PDF sudah dirender jadi gambar di peramban. --}}
                        <img src="{{ asset('storage/default-thumbnail.png') }}" alt="Berkas desain" style="max-width: 200px; max-height: 200px; opacity: 0.55;">
                        <span class="placeholder" style="padding: 4px 0 0;">
                            <strong>{{ strtoupper(\App\Support\BerkasDesain::ekstensi($line->thumbnail)) }}</strong><br>
                            <small>Berkas desain - sumber file ada di server, dibuka tim produksi</small>
                        </span>
                    @endif
                @else
                    <span class="placeholder">Belum ada thumbnail desain</span>
                @endif
            </div>
            <div class="hero-info">
                <h1>{{ $line->nama_barang }}</h1>
                <p class="sub">{{ $line->productType->name ?? '-' }} &middot; {{ $line->category->name ?? '-' }} &middot; {{ $line->material->name ?? $line->bahan ?? '-' }}</p>
                <div class="info-grid">
                    <div class="item"><span>Pemesan</span><strong>{{ $order->konsumen->nama_konsumen ?? $order->customer_name }}</strong></div>
                    <div class="item"><span>Tim</span><strong>{{ $order->team_name ?? '-' }}</strong></div>
                    <div class="item"><span>Tanggal Masuk</span><strong>{{ \Carbon\Carbon::parse($order->order_date)->format('d/m/Y') }}</strong></div>
                    <div class="item"><span>Tanggal Beres</span><strong>{{ $order->deadline ? \Carbon\Carbon::parse($order->deadline)->format('d/m/Y') : '-' }}</strong></div>
                    <div class="item"><span>Desainer</span><strong>{{ $order->designer_name ?? '-' }}</strong></div>
                    <div class="item"><span>Total Qty</span><strong class="accent">{{ angka($line->qty, 4) }} pcs</strong></div>
                </div>
            </div>
        </div>

        @if($line->keterangan)
        <div class="keterangan-box">
            <p>Keterangan</p>
            <pre>{{ $line->keterangan }}</pre>
        </div>
        @endif

        <div class="section">
            <div class="section-title">Rincian Ukuran</div>
            @if($sizeMap->isNotEmpty())
            <div class="size-chips">
                @foreach($sizeMap as $ukuran => $qtyUkuran)
                <div class="chip">
                    <span class="chip-size">{{ $ukuran }}</span>
                    <span class="chip-qty">{{ $qtyUkuran }}</span>
                </div>
                @endforeach
                <div class="chip-total">TOTAL {{ angka($line->qty, 4) }} PCS</div>
            </div>
            @else
            <p class="empty-note">Belum ada rincian ukuran untuk barang ini.</p>
            @endif
        </div>

        @if($line->items->isNotEmpty())
        <div class="section">
            <div class="section-title">Daftar Nama & Nomor Punggung</div>
            <table class="roster">
                <thead>
                    <tr><th>No</th><th>Nama</th><th>No. Punggung</th><th>Ukuran</th><th>Gender</th><th>Lengan</th><th>Kerah</th></tr>
                </thead>
                <tbody>
                    @php
                        // Urutkan sesuai sort_order master ukuran (S, M, L, XL, ...)
                        // alih-alih urutan input - konsisten dengan tabel pemain di
                        // halaman show; fallback urutan AB bila master tidak ada.
                        $itemsSorted = $line->items->sortBy([
                            ['sort_size_order', 'asc'],
                            ['player_name', 'asc'],
                        ], SORT_NATURAL | SORT_FLAG_CASE)->values();
                    @endphp
                    @foreach($itemsSorted as $i => $item)
                    <tr>
                        <td class="num">{{ $i + 1 }}</td>
                        <td>{{ $item->player_name }}</td>
                        <td>{{ $item->player_number ?? '-' }}</td>
                        <td>{{ $item->size }}</td>
                        <td>{{ $item->gender }}</td>
                        <td>{{ $item->sleeve }}</td>
                        <td>{{ $item->collar ?? '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <div class="section">
            <div class="section-title">Estimasi Kebutuhan Bahan <span class="note">(estimasi otomatis, bukan hasil timbang riil)</span></div>
            @php
                // Rib adalah bahan tersendiri (kain rib), bukan kain jersey. Kerah dipotong dari kain jersey.
                // Keduanya tidak boleh dijumlahkan: total di bawah hanya kain jersey.
                $adalahRib = fn ($m) => (bool) preg_match('/^rib\b/i', trim((string) $m->nama_barang));
                $bahanKain = $line->materials->reject($adalahRib)->values();
                $bahanRib = $line->materials->filter($adalahRib)->values();
                $barisBahan = function ($m) {
                    return [
                        $m->penggunaan_meter !== null ? number_format($m->penggunaan_meter, 2, ',', '.') . ' m' : '-',
                        $m->penggunaan_kg !== null ? \App\Services\JerseyMaterialEstimationService::formatBeratGram($m->penggunaan_kg * 1000) : '-',
                    ];
                };
            @endphp
            <table class="material">
                <thead>
                    <tr><th>Komponen</th><th class="num">Perkiraan Meter</th><th class="num">Perkiraan Berat</th></tr>
                </thead>
                <tbody>
                    @if($line->materials->isEmpty())
                    <tr class="empty-row"><td colspan="3" class="empty-note">Belum ada data estimasi bahan untuk ukuran pada order ini.</td></tr>
                    @endif
                    @if($bahanKain->isNotEmpty())
                    <tr class="kelompok"><td colspan="3">Kain jersey</td></tr>
                    @foreach($bahanKain as $m)
                    @php [$meter, $berat] = $barisBahan($m); @endphp
                    <tr><td>{{ $m->nama_barang }}</td><td class="num">{{ $meter }}</td><td class="num">{{ $berat }}</td></tr>
                    @endforeach
                    <tr class="total-row">
                        <td>Total kain jersey</td>
                        <td class="num">{{ number_format($bahanKain->sum('penggunaan_meter'), 2, ',', '.') }} m</td>
                        <td class="num">{{ \App\Services\JerseyMaterialEstimationService::formatBeratGram($bahanKain->sum('penggunaan_kg') * 1000) }}</td>
                    </tr>
                    @endif
                    @if($bahanRib->isNotEmpty())
                    <tr class="kelompok"><td colspan="3">Rib (bahan terpisah, tidak termasuk total kain)</td></tr>
                    @foreach($bahanRib as $m)
                    @php [$meter, $berat] = $barisBahan($m); @endphp
                    <tr><td>{{ $m->nama_barang }}</td><td class="num">{{ $meter }}</td><td class="num">{{ $berat }}</td></tr>
                    @endforeach
                    @endif
                </tbody>
            </table>
        </div>

        <div class="footer-strip">
            <span class="dot"></span>
            <span>Wajib tanda tangan di setiap tahap proses dan SPK wajib dikembalikan bersama barangnya.</span>
        </div>

        <div class="signature">
            <div><p class="role">Penerima</p><div class="line"></div></div>
            <div><p class="role">{{ $appSettingSpk->nama_aplikasi }}</p><div class="line"></div></div>
        </div>
    </div>

    <div class="no-print">
        <button onclick="window.print()">Cetak SPK</button>
    </div>
</body>
</html>
