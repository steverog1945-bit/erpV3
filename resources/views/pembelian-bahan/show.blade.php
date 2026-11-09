@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1100px] mx-auto">

    {{-- Hardening P2-1: halaman ini sebelumnya bergaya sendiri (bukan
         x-page-header/x-card/x-table seperti seluruh halaman lain di
         aplikasi) - dirombak memakai komponen bersama supaya senada,
         TANPA mengubah satu pun data/field yang ditampilkan. --}}
    <x-page-header title="Faktur Pembelian {{ $pembelian->no_faktur }}">
        <x-slot:subtitle>{{ \Carbon\Carbon::parse($pembelian->tgl_transaksi)->translatedFormat('d F Y') }}</x-slot:subtitle>
        <x-slot:actions>
            <x-button href="{{ route('pembelian-bahan.index') }}" variant="secondary">
                <i class="fas fa-arrow-left"></i> Kembali ke List
            </x-button>
            {{-- Pilihan format cetak: A4 atau Continuous Form --}}
            <div class="relative" id="menu-cetak">
                <x-button type="button" onclick="document.getElementById('menu-cetak-daftar').classList.toggle('hidden')" variant="outline" title="Pilih format kertas lalu cetak faktur">
                    <i class="fas fa-print"></i> Cetak Faktur <i class="fas fa-chevron-down text-[9px]"></i>
                </x-button>
                <div id="menu-cetak-daftar" class="hidden absolute right-0 top-full mt-2 w-64 bg-white rounded-2xl shadow-2xl border border-slate-100 overflow-hidden z-20">
                    <a href="{{ route('pembelian-bahan.cetak', [$pembelian->id_pembelian, 'a4']) }}" target="_blank" class="flex items-start gap-3 px-5 py-4 hover:bg-indigo-50 transition-all">
                        <i class="far fa-file-alt text-slate-500 mt-0.5"></i>
                        <span><span class="block text-xs font-medium text-slate-700 uppercase tracking-widest">Kertas A4</span><span class="block text-[11px] text-slate-400 font-medium">Invoice kertas biasa 210 x 297 mm</span></span>
                    </a>
                    <a href="{{ route('pembelian-bahan.cetak', [$pembelian->id_pembelian, 'continuous']) }}" target="_blank" class="flex items-start gap-3 px-5 py-4 hover:bg-indigo-50 transition-all border-t border-slate-50">
                        <i class="fas fa-scroll text-slate-500 mt-0.5"></i>
                        <span><span class="block text-xs font-medium text-slate-700 uppercase tracking-widest">Continuous Form</span><span class="block text-[11px] text-slate-400 font-medium">Kertas sambung dot-matrix 9,5 x 11 inci</span></span>
                    </a>
                </div>
            </div>
            <script>document.addEventListener('click', e => { const m = document.getElementById('menu-cetak'); if (m && !m.contains(e.target)) document.getElementById('menu-cetak-daftar').classList.add('hidden'); });</script>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
        <x-card>
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-3">Pemasok</p>
            <p class="text-lg font-medium text-slate-800">{{ $pembelian->kedatangan->supplier->nama_supplier ?? 'Umum' }}</p>
        </x-card>
        <x-card>
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-3">Reff. Surat Jalan</p>
            <p class="text-lg font-medium text-indigo-600">{{ $pembelian->kedatangan->no_surat_jalan }}</p>
        </x-card>
    </div>

    <x-table class="mb-6">
        <thead>
            <tr>
                <th class="px-8 py-5 text-left">Bahan</th>
                <th class="px-4 py-5 text-center">Qty</th>
                <th class="px-4 py-5 text-right">Harga</th>
                <th class="px-8 py-5 text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @foreach($pembelian->detail as $item)
            <tr class="hover:bg-slate-50/50 transition-all">
                <td class="px-8 py-5">
                    <p class="text-[13px] font-medium text-slate-700 uppercase">{{ $item->bahan->nama_bahan }}</p>
                    <p class="text-[10px] text-slate-400 font-medium tracking-widest">{{ $item->bahan->kode_bahan }}</p>
                </td>
                <td class="px-4 py-5 text-center font-medium text-slate-600 text-sm">
                    {{ angka($item->qty, 2) }}
                </td>
                <td class="px-4 py-5 text-right font-medium text-slate-600 text-sm">
                    {{ number_format($item->harga, 0, ',', '.') }}
                </td>
                <td class="px-8 py-5 text-right font-medium text-slate-800 text-sm">
                    {{ number_format($item->subtotal, 0, ',', '.') }}
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="bg-indigo-600 text-white">
                <td colspan="3" class="px-8 py-5 text-right text-[10px] font-medium uppercase tracking-widest">Grand Total Faktur</td>
                <td class="px-8 py-5 text-right font-medium text-xl">Rp {{ number_format($pembelian->grand_total, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </x-table>

    <x-card title="Riwayat Pembayaran Hutang">
        <x-slot:actions>
            <span class="text-xs font-medium {{ $sisa > 0 ? 'text-rose-500' : 'text-emerald-600' }}">Sisa hutang: Rp {{ number_format($sisa, 0, ',', '.') }}</span>
        </x-slot:actions>

        @forelse($pembayaran as $b)
            <div class="flex flex-wrap items-center justify-between gap-3 py-3 {{ !$loop->first ? 'border-t border-slate-50' : '' }} text-sm {{ $b->voided_at ? 'opacity-50' : '' }}">
                <div>
                    <span class="font-medium text-slate-700 {{ $b->voided_at ? 'line-through' : '' }}">Rp {{ number_format($b->nominal, 0, ',', '.') }}</span>
                    <span class="text-xs text-slate-400 ml-2">{{ \Carbon\Carbon::parse($b->tanggal)->translatedFormat('d M Y') }} - {{ $b->metode }}</span>
                    @if($b->keterangan)<div class="text-[11px] text-slate-400">{{ $b->keterangan }}</div>@endif
                    @if($b->voided_at)<div class="text-[11px] text-rose-500">Di-void: {{ $b->void_alasan }}</div>@endif
                </div>
                @if(!$b->voided_at && !$b->bagian_faktur)
                    <form method="POST" action="{{ route('pembelian.void-pembayaran', $b->id) }}" onsubmit="const a = prompt('Alasan void pembayaran ini (wajib):'); if (!a) return false; this.alasan.value = a; return true;">
                        @csrf
                        <input type="hidden" name="alasan">
                        <button type="submit" class="text-[10px] font-medium uppercase tracking-widest text-rose-500 hover:underline">Ajukan Void</button>
                    </form>
                @endif
            </div>
        @empty
            <x-empty-state title="Belum ada pembayaran tercatat" />
        @endforelse
    </x-card>
</div>
@endsection
