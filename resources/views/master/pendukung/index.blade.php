@extends('layouts.app')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
    
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
        <div class="flex items-center gap-3 mb-6">
            <span class="text-2xl">⚙️</span>
            <h2 class="text-xl font-medium text-slate-800">Daftar Mesin</h2>
        </div>
        
        <form action="{{ route('mesin.store') }}" method="POST" class="space-y-4 mb-8">
            @csrf
            <div>
                <label class="text-xs font-medium text-slate-500 uppercase ml-1">Nama Mesin</label>
                <input type="text" name="nama_mesin" placeholder="Contoh: Roland Eco-Solvent" class="w-full mt-1 rounded-xl border-slate-200 bg-slate-50 px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 transition" required>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-xs font-medium text-slate-500 uppercase ml-1">Tipe</label>
                    <input type="text" name="tipe_mesin" placeholder="Indoor/Outdoor" class="w-full mt-1 rounded-xl border-slate-200 bg-slate-50 px-4 py-2.5 transition">
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-500 uppercase ml-1">Biaya/m</label>
                    <input type="text" inputmode="numeric" autocomplete="off" name="biaya_per_meter" placeholder="5000" class="w-full mt-1 rounded-xl border-slate-200 bg-slate-50 px-4 py-2.5 transition format-ribuan">
                </div>
            </div>
            <button type="submit" class="w-full bg-slate-900 text-white font-medium py-3 rounded-xl hover:bg-slate-800 transition shadow-lg">Simpan Mesin</button>
        </form>

        <div class="border-t border-slate-100 pt-4">
            <table class="w-full text-sm text-slate-600">
                @foreach($mesin as $m)
                <tr class="border-b border-slate-50">
                    <td class="py-2 font-medium text-slate-800">{{ $m->nama_mesin }}</td>
                    <td class="py-2 text-right text-xs">{{ $m->tipe_mesin }}</td>
                </tr>
                @endforeach
            </table>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 h-fit">
        <div class="flex items-center gap-3 mb-6">
            <span class="text-2xl">🏷️</span>
            <h2 class="text-xl font-medium text-slate-800">Kategori Bahan</h2>
        </div>
        
        <form action="{{ route('kategori.store') }}" method="POST" class="flex gap-2 mb-8">
            @csrf
            <input type="text" name="nama_kategori" placeholder="Contoh: Sticker" class="flex-1 rounded-xl border-slate-200 bg-slate-50 px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 transition" required>
            <button type="submit" class="bg-indigo-600 text-white px-6 rounded-xl font-medium hover:bg-indigo-700 transition">Tambah</button>
        </form>

        <div class="grid grid-cols-2 gap-2">
            @foreach($kategori as $kat)
            <div class="bg-slate-50 border border-slate-100 px-4 py-2 rounded-lg text-sm font-medium text-slate-700 flex justify-between">
                {{ $kat->nama_kategori }}
            </div>
            @endforeach
        </div>
    </div>

</div>
@endsection