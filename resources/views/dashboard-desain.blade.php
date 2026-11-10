@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">
@php $tabInfo = request('tab') === 'info' && \App\Support\Izin::boleh(auth()->user(), [67], 'view'); @endphp
@include('partials.dashboard-tab', ['tabInfo' => $tabInfo])
@if($tabInfo)
    @include('postingan._daftar', array_merge(\App\Http\Controllers\PostinganController::dataDaftar(request(), route('dashboard')), ['basisUrl' => route('dashboard'), 'paramTetap' => ['tab' => 'info']]))
@else
<div class="mb-8">
    <h1 class="text-3xl font-medium text-slate-900 tracking-tight">Dashboard</h1>
    <p class="text-slate-500 text-sm">Pergerakan order & produksi hari ini, {{ now()->translatedFormat('d F Y') }} &middot; {{ Auth::user()->username }} ({{ Auth::user()->role->nama_role }})</p>
</div>

{{-- ALUR ORDER -> SPK -> PRODUKSI -> SELESAI -> COLLECTING - tidak ada
     satu pun angka Rupiah di halaman ini (role ini cuma input order &
     lihat status, bukan transaksi/nominal). --}}
<div class="bg-white rounded-lg border border-slate-100 shadow-sm p-6 mb-6">
    <p class="text-[10px] font-medium text-slate-400 uppercase tracking-[0.2em] mb-5">Alur Pergerakan Order</p>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <a href="{{ route('penjualan.riwayat') }}" class="text-center group">
            <div class="text-3xl font-medium text-slate-800 group-hover:text-indigo-600 transition-colors">{{ $orderHariIni }}</div>
            <div class="text-[10px] font-medium text-slate-400 uppercase tracking-wider mt-1">Order Hari Ini</div>
        </a>
        <a href="{{ route('spk.index', ['status_spk' => 'Antre']) }}" class="text-center group">
            <div class="text-3xl font-medium text-amber-500 group-hover:text-amber-600 transition-colors">{{ $spkAntre }}</div>
            <div class="text-[10px] font-medium text-slate-400 uppercase tracking-wider mt-1">SPK Antre</div>
        </a>
        <a href="{{ route('produksi.index') }}" class="text-center group">
            <div class="text-3xl font-medium text-indigo-500 group-hover:text-indigo-600 transition-colors">{{ $spkProses }}</div>
            <div class="text-[10px] font-medium text-slate-400 uppercase tracking-wider mt-1">Produksi Berjalan</div>
        </a>
        <a href="{{ route('collecting.index', ['status_pengerjaan' => 'lengkap']) }}" class="text-center group">
            <div class="text-3xl font-medium text-teal-500 group-hover:text-teal-600 transition-colors">{{ $notaLengkap }}</div>
            <div class="text-[10px] font-medium text-slate-400 uppercase tracking-wider mt-1">Siap Diambil</div>
        </a>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white p-6 rounded-lg border border-slate-100 shadow-sm hover:shadow-md transition">
        <div class="flex items-center gap-4">
            <div class="bg-emerald-100 p-3 rounded-lg"><i class="fas fa-check-circle text-2xl text-emerald-600" aria-hidden="true"></i></div>
            <div>
                <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Selesai Produksi</p>
                <h3 class="text-lg font-medium text-slate-900">{{ $spkSelesai }} item</h3>
            </div>
        </div>
    </div>
    <div class="bg-white p-6 rounded-lg border border-slate-100 shadow-sm hover:shadow-md transition">
        <div class="flex items-center gap-4">
            <div class="bg-orange-100 p-3 rounded-lg"><i class="fas fa-fire text-2xl text-orange-600" aria-hidden="true"></i></div>
            <div>
                <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Item Urgent Aktif</p>
                <h3 class="text-lg font-medium text-slate-900">{{ $urgentAktif }}</h3>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ORDER TERBARU - status pembayaran (badge) boleh terlihat, TANPA
         nominal sama sekali (tidak ada Rp di sini). --}}
    <div class="lg:col-span-2 bg-white rounded-lg border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-medium text-slate-800">Order Terbaru</h3>
            <a href="{{ route('penjualan.riwayat', ['from' => now()->subDays(30)->format('Y-m-d'), 'to' => now()->format('Y-m-d')]) }}" class="text-[10px] font-medium text-indigo-500 uppercase tracking-widest hover:underline">Lihat Semua</a>
        </div>
        <div class="divide-y divide-slate-50">
            @forelse($orderTerbaru as $o)
            <div class="px-6 py-4 flex items-center justify-between hover:bg-slate-50/50 transition-all">
                <div>
                    <div class="text-[13px] font-medium text-slate-700 uppercase">{{ $o->nomor_nota }}</div>
                    <div class="text-[11px] text-slate-400">{{ $o->nama_konsumen }} &middot; {{ \Carbon\Carbon::parse($o->tgl_order)->translatedFormat('d M Y H:i') }}</div>
                </div>
                <div class="text-right">
                    @if($o->status_nota === 'Dibatalkan')
                        <x-badge status="Dibatalkan" variant="danger" />
                    @else
                        <x-badge :status="$o->status_pembayaran" />
                    @endif
                </div>
            </div>
            @empty
            <div class="px-6 py-10">
                <x-empty-state title="Belum ada order" />
            </div>
            @endforelse
        </div>
    </div>

    {{-- PERLU PERHATIAN (URGENT) --}}
    <div class="bg-white rounded-lg border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-medium text-slate-800"><i class="fas fa-fire text-rose-500 mr-1.5" aria-hidden="true"></i>Perlu Perhatian</h3>
            <a href="{{ route('spk.index', ['urgent_only' => 1]) }}" class="text-[10px] font-medium text-indigo-500 uppercase tracking-widest hover:underline">Semua</a>
        </div>
        <div class="divide-y divide-slate-50">
            @forelse($urgentList as $u)
            <div class="px-6 py-4 hover:bg-slate-50/50 transition-all">
                <div class="text-[12px] font-medium text-slate-700 uppercase">{{ $u->nama_file }}</div>
                <div class="text-[11px] text-slate-400">{{ $u->nama_konsumen }}</div>
                <x-badge :status="$u->status_spk" class="mt-1" />
            </div>
            @empty
            <div class="px-6 py-10">
                <x-empty-state title="Tidak ada item urgent" description="Semua pekerjaan berjalan normal." />
            </div>
            @endforelse
        </div>
    </div>
</div>

@include('partials.dashboard-info-terbaru')

@endif
</div>
@endsection
