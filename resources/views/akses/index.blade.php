@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1200px] mx-auto">

    <x-page-header title="Pengaturan Hak Akses" subtitle="Tentukan per role: modul apa yang boleh dilihat, ditambah, dikelola (diubah), dan dihapus." />

    {{-- Pilih role --}}
    <div class="flex flex-wrap gap-2 mb-6">
        @foreach($roles as $r)
        <a href="{{ route('akses.index', ['role' => $r->id_role]) }}"
           class="px-4 py-2 rounded-xl text-[12px] font-medium uppercase tracking-widest transition-all {{ $r->id_role === $role->id_role ? 'bg-slate-900 text-white' : 'bg-white text-slate-500 border border-slate-200 hover:border-slate-400' }}">
            {{ $r->nama_role }}
        </a>
        @endforeach
    </div>

    @if($role->nama_role === 'Administrator')
        <div class="p-5 rounded-2xl bg-emerald-50 text-emerald-700 text-sm font-medium">Administrator selalu memiliki semua akses pada semua modul dan tidak bisa dibatasi.</div>
    @else
    <form action="{{ route('akses.update') }}" method="POST">
        @csrf
        <input type="hidden" name="role_id" value="{{ $role->id_role }}">
        <input type="hidden" name="_versi" value="{{ $versi }}">

        <div class="mb-3 p-5 rounded-2xl bg-amber-50/70 border border-amber-100 flex items-start gap-4">
            <input type="checkbox" name="boleh_lihat_harga" value="1" id="cek-harga" {{ $role->boleh_lihat_harga ? 'checked' : '' }} class="mt-1 w-6 h-6 rounded-lg border-amber-300 text-amber-600 focus:ring-amber-500 cursor-pointer">
            <label for="cek-harga" class="cursor-pointer">
                <div class="font-medium text-slate-800">Boleh melihat &amp; mengubah harga</div>
                <div class="text-[11px] text-slate-500 font-medium">Kosongkan untuk role yang hanya input nota &amp; produksi: harga hanya tampil (tidak bisa diubah) di Input Nota; halaman/kolom harga lain (Finishing, Quotation, detail nota, jersey, waste &amp; QC) ditutup.</div>
            </label>
        </div>

        <div class="mb-5 p-5 rounded-2xl bg-indigo-50/70 border border-indigo-100 flex items-start gap-4">
            <input type="checkbox" name="boleh_input_biaya_desain" value="1" id="cek-desain" {{ $role->boleh_input_biaya_desain ? 'checked' : '' }} class="mt-1 w-6 h-6 rounded-lg border-indigo-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
            <label for="cek-desain" class="cursor-pointer">
                <div class="font-medium text-slate-800">Boleh menambah Biaya Desain</div>
                <div class="text-[11px] text-slate-500 font-medium">Hanya mengaktifkan field <b>Biaya Desain</b> di Input Order. Tidak membuka harga produk, biaya cetak, biaya finishing, maupun halaman harga mana pun. Beri centang ini ke Operator Desain: pekerjaannya designing, tapi tetap tidak boleh melihat harga. Biarkan kosong kalau role ini tidak pernah mengisi Biaya Desain.</div>
            </label>
        </div>

        @include('akses._matriks', ['menus' => $menus, 'izin' => $izin])

        <div class="mt-6 flex gap-3">
            <x-button type="submit" variant="primary"><i class="fas fa-save"></i> Simpan Hak Akses {{ $role->nama_role }}</x-button>
        </div>
    </form>
    @endif

    <p class="mt-8 text-xs text-slate-400">Butuh pengecualian untuk satu orang saja? Buka <b>Data User</b> lalu pilih tombol Hak Akses pada user tersebut.</p>
</div>
@endsection
