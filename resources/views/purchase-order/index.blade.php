@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Pesanan Pembelian">
        <x-slot:subtitle>
            <i class="fas fa-file-invoice text-amber-400"></i>
            Pesanan pembelian ke supplier - dibuat langsung atau dari Purchase Request yang sudah disetujui
        </x-slot:subtitle>
        <x-slot:actions>
            <x-button :href="route('purchase-order.create')" variant="primary">
                <i class="fas fa-plus"></i> Purchase Order Baru
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-8">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <div class="w-full md:w-64">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Status</label>
                <x-form.select name="status" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    @foreach(['Draft', 'Dipesan', 'Diterima Sebagian', 'Selesai', 'Dibatalkan'] as $s)
                        <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </x-form.select>
            </div>
        </form>
    </x-card>

    <x-table>
        <thead>
            <tr>
                <th class="px-8 py-5 text-left">Nomor</th>
                <th class="px-6 py-5 text-left">Pemasok</th>
                <th class="px-6 py-5 text-left">Tanggal</th>
                <th class="px-6 py-5 text-right">Total</th>
                <th class="px-6 py-5 text-center">Status</th>
                <th class="px-8 py-5 text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($daftar as $p)
            <tr class="hover:bg-slate-50/30 transition-all">
                <td class="px-8 py-5"><span class="text-[13px] font-medium text-slate-700">{{ $p->nomor }}</span></td>
                <td class="px-6 py-5"><span class="text-sm font-medium text-slate-600">{{ $p->supplier?->nama_supplier ?? '-' }}</span></td>
                <td class="px-6 py-5"><span class="text-xs text-slate-500">{{ $p->tanggal->translatedFormat('d M Y') }}</span></td>
                <td class="px-6 py-5 text-right"><span class="text-sm font-medium text-slate-700">Rp {{ number_format($p->total, 0, ',', '.') }}</span></td>
                <td class="px-6 py-5 text-center">
                    @php $variantMap = ['Draft' => 'info', 'Dipesan' => 'warning', 'Diterima Sebagian' => 'warning', 'Selesai' => 'success', 'Dibatalkan' => 'danger']; @endphp
                    <x-badge :status="$p->status" :variant="$variantMap[$p->status] ?? 'info'" />
                </td>
                <td class="px-8 py-5 text-right">
                    <x-button size="sm" variant="{{ $p->status === 'Draft' ? 'secondary' : 'outline' }}" :href="route('purchase-order.edit', $p->id)">
                        <i class="fas fa-{{ $p->status === 'Draft' ? 'edit' : 'eye' }}"></i> {{ $p->status === 'Draft' ? 'Lanjutkan' : 'Lihat' }}
                    </x-button>
                </td>
            </tr>
            @empty
            <tr><td colspan="6"><x-empty-state title="Belum ada Pesanan Pembelian" /></td></tr>
            @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$daftar" />
</div>
@endsection
