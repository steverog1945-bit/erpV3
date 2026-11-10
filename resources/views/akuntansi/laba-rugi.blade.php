@extends('layouts.app')

@section('content')
<style>
    @media print {
        aside, .no-print { display: none !important; }
        main { margin-left: 0 !important; padding: 28px 32px !important; }
        body { background: #fff !important; }
    }
</style>
@include('partials.print-report-header', ['docTitle' => 'Laporan Laba Rugi', 'tglDari' => $awal, 'tglSampai' => $akhir])

<div class="w-full px-4 sm:px-6 lg:px-8 py-10 print:pt-0 max-w-[1600px] mx-auto">
    <div class="print:hidden">
        <x-page-header title="Laporan Laba Rugi" subtitle="Penjualan dikurangi HPP (Laba Kotor), dikurangi Beban Usaha (Laba Bersih), untuk periode yang dipilih.">
            <x-slot:actions>
                <x-button type="button" onclick="window.print()" variant="secondary" class="no-print">
                    <i class="fas fa-print"></i>
                    Cetak
                </x-button>
            </x-slot:actions>
        </x-page-header>
    </div>

    <div class="no-print">
        @include('akuntansi._subnav')
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-6 no-print">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2">Dari Tanggal</label>
                <input type="date" name="awal" value="{{ $awal }}" class="bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
            </div>
            <div>
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2">Sampai Tanggal</label>
                <input type="date" name="akhir" value="{{ $akhir }}" class="bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
            </div>
            <button type="submit" class="px-6 py-3 bg-indigo-600 text-white rounded-xl text-[11px] font-medium uppercase tracking-widest hover:bg-indigo-700">
                <i class="fas fa-filter mr-1"></i> Tampilkan
            </button>
        </form>
    </div>

    <div class="max-w-3xl mx-auto bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden print:hidden">
        <div class="px-10 py-6 border-b border-slate-100 text-center">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Periode</p>
            <p class="text-sm font-medium text-slate-700 mt-1">{{ \Carbon\Carbon::parse($awal)->translatedFormat('d M Y') }} s/d {{ \Carbon\Carbon::parse($akhir)->translatedFormat('d M Y') }}</p>
        </div>

        <div class="p-10 space-y-6">
            {{-- PENJUALAN --}}
            <div>
                <p class="text-xs font-medium text-emerald-600 uppercase tracking-widest mb-4">Penjualan</p>
                <div class="space-y-2">
                    @forelse($pendapatan as $p)
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-600">{{ $p->nama_akun }}</span>
                        <span class="font-medium text-slate-700">{{ number_format($p->jumlah, 0, ',', '.') }}</span>
                    </div>
                    @empty
                    <p class="text-xs text-slate-400 italic">Belum ada penjualan pada periode ini.</p>
                    @endforelse
                </div>
                <div class="flex justify-between text-sm font-medium border-t border-slate-100 mt-4 pt-3">
                    <span class="text-slate-700">Total Penjualan</span>
                    <span class="text-emerald-700">{{ number_format($totalPendapatan, 0, ',', '.') }}</span>
                </div>
            </div>

            {{-- HPP --}}
            <div>
                <p class="text-xs font-medium text-rose-500 uppercase tracking-widest mb-4">HPP (Harga Pokok Penjualan)</p>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-600">Bahan baku terpakai (harga pembelian rata-rata)</span>
                    <span class="font-medium text-slate-700">{{ number_format($totalHpp, 0, ',', '.') }}</span>
                </div>
            </div>

            {{-- LABA KOTOR --}}
            <div class="rounded-2xl p-5 bg-sky-50 border border-sky-100">
                <div class="flex justify-between items-center">
                    <span class="text-xs font-medium text-sky-700 uppercase tracking-widest">Laba Kotor</span>
                    <span class="text-lg font-medium text-sky-700">{{ number_format($labaKotor, 0, ',', '.') }}</span>
                </div>
            </div>

            {{-- BEBAN USAHA --}}
            <div>
                <p class="text-xs font-medium text-amber-600 uppercase tracking-widest mb-4">Beban Usaha</p>
                <div class="space-y-2">
                    @forelse($bebanUsaha as $b)
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-600">{{ $b->nama_akun }}</span>
                        <span class="font-medium text-slate-700">{{ number_format($b->jumlah, 0, ',', '.') }}</span>
                    </div>
                    @empty
                    <p class="text-xs text-slate-400 italic">Belum ada beban usaha tercatat pada periode ini.</p>
                    @endforelse
                </div>
                <div class="flex justify-between text-sm font-medium border-t border-slate-100 mt-4 pt-3">
                    <span class="text-slate-700">Total Beban Usaha</span>
                    <span class="text-amber-700">{{ number_format($totalBebanUsaha, 0, ',', '.') }}</span>
                </div>
            </div>

            {{-- LABA BERSIH & PERSENTASE --}}
            <div class="rounded-2xl p-6 {{ $labaBersih >= 0 ? 'bg-emerald-50 border border-emerald-100' : 'bg-rose-50 border border-rose-100' }}">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium uppercase tracking-widest {{ $labaBersih >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                        {{ $labaBersih >= 0 ? 'Laba Bersih' : 'Rugi Bersih' }}
                    </span>
                    <span class="text-2xl font-medium {{ $labaBersih >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                        {{ number_format(abs($labaBersih), 0, ',', '.') }}
                    </span>
                </div>
                <div class="flex justify-between items-center mt-2 pt-2 border-t {{ $labaBersih >= 0 ? 'border-emerald-100' : 'border-rose-100' }}">
                    <span class="text-[11px] font-medium uppercase tracking-widest {{ $labaBersih >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">Persentase Laba</span>
                    <span class="text-sm font-medium {{ $labaBersih >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ number_format($persentaseLaba, 2, ',', '.') }}%</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Versi cetak - pola print-table/resume-table yang sama dengan
         laporan lain, bukan kartu rounded/shadow di layar. --}}
    <div class="hidden print:block">
        <x-laporan.print-table title="Penjualan" :headers="['Akun', 'Jumlah']" :align="['left', 'right']">
            @forelse($pendapatan as $p)
            <tr>
                <td class="px-3 py-1.5 text-slate-700">{{ $p->nama_akun }}</td>
                <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($p->jumlah, 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td class="px-3 py-1.5 text-slate-400 italic" colspan="2">Belum ada penjualan pada periode ini.</td>
            </tr>
            @endforelse
            <tr class="bg-slate-50 border-t border-slate-200">
                <td class="px-3 py-1.5 font-medium text-slate-700 uppercase">Total Penjualan</td>
                <td class="px-3 py-1.5 text-right font-medium text-slate-800">{{ number_format($totalPendapatan, 0, ',', '.') }}</td>
            </tr>
        </x-laporan.print-table>

        <x-laporan.print-table title="Beban Usaha" :headers="['Akun', 'Jumlah']" :align="['left', 'right']">
            @forelse($bebanUsaha as $b)
            <tr>
                <td class="px-3 py-1.5 text-slate-700">{{ $b->nama_akun }}</td>
                <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($b->jumlah, 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td class="px-3 py-1.5 text-slate-400 italic" colspan="2">Belum ada beban usaha tercatat pada periode ini.</td>
            </tr>
            @endforelse
            <tr class="bg-slate-50 border-t border-slate-200">
                <td class="px-3 py-1.5 font-medium text-slate-700 uppercase">Total Beban Usaha</td>
                <td class="px-3 py-1.5 text-right font-medium text-slate-800">{{ number_format($totalBebanUsaha, 0, ',', '.') }}</td>
            </tr>
        </x-laporan.print-table>

        <x-laporan.resume-table title="Ringkasan" :rows="[
            'Total Penjualan' => number_format($totalPendapatan, 0, ',', '.'),
            'HPP (Harga Pokok Penjualan)' => number_format($totalHpp, 0, ',', '.'),
            'Laba Kotor' => number_format($labaKotor, 0, ',', '.'),
            'Total Beban Usaha' => number_format($totalBebanUsaha, 0, ',', '.'),
            ($labaBersih >= 0 ? 'Laba Bersih' : 'Rugi Bersih') => number_format(abs($labaBersih), 0, ',', '.'),
            'Persentase Laba' => number_format($persentaseLaba, 2, ',', '.') . '%',
        ]" />
    </div>
</div>
@endsection
