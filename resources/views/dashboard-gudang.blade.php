@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">
@php $tabInfo = request('tab') === 'info' && \App\Support\Izin::boleh(auth()->user(), [67], 'view'); @endphp
@include('partials.dashboard-tab', ['tabInfo' => $tabInfo])
@if($tabInfo)
    @include('postingan._daftar', array_merge(\App\Http\Controllers\PostinganController::dataDaftar(request(), route('dashboard')), ['basisUrl' => route('dashboard'), 'paramTetap' => ['tab' => 'info']]))
@else
<div class="mb-8">
    <h1 class="text-3xl font-medium text-slate-900 tracking-tight">Dashboard Gudang</h1>
    <p class="text-slate-500 text-sm">Kondisi stok bahan baku saat ini, {{ now()->translatedFormat('d F Y') }} &middot; {{ Auth::user()->username }}</p>
</div>

{{-- RINGKASAN STOK - tanpa nominal (harga rata-rata tidak ditampilkan). --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <a href="{{ route('bahan.index') }}" class="bg-white p-6 rounded-lg border border-slate-100 shadow-sm hover:shadow-md transition text-center group">
        <div class="text-3xl font-medium text-slate-800 group-hover:text-indigo-600 transition-colors">{{ $jumlahBahan }}</div>
        <div class="text-[10px] font-medium text-slate-400 uppercase tracking-wider mt-1">Bahan Aktif</div>
    </a>
    <div class="bg-white p-6 rounded-lg border border-slate-100 shadow-sm text-center">
        <div class="text-3xl font-medium text-rose-500">{{ $jumlahHabis }}</div>
        <div class="text-[10px] font-medium text-slate-400 uppercase tracking-wider mt-1">Stok Habis</div>
    </div>
    <div class="bg-white p-6 rounded-lg border border-slate-100 shadow-sm text-center">
        <div class="text-3xl font-medium text-amber-500">{{ $jumlahMenipis }}</div>
        <div class="text-[10px] font-medium text-slate-400 uppercase tracking-wider mt-1">Stok Menipis</div>
    </div>
    <div class="bg-white p-6 rounded-lg border border-slate-100 shadow-sm text-center">
        <div class="text-3xl font-medium text-sky-500">{{ $jumlahBelumAdaBatas }}</div>
        <div class="text-[10px] font-medium text-slate-400 uppercase tracking-wider mt-1">Belum Ada Batas Minimal</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    {{-- BAHAN PERLU PERHATIAN (Habis/Menipis) --}}
    <div class="bg-white rounded-lg border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-medium text-slate-800"><i class="fas fa-exclamation-triangle text-amber-500 mr-1.5" aria-hidden="true"></i>Perlu Pengadaan</h3>
            <a href="{{ route('laporan.stok-bahan') }}" class="text-[10px] font-medium text-indigo-500 uppercase tracking-widest hover:underline">Lihat Laporan</a>
        </div>
        <div class="divide-y divide-slate-50">
            @forelse($bahanPerluPerhatian as $b)
            <div class="px-6 py-4 flex items-center justify-between hover:bg-slate-50/50 transition-all">
                <div>
                    <div class="text-[13px] font-medium text-slate-700">{{ $b->nama_bahan }}</div>
                    <div class="text-[11px] text-slate-400">{{ $b->kode_bahan }} &middot; Sisa {{ number_format($b->stok, 2, ',', '.') }} {{ $b->satuan_stok }}</div>
                </div>
                <x-badge :variant="$b->status_stok === 'Habis' ? 'danger' : 'warning'">{{ $b->status_stok }}</x-badge>
            </div>
            @empty
            <div class="px-6 py-10">
                <x-empty-state title="Tidak ada bahan yang perlu perhatian" description="Semua stok masih di batas aman." />
            </div>
            @endforelse
        </div>
    </div>

    {{-- KEDATANGAN BAHAN TERBARU --}}
    <div class="bg-white rounded-lg border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-medium text-slate-800">Kedatangan Bahan Terbaru</h3>
            <a href="{{ route('kedatangan-bahan.index') }}" class="text-[10px] font-medium text-indigo-500 uppercase tracking-widest hover:underline">Lihat Semua</a>
        </div>
        <div class="divide-y divide-slate-50">
            @forelse($kedatanganTerbaru as $k)
            <div class="px-6 py-4 flex items-center justify-between hover:bg-slate-50/50 transition-all">
                <div>
                    <div class="text-[13px] font-medium text-slate-700">{{ $k->no_surat_jalan ?: '-' }}</div>
                    <div class="text-[11px] text-slate-400">{{ $k->nama_supplier ?? '-' }} &middot; {{ \Carbon\Carbon::parse($k->tgl_datang)->translatedFormat('d M Y') }}</div>
                </div>
                <x-badge :status="$k->status" />
            </div>
            @empty
            <div class="px-6 py-10">
                <x-empty-state title="Belum ada kedatangan bahan" />
            </div>
            @endforelse
        </div>
    </div>
</div>

@include('partials.dashboard-info-terbaru')
@endif
</div>
@endsection
