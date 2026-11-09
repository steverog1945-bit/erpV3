@extends('layouts.app')

@section('content')
<style>
    @media print {
        aside, .no-print { display: none !important; }
        main { margin-left: 0 !important; padding: 28px 32px !important; }
        body { background: #fff !important; }
    }
</style>
@include('partials.print-report-header', ['docTitle' => 'Laporan HPP & Margin'])

<div class="w-full px-4 sm:px-6 lg:px-8 py-10 print:pt-0 max-w-[1600px] mx-auto">

    <div class="print:hidden">
        <x-page-header title="Laporan HPP & Margin">
            <x-slot:subtitle>
                Laporan <span class="mx-1 text-slate-500">/</span> Estimasi Margin Keuntungan per Transaksi
            </x-slot:subtitle>
            <x-slot:actions>
                <x-button type="button" onclick="window.print()" variant="secondary" class="no-print">
                    <i class="fas fa-print"></i>
                    Cetak
                </x-button>
            </x-slot:actions>
        </x-page-header>
    </div>

    <div class="bg-amber-50 text-amber-700 p-4 rounded-2xl mb-6 text-xs font-medium flex items-start gap-2 no-print">
        <i class="fas fa-triangle-exclamation mt-0.5"></i>
        <span>Margin di sini hanya memperhitungkan biaya BAHAN BAKU sesuai komposisi BOM produk - BELUM termasuk ongkos finishing, jasa, tenaga kerja, atau overhead (aplikasi belum mencatat biaya-biaya itu). Angka ini adalah <b>margin kotor atas bahan</b>, bukan laba bersih sesungguhnya. Baris Jasa selalu HPP Rp0 (tidak berbasis BOM).</span>
    </div>

    {{-- FILTER --}}
    <x-card class="mb-6 no-print">
        <form method="GET" action="{{ route('laporan.hpp') }}" class="flex flex-wrap items-end gap-4">
            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Dari Tanggal</label>
                <x-form.input type="date" name="tgl_dari" value="{{ $tglDari }}" />
            </div>
            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Sampai Tanggal</label>
                <x-form.input type="date" name="tgl_sampai" value="{{ $tglSampai }}" />
            </div>
            <div class="w-full md:w-52">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Mesin/Divisi</label>
                <x-form.select name="id_mesin">
                    <option value="">Semua Mesin</option>
                    @foreach($mesinList as $m)
                        <option value="{{ $m->id_mesin }}" {{ (string) request('id_mesin') === (string) $m->id_mesin ? 'selected' : '' }}>{{ strtoupper($m->nama_mesin) }}</option>
                    @endforeach
                </x-form.select>
            </div>

            <x-button type="submit">
                <i class="fas fa-filter"></i>
                Filter
            </x-button>

            @if(request()->anyFilled(['tgl_dari', 'tgl_sampai', 'id_mesin']))
                <a href="{{ route('laporan.hpp') }}" class="text-[11px] font-medium text-rose-500 uppercase tracking-widest hover:underline ml-2">Reset</a>
            @endif
        </form>
    </x-card>

    {{-- RESUME - kartu berwarna di layar, tabel standar polos khusus cetak. --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6 print:hidden">
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Total Omzet</p>
            <h3 class="text-xl font-medium text-slate-900 mt-1">Rp {{ number_format($totalOmzet, 0, ',', '.') }}</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Total HPP Bahan</p>
            <h3 class="text-xl font-medium text-slate-900 mt-1">Rp {{ number_format($totalHpp, 0, ',', '.') }}</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-emerald-100 shadow-sm">
            <p class="text-[10px] font-medium text-emerald-500 uppercase tracking-widest">Margin Kotor</p>
            <h3 class="text-xl font-medium text-emerald-600 mt-1">Rp {{ number_format($totalMargin, 0, ',', '.') }}</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-emerald-100 shadow-sm">
            <p class="text-[10px] font-medium text-emerald-500 uppercase tracking-widest">Margin %</p>
            <h3 class="text-xl font-medium text-emerald-600 mt-1">{{ number_format($marginPersen, 1, ',', '.') }}%</h3>
        </div>
    </div>

    <div class="hidden print:block">
        <x-laporan.resume-table title="Resume" :rows="[
            'Total Omzet' => 'Rp ' . number_format($totalOmzet, 0, ',', '.'),
            'Total HPP Bahan' => 'Rp ' . number_format($totalHpp, 0, ',', '.'),
            'Margin Kotor' => 'Rp ' . number_format($totalMargin, 0, ',', '.'),
            'Margin %' => number_format($marginPersen, 1, ',', '.') . '%',
        ]" />
    </div>

    {{-- BREAKDOWN PER DIVISI --}}
    @if($breakdownDivisi->isNotEmpty())
    <x-card class="mb-6 print:hidden">
        <h3 class="text-sm font-medium text-slate-800 mb-4">Ringkasan per Divisi</h3>
        <x-table bare>
            <thead>
                <tr>
                    <th class="p-3 text-left">Divisi</th>
                    <th class="p-3 text-right">Omzet</th>
                    <th class="p-3 text-right">HPP Bahan</th>
                    <th class="p-3 text-right">Margin</th>
                    <th class="p-3 text-right">Margin %</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @foreach($breakdownDivisi as $d)
                <tr>
                    <td class="p-3 font-medium text-slate-700 uppercase text-xs">{{ $d->divisi }}</td>
                    <td class="p-3 text-right text-xs">Rp {{ number_format($d->total_omzet, 0, ',', '.') }}</td>
                    <td class="p-3 text-right text-xs">Rp {{ number_format($d->total_hpp, 0, ',', '.') }}</td>
                    <td class="p-3 text-right text-xs font-medium text-emerald-600">Rp {{ number_format($d->margin, 0, ',', '.') }}</td>
                    <td class="p-3 text-right text-xs font-medium text-emerald-600">{{ number_format($d->margin_persen, 1, ',', '.') }}%</td>
                </tr>
                @endforeach
            </tbody>
        </x-table>
    </x-card>
    <div class="hidden print:block break-inside-avoid">
        <x-laporan.print-table title="Ringkasan per Divisi" :headers="['Divisi', 'Omzet', 'HPP Bahan', 'Margin', 'Margin %']" :align="['left', 'right', 'right', 'right', 'right']">
            @foreach($breakdownDivisi as $d)
            <tr>
                <td class="px-3 py-1.5 font-medium text-slate-700 uppercase">{{ $d->divisi }}</td>
                <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($d->total_omzet, 0, ',', '.') }}</td>
                <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($d->total_hpp, 0, ',', '.') }}</td>
                <td class="px-3 py-1.5 text-right text-slate-800">{{ number_format($d->margin, 0, ',', '.') }}</td>
                <td class="px-3 py-1.5 text-right text-slate-800">{{ number_format($d->margin_persen, 1, ',', '.') }}%</td>
            </tr>
            @endforeach
        </x-laporan.print-table>
    </div>
    @endif

    <div class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-3 px-1 print:hidden">Detail Per Item</div>

    {{-- DETAIL PER ITEM --}}
    <div class="print:hidden">
    <x-table>
        <thead>
            <tr>
                <th class="px-4 py-5 text-center w-10">#</th>
                <th class="px-6 py-5 text-left">Nota</th>
                <th class="px-4 py-5 text-left">Tanggal</th>
                <th class="px-4 py-5 text-left">Item</th>
                <th class="px-4 py-5 text-left">Divisi</th>
                <th class="px-4 py-5 text-center">Qty</th>
                <th class="px-4 py-5 text-right">Omzet</th>
                <th class="px-4 py-5 text-right">HPP Bahan</th>
                <th class="px-6 py-5 text-right">Margin</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($data as $i => $row)
            @php $marginBaris = $row->subtotal - $row->hpp_snapshot; @endphp
            <tr class="hover:bg-slate-50/50 transition-all">
                <td class="px-4 py-4 text-center text-[11px] text-slate-400">{{ $data->firstItem() + $i }}</td>
                <td class="px-6 py-4 text-[12px] font-medium text-slate-700">{{ $row->nomor_nota }}</td>
                <td class="px-4 py-4 text-[12px] text-slate-500">{{ \Carbon\Carbon::parse($row->tgl_order)->translatedFormat('d M Y') }}</td>
                <td class="px-4 py-4 text-[12px] font-medium text-slate-600">{{ $row->nama_item }}</td>
                <td class="px-4 py-4 text-[11px] text-slate-400 uppercase">{{ $row->divisi }}</td>
                <td class="px-4 py-4 text-[12px] text-center text-slate-600">{{ number_format($row->qty, 0, ',', '.') }}</td>
                <td class="px-4 py-4 text-[12px] text-right text-slate-600">Rp {{ number_format($row->subtotal, 0, ',', '.') }}</td>
                <td class="px-4 py-4 text-[12px] text-right text-slate-500">Rp {{ number_format($row->hpp_snapshot, 0, ',', '.') }}</td>
                <td class="px-6 py-4 text-[12px] text-right font-medium text-emerald-600">Rp {{ number_format($marginBaris, 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="9">
                    <x-empty-state title="Belum ada data" description="Tidak ada transaksi pada rentang tanggal yang dipilih." />
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-table>
    </div>

    <div class="hidden print:block">
        <x-laporan.print-table title="Detail Per Item" :headers="['#', 'Nota', 'Tanggal', 'Item', 'Divisi', 'Qty', 'Omzet', 'HPP Bahan', 'Margin']" :align="['center', 'left', 'left', 'left', 'left', 'center', 'right', 'right', 'right']">
            @forelse($data as $i => $row)
            <tr>
                <td class="px-3 py-1.5 text-center text-slate-400">{{ $data->firstItem() + $i }}</td>
                <td class="px-3 py-1.5 font-medium text-slate-700">{{ $row->nomor_nota }}</td>
                <td class="px-3 py-1.5 text-slate-600">{{ \Carbon\Carbon::parse($row->tgl_order)->format('d/m/Y') }}</td>
                <td class="px-3 py-1.5 text-slate-700">{{ $row->nama_item }}</td>
                <td class="px-3 py-1.5 text-slate-600 uppercase">{{ $row->divisi }}</td>
                <td class="px-3 py-1.5 text-center text-slate-600">{{ number_format($row->qty, 0, ',', '.') }}</td>
                <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($row->subtotal, 0, ',', '.') }}</td>
                <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($row->hpp_snapshot, 0, ',', '.') }}</td>
                <td class="px-3 py-1.5 text-right font-medium text-slate-800">{{ number_format($row->subtotal - $row->hpp_snapshot, 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td class="px-3 py-1.5 text-slate-400 italic" colspan="9">Tidak ada transaksi pada rentang tanggal yang dipilih.</td>
            </tr>
            @endforelse
        </x-laporan.print-table>
    </div>

    <div class="no-print">
        <x-table.pagination :paginator="$data" />
    </div>

</div>
@endsection
