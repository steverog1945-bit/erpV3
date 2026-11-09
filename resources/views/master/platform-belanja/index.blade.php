@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-3xl mx-auto">
    <x-page-header title="Platform Belanja Online" subtitle="Daftar toko online tempat bahan dibeli. Tambah platform baru sendiri (Lazada, TikTok Shop, dst) tanpa mengubah kode." />

    <x-card class="mb-6">
        <form method="POST" action="{{ route('platform-belanja.store') }}" class="flex flex-wrap items-end gap-4">
            @csrf
            <div class="flex-1 min-w-[180px]"><label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Nama Platform</label><x-form.input type="text" name="nama" required placeholder="mis. Lazada" /></div>
            <div class="flex-1 min-w-[220px]"><label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Catatan (opsional)</label><x-form.input type="text" name="catatan" placeholder="mis. akun toko: ..." /></div>
            <x-button type="submit" variant="primary"><i class="fas fa-plus"></i> Tambah</x-button>
        </form>
    </x-card>

    <x-card>
        <div class="divide-y divide-slate-100">
            @forelse($platform as $p)
            <div class="py-4 flex flex-wrap items-center gap-3 {{ $p->aktif ? '' : 'opacity-50' }}">
                <form method="POST" action="{{ route('platform-belanja.update', $p->id) }}" class="flex flex-1 min-w-[260px] items-center gap-2">
                    @csrf @method('PUT')
                    <input type="text" name="nama" value="{{ $p->nama }}" required class="w-40 rounded-xl border-slate-200 text-sm font-medium p-2">
                    <input type="text" name="catatan" value="{{ $p->catatan }}" placeholder="Catatan" class="flex-1 rounded-xl border-slate-200 text-xs p-2">
                    <button type="submit" class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white" title="Simpan"><i class="fas fa-save text-xs"></i></button>
                </form>
                <span class="text-[11px] font-medium text-slate-400">{{ $dipakai[$p->id] ?? 0 }} pembelian</span>
                <form method="POST" action="{{ route('platform-belanja.toggle', $p->id) }}">@csrf @method('PATCH')
                    <button type="submit" class="px-3 py-2 rounded-lg text-[11px] font-medium uppercase {{ $p->aktif ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500' }}">{{ $p->aktif ? 'Aktif' : 'Nonaktif' }}</button>
                </form>
                @if(!($dipakai[$p->id] ?? 0))
                <form method="POST" action="{{ route('platform-belanja.destroy', $p->id) }}" data-konfirmasi="Hapus platform ini?">@csrf @method('DELETE')
                    <button type="submit" class="w-9 h-9 rounded-lg text-slate-500 hover:text-rose-500 hover:bg-rose-50" title="Hapus"><i class="far fa-trash-alt text-xs"></i></button>
                </form>
                @endif
            </div>
            @empty
            <p class="py-6 text-center text-sm text-slate-400">Belum ada platform.</p>
            @endforelse
        </div>
    </x-card>
</div>
@endsection
