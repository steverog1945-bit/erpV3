@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">
@php $tabInfo = request('tab') === 'info' && \App\Support\Izin::boleh(auth()->user(), [67], 'view'); @endphp
@include('partials.dashboard-tab', ['tabInfo' => $tabInfo])
@if($tabInfo)
    @include('postingan._daftar', array_merge(\App\Http\Controllers\PostinganController::dataDaftar(request(), route('dashboard')), ['basisUrl' => route('dashboard'), 'paramTetap' => ['tab' => 'info']]))
@else
<div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
        <h1 class="text-3xl font-medium text-slate-900 tracking-tight">Dashboard Produksi</h1>
        <p class="text-slate-500 text-sm">Antrean pekerjaan cetak, {{ now()->translatedFormat('d F Y') }} &middot; {{ Auth::user()->username }}</p>
    </div>
    @if($namaMesinDiizinkan && $namaMesinDiizinkan->isNotEmpty())
    <div class="flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-100 text-slate-600 border border-slate-200">
        <i class="fas fa-print text-xs"></i>
        <span class="text-[11px] font-medium uppercase tracking-widest">{{ $namaMesinDiizinkan->implode(', ') }}</span>
    </div>
    @endif
</div>

{{-- RINGKASAN - tanpa nominal sama sekali, dibatasi ke mesin/divisi
     tanggung jawab sendiri (kalau ada penugasan mesin). --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <a href="{{ route('spk.index', ['status_spk' => 'Antre']) }}" class="bg-white p-6 rounded-lg border border-slate-100 shadow-sm hover:shadow-md transition text-center group">
        <div class="text-3xl font-medium text-amber-500 group-hover:text-amber-600 transition-colors">{{ $spkAntre }}</div>
        <div class="text-[10px] font-medium text-slate-400 uppercase tracking-wider mt-1">SPK Antre</div>
    </a>
    <a href="{{ route('produksi.index') }}" class="bg-white p-6 rounded-lg border border-slate-100 shadow-sm hover:shadow-md transition text-center group">
        <div class="text-3xl font-medium text-indigo-500 group-hover:text-indigo-600 transition-colors">{{ $spkProses }}</div>
        <div class="text-[10px] font-medium text-slate-400 uppercase tracking-wider mt-1">Sedang Dikerjakan</div>
    </a>
    <a href="{{ route('produksi.index', ['status_produksi' => 'selesai']) }}" class="bg-white p-6 rounded-lg border border-slate-100 shadow-sm hover:shadow-md transition text-center group">
        <div class="text-3xl font-medium text-emerald-500 group-hover:text-emerald-600 transition-colors">{{ $spkSelesai }}</div>
        <div class="text-[10px] font-medium text-slate-400 uppercase tracking-wider mt-1">Selesai</div>
    </a>
    <a href="{{ route('spk.index', ['urgent_only' => 1]) }}" class="bg-white p-6 rounded-lg border border-slate-100 shadow-sm hover:shadow-md transition text-center group">
        <div class="text-3xl font-medium text-rose-500 group-hover:text-rose-600 transition-colors">{{ $urgentAktif }}</div>
        <div class="text-[10px] font-medium text-slate-400 uppercase tracking-wider mt-1">🔥 Urgent Aktif</div>
    </a>
</div>

{{-- SPK YANG PERLU DIKERJAKAN - langsung tersaji (bukan cuma angka),
     Antre dulu (siap diambil), baru yang sedang dikerjakan tapi urgent. --}}
<div class="bg-white rounded-lg border border-slate-100 shadow-sm overflow-hidden mb-6">
    <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
        <h3 class="text-sm font-medium text-slate-800">SPK Perlu Dikerjakan</h3>
        <a href="{{ route('spk.index') }}" class="text-[10px] font-medium text-indigo-500 uppercase tracking-widest hover:underline">Buka SPK</a>
    </div>
    <div class="divide-y divide-slate-50">
        @forelse($spkPerluDikerjakan as $s)
        <div class="px-6 py-4 flex flex-wrap items-center justify-between gap-3 hover:bg-slate-50/50 transition-all">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[13px] font-medium text-slate-700 uppercase">{{ $s->nama_file }}</span>
                    @if($s->status_urgent)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[9px] font-medium uppercase tracking-wider bg-rose-50 text-rose-600 border border-rose-100">🔥 Urgent</span>
                    @endif
                </div>
                <div class="text-[11px] text-slate-400">{{ $s->nomor_nota }} &middot; {{ $s->nama_konsumen }} &middot; {{ $s->divisi }} &middot; Qty {{ angka($s->qty, 4) }}</div>
            </div>
            <x-badge :status="$s->status_spk" />
        </div>
        @empty
        <div class="px-6 py-14">
            <x-empty-state title="Tidak ada SPK yang perlu dikerjakan" description="Semua pekerjaan di divisi Anda sudah selesai atau belum ada order baru." />
        </div>
        @endforelse
    </div>
</div>

{{-- ELEMEN PENDUKUNG - akses cepat ke halaman kerja sehari-hari. --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <a href="{{ route('spk.index', ['status_spk' => 'Antre']) }}" class="bg-white p-6 rounded-lg border border-slate-100 shadow-sm hover:shadow-md hover:border-indigo-200 transition flex items-center gap-4">
        <div class="bg-amber-100 p-3 rounded-lg text-2xl">📋</div>
        <div>
            <h4 class="text-sm font-medium text-slate-800">Ambil Pekerjaan</h4>
            <p class="text-[11px] text-slate-400">Lihat & ambil SPK yang masih Antre</p>
        </div>
    </a>
    <a href="{{ route('produksi.index') }}" class="bg-white p-6 rounded-lg border border-slate-100 shadow-sm hover:shadow-md hover:border-indigo-200 transition flex items-center gap-4">
        <div class="bg-indigo-100 p-3 rounded-lg text-2xl">🖨️</div>
        <div>
            <h4 class="text-sm font-medium text-slate-800">Proses Produksi</h4>
            <p class="text-[11px] text-slate-400">Update qty cetak & tandai selesai</p>
        </div>
    </a>
    <a href="{{ route('finishing.index') }}" class="bg-white p-6 rounded-lg border border-slate-100 shadow-sm hover:shadow-md hover:border-indigo-200 transition flex items-center gap-4">
        <div class="bg-emerald-100 p-3 rounded-lg text-2xl">✂️</div>
        <div>
            <h4 class="text-sm font-medium text-slate-800">Finishing</h4>
            <p class="text-[11px] text-slate-400">Kelola tahap finishing produk</p>
        </div>
    </a>
</div>

@include('partials.dashboard-info-terbaru')

@endif
</div>
@endsection
