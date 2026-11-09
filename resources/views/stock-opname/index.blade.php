@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Stok Opname">
        <x-slot:subtitle>
            <i class="fas fa-clipboard-list text-amber-400"></i>
            Hitung fisik stok gudang - stok baru berubah setelah hasil hitung disetujui lewat Approval Workflow
        </x-slot:subtitle>
        <x-slot:actions>
            <x-button :href="route('stock-opname.create')" variant="primary">
                <i class="fas fa-plus"></i> Stock Opname Baru
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-8">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <div class="w-full md:w-64">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Status</label>
                <x-form.select name="status" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    <option value="Draft" {{ request('status') == 'Draft' ? 'selected' : '' }}>Draft</option>
                    <option value="Diajukan" {{ request('status') == 'Diajukan' ? 'selected' : '' }}>Diajukan</option>
                    <option value="Disetujui" {{ request('status') == 'Disetujui' ? 'selected' : '' }}>Disetujui</option>
                    <option value="Ditolak" {{ request('status') == 'Ditolak' ? 'selected' : '' }}>Ditolak</option>
                </x-form.select>
            </div>
        </form>
    </x-card>

    <x-table>
        <thead>
            <tr>
                <th class="px-8 py-5 text-left">Nomor</th>
                <th class="px-6 py-5 text-left">Tanggal</th>
                <th class="px-6 py-5 text-left">Dibuat Oleh</th>
                <th class="px-6 py-5 text-center">Status</th>
                <th class="px-8 py-5 text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($daftar as $o)
            <tr class="hover:bg-slate-50/30 transition-all">
                <td class="px-8 py-5">
                    <span class="text-[13px] font-medium text-slate-700">{{ $o->nomor }}</span>
                    @if($o->catatan)
                        <div class="text-[11px] text-slate-400">{{ $o->catatan }}</div>
                    @endif
                </td>
                <td class="px-6 py-5">
                    <span class="text-xs text-slate-500">{{ $o->tanggal->translatedFormat('d M Y') }}</span>
                </td>
                <td class="px-6 py-5">
                    <span class="text-sm font-medium text-slate-600">{{ $o->pembuat?->username ?? '-' }}</span>
                </td>
                <td class="px-6 py-5 text-center">
                    @php
                        $variantMap = ['Draft' => 'info', 'Diajukan' => 'warning', 'Disetujui' => 'success', 'Ditolak' => 'danger'];
                    @endphp
                    <x-badge :status="$o->status" :variant="$variantMap[$o->status] ?? 'info'" />
                </td>
                <td class="px-8 py-5 text-right">
                    <div class="flex items-center justify-end gap-2">
                        @if($o->status === 'Draft')
                            <x-button size="sm" variant="secondary" :href="route('stock-opname.edit', $o->id)">
                                <i class="fas fa-edit"></i> Lanjutkan
                            </x-button>
                            <form method="POST" action="{{ route('stock-opname.destroy', $o->id) }}" data-konfirmasi="Hapus Stok Opname {{ $o->nomor }}?">
                                @csrf @method('DELETE')
                                <x-button size="sm" variant="danger" type="submit" aria-label="Hapus" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </x-button>
                            </form>
                        @else
                            <x-button size="sm" variant="outline" :href="route('stock-opname.edit', $o->id)">
                                <i class="fas fa-eye"></i> Lihat
                            </x-button>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5">
                    <x-empty-state title="Belum ada Stok Opname" />
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$daftar" />

</div>
@endsection
