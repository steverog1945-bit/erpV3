@extends('layouts.app')

@section('content')
<div class="dashboard-root w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">
@php $tabInfo = request('tab') === 'info' && \App\Support\Izin::boleh(auth()->user(), [67], 'view'); @endphp
@include('partials.dashboard-tab', ['tabInfo' => $tabInfo])
@if($tabInfo)
    @include('postingan._daftar', array_merge(\App\Http\Controllers\PostinganController::dataDaftar(request(), route('dashboard')), ['basisUrl' => route('dashboard'), 'paramTetap' => ['tab' => 'info']]))
@else
@php
    // Tautan hanya aktif bila role boleh membukanya (tidak mengarah ke 403); selain itu '#'.
    $u = function (string $nama, array $param = []) {
        $url = route($nama, $param);

        return \App\Support\JalurLanjut::boleh($url) ? $url : '#';
    };
    $namaRole = optional(Auth::user()->role)->nama_role;
    $jam = (int) now()->format('H');
    $salam = $jam < 11 ? 'Selamat pagi' : ($jam < 15 ? 'Selamat siang' : ($jam < 18 ? 'Selamat sore' : 'Selamat malam'));
    $nama = optional(Auth::user()->karyawan)->nama_karyawan ?: Auth::user()->username;

    // Perubahan dibanding kemarin: [teks, kelas warna, ikon]
    $delta = function (float $sekarang, float $lalu) {
        if ($lalu <= 0) {
            return $sekarang > 0 ? ['baru hari ini', 'text-slate-400', 'fa-minus'] : ['belum ada data', 'text-slate-400', 'fa-minus'];
        }
        $p = ($sekarang - $lalu) / $lalu * 100;
        if (abs($p) < 0.5) return ['sama seperti kemarin', 'text-slate-400', 'fa-minus'];

        return [($p > 0 ? '+' : '') . angka($p, 0) . '% vs kemarin', $p > 0 ? 'text-emerald-600' : 'text-rose-500', $p > 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'];
    };
    [$dOrderTeks, $dOrderKls, $dOrderIkon] = $delta((float) $orderHariIni, (float) $orderKemarin);
    [$dKasTeks, $dKasKls, $dKasIkon] = $delta((float) $kasMasukHariIni, (float) $kasMasukKemarin);

    $totalSpk = max(1, $spkAntre + $spkProses + $spkSelesai);
    $maksOrder = max(1, $tren->max('order'));
    $maksKas = max(1, $tren->max('kas'));
    $nadaTone = ['rose' => 'bg-rose-50 text-rose-600', 'amber' => 'bg-amber-50 text-amber-600', 'indigo' => 'bg-indigo-50 text-indigo-600'];
@endphp

{{-- ============ SAPAAN + AKSI CEPAT ============ --}}
<div class="flex flex-col lg:flex-row lg:items-end justify-between gap-5 mb-8">
    <div>
        <p class="text-[11px] font-medium text-indigo-500 uppercase tracking-[0.2em]">{{ now()->translatedFormat('l, d F Y') }}</p>
        <h1 class="text-3xl font-medium text-slate-900 tracking-tight mt-1">{{ $salam }}, {{ $nama }}</h1>
        <p class="text-sm text-slate-400 mt-1">Ringkasan pergerakan bisnis hari ini &middot; {{ $namaRole }}</p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2 px-4 py-2.5 rounded-lg border {{ $kasAktif ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-slate-50 text-slate-500 border-slate-100' }}" title="{{ $kasAktif ? 'Sesi kas Anda sedang dibuka' : 'Belum ada sesi kas yang dibuka atas nama Anda' }}">
            <span class="relative flex h-2 w-2">
                @if($kasAktif)<span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>@endif
                <span class="relative inline-flex rounded-full h-2 w-2 {{ $kasAktif ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
            </span>
            <span class="text-[11px] font-medium uppercase tracking-widest">Kasir {{ $kasAktif ? 'Aktif' : 'Standby' }}</span>
        </div>
        @if($u('penjualan.create') !== '#')
        <a href="{{ $u('penjualan.create') }}" title="Buat nota / order baru" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-indigo-600 text-white text-[11px] font-medium uppercase tracking-widest shadow-sm hover:bg-indigo-700 transition-all"><i class="fas fa-plus"></i> Nota Baru</a>
        @endif
        @if($u('kasir.index') !== '#')
        <a href="{{ $u('kasir.index') }}" title="Buka Monitoring Kasir untuk pembayaran" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-white border border-slate-100 text-slate-600 text-[11px] font-medium uppercase tracking-widest hover:bg-slate-50 transition-all"><i class="fas fa-cash-register text-slate-500"></i> Kasir</a>
        @endif
        @if($u('spk.index') !== '#')
        <a href="{{ $u('spk.index') }}" title="Lihat antrean SPK produksi" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-white border border-slate-100 text-slate-600 text-[11px] font-medium uppercase tracking-widest hover:bg-slate-50 transition-all"><i class="fas fa-list-check text-slate-500"></i> SPK</a>
        @endif
    </div>
</div>

{{-- ============ KPI ============ --}}
{{-- Baris status real-time: titik "live" berdenyut + jam pembaruan terakhir,
     diletakkan tepat DI ATAS kartu yang memang ia perbarui (sebelumnya tersemat
     sendirian di samping judul sapaan sehingga terasa mengambang). Warna hanya
     memakai palet yang sudah punya pasangan mode gelap/terang di app.css
     (emerald/slate), jadi tampilannya benar tanpa menyentuh CSS global. --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div class="flex items-center gap-2.5">
        <span class="relative flex h-2 w-2" title="Angka pada kartu di bawah diperbarui otomatis dari server">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
        </span>
        <span class="text-[11px] font-medium text-slate-500 uppercase tracking-[0.2em]">Pembaruan Langsung</span>
    </div>
    <p class="text-[11px] text-slate-400">
        Diperbarui otomatis <span id="dashboard-updated-at" class="font-medium text-slate-500">{{ now()->format('H.i') }}</span>
        &middot; tiap 30 detik
    </p>
</div>
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <a href="{{ $u('penjualan.riwayat') }}" title="Jumlah order hari ini dan nilainya" class="bg-white p-5 rounded-2xl border border-slate-200/70 shadow-[0_1px_3px_rgba(15,30,62,0.06)] hover:shadow-[0_10px_24px_rgba(15,30,62,0.10)] transition-all duration-200">
        <div class="flex items-center justify-between"><p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Order Hari Ini</p><span class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-500 flex items-center justify-center"><i class="fas fa-receipt text-xs"></i></span></div>
        <p class="text-3xl font-medium text-slate-900 mt-3">{{ $orderHariIni }}</p>
        <p class="text-xs font-medium text-slate-500 mt-0.5">Rp {{ number_format($nilaiOrderHariIni, 0, ',', '.') }}</p>
        <p class="text-[11px] font-medium mt-2 {{ $dOrderKls }}"><i class="fas {{ $dOrderIkon }} text-[10px]"></i> {{ $dOrderTeks }}</p>
    </a>
    <a href="{{ $u('laporan.kas') }}" title="Uang yang diterima dari pembayaran nota hari ini" class="bg-white p-5 rounded-2xl border border-slate-200/70 shadow-[0_1px_3px_rgba(15,30,62,0.06)] hover:shadow-[0_10px_24px_rgba(15,30,62,0.10)] transition-all duration-200">
        <div class="flex items-center justify-between"><p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Kas Masuk Hari Ini</p><span class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-500 flex items-center justify-center"><i class="fas fa-wallet text-xs"></i></span></div>
        <p class="text-3xl font-medium text-slate-900 mt-3">Rp {{ number_format($kasMasukHariIni, 0, ',', '.') }}</p>
        <p class="text-[11px] font-medium mt-3 {{ $dKasKls }}"><i class="fas {{ $dKasIkon }} text-[10px]"></i> {{ $dKasTeks }}</p>
    </a>
    <a href="{{ $u('laporan.piutang') }}" title="Tagihan nota yang belum lunas" class="bg-white p-5 rounded-2xl border border-slate-200/70 shadow-[0_1px_3px_rgba(15,30,62,0.06)] hover:shadow-[0_10px_24px_rgba(15,30,62,0.10)] transition-all duration-200">
        <div class="flex items-center justify-between"><p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Piutang</p><span class="w-8 h-8 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center"><i class="fas fa-file-invoice-dollar text-xs"></i></span></div>
        <p class="text-3xl font-medium text-slate-900 mt-3">Rp {{ number_format($piutang, 0, ',', '.') }}</p>
        <p class="text-[11px] font-medium text-slate-400 mt-3">{{ $piutangCount }} nota belum lunas</p>
    </a>
    <a href="{{ $u('spk.index', ['urgent_only' => 1]) }}" title="Item urgent yang belum selesai" class="bg-white p-5 rounded-2xl border {{ $urgentAktif > 0 ? 'border-orange-200' : 'border-slate-200/70' }} shadow-[0_1px_3px_rgba(15,30,62,0.06)] hover:shadow-[0_10px_24px_rgba(15,30,62,0.10)] transition-all duration-200">
        <div class="flex items-center justify-between"><p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Item Urgent</p><span class="w-8 h-8 rounded-xl bg-orange-50 text-orange-500 flex items-center justify-center"><i class="fas fa-fire text-xs"></i></span></div>
        <p class="text-3xl font-medium {{ $urgentAktif > 0 ? 'text-orange-600' : 'text-slate-900' }} mt-3">{{ $urgentAktif }}</p>
        <p class="text-[11px] font-medium text-slate-400 mt-3">{{ $urgentAktif > 0 ? 'perlu diprioritaskan' : 'semua berjalan normal' }}</p>
    </a>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">

    {{-- ============ ALUR PRODUKSI ============ --}}
    <div class="xl:col-span-2 bg-white rounded-2xl border border-slate-200/70 shadow-[0_1px_4px_rgba(15,30,62,0.05)] p-6">
        <div class="flex items-center justify-between mb-5">
            <div><h3 class="text-sm font-medium text-slate-800">Alur Pekerjaan</h3><p class="text-[11px] text-slate-400">Dari antrean sampai siap diserahkan</p></div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
            @foreach([
                ['Antre', $spkAntre, $u('spk.index', ['status_spk' => 'Antre']), 'bg-amber-50/70 hover:bg-amber-100/70', 'text-amber-600', 'Item SPK yang menunggu dikerjakan'],
                ['Diproses', $spkProses, $u('produksi.index'), 'bg-indigo-50/70 hover:bg-indigo-100/70', 'text-indigo-600', 'Item yang sedang didesain/dicetak'],
                ['Selesai Produksi', $spkSelesai, $u('produksi.index', ['status_produksi' => 'selesai']), 'bg-emerald-50/70 hover:bg-emerald-100/70', 'text-emerald-600', 'Item yang sudah selesai diproduksi'],
                ['Siap Diambil', $notaLengkap, $u('collecting.index', ['status_pengerjaan' => 'lengkap']), 'bg-indigo-50/70 hover:bg-indigo-100/70', 'text-indigo-600', 'Nota yang semua itemnya sudah selesai'],
            ] as $i => [$judul, $n, $url, $bg, $tx, $tip])
            <a href="{{ $url }}" title="{{ $tip }}" class="group rounded-lg {{ $bg }} p-4 transition-all">
                <p class="text-[10px] font-medium {{ $tx }} uppercase tracking-widest">{{ $i + 1 }}. {{ $judul }}</p>
                <p class="text-3xl font-medium {{ $tx }} mt-1">{{ $n }}</p>
            </a>
            @endforeach
        </div>
        <div class="flex h-2.5 rounded-full overflow-hidden bg-slate-100" title="Proporsi item SPK: antre / diproses / selesai">
            <div class="bg-amber-400" style="width: {{ round($spkAntre / $totalSpk * 100) }}%"></div>
            <div class="bg-indigo-500" style="width: {{ round($spkProses / $totalSpk * 100) }}%"></div>
            <div class="bg-emerald-500" style="width: {{ round($spkSelesai / $totalSpk * 100) }}%"></div>
        </div>
        <p class="text-[11px] text-slate-400 mt-2">{{ $spkSelesai }} dari {{ $spkAntre + $spkProses + $spkSelesai }} item SPK sudah selesai diproduksi.</p>

        @if($notaBelumLengkap > 0)
        <a href="{{ $u('collecting.index', ['status_pengerjaan' => 'belum']) }}" class="mt-4 flex items-center gap-3 rounded-lg bg-amber-50 border border-amber-100 px-4 py-3 text-amber-700 hover:bg-amber-100/60 transition-all" title="Nota yang masih punya item belum selesai">
            <i class="fas fa-hourglass-half"></i>
            <span class="flex-1 text-xs font-medium">{{ $notaBelumLengkap }} nota belum lengkap produksinya - barang belum bisa diserahkan.</span>
            <span class="text-[10px] font-medium uppercase tracking-widest">Lihat</span>
        </a>
        @endif
    </div>

    {{-- ============ PERLU TINDAKAN ============ --}}
    <div class="bg-white rounded-2xl border border-slate-200/70 shadow-[0_1px_4px_rgba(15,30,62,0.05)] overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100"><h3 class="text-sm font-medium text-slate-800">Perlu Tindakan</h3><p class="text-[11px] text-slate-400">Pekerjaan yang menunggu Anda</p></div>
        <div class="divide-y divide-slate-50 max-h-[22rem] overflow-y-auto">
            @forelse($perluTindakan as $it)
            <a href="{{ $it['url'] }}" class="flex items-center gap-3 px-6 py-3.5 hover:bg-slate-50/60 transition-all" title="Buka {{ $it['label'] }}">
                <span class="min-w-[2rem] h-8 px-2 rounded-xl flex items-center justify-center text-xs font-medium {{ $nadaTone[$it['tone']] ?? $nadaTone['indigo'] }}">{{ $it['count'] > 99 ? '99+' : $it['count'] }}</span>
                <span class="flex-1 text-[12px] font-medium text-slate-600 leading-snug">{{ $it['label'] }}</span>
                <i class="fas fa-chevron-right text-[10px] text-slate-500"></i>
            </a>
            @empty
            <div class="px-6 py-10"><x-empty-state title="Semua beres" description="Tidak ada pekerjaan yang menunggu tindakan." /></div>
            @endforelse
        </div>
    </div>
</div>

{{-- ============ TREN 7 HARI ============ --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    @foreach([['Order 7 Hari Terakhir', 'order', $maksOrder, 'bg-indigo-500', false, 'Jumlah nota per hari'], ['Kas Masuk 7 Hari Terakhir', 'kas', $maksKas, 'bg-emerald-500', true, 'Pembayaran yang diterima per hari']] as [$jdl, $kunci, $maks, $warna, $uang, $tip])
    <div class="bg-white rounded-2xl border border-slate-200/70 shadow-[0_1px_4px_rgba(15,30,62,0.05)] p-6" title="{{ $tip }}">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-medium text-slate-800">{{ $jdl }}</h3>
            <span class="text-xs font-medium text-slate-500">{{ $uang ? 'Rp ' . number_format($tren->sum($kunci), 0, ',', '.') : $tren->sum($kunci) . ' order' }}</span>
        </div>
        <div class="flex items-end gap-2 h-28">
            @foreach($tren as $t)
                @php $tinggi = $t[$kunci] > 0 ? max(6, round($t[$kunci] / $maks * 100)) : 3; @endphp
                <div class="flex-1 flex flex-col items-center justify-end h-full group" title="{{ $t['tgl'] }}: {{ $uang ? 'Rp ' . number_format($t[$kunci], 0, ',', '.') : $t[$kunci] . ' order' }}">
                    <div class="w-full rounded-t-lg {{ $loop->last ? $warna : 'bg-slate-200 group-hover:bg-slate-300' }} transition-all" style="height: {{ $tinggi }}%"></div>
                </div>
            @endforeach
        </div>
        <div class="flex gap-2 mt-2">
            @foreach($tren as $t)<div class="flex-1 text-center text-[10px] font-medium {{ $loop->last ? 'text-slate-700' : 'text-slate-400' }}">{{ $t['label'] }}</div>@endforeach
        </div>
    </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ============ ORDER TERBARU ============ --}}
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/70 shadow-[0_1px_4px_rgba(15,30,62,0.05)] overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-medium text-slate-800">Order Terbaru</h3>
            {{-- Rentang 30 hari supaya "Lihat Semua" selalu mencakup order yang tampil di sini. --}}
            <a href="{{ $u('penjualan.riwayat', ['from' => now()->subDays(30)->format('Y-m-d'), 'to' => now()->format('Y-m-d')]) }}" title="Buka riwayat nota 30 hari terakhir" class="text-[10px] font-medium text-indigo-500 uppercase tracking-widest hover:underline">Lihat Semua</a>
        </div>
        <div class="divide-y divide-slate-50">
            @forelse($orderTerbaru as $o)
            <div class="px-6 py-4 flex items-center justify-between hover:bg-slate-50/50 transition-all">
                <div class="min-w-0">
                    <div class="text-[13px] font-medium text-slate-700 uppercase">{{ $o->nomor_nota }}</div>
                    <div class="text-[11px] text-slate-400 truncate">{{ $o->nama_konsumen }} &middot; {{ \Carbon\Carbon::parse($o->tgl_order)->translatedFormat('d M Y H:i') }}</div>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-sm font-medium text-slate-800">Rp {{ number_format($o->total_bayar, 0, ',', '.') }}</div>
                    @if($o->status_nota === 'Dibatalkan')
                        <x-badge status="Dibatalkan" variant="danger" />
                    @else
                        <x-badge :status="$o->status_pembayaran" />
                    @endif
                </div>
            </div>
            @empty
            <div class="px-6 py-10"><x-empty-state title="Belum ada order" description="Order pertama akan muncul di sini." /></div>
            @endforelse
        </div>
    </div>

    {{-- ============ PERLU DIPRIORITASKAN ============ --}}
    <div class="bg-white rounded-2xl border border-slate-200/70 shadow-[0_1px_4px_rgba(15,30,62,0.05)] overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-medium text-slate-800"><i class="fas fa-fire text-orange-500 mr-1"></i> Diprioritaskan</h3>
            <a href="{{ $u('spk.index', ['urgent_only' => 1]) }}" title="Lihat semua item urgent" class="text-[10px] font-medium text-indigo-500 uppercase tracking-widest hover:underline">Semua</a>
        </div>
        <div class="divide-y divide-slate-50">
            @forelse($urgentList as $u)
            <div class="px-6 py-4 hover:bg-slate-50/50 transition-all">
                <div class="text-[12px] font-medium text-slate-700 uppercase">{{ $u->nama_file }}</div>
                <div class="text-[11px] text-slate-400">{{ $u->nama_konsumen }}</div>
                <x-badge :status="$u->status_spk" class="mt-1" />
            </div>
            @empty
            <div class="px-6 py-10"><x-empty-state title="Tidak ada item urgent" description="Semua pekerjaan berjalan normal." /></div>
            @endforelse
        </div>
    </div>
</div>

@include('partials.dashboard-info-terbaru')

@endif
</div>

@push('scripts')
<script>
    // Dashboard real-time KPI polling (refresh tiap 30 detik, tanpa reload halaman).
    (function () {
        var interval = 30000;
        var cards = {
            piutang: document.querySelector('a[href*="piutang"] .text-3xl'),
            order: document.querySelector('a[href*="riwayat"] .text-3xl'),
            kas: document.querySelector('a[href*="laporan.kas"] .text-3xl'),
            spkAntre: null,
            spkProses: null,
            spkSelesai: null,
            urgent: null,
        };
        var updatedAt = null;

        function formatRupiah(n) {
            return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        function update(data) {
            if (!data) return;
            if (cards.piutang) cards.piutang.textContent = "Rp " + formatRupiah(Math.round(data.piutang));
            if (cards.order) cards.order.textContent = data.order_hari_ini;
            if (cards.kas) cards.kas.textContent = "Rp " + formatRupiah(Math.round(data.kas_masuk_hari_ini));
            if (updatedAt) {
                updatedAt.textContent = new Date(data.updated_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                // Kedip halus sekali saat angka benar-benar disegarkan, supaya
                // pembaruan terasa hidup tanpa mengubah layout.
                if (updatedAt.animate) updatedAt.animate([{ opacity: 0.3 }, { opacity: 1 }], { duration: 450, easing: 'ease-out' });
            }
        }

        function fetchKpi() {
            fetch('{{ route('dashboard.kpi-api') }}', {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
                .then(function (r) { return r.json(); })
                .then(update)
                .catch(function () { /* silent - coba lagi pada interval berikutnya */ });
        }

        // Indikator pembaruan kini menetap di baris status di atas kartu KPI
        // (dipasang langsung oleh Blade), bukan ditambal ke judul sapaan.
        updatedAt = document.getElementById('dashboard-updated-at');

        fetchKpi();
        setInterval(fetchKpi, interval);
    })();
</script>
@endpush
@endsection
