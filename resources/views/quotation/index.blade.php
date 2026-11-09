@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Penawaran">
        <x-slot:subtitle>
            <i class="fas fa-file-signature text-amber-400"></i>
            Penawaran harga ke pelanggan - cetak, kirim lewat WhatsApp, lalu jadikan order
        </x-slot:subtitle>
        <x-slot:actions>
            <x-button :href="route('quotation.create')" variant="primary"><i class="fas fa-plus"></i> Penawaran Baru</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-8">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <div class="w-full md:w-72">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari nomor / pelanggan</label>
                <x-form.input type="text" name="q" value="{{ request('q') }}" placeholder="QT-... atau nama" />
            </div>
            <div class="w-full md:w-56">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Status</label>
                <x-form.select name="status" onchange="this.form.submit()">
                    <option value="">Semua</option>
                    @foreach(['Draft', 'Terkirim', 'Disetujui', 'Ditolak', 'Menjadi Order', 'Kedaluwarsa'] as $s)
                        <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </x-form.select>
            </div>
            <x-button type="submit" variant="secondary"><i class="fas fa-search"></i> Cari</x-button>
        </form>
    </x-card>

    <x-table>
        <thead>
            <tr>
                <th class="px-8 py-4 text-left">Nomor</th>
                <th class="px-6 py-4 text-left">Pelanggan</th>
                <th class="px-6 py-4 text-left">Berlaku s/d</th>
                <th class="px-6 py-4 text-right">Total</th>
                <th class="px-6 py-4 text-center">Status</th>
                <th class="px-8 py-4 text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @php $variantMap = ['Draft' => 'info', 'Terkirim' => 'warning', 'Disetujui' => 'success', 'Ditolak' => 'danger', 'Menjadi Order' => 'success', 'Kedaluwarsa' => 'danger']; @endphp
            @forelse($daftar as $q)
            <tr class="hover:bg-slate-50/30">
                <td class="px-8 py-4"><span class="text-[13px] font-medium text-slate-700">{{ $q->nomor }}</span><div class="text-[11px] text-slate-400">{{ $q->tanggal->translatedFormat('d M Y') }}</div></td>
                <td class="px-6 py-4 text-sm font-medium text-slate-600">{{ $q->nama_pelanggan }}</td>
                <td class="px-6 py-4 text-xs text-slate-500">{{ $q->berlaku_sampai->translatedFormat('d M Y') }}</td>
                <td class="px-6 py-4 text-right text-sm font-medium text-slate-700">Rp {{ number_format($q->total, 0, ',', '.') }}</td>
                <td class="px-6 py-4 text-center"><x-badge :status="$q->status_efektif" :variant="$variantMap[$q->status_efektif] ?? 'info'" /></td>
                <td class="px-8 py-4 text-right">
                    <x-button size="sm" variant="{{ $q->status === 'Draft' ? 'secondary' : 'outline' }}" :href="route('quotation.edit', $q->id)">
                        <i class="fas fa-{{ $q->status === 'Draft' ? 'edit' : 'eye' }}"></i> {{ $q->status === 'Draft' ? 'Lanjutkan' : 'Lihat' }}
                    </x-button>
                </td>
            </tr>
            @empty
            <tr><td colspan="6"><x-empty-state title="Belum ada penawaran" /></td></tr>
            @endforelse
        </tbody>
    </x-table>
    <x-table.pagination :paginator="$daftar" />
</div>
@endsection
