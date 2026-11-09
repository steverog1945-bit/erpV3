@extends('layouts.app')

@section('content')
@php $bisaUbah = \App\Support\Izin::boleh(auth()->user(), [1, 23], 'edit'); @endphp
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Pengelolaan Limbah">
        <x-slot:subtitle>
            <i class="fas fa-recycle text-amber-400"></i>
            Catat susut bahan - Normal langsung mengurangi stok, Abnormal menunggu persetujuan
        </x-slot:subtitle>
        <x-slot:actions>
            @if($bisaUbah)<x-button :href="route('waste.create')" variant="primary"><i class="fas fa-plus"></i> Catat Waste</x-button>@endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
        @foreach(['Normal' => 'text-amber-500', 'Abnormal' => 'text-rose-600'] as $jenis => $warna)
        <x-card>
            <div class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2">Waste {{ $jenis }} bulan ini</div>
            @bolehHarga<div class="text-lg font-medium {{ $warna }}">Rp {{ number_format($ringkasan[$jenis]->biaya ?? 0, 0, ',', '.') }}</div>@endbolehHarga
            <div class="text-xs text-slate-400">{{ $ringkasan[$jenis]->jumlah ?? 0 }} catatan diterapkan</div>
        </x-card>
        @endforeach
    </div>

    <x-card class="mb-8">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <div class="w-full md:w-56">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Jenis</label>
                <x-form.select name="jenis" onchange="this.form.submit()">
                    <option value="">Semua</option>
                    @foreach(['Normal', 'Abnormal'] as $j)<option value="{{ $j }}" {{ request('jenis') == $j ? 'selected' : '' }}>{{ $j }}</option>@endforeach
                </x-form.select>
            </div>
            <div class="w-full md:w-56">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Status</label>
                <x-form.select name="status" onchange="this.form.submit()">
                    <option value="">Semua</option>
                    @foreach(['Diterapkan', 'Menunggu', 'Ditolak'] as $s)<option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ $s }}</option>@endforeach
                </x-form.select>
            </div>
        </form>
    </x-card>

    <x-table>
        <thead>
            <tr>
                <th class="px-8 py-4 text-left">Nomor</th>
                <th class="px-6 py-4 text-left">Bahan</th>
                <th class="px-6 py-4 text-right">Qty</th>
                <th class="px-6 py-4 text-center">Jenis</th>
                <th class="px-6 py-4 text-left">Alasan</th>
                @bolehHarga<th class="px-6 py-4 text-right">Biaya</th>@endbolehHarga
                <th class="px-6 py-4 text-center">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($daftar as $w)
            <tr>
                <td class="px-8 py-4"><span class="text-[13px] font-medium text-slate-700">{{ $w->nomor }}</span><div class="text-[11px] text-slate-400">{{ \Carbon\Carbon::parse($w->tanggal)->translatedFormat('d M Y') }} - {{ $w->username }}</div></td>
                <td class="px-6 py-4 text-sm font-medium text-slate-600">{{ $w->nama_bahan }}</td>
                <td class="px-6 py-4 text-right text-sm">{{ angka($w->qty, 4) }} {{ $w->satuan_stok }}</td>
                <td class="px-6 py-4 text-center"><x-badge :status="$w->jenis" :variant="$w->jenis === 'Normal' ? 'warning' : 'danger'" /></td>
                <td class="px-6 py-4 text-xs text-slate-500">{{ $w->alasan }}</td>
                @bolehHarga<td class="px-6 py-4 text-right text-sm">{{ $w->status === 'Diterapkan' ? 'Rp ' . number_format($w->biaya, 0, ',', '.') : '-' }}</td>@endbolehHarga
                <td class="px-6 py-4 text-center">
                    <x-badge :status="$w->status" :variant="['Diterapkan' => 'success', 'Menunggu' => 'warning', 'Ditolak' => 'danger'][$w->status] ?? 'info'" />
                </td>
            </tr>
            @empty
            <tr><td colspan="7"><x-empty-state title="Belum ada catatan waste" /></td></tr>
            @endforelse
        </tbody>
    </x-table>
    <x-table.pagination :paginator="$daftar" />
</div>
@endsection
