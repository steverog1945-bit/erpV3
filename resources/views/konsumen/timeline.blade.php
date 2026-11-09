@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1200px] mx-auto">

    <x-page-header title="Timeline Pelanggan">
        <x-slot:subtitle>
            <i class="fas fa-stream text-amber-400"></i>
            {{ $konsumen->nama_konsumen }} - {{ $konsumen->telepon ?: 'tanpa telepon' }} - {{ $konsumen->nama_golongan ?? 'tanpa golongan' }}
        </x-slot:subtitle>
        <x-slot:actions>
            <x-button :href="route('konsumen.index')" variant="outline"><i class="fas fa-arrow-left"></i> Data Konsumen</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        @foreach([
            ['Total Omzet', 'Rp ' . number_format($ringkasan['omzet'], 0, ',', '.'), $ringkasan['jumlah_nota'] . ' nota aktif', 'text-slate-800'],
            ['Sudah Dibayar', 'Rp ' . number_format($ringkasan['dibayar'], 0, ',', '.'), '', 'text-emerald-600'],
            ['Piutang', 'Rp ' . number_format($ringkasan['piutang'], 0, ',', '.'), '', $ringkasan['piutang'] > 0 ? 'text-rose-600' : 'text-slate-400'],
            ['Saldo Deposit', 'Rp ' . number_format($ringkasan['saldo_deposit'], 0, ',', '.'), '', 'text-sky-600'],
        ] as [$label, $nilai, $ket, $warna])
        <x-card>
            <div class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2">{{ $label }}</div>
            <div class="text-lg font-medium {{ $warna }}">{{ $nilai }}</div>
            @if($ket)<div class="text-[11px] text-slate-400">{{ $ket }}</div>@endif
        </x-card>
        @endforeach
    </div>
    <div class="flex flex-wrap gap-x-8 gap-y-1 text-xs text-slate-500 mb-8 px-1">
        <span>Order terakhir: <b>{{ $ringkasan['order_terakhir'] ? \Carbon\Carbon::parse($ringkasan['order_terakhir'])->translatedFormat('d M Y') : '-' }}</b></span>
        <span>Belum diambil: <b>{{ $ringkasan['belum_diambil'] }}</b> nota</span>
        <span>Penawaran: <b>{{ $ringkasan['jumlah_penawaran'] }}</b> ({{ $ringkasan['penawaran_jadi_order'] }} jadi order)</span>
        <span>Order Jersey: <b>{{ $ringkasan['jumlah_jersey'] }}</b> (Rp {{ number_format($ringkasan['omzet_jersey'], 0, ',', '.') }})</span>
    </div>

    @php
        $kelasWarna = [
            'amber' => 'bg-amber-50 text-amber-500', 'indigo' => 'bg-indigo-50 text-indigo-500', 'rose' => 'bg-rose-50 text-rose-500',
            'emerald' => 'bg-emerald-50 text-emerald-500', 'sky' => 'bg-sky-50 text-sky-500', 'violet' => 'bg-violet-50 text-violet-500',
        ];
    @endphp
    <x-card>
        @forelse($kejadian as $k)
        <div class="flex gap-4 py-4 {{ !$loop->last ? 'border-b border-slate-50' : '' }}">
            <div class="w-10 h-10 rounded-full {{ $kelasWarna[$k['warna']] ?? 'bg-slate-50 text-slate-500' }} flex items-center justify-center shrink-0"><i class="fas fa-{{ $k['ikon'] }} text-sm"></i></div>
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap justify-between gap-2">
                    <div class="text-sm font-medium text-slate-700">
                        @if($k['url'])<a href="{{ $k['url'] }}" class="hover:text-indigo-600">{{ $k['judul'] }}</a>@else{{ $k['judul'] }}@endif
                        <span class="ml-2 text-[10px] font-medium uppercase tracking-widest text-slate-500">{{ $k['jenis'] }}</span>
                    </div>
                    <div class="text-sm font-medium {{ $k['nominal'] < 0 ? 'text-rose-500' : 'text-slate-700' }}">Rp {{ number_format($k['nominal'], 0, ',', '.') }}</div>
                </div>
                <div class="text-xs text-slate-400">{{ $k['waktu']->translatedFormat('d M Y H:i') }}{{ $k['detail'] ? ' - ' . $k['detail'] : '' }}</div>
            </div>
        </div>
        @empty
        <x-empty-state title="Belum ada aktivitas untuk pelanggan ini" />
        @endforelse
    </x-card>
</div>
@endsection
