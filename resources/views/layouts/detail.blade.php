@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Kartu Stok: {{ $bahan->nama_bahan }}"
                   subtitle="Stok saat ini: {{ angka($bahan->stok, 2) }}">
        <x-slot:actions>
            <x-button href="{{ url('/kartu-stok') }}" variant="outline">Kembali</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-table>
        <thead>
            <tr>
                <th class="px-8 py-5 text-left">Tanggal</th>
                <th class="px-6 py-5 text-center">Tipe</th>
                <th class="px-6 py-5 text-right">Qty</th>
                <th class="px-6 py-5 text-right">Stok Sebelum</th>
                <th class="px-6 py-5 text-right">Stok Sesudah</th>
                <th class="px-6 py-5 text-left">Referensi</th>
                <th class="px-6 py-5 text-left">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($kartu as $k)
            <tr>
                <td class="px-8 py-5">{{ $k->tanggal }}</td>
                <td class="px-6 py-5 text-center">
                    @if($k->tipe == 'IN')
                        <x-badge variant="success">IN</x-badge>
                    @elseif($k->tipe == 'OUT')
                        <x-badge variant="danger">OUT</x-badge>
                    @else
                        <x-badge variant="warning">ADJ</x-badge>
                    @endif
                </td>
                <td class="px-6 py-5 text-right font-medium text-slate-700">{{ angka($k->qty, 2) }}</td>
                <td class="px-6 py-5 text-right text-slate-500">{{ angka($k->stok_sebelum, 2) }}</td>
                <td class="px-6 py-5 text-right text-slate-500">{{ angka($k->stok_sesudah, 2) }}</td>
                <td class="px-6 py-5">{{ $k->referensi }}</td>
                <td class="px-6 py-5 text-slate-500">{{ $k->keterangan }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7">
                    <x-empty-state title="Belum ada transaksi" />
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-table>

</div>
@endsection
