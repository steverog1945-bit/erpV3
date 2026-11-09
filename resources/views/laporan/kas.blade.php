@extends('layouts.app')

@section('content')
<style>
    @media print {
        aside, .no-print { display: none !important; }
        main { margin-left: 0 !important; padding: 28px 32px !important; }
        body { background: #fff !important; }
        .sesi-block { break-inside: avoid; }
    }
</style>
@include('partials.print-report-header', ['docTitle' => 'Laporan Kas'])

<div class="w-full px-4 sm:px-6 lg:px-8 py-10 print:pt-0 max-w-[1600px] mx-auto">

    <div class="print:hidden">
        <x-page-header title="Laporan Kas">
            <x-slot:subtitle>
                Laporan <span class="mx-1 text-slate-500">/</span> Rekap Sesi Kas Harian
            </x-slot:subtitle>
            <x-slot:actions>
                <x-button type="button" onclick="window.print()" variant="secondary" class="no-print">
                    <i class="fas fa-print"></i>
                    Cetak
                </x-button>
            </x-slot:actions>
        </x-page-header>
    </div>

    {{-- FILTER --}}
    <x-card class="mb-6 no-print">
        <form method="GET" action="{{ route('laporan.kas') }}" class="flex flex-wrap items-end gap-4">
            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Dari Tanggal</label>
                <x-form.input type="date" name="tgl_dari" value="{{ $tglDari }}" />
            </div>
            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Sampai Tanggal</label>
                <x-form.input type="date" name="tgl_sampai" value="{{ $tglSampai }}" />
            </div>
            <div class="w-full md:w-48">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Status Sesi</label>
                <x-form.select name="status_lock">
                    <option value="">-- Semua --</option>
                    <option value="open" {{ request('status_lock') === 'open' ? 'selected' : '' }}>Masih Buka</option>
                    <option value="closed" {{ request('status_lock') === 'closed' ? 'selected' : '' }}>Sudah Tutup</option>
                </x-form.select>
            </div>

            <x-button type="submit">
                <i class="fas fa-filter"></i>
                Filter
            </x-button>

            @if(request()->anyFilled(['tgl_dari', 'tgl_sampai', 'status_lock']))
                <a href="{{ route('laporan.kas') }}" class="text-[11px] font-medium text-rose-500 uppercase tracking-widest hover:underline ml-2">
                    Reset
                </a>
            @endif
        </form>
    </x-card>

    {{-- RESUME - kartu berwarna di layar (tidak diubah), tabel standar
         polos khusus cetak. --}}
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-6 mb-6 print:hidden">
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Jumlah Sesi</p>
            <h3 class="text-2xl font-medium text-slate-900 mt-1">{{ number_format($jumlahSesi, 0, ',', '.') }}</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Total Tunai Masuk</p>
            <h3 class="text-2xl font-medium text-emerald-600 mt-1">Rp {{ number_format($totalTunaiMasuk, 0, ',', '.') }}</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Total Transfer Masuk</p>
            <h3 class="text-2xl font-medium text-indigo-600 mt-1">Rp {{ number_format($totalTransferMasuk, 0, ',', '.') }}</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Total Debit Masuk</p>
            <h3 class="text-2xl font-medium text-sky-600 mt-1">Rp {{ number_format($totalDebitMasuk, 0, ',', '.') }}</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Total Selisih</p>
            <h3 class="text-2xl font-medium {{ $totalSelisih < 0 ? 'text-rose-500' : 'text-slate-900' }} mt-1">Rp {{ number_format($totalSelisih, 0, ',', '.') }}</h3>
        </div>
    </div>
    <div class="hidden print:block">
        <x-laporan.resume-table title="Resume" :rows="[
            'Jumlah Sesi' => number_format($jumlahSesi, 0, ',', '.'),
            'Total Tunai Masuk' => 'Rp ' . number_format($totalTunaiMasuk, 0, ',', '.'),
            'Total Transfer Masuk' => 'Rp ' . number_format($totalTransferMasuk, 0, ',', '.'),
            'Total Debit Masuk' => 'Rp ' . number_format($totalDebitMasuk, 0, ',', '.'),
            'Total Selisih' => 'Rp ' . number_format($totalSelisih, 0, ',', '.'),
        ]" />
    </div>

    <div class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-3 px-1 print:hidden">Detail Sesi Kas</div>

    {{-- Versi cetak: satu tabel polos per sesi (bukan kartu rounded/
         shadow/badge berwarna), gaya visual sama dengan Laporan Order. --}}
    <div class="hidden print:block">
        <p class="text-[10px] font-medium text-slate-700 uppercase tracking-wide mb-2">Detail Sesi Kas</p>
        @forelse($data as $k)
        @php $manualPrint = $manualPerSesi->get($k->id_kas, collect()); @endphp
        <div class="mb-6 border border-slate-200 overflow-hidden break-inside-avoid">
            <div class="px-3 py-2 bg-slate-50 border-b border-slate-200 text-[10.5px] text-slate-600 leading-relaxed">
                <span class="font-medium text-slate-800">{{ \Carbon\Carbon::parse($k->tgl_kas)->format('d/m/Y') }}</span>
                &middot; Kasir: {{ $k->nama_kasir ?? '-' }}
                &middot; Saldo Awal: {{ number_format($k->saldo_awal, 0, ',', '.') }}
                &middot; Status: {{ $k->status_lock === 'open' ? 'Masih Buka' : 'Tutup' }}
            </div>
            <table class="w-full text-[11px] border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-left w-6">#</th>
                        <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-left">Waktu</th>
                        <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-left">Keterangan</th>
                        <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-left">Oleh</th>
                        <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-left">Status</th>
                        <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-right">Nominal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($manualPrint as $i => $m)
                    <tr>
                        <td class="px-3 py-1.5 text-slate-400">{{ $i + 1 }}</td>
                        <td class="px-3 py-1.5 text-slate-600">{{ \Carbon\Carbon::parse($m->waktu)->format('H:i') }}</td>
                        <td class="px-3 py-1.5 text-slate-700">{{ $m->keterangan }}</td>
                        <td class="px-3 py-1.5 text-slate-600">{{ $m->nama_user ?? '-' }}</td>
                        <td class="px-3 py-1.5 text-slate-600">{{ $m->status }}</td>
                        <td class="px-3 py-1.5 text-right font-medium text-slate-800">{{ $m->status === 'Masuk' ? '+' : '-' }} {{ number_format($m->nominal, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td class="px-3 py-1.5 text-slate-400 italic" colspan="6">Tidak ada transaksi manual pada sesi ini.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-3 py-2 bg-slate-50 border-t border-slate-200 flex justify-end">
                <table class="text-[11px]">
                    <tr><td class="pr-4 text-slate-600">Tunai Masuk</td><td class="text-right font-medium text-slate-800">{{ number_format($k->total_tunai_masuk, 0, ',', '.') }}</td></tr>
                    <tr><td class="pr-4 text-slate-600">Transfer Masuk</td><td class="text-right font-medium text-slate-800">{{ number_format($k->total_transfer_masuk, 0, ',', '.') }}</td></tr>
                    <tr><td class="pr-4 text-slate-600">Debit Masuk</td><td class="text-right font-medium text-slate-800">{{ number_format($k->total_debit_masuk ?? 0, 0, ',', '.') }}</td></tr>
                    <tr><td class="pr-4 text-slate-600">Tunai Keluar</td><td class="text-right font-medium text-slate-800">{{ number_format($k->total_tunai_keluar, 0, ',', '.') }}</td></tr>
                    <tr><td class="pr-4 text-slate-600">Saldo Fisik</td><td class="text-right font-medium text-slate-800">{{ $k->saldo_fisik !== null ? number_format($k->saldo_fisik, 0, ',', '.') : '-' }}</td></tr>
                    <tr><td class="pr-4 font-medium text-slate-700">Selisih</td><td class="text-right font-medium text-slate-900">{{ $k->selisih !== null ? number_format($k->selisih, 0, ',', '.') : '-' }}</td></tr>
                </table>
            </div>
        </div>
        @empty
        <p class="text-[11px] text-slate-400 italic">Tidak ada data.</p>
        @endforelse
    </div>

    <div class="print:hidden">
    @forelse($data as $k)
    @php $manual = $manualPerSesi->get($k->id_kas, collect()); @endphp
    <div class="sesi-block bg-white rounded-2xl border border-slate-100 shadow-sm mb-5 overflow-hidden">
        <div class="px-6 py-4 bg-slate-50/80 border-b border-slate-100 flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[14px] font-medium text-slate-800">{{ \Carbon\Carbon::parse($k->tgl_kas)->translatedFormat('d M Y') }}</span>
                    @if($k->status_lock === 'open')
                        <x-badge variant="warning">Masih Buka</x-badge>
                    @else
                        <x-badge variant="success">Tutup</x-badge>
                    @endif
                </div>
                <p class="text-[12px] text-slate-500 mt-1.5">
                    Kasir: <span class="font-medium text-slate-700">{{ $k->nama_kasir ?? '-' }}</span>
                    &middot; Saldo Awal: Rp {{ number_format($k->saldo_awal, 0, ',', '.') }}
                    @if($k->keterangan_pembukaan)
                        &middot; <span class="text-amber-700">saldo awal berbeda dari isi laci sebelumnya</span>
                    @endif
                </p>
                @if($k->keterangan_pembukaan)
                <p class="text-[11px] text-amber-700 mt-1 max-w-xl">
                    Alasan pembuka: {{ $k->keterangan_pembukaan }}
                </p>
                @endif
            </div>

            {{-- Print per sesi. Hanya sesi yang sudah TUTUP yang bisa
                 dicetak: angka isi laci dan selisih baru ada setelah
                 closing, dan rekap yang dicetak sebelum angka final
                 bukan bukti rekonsiliasi.
                 Thermal untuk strip yang ditempel di laci, A4 untuk arsip. --}}
            @if($k->status_lock === 'closed')
            <div class="flex items-center gap-2 no-print">
                <a href="{{ route('kasir.print-closing', ['id' => $k->id_kas, 'format' => 'thermal']) }}"
                   target="_blank" rel="noopener" title="Cetak thermal 58mm"
                   class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 px-2.5 py-1.5 text-[12px] font-medium text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900">
                    <i class="fas fa-receipt text-[10px]"></i> Thermal
                </a>
                <a href="{{ route('kasir.print-closing', ['id' => $k->id_kas, 'format' => 'a4']) }}"
                   target="_blank" rel="noopener" title="Cetak A4 lengkap"
                   class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 px-2.5 py-1.5 text-[12px] font-medium text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900">
                    <i class="fas fa-file-lines text-[10px]"></i> A4 Lengkap
                </a>
            </div>
            @endif
        </div>

        @if($manual->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="w-full text-xs rtable">
                <thead>
                    <tr class="text-[10px] text-slate-400 uppercase bg-slate-50/60">
                        <th class="p-3 text-left w-6">#</th>
                        <th class="p-3 text-left">Waktu</th>
                        <th class="p-3 text-left">Keterangan</th>
                        <th class="p-3 text-left">Oleh</th>
                        <th class="p-3 text-center">Status</th>
                        <th class="p-3 text-right">Nominal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($manual as $i => $m)
                    <tr>
                        <td data-label="#" class="p-3 text-slate-400">{{ $i + 1 }}</td>
                        <td data-label="Waktu" class="p-3 text-slate-500">{{ \Carbon\Carbon::parse($m->waktu)->format('H:i') }}</td>
                        <td data-label="Keterangan" class="p-3 font-medium text-slate-700">{{ $m->keterangan }}</td>
                        <td data-label="Oleh" class="p-3 text-slate-500">{{ $m->nama_user ?? '-' }}</td>
                        <td data-label="Status" class="p-3 text-center">
                            <x-badge :variant="$m->status === 'Masuk' ? 'success' : 'danger'">{{ $m->status }}</x-badge>
                        </td>
                        <td data-label="Nominal" class="p-3 text-right font-medium {{ $m->status === 'Masuk' ? 'text-emerald-600' : 'text-rose-500' }}">
                            {{ $m->status === 'Masuk' ? '+' : '-' }} Rp {{ number_format($m->nominal, 0, ',', '.') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <p class="text-[11px] text-slate-500 italic p-4">Tidak ada transaksi manual pada sesi ini.</p>
        @endif

        <div class="px-6 py-4 bg-slate-50/60 border-t border-slate-100 grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-2">
            <p class="text-[12px] text-slate-500 flex justify-between gap-3"><span>Tunai Masuk</span> <span class="font-medium text-emerald-600">Rp {{ number_format($k->total_tunai_masuk, 0, ',', '.') }}</span></p>
            <p class="text-[12px] text-slate-500 flex justify-between gap-3"><span>Transfer Masuk</span> <span class="font-medium text-indigo-600">Rp {{ number_format($k->total_transfer_masuk, 0, ',', '.') }}</span></p>
            <p class="text-[12px] text-slate-500 flex justify-between gap-3"><span>Debit Masuk</span> <span class="font-medium text-sky-600">Rp {{ number_format($k->total_debit_masuk ?? 0, 0, ',', '.') }}</span></p>
            <p class="text-[12px] text-slate-500 flex justify-between gap-3"><span>Tunai Keluar</span> <span class="font-medium text-rose-500">Rp {{ number_format($k->total_tunai_keluar, 0, ',', '.') }}</span></p>
            <p class="text-[12px] text-slate-500 flex justify-between gap-3"><span>Saldo Fisik</span> <span class="font-medium text-slate-700">{{ $k->saldo_fisik !== null ? 'Rp ' . number_format($k->saldo_fisik, 0, ',', '.') : '-' }}</span></p>
            <p class="text-[13px] font-medium flex justify-between gap-3 {{ $k->selisih === null ? 'text-slate-500' : ($k->selisih < 0 ? 'text-rose-500' : ($k->selisih > 0 ? 'text-amber-500' : 'text-emerald-600')) }}">
                <span>Selisih</span> <span>{{ $k->selisih !== null ? 'Rp ' . number_format($k->selisih, 0, ',', '.') : '-' }}</span>
            </p>
        </div>
    </div>
    @empty
    <x-card>
        <x-empty-state title="Tidak ada sesi kas" description="Sesuaikan filter tanggal untuk melihat rekap kas." />
    </x-card>
    @endforelse
    </div>

    <div class="no-print">
        <x-table.pagination :paginator="$data" />
    </div>

</div>
@endsection
