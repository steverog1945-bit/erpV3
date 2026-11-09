@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Periode Akuntansi">
        <x-slot:subtitle>
            <i class="fas fa-lock text-amber-400"></i>
            Tutup periode supaya jurnal bertanggal periode itu tidak bisa diposting/diubah lagi
        </x-slot:subtitle>
    </x-page-header>

    <x-table class="mb-8">
        <thead>
            <tr>
                <th class="px-8 py-5 text-left">Periode</th>
                <th class="px-6 py-5 text-center">Status</th>
                <th class="px-6 py-5 text-left">Ditutup Pada</th>
                <th class="px-6 py-5 text-left">Catatan</th>
                <th class="px-8 py-5 text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @foreach($daftarPeriode as $p)
            <tr class="hover:bg-slate-50/30 transition-all">
                <td class="px-8 py-5">
                    <span class="text-[13px] font-medium text-slate-700">{{ $p['nama'] }}</span>
                </td>
                <td class="px-6 py-5 text-center">
                    <x-badge :status="$p['status']" :variant="$p['status'] === 'Closed' ? 'danger' : 'success'" />
                </td>
                <td class="px-6 py-5">
                    <span class="text-xs text-slate-500">{{ $p['closed_at']?->translatedFormat('d M Y H:i') ?? '-' }}</span>
                </td>
                <td class="px-6 py-5 max-w-xs">
                    <span class="text-xs text-slate-500">{{ $p['catatan'] ?? '-' }}</span>
                </td>
                <td class="px-8 py-5 text-right">
                    @if($p['status'] === 'Closed')
                        <form method="POST" action="{{ route('accounting-period.buka') }}" data-konfirmasi="Buka kembali periode {{ $p['nama'] }}? Jurnal baru akan bisa diposting lagi ke periode ini.">
                            @csrf @method('PATCH')
                            <input type="hidden" name="tahun" value="{{ $p['tahun'] }}">
                            <input type="hidden" name="bulan" value="{{ $p['bulan'] }}">
                            <x-button size="sm" variant="secondary" type="submit">
                                <i class="fas fa-lock-open"></i> Buka Kembali
                            </x-button>
                        </form>
                    @else
                        <x-button size="sm" variant="danger" onclick="openTutup({{ $p['tahun'] }}, {{ $p['bulan'] }}, {{ json_encode($p['nama']) }})">
                            <i class="fas fa-lock"></i> Tutup Periode
                        </x-button>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </x-table>

    {{-- MODAL TUTUP --}}
    <x-modal id="tutup-periode" title="Tutup Periode Akuntansi" maxWidth="sm">
        <form id="form-tutup-periode" method="POST" action="{{ route('accounting-period.tutup') }}">
            @csrf @method('PATCH')
            <input type="hidden" name="tahun" id="tutup-tahun">
            <input type="hidden" name="bulan" id="tutup-bulan">
            <p class="text-sm text-slate-600 mb-4">
                Setelah ditutup, periode <b id="tutup-nama-periode"></b> tidak bisa lagi menerima jurnal baru
                (penjualan, pembayaran, pembelian, dsb bertanggal periode ini akan gagal diposting) sampai dibuka kembali.
            </p>
            <x-form.group label="Catatan (opsional)" dalam>
                <textarea name="catatan" rows="3" placeholder="Mis. Laporan keuangan bulan ini sudah difinalkan" class="w-full rounded-xl border-slate-200 text-sm p-3"></textarea>
            </x-form.group>
            <div class="pt-4 flex gap-3">
                <x-button type="button" variant="secondary" onclick="closeModal('tutup-periode')" class="flex-1">Batal</x-button>
                <x-button type="submit" variant="danger" class="flex-1">Tutup Periode</x-button>
            </div>
        </form>
    </x-modal>

</div>

@push('scripts')
<script>
    function openTutup(tahun, bulan, nama) {
        document.getElementById('tutup-tahun').value = tahun;
        document.getElementById('tutup-bulan').value = bulan;
        document.getElementById('tutup-nama-periode').innerText = nama;
        openModal('tutup-periode');
    }
</script>
@endpush
@endsection
