@extends('layouts.app')

@section('content')
<style>
    @media print {
        aside, .no-print { display: none !important; }
        main { margin-left: 0 !important; padding: 28px 32px !important; }
        body { background: #fff !important; }
    }
</style>
@include('partials.print-report-header', ['docTitle' => 'Neraca Keuangan per ' . \Carbon\Carbon::parse($tanggal)->format('d/m/Y')])

<div class="w-full px-4 sm:px-6 lg:px-8 py-10 print:pt-0 max-w-[1600px] mx-auto">
    <div class="print:hidden">
        <x-page-header title="Neraca" subtitle="Posisi keuangan (Aset = Kewajiban + Ekuitas) per tanggal tertentu.">
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
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2">Per Tanggal</label>
                <input type="date" name="tanggal" value="{{ $tanggal }}" class="bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-700 focus:ring-4 focus:ring-indigo-500/10 shadow-inner">
            </div>
            <button type="submit" class="px-6 py-3 bg-indigo-600 text-white rounded-xl text-[11px] font-medium uppercase tracking-widest hover:bg-indigo-700">
                <i class="fas fa-filter mr-1"></i> Tampilkan
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 print:hidden">
        {{-- AKTIVA --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-8 py-5 border-b border-slate-100">
                <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-sky-50 text-sky-700 border border-sky-100 uppercase">Aktiva (Aset)</span>
            </div>
            <div class="p-8 space-y-2">
                @forelse($aset as $a)
                <div class="flex justify-between text-sm">
                    <span class="text-slate-600">{{ $a->nama_akun }}</span>
                    <span class="font-medium text-slate-700">{{ number_format($a->jumlah, 0, ',', '.') }}</span>
                </div>
                @empty
                <p class="text-xs text-slate-400 italic">Belum ada akun aset dengan saldo.</p>
                @endforelse
            </div>
            <div class="px-8 py-5 bg-sky-50/50 border-t border-sky-100 flex justify-between items-center">
                <span class="text-xs font-medium text-sky-700 uppercase tracking-widest">Total Aktiva</span>
                <span class="text-lg font-medium text-sky-700">{{ number_format($totalAset, 0, ',', '.') }}</span>
            </div>
        </div>

        {{-- PASIVA --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-8 py-5 border-b border-slate-100">
                <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-rose-50 text-rose-700 border border-rose-100 uppercase">Kewajiban</span>
            </div>
            <div class="p-8 space-y-2">
                @forelse($kewajiban as $k)
                <div class="flex justify-between text-sm">
                    <span class="text-slate-600">{{ $k->nama_akun }}</span>
                    <span class="font-medium text-slate-700">{{ number_format($k->jumlah, 0, ',', '.') }}</span>
                </div>
                @empty
                <p class="text-xs text-slate-400 italic">Belum ada akun kewajiban dengan saldo.</p>
                @endforelse
            </div>
            <div class="px-8 py-4 bg-rose-50/50 border-t border-rose-100 flex justify-between items-center">
                <span class="text-xs font-medium text-rose-700 uppercase tracking-widest">Total Kewajiban</span>
                <span class="text-sm font-medium text-rose-700">{{ number_format($totalKewajiban, 0, ',', '.') }}</span>
            </div>

            <div class="px-8 py-5 border-b border-t border-slate-100">
                <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-violet-50 text-violet-700 border border-violet-100 uppercase">Ekuitas</span>
            </div>
            <div class="p-8 space-y-2">
                @forelse($modal as $m)
                <div class="flex justify-between text-sm">
                    <span class="text-slate-600">{{ $m->nama_akun }}</span>
                    <span class="font-medium text-slate-700">{{ number_format($m->jumlah, 0, ',', '.') }}</span>
                </div>
                @empty
                <p class="text-xs text-slate-400 italic">Belum ada setoran modal tercatat.</p>
                @endforelse
                <div class="flex justify-between text-sm">
                    <span class="text-slate-600">Laba Ditahan Berjalan</span>
                    <span class="font-medium {{ $labaBerjalan >= 0 ? 'text-slate-700' : 'text-rose-600' }}">{{ number_format($labaBerjalan, 0, ',', '.') }}</span>
                </div>
            </div>
            <div class="px-8 py-5 bg-violet-50/50 border-t border-violet-100 flex justify-between items-center">
                <span class="text-xs font-medium text-violet-700 uppercase tracking-widest">Total Ekuitas</span>
                <span class="text-lg font-medium text-violet-700">{{ number_format($totalEkuitas, 0, ',', '.') }}</span>
            </div>

            <div class="px-8 py-5 bg-slate-50 border-t-2 border-slate-100 flex justify-between items-center">
                <span class="text-xs font-medium text-slate-600 uppercase tracking-widest">Total Kewajiban + Ekuitas</span>
                <span class="text-lg font-medium text-slate-800">{{ number_format($totalPasiva, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>

    <div class="mt-6 rounded-2xl p-5 text-center print:hidden {{ $balance ? 'bg-emerald-50 border border-emerald-100' : 'bg-rose-50 border border-rose-100' }}">
        @if($balance)
            <span class="text-xs font-medium text-emerald-700 uppercase tracking-widest"><i class="fas fa-check-circle"></i> Balance - Total Aktiva = Total Kewajiban + Ekuitas</span>
        @else
            <span class="text-xs font-medium text-rose-700 uppercase tracking-widest"><i class="fas fa-exclamation-circle"></i> TIDAK BALANCE - Selisih {{ number_format(abs($totalAset - $totalPasiva), 0, ',', '.') }}</span>
        @endif
    </div>

    {{-- Analisis Otomatis - narasi RULE-BASED dari angka yang sudah
         dihitung controller (AkuntansiController::buildNarasiNeraca()),
         BUKAN dari AI/LLM - deterministik, instan, tanpa API eksternal.
         Sengaja ikut tercetak (bukan no-print) - ini bagian dari nilai
         tambah "penjelasan profesional" yang diminta, bukan cuma alat
         bantu di layar. --}}
    <div class="mt-6 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden print:hidden">
        <div class="px-8 py-5 border-b border-slate-100 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                <i class="fas fa-chart-simple text-sm"></i>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-800">Analisis Otomatis</h3>
                <p class="text-[11px] text-slate-400">Dibentuk otomatis dari rasio &amp; komposisi neraca ini - bukan nasihat keuangan formal, gunakan sebagai gambaran awal.</p>
            </div>
        </div>
        <div class="p-8 space-y-3">
            @foreach($narasi as $butir)
                @php
                    $ikon = ['positif' => 'fa-circle-check', 'peringatan' => 'fa-triangle-exclamation', 'info' => 'fa-circle-info'][$butir['tone']];
                    $warna = ['positif' => 'text-emerald-600 bg-emerald-50', 'peringatan' => 'text-amber-600 bg-amber-50', 'info' => 'text-slate-500 bg-slate-50'][$butir['tone']];
                @endphp
                <div class="flex items-start gap-3">
                    <span class="w-6 h-6 rounded-lg {{ $warna }} flex items-center justify-center shrink-0 mt-0.5">
                        <i class="fas {{ $ikon }} text-[11px]"></i>
                    </span>
                    <p class="text-sm text-slate-600 leading-relaxed">{{ $butir['teks'] }}</p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Versi cetak - pola print-table/resume-table yang sama dengan
         laporan lain, tanpa kartu rounded/shadow/warna berbeda. --}}
    <div class="hidden print:block">
        <x-laporan.print-table title="Aktiva (Aset)" :headers="['Akun', 'Jumlah']" :align="['left', 'right']">
            @forelse($aset as $a)
            <tr>
                <td class="px-3 py-1.5 text-slate-700">{{ $a->nama_akun }}</td>
                <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($a->jumlah, 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td class="px-3 py-1.5 text-slate-400 italic" colspan="2">Belum ada akun aset dengan saldo.</td>
            </tr>
            @endforelse
            <tr class="bg-slate-50 border-t border-slate-200">
                <td class="px-3 py-1.5 font-medium text-slate-700 uppercase">Total Aktiva</td>
                <td class="px-3 py-1.5 text-right font-medium text-slate-800">{{ number_format($totalAset, 0, ',', '.') }}</td>
            </tr>
        </x-laporan.print-table>

        <x-laporan.print-table title="Kewajiban" :headers="['Akun', 'Jumlah']" :align="['left', 'right']">
            @forelse($kewajiban as $k)
            <tr>
                <td class="px-3 py-1.5 text-slate-700">{{ $k->nama_akun }}</td>
                <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($k->jumlah, 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td class="px-3 py-1.5 text-slate-400 italic" colspan="2">Belum ada akun kewajiban dengan saldo.</td>
            </tr>
            @endforelse
            <tr class="bg-slate-50 border-t border-slate-200">
                <td class="px-3 py-1.5 font-medium text-slate-700 uppercase">Total Kewajiban</td>
                <td class="px-3 py-1.5 text-right font-medium text-slate-800">{{ number_format($totalKewajiban, 0, ',', '.') }}</td>
            </tr>
        </x-laporan.print-table>

        <x-laporan.print-table title="Ekuitas" :headers="['Akun', 'Jumlah']" :align="['left', 'right']">
            @forelse($modal as $m)
            <tr>
                <td class="px-3 py-1.5 text-slate-700">{{ $m->nama_akun }}</td>
                <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($m->jumlah, 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td class="px-3 py-1.5 text-slate-400 italic" colspan="2">Belum ada setoran modal tercatat.</td>
            </tr>
            @endforelse
            <tr>
                <td class="px-3 py-1.5 text-slate-700">Laba Ditahan Berjalan</td>
                <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($labaBerjalan, 0, ',', '.') }}</td>
            </tr>
            <tr class="bg-slate-50 border-t border-slate-200">
                <td class="px-3 py-1.5 font-medium text-slate-700 uppercase">Total Ekuitas</td>
                <td class="px-3 py-1.5 text-right font-medium text-slate-800">{{ number_format($totalEkuitas, 0, ',', '.') }}</td>
            </tr>
        </x-laporan.print-table>

        <x-laporan.resume-table title="Ringkasan" :rows="[
            'Total Aktiva' => number_format($totalAset, 0, ',', '.'),
            'Total Kewajiban + Ekuitas' => number_format($totalPasiva, 0, ',', '.'),
            'Status' => $balance ? 'BALANCE' : 'TIDAK BALANCE - Selisih ' . number_format(abs($totalAset - $totalPasiva), 0, ',', '.'),
        ]" />

        <div class="mb-6 border border-slate-200 overflow-hidden">
            <p class="text-[10px] font-medium text-slate-700 uppercase tracking-wide px-3 py-2 bg-slate-50 border-b border-slate-200">Analisis Otomatis</p>
            <div class="p-3 space-y-1.5">
                @foreach($narasi as $butir)
                    <p class="text-[11px] text-slate-600">&bull; {{ $butir['teks'] }}</p>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
