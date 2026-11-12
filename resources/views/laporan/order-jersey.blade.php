@extends('layouts.app')

@section('content')
<style>
    @media print {
        aside, .no-print { display: none !important; }
        main { margin-left: 0 !important; padding: 28px 32px !important; }
        body { background: #fff !important; }
        .order-block { break-inside: avoid; }
    }
</style>
@include('partials.print-report-header', ['docTitle' => 'Laporan Pesanan Jersey'])

<div class="w-full px-4 sm:px-6 lg:px-8 py-10 print:pt-0 max-w-[1600px] mx-auto">

    <div class="print:hidden">
        <x-page-header title="Laporan Pesanan Jersey">
            <x-slot:subtitle>
                Laporan <span class="mx-1 text-slate-500">/</span> Rekap Order &amp; Piutang Jersey - terpisah dari Laporan Order percetakan biasa
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
        <form method="GET" action="{{ route('laporan.order-jersey') }}" class="flex flex-wrap items-end gap-4">
            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Dari Tanggal</label>
                <x-form.input type="date" name="tgl_dari" value="{{ $tglDari }}" />
            </div>
            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Sampai Tanggal</label>
                <x-form.input type="date" name="tgl_sampai" value="{{ $tglSampai }}" />
            </div>
            <div class="w-full md:w-48">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Status Pembayaran</label>
                <x-form.select name="status_pembayaran">
                    <option value="">-- Semua --</option>
                    @foreach(['Lunas', 'DP', 'Belum Bayar'] as $s)
                        <option value="{{ $s }}" {{ request('status_pembayaran') === $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </x-form.select>
            </div>
            <div class="w-full md:w-48">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Desainer</label>
                <x-form.select name="jersey_designer_id">
                    <option value="">-- Semua --</option>
                    @foreach($designers as $d)
                        <option value="{{ $d->id }}" {{ (string) request('jersey_designer_id') === (string) $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                    @endforeach
                </x-form.select>
            </div>
            <div class="w-full md:w-64 lg:w-72">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Konsumen</label>
                <select name="id_konsumen" class="select2-konsumen-laporan w-full">
                    <option value="">-- Cari Nama / No. HP --</option>
                    @foreach($konsumenList as $k)
                        <option value="{{ $k->id_konsumen }}" {{ (string) request('id_konsumen') === (string) $k->id_konsumen ? 'selected' : '' }}>{{ $k->nama_konsumen }}{{ $k->telepon ? ' - ' . $k->telepon : '' }}</option>
                    @endforeach
                </select>
            </div>

            <x-button type="submit">
                <i class="fas fa-filter"></i>
                Filter
            </x-button>

            @if(request()->anyFilled(['tgl_dari', 'tgl_sampai', 'status_pembayaran', 'jersey_designer_id', 'id_konsumen']))
                <a href="{{ route('laporan.order-jersey') }}" class="text-[11px] font-medium text-rose-500 uppercase tracking-widest hover:underline ml-2">
                    Reset
                </a>
            @endif
        </form>
    </x-card>

    {{-- RESUME - kartu berwarna di layar (tidak diubah), tabel standar
         polos khusus cetak. --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 print:hidden">
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Jumlah Order</p>
            <h3 class="text-2xl font-medium text-slate-900 mt-1">{{ number_format($jumlahOrder, 0, ',', '.') }}</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Total Transaksi</p>
            <h3 class="text-2xl font-medium text-indigo-600 mt-1">Rp {{ number_format($totalTransaksi, 0, ',', '.') }}</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Total Piutang (sisa tagihan)</p>
            <h3 class="text-2xl font-medium text-rose-500 mt-1">Rp {{ number_format($totalPiutang, 0, ',', '.') }}</h3>
        </div>
    </div>
    <div class="hidden print:block">
        <x-laporan.resume-table title="Resume Transaksi" :rows="[
            'Jumlah Order' => number_format($jumlahOrder, 0, ',', '.') . ' Kali',
            'Total Transaksi' => 'Rp ' . number_format($totalTransaksi, 0, ',', '.'),
            'Total Piutang (sisa tagihan)' => 'Rp ' . number_format($totalPiutang, 0, ',', '.'),
        ]" />
    </div>

    {{-- BREAKDOWN PER KATEGORI &amp; DESAINER - kartu berwarna di layar
         (tidak diubah), tabel polos sederhana khusus cetak. --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6 break-inside-avoid print:hidden">
        @if($breakdownKategori->isNotEmpty())
        <x-card title="Rincian per Kategori Jersey">
            <x-table bare class="rtable">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Kategori</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($breakdownKategori as $b)
                    <tr>
                        <td data-label="Kategori" class="px-4 py-3 text-[12px] font-medium text-slate-700">{{ $b->kategori }}</td>
                        <td data-label="Qty" class="px-4 py-3 text-right text-[12px] text-slate-600">{{ number_format($b->total_qty, 0, ',', '.') }}</td>
                        <td data-label="Subtotal" class="px-4 py-3 text-right text-[12px] font-medium text-slate-600">Rp {{ number_format($b->total_subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </x-table>
        </x-card>
        @endif

        @if($breakdownDesainer->isNotEmpty())
        <x-card title="Rincian per Desainer">
            <x-table bare class="rtable">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Desainer</th>
                        <th class="px-4 py-3 text-right">Jml Order</th>
                        <th class="px-4 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($breakdownDesainer as $b)
                    <tr>
                        <td data-label="Desainer" class="px-4 py-3 text-[12px] font-medium text-slate-700">{{ $b->nama }}</td>
                        <td data-label="Jml Order" class="px-4 py-3 text-right text-[12px] text-slate-600">{{ number_format($b->jumlah_order, 0, ',', '.') }}</td>
                        <td data-label="Total" class="px-4 py-3 text-right text-[12px] font-medium text-slate-600">Rp {{ number_format($b->total, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </x-table>
        </x-card>
        @endif
    </div>

    <div class="hidden print:block">
        @if($breakdownKategori->isNotEmpty())
        <div class="break-inside-avoid">
            <x-laporan.print-table title="Rincian per Kategori Jersey" :headers="['Kategori', 'Qty', 'Subtotal']" :align="['left', 'right', 'right']">
                @foreach($breakdownKategori as $b)
                <tr>
                    <td class="px-3 py-1.5 font-medium text-slate-700">{{ $b->kategori }}</td>
                    <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($b->total_qty, 0, ',', '.') }}</td>
                    <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($b->total_subtotal, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </x-laporan.print-table>
        </div>
        @endif
        @if($breakdownDesainer->isNotEmpty())
        <div class="break-inside-avoid">
            <x-laporan.print-table title="Rincian per Desainer" :headers="['Desainer', 'Jml Order', 'Total']" :align="['left', 'right', 'right']">
                @foreach($breakdownDesainer as $b)
                <tr>
                    <td class="px-3 py-1.5 font-medium text-slate-700">{{ $b->nama }}</td>
                    <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($b->jumlah_order, 0, ',', '.') }}</td>
                    <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($b->total, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </x-laporan.print-table>
        </div>
        @endif
    </div>

    <div class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-3 px-1 print:hidden">Detail Order</div>

    {{-- Versi cetak: satu tabel polos per order (bukan kartu rounded/
         shadow/badge berwarna), gaya visual sama dengan Laporan Order. --}}
    <div class="hidden print:block">
        <p class="text-[10px] font-medium text-slate-700 uppercase tracking-wide mb-2">Detail Order</p>
        @forelse($data as $o)
        @php $lineOrderPrint = $linePerOrder->get($o->id, collect()); @endphp
        <div class="mb-6 border border-slate-200 overflow-hidden break-inside-avoid">
            <div class="px-3 py-2 bg-slate-50 border-b border-slate-200 text-[10.5px] text-slate-600 leading-relaxed">
                <span class="font-medium text-slate-800">{{ $o->order_number }}</span>
                @if($o->team_name) &middot; Tim {{ $o->team_name }} @endif
                &middot; Konsumen: {{ $o->konsumen->nama_konsumen ?? $o->customer_name ?? '-' }}
                &middot; Desainer: {{ $o->designer->name ?? $o->designer_name ?? '-' }}
                &middot; Tanggal: {{ \Carbon\Carbon::parse($o->order_date)->format('d/m/Y') }}
                @if($o->deadline) &middot; Deadline: {{ \Carbon\Carbon::parse($o->deadline)->format('d/m/Y') }} @endif
                &middot; Status: {{ $o->status_pembayaran }}
            </div>
            <table class="w-full text-[11px] border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-left w-6">#</th>
                        <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-left">Nama Barang</th>
                        <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-right">Qty</th>
                        <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-right">Harga Satuan</th>
                        <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-right">Tambahan</th>
                        <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($lineOrderPrint as $i => $line)
                    <tr>
                        <td class="px-3 py-1.5 text-slate-400">{{ $i + 1 }}</td>
                        <td class="px-3 py-1.5 text-slate-700">{{ $line->nama_barang }}</td>
                        <td class="px-3 py-1.5 text-right text-slate-600">{{ angka($line->qty, 4) }}</td>
                        <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($line->harga_satuan, 0, ',', '.') }}</td>
                        <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($line->size_surcharge_total + $line->sleeve_collar_surcharge_total, 0, ',', '.') }}</td>
                        <td class="px-3 py-1.5 text-right font-medium text-slate-800">{{ number_format($line->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td class="px-3 py-1.5 text-slate-400 italic" colspan="6">Tidak ada rincian barang untuk order ini.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-3 py-2 bg-slate-50 border-t border-slate-200 flex justify-end">
                @php
                    $subtotalBarang = $lineOrderPrint->sum('subtotal');
                    $finishingOrder = (float) ($finishingPerOrder->get($o->id, 0) ?? 0);
                    $diskonOrder = (float) ($o->discount ?? 0);
                    $tambahanOrder = (float) ($o->additional_charge ?? 0);
                @endphp
                <table class="text-[11px]">
                    <tr><td class="pr-4 text-slate-600">Subtotal Barang</td><td class="text-right font-medium text-slate-800">{{ number_format($subtotalBarang, 0, ',', '.') }}</td></tr>
                    @if($finishingOrder > 0)
                    <tr><td class="pr-4 text-slate-600">Finishing</td><td class="text-right font-medium text-slate-800">+{{ number_format($finishingOrder, 0, ',', '.') }}</td></tr>
                    @endif
                    @if($diskonOrder > 0)
                    <tr><td class="pr-4 text-slate-600">Diskon</td><td class="text-right font-medium text-emerald-600">-{{ number_format($diskonOrder, 0, ',', '.') }}</td></tr>
                    @endif
                    @if($tambahanOrder > 0)
                    <tr><td class="pr-4 text-slate-600">Biaya Tambahan</td><td class="text-right font-medium text-slate-800">+{{ number_format($tambahanOrder, 0, ',', '.') }}</td></tr>
                    @endif
                    <tr><td class="pr-4 font-medium text-slate-800">Grand Total</td><td class="text-right font-semibold text-slate-900">{{ number_format($o->total, 0, ',', '.') }}</td></tr>
                    <tr><td class="pr-4 text-slate-600">Sudah Dibayar</td><td class="text-right font-medium text-slate-800">{{ number_format($o->deposit, 0, ',', '.') }}</td></tr>
                    <tr><td class="pr-4 font-medium text-slate-700">{{ $o->sisa_tagihan > 0 ? 'Sisa Tagihan' : 'Lunas' }}</td><td class="text-right font-medium text-slate-900">{{ number_format($o->sisa_tagihan, 0, ',', '.') }}</td></tr>
                </table>
            </div>
        </div>
        @empty
        <p class="text-[11px] text-slate-400 italic">Tidak ada data.</p>
        @endforelse
    </div>

    <div class="print:hidden">
    @forelse($data as $o)
    @php $lineOrder = $linePerOrder->get($o->id, collect()); @endphp
    <div class="order-block bg-white rounded-2xl border border-slate-100 shadow-sm mb-5 overflow-hidden">
        <div class="px-6 py-4 bg-slate-50/80 border-b border-slate-100 flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[14px] font-medium text-slate-800 uppercase">{{ $o->order_number }}</span>
                    @if($o->team_name)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[9px] font-medium uppercase tracking-wider bg-slate-100 text-slate-500">
                        Tim {{ $o->team_name }}
                    </span>
                    @endif
                </div>
                <p class="text-[12px] text-slate-500 mt-1.5">
                    <span class="font-medium text-slate-700">{{ $o->konsumen->nama_konsumen ?? $o->customer_name ?? '-' }}</span>
                    &middot; Desainer: {{ $o->designer->name ?? $o->designer_name ?? '-' }}
                </p>
                <p class="text-[11px] text-slate-400 mt-0.5">
                    {{ \Carbon\Carbon::parse($o->order_date)->translatedFormat('d M Y') }}
                    @if($o->deadline) &middot; Deadline: {{ \Carbon\Carbon::parse($o->deadline)->translatedFormat('d M Y') }} @endif
                </p>
            </div>
            <div class="text-right shrink-0">
                <x-badge :status="$o->status_pembayaran" />
            </div>
        </div>

        @if($lineOrder->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="w-full text-xs rtable">
                <thead>
                    <tr class="text-[10px] text-slate-400 uppercase bg-slate-50/60">
                        <th class="p-3 text-left w-6">#</th>
                        <th class="p-3 text-left">Nama Barang</th>
                        <th class="p-3 text-right">Qty</th>
                        <th class="p-3 text-right">Harga Satuan</th>
                        <th class="p-3 text-right">Tambahan</th>
                        <th class="p-3 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($lineOrder as $i => $line)
                    <tr>
                        <td data-label="#" class="p-3 text-slate-400">{{ $i + 1 }}</td>
                        <td data-label="Nama Barang" class="p-3 font-medium text-slate-700">{{ $line->nama_barang }}</td>
                        <td data-label="Qty" class="p-3 text-right text-slate-500">{{ angka($line->qty, 4) }}</td>
                        <td data-label="Harga Satuan" class="p-3 text-right text-slate-500">{{ number_format($line->harga_satuan, 0, ',', '.') }}</td>
                        <td data-label="Tambahan" class="p-3 text-right text-slate-500">{{ number_format($line->size_surcharge_total + $line->sleeve_collar_surcharge_total, 0, ',', '.') }}</td>
                        <td data-label="Subtotal" class="p-3 text-right font-medium text-slate-800">{{ number_format($line->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <p class="text-[11px] text-slate-500 italic p-4">Tidak ada rincian barang untuk order ini.</p>
        @endif

        <div class="px-6 py-4 bg-slate-50/60 border-t border-slate-100 flex justify-end">
            <div class="text-right space-y-1 min-w-[220px]">
                <p class="text-[12px] text-slate-500 flex justify-between gap-6"><span>Grand Total</span> <span class="font-medium text-slate-800">Rp {{ number_format($o->total, 0, ',', '.') }}</span></p>
                <p class="text-[12px] text-slate-500 flex justify-between gap-6"><span>Sudah Dibayar</span> <span class="font-medium text-emerald-600">Rp {{ number_format($o->deposit, 0, ',', '.') }}</span></p>
                <p class="text-[13px] font-medium flex justify-between gap-6 pt-1 border-t border-slate-200 {{ $o->sisa_tagihan > 0 ? 'text-rose-500' : 'text-emerald-600' }}"><span>{{ $o->sisa_tagihan > 0 ? 'Sisa Tagihan' : 'Lunas' }}</span> <span>Rp {{ number_format($o->sisa_tagihan, 0, ',', '.') }}</span></p>
            </div>
        </div>
    </div>
    @empty
    <x-card>
        <x-empty-state title="Tidak ada order jersey" description="Sesuaikan filter untuk melihat data." />
    </x-card>
    @endforelse
    </div>

    <div class="no-print">
        <x-table.pagination :paginator="$data" />
    </div>

</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        $('.select2-konsumen-laporan').select2({ placeholder: '-- Cari Nama / No. HP --', allowClear: true, width: '100%' });
    });
</script>
@endpush
@endsection

