@extends('layouts.app')

@section('content')
@php $bisaUbah = \App\Support\Izin::boleh(auth()->user(), [23], 'edit'); @endphp
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="QC & Reparasi">
        <x-slot:subtitle>
            <i class="fas fa-clipboard-check text-amber-400"></i>
            Pemeriksaan hasil cetak per item order - item yang ditolak otomatis membuka Rework
        </x-slot:subtitle>
        <x-slot:actions>
            @if($bisaUbah)<x-button :href="route('qc.create')" variant="primary"><i class="fas fa-plus"></i> QC Baru</x-button>@endif
        </x-slot:actions>
    </x-page-header>

    <h2 class="text-xs font-medium text-slate-500 uppercase tracking-widest mb-3">Rework</h2>
    <x-table class="mb-10">
        <thead>
            <tr>
                <th class="px-8 py-4 text-left">Rework</th>
                <th class="px-6 py-4 text-left">Nota / Item</th>
                <th class="px-6 py-4 text-right">Qty</th>
                @bolehHarga<th class="px-6 py-4 text-right">Biaya Material</th>@endbolehHarga
                <th class="px-6 py-4 text-center">Status</th>
                <th class="px-8 py-4 text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($rework as $r)
            <tr>
                <td class="px-8 py-4 text-[13px] font-medium text-slate-700">{{ $r->nomor }}</td>
                <td class="px-6 py-4 text-xs text-slate-500">{{ $r->nomor_nota }} - {{ $r->nama_file }}</td>
                <td class="px-6 py-4 text-right text-sm font-medium">{{ angka($r->qty_rework, 4) }}</td>
                @bolehHarga<td class="px-6 py-4 text-right text-sm">{{ $r->status === 'Selesai' ? 'Rp ' . number_format($r->biaya_material, 0, ',', '.') : '-' }}</td>@endbolehHarga
                <td class="px-6 py-4 text-center"><x-badge :status="$r->status" :variant="match ($r->status) { 'Selesai' => 'success', 'Ditutup' => 'info', default => 'warning' }" /></td>
                <td class="px-8 py-4 text-right">
                    @if($r->status === 'Dibuka' && $bisaUbah)
                    <div class="flex items-center justify-end gap-2">
                    <form method="POST" action="{{ route('qc.rework.proses', $r->id) }}" data-konfirmasi="Proses rework? Material tambahan sesuai BOM akan dikeluarkan dari stok dan dijurnal sebagai beban. Stok boleh menjadi minus bila belum direstock.">
                        @csrf
                        <x-button type="submit" size="sm" variant="success"><i class="fas fa-tools"></i> Proses Rework</x-button>
                    </form>
                    <form method="POST" action="{{ route('qc.rework.tutup', $r->id) }}" class="form-tutup-rework">
                        @csrf
                        <input type="hidden" name="alasan">
                        <x-button type="submit" size="sm" variant="secondary">Tutup</x-button>
                    </form>
                    </div>
                    @elseif($r->status === 'Ditutup' && $r->catatan)
                    <span class="text-[11px] text-slate-400">{{ \Illuminate\Support\Str::after($r->catatan, 'Ditutup tanpa cetak ulang: ') }}</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="6"><x-empty-state title="Belum ada rework" /></td></tr>
            @endforelse
        </tbody>
    </x-table>
    <x-table.pagination :paginator="$rework" />

    <h2 class="text-xs font-medium text-slate-500 uppercase tracking-widest mt-10 mb-3">Riwayat QC</h2>
    <x-table>
        <thead>
            <tr>
                <th class="px-8 py-4 text-left">QC</th>
                <th class="px-6 py-4 text-left">Nota / Item</th>
                <th class="px-6 py-4 text-right">Diperiksa</th>
                <th class="px-6 py-4 text-right">Lolos</th>
                <th class="px-6 py-4 text-right">Reject</th>
                <th class="px-6 py-4 text-center">Hasil</th>
                <th class="px-8 py-4 text-left">Poin Bermasalah</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($inspeksi as $q)
            @php
                $cek = collect(json_decode($q->checklist ?? '[]', true))->filter()->keys()->map(fn ($k) => \App\Services\QcService::CHECKLIST[$k] ?? $k)->implode(', ');
            @endphp
            <tr>
                <td class="px-8 py-4"><span class="text-[13px] font-medium text-slate-700">{{ $q->nomor }}</span><div class="text-[11px] text-slate-400">{{ \Carbon\Carbon::parse($q->tanggal)->translatedFormat('d M Y') }} - {{ $q->username }}</div></td>
                <td class="px-6 py-4 text-xs text-slate-500">{{ $q->nomor_nota }} - {{ $q->nama_file }}</td>
                <td class="px-6 py-4 text-right text-sm">{{ angka($q->qty_diperiksa, 4) }}</td>
                <td class="px-6 py-4 text-right text-sm text-emerald-600 font-medium">{{ $q->qty_lolos }}</td>
                <td class="px-6 py-4 text-right text-sm text-rose-500 font-medium">{{ angka($q->qty_reject, 4) }}</td>
                <td class="px-6 py-4 text-center"><x-badge :status="$q->hasil" :variant="$q->hasil === 'Lulus' ? 'success' : 'danger'" /></td>
                <td class="px-8 py-4 text-xs text-slate-500">{{ $cek ?: '-' }}{{ $q->catatan ? ' | ' . $q->catatan : '' }}</td>
            </tr>
            @empty
            <tr><td colspan="7"><x-empty-state title="Belum ada QC" /></td></tr>
            @endforelse
        </tbody>
    </x-table>
    <x-table.pagination :paginator="$inspeksi" />
</div>
@endsection

@push('scripts')
<script>
    // Menutup rework tanpa cetak ulang wajib beralasan (tercatat di rework dan log aktivitas).
    document.querySelectorAll('.form-tutup-rework').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.siap) return;
            e.preventDefault();
            Swal.fire({
                title: 'Tutup rework tanpa cetak ulang?',
                text: 'Tidak ada material yang dikeluarkan dan tidak ada biaya yang dijurnal.',
                input: 'textarea',
                inputPlaceholder: 'Alasan (wajib), mis. pelanggan menerima hasil apa adanya',
                inputValidator: (v) => !v ? 'Alasan wajib diisi' : null,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Tutup Rework',
                cancelButtonText: 'Batal',
                customClass: { popup: 'rounded-2xl' }
            }).then(function (hasil) {
                if (!hasil.isConfirmed) return;
                form.querySelector('[name=alasan]').value = hasil.value;
                form.dataset.siap = '1';
                form.requestSubmit ? form.requestSubmit() : form.submit();
            });
        });
    });
</script>
@endpush
