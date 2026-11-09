@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Permintaan Pembelian">
        <x-slot:subtitle>
            <i class="fas fa-clipboard-list text-amber-400"></i>
            Permintaan pembelian bahan - disetujui dulu lewat Approval Workflow, baru dijadikan Purchase Order
        </x-slot:subtitle>
        <x-slot:actions>
            <x-button :href="route('purchase-request.create')" variant="primary">
                <i class="fas fa-plus"></i> Purchase Request Baru
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-8">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <div class="w-full md:w-64">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Status</label>
                <x-form.select name="status" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    @foreach(['Draft', 'Diajukan', 'Disetujui', 'Ditolak', 'Diproses PO'] as $s)
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
                <th class="px-6 py-5 text-left">Tanggal</th>
                <th class="px-6 py-5 text-left">Isi permintaan</th>
                <th class="px-6 py-5 text-left">Supplier</th>
                <th class="px-6 py-5 text-left">Dibuat Oleh</th>
                <th class="px-6 py-5 text-center">Status</th>
                <th class="px-8 py-5 text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($daftar as $p)
            <tr class="hover:bg-slate-50/30 transition-all">
                <td class="px-8 py-5">
                    <span class="text-[13px] font-medium text-slate-700">{{ $p->nomor }}</span>
                    @if($p->catatan)<div class="text-[11px] text-slate-400">{{ $p->catatan }}</div>@endif
                </td>
                <td class="px-6 py-5"><span class="text-xs text-slate-500">{{ $p->tanggal->translatedFormat('d M Y') }}</span></td>
                <td class="px-6 py-5 align-top">
                    {{-- Tiga item pertama tampil langsung; sisanya lewat "lainnya" supaya baris tidak memanjang. --}}
                    @forelse($p->details->take(3) as $d)
                        <div class="text-[13px] text-slate-700 whitespace-nowrap">{{ $d->bahan->nama_bahan ?? 'Bahan dihapus' }} <span class="text-slate-400">{{ angka($d->qty, 2) }} {{ $d->bahan->satuan_stok ?? '' }}</span></div>
                    @empty
                        <span class="text-xs text-slate-400">Belum ada item</span>
                    @endforelse
                    @if($p->details->count() > 3)
                        <details class="text-[13px]">
                            <summary class="cursor-pointer text-slate-500">{{ $p->details->count() - 3 }} item lainnya</summary>
                            @foreach($p->details->slice(3) as $d)
                                <div class="text-slate-700 whitespace-nowrap">{{ $d->bahan->nama_bahan ?? 'Bahan dihapus' }} <span class="text-slate-400">{{ angka($d->qty, 2) }} {{ $d->bahan->satuan_stok ?? '' }}</span></div>
                            @endforeach
                        </details>
                    @endif
                </td>
                <td class="px-6 py-5 align-top">
                    @php
                        // Bila sudah jadi Pesanan Pembelian, supplier yang sebenarnya dipakai lebih penting dari rencananya.
                        $poAktif = $p->purchaseOrders->where('status', '!=', 'Dibatalkan');
                    @endphp
                    @if($poAktif->isNotEmpty())
                        @foreach($poAktif as $po)
                            <div class="text-[13px] text-slate-700">{{ $po->supplier->nama_supplier ?? '-' }}</div>
                            <div class="text-xs text-slate-400">{{ $po->nomor }}</div>
                        @endforeach
                    @elseif($p->supplierRencana)
                        <div class="text-[13px] text-slate-700">{{ $p->supplierRencana->nama_supplier }}</div>
                        <div class="text-xs text-slate-400">Rencana</div>
                    @else
                        <span class="text-xs text-slate-400">Belum ditentukan</span>
                    @endif
                </td>
                <td class="px-6 py-5"><span class="text-sm font-medium text-slate-600">{{ $p->pembuat?->username ?? '-' }}</span></td>
                <td class="px-6 py-5 text-center">
                    @php $variantMap = ['Draft' => 'info', 'Diajukan' => 'warning', 'Disetujui' => 'success', 'Ditolak' => 'danger', 'Diproses PO' => 'success']; @endphp
                    <x-badge :status="$p->status" :variant="$variantMap[$p->status] ?? 'info'" />
                </td>
                <td class="px-8 py-5 text-right">
                    <div class="flex items-center justify-end gap-2">
                        @if($p->status === 'Disetujui')
                            <x-button size="sm" variant="success" :href="route('purchase-order.create', ['from_pr' => $p->id])">
                                <i class="fas fa-file-invoice"></i> Buat PO
                            </x-button>
                        @endif
                        <x-button size="sm" variant="{{ $p->status === 'Draft' ? 'secondary' : 'outline' }}" :href="route('purchase-request.edit', $p->id)">
                            <i class="fas fa-{{ $p->status === 'Draft' ? 'edit' : 'eye' }}"></i> {{ $p->status === 'Draft' ? 'Lanjutkan' : 'Lihat' }}
                        </x-button>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="7"><x-empty-state title="Belum ada Permintaan Pembelian" /></td></tr>
            @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$daftar" />
</div>
@endsection
