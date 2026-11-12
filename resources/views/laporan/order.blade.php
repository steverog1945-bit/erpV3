@extends('layouts.app')

@section('content')
<style>
    @media print {
        aside, .no-print { display: none !important; }
        main { margin-left: 0 !important; padding: 28px 32px !important; }
        body { background: #fff !important; }
        .nota-block { break-inside: avoid; }
    }
</style>
@include('partials.print-report-header', ['docTitle' => 'Laporan Rekap Transaksi Order'])

<div class="w-full px-4 sm:px-6 lg:px-8 py-10 print:pt-0 max-w-[1600px] mx-auto">

    <div class="print:hidden">
        <x-page-header title="Laporan Rekap Transaksi Order">
            <x-slot:subtitle>
                Laporan <span class="mx-1 text-slate-500">/</span> Rekap Order &amp; Piutang
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
        <form method="GET" action="{{ route('laporan.order') }}" class="flex flex-wrap items-end gap-4">
            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Dari Tanggal</label>
                <x-form.input type="date" name="tgl_dari" value="{{ $tglDari }}" />
            </div>
            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Sampai Tanggal</label>
                <x-form.input type="date" name="tgl_sampai" value="{{ $tglSampai }}" />
            </div>
            <div class="w-full md:w-48">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Status Transaksi</label>
                <x-form.select name="status_pembayaran">
                    <option value="">-- Semua --</option>
                    @foreach(['Lunas', 'DP', 'Belum Bayar'] as $s)
                        <option value="{{ $s }}" {{ request('status_pembayaran') === $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </x-form.select>
            </div>
            <div class="w-full md:w-48">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Golongan Konsumen</label>
                <x-form.select name="id_golongan">
                    <option value="">-- Semua --</option>
                    @foreach($golongan as $g)
                        <option value="{{ $g->id_golongan }}" {{ (string) request('id_golongan') === (string) $g->id_golongan ? 'selected' : '' }}>{{ $g->nama_golongan }}</option>
                    @endforeach
                </x-form.select>
            </div>
            <div class="w-full md:w-64 lg:w-80">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Konsumen</label>
                <select name="id_konsumen" id="select-konsumen-laporan" class="select2-konsumen-laporan w-full">
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

            @if(request()->anyFilled(['tgl_dari', 'tgl_sampai', 'status_pembayaran', 'id_golongan', 'id_konsumen']))
                <a href="{{ route('laporan.order') }}" class="text-[11px] font-medium text-rose-500 uppercase tracking-widest hover:underline ml-2">
                    Reset
                </a>
            @endif
        </form>
    </x-card>

    {{-- RESUME - kartu berwarna di layar (tampilan yang sudah pas, tidak
         diubah), tabel standar polos khusus saat dicetak. --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 print:hidden">
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Jumlah Transaksi</p>
            <h3 class="text-2xl font-medium text-slate-900 mt-1">{{ number_format($jumlahTransaksi, 0, ',', '.') }}</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Total Transaksi</p>
            <h3 class="text-2xl font-medium text-indigo-600 mt-1">Rp {{ number_format($totalTransaksi, 0, ',', '.') }}</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Total Piutang (sisa tagihan riil)</p>
            <h3 class="text-2xl font-medium text-rose-500 mt-1">Rp {{ number_format($totalPiutang, 0, ',', '.') }}</h3>
        </div>
    </div>
    <div class="hidden print:block">
        <x-laporan.resume-table title="Resume Transaksi" :rows="[
            'Jumlah Transaksi' => number_format($jumlahTransaksi, 0, ',', '.') . ' Kali',
            'Total Transaksi' => 'Rp ' . number_format($totalTransaksi, 0, ',', '.'),
            'Total Piutang (sisa tagihan riil)' => 'Rp ' . number_format($totalPiutang, 0, ',', '.'),
        ]" />
    </div>

    {{-- BREAKDOWN PER DIVISI/MESIN - tabel produk tidak punya kolom
         kategori tersendiri, jadi pengelompokan pakai Divisi (mesin) yang
         sudah dipakai konsisten di Laporan Produksi. Kartu berwarna di
         layar (tidak diubah), tabel polos sederhana khusus cetak. --}}
    @if($breakdownDivisi->isNotEmpty())
    <x-card title="Rincian Biaya per Divisi" class="mb-6 break-inside-avoid print:hidden">
        <x-table bare class="rtable">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left">Divisi</th>
                    <th class="px-4 py-3 text-right">Biaya Cetak</th>
                    <th class="px-4 py-3 text-right">Biaya Design</th>
                    <th class="px-4 py-3 text-right">Biaya Finishing</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @foreach($breakdownDivisi as $b)
                <tr>
                    <td data-label="Divisi" class="px-4 py-3 text-[12px] font-medium text-slate-700">{{ $b->divisi }}</td>
                    <td data-label="Biaya Cetak" class="px-4 py-3 text-right text-[12px] font-medium text-slate-600">{{ number_format($b->total_biaya_cetak, 0, ',', '.') }}</td>
                    <td data-label="Biaya Design" class="px-4 py-3 text-right text-[12px] font-medium text-slate-600">{{ number_format($b->total_biaya_desain, 0, ',', '.') }}</td>
                    <td data-label="Biaya Finishing" class="px-4 py-3 text-right text-[12px] font-medium text-slate-600">{{ number_format($b->total_biaya_finishing, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </x-table>
    </x-card>
    <div class="hidden print:block">
        <div class="break-inside-avoid">
            <x-laporan.print-table title="Rincian Biaya per Divisi" :headers="['Divisi', 'Biaya Cetak', 'Biaya Design', 'Biaya Finishing']" :align="['left', 'right', 'right', 'right']">
                @foreach($breakdownDivisi as $b)
                <tr>
                    <td class="px-3 py-1.5 font-medium text-slate-700">{{ $b->divisi }}</td>
                    <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($b->total_biaya_cetak, 0, ',', '.') }}</td>
                    <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($b->total_biaya_desain, 0, ',', '.') }}</td>
                    <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($b->total_biaya_finishing, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </x-laporan.print-table>
        </div>

        {{-- RINCIAN PER PRODUK DI TIAP DIVISI - selengkap referensi (tiap
             divisi jadi tabel produk tersendiri dengan Total-nya sendiri,
             sama seperti pola "Ringkasan per Divisi" di Laporan Produksi -
             TIDAK digabung jadi satu angka lintas divisi). --}}
        @foreach($breakdownProduk as $namaDivisi => $baris)
        <div class="break-inside-avoid">
            <x-laporan.print-table :title="'Divisi: ' . $namaDivisi" :headers="['Produk', 'Jml Nota', 'Qty', 'Total']" :align="['left', 'right', 'right', 'right']">
                @foreach($baris as $p)
                <tr>
                    <td class="px-3 py-1.5 text-slate-600">{{ $p->produk }}</td>
                    <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($p->jml_nota, 0, ',', '.') }}</td>
                    <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($p->total_qty, 0, ',', '.') }}</td>
                    <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($p->total_subtotal, 0, ',', '.') }}</td>
                </tr>
                @endforeach
                <tr class="bg-slate-50 border-t border-slate-200">
                    <td class="px-3 py-1.5 font-medium text-slate-700 uppercase" colspan="3">Total {{ $namaDivisi }}</td>
                    <td class="px-3 py-1.5 text-right font-medium text-slate-800">{{ number_format($baris->sum('total_subtotal'), 0, ',', '.') }}</td>
                </tr>
            </x-laporan.print-table>
        </div>
        @endforeach
    </div>
    @endif

    <div class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-3 px-1 print:hidden">Detail Transaksi</div>

    {{-- DETAIL TRANSAKSI - kartu per nota di layar (tidak diubah). Saat
         cetak, isinya disamakan persis dengan referensi (satu tabel per
         nota: kolom Divisi/Produk/Ukuran/Harga/Potongan/Qty/Cetak/Design/
         Finishing/Subtotal + baris Grand Total/Bayar/Kurang Bayar) tapi
         tetap pakai gaya visual kita sendiri (tabel polos, bukan kartu
         berwarna/rounded/shadow seperti tampilan layar). --}}
    <div class="hidden print:block">
        <p class="text-[10px] font-medium text-slate-700 uppercase tracking-wide mb-2">Detail Transaksi</p>
        @forelse($data as $t)
        @php
            $itemNotaPrint = $itemPerNota->get($t->id_transaksi, collect());
            $kurangBayarPrint = max(0, $t->total_bayar - $t->total_dibayar);
        @endphp
        <div class="mb-6 border border-slate-200 overflow-hidden break-inside-avoid">
            <div class="px-3 py-2 bg-slate-50 border-b border-slate-200 text-[10.5px] text-slate-600 leading-relaxed">
                <span class="font-medium text-slate-800">{{ $t->nomor_nota }}</span>
                &middot; Konsumen: {{ $t->nama_konsumen }}
                &middot; Golongan: {{ $t->nama_golongan }}
                @if($t->no_po) &middot; No. PO: {{ $t->no_po }} @endif
                &middot; Tanggal: {{ \Carbon\Carbon::parse($t->tgl_order)->format('d/m/Y') }}
                @if($t->nama_operator) &middot; Operator: {{ $t->nama_operator }} @endif
                &middot; Status: {{ $t->status_nota === 'Dibatalkan' ? 'Dibatalkan' : $t->status_pembayaran }}
            </div>
            <table class="w-full text-[11px] border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-left w-6">#</th>
                        <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-left">Divisi</th>
                        <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-left">Produk</th>
                        <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-left">Ukuran</th>
                        <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-right">Harga</th>
                        <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-right">Potongan</th>
                        <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-right">Qty</th>
                        <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-right">Cetak(Rp)</th>
                        <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-right">Design(Rp)</th>
                        <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-right">Finishing(Rp)</th>
                        <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($itemNotaPrint as $i => $item)
                    <tr>
                        <td class="px-3 py-1.5 text-slate-400">{{ $i + 1 }}</td>
                        <td class="px-3 py-1.5 text-slate-600">{{ $item->divisi ?? '-' }}</td>
                        <td class="px-3 py-1.5 text-slate-700">{{ $item->nama_produk ?? $item->nama_jasa ?? '-' }}</td>
                        <td class="px-3 py-1.5 text-slate-600">{{ $item->p_ukuran > 0 && $item->l_ukuran > 0 ? number_format($item->p_ukuran, 0, ',', '.') . 'x' . number_format($item->l_ukuran, 0, ',', '.') . ' cm' : '-' }}</td>
                        <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($item->harga_jual_snapshot, 0, ',', '.') }}</td>
                        <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($item->potongan, 0, ',', '.') }}</td>
                        <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($item->qty, 0, ',', '.') }}</td>
                        <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($item->biaya_cetak, 0, ',', '.') }}</td>
                        <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($item->biaya_desain, 0, ',', '.') }}</td>
                        <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($item->biaya_finishing, 0, ',', '.') }}</td>
                        <td class="px-3 py-1.5 text-right font-medium text-slate-800">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td class="px-3 py-1.5 text-slate-400 italic" colspan="11">Tidak ada rincian item untuk nota ini.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-3 py-2 bg-slate-50 border-t border-slate-200 flex justify-end">
                <table class="text-[11px]">
                    @if((float) ($t->diskon ?? 0) > 0)
                    <tr><td class="pr-4 text-slate-600">Subtotal Barang</td><td class="text-right font-medium text-slate-800">{{ number_format($t->total_bayar + (float) $t->diskon, 0, ',', '.') }}</td></tr>
                    <tr><td class="pr-4 text-slate-600">Diskon</td><td class="text-right font-medium text-emerald-600">-{{ number_format((float) $t->diskon, 0, ',', '.') }}</td></tr>
                    @endif
                    <tr><td class="pr-4 text-slate-600">Grand Total</td><td class="text-right font-medium text-slate-800">{{ number_format($t->total_bayar, 0, ',', '.') }}</td></tr>
                    <tr><td class="pr-4 text-slate-600">Total Bayar</td><td class="text-right font-medium text-slate-800">{{ number_format($t->total_dibayar, 0, ',', '.') }}</td></tr>
                    <tr><td class="pr-4 font-medium text-slate-700">{{ $kurangBayarPrint > 0 ? 'Kurang Bayar' : 'Lunas' }}</td><td class="text-right font-medium text-slate-900">{{ number_format($kurangBayarPrint, 0, ',', '.') }}</td></tr>
                </table>
            </div>
        </div>
        @empty
        <p class="text-[11px] text-slate-400 italic">Tidak ada data.</p>
        @endforelse
    </div>

    <div class="print:hidden">
    @forelse($data as $t)
    @php $itemNota = $itemPerNota->get($t->id_transaksi, collect()); @endphp
    <div class="nota-block bg-white rounded-2xl border border-slate-100 shadow-sm mb-5 overflow-hidden {{ $t->status_nota === 'Dibatalkan' ? 'opacity-60' : '' }}">
        {{-- HEADER NOTA - konsumen, golongan, operator, tipe order semua
             langsung terlihat (tidak perlu klik apa pun), sesuai referensi
             yang diacu user. --}}
        <div class="px-6 py-4 bg-slate-50/80 border-b border-slate-100 flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[14px] font-medium text-slate-800 uppercase">{{ $t->nomor_nota }}</span>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[9px] font-medium uppercase tracking-wider bg-slate-100 text-slate-600 border border-slate-200">
                        {{ $t->nama_golongan }}
                    </span>
                    @if($t->tipe_order)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[9px] font-medium uppercase tracking-wider bg-slate-100 text-slate-500">
                        {{ $t->tipe_order }}
                    </span>
                    @endif
                </div>
                <p class="text-[12px] text-slate-500 mt-1.5">
                    <span class="font-medium text-slate-700">{{ $t->nama_konsumen }}</span>
                    @if($t->no_po) &middot; No. PO: {{ $t->no_po }} @endif
                </p>
                <p class="text-[11px] text-slate-400 mt-0.5">
                    {{ \Carbon\Carbon::parse($t->tgl_order)->translatedFormat('d M Y') }}
                    @if($t->created_at) &middot; {{ \Carbon\Carbon::parse($t->created_at)->format('H:i') }} @endif
                    @if($t->nama_operator) &middot; Operator: {{ $t->nama_operator }} @endif
                </p>
            </div>
            <div class="text-right shrink-0">
                @if($t->status_nota === 'Dibatalkan')
                    <x-badge status="Dibatalkan" variant="danger" />
                @else
                    <x-badge :status="$t->status_pembayaran" />
                @endif
            </div>
        </div>

        {{-- ITEM PESANAN - selalu tampil penuh, tidak lagi expand/collapse. --}}
        @if($itemNota->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="w-full text-xs rtable">
                <thead>
                    <tr class="text-[10px] text-slate-400 uppercase bg-slate-50/60">
                        <th class="p-3 text-left w-6">#</th>
                        <th class="p-3 text-left">Divisi</th>
                        <th class="p-3 text-left">Produk / Jasa</th>
                        <th class="p-3 text-left">Ukuran</th>
                        <th class="p-3 text-right">Harga</th>
                        <th class="p-3 text-right">Potongan</th>
                        <th class="p-3 text-right">Qty</th>
                        <th class="p-3 text-right">Cetak</th>
                        <th class="p-3 text-right">Design</th>
                        <th class="p-3 text-right">Finishing</th>
                        <th class="p-3 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($itemNota as $i => $item)
                    <tr>
                        <td data-label="#" class="p-3 text-slate-400">{{ $i + 1 }}</td>
                        <td data-label="Divisi" class="p-3 text-slate-500">{{ $item->divisi ?? '-' }}</td>
                        <td data-label="Produk / Jasa" class="p-3 font-medium text-slate-700">{{ $item->nama_produk ?? $item->nama_jasa ?? '-' }}<br><span class="text-[10px] font-normal text-slate-400">{{ $item->nama_file }}</span></td>
                        <td data-label="Ukuran" class="p-3 text-slate-500">{{ $item->p_ukuran > 0 && $item->l_ukuran > 0 ? number_format($item->p_ukuran, 0, ',', '.') . 'x' . number_format($item->l_ukuran, 0, ',', '.') . ' cm' : '-' }}</td>
                        <td data-label="Harga" class="p-3 text-right text-slate-500">{{ number_format($item->harga_jual_snapshot, 0, ',', '.') }}</td>
                        <td data-label="Potongan" class="p-3 text-right text-slate-500">{{ number_format($item->potongan, 0, ',', '.') }}</td>
                        <td data-label="Qty" class="p-3 text-right text-slate-500">{{ number_format($item->qty, 0, ',', '.') }}</td>
                        <td data-label="Cetak" class="p-3 text-right text-slate-500">{{ number_format($item->biaya_cetak, 0, ',', '.') }}</td>
                        <td data-label="Design" class="p-3 text-right text-slate-500">{{ number_format($item->biaya_desain, 0, ',', '.') }}</td>
                        <td data-label="Finishing" class="p-3 text-right text-slate-500">{{ number_format($item->biaya_finishing, 0, ',', '.') }}</td>
                        <td data-label="Subtotal" class="p-3 text-right font-medium text-slate-800">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <p class="text-[11px] text-slate-500 italic p-4">Tidak ada rincian item untuk nota ini.</p>
        @endif

        {{-- TOTAL PER NOTA --}}
        <div class="px-6 py-4 bg-slate-50/60 border-t border-slate-100 flex justify-end">
            <div class="text-right space-y-1 min-w-[220px]">
                @if((float) ($t->diskon ?? 0) > 0)
                <p class="text-[12px] text-slate-500 flex justify-between gap-6"><span>Subtotal Barang</span> <span class="font-medium text-slate-800">Rp {{ number_format($t->total_bayar + (float) $t->diskon, 0, ',', '.') }}</span></p>
                <p class="text-[12px] text-emerald-600 flex justify-between gap-6"><span>Diskon</span> <span class="font-medium">âˆ’Rp {{ number_format((float) $t->diskon, 0, ',', '.') }}</span></p>
                @endif
                <p class="text-[12px] text-slate-500 flex justify-between gap-6"><span>Grand Total</span> <span class="font-medium text-slate-800">Rp {{ number_format($t->total_bayar, 0, ',', '.') }}</span></p>
                <p class="text-[12px] text-slate-500 flex justify-between gap-6"><span>Sudah Dibayar</span> <span class="font-medium text-emerald-600">Rp {{ number_format($t->total_dibayar, 0, ',', '.') }}</span></p>
                @php $kurangBayar = max(0, $t->total_bayar - $t->total_dibayar); @endphp
                <p class="text-[13px] font-medium flex justify-between gap-6 pt-1 border-t border-slate-200 {{ $kurangBayar > 0 ? 'text-rose-500' : 'text-emerald-600' }}"><span>{{ $kurangBayar > 0 ? 'Kurang Bayar' : 'Lunas' }}</span> <span>Rp {{ number_format($kurangBayar, 0, ',', '.') }}</span></p>
            </div>
        </div>
    </div>
    @empty
    <x-card>
        <x-empty-state title="Tidak ada data" description="Sesuaikan filter untuk melihat transaksi." />
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

