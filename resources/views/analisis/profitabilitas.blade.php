@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1500px] mx-auto">

    <x-page-header title="Profitabilitas">
        <x-slot:subtitle>
            <i class="fas fa-percentage text-amber-400"></i>
            Laba kotor (omzet - HPP material) per {{ $dimensi }} - rework & waste belum dialokasikan per {{ $dimensi }}, lihat Job Costing untuk per nota
        </x-slot:subtitle>
    </x-page-header>

    <x-card class="mb-8">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Kelompokkan per</label>
                <x-form.select name="dimensi" onchange="this.form.submit()">
                    @foreach(['produk' => 'Produk / Jasa', 'pelanggan' => 'Pelanggan', 'mesin' => 'Mesin / Divisi'] as $k => $v)<option value="{{ $k }}" {{ $dimensi === $k ? 'selected' : '' }}>{{ $v }}</option>@endforeach
                </x-form.select>
            </div>
            <div><label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Dari</label><x-form.input type="date" name="tgl_dari" value="{{ $dari->toDateString() }}" /></div>
            <div><label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Sampai</label><x-form.input type="date" name="tgl_sampai" value="{{ $sampai->toDateString() }}" /></div>
            <x-button type="submit" variant="primary"><i class="fas fa-filter"></i> Tampilkan</x-button>
        </form>
    </x-card>

    <x-table>
        <thead>
            <tr>
                <th class="px-8 py-4 text-left">{{ ucfirst($dimensi) }}</th>
                <th class="px-4 py-4 text-right">Nota</th>
                <th class="px-4 py-4 text-right">Qty</th>
                <th class="px-4 py-4 text-right">Omzet</th>
                <th class="px-4 py-4 text-right">HPP</th>
                <th class="px-4 py-4 text-right">Laba Kotor</th>
                <th class="px-8 py-4 text-right">Margin</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($data as $r)
            <tr class="hover:bg-slate-50/30">
                <td class="px-8 py-4 text-[13px] font-medium text-slate-700">{{ $r->nama }}</td>
                <td class="px-4 py-4 text-right text-sm">{{ $r->jumlah_nota }}</td>
                <td class="px-4 py-4 text-right text-sm">{{ angka($r->qty, 2) }}</td>
                <td class="px-4 py-4 text-right text-sm">{{ number_format($r->omzet, 0, ',', '.') }}</td>
                <td class="px-4 py-4 text-right text-sm text-slate-500">{{ number_format($r->hpp, 0, ',', '.') }}</td>
                <td class="px-4 py-4 text-right text-sm font-medium {{ $r->laba < 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ number_format($r->laba, 0, ',', '.') }}</td>
                <td class="px-8 py-4 text-right text-sm font-medium {{ $r->margin < 20 ? 'text-amber-600' : 'text-slate-600' }}">{{ angka($r->margin, 1) }}%</td>
            </tr>
            @empty
            <tr><td colspan="7"><x-empty-state title="Tidak ada data pada periode ini" /></td></tr>
            @endforelse
        </tbody>
    </x-table>
</div>
@endsection
