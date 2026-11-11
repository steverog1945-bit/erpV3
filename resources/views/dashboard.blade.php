@extends('layouts.app')

@section('content')
<div class="dashboard-root w-full px-3 sm:px-6 lg:px-8 py-6 max-w-[1600px] mx-auto">
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
        $nadaTone = ['rose' => 'bg-rose-500', 'amber' => 'bg-amber-500', 'indigo' => 'bg-indigo-500'];
    @endphp

    {{-- ============ HEADER: eyebrow + judul + aksi bawah ============ --}}
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4 mb-6">
        <div>
            <p class="text-[10px] font-bold text-indigo-600 uppercase tracking-[0.22em] flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-indigo-500 inline-block"></span>Operasional</p>
            <h1 class="text-[27px] md:text-[32px] font-semibold text-slate-900 tracking-tight mt-2">Ringkasan operasional</h1>
            <p class="text-[13px] text-slate-400 mt-1">{{ $salam }}, {{ $nama }} &middot; Pantau pekerjaan dan transaksi Orion Grafika.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-2 h-9 pl-3 pr-3.5 rounded-lg border border-slate-200 bg-white text-[12px] font-medium text-slate-600 shadow-[0_1px_2px_rgba(15,30,62,.04)]">
                <i class="far fa-calendar-days text-slate-400 text-[11px]"></i>{{ now()->translatedFormat('d F Y') }}
            </span>
            <a href="{{ route('laporan.order') }}" class="inline-flex items-center gap-2 h-9 px-4 rounded-lg border border-slate-200 bg-white text-[12px] font-semibold text-slate-600 shadow-[0_1px_2px_rgba(15,30,62,.04)] hover:border-slate-300 hover:text-slate-800 transition-colors">
                <i class="fas fa-download text-[11px]"></i>Ekspor
            </a>
        </div>
    </div>

    {{-- ============ KPI CARDS (4 kartu, divider rambut + catatan kiri/kanan) ============ --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
        <a href="{{ $u('penjualan.riwayat') }}" class="group rounded-2xl border border-slate-200/70 bg-white px-5 py-4 shadow-[0_1px_3px_rgba(15,30,62,.05)] hover:shadow-[0_8px_20px_rgba(15,30,62,.07)] hover:border-slate-300/70 transition-all duration-200">
            <p class="text-[9.5px] font-bold text-slate-400 uppercase tracking-[0.14em]">Pesanan Hari Ini</p>
            <p class="text-[30px] leading-none font-semibold text-slate-900 mt-2 tracking-tight tabular-nums">{{ $orderHariIni }}</p>
            <p class="text-[11px] font-medium text-slate-500 mt-1.5">Rp {{ number_format($nilaiOrderHariIni, 0, ',', '.') }}</p>
            <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                <span class="text-[10px] text-slate-400">Total dalam proses cetak</span>
                <span class="text-[10px] font-semibold {{ $dOrderKls }}">{{ $dOrderTeks }}</span>
            </div>
        </a>

        <a href="{{ $u('spk.index') }}" class="group rounded-2xl border border-slate-200/70 bg-white px-5 py-4 shadow-[0_1px_3px_rgba(15,30,62,.05)] hover:shadow-[0_8px_20px_rgba(15,30,62,.07)] hover:border-slate-300/70 transition-all duration-200">
            <p class="text-[9.5px] font-bold text-slate-400 uppercase tracking-[0.14em]">Dalam Produksi</p>
            <p class="text-[30px] leading-none font-semibold text-slate-900 mt-2 tracking-tight tabular-nums">{{ $spkProses }}</p>
            <p class="text-[11px] font-medium text-slate-500 mt-1.5">Sedang dikerjakan di produksi</p>
            <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                <span class="text-[10px] text-slate-400">Detail per divisi</span>
                <span class="text-[10px] font-semibold text-slate-500">{{ $spkDesain }} desain &middot; {{ $spkCetak }} cetak</span>
            </div>
        </a>

        <a href="{{ $u('collecting.index', ['status_pengerjaan' => 'lengkap']) }}" class="group rounded-2xl border border-slate-200/70 bg-white px-5 py-4 shadow-[0_1px_3px_rgba(15,30,62,.05)] hover:shadow-[0_8px_20px_rgba(15,30,62,.07)] hover:border-slate-300/70 transition-all duration-200">
            <p class="text-[9.5px] font-bold text-slate-400 uppercase tracking-[0.14em]">Siap Diambil</p>
            <p class="text-[30px] leading-none font-semibold text-slate-900 mt-2 tracking-tight tabular-nums">{{ $notaLengkap }}</p>
            <p class="text-[11px] font-medium text-slate-500 mt-1.5">Pesanan selesai dicetak</p>
            <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                <span class="text-[10px] text-slate-400">Menunggu finalisasi</span>
                <span class="text-[10px] font-semibold text-slate-500">{{ $notaBelumLengkap }} nota belum lengkap</span>
            </div>
        </a>

        <a href="{{ $u('laporan.piutang') }}" class="group rounded-2xl border border-slate-200/70 bg-white px-5 py-4 shadow-[0_1px_3px_rgba(15,30,62,.05)] hover:shadow-[0_8px_20px_rgba(15,30,62,.07)] hover:border-slate-300/70 transition-all duration-200">
            <p class="text-[9.5px] font-bold text-slate-400 uppercase tracking-[0.14em]">Belum Dibayar</p>
            <p class="text-[26px] leading-none font-semibold text-slate-900 mt-2 tracking-tight tabular-nums">Rp {{ number_format($piutang, 0, ',', '.') }}</p>
            <p class="text-[11px] font-medium text-slate-500 mt-1.5">Total tagihan belum lunas</p>
            <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                <span class="text-[10px] text-slate-400">Belum lunas</span>
                <span class="text-[10px] font-semibold text-slate-500">{{ $piutangCount }} nota</span>
            </div>
        </a>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 sm:gap-5 mb-6">
        {{-- ============ ALUR PEKERJAAN (5 tahap) ============ --}}
        <div class="xl:col-span-2 rounded-2xl border border-slate-200/70 bg-white shadow-[0_1px_4px_rgba(15,30,62,.05)] overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 sm:px-6 py-4">
                <div class="flex items-baseline gap-3">
                    <h2 class="text-[13.5px] font-semibold text-slate-800">Alur pekerjaan</h2>
                    <span class="text-[10.5px] text-slate-400 hidden sm:inline">Ringkasan proses pesanan hari ini</span>
                </div>
                <a href="{{ $u('spk.index') }}" class="text-[10.5px] font-semibold text-indigo-600 hover:text-indigo-700 transition-colors">Antrean SPK &rarr;</a>
            </div>
            <div class="px-5 sm:px-6 pt-6 pb-5">
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-y-6 gap-x-4">
                    @php
                        $tahap = [
                            ['label' => 'SPK Baru', 'n' => $spkAntre, 'warna' => 'bg-amber-500', 'desc' => 'Pesanan baru diterima', 'url' => $u('spk.index', ['status_spk' => 'Antre'])],
                            ['label' => 'Desain', 'n' => $spkDesain, 'warna' => 'bg-sky-500', 'desc' => 'Dalam proses desain &amp; revisi', 'url' => $u('spk.index', ['status_spk' => 'Desain'])],
                            ['label' => 'Produksi', 'n' => $spkCetak, 'warna' => 'bg-indigo-500', 'desc' => 'Sedang dicetak / dikerjakan', 'url' => $u('produksi.index')],
                            ['label' => 'Collecting', 'n' => $notaLengkap, 'warna' => 'bg-orange-500', 'desc' => 'Menunggu finalisasi &amp; packing', 'url' => $u('collecting.index', ['status_pengerjaan' => 'lengkap'])],
                            ['label' => 'Selesai', 'n' => $spkSelesai, 'warna' => 'bg-emerald-500', 'desc' => 'Item telah selesai', 'url' => $u('produksi.index', ['status_produksi' => 'selesai'])],
                        ];
                    @endphp
                    @foreach($tahap as $i => $t)
                        <a href="{{ $t['url'] }}" title="{{ $t['desc'] }}" class="group flex flex-col {{ $i > 0 ? 'sm:border-l sm:border-slate-100 sm:pl-4' : '' }}">
                            <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full {{ $t['warna'] }}"></span><span class="text-[9px] font-bold text-slate-400 uppercase tracking-[0.13em] group-hover:text-slate-600 transition-colors">{{ $t['label'] }}</span></span>
                            <span class="text-[30px] leading-none font-semibold text-slate-900 mt-2 tabular-nums">{{ $t['n'] }}</span>
                            <span class="text-[10px] text-slate-400 mt-1.5 leading-snug">{{ $t['desc'] }}</span>
                        </a>
                    @endforeach
                </div>

                {{-- Proporsi item SPK --}}
                <div class="mt-7">
                    <div class="flex h-2.5 rounded-full overflow-hidden bg-slate-100" title="Proporsi item SPK: antre / desain / cetak / selesai">
                        <div class="bg-amber-400" style="width: {{ max(2, round($spkAntre / $totalSpk * 100)) }}%"></div>
                        <div class="bg-sky-400" style="width: {{ max(2, round($spkDesain / $totalSpk * 100)) }}%"></div>
                        <div class="bg-indigo-500" style="width: {{ max(2, round($spkCetak / $totalSpk * 100)) }}%"></div>
                        <div class="bg-emerald-500" style="width: {{ max(2, round($spkSelesai / $totalSpk * 100)) }}%"></div>
                    </div>
                    <p class="text-[10.5px] text-slate-400 mt-2">{{ $spkSelesai }} dari {{ $totalSpk }} item SPK sudah selesai diproduksi.</p>
                </div>
            </div>
        </div>

        {{-- ============ PERLU PERHATIAN (daftar aksi, dinamis) ============ --}}
        <div class="rounded-2xl border border-slate-200/70 bg-white shadow-[0_1px_4px_rgba(15,30,62,.05)] overflow-hidden flex flex-col">
            <div class="border-b border-slate-100 px-5 sm:px-6 py-4 flex items-center justify-between">
                <h2 class="text-[13.5px] font-semibold text-slate-800">Perlu perhatian</h2>
                <span class="text-[9.5px] font-bold uppercase tracking-[0.15em] text-slate-400">{{ count($perluTindakan) }} Aktif</span>
            </div>
            <div class="divide-y divide-slate-50 max-h-[24rem] overflow-y-auto flex-1">
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
    </div>

    {{-- ============ TRANSAKSI TERBARU (tabel, filter cari sisi klien) ============ --}}
    <div class="rounded-2xl border border-slate-200/70 bg-white shadow-[0_1px_4px_rgba(15,30,62,.05)] overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 sm:px-6 py-4">
            <div class="flex items-baseline gap-3">
                <h2 class="text-[13.5px] font-semibold text-slate-800">Transaksi terbaru</h2>
                <span class="text-[10.5px] text-slate-400 hidden sm:inline">Daftar nota dan pembayaran terakhir</span>
            </div>
            <div class="flex items-center gap-2">
                <label class="relative flex items-center">
                    <i class="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[10px] text-slate-400 pointer-events-none"></i>
                    <input type="text" id="trxCari" placeholder="Cari nota atau konsumen..." aria-label="Cari nota atau konsumen" autocomplete="off" class="h-9 w-44 sm:w-56 pl-8 pr-3 rounded-lg border border-slate-200 bg-slate-50/60 text-[12px] text-slate-700 placeholder:text-slate-400 focus:outline-none focus:border-indigo-400 focus:bg-white transition-colors">
                </label>
                <a href="{{ $u('penjualan.riwayat') }}" class="inline-flex items-center gap-1 h-9 px-3 rounded-lg border border-slate-200 text-[11px] font-semibold text-slate-500 hover:text-slate-700 hover:border-slate-300 transition-colors whitespace-nowrap">Lihat semua <i class="fas fa-arrow-right text-[9px]"></i></a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-[12.5px] min-w-[560px]">
                <thead>
                    <tr class="text-[9.5px] uppercase tracking-[0.13em] text-slate-400 bg-[#F7F9FE]">
                        <th class="px-6 py-3 font-bold">Nota</th>
                        <th class="px-6 py-3 font-bold">Konsumen</th>
                        <th class="px-6 py-3 font-bold text-right">Total</th>
                        <th class="px-6 py-3 font-bold text-right">Pembayaran</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($orderTerbaru as $o)
                        <tr class="trx-row hover:bg-[#FAFCFF] transition-colors">
                            <td class="px-6 py-3.5"><span class="font-mono text-[11px] font-semibold text-slate-700">{{ $o->nomor_nota }}</span></td>
                            <td class="px-6 py-3.5 font-medium text-slate-700">{{ $o->nama_konsumen ?? 'Tanpa nama' }}</td>
                            <td class="px-6 py-3.5 text-right font-semibold text-slate-800 tabular-nums">Rp {{ number_format((float) $o->total_bayar, 0, ',', '.') }}</td>
                            <td class="px-6 py-3.5 text-right">@if($o->status_pembayaran === 'Lunas')<span class="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-1 text-[9.5px] font-bold uppercase tracking-wide text-emerald-700">Lunas</span>@elseif($o->status_pembayaran === 'DP')<span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-200 px-2.5 py-1 text-[9.5px] font-bold uppercase tracking-wide text-amber-800">DP</span>@else<span class="inline-flex items-center rounded-full bg-rose-50 border border-rose-200 px-2.5 py-1 text-[9.5px] font-bold uppercase tracking-wide text-rose-600">Belum Bayar</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-12 text-center text-[12px] text-slate-400">Belum ada transaksi.</td></tr>
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
});
</script>
@endpush
@endsection
