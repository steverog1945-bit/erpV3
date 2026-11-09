@extends('layouts.app')

@section('content')
<div class="jersey-orders-mobile-page w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Pesanan Jersey" subtitle="Transaksi order jersey - terpisah dari alur Kasir percetakan.">
        <x-slot:actions>
            <x-button href="{{ route('jersey.master.index') }}" variant="secondary">
                <i class="fas fa-sliders-h"></i> Master Data
            </x-button>
            <x-button href="{{ route('jersey.orders.create') }}">
                <i class="fas fa-plus"></i> Order Baru
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('success'))
        <div class="bg-emerald-50 text-emerald-600 p-4 rounded-2xl mb-6 font-medium">{{ session('success') }}</div>
    @endif

    <x-card class="mb-6">
        <form method="GET" class="jersey-orders-filter grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <x-form.input type="text" name="search" value="{{ request('search') }}" placeholder="Cari no. order / pelanggan / tim..." />
            </div>
            <x-form.select name="status">
                <option value="">-- Semua Status --</option>
                @foreach(['baru' => 'Baru', 'proses' => 'Proses', 'selesai' => 'Selesai', 'diambil' => 'Diambil'] as $val => $label)
                    <option value="{{ $val }}" @selected(request('status') === $val)>{{ $label }}</option>
                @endforeach
            </x-form.select>
            <x-button type="submit">Filter</x-button>
        </form>
    </x-card>

    {{-- Chip filter cepat: akses & baca data tanpa membuka dropdown status. --}}
    @php
        $totalChip = (int) $statusCounts->sum();
        $chipBase = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium border transition-colors';
        $chipAktif = 'bg-indigo-600 text-white border-indigo-600';
        $chipNon = 'bg-white text-slate-600 border-slate-200 hover:border-indigo-300 hover:text-indigo-600';
        $statusAktif = request('status');
        $unpaidAktif = request('bayar') === 'belum';
    @endphp
    <div class="flex flex-wrap gap-2 mb-4">
        <a href="{{ route('jersey.orders.index', array_filter(['search' => request('search')])) }}" class="{{ $chipBase }} {{ !$statusAktif && !$unpaidAktif ? $chipAktif : $chipNon }}">
            Semua <span class="opacity-70">{{ $totalChip }}</span>
        </a>
        @foreach(['baru' => 'Baru', 'proses' => 'Proses', 'selesai' => 'Selesai', 'diambil' => 'Diambil'] as $val => $label)
            <a href="{{ route('jersey.orders.index', array_filter(['search' => request('search'), 'status' => $val])) }}" class="{{ $chipBase }} {{ $statusAktif === $val ? $chipAktif : $chipNon }}">
                {{ $label }} <span class="opacity-70">{{ (int) ($statusCounts[$val] ?? 0) }}</span>
            </a>
        @endforeach
        <a href="{{ route('jersey.orders.index', array_filter(['search' => request('search'), 'bayar' => 'belum'])) }}" class="{{ $chipBase }} {{ $unpaidAktif ? $chipAktif : $chipNon }}">
            Belum Lunas <span class="opacity-70">{{ $unpaidCount }}</span>
        </a>
    </div>

    <x-table wrapperClass="jersey-orders-mobile-table-shell overflow-hidden" class="jersey-orders-mobile-table">
        <thead>
            <tr>
                <th class="px-4 py-3 text-center w-10">#</th>
                <th class="px-4 py-3 text-left">No. Order</th>
                <th class="px-4 py-3 text-left whitespace-nowrap">Tgl Pesan</th>
                <th class="px-4 py-3 text-left">Pelanggan / Tim</th>
                <th class="px-4 py-3 text-left">Barang</th>
                <th class="px-4 py-3 text-center">Deadline</th>
                <th class="px-4 py-3 text-center">Status</th>
                <th class="px-4 py-3 text-center">Bayar</th>
                @bolehHarga<th class="px-4 py-3 text-right">Total</th>@endbolehHarga
                <th class="px-4 py-3 text-center w-24">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($orders as $i => $o)
            <tr class="jersey-orders-row hover:bg-slate-50/50">
                <td data-label="Urutan" class="jersey-order-sequence px-4 py-3 text-center text-[11px] text-slate-400">{{ $orders->firstItem() + $i }}</td>
                <td data-label="No. Order" class="jersey-order-number px-4 py-3 font-medium text-slate-700">{{ $o->order_number }}</td>
                <td data-label="Tanggal pesan" class="jersey-order-date px-4 py-3 text-xs text-slate-600 whitespace-nowrap">{{ $o->order_date ? \Carbon\Carbon::parse($o->order_date)->translatedFormat('d M Y') : '-' }}</td>
                <td data-label="Pelanggan / Tim" class="jersey-order-customer px-4 py-3">
                    <p class="font-medium text-slate-700">{{ $o->konsumen->nama_konsumen ?? $o->customer_name }}</p>
                    @if($o->team_name)<p class="text-[11px] text-slate-400">{{ $o->team_name }}</p>@endif
                </td>
                <td data-label="Barang" class="jersey-order-product px-4 py-3 text-xs text-slate-500">
                    {{ $o->lines->pluck('nama_barang')->implode(', ') ?: '-' }}
                    @if($o->lines->count() > 1)<span class="text-slate-500"> ({{ $o->lines->count() }} jenis)</span>@endif
                </td>
                <td data-label="Deadline" class="jersey-order-deadline px-4 py-3 text-center text-xs">{{ $o->deadline ? \Carbon\Carbon::parse($o->deadline)->translatedFormat('d M Y') : '-' }}</td>
                <td data-label="Status" class="jersey-order-status px-4 py-3 text-center"><x-badge :status="$o->status" /></td>
                <td data-label="Bayar" class="jersey-order-payment px-4 py-3 text-center"><x-badge :status="$o->status_pembayaran" /></td>
                @bolehHarga<td data-label="Total" class="jersey-order-total px-4 py-3 text-right font-medium">Rp {{ number_format($o->total, 0, ',', '.') }}</td>@endbolehHarga
                <td data-label="Aksi" class="jersey-order-action px-4 py-3 text-center">
                    <x-button href="{{ route('jersey.orders.show', $o->id) }}" size="icon" variant="ghost" class="hover:text-slate-700" title="Detail">
                        <i class="fas fa-eye text-xs"></i><span class="jersey-mobile-action-label">Detail</span>
                    </x-button>
                </td>
            </tr>
            @empty
            <tr class="jersey-orders-empty-row"><td colspan="10"><x-empty-state title="Belum ada order jersey" description="Klik 'Order Baru' untuk membuat order pertama." /></td></tr>
            @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$orders" />
</div>
@endsection
