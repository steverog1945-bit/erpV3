@extends('layouts.app')

@section('content')
<div id="new-data-alert" class="hidden fixed top-10 left-1/2 -translate-x-1/2 z-[100]">
    <button onclick="location.reload()" class="bg-amber-500 text-white px-8 py-4 rounded-2xl shadow-2xl font-medium text-sm flex items-center gap-3 animate-bounce border-4 border-white">
        <span class="text-xl">📩</span> <span id="text-count">ADA PESANAN BARU!</span>
    </button>
</div>

<div class="mb-8">
    <h2 class="text-2xl font-medium text-slate-800 uppercase tracking-tight">Pesanan Baru (Open Order)</h2>
    <p class="text-slate-500 text-sm font-medium">Daftar pesanan yang baru masuk dari admin/desain dan belum diproses kasir.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @forelse($transaksi as $t)
    <div class="bg-white rounded-2xl border-2 border-slate-100 p-6 hover:border-indigo-500 transition-all group shadow-sm">
        <div class="flex justify-between items-start mb-4">
            <span class="bg-slate-100 text-slate-600 px-4 py-1 rounded-xl text-[10px] font-medium uppercase">#{{ $t->nomor_nota }}</span>
            <span class="text-[10px] font-medium text-slate-400">{{ date('H:i', strtotime($t->tgl_order)) }}</span>
        </div>
        
        <h3 class="text-lg font-medium text-slate-800 uppercase mb-1">{{ $t->nama_konsumen }}</h3>
        <p class="text-[11px] font-medium text-slate-500 mb-6 italic">Pesanan masuk, menunggu validasi pembayaran.</p>

        <div class="flex items-center justify-between pt-4 border-t border-slate-50">
            <div>
                <p class="text-[9px] font-medium text-slate-400 uppercase">Estimasi Tagihan</p>
                <p class="text-lg font-medium text-slate-800">Rp {{ number_format($t->total_bayar, 0, ',', '.') }}</p>
            </div>
            <a href="{{ url('/kasir/bayar-form/'.$t->id_transaksi) }}" class="bg-slate-900 group-hover:bg-indigo-600 text-white w-12 h-12 rounded-2xl flex items-center justify-center transition-all shadow-sm">
                <span class="text-xl">💰</span>
            </a>
        </div>
    </div>
    @empty
    <div class="col-span-full py-20 text-center">
        <div class="text-6xl mb-4">☕</div>
        <p class="text-slate-400 font-medium italic text-sm">Belum ada pesanan baru. Waktunya istirahat sejenak.</p>
    </div>
    @endforelse
</div>
@endsection