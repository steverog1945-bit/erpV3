@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Kartu Stok" subtitle="Ringkasan stok seluruh bahan baku" />

    <x-table>
        <thead>
            <tr>
                <th class="px-8 py-5 text-left">Kode</th>
                <th class="px-6 py-5 text-left">Nama Bahan</th>
                <th class="px-6 py-5 text-right">Stok Saat Ini</th>
                <th class="px-6 py-5 text-center">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bahan as $b)
            <tr>
                <td class="px-8 py-5 font-medium text-slate-700">{{ $b->kode_bahan }}</td>
                <td class="px-6 py-5">{{ $b->nama_bahan }}</td>
                <td class="px-6 py-5 text-right font-medium text-slate-700">{{ angka($b->stok, 2) }}</td>
                <td class="px-6 py-5 text-center">
                    <x-button href="{{ url('/kartu-stok/'.$b->id_bahan) }}" size="sm" variant="secondary">
                        Lihat Kartu
                    </x-button>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="4">
                    <x-empty-state title="Belum ada bahan baku" />
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-table>

</div>
@endsection
