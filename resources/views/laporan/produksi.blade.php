@extends('layouts.app')

@section('content')
<style>
    @media print {
        aside, .no-print { display: none !important; }
        main { margin-left: 0 !important; padding: 28px 32px !important; }
        body { background: #fff !important; }
    }
</style>
@include('partials.print-report-header', ['docTitle' => 'Laporan Produksi'])

<div class="w-full px-4 sm:px-6 lg:px-8 py-10 print:pt-0 max-w-[1600px] mx-auto">

    <div class="print:hidden">
        <x-page-header title="Laporan Produksi">
            <x-slot:subtitle>
                Laporan <span class="mx-1 text-slate-500">/</span> Rekap Produksi per Divisi &amp; Produk
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
        <form method="GET" action="{{ route('laporan.produksi') }}" class="flex flex-wrap items-end gap-4">
            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Dari Tanggal</label>
                <x-form.input type="date" name="tgl_dari" value="{{ $tglDari }}" />
            </div>
            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Sampai Tanggal</label>
                <x-form.input type="date" name="tgl_sampai" value="{{ $tglSampai }}" />
            </div>
            <div class="w-full md:w-52">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Divisi (Mesin)</label>
                <x-form.select name="id_mesin">
                    <option value="">-- Semua Divisi --</option>
                    @foreach($divisis as $d)
                        <option value="{{ $d->id_mesin }}" {{ (string) request('id_mesin') === (string) $d->id_mesin ? 'selected' : '' }}>{{ $d->nama_mesin }}</option>
                    @endforeach
                </x-form.select>
            </div>
            <div class="w-full md:w-48">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Status SPK (detail)</label>
                <x-form.select name="status_spk">
                    <option value="">-- Semua Status --</option>
                    @foreach(['Antre', 'Desain', 'Cetak', 'Selesai', 'Batal'] as $s)
                        <option value="{{ $s }}" {{ request('status_spk') === $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </x-form.select>
            </div>

            <x-button type="submit">
                <i class="fas fa-filter"></i>
                Filter
            </x-button>

            @if(request()->anyFilled(['tgl_dari', 'tgl_sampai', 'id_mesin', 'status_spk']))
                <a href="{{ route('laporan.produksi') }}" class="text-[11px] font-medium text-rose-500 uppercase tracking-widest hover:underline ml-2">
                    Reset
                </a>
            @endif
        </form>
    </x-card>

    {{-- RESUME - hanya metrik yang memang sah dijumlah lintas divisi
         (jumlah pcs/nota, satuannya konsisten). Luas (m2) SENGAJA TIDAK
         ditampilkan di sini sebagai satu angka gabungan - rincian m2 per
         divisi ada di tabel "Ringkasan per Divisi" di bawah. Kartu
         berwarna di layar (tidak diubah), tabel standar polos khusus
         cetak. --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 print:hidden">
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Total Order (Semua Divisi)</p>
            <h3 class="text-2xl font-medium text-slate-900 mt-1">{{ number_format($totalOrderKeseluruhan, 0, ',', '.') }} pcs</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Total Cetak (Semua Divisi)</p>
            <h3 class="text-2xl font-medium text-emerald-600 mt-1">{{ number_format($totalCetakKeseluruhan, 0, ',', '.') }} pcs</h3>
        </div>
    </div>
    <div class="hidden print:block">
        <x-laporan.resume-table title="Resume" :rows="[
            'Total Order (Semua Divisi)' => number_format($totalOrderKeseluruhan, 0, ',', '.') . ' pcs',
            'Total Cetak (Semua Divisi)' => number_format($totalCetakKeseluruhan, 0, ',', '.') . ' pcs',
        ]" />
    </div>

    {{-- RINGKASAN PER DIVISI - tiap divisi jadi BLOK/BAGIAN SENDIRI dengan
         Total-nya masing-masing (persis pola referensi: tiap Kategori
         tampil sebagai bagian terpisah dengan Total sendiri) - TIDAK
         digabung jadi satu tabel/angka besar lintas divisi, karena luas
         (m2) satu divisi tidak sepadan dijumlah dengan pcs/m2 divisi lain
         (mesin/proses produksi berbeda). --}}
    <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1 px-1 print:hidden">Ringkasan per Divisi</p>
    <p class="text-[11px] text-slate-400 mb-3 px-1 print:hidden">Nota dibatalkan dan SPK Batal tidak dihitung di ringkasan.</p>
    @forelse($ringkasanPerDivisi as $div)
    <x-card :title="$div->divisi" class="mb-4 break-inside-avoid print:hidden" :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-left rtable">
                <thead class="bg-slate-50 border-y border-slate-100">
                    <tr>
                        <th class="px-6 py-3 text-[10px] font-medium uppercase text-slate-400">Produk</th>
                        <th class="px-4 py-3 text-[10px] font-medium uppercase text-slate-400 text-center">Jml Nota</th>
                        <th class="px-4 py-3 text-[10px] font-medium uppercase text-slate-400 text-center">Qty Order</th>
                        <th class="px-6 py-3 text-[10px] font-medium uppercase text-slate-400 text-center">Qty Cetak</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($div->baris as $r)
                    <tr class="hover:bg-slate-50/50">
                        <td data-label="Produk" class="px-6 py-3 text-[12px] font-medium text-slate-600">{{ $r->nama_produk }}</td>
                        <td data-label="Jml Nota" class="px-4 py-3 text-[12px] font-medium text-slate-700 text-center">{{ $r->total_transaksi }}</td>
                        <td data-label="Qty Order" class="px-4 py-3 text-[12px] font-medium text-slate-700 text-center">
                            {!! $r->metode_hitung === 'ukuran' ? number_format($r->total_m2_order, 2, ',', '.') . ' m&sup2;' : number_format($r->total_order, 0, ',', '.') . ' pcs' !!}
                        </td>
                        <td data-label="Qty Cetak" class="px-6 py-3 text-[12px] font-medium text-emerald-600 text-center">
                            {!! $r->metode_hitung === 'ukuran' ? number_format($r->total_m2_cetak, 2, ',', '.') . ' m&sup2;' : number_format($r->total_cetak, 0, ',', '.') . ' pcs' !!}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-50 border-t border-slate-100">
                    <tr>
                        <td class="px-6 py-3 text-[11px] font-medium uppercase text-slate-500">Total {{ $div->divisi }}</td>
                        <td></td>
                        <td class="px-4 py-3 text-[13px] font-medium text-slate-800 text-center">
                            @if($div->subtotal_qty_order_pcs > 0) {{ number_format($div->subtotal_qty_order_pcs, 0, ',', '.') }} pcs @endif
                            @if($div->subtotal_m2_order > 0) {{ number_format($div->subtotal_m2_order, 2, ',', '.') }} m&sup2; @endif
                        </td>
                        <td class="px-6 py-3 text-[13px] font-medium text-emerald-700 text-center">
                            @if($div->subtotal_qty_cetak_pcs > 0) {{ number_format($div->subtotal_qty_cetak_pcs, 0, ',', '.') }} pcs @endif
                            @if($div->subtotal_m2_cetak > 0) {{ number_format($div->subtotal_m2_cetak, 2, ',', '.') }} m&sup2; @endif
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-card>
    @empty
    <x-card class="mb-6 print:hidden">
        <x-empty-state title="Tidak ada data" description="Sesuaikan filter untuk melihat ringkasan produksi." />
    </x-card>
    @endforelse

    {{-- Versi cetak: tabel polos per divisi (bukan kartu rounded/shadow),
         gaya visual sama dengan Laporan Order. --}}
    <div class="hidden print:block">
        <p class="text-[10px] font-medium text-slate-700 uppercase tracking-wide mb-2">Ringkasan per Divisi</p>
        @forelse($ringkasanPerDivisi as $div)
        <div class="break-inside-avoid">
            <x-laporan.print-table :title="$div->divisi" :headers="['Produk', 'Jml Nota', 'Qty Order', 'Qty Cetak']" :align="['left', 'right', 'right', 'right']">
                @foreach($div->baris as $r)
                <tr>
                    <td class="px-3 py-1.5 text-slate-600">{{ $r->nama_produk }}</td>
                    <td class="px-3 py-1.5 text-right text-slate-600">{{ $r->total_transaksi }}</td>
                    <td class="px-3 py-1.5 text-right text-slate-600">
                        {!! $r->metode_hitung === 'ukuran' ? number_format($r->total_m2_order, 2, ',', '.') . ' m&sup2;' : number_format($r->total_order, 0, ',', '.') . ' pcs' !!}
                    </td>
                    <td class="px-3 py-1.5 text-right text-slate-600">
                        {!! $r->metode_hitung === 'ukuran' ? number_format($r->total_m2_cetak, 2, ',', '.') . ' m&sup2;' : number_format($r->total_cetak, 0, ',', '.') . ' pcs' !!}
                    </td>
                </tr>
                @endforeach
                <tr class="bg-slate-50 border-t border-slate-200">
                    <td class="px-3 py-1.5 font-medium text-slate-700 uppercase">Total {{ $div->divisi }}</td>
                    <td></td>
                    <td class="px-3 py-1.5 text-right font-medium text-slate-800">
                        @if($div->subtotal_qty_order_pcs > 0) {{ number_format($div->subtotal_qty_order_pcs, 0, ',', '.') }} pcs @endif
                        @if($div->subtotal_m2_order > 0) {{ number_format($div->subtotal_m2_order, 2, ',', '.') }} m&sup2; @endif
                    </td>
                    <td class="px-3 py-1.5 text-right font-medium text-slate-800">
                        @if($div->subtotal_qty_cetak_pcs > 0) {{ number_format($div->subtotal_qty_cetak_pcs, 0, ',', '.') }} pcs @endif
                        @if($div->subtotal_m2_cetak > 0) {{ number_format($div->subtotal_m2_cetak, 2, ',', '.') }} m&sup2; @endif
                    </td>
                </tr>
            </x-laporan.print-table>
        </div>
        @empty
        <p class="text-[11px] text-slate-400 italic">Tidak ada data.</p>
        @endforelse
    </div>

    {{-- DETAIL PER SPK - kartu/tabel biasa di layar, versi cetak
         disederhanakan (font 10/11px konsisten, tanpa styling badge
         berwarna). --}}
    <div class="print:hidden">
    <x-table class="rtable">
        <thead>
            <tr>
                <th class="px-4 py-5 text-center w-10">#</th>
                <th class="px-6 py-5 text-left">SPK</th>
                <th class="px-4 py-5 text-left">Nama File</th>
                <th class="px-4 py-5 text-left">Konsumen</th>
                <th class="px-4 py-5 text-left">Tanggal</th>
                <th class="px-4 py-5 text-left">Divisi</th>
                <th class="px-4 py-5 text-left">Desainer</th>
                <th class="px-4 py-5 text-left">Ukuran</th>
                <th class="px-4 py-5 text-center">Qty Order</th>
                <th class="px-4 py-5 text-center">Qty Cetak</th>
                <th class="px-4 py-5 text-center">m&sup2;</th>
                <th class="px-8 py-5 text-center">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($detail as $i => $d)
            @php $m2Baris = $d->metode_hitung === 'ukuran' && $d->p_ukuran > 0 && $d->l_ukuran > 0 ? ($d->p_ukuran * $d->l_ukuran / 10000) * $d->qty : null; @endphp
            <tr class="hover:bg-slate-50/50 transition-all">
                <td class="px-4 py-5 text-center text-[11px] text-slate-400">{{ $detail->firstItem() + $i }}</td>
                <td data-label="SPK" class="px-6 py-5"><span class="text-[12px] font-medium text-slate-500">#{{ $d->id_detail }}</span></td>
                <td data-label="Nama File" class="px-4 py-5"><span class="text-[13px] font-medium text-slate-700 uppercase">{{ $d->nama_file }}</span> <div class="text-[10px] text-slate-400">{{ $d->nomor_nota }}</div></td>
                <td data-label="Konsumen" class="px-4 py-5"><span class="text-[12px] font-medium text-slate-700 uppercase">{{ $d->nama_konsumen }}</span></td>
                <td data-label="Tanggal" class="px-4 py-5"><span class="text-[12px] font-medium text-slate-600">{{ \Carbon\Carbon::parse($d->tgl_order)->translatedFormat('d M Y') }}</span></td>
                <td data-label="Divisi" class="px-4 py-5"><span class="text-[12px] text-slate-500">{{ $d->divisi ?? '-' }}</span></td>
                <td data-label="Desainer" class="px-4 py-5"><span class="text-[12px] text-slate-500">{{ $d->desainer ?? '-' }}</span></td>
                <td data-label="Ukuran" class="px-4 py-5"><span class="text-[12px] text-slate-500">{{ $d->p_ukuran > 0 && $d->l_ukuran > 0 ? number_format($d->p_ukuran, 0, ',', '.') . 'x' . number_format($d->l_ukuran, 0, ',', '.') . ' cm' : '-' }}</span></td>
                <td data-label="Qty Order" class="px-4 py-5 text-center"><span class="text-[13px] font-medium text-slate-700">{{ angka($d->qty, 4) }}</span></td>
                <td data-label="Qty Cetak" class="px-4 py-5 text-center"><span class="text-[13px] font-medium text-slate-700">{{ $d->qty_cetak ?? '-' }}</span></td>
                <td data-label="m&sup2;" class="px-4 py-5 text-center"><span class="text-[13px] font-medium text-indigo-600">{{ $m2Baris !== null ? number_format($m2Baris, 2, ',', '.') : '-' }}</span></td>
                <td data-label="Status" class="px-8 py-5 text-center"><x-badge :status="$d->status_spk" /></td>
            </tr>
            @empty
            <tr>
                <td colspan="12">
                    <x-empty-state title="Tidak ada data" description="Sesuaikan filter untuk melihat detail produksi." />
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-table>
    </div>

    <div class="hidden print:block">
        <x-laporan.print-table title="Detail per SPK" :headers="['#', 'SPK', 'Nama File', 'Konsumen', 'Tanggal', 'Divisi', 'Desainer', 'Ukuran', 'Qty Order', 'Qty Cetak', 'm²', 'Status']" :align="['center', 'left', 'left', 'left', 'left', 'left', 'left', 'left', 'right', 'right', 'right', 'left']">
            @forelse($detail as $i => $d)
            @php $m2BarisPrint = $d->metode_hitung === 'ukuran' && $d->p_ukuran > 0 && $d->l_ukuran > 0 ? ($d->p_ukuran * $d->l_ukuran / 10000) * $d->qty : null; @endphp
            <tr>
                <td class="px-3 py-1.5 text-center text-slate-400">{{ $detail->firstItem() + $i }}</td>
                <td class="px-3 py-1.5 text-slate-500">#{{ $d->id_detail }}</td>
                <td class="px-3 py-1.5 text-slate-700">{{ $d->nama_file }}<br><span class="text-slate-400">{{ $d->nomor_nota }}</span></td>
                <td class="px-3 py-1.5 text-slate-700">{{ $d->nama_konsumen }}</td>
                <td class="px-3 py-1.5 text-slate-600">{{ \Carbon\Carbon::parse($d->tgl_order)->format('d/m/Y') }}</td>
                <td class="px-3 py-1.5 text-slate-600">{{ $d->divisi ?? '-' }}</td>
                <td class="px-3 py-1.5 text-slate-600">{{ $d->desainer ?? '-' }}</td>
                <td class="px-3 py-1.5 text-slate-600">{{ $d->p_ukuran > 0 && $d->l_ukuran > 0 ? number_format($d->p_ukuran, 0, ',', '.') . 'x' . number_format($d->l_ukuran, 0, ',', '.') . ' cm' : '-' }}</td>
                <td class="px-3 py-1.5 text-right text-slate-600">{{ angka($d->qty, 4) }}</td>
                <td class="px-3 py-1.5 text-right text-slate-600">{{ $d->qty_cetak ?? '-' }}</td>
                <td class="px-3 py-1.5 text-right text-slate-600">{{ $m2BarisPrint !== null ? number_format($m2BarisPrint, 2, ',', '.') : '-' }}</td>
                <td class="px-3 py-1.5 text-slate-600">{{ $d->status_spk }}</td>
            </tr>
            @empty
            <tr>
                <td class="px-3 py-1.5 text-slate-400 italic" colspan="12">Tidak ada data</td>
            </tr>
            @endforelse
        </x-laporan.print-table>
    </div>

    <div class="no-print">
        <x-table.pagination :paginator="$detail" />
    </div>

</div>
@endsection
