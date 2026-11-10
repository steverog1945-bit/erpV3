@extends('layouts.app')

@section('content')
<style>
    /* ============================================================
       LEMBAR HALAMAN - panel solid dengan garis tepi. Warna hidup di
       variabel --lembar-* sehingga mode terang dan gelap memakai
       struktur yang sama, cukup ganti nilai variabelnya. Tanpa blur
       dan tanpa latar tembus pandang.
       Mengikuti design system: radius 8px (panel), 6px (input/chip),
       font-size 13px (body), 10px uppercase (label), font-weight 500/700.
       ============================================================ */
    .lembar {
        --lembar-bg: #ffffff;
        --lembar-hover: #f8f9fd;
        --lembar-border: #dfe4ec;
        --lembar-line: #edf0f5;
        --lembar-head: #f4f6fa;
        --lembar-ink: #1d2433;
        --lembar-muted: #5b6472;
        background: var(--lembar-bg);
        border: 1px solid var(--lembar-border);
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
        transition:
            background 220ms cubic-bezier(0.32, 0.72, 0, 1),
            border-color 220ms cubic-bezier(0.32, 0.72, 0, 1);
    }
    .lembar:hover { background: var(--lembar-hover); border-color: #cbd3e0; }
    html.dark .lembar {
        --lembar-bg: #111a2e;
        --lembar-hover: #16213a;
        --lembar-border: #26314a;
        --lembar-line: #1d2942;
        --lembar-head: #01434D;
        --lembar-ink: #F1F5F1;
        --lembar-muted: #94a3b8;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.30);
    }
    html.dark .lembar:hover { border-color: #0A4C57; }

    /* Kartu angka: mungil, satu fakta per kartu */
    .lembar-e {
        padding: 10px 14px 11px;
        border-radius: 6px;
        cursor: default;
    }
    .lembar-e .l { font-size: 10px; font-weight: 500; color: var(--lembar-muted); text-transform: uppercase; letter-spacing: 0.06em; line-height: 1.35; }
    .lembar-e .v { font-size: 13px; font-weight: 700; color: var(--lembar-ink); margin-top: 2px; font-variant-numeric: tabular-nums; letter-spacing: -0.01em; }
    .lembar-e .v small { font-size: 10px; font-weight: 600; }

    /* Panel berisi tabel */
    .lembar-p { padding: 12px 16px 8px; }
    /* Tabel lebar (mis. pemakaian bahan) menggulir di dalam kartunya sendiri di layar sempit, bukan menggeser seluruh halaman. */
    @media screen { .lembar-p { overflow-x: auto; } }
    .k-judul { font-size: 10px; font-weight: 500; color: var(--lembar-muted); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 8px; display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
    .k-ket { font-size: 12px; color: var(--lembar-muted); margin-bottom: 8px; }

    /* Tabel: rapat, tenang, baris bernafas */
    .t { width: 100%; border-collapse: collapse; }
    .t th { font-size: 13px; font-weight: 500; text-transform: uppercase; letter-spacing: 0; color: var(--lembar-muted); text-align: left; padding: 10px 16px; border-bottom: 1px solid var(--lembar-border); background: var(--lembar-head); white-space: nowrap; }
    .t td { font-size: 13px; color: var(--lembar-ink); padding: 10px 16px; border-bottom: 1px solid var(--lembar-line); vertical-align: top; font-variant-numeric: tabular-nums; }
    .t tbody tr:last-child td { border-bottom: 0; }
    .t tbody tr { transition: background 140ms cubic-bezier(0.23, 1, 0.32, 1); }
    .t tbody tr:hover { background: var(--lembar-head); }
    .t td.num, .t th.num { text-align: right; font-variant-numeric: tabular-nums; }
    .t .kosong { color: var(--lembar-muted); opacity: 0.7; }

    /* Filter & tombol lembar */
    .lembar-input {
        background: #ffffff;
        border: 1px solid #cfd6e2;
        border-radius: 6px; padding: 8px 12px;
        font-size: 13px; font-weight: 500; color: #1d2433;
        box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.04);
        transition: border-color 140ms cubic-bezier(0.23, 1, 0.32, 1), box-shadow 140ms cubic-bezier(0.23, 1, 0.32, 1), background 140ms cubic-bezier(0.23, 1, 0.32, 1);
    }
    .lembar-input:focus { outline: none; border-color: #2563EB; box-shadow: 0 0 0 3px #D2EBEE; background: #ffffff; }
    html.dark .lembar-input { background: #01434D; border-color: #0A4C57; color: #E2EAE4; }
    html.dark .lembar-input:focus { background: #01434D; border-color: #5FC4D4; box-shadow: 0 0 0 3px #2a3560; }

    .lembar-btn {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 8px 16px; border-radius: 999px;
        font-size: 13px; font-weight: 500; color: #1d2433;
        background: #ffffff;
        border: 1px solid #cfd6e2;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
        transition: all 140ms cubic-bezier(0.23, 1, 0.32, 1);
        cursor: pointer;
    }
    .lembar-btn:hover { background: #f4f6fa; border-color: #b9c2d3; }
    .lembar-btn:active { transform: scale(0.97); }
    html.dark .lembar-btn { background: #01434D; border-color: #0A4C57; color: #E2EAE4; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.3); }
    html.dark .lembar-btn:hover { background: #1f2c49; border-color: #4a5a7d; }
    .lembar-btn-utama { background: #2563EB; border-color: #1D4ED8; color: #fff; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.18); }
    .lembar-btn-utama:hover { background: #1D4ED8; border-color: #01434D; }
    html.dark .lembar-btn-utama { background: #04727E; color: #fff; }

    /* Pil status kecil di judul panel */
    .pil { display: inline-flex; align-items: center; padding: 2px 10px; border-radius: 999px; font-size: 10px; font-weight: 500; color: var(--lembar-muted); background: var(--lembar-head); border: 1px solid var(--lembar-line); text-transform: none; letter-spacing: 0; }

    /* Nomor bab */
    .bab-no { width: 20px; height: 20px; border-radius: 999px; background: #1d2433; color: #fff; font-size: 10px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
    html.dark .bab-no { background: #E2EAE4; color: #1A231F; }
    .bab-judul { font-size: 13px; font-weight: 700; color: #3F4F47; letter-spacing: -0.01em; }
    html.dark .bab-judul { color: #C9D6CE; }

    @media (prefers-reduced-motion: reduce) {
        .lembar, .lembar-btn, .lembar-input, .t tbody tr { transition: none !important; }
    }

    /* Cetak mengikuti laporan lain: kop perusahaan (partials.print-report-header), panel polos bergaris tipis
       tanpa sudut bulat/bayangan, judul tabel & kepala kolom 10px, isi 11px (skala x-laporan.print-table),
       kartu angka diganti tabel ringkasan (x-laporan.resume-table). Tampilan layar tidak berubah. */
    @media print {
        @page { size: A4 landscape; margin: 10mm; }
        aside, .no-print, .topbar { display: none !important; }
        main { margin-left: 0 !important; padding: 28px 32px !important; }
        body { background: #fff !important; }
        .lembar, .lembar-e, .lembar:hover {
            background: #fff !important; border: 1px solid #E2EAE4 !important; border-radius: 3px !important;
            box-shadow: none !important; padding: 0 !important; margin-bottom: 16px !important; overflow: hidden;
        }
        .k-judul {
            font-size: 10px !important; font-weight: 500 !important; color: #3F4F47 !important; text-transform: uppercase; letter-spacing: 0.06em;
            background: #FAFBF9 !important; border-bottom: 1px solid #E2EAE4; padding: 8px 12px; margin: 0 !important;
        }
        .k-ket { font-size: 12px !important; color: #4A5B53 !important; padding: 6px 12px 0; margin: 0 0 6px !important; }
        .pil { border: 0 !important; background: transparent !important; padding: 0 6px !important; font-size: 10px !important; color: #4A5B53 !important; }
        .t th { background: #FAFBF9 !important; color: #4A5B53 !important; font-size: 10px !important; font-weight: 500 !important; padding: 6px 12px !important; border-bottom: 1px solid #E2EAE4 !important; }
        .t td { color: #2A3731 !important; font-size: 11px !important; padding: 6px 12px !important; border-bottom: 1px solid #F1F5F1 !important; }
        .t tbody tr:hover { background: transparent !important; }
        .bab-no { display: none !important; }
        .bab-judul { font-size: 12px !important; font-weight: 600 !important; color: #1A231F !important; margin-top: 8px; }
        .blok { break-inside: avoid; }
        .text-rose-600 { color: #be123c !important; }
        .text-emerald-600 { color: #047857 !important; }
    }
</style>

@php
    $n = fn ($v, $d = 2) => angka((float) $v, $d);
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $k = $a['keuangan'];
    $sc = $a['salah_cetak'];
@endphp

@include('partials.print-report-header', ['docTitle' => 'Audit Bulanan ' . $bulan->translatedFormat('F Y'), 'tglDari' => $bulan->copy()->startOfMonth()->toDateString(), 'tglSampai' => $bulan->copy()->endOfMonth()->toDateString()])

<div class="w-full px-4 sm:px-6 lg:px-8 py-8 print:py-0 max-w-[1500px] mx-auto">

    <div class="print:hidden">
    <x-page-header title="Audit Bulanan">
        <x-slot:subtitle>
            <i class="fas fa-clipboard-check text-emerald-500"></i>
            Seluruh input {{ $bulan->translatedFormat('F Y') }} dalam satu halaman &middot; hanya-baca
        </x-slot:subtitle>
    </x-page-header>
    </div>

    @php($integritas = \Illuminate\Support\Facades\Cache::get('audit_integritas_terakhir'))
    @if($integritas && $integritas['jumlah'] > 0)
        <div class="lembar no-print mb-6 px-5 py-4 border-rose-200">
            <p class="text-sm font-medium text-rose-700">Pemeriksaan pembukuan menemukan {{ $integritas['jumlah'] }} hal yang perlu dicek</p>
            <p class="text-xs text-slate-500 mt-0.5">Diperiksa {{ \Carbon\Carbon::parse($integritas['waktu'])->translatedFormat('d M Y, H:i') }}. Jalankan ulang setelah diperbaiki: <code>php artisan audit:integritas</code></p>
            <ul class="mt-3 space-y-1 text-[13px] text-slate-700">
                @foreach(array_slice($integritas['temuan'], 0, 10) as [$jenis, $target, $detail])
                    <li><span class="font-medium">{{ str_replace('_', ' ', $jenis) }}</span>: {{ $target }} <span class="text-slate-500">({{ $detail }})</span></li>
                @endforeach
            </ul>
            @if($integritas['jumlah'] > 10)
                <p class="text-xs text-slate-500 mt-2">Dan {{ $integritas['jumlah'] - 10 }} lainnya.</p>
            @endif
        </div>
    @endif

    {{-- Filter: satu baris tenang, tanpa kotak besar --}}
    <div class="lembar no-print mb-6 px-5 py-3.5">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <span class="text-[10px] font-medium uppercase tracking-[0.12em]" style="color:var(--lembar-muted)">Bulan</span>
            <input type="month" name="bulan" value="{{ $bulan->format('Y-m') }}" class="lembar-input" data-no-autosubmit>
            <span class="flex-1"></span>
            <button type="submit" class="lembar-btn lembar-btn-utama"><i class="fas fa-filter text-[11px]"></i> Tampilkan</button>
            <a href="{{ route('audit-owner.csv', ['bulan' => $bulan->format('Y-m')]) }}" class="lembar-btn" title="Semua bagian laporan dalam satu berkas CSV"><i class="fas fa-file-csv text-[11px]"></i> Unduh CSV</a>
            <button type="button" onclick="window.print()" class="lembar-btn"><i class="fas fa-print text-[11px]"></i> Cetak</button>
        </form>
    </div>

    {{-- ================= 1. KEUANGAN ================= --}}
    <div class="flex items-center gap-2.5 mb-3">
        <span class="bab-no">1</span>
        <h2 class="bab-judul">Keuangan bulan ini</h2>
    </div>

    <div class="hidden print:block blok">
        <x-laporan.resume-table title="Ringkasan keuangan" :rows="[
            'Omzet nota (' . $k['jumlah_nota'] . ' nota)' => e($rp($k['omzet'])),
            'Omzet jersey (' . $k['jumlah_jersey'] . ' order)' => e($rp($k['omzet_jersey'])),
            'Biaya aktual (HPP + rework + waste)' => e($rp($k['biaya_aktual'])),
            'Laba kotor (margin ' . $n($k['margin'], 1) . '%)' => e($rp($k['laba_kotor'])),
            'Saldo kas (buku besar)' => e($rp($k['saldo_kas'])),
            'Saldo bank' => e($rp($k['saldo_bank'])),
            'Piutang (' . $k['nota_belum_lunas'] . ' nota belum lunas)' => e($rp($k['piutang'])),
            'Hutang supplier' => e($rp($k['hutang'])),
        ]" />
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5 mb-3 blok print:hidden">
        <div class="lembar lembar-e"><div class="l">Omzet nota &middot; {{ $k['jumlah_nota'] }} nota</div><div class="v">{{ $rp($k['omzet']) }}</div></div>
        <div class="lembar lembar-e"><div class="l">Omzet jersey &middot; {{ $k['jumlah_jersey'] }} order</div><div class="v">{{ $rp($k['omzet_jersey']) }}</div></div>
        <div class="lembar lembar-e"><div class="l">Biaya aktual &middot; HPP + rework + waste</div><div class="v">{{ $rp($k['biaya_aktual']) }}</div></div>
        <div class="lembar lembar-e"><div class="l">Laba kotor &middot; margin {{ $n($k['margin'], 1) }}%</div><div class="v {{ $k['laba_kotor'] < 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $rp($k['laba_kotor']) }}</div></div>
        <div class="lembar lembar-e"><div class="l">Saldo kas &middot; buku besar</div><div class="v">{{ $rp($k['saldo_kas']) }}</div></div>
        <div class="lembar lembar-e"><div class="l">Saldo bank</div><div class="v">{{ $rp($k['saldo_bank']) }}</div></div>
        <div class="lembar lembar-e"><div class="l">Piutang &middot; {{ $k['nota_belum_lunas'] }} nota belum lunas</div><div class="v">{{ $rp($k['piutang']) }}</div></div>
        <div class="lembar lembar-e"><div class="l">Hutang supplier</div><div class="v">{{ $rp($k['hutang']) }}</div></div>
    </div>

    <div class="grid md:grid-cols-2 gap-3 mb-3">
        <div class="lembar lembar-p blok">
            <h3 class="k-judul">Uang masuk: pembayaran nota</h3>
            <table class="t">
                <thead><tr><th>Metode</th><th class="num">Transaksi</th><th class="num">Total</th></tr></thead>
                <tbody>
                @forelse($k['kas_masuk'] as $r)<tr><td>{{ $r->metode }}</td><td class="num">{{ $r->jumlah }}</td><td class="num">{{ $rp($r->total) }}</td></tr>@empty<tr><td colspan="3" class="kosong">Belum ada pembayaran nota bulan ini.</td></tr>@endforelse
                <tr><td colspan="2" class="font-medium">Total</td><td class="num font-medium">{{ $rp($k['kas_masuk_total']) }}</td></tr>
                <tr><td>Setoran deposit pelanggan</td><td class="num">-</td><td class="num">{{ $rp($k['setoran_deposit']) }}</td></tr>
                <tr><td>Pembayaran order jersey</td><td class="num">-</td><td class="num">{{ $rp($k['pembayaran_jersey']) }}</td></tr>
                @if($k['pemakaian_deposit'] > 0)<tr><td class="text-slate-500">Nota dibayar dari saldo deposit (bukan uang baru)</td><td class="num">-</td><td class="num text-slate-500">{{ $rp($k['pemakaian_deposit']) }}</td></tr>@endif
                </tbody>
            </table>
        </div>
        <div class="lembar lembar-p blok">
            <h3 class="k-judul">Uang keluar</h3>
            <table class="t">
                <thead><tr><th>Uraian</th><th class="num">Jumlah</th><th class="num">Nilai</th></tr></thead>
                <tbody>
                <tr><td>Pembelian bahan (faktur)</td><td class="num">{{ $k['pembelian_jumlah'] }}</td><td class="num">{{ $rp($k['pembelian_total']) }}</td></tr>
                <tr><td>Pembayaran hutang supplier (dana pemilik)</td><td class="num">-</td><td class="num">{{ $rp($k['pembayaran_hutang']) }}</td></tr>
                @foreach($k['kas_kecil'] as $r)<tr><td>Kas kecil &middot; {{ $r->kategori }}@if($r->pemilik) (dibayar pemilik)@endif</td><td class="num">{{ $r->jumlah }}</td><td class="num">{{ $rp($r->total) }}</td></tr>@endforeach
                @foreach($k['kas_manual'] as $r)<tr><td>Kas manual &middot; {{ $r->status }}</td><td class="num">{{ $r->jumlah }}</td><td class="num">{{ $rp($r->total) }}</td></tr>@endforeach
                @if($k['kas_kecil']->isEmpty() && $k['kas_manual']->isEmpty())<tr><td colspan="3" class="kosong">Belum ada pengeluaran lain.</td></tr>@endif
                </tbody>
            </table>
        </div>
    </div>

    <div class="lembar lembar-p mb-3 blok">
        <h3 class="k-judul">Kas harian: {{ $a['kas_harian']->count() }} sesi closing</h3>
        <table class="t">
            <thead><tr><th>Tanggal</th><th>Kasir</th><th class="num">Saldo awal</th><th class="num">Saldo sistem</th><th class="num">Saldo fisik</th><th class="num">Selisih</th><th>Status</th><th>Keterangan</th></tr></thead>
            <tbody>
            @forelse($a['kas_harian'] as $r)
                <tr><td>{{ \Carbon\Carbon::parse($r->tgl_kas)->format('d/m/Y') }}</td><td>{{ $r->kasir }}</td><td class="num">{{ $rp($r->saldo_awal) }}</td><td class="num">{{ $rp($r->saldo_akhir) }}</td><td class="num">{{ $r->saldo_fisik !== null ? $rp($r->saldo_fisik) : '-' }}</td>
                    <td class="num {{ abs((float) $r->selisih) > 0 ? 'text-rose-600 font-medium' : '' }}">{{ $r->selisih !== null ? $rp($r->selisih) : '-' }}</td><td>{{ $r->status_lock }}</td><td>{{ $r->keterangan_closing }}</td></tr>
            @empty<tr><td colspan="8" class="kosong">Belum ada sesi kas bulan ini.</td></tr>@endforelse
            </tbody>
        </table>
    </div>

    <div class="lembar lembar-p mb-3 blok">
        <h3 class="k-judul">Nota dibatalkan: {{ $k['nota_dibatalkan_jumlah'] }} nota senilai {{ $rp($k['nota_dibatalkan_nilai']) }}</h3>
        <table class="t">
            <thead><tr><th>Nota</th><th class="num">Nilai</th><th>Tanggal batal</th><th>Alasan</th></tr></thead>
            <tbody>
            @forelse($k['nota_dibatalkan'] as $r)<tr><td>{{ $r->nomor_nota }}</td><td class="num">{{ $rp($r->total_bayar) }}</td><td>{{ $r->tgl_dibatalkan_nota }}</td><td>{{ $r->alasan_batal_nota }}</td></tr>@empty<tr><td colspan="4" class="kosong">Tidak ada nota yang dibatalkan.</td></tr>@endforelse
            </tbody>
        </table>
    </div>

    {{-- ================= 2. PERMINTAAN BAHAN ================= --}}
    <div class="flex items-center gap-2.5 mb-3 mt-8">
        <span class="bab-no">2</span>
        <h2 class="bab-judul">Permintaan bahan</h2>
    </div>

    <div class="lembar lembar-p mb-3 blok">
        <h3 class="k-judul">Purchase Request
            @foreach($a['permintaan']['pr_per_status'] as $st => $c)<span class="pil">{{ $st }} &middot; {{ $c }}</span>@endforeach
        </h3>
        <table class="t">
            <thead><tr><th>Nomor</th><th>Tanggal</th><th>Status</th><th>Pembuat</th><th>Item diminta</th></tr></thead>
            <tbody>
            @forelse($a['permintaan']['pr'] as $r)<tr><td>{{ $r->nomor }}</td><td>{{ \Carbon\Carbon::parse($r->tanggal)->format('d/m/Y') }}</td><td>{{ $r->status }}</td><td>{{ $r->pembuat }}</td><td>{{ $r->item }}</td></tr>@empty<tr><td colspan="5" class="kosong">Belum ada permintaan bahan.</td></tr>@endforelse
            </tbody>
        </table>
    </div>

    <div class="lembar lembar-p mb-3 blok">
        <h3 class="k-judul">Pesanan Pembelian <span class="pil">total {{ $rp($a['permintaan']['po_total']) }}</span></h3>
        <table class="t">
            <thead><tr><th>Nomor</th><th>Tanggal</th><th>Pemasok</th><th>Status</th><th class="num">Total</th></tr></thead>
            <tbody>
            @forelse($a['permintaan']['po'] as $r)<tr><td>{{ $r->nomor }}</td><td>{{ \Carbon\Carbon::parse($r->tanggal)->format('d/m/Y') }}</td><td>{{ $r->nama_supplier }}</td><td>{{ $r->status }}</td><td class="num">{{ $rp($r->total) }}</td></tr>@empty<tr><td colspan="5" class="kosong">Belum ada PO bulan ini.</td></tr>@endforelse
            </tbody>
        </table>
    </div>

    {{-- ================= 3. PEMAKAIAN BAHAN ================= --}}
    <div class="flex items-center gap-2.5 mb-3 mt-8">
        <span class="bab-no">3</span>
        <h2 class="bab-judul">Pemakaian bahan: cetak &amp; salah cetak</h2>
    </div>

    <div class="lembar lembar-p mb-3 blok">
        <p class="k-ket">Dihitung dari Kartu Stok. Uji: stok awal + masuk &minus; keluar + penyesuaian harus pas dengan stok akhir; kolom <b>Kontrol</b> menunjukkan 0 kalau cocok.</p>
        <div class="overflow-x-auto">
        <table class="t">
            <thead><tr><th>Bahan</th><th>Satuan</th><th class="num">Stok awal</th><th class="num">Masuk</th><th class="num">Keluar cetak</th><th class="num">Salah cetak</th><th class="num">Rework</th><th class="num">Keluar lain</th><th class="num">Penyesuaian</th><th class="num">Stok akhir</th><th class="num">Nilai salah cetak</th><th class="num">Kontrol</th></tr></thead>
            <tbody>
            @forelse($a['stok'] as $r)
                <tr><td class="font-medium">{{ $r->nama_bahan }}</td><td>{{ $r->satuan }}</td><td class="num">{{ $n($r->stok_awal, 4) }}</td><td class="num">{{ $n($r->masuk, 4) }}</td><td class="num">{{ $n($r->keluar_cetak, 4) }}</td>
                    <td class="num {{ $r->keluar_waste > 0 ? 'text-rose-600 font-medium' : '' }}">{{ $n($r->keluar_waste, 4) }}</td><td class="num {{ $r->keluar_rework > 0 ? 'text-rose-600 font-medium' : '' }}">{{ $n($r->keluar_rework, 4) }}</td>
                    <td class="num">{{ $n($r->keluar_lain, 4) }}</td><td class="num">{{ $n($r->penyesuaian, 4) }}</td><td class="num font-medium">{{ $n($r->stok_akhir, 4) }}</td>
                    <td class="num">{{ $rp($r->nilai_salah_cetak) }}</td><td class="num {{ abs($r->selisih_kontrol) > 0.0001 ? 'text-rose-600 font-medium' : 'text-emerald-600' }}">{{ abs($r->selisih_kontrol) > 0.0001 ? $n($r->selisih_kontrol, 4) : 'pas' }}</td></tr>
            @empty<tr><td colspan="12" class="kosong">Belum ada pergerakan stok.</td></tr>@endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="hidden print:block blok">
        <x-laporan.resume-table title="Ringkasan salah cetak" :rows="[
            'Biaya waste diterapkan' => e($rp($sc['waste_biaya'])),
            'Waste abnormal' => e($sc['waste_abnormal'] . ' kejadian'),
            'Biaya rework selesai' => e($rp($sc['rework_biaya'])),
            'Reject QC' => e($n($sc['qc_reject'], 4) . ' / ' . $n($sc['qc_diperiksa'], 4) . ' (' . $n($sc['qc_persen_reject'], 2) . '%)'),
        ]" />
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5 mb-3 blok print:hidden">
        <div class="lembar lembar-e"><div class="l">Biaya waste diterapkan</div><div class="v">{{ $rp($sc['waste_biaya']) }}</div></div>
        <div class="lembar lembar-e"><div class="l">Waste abnormal</div><div class="v">{{ $sc['waste_abnormal'] }} <small>kejadian</small></div></div>
        <div class="lembar lembar-e"><div class="l">Biaya rework selesai</div><div class="v">{{ $rp($sc['rework_biaya']) }}</div></div>
        <div class="lembar lembar-e"><div class="l">Reject QC</div><div class="v">{{ $n($sc['qc_reject'], 4) }} <small>/ {{ $n($sc['qc_diperiksa'], 4) }} &middot; {{ $n($sc['qc_persen_reject'], 2) }}%</small></div></div>
    </div>

    <div class="lembar lembar-p mb-3 blok">
        <h3 class="k-judul">Rincian waste: bahan terbuang</h3>
        <table class="t">
            <thead><tr><th>Nomor</th><th>Tanggal</th><th>Bahan</th><th class="num">Qty</th><th>Jenis</th><th>Status</th><th class="num">Biaya</th><th>Alasan</th><th>Petugas</th></tr></thead>
            <tbody>
            @forelse($sc['waste'] as $r)<tr><td>{{ $r->nomor }}</td><td>{{ \Carbon\Carbon::parse($r->tanggal)->format('d/m/Y') }}</td><td>{{ $r->nama_bahan }}</td><td class="num">{{ $n($r->qty, 4) }} {{ $r->satuan_stok }}</td><td>{{ $r->jenis }}</td><td>{{ $r->status }}</td><td class="num">{{ $rp($r->biaya) }}</td><td>{{ $r->alasan }}</td><td>{{ $r->petugas }}</td></tr>@empty<tr><td colspan="9" class="kosong">Tidak ada waste bulan ini.</td></tr>@endforelse
            </tbody>
        </table>
    </div>

    <div class="lembar lembar-p mb-3 blok">
        <h3 class="k-judul">Rincian rework: cetak ulang</h3>
        <table class="t">
            <thead><tr><th>Nomor</th><th>Tanggal</th><th class="num">Qty</th><th>Status</th><th class="num">Biaya material</th><th>Catatan</th><th>Petugas</th></tr></thead>
            <tbody>
            @forelse($sc['rework'] as $r)<tr><td>{{ $r->nomor }}</td><td>{{ \Carbon\Carbon::parse($r->tanggal)->format('d/m/Y') }}</td><td class="num">{{ $n($r->qty_rework, 4) }}</td><td>{{ $r->status }}</td><td class="num">{{ $rp($r->biaya_material) }}</td><td>{{ $r->catatan }}</td><td>{{ $r->petugas }}</td></tr>@empty<tr><td colspan="7" class="kosong">Tidak ada rework bulan ini.</td></tr>@endforelse
            </tbody>
        </table>
    </div>

    <div class="lembar lembar-p mb-3 blok">
        <h3 class="k-judul">Penyesuaian stok &amp; opname</h3>
        <table class="t">
            <thead><tr><th>Tanggal</th><th>Bahan</th><th class="num">Sebelum</th><th class="num">Sesudah</th><th class="num">Selisih</th><th class="num">Nilai</th><th>Referensi / alasan</th><th>Petugas</th></tr></thead>
            <tbody>
            @forelse($a['penyesuaian'] as $r)<tr><td>{{ \Carbon\Carbon::parse($r->tanggal)->format('d/m/Y') }}</td><td>{{ $r->nama_bahan }}</td><td class="num">{{ $n($r->stok_sebelum, 4) }} {{ $r->satuan_stok }}</td><td class="num">{{ $n($r->stok_sesudah, 4) }} {{ $r->satuan_stok }}</td>
                <td class="num {{ $r->selisih < 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $n($r->selisih, 4) }}</td><td class="num">{{ $rp($r->nilai) }}</td><td>{{ $r->referensi }} {{ $r->keterangan }}</td><td>{{ $r->petugas }}</td></tr>@empty<tr><td colspan="8" class="kosong">Tidak ada penyesuaian bulan ini.</td></tr>@endforelse
            </tbody>
        </table>
    </div>

    {{-- ================= 4. PENGENDALIAN ================= --}}
    <div class="flex items-center gap-2.5 mb-3 mt-8">
        <span class="bab-no">4</span>
        <h2 class="bab-judul">Pengendalian &amp; jejak input</h2>
    </div>

    <div class="lembar lembar-p mb-3 blok">
        <h3 class="k-judul">Pengajuan &amp; persetujuan
            @foreach($a['approval']['per_status'] as $st => $c)<span class="pil">{{ $st }} &middot; {{ $c }}</span>@endforeach
        </h3>
        <table class="t">
            <thead><tr><th>Dibuat</th><th>Jenis</th><th>Ringkasan</th><th>Pengaju</th><th>Alasan</th><th>Status</th><th>Pemutus</th><th>Catatan</th></tr></thead>
            <tbody>
            @forelse($a['approval']['daftar'] as $r)<tr><td>{{ $r->created_at }}</td><td>{{ str_replace('_', ' ', $r->tipe_aksi) }}</td><td>{{ $r->ringkasan }}</td><td>{{ $r->pengaju }}</td><td>{{ $r->alasan_pengajuan }}</td><td>{{ $r->status }}</td><td>{{ $r->pemutus }}</td><td>{{ $r->catatan_keputusan }}</td></tr>@empty<tr><td colspan="8" class="kosong">Belum ada pengajuan bulan ini.</td></tr>@endforelse
            </tbody>
        </table>
    </div>

    <div class="lembar lembar-p mb-3 blok">
        <h3 class="k-judul">Input per pengguna</h3>
        <table class="t">
            <thead><tr><th>Pengguna</th>@foreach($a['input_user']['kolom'] as $c)<th class="num">{{ $c }}</th>@endforeach</tr></thead>
            <tbody>
            @forelse($a['input_user']['baris'] as $r)<tr><td>{{ $r['nama'] }}</td>@foreach($a['input_user']['kolom'] as $c)<td class="num">{{ $r[$c] ?? 0 }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($a['input_user']['kolom']) + 1 }}" class="kosong">Belum ada input tercatat.</td></tr>@endforelse
            </tbody>
        </table>
    </div>

    <div class="lembar lembar-p mb-6 blok">
        <h3 class="k-judul">Jejak aktivitas <span class="pil">{{ $a['jejak']['total'] }} catatan</span>
            @foreach($a['jejak']['per_event'] as $ev => $c)<span class="pil">{{ $ev }} &middot; {{ $c }}</span>@endforeach
        </h3>
        <p class="k-ket">Yang ditampilkan hanya yang penting: perubahan harga, pembatalan, koreksi, void, dan tutup paksa. Riwayat lengkapnya ada di menu Log Aktivitas.</p>
        <table class="t">
            <thead><tr><th>Waktu</th><th>Pengguna</th><th>Event</th><th>Objek</th><th>Keterangan</th></tr></thead>
            <tbody>
            @forelse($a['jejak']['penting'] as $r)<tr><td>{{ $r->created_at }}</td><td>{{ $r->user_label }}</td><td>{{ $r->event }}</td><td>{{ class_basename($r->auditable_type) }}</td><td>{{ $r->keterangan }}</td></tr>@empty<tr><td colspan="5" class="kosong">Tidak ada aktivitas khusus bulan ini.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
