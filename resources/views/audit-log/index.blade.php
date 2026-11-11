@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Log Aktivitas">
        <x-slot:subtitle>
            Riwayat perubahan data - lihat catatan di App\Services\AuditLogger: baru mencakup titik yang sudah
            diaudit manual (posting akuntansi, deposit konsumen) & model Eloquent yang pakai trait Auditable
            (User, KasKecil, PembelianBahan, JerseyOrder, JerseyOrderPayment). Belum mencakup semua controller.
        </x-slot:subtitle>
    </x-page-header>

    <x-card class="mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
            <x-form.group label="Jenis Data">
                <x-form.select name="auditable_type">
                    <option value="">Semua</option>
                    @foreach($daftarTipe as $tipe)
                        <option value="{{ $tipe }}" {{ request('auditable_type') === $tipe ? 'selected' : '' }}>{{ class_basename($tipe) }}</option>
                    @endforeach
                </x-form.select>
            </x-form.group>
            <x-form.group label="Aksi">
                <x-form.select name="event">
                    <option value="">Semua</option>
                    @foreach(['created' => 'Dibuat', 'updated' => 'Diubah', 'deleted' => 'Dihapus', 'login' => 'Masuk', 'login_gagal' => 'Gagal masuk', 'logout' => 'Keluar', 'harga_manual' => 'Harga diubah'] as $val => $label)
                        <option value="{{ $val }}" {{ request('event') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </x-form.select>
            </x-form.group>
            <x-form.group label="Dari Tanggal">
                <x-form.input type="date" name="awal" value="{{ request('awal') }}" />
            </x-form.group>
            <x-form.group label="Sampai Tanggal">
                <x-form.input type="date" name="akhir" value="{{ request('akhir') }}" />
            </x-form.group>
            <label class="flex items-center gap-2 text-sm text-slate-600" title="Log yang lebih tua dari masa simpan harian dipindahkan ke arsip, tidak dihapus">
                <input type="checkbox" name="arsip" value="1" {{ ($arsip ?? false) ? 'checked' : '' }}> Tampilkan arsip
            </label>
            <x-button type="submit"><i class="fas fa-filter"></i> Filter</x-button>
        </form>
    </x-card>

    <x-table>
        <thead>
            <tr>
                <th class="px-4 py-5 text-center w-10">#</th>
                <th class="px-4 py-5 text-left">Waktu</th>
                <th class="px-4 py-5 text-left">User</th>
                <th class="px-4 py-5 text-left">Aksi</th>
                <th class="px-4 py-5 text-left">Data</th>
                <th class="px-6 py-5 text-left">Keterangan</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($logs as $i => $log)
            <tr class="hover:bg-slate-50/50 transition-all align-top">
                <td class="px-4 py-5 text-center text-[11px] text-slate-400">{{ $logs->firstItem() + $i }}</td>
                <td class="px-4 py-5 text-[12px] text-slate-600 whitespace-nowrap">{{ $log->created_at?->translatedFormat('d M Y H:i:s') }}</td>
                <td class="px-4 py-5 text-[12px] font-medium text-slate-700">{{ $log->user_label ?? $log->user?->username ?? '-' }}</td>
                <td class="px-4 py-5">
                    @php
                        $warna = ['created' => 'emerald', 'updated' => 'amber', 'deleted' => 'rose', 'login' => 'emerald', 'login_gagal' => 'rose', 'harga_manual' => 'amber'][$log->event] ?? 'slate';
                        $namaAksi = ['created' => 'Dibuat', 'updated' => 'Diubah', 'deleted' => 'Dihapus', 'login' => 'Masuk', 'login_gagal' => 'Gagal masuk', 'logout' => 'Keluar', 'harga_manual' => 'Harga diubah'][$log->event] ?? $log->event;
                    @endphp
                    <span class="text-[11px] font-medium uppercase text-{{ $warna }}-600 bg-{{ $warna }}-50 rounded px-2 py-1">{{ $namaAksi }}</span>
                </td>
                <td class="px-4 py-5 text-[12px] text-slate-600">
                    {{ class_basename($log->auditable_type) }}
                    @if($log->auditable_id) <span class="text-slate-400">#{{ $log->auditable_id }}</span> @endif
                </td>
                <td class="px-6 py-5 text-[12px] text-slate-600">
                    {{ $log->keterangan }}
                    @if($log->old_values || $log->new_values)
                        <details class="mt-1">
                            <summary class="text-[10px] text-indigo-500 cursor-pointer uppercase tracking-wide font-medium">Lihat detail</summary>
                            <div class="mt-2 grid grid-cols-2 gap-2 text-[10px] font-medium tabular-nums">
                                <div>
                                    <p class="text-slate-400 font-medium mb-1">SEBELUM</p>
                                    <pre class="bg-slate-50 rounded p-2 overflow-x-auto">{{ $log->old_values ? json_encode($log->old_values, JSON_PRETTY_PRINT) : '-' }}</pre>
                                </div>
                                <div>
                                    <p class="text-slate-400 font-medium mb-1">SESUDAH</p>
                                    <pre class="bg-slate-50 rounded p-2 overflow-x-auto">{{ $log->new_values ? json_encode($log->new_values, JSON_PRETTY_PRINT) : '-' }}</pre>
                                </div>
                            </div>
                        </details>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-6 py-10 text-center text-slate-400 italic">Belum ada aktivitas tercatat.</td>
            </tr>
            @endforelse
        </tbody>
        <x-table.pagination :paginator="$logs" />
    </x-table>
</div>
@endsection
