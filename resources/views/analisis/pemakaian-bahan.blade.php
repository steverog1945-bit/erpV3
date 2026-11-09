@extends('layouts.app')

@section('content')
@php $n = fn ($v) => angka($v, 2); @endphp
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1500px] mx-auto">

    <x-page-header title="Analisis Pemakaian Bahan">
        <x-slot:subtitle>
            <i class="fas fa-layer-group text-amber-400"></i>
            Stok keluar per bahan dari Kartu Stok, dipisah: pemakaian order, rework, dan waste
        </x-slot:subtitle>
    </x-page-header>

    <x-card class="mb-8">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <div><label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Dari</label><x-form.input type="date" name="tgl_dari" value="{{ $dari->toDateString() }}" /></div>
            <div><label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Sampai</label><x-form.input type="date" name="tgl_sampai" value="{{ $sampai->toDateString() }}" /></div>
            <x-button type="submit" variant="primary"><i class="fas fa-filter"></i> Tampilkan</x-button>
        </form>
    </x-card>

    <x-table>
        <thead>
            <tr>
                <th class="px-8 py-4 text-left">Bahan</th>
                <th class="px-4 py-4 text-right">Pemakaian Order / Lain</th>
                <th class="px-4 py-4 text-right">Rework</th>
                <th class="px-4 py-4 text-right">Waste</th>
                <th class="px-4 py-4 text-right">Total Keluar</th>
                <th class="px-4 py-4 text-right">% Susut</th>
                <th class="px-8 py-4 text-right">Nilai Susut</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($data as $r)
            <tr class="hover:bg-slate-50/30">
                <td class="px-8 py-4"><span class="text-[13px] font-medium text-slate-700">{{ $r->nama_bahan }}</span><div class="text-[11px] text-slate-400">{{ $r->satuan_stok }}</div></td>
                <td class="px-4 py-4 text-right text-sm">{{ $n($r->qty_pemakaian) }}</td>
                <td class="px-4 py-4 text-right text-sm {{ $r->qty_rework > 0 ? 'text-rose-500 font-medium' : 'text-slate-500' }}">{{ $n($r->qty_rework) }}</td>
                <td class="px-4 py-4 text-right text-sm {{ $r->qty_waste > 0 ? 'text-rose-500 font-medium' : 'text-slate-500' }}">{{ $n($r->qty_waste) }}</td>
                <td class="px-4 py-4 text-right text-sm font-medium">{{ $n($r->qty_total) }}</td>
                <td class="px-4 py-4 text-right text-sm font-medium {{ $r->persen_susut >= 10 ? 'text-rose-600' : 'text-slate-600' }}">{{ $r->persen_susut }}%</td>
                <td class="px-8 py-4 text-right text-sm">Rp {{ number_format($r->nilai_susut, 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td colspan="7"><x-empty-state title="Tidak ada pengeluaran stok pada periode ini" /></td></tr>
            @endforelse
        </tbody>
    </x-table>
    <p class="text-[11px] text-slate-400 mt-3 px-1">Klasifikasi memakai kode referensi Kartu Stok: RW- = rework, WS- = waste, selain itu dianggap pemakaian order atau penyesuaian lain. Nilai susut memakai harga rata-rata bahan saat ini.</p>
</div>
@endsection
