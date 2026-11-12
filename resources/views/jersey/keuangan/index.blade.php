@extends('layouts.app')

@section('content')
<div class="jersey-mobile-finance-page w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1400px] mx-auto">

    <x-page-header title="Keuangan Jersey" subtitle="Piutang, DP & pelunasan order jersey - dicatat terpisah dari kas Kasir utama.">
        <x-slot:actions>
            <x-button href="{{ route('jersey.orders.index') }}" variant="secondary">
                <i class="fas fa-arrow-left"></i> Daftar Pesanan
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('success'))
        <div class="bg-emerald-50 text-emerald-600 p-4 rounded-2xl mb-6 font-medium">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <x-card>
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2">Total Piutang Berjalan</p>
            <p class="text-2xl font-medium text-rose-600">Rp {{ number_format($totalPiutang, 0, ',', '.') }}</p>
            <p class="text-[11px] text-slate-400 mt-1">Order belum lunas, sisa tagihan</p>
        </x-card>
        <x-card>
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2">Jatuh Tempo</p>
            <p class="text-2xl font-medium {{ $jumlahJatuhTempo > 0 ? 'text-amber-500' : 'text-slate-700' }}">{{ $jumlahJatuhTempo }} order</p>
            <p class="text-[11px] text-slate-400 mt-1">Deadline lewat, belum lunas</p>
        </x-card>
        <x-card>
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2">Diterima Bulan Ini</p>
            <p class="text-2xl font-medium text-emerald-600">Rp {{ number_format($totalDiterimaBulanIni, 0, ',', '.') }}</p>
            <p class="text-[11px] text-slate-400 mt-1">{{ now()->translatedFormat('F Y') }}</p>
        </x-card>
    </div>

    <x-card class="mb-6">
        <form method="GET" class="flex flex-col sm:flex-row gap-3">
            <div class="w-full sm:w-64 lg:w-80">
                <x-form.input type="text" name="search" value="{{ request('search') }}" placeholder="Cari no. order / pelanggan..." />
            </div>
            <x-button type="submit">Cari</x-button>
        </form>
    </x-card>

    <x-table wrapperClass="jersey-finance-mobile-table-shell overflow-hidden" class="jersey-finance-mobile-table">
        <thead>
            <tr>
                <th class="px-4 py-3 text-center w-10">#</th>
                <th class="px-4 py-3 text-left">No. Order</th>
                <th class="px-4 py-3 text-left whitespace-nowrap">Tgl Pesan</th>
                <th class="px-4 py-3 text-left">Pelanggan</th>
                <th class="px-4 py-3 text-right">Total</th>
                <th class="px-4 py-3 text-right">Dibayar</th>
                <th class="px-4 py-3 text-right">Sisa Tagihan</th>
                <th class="px-4 py-3 text-center">Deadline</th>
                <th class="px-4 py-3 text-center">Status</th>
                <th class="px-4 py-3 text-center w-24">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($piutang as $i => $o)
            <tr class="jersey-finance-row hover:bg-slate-50/50 {{ $o->jatuh_tempo ? 'bg-rose-50/40' : '' }}">
                <td data-label="Urutan" class="jersey-finance-sequence px-4 py-3 text-center text-[11px] text-slate-400">{{ $piutang->firstItem() + $i }}</td>
                <td data-label="No. Order" class="jersey-finance-order px-4 py-3 font-medium text-slate-700">{{ $o->order_number }}</td>
                <td data-label="Tanggal pesan" class="jersey-finance-date px-4 py-3 text-xs text-slate-600 whitespace-nowrap">{{ $o->order_date ? \Carbon\Carbon::parse($o->order_date)->translatedFormat('d M Y') : '-' }}</td>
                <td data-label="Pelanggan" class="jersey-finance-customer px-4 py-3">
                    <p class="font-medium text-slate-700">{{ $o->konsumen->nama_konsumen ?? $o->customer_name }}</p>
                    @if($o->team_name)<p class="text-[11px] text-slate-400">{{ $o->team_name }}</p>@endif
                </td>
                <td data-label="Total" class="jersey-finance-total px-4 py-3 text-right">Rp {{ number_format($o->total, 0, ',', '.') }}</td>
                <td data-label="Dibayar" class="jersey-finance-paid px-4 py-3 text-right text-emerald-600 font-medium">Rp {{ number_format($o->deposit, 0, ',', '.') }}</td>
                <td data-label="Sisa tagihan" class="jersey-finance-remain px-4 py-3 text-right font-medium text-rose-600">Rp {{ number_format($o->sisa_tagihan, 0, ',', '.') }}</td>
                <td data-label="Deadline" class="jersey-finance-deadline px-4 py-3 text-center text-xs">
                    {{ $o->deadline ? \Carbon\Carbon::parse($o->deadline)->translatedFormat('d M Y') : '-' }}
                    @if($o->jatuh_tempo)
                        <span class="block mt-1 px-2 py-0.5 rounded-md bg-rose-100 text-rose-600 text-[9px] font-medium uppercase tracking-widest">Jatuh Tempo</span>
                    @endif
                </td>
                <td data-label="Status" class="jersey-finance-status px-4 py-3 text-center"><x-badge :status="$o->status_pembayaran" /></td>
                <td data-label="Aksi" class="jersey-finance-action px-4 py-3 text-center">
                    <x-button href="{{ route('jersey.orders.show', $o->id) }}" size="icon" variant="ghost" title="Detail & Bayar">
                        <i class="fas fa-hand-holding-usd text-xs"></i><span class="jersey-mobile-action-label">Detail &amp; Bayar</span>
                    </x-button>
                </td>
            </tr>
            @empty
            <tr class="jersey-finance-empty-row"><td colspan="10"><x-empty-state title="Tidak ada piutang" description="Semua order jersey sudah lunas, atau belum ada order sama sekali." /></td></tr>
            @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$piutang" />
</div>
@endsection
