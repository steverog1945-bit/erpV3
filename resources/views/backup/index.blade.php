@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Cadangan & Pemulihan">
        <x-slot:subtitle>
            <i class="fas fa-database text-amber-400"></i>
            Status backup database - daily/weekly/monthly otomatis lewat scheduler, atau jalankan manual kapan saja
        </x-slot:subtitle>
    </x-page-header>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $tipe => $label)
        <x-card>
            <div class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2">{{ $label }}</div>
            @if($terakhirPerTipe[$tipe])
                @php $b = $terakhirPerTipe[$tipe]; @endphp
                <x-badge :status="$b->status" :variant="$b->status === 'Sukses' ? 'success' : 'danger'" />
                <div class="text-sm font-medium text-slate-700 mt-2">{{ $b->started_at?->translatedFormat('d M Y H:i') }}</div>
                <div class="text-xs text-slate-400 mt-1">
                    {{ $b->ukuran_bytes ? angka($b->ukuran_bytes / 1024, 1) . ' KB' : '-' }}
                </div>
            @else
                <div class="text-sm text-slate-400 italic">Belum pernah ada backup {{ $label }}</div>
            @endif
        </x-card>
        @endforeach
    </div>

    <x-card class="mb-8">
        <form method="POST" action="{{ route('backup.jalankan') }}" data-konfirmasi="Jalankan backup manual sekarang?">
            @csrf
            <x-button type="submit" variant="primary">
                <i class="fas fa-play"></i> Jalankan Backup Manual Sekarang
            </x-button>
        </form>
    </x-card>

    <x-table>
        <thead>
            <tr>
                <th class="px-8 py-5 text-left">Tipe</th>
                <th class="px-6 py-5 text-center">Status</th>
                <th class="px-6 py-5 text-center" title="Salinan di luar server ini - lihat Pengaturan > BACKUP_SECONDARY_DISK">Lokasi Kedua</th>
                <th class="px-6 py-5 text-left">Ukuran</th>
                <th class="px-6 py-5 text-left">Waktu Mulai</th>
                <th class="px-6 py-5 text-left">Waktu Selesai</th>
                <th class="px-8 py-5 text-left">Keterangan</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($riwayat as $r)
            <tr class="hover:bg-slate-50/30 transition-all">
                <td class="px-8 py-5">
                    <span class="text-[13px] font-medium text-slate-700 capitalize">{{ $r->tipe }}</span>
                </td>
                <td class="px-6 py-5 text-center">
                    <x-badge :status="$r->status" :variant="$r->status === 'Sukses' ? 'success' : 'danger'" />
                </td>
                <td class="px-6 py-5 text-center">
                    @if($r->offsite_status === null)
                        <span class="text-[10px] text-slate-500 italic" title="BACKUP_SECONDARY_DISK belum diatur di .env">Tidak diatur</span>
                    @else
                        <x-badge :status="$r->offsite_status" :variant="$r->offsite_status === 'Sukses' ? 'success' : 'danger'" />
                    @endif
                </td>
                <td class="px-6 py-5">
                    <span class="text-xs text-slate-500">{{ $r->ukuran_bytes ? angka($r->ukuran_bytes / 1024, 1) . ' KB' : '-' }}</span>
                </td>
                <td class="px-6 py-5">
                    <span class="text-xs text-slate-500">{{ $r->started_at?->translatedFormat('d M Y H:i:s') }}</span>
                </td>
                <td class="px-6 py-5">
                    <span class="text-xs text-slate-500">{{ $r->finished_at?->translatedFormat('d M Y H:i:s') ?? '-' }}</span>
                </td>
                <td class="px-8 py-5 max-w-sm">
                    @if($r->status === 'Gagal')
                        <span class="text-xs text-red-500">{{ $r->pesan_error }}</span>
                    @else
                        <span class="text-[11px] text-slate-400 truncate block font-medium tabular-nums">{{ $r->file_path }}</span>
                    @endif
                    @if($r->offsite_status === 'Gagal')
                        <span class="text-xs text-red-500 block mt-1"><i class="fas fa-triangle-exclamation"></i> Salinan kedua gagal: {{ $r->offsite_error }}</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7">
                    <x-empty-state title="Belum ada riwayat backup" />
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$riwayat" />

</div>
@endsection
