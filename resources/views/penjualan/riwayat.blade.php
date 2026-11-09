@extends('layouts.app')

@section('content')



<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Riwayat Nota" :subtitle="$konsumenTerpilih ? 'Daftar transaksi penjualan per konsumen' : 'Daftar transaksi penjualan Rp- default hari ini'">
        <x-slot:actions>
            @if($isAdmin)
                <a href="{{ route('penjualan.edit-approval') }}" class="relative inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-amber-50 text-amber-700 border border-amber-100 hover:bg-amber-100 transition-all font-medium text-xs uppercase tracking-widest">
                    <i class="fas fa-user-shield"></i> Persetujuan Edit Nota
                    @if($pendingEditCount > 0)
                        <span class="absolute -top-2 -right-2 w-5 h-5 rounded-full bg-rose-500 text-white text-[10px] font-medium flex items-center justify-center">{{ $pendingEditCount }}</span>
                    @endif
                </a>
            @endif
            <x-button href="{{ route('penjualan.create') }}" variant="outline">
                <i class="fas fa-arrow-left"></i> Kembali ke Kasir
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if($konsumenTerpilih)
        <div class="mb-6 flex items-center justify-between bg-indigo-50 border border-indigo-100 rounded-2xl px-6 py-4">
            <p class="text-[13px] text-indigo-700">
                <i class="fas fa-user mr-1.5"></i>
                Menampilkan seluruh riwayat untuk <b>{{ $konsumenTerpilih->nama_konsumen }}</b>
                @if($from || $to)
                    (dipersempit ke rentang tanggal di bawah)
                @endif
            </p>
            <a href="{{ route('penjualan.riwayat') }}" class="text-[11px] font-medium text-indigo-500 uppercase tracking-widest hover:underline">
                <i class="fas fa-times-circle mr-1"></i> Hapus Filter Konsumen
            </a>
        </div>
    @endif

    @if(!empty($cari))
        <div class="mb-6 flex items-center justify-between bg-amber-50 border border-amber-100 rounded-2xl px-6 py-4">
            <p class="text-[13px] text-amber-700">
                <i class="fas fa-search mr-1.5"></i>
                Hasil pencarian <b>{{ $cari }}</b> (nomor nota / nama konsumen) - rentang tanggal bebas.
            </p>
            <a href="{{ route('penjualan.riwayat') }}" class="text-[11px] font-medium text-amber-600 uppercase tracking-widest hover:underline">
                <i class="fas fa-times-circle mr-1"></i> Hapus Pencarian
            </a>
        </div>
    @endif

    {{-- FILTER RANGE TANGGAL + PENCARIAN --}}
    <x-card class="mb-8">
        <form method="GET" action="{{ route('penjualan.riwayat') }}" class="flex flex-wrap items-end gap-4">
            @if($konsumenTerpilih)
                <input type="hidden" name="id_konsumen" value="{{ $konsumenTerpilih->id_konsumen }}">
            @endif
            {{-- Pencarian nomor nota / nama konsumen. Saat terisi, filter
                 default "hari ini" dilepas di controller supaya nota lama
                 bisa ditemukan tanpa harus melebarkan tanggal manual. --}}
            <div class="w-full md:w-72">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari No. Nota / Konsumen</label>
                <x-form.input type="search" name="cari" value="{{ $cari ?? '' }}" placeholder="cth. NT-1032 / Budi" />
            </div>

            <div class="w-full md:w-56">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Dari Tanggal</label>
                <x-form.input type="date" name="from" value="{{ $from }}" />
            </div>

            <div class="w-full md:w-56">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Sampai Tanggal</label>
                <x-form.input type="date" name="to" value="{{ $to }}" />
            </div>

            <x-button type="submit">
                <i class="fas fa-filter"></i> Tampilkan
            </x-button>

            <x-button href="{{ route('penjualan.riwayat') }}" variant="outline">
                Hari Ini
            </x-button>
        </form>
    </x-card>

    <x-table>
        <thead>
            <tr>
                <th class="px-4 py-5 text-center w-10">#</th>
                <th class="px-8 py-5 text-left">No. Nota</th>
                <th class="px-6 py-5 text-left">Tanggal & Jam</th>
                <th class="px-6 py-5 text-left">Pelanggan</th>
                <th class="px-6 py-5 text-left">Golongan</th>
                <th class="px-6 py-5 text-left">Tipe Order</th>
                <th class="px-6 py-5 text-left">Kasir</th>
                <th class="px-6 py-5 text-center">Status</th>
                <th class="px-6 py-5 text-right">Total</th>
                <th class="px-8 py-5 text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($transaksis as $i => $t)
            <tr class="group hover:bg-slate-50/30 transition-all {{ $t->status_nota === 'Dibatalkan' ? 'opacity-50' : '' }}">
                <td class="px-4 py-5 text-center text-[11px] text-slate-400">{{ $transaksis->firstItem() + $i }}</td>
                <td class="px-8 py-5">
                    <span class="text-[13px] font-medium text-slate-700">{{ $t->nomor_nota }}</span>
                </td>
                <td class="px-6 py-5">
                    <span class="text-sm font-medium text-slate-600">{{ \Carbon\Carbon::parse($t->tgl_order)->translatedFormat('d M Y') }}</span>
                    <div class="text-[11px] text-slate-400">{{ \Carbon\Carbon::parse($t->tgl_order)->format('H:i') }}</div>
                </td>
                <td class="px-6 py-5">
                    <span class="text-sm font-medium text-slate-700">{{ $t->nama_konsumen }}</span>
                </td>
                <td class="px-6 py-5">
                    <span class="text-xs text-slate-500">{{ $t->nama_golongan ?? '-' }}</span>
                </td>
                <td class="px-6 py-5">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-medium {{ ($t->tipe_order ?? 'Reguler') === 'PO' ? 'bg-amber-50 text-amber-700 border border-amber-100' : 'bg-slate-50 text-slate-500 border border-slate-100' }}">
                        {{ $t->tipe_order ?? 'Reguler' }}
                    </span>
                </td>
                <td class="px-6 py-5">
                    <span class="text-sm text-slate-500">{{ $t->nama_kasir ?? '-' }}</span>
                </td>
                <td class="px-6 py-5 text-center">
                    @if($t->status_nota === 'Dibatalkan')
                        <x-badge status="Dibatalkan" variant="danger" />
                    @else
                        <x-badge :status="$t->status_pembayaran" />
                    @endif
                </td>
                <td class="px-6 py-5 text-right">
                    @bolehHarga<span class="text-[14px] font-medium text-indigo-600">Rp {{ number_format($t->total_bayar, 0, ',', '.') }}</span>@endbolehHarga
                </td>
                <td class="px-8 py-5 text-right">
                    <div class="flex items-center justify-end gap-1.5">
                        <a href="{{ route('penjualan.show', $t->id_transaksi) }}" class="inline-flex items-center gap-1 px-3 py-2 rounded-lg text-[11px] font-medium uppercase text-indigo-600 hover:bg-indigo-50 transition-all">
                            <i class="far fa-eye"></i> Lihat
                        </a>
                        @if($t->status_nota !== 'Dibatalkan' && \App\Support\Izin::boleh(auth()->user(), [3, 16, 48]))
                            <a href="{{ route('kasir.print-opsi', $t->id_transaksi) }}" target="_blank" class="inline-flex items-center gap-1 px-3 py-2 rounded-lg text-[11px] font-medium uppercase text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 transition-all" title="Cetak nota">
                                <i class="fas fa-print"></i> Cetak
                            </a>
                        @endif

                        {{-- EDIT NOTA (selisih). Satu jalur untuk semua: operator
                             yang membuat nota (termasuk Operator Desain) bisa
                             mengoreksinya langsung lewat Input Pesanan. Nota TIDAK
                             dibatalkan - yang berubah hanya selisihnya.

                             Tombol lama "Ajukan Edit" (batal + buat ulang lewat
                             persetujuan Administrator) sudah dilepas: alur itu
                             membatalkan nota, mengembalikan seluruh stok, dan
                             membalik jurnal - jauh lebih besar daripada yang
                             sebenarnya perlu untuk koreksi nominal.

                             Syarat & alasannya dihitung server
                             (NotaEditService) yang sama dengan saat menyimpan,
                             jadi tombol di daftar dan penolakan saat disimpan
                             tidak bisa berbeda pendapat. --}}
                        @if($t->boleh_edit)
                            <a href="{{ route('penjualan.create', ['edit' => $t->id_transaksi]) }}"
                               class="inline-flex items-center gap-1 px-3 py-2 rounded-lg text-[11px] font-medium uppercase text-amber-600 hover:bg-amber-50 transition-all"
                               title="Koreksi item nota ini (stok & jurnal hanya menyesuaikan selisihnya)">
                                <i class="far fa-edit"></i> Edit
                            </a>
                        @elseif($t->status_nota !== 'Dibatalkan' && !empty($t->alasan_edit) && (int) $t->id_user_kasir === (int) auth()->id())
                            <span class="inline-flex items-center gap-1 px-3 py-2 rounded-lg text-[11px] font-medium uppercase text-slate-300"
                                  title="{{ $t->alasan_edit }}">
                                <i class="far fa-edit"></i> Edit
                            </span>
                        @endif

                        @php
                            $bisaTambahItemRiwayat = $t->status_nota !== 'Dibatalkan'
                                && in_array($t->status_order, ['Menunggu Kasir', 'Diterima Kasir'], true)
                                && (int) $t->id_user_kasir === (int) auth()->id()
                                && \Carbon\Carbon::parse($t->tgl_order)->toDateString() === now()->toDateString();
                        @endphp
                        @if($bisaTambahItemRiwayat)
                            <a href="{{ route('penjualan.create', ['susulan' => $t->id_transaksi]) }}"
                               class="inline-flex items-center gap-1 px-3 py-2 rounded-lg text-[11px] font-medium uppercase text-indigo-500 hover:bg-indigo-50 transition-all"
                               title="Tambah order susulan ke nota ini (hanya hari ini)">
                                <i class="fas fa-plus"></i> Tambah Item
                            </a>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="10">
                    <x-empty-state title="Belum ada nota pada rentang tanggal ini" />
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$transaksis" />

</div>
@endsection