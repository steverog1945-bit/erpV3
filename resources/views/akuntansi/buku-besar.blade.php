@extends('layouts.app')

@section('content')
<style>
    @media print {
        aside, .no-print { display: none !important; }
        main { margin-left: 0 !important; padding: 28px 32px !important; }
        body { background: #fff !important; }
    }
</style>
@include('partials.print-report-header', ['docTitle' => 'Buku Besar' . (isset($akun) && $akun ? ' - ' . $akun->nama_akun : ''), 'tglDari' => $awal, 'tglSampai' => $akhir])

<div class="w-full px-4 sm:px-6 lg:px-8 py-10 print:pt-0 max-w-[1600px] mx-auto">
    <div class="print:hidden">
        <x-page-header title="Buku Besar" subtitle="Mutasi & saldo berjalan per akun.">
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
            <div class="min-w-[260px]">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2">Akun</label>
                <select name="kode_akun" class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
                    @foreach($daftarAkun as $a)
                        <option value="{{ $a->kode_akun }}" {{ $kodeAkun == $a->kode_akun ? 'selected' : '' }}>{{ $a->kode_akun }} - {{ $a->nama_akun }}</option>
                    @endforeach
                </select>
            </div>
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

    @if($akun)
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden print:hidden">
        <div class="px-8 py-5 border-b border-slate-100 flex items-center justify-between">
            <div>
                <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200">{{ $akun->kode_akun }}</span>
                <span class="ml-2 text-sm font-medium text-slate-700">{{ $akun->nama_akun }}</span>
            </div>
            <span class="text-[10px] font-medium text-slate-400 uppercase">Saldo Normal: {{ $akun->saldo_normal }}</span>
        </div>
        <x-table bare>
            <thead>
                <tr>
                    <th class="px-4 py-4 text-center w-10">#</th>
                    <th class="px-8 py-4 text-left w-28">Tanggal</th>
                    <th class="px-6 py-4 text-left">No. Referensi / Keterangan</th>
                    <th class="px-6 py-4 text-right w-32">Debet</th>
                    <th class="px-6 py-4 text-right w-32">Kredit</th>
                    <th class="px-8 py-4 text-right w-36">Saldo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <tr class="bg-slate-50/40">
                    <td colspan="5" class="px-8 py-3 text-xs font-medium text-slate-500 uppercase tracking-widest">Saldo Awal</td>
                    <td class="px-8 py-3 text-right text-sm font-medium text-slate-700">{{ number_format($saldoAwal, 0, ',', '.') }}</td>
                </tr>
                @forelse($rows as $i => $r)
                <tr class="hover:bg-slate-50/30 transition-all">
                    <td class="px-4 py-3 text-center text-[11px] text-slate-400">{{ $i + 1 }}</td>
                    <td class="px-8 py-3 text-xs text-slate-500">{{ \Carbon\Carbon::parse($r->tgl_jurnal)->format('d/m/Y') }}</td>
                    <td class="px-6 py-3 text-sm text-slate-700">
                        <span class="text-xs text-slate-400">{{ $r->no_referensi }}</span>: {{ $r->keterangan }}
                    </td>
                    <td class="px-6 py-3 text-right text-sm {{ $r->debet > 0 ? 'text-slate-700 font-medium' : 'text-slate-500' }}">
                        {{ $r->debet > 0 ? number_format($r->debet, 0, ',', '.') : '-' }}
                    </td>
                    <td class="px-6 py-3 text-right text-sm {{ $r->kredit > 0 ? 'text-slate-700 font-medium' : 'text-slate-500' }}">
                        {{ $r->kredit > 0 ? number_format($r->kredit, 0, ',', '.') : '-' }}
                    </td>
                    <td class="px-8 py-3 text-right text-sm font-medium text-slate-800">{{ number_format($r->saldo_berjalan, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="py-16 text-center text-slate-400 font-medium italic">Tidak ada mutasi pada rentang tanggal ini.</td>
                </tr>
                @endforelse
                <tr class="bg-indigo-50/40 border-t-2 border-indigo-100">
                    <td colspan="5" class="px-8 py-4 text-right text-[11px] font-medium text-indigo-700 uppercase tracking-widest">Saldo Akhir</td>
                    <td class="px-8 py-4 text-right text-sm font-medium text-indigo-700">{{ number_format($saldoAkhir, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </x-table>
    </div>

    {{-- Versi cetak - pola print-table yang sama dengan laporan lain. --}}
    <div class="hidden print:block">
        <x-laporan.print-table :title="'Buku Besar - ' . $akun->kode_akun . ' ' . $akun->nama_akun" :headers="['#', 'Tanggal', 'No. Referensi / Keterangan', 'Debet', 'Kredit', 'Saldo']" :align="['center', 'left', 'left', 'right', 'right', 'right']">
            <tr class="bg-slate-50">
                <td class="px-3 py-1.5 font-medium text-slate-700 uppercase" colspan="5">Saldo Awal</td>
                <td class="px-3 py-1.5 text-right font-medium text-slate-800">{{ number_format($saldoAwal, 0, ',', '.') }}</td>
            </tr>
            @forelse($rows as $i => $r)
            <tr>
                <td class="px-3 py-1.5 text-center text-slate-400">{{ $i + 1 }}</td>
                <td class="px-3 py-1.5 text-slate-500">{{ \Carbon\Carbon::parse($r->tgl_jurnal)->format('d/m/Y') }}</td>
                <td class="px-3 py-1.5 text-slate-700">{{ $r->no_referensi }}: {{ $r->keterangan }}</td>
                <td class="px-3 py-1.5 text-right text-slate-600">{{ $r->debet > 0 ? number_format($r->debet, 0, ',', '.') : '-' }}</td>
                <td class="px-3 py-1.5 text-right text-slate-600">{{ $r->kredit > 0 ? number_format($r->kredit, 0, ',', '.') : '-' }}</td>
                <td class="px-3 py-1.5 text-right font-medium text-slate-800">{{ number_format($r->saldo_berjalan, 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td class="px-3 py-1.5 text-slate-400 italic" colspan="6">Tidak ada mutasi pada rentang tanggal ini.</td>
            </tr>
            @endforelse
            <tr class="bg-slate-50 border-t border-slate-200">
                <td class="px-3 py-1.5 font-medium text-slate-700 uppercase" colspan="5">Saldo Akhir</td>
                <td class="px-3 py-1.5 text-right font-medium text-slate-800">{{ number_format($saldoAkhir, 0, ',', '.') }}</td>
            </tr>
        </x-laporan.print-table>
    </div>
    @else
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-16 text-center text-slate-400 italic no-print">
        Belum ada akun terdaftar. Tambah akun dulu di Chart of Accounts.
    </div>
    @endif
</div>
@endsection
