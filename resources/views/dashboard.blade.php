@extends('layouts.app')

@section('content')
<style>
/* Scoped ke halaman Dasbor — tidak bocor ke halaman lain. */
.gauge-track { stroke: #EAEFF8; fill: none; }
.gauge-arc { fill: none; transition: stroke-dashoffset 1.05s cubic-bezier(.22, 1, .36, 1); }
.gauge-arc--ok { stroke: #10B981; }
.gauge-arc--indigo { stroke: #2563EB; }
.gauge-arc--amber { stroke: #D97706; }
.gauge-arc--rose { stroke: #E11D48; }
.gauge-text { font-size: 11px; font-weight: 600; fill: #0F1E3E; font-variant-numeric: tabular-nums; }

@media (prefers-reduced-motion: reduce) {
    .gauge-arc { transition: none; }
}
.alur-stage { transition: transform .25s cubic-bezier(.22,1,.36,1), box-shadow .25s ease, border-color .25s ease; }
.alur-stage:hover { transform: translateY(-3px); box-shadow: 0 12px 28px rgba(15,30,62,.10); border-color: #CBD6EE; }
.beban-bar { transition: width 1.1s cubic-bezier(.22,1,.36,1); }
</style>
<div class="dashboard-root w-full px-4 sm:px-6 lg:px-8 py-6">
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

        $delta = function (float $sekarang, float $lalu) {
            if ($lalu <= 0) {
                return $sekarang > 0 ? ['baru hari ini', 'text-slate-400'] : ['belum ada data', 'text-slate-400'];
            }
            $p = ($sekarang - $lalu) / $lalu * 100;
            if (abs($p) < 0.5) return ['sama seperti kemarin', 'text-slate-400'];

            return [($p > 0 ? '+' : '') . angka($p, 0) . '% vs kemarin', $p > 0 ? 'text-emerald-600' : 'text-rose-500'];
        };
        [$dOrderTeks, $dOrderKls] = $delta((float) $orderHariIni, (float) $orderKemarin);

        $totalSpk = max(1, $spkAntre + $spkDesain + $spkCetak + $spkSelesai);
        $spkDonePct = $spkSelesai / $totalSpk * 100;
        $spkProsesPct = $spkProses / $totalSpk * 100;
        $collectingPct = ($notaLengkap + $notaBelumLengkap) > 0 ? $notaLengkap / ($notaLengkap + $notaBelumLengkap) * 100 : 0;
        $lunasPct = $totalNotaAll > 0 ? max(0, min(100, (1 - $piutangCount / $totalNotaAll) * 100)) : 0;
        $C = 2 * pi() * 34; // kalibrasi keliling lingkaran gauge (r=34)
        $nadaTone = ['rose' => 'bg-rose-500', 'amber' => 'bg-amber-500', 'indigo' => 'bg-indigo-500'];
    @endphp

    {{-- ============ HEADER ============ --}}
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4 mb-5">
        <div>
            <p class="text-[11px] font-medium text-indigo-600 flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 inline-block"></span>Operasional
                <span class="text-slate-400 font-normal">· {{ now()->translatedFormat('d F Y') }}</span>
            </p>
            <h1 class="text-[26px] sm:text-[30px] leading-tight font-semibold text-slate-900 tracking-tight mt-2">Ringkasan Operasi &amp; Pesanan</h1>
            <p class="text-[13px] text-slate-500 mt-1.5 max-w-2xl">{{ $salam }}, {{ $nama }} &middot; Pantau pesanan masuk, produksi berjalan, koleksi pesanan, dan piutang tertunggak.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-slate-200 bg-white text-[12px] font-medium text-slate-600 shadow-sm">
                <i class="far fa-calendar-days text-slate-400 text-[11px]"></i>{{ now()->translatedFormat('d F Y') }}
            </span>
            <button type="button" id="btnRekapWa"
                data-rekap-order="{{ $orderHariIni }}"
                data-rekap-omzet="{{ (int) round($nilaiOrderHariIni) }}"
                data-rekap-antre="{{ $spkAntre }}"
                data-rekap-proses="{{ $spkProses }}"
                data-rekap-piutang="{{ $piutangCount }}"
                data-rekap-urgent="{{ $urgentAktif }}"
                class="inline-flex items-center gap-2 h-9 px-4 rounded-lg border border-[#BFE7CC] bg-[#25D366]/10 text-[12px] font-semibold text-[#075E33] shadow-sm hover:bg-[#25D366]/25 transition-colors" title="Kirim ringkasan hari ini ke WhatsApp Anda">
                <i class="fab fa-whatsapp text-[15px] leading-none"></i>Rekap ke WA
            </button>
            <a href="{{ $u('laporan.order') }}" class="inline-flex items-center gap-2 h-9 px-4 rounded-lg border border-slate-200 bg-white text-[12px] font-semibold text-slate-600 shadow-sm hover:border-slate-300 hover:text-slate-800 transition-colors">
                <i class="fas fa-download text-[11px]"></i>Ekspor Laporan
            </a>
        </div>
    </div>

    {{-- ============ PERHATIAN HARI INI (hanya tampil jika ada masalah) ============ --}}
    @if (isset($anomali) && $anomali->isNotEmpty())
        <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-2.5" role="alert" aria-label="Perhatian hari ini">
            <span class="inline-flex items-center gap-2 text-[12px] font-semibold text-rose-700 shrink-0"><i class="fas fa-triangle-exclamation text-[12px]"></i>Perhatian hari ini</span>
            <ul class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[12px] font-medium text-rose-800">
                @foreach ($anomali as $a)
                    @php $urlAnomali = \App\Support\JalurLanjut::boleh(route($a['route'], $a['param'])) ? route($a['route'], $a['param']) : '#'; @endphp
                    <li><a href="{{ $urlAnomali }}" class="underline underline-offset-2 decoration-rose-300 hover:text-rose-900">{{ $a['label'] }}</a></li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ============ KPI CARDS (4 kartu, gauge lingkaran beranimasi saat reload) ============ --}}
    @php
        $kpi = [
            ['label' => 'Pesanan Hari Ini', 'value' => (string) $orderHariIni, 'unit' => 'nota', 'sub' => 'Rp ' . number_format($nilaiOrderHariIni, 0, ',', '.'), 'pct' => $spkDonePct, 'arc' => 'gauge-arc--ok', 'footL' => 'SPK selesai', 'footR' => $dOrderTeks, 'footKls' => $dOrderKls, 'url' => $u('penjualan.riwayat')],
            ['label' => 'Dalam Produksi', 'value' => (string) $spkProses, 'unit' => 'item', 'sub' => 'desain ' . $spkDesain . ' · cetak ' . $spkCetak, 'pct' => $spkProsesPct, 'arc' => 'gauge-arc--indigo', 'footL' => 'Sedang dikerjakan', 'footR' => 'antre ' . $spkAntre, 'footKls' => 'text-slate-500', 'url' => $u('spk.index')],
            ['label' => 'Siap Diambil', 'value' => (string) $notaLengkap, 'unit' => 'nota', 'sub' => $notaBelumLengkap . ' nota menunggu pelengkap', 'pct' => $collectingPct, 'arc' => 'gauge-arc--amber', 'footL' => 'Menunggu finalisasi', 'footR' => $notaBelumLengkap . ' belum lengkap', 'footKls' => 'text-slate-500', 'url' => $u('collecting.index', ['status_pengerjaan' => 'lengkap'])],
            ['label' => 'Piutang Belum Lunas', 'value' => 'Rp ' . number_format($piutang, 0, ',', '.'), 'unit' => '', 'sub' => $piutangCount . ' nota belum lunas', 'pct' => $lunasPct, 'arc' => 'gauge-arc--rose', 'footL' => 'Nota lunas keseluruhan', 'footR' => $piutangCount . ' dari ' . $totalNotaAll, 'footKls' => 'text-slate-500', 'url' => $u('laporan.piutang')],
        ];
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
        @foreach ($kpi as $k)
            <a href="{{ $k['url'] }}" class="group rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-[0_1px_3px_rgba(15,30,62,.06)] hover:shadow-[0_10px_24px_rgba(15,30,62,.08)] hover:border-slate-300/70 transition-all duration-200">
                <p class="text-[11px] font-medium text-slate-500">{{ $k['label'] }}</p>
                <div class="mt-2 flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[22px] sm:text-[26px] leading-none font-semibold text-slate-900 tracking-tight tabular-nums truncate">{{ $k['value'] }}<span class="text-[11px] font-medium text-slate-400 ml-1">{{ $k['unit'] }}</span></p>
                        <p class="text-[11px] text-slate-500 mt-1.5 truncate">{{ $k['sub'] }}</p>
                    </div>
                    <svg class="shrink-0" width="62" height="62" viewBox="0 0 80 80" role="img" aria-label="{{ round($k['pct']) }} persen">
                        <circle class="gauge-track" cx="40" cy="40" r="34" stroke-width="8" />
                        <circle class="gauge-arc {{ $k['arc'] }}" cx="40" cy="40" r="34" stroke-width="8" stroke-linecap="round"
                                stroke-dasharray="{{ $C }}" stroke-dashoffset="{{ $C }}" data-offset="{{ $C * (1 - min(100, max(0, $k['pct'])) / 100) }}" transform="rotate(-90 40 40)" />
                        <text class="gauge-text" x="40" y="42" text-anchor="middle">{{ round($k['pct']) }}<tspan font-size="9">%</tspan></text>
                    </svg>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between gap-2 text-[10px]">
                    <span class="text-slate-400 truncate">{{ $k['footL'] }}</span>
                    <span class="font-semibold whitespace-nowrap {{ $k['footKls'] }}">{{ $k['footR'] }}</span>
                </div>
            </a>
        @endforeach
    </div>

    {{-- ============ SIKLUS ALUR PRODUKSI (5 tahap) ============ --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_4px_rgba(15,30,62,.05)] overflow-hidden mb-6">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 sm:px-6 py-4">
            <div class="flex items-baseline gap-3">
                <h2 class="text-[13.5px] font-semibold text-slate-800">Siklus Alur Produksi</h2>
                <span class="text-[10.5px] text-slate-400 hidden sm:inline">Lima tahap pesanan, dari antre sampai selesai</span>
            </div>
            <a href="{{ $u('spk.index') }}" class="text-[11px] font-semibold text-indigo-600 hover:text-indigo-700 transition-colors">Antrean SPK &rarr;</a>
        </div>
        <div class="px-4 sm:px-6 py-5">
            @php
                $tahap = [
                    ['n' => '01', 'label' => 'Antre', 'count' => $spkAntre, 'dot' => 'bg-amber-500', 'desc' => 'Pesanan baru masuk', 'url' => $u('spk.index', ['status_spk' => 'Antre'])],
                    ['n' => '02', 'label' => 'Desain', 'count' => $spkDesain, 'dot' => 'bg-sky-500', 'desc' => 'Sedang didesain / revisi', 'url' => $u('spk.index', ['status_spk' => 'Desain'])],
                    ['n' => '03', 'label' => 'Cetak', 'count' => $spkCetak, 'dot' => 'bg-indigo-500', 'desc' => 'Sedang dicetak / dikerjakan', 'url' => $u('produksi.index')],
                    ['n' => '04', 'label' => 'Collecting', 'count' => $notaLengkap, 'dot' => 'bg-orange-500', 'desc' => 'Lengkap, menunggu diambil', 'url' => $u('collecting.index', ['status_pengerjaan' => 'lengkap'])],
                    ['n' => '05', 'label' => 'Selesai', 'count' => $spkSelesai, 'dot' => 'bg-emerald-500', 'desc' => 'Sudah diserahkan', 'url' => $u('produksi.index', ['status_produksi' => 'selesai'])],
                ];
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 sm:gap-3">
                @foreach ($tahap as $t)
                    <a href="{{ $t['url'] }}" title="{{ $t['desc'] }}" class="alur-stage rounded-xl border border-slate-100 bg-slate-50/60 hover:bg-white px-3 sm:px-4 py-3 sm:py-4 flex flex-col focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400">
                        <span class="flex items-center justify-between gap-2">
                            <span class="text-[10px] font-medium text-slate-500">Tahap {{ $t['n'] }}</span>
                            <span class="w-2 h-2 rounded-full {{ $t['dot'] }}"></span>
                        </span>
                        <p class="text-[12px] font-semibold text-slate-500 mt-2.5">{{ $t['label'] }}</p>
                        <p class="text-[26px] sm:text-[30px] leading-none font-semibold text-slate-900 tabular-nums tracking-tight mt-1">{{ $t['count'] }}</p>
                        <p class="text-[10.5px] text-slate-400 mt-1.5 leading-snug hidden sm:block">{{ $t['desc'] }}</p>
                    </a>
                @endforeach
            </div>
            <div class="mt-4 sm:mt-5">
                <div class="flex h-2 rounded-full overflow-hidden bg-slate-100" title="Proporsi item SPK: antre / desain / cetak / selesai">
                    <div class="bg-amber-400" style="width: {{ max(2, round($spkAntre / $totalSpk * 100)) }}%"></div>
                    <div class="bg-sky-400" style="width: {{ max(2, round($spkDesain / $totalSpk * 100)) }}%"></div>
                    <div class="bg-indigo-500" style="width: {{ max(2, round($spkCetak / $totalSpk * 100)) }}%"></div>
                    <div class="bg-emerald-500" style="width: {{ max(2, round($spkSelesai / $totalSpk * 100)) }}%"></div>
                </div>
                <p class="text-[10.5px] font-medium text-slate-500 mt-2">{{ $spkSelesai }} dari {{ $totalSpk }} item SPK sudah selesai diproduksi.</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 sm:gap-5 mb-6">
        {{-- ============ BEBAN PRODUKSI PER MESIN (data SPK aktif nyata) ============ --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_4px_rgba(15,30,62,.05)] overflow-hidden flex flex-col">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 sm:px-6 py-4">
                <div class="flex items-baseline gap-3">
                    <h2 class="text-[13.5px] font-semibold text-slate-800">Beban Produksi per Mesin</h2>
                    <span class="text-[10.5px] text-slate-400 hidden md:inline">SPK aktif saja</span>
                </div>
                <a href="{{ $u('produksi.index') }}" class="text-[11px] font-semibold text-indigo-600 hover:text-indigo-700 transition-colors">Ke Produksi &rarr;</a>
            </div>
            <div class="px-5 sm:px-6 py-5">
                @if ($bebanMesin->isNotEmpty())
                    <div class="flex flex-col gap-4">
                        @foreach ($bebanMesin as $m)
                            <div>
                                <div class="flex items-center justify-between gap-3 text-[12px]">
                                    <span class="font-medium text-slate-700 truncate">{{ $m->nama_mesin }}</span>
                                    <span class="font-semibold text-slate-900 tabular-nums shrink-0">{{ $m->jumlah }} item</span>
                                </div>
                                <div class="h-2.5 mt-1.5 rounded-full bg-slate-100 overflow-hidden">
                                    <div class="beban-bar h-full rounded-full bg-indigo-500" style="width: {{ max(6, round($m->jumlah / $bebanMesinMaks * 100, 1)) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-10 text-center">
                        <i class="fas fa-industry text-slate-300 text-2xl mb-3 block"></i>
                        <p class="text-[12.5px] font-medium text-slate-500">Belum ada SPK aktif saat ini.</p>
                        <p class="text-[10.5px] text-slate-400 mt-1">Bar ini akan terisi otomatis selama ada SPK berstatus antre, desain, atau cetak.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- ============ PERLU PERHATIAN ============ --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_4px_rgba(15,30,62,.05)] overflow-hidden flex flex-col">
            <div class="border-b border-slate-100 px-5 sm:px-6 py-4 flex items-center justify-between gap-3">
                <h2 class="text-[13.5px] font-semibold text-slate-800">Perlu Perhatian</h2>
                <span class="text-[11px] font-medium text-slate-500">{{ count($perluTindakan) }} menunggu</span>
            </div>
            <div class="divide-y divide-slate-50 max-h-[26rem] overflow-y-auto flex-1">
                @forelse($perluTindakan as $it)
                    <a href="{{ $it['url'] }}" title="Buka: {{ $it['label'] }}" class="flex items-start gap-3 px-5 sm:px-6 py-4 hover:bg-[#FAFCFF] transition-colors group">
                        <span class="mt-1 w-2 h-2 rounded-full {{ $nadaTone[$it['tone'] ?? 'indigo'] ?? 'bg-indigo-500' }} shrink-0"></span>
                        <span class="flex-1 text-[12.5px] text-slate-700 font-medium leading-snug group-hover:text-slate-900 transition-colors">{{ $it['label'] }}</span>
                        <i class="fas fa-chevron-right text-[10px] text-slate-300 group-hover:text-indigo-500 mt-0.5 transition-colors"></i>
                    </a>
                @empty
                    <div class="px-6 py-12 text-center">
                        <i class="fas fa-circle-check text-emerald-400 text-2xl mb-2 block"></i>
                        <p class="text-[12px] font-medium text-slate-500">Semua beres.</p>
                        <p class="text-[10.5px] text-slate-400 mt-1">Tidak ada pekerjaan yang menunggu tindakan.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ============ TRANSAKSI TERBARU ============ --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_4px_rgba(15,30,62,.05)] overflow-hidden flex flex-col min-w-0">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 sm:px-6 py-4">
            <div class="flex items-baseline gap-3">
                <h2 class="text-[13.5px] font-semibold text-slate-800">Transaksi terbaru</h2>
                <span class="text-[10.5px] text-slate-400 hidden sm:inline">Daftar nota dan pembayaran terakhir</span>
            </div>
            <div class="flex items-center gap-2">
                <label class="relative flex items-center">
                    <i class="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[10px] text-slate-400 pointer-events-none"></i>
                    <input type="text" id="trxCari" placeholder="Cari nota..." aria-label="Cari nota atau konsumen" autocomplete="off" class="h-9 w-32 pl-8 pr-2 rounded-lg border border-slate-200 bg-slate-50/60 text-[11.5px] text-slate-700 placeholder:text-slate-400 focus:outline-none focus:border-indigo-400 focus:bg-white transition-colors">
                </label>
                <a href="{{ $u('penjualan.riwayat') }}" class="inline-flex items-center gap-1 h-9 px-3 rounded-lg border border-slate-200 text-[11px] font-semibold text-slate-500 hover:text-slate-700 hover:border-slate-300 transition-colors whitespace-nowrap">Lihat semua <i class="fas fa-arrow-right text-[9px]"></i></a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-[11px] table-fixed">
                <thead>
                    <tr class="text-[10px] font-medium text-slate-500 bg-[#F7F9FE]">
                        <th class="px-3 py-2.5 w-[30%]">Nota</th>
                        <th class="px-3 py-2.5 w-[27%]">Konsumen</th>
                        <th class="px-3 py-2.5 text-right w-[22%]">Total</th>
                        <th class="px-3 py-2.5 text-right w-[21%]">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($orderTerbaru as $o)
                        <tr class="trx-row hover:bg-[#FAFCFF] transition-colors">
                            <td class="px-3 py-3"><span class="text-[10.5px] font-semibold text-slate-700 tabular-nums whitespace-nowrap">{{ $o->nomor_nota }}</span></td>
                            <td class="px-3 py-3 font-medium text-slate-700"><span class="block truncate">{{ $o->nama_konsumen ?? 'Tanpa nama' }}</span></td>
                            <td class="px-3 py-3 text-right font-semibold text-slate-800 tabular-nums whitespace-nowrap">Rp {{ number_format((float) $o->total_bayar, 0, ',', '.') }}</td>
                            <td class="px-3 py-3 text-right">@if($o->status_pembayaran === 'Lunas')<span class="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[10px] font-medium text-emerald-700">Lunas</span>@elseif($o->status_pembayaran === 'DP')<span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-200 px-2 py-0.5 text-[10px] font-medium text-amber-800">DP</span>@else<span class="inline-flex items-center rounded-full bg-rose-50 border border-rose-200 px-2 py-0.5 text-[10px] font-medium text-rose-600">Belum</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-12 text-center text-[11.5px] text-slate-400">Belum ada transaksi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
// Filter tabel Transaksi Terbaru berdasarkan ketikan pada kotak cari.
document.addEventListener('DOMContentLoaded', function () {
    var cari = document.getElementById('trxCari');
    if (!cari) return;
    cari.addEventListener('input', function () {
        var q = cari.value.trim().toLowerCase();
        document.querySelectorAll('.trx-row').forEach(function (r) {
            var cocok = !q || r.textContent.toLowerCase().indexOf(q) !== -1;
            r.classList.toggle('hidden', !cocok);
        });
    });

    // Animasi gauge lingkaran saat halaman dimuat (menghormati prefers-reduced-motion).
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.querySelectorAll('.gauge-arc').forEach(function (el, i) {
        var target = parseFloat(el.getAttribute('data-offset') || '0');
        if (reduce) { el.style.strokeDashoffset = String(target); return; }
        requestAnimationFrame(function () {
            setTimeout(function () { el.style.strokeDashoffset = String(target); }, 80 + i * 120);
        });
    });

    // Kirim rekap hari ini ke WhatsApp (pesan terisi, lewat WhatsApp share).
    var btnWa = document.getElementById('btnRekapWa');
    if (btnWa) btnWa.addEventListener('click', function () {
        var b = this;
        var d = new Date();
        var fmt = d.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        var rupiah = function (n) { return Number(n || 0).toLocaleString('id-ID'); };
        var teks = 'Rekap Orion Grafika - ' + fmt + '\n'
            + '- Order hari ini: ' + (b.dataset.rekapOrder || '0') + ' nota (Rp ' + rupiah(b.dataset.rekapOmzet) + ')\n'
            + '- Antre produksi: ' + (b.dataset.rekapAntre || '0') + ' item\n'
            + '- Sedang dikerjakan: ' + (b.dataset.rekapProses || '0') + ' item\n'
            + '- Urgent aktif: ' + (b.dataset.rekapUrgent || '0') + ' item\n'
            + '- Piutang belum lunas: ' + (b.dataset.rekapPiutang || '0') + ' nota';
        window.open('https://wa.me/?text=' + encodeURIComponent(teks), '_blank', 'noopener');
    });
});
</script>
@endpush
@endsection
