@extends('layouts.app')

@section('content')
<style>
    @media print {
        aside, .no-print { display: none !important; }
        main { margin-left: 0 !important; padding: 28px 32px !important; }
        body { background: #fff !important; }
    }
</style>
@include('partials.print-report-header', ['docTitle' => 'Perbandingan Pemasukan dan Pengeluaran', 'tglDari' => $awal, 'tglSampai' => $akhir])

@php $rp = fn ($v) => number_format($v, 0, ',', '.'); @endphp

<div class="w-full px-4 sm:px-6 lg:px-8 py-10 print:pt-0 max-w-[1600px] mx-auto">
    <div class="print:hidden">
        <x-page-header title="Perbandingan Pemasukan dan Pengeluaran" subtitle="Pemasukan dari Input Order dibandingkan dengan pengeluaran dari Kas Kecil dan biaya yang dibayar langsung pemilik.">
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
                <input type="date" name="awal" value="{{ $awal }}" class="bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-700 focus:ring-4 focus:ring-indigo-500/10 shadow-inner">
            </div>
            <div>
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2">Sampai Tanggal</label>
                <input type="date" name="akhir" value="{{ $akhir }}" class="bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-700 focus:ring-4 focus:ring-indigo-500/10 shadow-inner">
            </div>
            <button type="submit" class="px-6 py-3 bg-indigo-600 text-white rounded-xl text-[11px] font-medium uppercase tracking-widest hover:bg-indigo-700">
                <i class="fas fa-filter mr-1"></i> Tampilkan
            </button>
        </form>
    </div>

    <div class="max-w-3xl mx-auto bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden print:hidden">
        <div class="px-6 sm:px-10 py-6 border-b border-slate-100 text-center">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Periode</p>
            <p class="text-sm font-medium text-slate-700 mt-1">{{ \Carbon\Carbon::parse($awal)->translatedFormat('d M Y') }} s/d {{ \Carbon\Carbon::parse($akhir)->translatedFormat('d M Y') }}</p>
        </div>

        <div class="p-6 sm:p-10 space-y-6">
            <div>
                <p class="text-xs font-medium text-emerald-600 uppercase tracking-widest mb-4">Pemasukan dari Input Order</p>
                <div class="space-y-2">
                    <div class="flex justify-between gap-4 text-sm"><span class="text-slate-600">Penjualan barang dan cetak</span><span class="font-medium text-slate-700">{{ $rp($pendapatanPenjualan) }}</span></div>
                    <div class="flex justify-between gap-4 text-sm"><span class="text-slate-600">Jasa</span><span class="font-medium text-slate-700">{{ $rp($pendapatanJasa) }}</span></div>
                </div>
                <div class="flex justify-between gap-4 text-sm font-medium border-t border-slate-100 mt-4 pt-3">
                    <span class="text-slate-700">Total Pemasukan</span>
                    <span class="text-emerald-700">{{ $rp($totalPemasukan) }}</span>
                </div>
                <p class="text-[11px] text-slate-400 mt-2">Uang yang sudah diterima dari pembayaran nota pada periode ini: Rp {{ $rp($uangDiterima) }}. Sisanya masih piutang atau dibayar dari saldo deposit.</p>
                @if($setoranDeposit > 0)
                <p class="text-[11px] text-slate-400 mt-1">Setoran deposit pelanggan pada periode ini: Rp {{ $rp($setoranDeposit) }} (uang masuk lebih dulu, dipakai untuk nota berikutnya).</p>
                @endif
            </div>

            <div>
                <p class="text-xs font-medium text-amber-600 uppercase tracking-widest mb-4">Pengeluaran dari Kas Kecil</p>
                <div class="space-y-2">
                    @forelse($kasKecil as $b)
                    <div class="flex justify-between gap-4 text-sm"><span class="text-slate-600">{{ $b->kategori }}</span><span class="font-medium text-slate-700">{{ $rp($b->total) }}</span></div>
                    @empty
                    <p class="text-xs text-slate-400 italic">Belum ada pengeluaran Kas Kecil pada periode ini.</p>
                    @endforelse
                </div>
                <div class="flex justify-between gap-4 text-sm font-medium border-t border-slate-100 mt-4 pt-3">
                    <span class="text-slate-700">Subtotal Kas Kecil</span>
                    <span class="text-amber-700">{{ $rp($totalKasKecil) }}</span>
                </div>
            </div>

            <div>
                <p class="text-xs font-medium text-sky-700 uppercase tracking-widest mb-4">Dibayar Langsung Pemilik</p>
                <div class="space-y-2">
                    @forelse($pemilik as $b)
                    <div class="flex justify-between gap-4 text-sm"><span class="text-slate-600">{{ $b->kategori }}</span><span class="font-medium text-slate-700">{{ $rp($b->total) }}</span></div>
                    @empty
                    <p class="text-xs text-slate-400 italic">Belum ada biaya yang dibayar pemilik pada periode ini.</p>
                    @endforelse
                </div>
                <div class="flex justify-between gap-4 text-sm font-medium border-t border-slate-100 mt-4 pt-3">
                    <span class="text-slate-700">Subtotal Dibayar Pemilik</span>
                    <span class="text-sky-700">{{ $rp($totalPemilik) }}</span>
                </div>
            </div>

            <div class="flex justify-between gap-4 text-sm font-medium border-t border-slate-200 pt-3">
                <span class="text-slate-700">Total Pengeluaran</span>
                <span class="text-slate-800">{{ $rp($totalPengeluaran) }}</span>
            </div>

            <div class="rounded-2xl p-6 {{ $selisih >= 0 ? 'bg-emerald-50 border border-emerald-100' : 'bg-rose-50 border border-rose-100' }}">
                <div class="flex justify-between items-center gap-4">
                    <span class="text-sm font-medium uppercase tracking-widest {{ $selisih >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                        {{ $selisih >= 0 ? 'Pemasukan lebih besar' : 'Pengeluaran lebih besar' }}
                    </span>
                    <span class="text-2xl font-medium {{ $selisih >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $rp(abs($selisih)) }}</span>
                </div>
            </div>

            @if($bahanDariPemilik != 0)
            <p class="text-[11px] text-slate-400">Di luar perbandingan: pembelian bahan yang dibayar pemilik pada periode ini Rp {{ $rp($bahanDariPemilik) }}. Bahan masuk persediaan dan baru menjadi biaya saat terpakai (HPP).</p>
            @endif
        </div>
    </div>

    <div class="hidden print:block">
        <x-laporan.print-table title="Pemasukan dari Input Order" :headers="['Uraian', 'Jumlah']" :align="['left', 'right']">
            <tr><td class="px-3 py-1.5 text-slate-700">Penjualan barang dan cetak</td><td class="px-3 py-1.5 text-right text-slate-600">{{ $rp($pendapatanPenjualan) }}</td></tr>
            <tr><td class="px-3 py-1.5 text-slate-700">Jasa</td><td class="px-3 py-1.5 text-right text-slate-600">{{ $rp($pendapatanJasa) }}</td></tr>
            <tr class="bg-slate-50 border-t border-slate-200"><td class="px-3 py-1.5 font-medium text-slate-700 uppercase">Total Pemasukan</td><td class="px-3 py-1.5 text-right font-medium text-slate-800">{{ $rp($totalPemasukan) }}</td></tr>
        </x-laporan.print-table>

        <x-laporan.print-table title="Pengeluaran" :headers="['Kategori', 'Sumber', 'Jumlah']" :align="['left', 'left', 'right']">
            @foreach($kasKecil as $b)
            <tr><td class="px-3 py-1.5 text-slate-700">{{ $b->kategori }}</td><td class="px-3 py-1.5 text-slate-600">Kas Kecil</td><td class="px-3 py-1.5 text-right text-slate-600">{{ $rp($b->total) }}</td></tr>
            @endforeach
            @foreach($pemilik as $b)
            <tr><td class="px-3 py-1.5 text-slate-700">{{ $b->kategori }}</td><td class="px-3 py-1.5 text-slate-600">Pemilik</td><td class="px-3 py-1.5 text-right text-slate-600">{{ $rp($b->total) }}</td></tr>
            @endforeach
            <tr class="bg-slate-50 border-t border-slate-200"><td class="px-3 py-1.5 font-medium text-slate-700 uppercase" colspan="2">Total Pengeluaran</td><td class="px-3 py-1.5 text-right font-medium text-slate-800">{{ $rp($totalPengeluaran) }}</td></tr>
        </x-laporan.print-table>

        <x-laporan.resume-table title="Ringkasan" :rows="[
            'Total Pemasukan' => $rp($totalPemasukan),
            'Uang diterima dari nota' => $rp($uangDiterima),
            'Setoran deposit' => $rp($setoranDeposit),
            'Pengeluaran dari Kas Kecil' => $rp($totalKasKecil),
            'Dibayar langsung pemilik' => $rp($totalPemilik),
            ($selisih >= 0 ? 'Pemasukan lebih besar' : 'Pengeluaran lebih besar') => $rp(abs($selisih)),
        ]" />
    </div>
</div>
@endsection
