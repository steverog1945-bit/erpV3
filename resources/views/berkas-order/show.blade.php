@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1100px] mx-auto">

    <x-page-header title="Berkas Order {{ $nota->nomor_nota }}">
        <x-slot:subtitle>
            <i class="fas fa-folder-open text-amber-400"></i>
            {{ $nota->nama_konsumen ?? 'tanpa pelanggan' }} - kumpulan berkas per kategori (bukti, tidak bisa dihapus dari sini)
        </x-slot:subtitle>
        <x-slot:actions>
            <x-button :href="route('penjualan.show', $nota->id_transaksi)" variant="outline"><i class="fas fa-arrow-left"></i> Nota</x-button>
        </x-slot:actions>
    </x-page-header>

    @if($errors->any())
        <div class="mb-6 p-4 rounded-xl bg-rose-50 text-rose-600 text-sm font-medium">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    <x-card class="mb-8">
        <h3 class="text-xs font-medium text-slate-800 uppercase tracking-widest mb-4">Unggah Berkas</h3>
        <form method="POST" action="{{ route('berkas-order.unggah', $nota->id_transaksi) }}" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            @csrf
            <div><label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">Kategori</label>
                <select name="kategori" class="w-full rounded-xl border-slate-200 text-sm p-2">@foreach($kategoriUnggah as $k)<option value="{{ $k }}">{{ $k }}</option>@endforeach</select></div>
            <div><label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">Item (opsional)</label>
                <select name="id_detail" class="w-full rounded-xl border-slate-200 text-sm p-2"><option value="">Seluruh nota</option>@foreach($items as $it)<option value="{{ $it->id_detail }}">{{ $it->nama_file }}</option>@endforeach</select></div>
            <div><label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">File (maks 50 MB)</label>
                <input type="file" name="file" required class="w-full text-sm"></div>
            <div><label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">Catatan</label>
                <input type="text" name="catatan" maxlength="255" class="w-full rounded-xl border-slate-200 text-sm p-2"></div>
            <div class="md:col-span-4 text-right"><x-button type="submit" variant="primary"><i class="fas fa-upload"></i> Unggah</x-button></div>
        </form>
        <p class="text-[11px] text-slate-400 mt-3">Format: JPG, PNG, WEBP, PDF, ZIP, AI, PSD, CDR, EPS. Foto bukti serah terima lewat tombol serah terima di Collecting.</p>
    </x-card>

    @if($pengambilan)
    <x-card class="mb-8">
        <h3 class="text-xs font-medium text-slate-800 uppercase tracking-widest mb-2">Serah Terima</h3>
        <div class="text-sm text-slate-600">{{ $pengambilan->jenis }}{{ $pengambilan->nama_penerima ? ' - ' . $pengambilan->nama_penerima : '' }} - {{ \Carbon\Carbon::parse($pengambilan->created_at)->translatedFormat('d M Y H:i') }}{{ $pengambilan->catatan ? ' - ' . $pengambilan->catatan : '' }}</div>
    </x-card>
    @endif

    @forelse($grup as $kategori => $daftar)
    <h3 class="text-xs font-medium text-slate-500 uppercase tracking-widest mb-3 mt-8">{{ $kategori }}</h3>
    <x-card>
        @foreach($daftar as $b)
        <div class="flex flex-wrap items-center justify-between gap-3 py-3 {{ !$loop->last ? 'border-b border-slate-50' : '' }}">
            <div class="min-w-0">
                <a href="{{ $b['url'] }}" target="_blank" class="text-sm font-medium text-slate-700 hover:text-indigo-600 break-all">{{ $b['nama'] }}</a>
                <div class="text-[11px] text-slate-400">{{ $b['item'] }}{{ $b['info'] ? ' - ' . $b['info'] : '' }}{{ $b['waktu'] ? ' - ' . \Carbon\Carbon::parse($b['waktu'])->translatedFormat('d M Y H:i') : '' }}</div>
            </div>
            <a href="{{ $b['url'] }}" target="_blank" class="text-xs font-medium text-indigo-600 uppercase">Buka</a>
        </div>
        @endforeach
    </x-card>
    @empty
    <x-card><x-empty-state title="Belum ada berkas untuk nota ini" /></x-card>
    @endforelse
</div>
@endsection
