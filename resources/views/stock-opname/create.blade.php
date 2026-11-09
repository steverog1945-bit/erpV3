@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-10">

    <x-page-header title="Stok Opname Baru" subtitle="Semua bahan aktif akan otomatis dimasukkan - isi hasil hitung fisik di langkah berikutnya" />

    <x-card>
        <form method="POST" action="{{ route('stock-opname.store') }}" class="space-y-5">
            @csrf

            <x-form.group label="Gudang" dalam>
                <x-form.select name="id_gudang">
                    <option value="">-- Tidak spesifik --</option>
                    @foreach($gudangList as $g)
                        <option value="{{ $g->id_gudang }}" {{ old('id_gudang') == $g->id_gudang ? 'selected' : '' }}>{{ $g->nama_gudang }}</option>
                    @endforeach
                </x-form.select>
            </x-form.group>

            <x-form.group label="Tanggal Opname" dalam>
                <x-form.input type="date" name="tanggal" value="{{ old('tanggal', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required />
            </x-form.group>

            <x-form.group label="Catatan (opsional)" dalam>
                <textarea name="catatan" rows="3" class="w-full rounded-xl border-slate-200 text-sm p-3">{{ old('catatan') }}</textarea>
            </x-form.group>

            <x-button type="submit" variant="primary" class="w-full">
                Lanjut Isi Hasil Hitung Fisik
            </x-button>
        </form>
    </x-card>

</div>
@endsection
