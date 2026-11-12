@extends('layouts.app')

@section('content')
<style>
    @media print {
        aside, .no-print { display: none !important; }
        main { margin-left: 0 !important; padding: 28px 32px !important; }
        body { background: #fff !important; }
    }
</style>
@include('partials.print-report-header', ['docTitle' => 'Laporan Stok Bahan Baku'])

<div class="w-full px-4 sm:px-6 lg:px-8 py-10 print:pt-0 max-w-[1600px] mx-auto">

    <div class="print:hidden">
        <x-page-header title="Laporan Stok Bahan Baku">
            <x-slot:subtitle>
                Laporan <span class="mx-1 text-slate-500">/</span> Evaluasi Pengadaan &amp; Pembelanjaan Bahan - kondisi stok saat ini, bukan rekap transaksi periode
            </x-slot:subtitle>
            <x-slot:actions>
                <x-button type="button" onclick="window.print()" variant="secondary" class="no-print">
                    <i class="fas fa-print"></i>
                    Cetak
                </x-button>
            </x-slot:actions>
        </x-page-header>
    </div>

    {{-- FILTER - TIDAK ada filter tanggal (laporan ini snapshot kondisi
         stok SAAT INI, bukan rekap transaksi per periode seperti laporan
         lain). --}}
    <x-card class="mb-6 no-print">
        <form method="GET" action="{{ route('laporan.stok-bahan') }}" class="flex flex-wrap items-end gap-4">
            <div class="w-full md:w-64 lg:w-80">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari Bahan</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Nama atau kode bahan..." class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
            </div>
            <div class="w-full md:w-52">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Kategori</label>
                <x-form.select name="id_kategori">
                    <option value="">-- Semua Kategori --</option>
                    @foreach($kategoriList as $kat)
                        <option value="{{ $kat->id_kategori }}" {{ (string) request('id_kategori') === (string) $kat->id_kategori ? 'selected' : '' }}>{{ $kat->nama_kategori }}</option>
                    @endforeach
                </x-form.select>
            </div>
            <div class="w-full md:w-52">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Status Stok</label>
                <x-form.select name="status_stok">
                    <option value="">-- Semua Status --</option>
                    <option value="Habis" {{ request('status_stok') === 'Habis' ? 'selected' : '' }}>Habis</option>
                    <option value="Menipis" {{ request('status_stok') === 'Menipis' ? 'selected' : '' }}>Menipis</option>
                    <option value="Belum Ada Batas" {{ request('status_stok') === 'Belum Ada Batas' ? 'selected' : '' }}>Belum Ada Batas Minimal</option>
                    <option value="Aman" {{ request('status_stok') === 'Aman' ? 'selected' : '' }}>Aman</option>
                </x-form.select>
            </div>

            <x-button type="submit">
                <i class="fas fa-filter"></i>
                Filter
            </x-button>

            @if(request()->anyFilled(['search', 'id_kategori', 'status_stok']))
                <a href="{{ route('laporan.stok-bahan') }}" class="text-[11px] font-medium text-rose-500 uppercase tracking-widest hover:underline ml-2">
                    Reset
                </a>
            @endif
        </form>
    </x-card>

    {{-- RESUME - kartu berwarna di layar, tabel standar polos khusus
         cetak. --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6 print:hidden">
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Jumlah Bahan Aktif</p>
            <h3 class="text-2xl font-medium text-slate-900 mt-1">{{ number_format($jumlahBahan, 0, ',', '.') }}</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Stok Habis</p>
            <h3 class="text-2xl font-medium text-rose-500 mt-1">{{ number_format($jumlahHabis, 0, ',', '.') }}</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Stok Menipis</p>
            <h3 class="text-2xl font-medium text-amber-500 mt-1">{{ number_format($jumlahMenipis, 0, ',', '.') }}</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Belum Ada Batas Minimal</p>
            <h3 class="text-2xl font-medium text-sky-500 mt-1">{{ number_format($jumlahBelumAdaBatas, 0, ',', '.') }}</h3>
        </div>
    </div>
    <div class="hidden print:block">
        <x-laporan.resume-table title="Resume" :rows="[
            'Jumlah Bahan Aktif' => number_format($jumlahBahan, 0, ',', '.'),
            'Stok Habis' => number_format($jumlahHabis, 0, ',', '.'),
            'Stok Menipis' => number_format($jumlahMenipis, 0, ',', '.'),
            'Belum Ada Batas Minimal' => number_format($jumlahBelumAdaBatas, 0, ',', '.'),
        ]" />
    </div>

    <div class="print:hidden">
    <x-table class="rtable">
        <thead>
            <tr>
                <th class="px-4 py-5 text-center w-10">#</th>
                <th class="px-6 py-5 text-left">Kode</th>
                <th class="px-4 py-5 text-left">Nama Bahan</th>
                <th class="px-4 py-5 text-left">Kategori</th>
                <th class="px-4 py-5 text-left">Satuan</th>
                <th class="px-4 py-5 text-right">Stok Saat Ini</th>
                <th class="px-4 py-5 text-right">Stok Minimal</th>
                <th class="px-4 py-5 text-right">Harga Rata-rata</th>
                <th class="px-8 py-5 text-center">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($data as $i => $b)
            <tr class="hover:bg-slate-50/50 transition-all">
                <td class="px-4 py-5 text-center text-[11px] text-slate-400">{{ $data->firstItem() + $i }}</td>
                <td data-label="Kode" class="px-6 py-5"><span class="text-[12px] font-medium text-slate-500 uppercase">{{ $b->kode_bahan }}</span></td>
                <td data-label="Nama Bahan" class="px-4 py-5"><span class="text-[13px] font-medium text-slate-700">{{ $b->nama_bahan }}</span></td>
                <td data-label="Kategori" class="px-4 py-5"><span class="text-[12px] text-slate-500">{{ $b->kategori->nama_kategori ?? '-' }}</span></td>
                <td data-label="Satuan" class="px-4 py-5"><span class="inline-flex px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 text-[11px] font-medium uppercase">{{ $b->satuan_stok ?: '-' }}</span></td>
                <td data-label="Stok Saat Ini" class="px-4 py-5 text-right"><span class="text-[13px] font-medium {{ $b->status_stok === 'Habis' ? 'text-rose-500' : ($b->status_stok === 'Menipis' ? 'text-amber-600' : 'text-slate-700') }}">{{ number_format($b->stok, 2, ',', '.') }} {{ $b->satuan_stok }}</span></td>
                <td data-label="Stok Minimal" class="px-4 py-5 text-right"><span class="text-[12px] text-slate-500">{{ $b->stok_minimal > 0 ? number_format($b->stok_minimal, 2, ',', '.') . ' ' . $b->satuan_stok : '-' }}</span></td>
                <td data-label="Harga Rata-rata" class="px-4 py-5 text-right"><span class="text-[12px] text-slate-500">Rp {{ number_format($b->harga_rata_rata, 0, ',', '.') }}</span></td>
                <td data-label="Status" class="px-8 py-5 text-center">
                    <x-badge :variant="match($b->status_stok) { 'Habis' => 'danger', 'Menipis' => 'warning', 'Belum Ada Batas' => 'info', default => 'success' }">{{ $b->status_stok }}</x-badge>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8">
                    <x-empty-state title="Tidak ada bahan" description="Sesuaikan filter untuk melihat stok bahan baku." />
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-table>
    </div>

    {{-- Versi cetak: tabel polos sederhana, gaya visual sama dengan
         laporan lain (font 10/11px konsisten). --}}
    <div class="hidden print:block">
        <x-laporan.print-table title="Detail Stok Bahan Baku" :headers="['#', 'Kode', 'Nama Bahan', 'Kategori', 'Stok Saat Ini', 'Stok Minimal', 'Harga Rata-rata', 'Status']" :align="['center', 'left', 'left', 'left', 'right', 'right', 'right', 'left']">
            @forelse($data as $i => $b)
            <tr>
                <td class="px-3 py-1.5 text-center text-slate-400">{{ $data->firstItem() + $i }}</td>
                <td class="px-3 py-1.5 text-slate-500">{{ $b->kode_bahan }}</td>
                <td class="px-3 py-1.5 font-medium text-slate-700">{{ $b->nama_bahan }}</td>
                <td class="px-3 py-1.5 text-slate-600">{{ $b->kategori->nama_kategori ?? '-' }}</td>
                <td class="px-3 py-1.5 text-right text-slate-700">{{ number_format($b->stok, 2, ',', '.') }} {{ $b->satuan_stok }}</td>
                <td class="px-3 py-1.5 text-right text-slate-600">{{ $b->stok_minimal > 0 ? number_format($b->stok_minimal, 2, ',', '.') . ' ' . $b->satuan_stok : '-' }}</td>
                <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($b->harga_rata_rata, 0, ',', '.') }}</td>
                <td class="px-3 py-1.5 text-slate-600">{{ $b->status_stok }}</td>
            </tr>
            @empty
            <tr>
                <td class="px-3 py-1.5 text-slate-400 italic" colspan="8">Tidak ada bahan.</td>
            </tr>
            @endforelse
        </x-laporan.print-table>
    </div>

    <div class="no-print">
        <x-table.pagination :paginator="$data" />
    </div>

</div>
@endsection

