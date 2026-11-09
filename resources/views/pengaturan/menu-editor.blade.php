@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-5xl mx-auto">
    <x-page-header title="Pengaturan Menu" subtitle="Sembunyikan modul yang tidak dipakai, beri nama tampilan, dan tambah menu pintasan sendiri." />

    <x-card class="mb-8">
        <h3 class="text-xs font-medium text-slate-500 uppercase tracking-widest mb-4">Tambah Menu Kustom</h3>
        <form method="POST" action="{{ route('menu-editor.store') }}" class="flex flex-wrap items-end gap-4">
            @csrf
            <div class="flex-1 min-w-[200px]"><label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Nama Menu</label><x-form.input type="text" name="label" required placeholder="Mis. Panduan Internal" /></div>
            <div class="flex-1 min-w-[260px]"><label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Alamat (halaman internal atau tautan luar)</label><x-form.input type="text" name="url" required placeholder="/laporan/order  atau  https://..." /></div>
            <x-button type="submit" variant="primary"><i class="fas fa-plus"></i> Tambah</x-button>
        </form>
        <p class="mt-3 text-[11px] text-slate-400">Menu kustom tampil di grup "Menu Tambahan" untuk role/user yang diberi hak Lihat di Setting Akses. Ini hanya pintasan; akses halaman internalnya tetap mengikuti hak akses modul aslinya.</p>
    </x-card>

    <form method="POST" action="{{ route('menu-editor.update') }}">
        @csrf
        <x-card>
            <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead><tr class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">
                    <th class="p-3 w-16 text-center">Tampil</th><th class="p-3">Modul</th><th class="p-3">Nama Tampilan (opsional)</th><th class="p-3 w-24">Urutan</th><th class="p-3 w-16"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach($menus as $m)
                @php $terkunci = in_array((int) $m->id_menu, $wajib, true); @endphp
                <tr class="{{ $m->aktif ? '' : 'opacity-60' }}">
                    <td class="p-3 text-center">
                        @if($terkunci)
                            <input type="checkbox" checked disabled class="w-5 h-5 rounded accent-indigo-600" title="Modul ini tidak bisa disembunyikan">
                        @else
                            <input type="checkbox" name="menu[{{ $m->id_menu }}][aktif]" value="1" {{ $m->aktif ? 'checked' : '' }} class="w-5 h-5 rounded accent-indigo-600">
                        @endif
                    </td>
                    <td class="p-3"><div class="text-sm font-medium text-slate-700">{{ $m->nama_menu }} @if($m->jenis === 'kustom')<span class="ml-1 px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 text-[10px] font-medium uppercase">kustom</span>@endif</div><div class="text-[11px] text-slate-400 break-all">{{ $m->url_link }}</div></td>
                    <td class="p-3"><input type="text" name="menu[{{ $m->id_menu }}][label]" value="{{ $m->label_kustom }}" maxlength="100" placeholder="{{ $m->nama_menu }}" class="w-full rounded-xl border-slate-200 text-sm p-2"></td>
                    <td class="p-3"><input type="number" name="menu[{{ $m->id_menu }}][urutan]" value="{{ $m->urutan }}" min="0" class="w-full rounded-xl border-slate-200 text-sm p-2 text-right"></td>
                    <td class="p-3 text-center">
                        @if($m->jenis === 'kustom')
                        <button type="submit" form="hapus-menu-{{ $m->id_menu }}" class="text-slate-500 hover:text-rose-500" title="Hapus menu kustom" data-konfirmasi="Hapus menu kustom ini?"><i class="fas fa-trash-alt"></i></button>
                        @endif
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            <div class="pt-4 mt-2 flex justify-end border-t border-slate-100"><x-button type="submit" class="!px-10">Simpan Menu</x-button></div>
        </x-card>
    </form>
    @foreach($menus->where('jenis', 'kustom') as $m)
    <form id="hapus-menu-{{ $m->id_menu }}" method="POST" action="{{ route('menu-editor.destroy', $m->id_menu) }}">@csrf @method('DELETE')</form>
    @endforeach

    <p class="mt-4 text-xs text-slate-400">Catatan: modul yang disembunyikan hilang dari sidebar semua role, tetapi datanya tetap ada. Setting Akses, Data User, dan Pengaturan Aplikasi tidak bisa disembunyikan agar admin tidak terkunci.</p>
</div>
@endsection
