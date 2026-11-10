@extends('layouts.app')

@section('content')
@include('partials.rupiah-format-script')
@include('partials.image-compress-script')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-6xl mx-auto">

    <x-page-header :title="$order->order_number" subtitle="Detail order jersey">
        <x-slot:actions>
            <div class="relative">
                <x-button type="button" variant="secondary" onclick="togglePrintMenuRingkasan(event)">
                    <i class="fas fa-print"></i> Cetak Ringkasan
                </x-button>
                <div id="print-menu-ringkasan" class="hidden absolute right-0 mt-2 w-52 bg-white border border-slate-100 shadow-xl rounded-xl z-50 overflow-hidden">
                    <a href="{{ route('jersey.orders.print', [$order->id, 'thermal']) }}" target="_blank" class="flex items-center gap-2 px-4 py-2.5 text-[10px] font-medium text-slate-600 hover:bg-slate-50 border-b border-slate-50">
                        <i class="fas fa-receipt text-slate-500"></i> THERMAL (58MM)
                    </a>
                    <a href="{{ route('jersey.orders.print', [$order->id, 'normal']) }}" target="_blank" class="flex items-center gap-2 px-4 py-2.5 text-[10px] font-medium text-slate-600 hover:bg-slate-50">
                        <i class="fas fa-file-invoice text-slate-500"></i> A4 / F4
                    </a>
                </div>
            </div>
            <x-button type="button" variant="success" onclick="openModal('tambah-susulan')">
                <i class="fas fa-plus"></i> Tambah Barang Susulan
            </x-button>
            <x-button href="{{ route('jersey.orders.edit-lines', $order->id) }}" variant="secondary">
                <i class="fas fa-boxes-stacked"></i> Kelola Barang
            </x-button>
            <x-button href="{{ route('jersey.orders.index') }}" variant="outline">
                <i class="fas fa-arrow-left"></i> Kembali
            </x-button>
            <form id="delete-form-jersey-order" action="{{ route('jersey.orders.destroy', $order->id) }}" method="POST">
                @csrf @method('DELETE')
                <x-button type="button" variant="danger" onclick="confirmHapusOrderJersey()"><i class="far fa-trash-alt"></i> Hapus</x-button>
            </form>
        </x-slot:actions>
    </x-page-header>

    @if (session('success'))
        <div class="bg-emerald-50 text-emerald-600 p-4 rounded-2xl mb-6 font-medium">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <x-card class="md:col-span-2">
            <h3 class="text-sm font-medium text-slate-700 mb-4">Informasi Order</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div><p class="text-[10px] text-slate-400 uppercase font-medium">Pelanggan</p><p class="font-medium">{{ $order->konsumen->nama_konsumen ?? $order->customer_name }}</p></div>
                <div><p class="text-[10px] text-slate-400 uppercase font-medium">No. HP</p><p class="font-medium">{{ $order->customer_phone ?? '-' }}</p></div>
                <div><p class="text-[10px] text-slate-400 uppercase font-medium">Tim</p><p class="font-medium">{{ $order->team_name ?? '-' }}</p></div>
                <div><p class="text-[10px] text-slate-400 uppercase font-medium">Tanggal Order</p><p class="font-medium">{{ \Carbon\Carbon::parse($order->order_date)->format('d/m/Y') }}</p></div>
                <div><p class="text-[10px] text-slate-400 uppercase font-medium">Deadline</p><p class="font-medium">{{ $order->deadline ? \Carbon\Carbon::parse($order->deadline)->format('d/m/Y') : '-' }}</p></div>
                <div><p class="text-[10px] text-slate-400 uppercase font-medium">Desainer</p><p class="font-medium">{{ $order->desainer?->nama ?? $order->designer_name ?? '-' }}@if($order->file_siap_cetak) <span class="text-[10px] text-emerald-600">(file siap cetak)</span>@endif</p></div>
            </div>
            @if($order->notes)
                <div class="mt-4 p-3 bg-slate-50 rounded-xl text-xs text-slate-500">{{ $order->notes }}</div>
            @endif
        </x-card>

        <x-card>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-slate-700">Ringkasan Biaya</h3>
                <x-badge :status="$order->status_pembayaran" />
            </div>
            @bolehHarga
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-slate-400">Subtotal Barang</span><span>Rp {{ number_format($order->lines->sum('subtotal'), 0, ',', '.') }}</span></div>
                <div class="flex justify-between"><span class="text-slate-400">Diskon</span><span>Rp {{ number_format($order->discount, 0, ',', '.') }}</span></div>
                <div class="flex justify-between"><span class="text-slate-400">Biaya Tambahan</span><span>Rp {{ number_format($order->additional_charge, 0, ',', '.') }}</span></div>
                <div class="flex justify-between border-t border-slate-100 pt-2 font-medium text-base"><span>Total</span><span>Rp {{ number_format($order->total, 0, ',', '.') }}</span></div>
                <div class="flex justify-between text-emerald-600"><span class="text-slate-400">Sudah Dibayar</span><span class="font-medium">Rp {{ number_format($order->deposit, 0, ',', '.') }}</span></div>
                <div class="flex justify-between border-t border-slate-100 pt-2 font-medium text-base text-rose-600"><span>Sisa Tagihan</span><span>Rp {{ number_format($order->sisa_tagihan, 0, ',', '.') }}</span></div>
            </div>
            @endbolehHarga
            @if($order->sisa_tagihan > 0)
                <x-button type="button" variant="success" class="w-full mt-4" onclick="openModal('tambah-pembayaran')">
                    <i class="fas fa-hand-holding-usd"></i> Catat Pembayaran
                </x-button>
            @endif
        </x-card>
    </div>

    <div class="space-y-6 mb-6">
        @foreach($order->lines as $line)
        <x-card>
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between mb-4 gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-center overflow-hidden shrink-0">
                        @if($line->thumbnail)
                            @if(\App\Support\BerkasDesain::bisaDitampilkan($line->thumbnail))
                                <img loading="lazy" decoding="async" src="{{ asset('storage/' . $line->thumbnail) }}" class="w-full h-full object-cover">
                            @else
                                {{-- Berkas desain non-gambar (CDR, AI, EPS, PSD) tidak
                                     bisa ditampilkan peramban - pakai thumbnail
                                     default supaya kotaknya tidak kosong. Nama
                                     file tetap dari berkas asli. --}}
                                <img loading="lazy" decoding="async" src="{{ asset('storage/default-thumbnail.png') }}" class="w-full h-full object-cover opacity-50" title="Berkas desain {{ strtoupper(\App\Support\BerkasDesain::ekstensi($line->thumbnail)) }}">
                            @endif
                        @else
                            <i class="fas fa-image text-slate-500"></i>
                        @endif
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-slate-700 flex items-center gap-2">
                            {{ $line->nama_barang }}
                            @if($line->is_susulan)
                                <span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-600 text-[9px] font-medium uppercase tracking-widest">Susulan</span>
                            @endif
                        </h3>
                        <p class="text-[11px] text-slate-400">{{ $line->productType->name ?? '-' }} &middot; {{ $line->category->name ?? '-' }} &middot; {{ $line->bahan ?? '-' }} @if($line->sleeveType) &middot; {{ $line->sleeveType->name }} @endif @if($line->collarType) &middot; {{ $line->collarType->name }} @endif</p>
                        @bolehHarga
                        <p class="text-[11px] text-slate-400">
                            {{ angka($line->qty, 4) }} pcs &times; Rp {{ number_format($line->harga_satuan, 0, ',', '.') }}
                            @if($line->size_surcharge_total > 0)
                                + Rp {{ number_format($line->size_surcharge_total, 0, ',', '.') }} <span class="text-amber-500 font-medium">(tambahan ukuran)</span>
                            @endif
                            @if($line->sleeve_collar_surcharge_total > 0)
                                + Rp {{ number_format($line->sleeve_collar_surcharge_total, 0, ',', '.') }} <span class="text-amber-500 font-medium">(tambahan lengan/kerah)</span>
                            @endif
                            = <span class="font-medium text-slate-600">Rp {{ number_format($line->subtotal, 0, ',', '.') }}</span>
                        </p>
                        @foreach($line->finishings as $fin)
                            <p class="text-[11px] text-violet-500">
                                <i class="fas fa-cut mr-1"></i>{{ $fin->finishing->nama_finishing ?? '-' }}
                                @if($fin->status_finishing === 'Custom') <span class="font-medium">(Custom)</span>@endif
                                @if($fin->qty_pakai) &middot; {{ angka($fin->qty_pakai, 4) }} meter @endif
                                @if($fin->keping) &middot; {{ $fin->keping }} keping @endif
                                &middot; Rp {{ number_format($fin->biaya, 0, ',', '.') }} <span class="text-slate-300">(masuk total order)</span>
                            </p>
                        @endforeach
                        @endbolehHarga
                    </div>
                </div>
                <x-button href="{{ route('jersey.orders.lines.spk', [$order->id, $line->id]) }}" size="sm" variant="secondary" target="_blank">
                    <i class="fas fa-print"></i> SPK
                </x-button>
            </div>

            @if($line->keterangan)
                <div class="mb-4 p-3 bg-slate-50 rounded-xl text-xs text-slate-500">{{ $line->keterangan }}</div>
            @endif

            @if($line->items->isNotEmpty())
            <div class="mb-4 overflow-x-auto">
                @php
                    // Urutkan pemain sesuai sort_order master ukuran (S, M, L,
                    // XL, ... diikuti ukuran dewasa/anak sesuai master) - bukan
                    // urutan input - supaya mudah difilter/diperiksa per ukuran.
                    // Snapshot teks size dipakai sebagai fallback urutan AB bila
                    // relasi master tidak ada (data lama sebelum backfill).
                    $itemsSorted = $line->items->sortBy([
                        ['sort_size_order', 'asc'],
                        ['player_name', 'asc'],
                    ], SORT_NATURAL | SORT_FLAG_CASE)->values();
                @endphp
                <table class="w-full text-xs">
                    <thead><tr class="text-[10px] text-slate-400 uppercase"><th class="p-2 text-left">Nama</th><th class="p-2 text-left">No.</th><th class="p-2 text-left">Ukuran</th><th class="p-2 text-left">Gender</th><th class="p-2 text-left">Lengan</th><th class="p-2 text-left">Kerah</th></tr></thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach($itemsSorted as $item)
                        <tr>
                            <td class="p-2 font-medium">{{ $item->player_name }}</td>
                            <td class="p-2">{{ $item->player_number ?? '-' }}</td>
                            <td class="p-2">{{ $item->size }}</td>
                            <td class="p-2">{{ $item->gender }}</td>
                            <td class="p-2">{{ $item->sleeve }}</td>
                            <td class="p-2">{{ $item->collar ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            @if($line->sizeQuantities->isNotEmpty())
            <div class="flex flex-wrap gap-2">
                @foreach($line->sizeQuantities as $sq)
                    <div class="px-3 py-1.5 bg-slate-50 rounded-lg text-[11px] font-medium text-slate-600">{{ $sq->size->name ?? '-' }}: {{ $sq->quantity }} pcs</div>
                @endforeach
            </div>
            @endif
        </x-card>
        @endforeach
    </div>

    <x-card class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-medium text-slate-700">Riwayat Pembayaran</h3>
            @if($order->sisa_tagihan > 0)
                <x-button type="button" size="sm" variant="success" onclick="openModal('tambah-pembayaran')">
                    <i class="fas fa-plus"></i> Tambah Pembayaran
                </x-button>
            @endif
        </div>
        @if($order->payments->isEmpty())
            <x-empty-state title="Belum ada pembayaran" description="Catat DP atau pelunasan lewat tombol Tambah Pembayaran / Catat Pembayaran." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead><tr class="text-[10px] text-slate-400 uppercase"><th class="p-2 text-left">Tanggal</th><th class="p-2 text-left">Metode</th><th class="p-2 text-right">Jumlah</th><th class="p-2 text-left">Keterangan</th><th class="p-2 text-center">Bukti</th><th class="p-2 text-center w-12">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach($order->payments->sortByDesc('tanggal_bayar') as $p)
                        <tr>
                            <td class="p-2 font-medium text-slate-600">{{ $p->tanggal_bayar->format('d/m/Y') }}</td>
                            <td class="p-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-50 text-slate-500 text-[10px] font-medium uppercase">{{ $p->metode_bayar }}</span>
                            </td>
                            @bolehHarga<td class="p-2 text-right font-medium text-emerald-600">Rp {{ number_format($p->jumlah, 0, ',', '.') }}</td>@endbolehHarga
                            <td class="p-2 text-slate-400">{{ $p->keterangan ?? '-' }}</td>
                            <td class="p-2 text-center">
                                @if($p->bukti_path)
                                    <a href="{{ asset('storage/' . $p->bukti_path) }}" target="_blank" class="text-indigo-500 hover:text-indigo-700"><i class="fas fa-image"></i></a>
                                @else
                                    <span class="text-slate-500">-</span>
                                @endif
                            </td>
                            <td class="p-2 text-center">
                                <form id="delete-payment-form-{{ $p->id }}" action="{{ route('jersey.keuangan.bayar.destroy', $p->id) }}" method="POST" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="button" onclick="confirmHapusPembayaran({{ $p->id }})" class="text-slate-500 hover:text-rose-500" title="Hapus"><i class="far fa-trash-alt"></i></button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <x-card>
        <h3 class="text-sm font-medium text-slate-700 mb-4">Perbarui Data Order</h3>
        <form action="{{ route('jersey.orders.update', $order->id) }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @csrf @method('PUT') @versi($order)
            <x-form.group label="Status Order">
                <x-form.select name="status">
                    @foreach(['baru' => 'Baru', 'proses' => 'Proses', 'selesai' => 'Selesai', 'diambil' => 'Diambil'] as $val => $label)
                        <option value="{{ $val }}" @selected($order->status === $val)>{{ $label }}</option>
                    @endforeach
                </x-form.select>
            </x-form.group>
            <x-form.group label="Deadline">
                <x-form.input type="date" name="deadline" value="{{ $order->deadline?->format('Y-m-d') }}" />
            </x-form.group>
            <x-form.group label="Nama Tim (opsional)">
                <x-form.input type="text" name="team_name" value="{{ $order->team_name }}" />
            </x-form.group>
            <x-form.group label="Desainer (Operator Desain)">
                @if($desainerTerkunci)
                    <input type="hidden" name="id_desainer" value="{{ $desainerTerkunci }}">
                    <div class="w-full bg-slate-100 border border-slate-200 rounded-xl py-3 px-4 text-sm font-medium text-slate-700 flex items-center gap-2.5">
                        <i class="fas fa-user-check text-slate-400 text-xs shrink-0"></i>
                        <span class="truncate">{{ $desainerTerkunciNama }}</span>
                        <span class="ml-auto text-[11px] font-normal text-slate-400 shrink-0">otomatis (Anda)</span>
                    </div>
                @else
                    <select name="id_desainer" class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
                        <option value="">-- Tanpa Desainer --</option>
                        @foreach($desainerList as $ds)
                            <option value="{{ $ds->id_user }}" @selected((int) $order->id_desainer === (int) $ds->id_user)>{{ $ds->nama }}</option>
                        @endforeach
                    </select>
                @endif
            </x-form.group>
            <x-form.group label="File Siap Cetak">
                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="file_siap_cetak" value="1" @checked($order->file_siap_cetak) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    <span>Desain sudah siap cetak</span>
                </label>
            </x-form.group>
            @bolehHarga
            <x-form.group label="Diskon (Rp)">
                <input type="text" inputmode="numeric" name="discount" value="{{ number_format($order->discount, 0, ',', '.') }}" class="format-ribuan w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
            </x-form.group>
            <x-form.group label="Mode Diskon">
                <select id="mode-diskon" onchange="toggleModeDiskon()" class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
                    <option value="manual">Manual (Rp total)</option>
                    <option value="tier">Dari tier harga (potongan per pcs)</option>
                </select>
            </x-form.group>
            <div id="wrapper-diskon-tier" class="hidden md:col-span-3">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <x-form.group label="Potongan per pcs dari tier (Rp)">
                        <input type="text" id="potongan-per-pcs" inputmode="numeric" class="format-ribuan w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 " placeholder="mis. 20.000">
                    </x-form.group>
                    <div class="flex items-end">
                        <button type="button" onclick="terapkanDiskonDariTier()" class="w-full py-3 rounded-xl bg-indigo-50 text-indigo-600 text-xs font-medium uppercase tracking-widest hover:bg-indigo-100">Terapkan</button>
                    </div>
                </div>
                <p class="text-[10px] text-slate-400 mt-1">Diskon = potongan per pcs x total qty barang. Mis. tier 80.000, potongan 20.000, qty 10 -> diskon Rp 200.000.</p>
            </div>
            <input type="hidden" id="total-qty-order" value="{{ $order->lines->sum(fn($l) => $l->sizeQuantities->sum('quantity') ?: $l->qty) }}">
            <x-form.group label="Biaya Tambahan (Rp)">
                <input type="text" inputmode="numeric" name="additional_charge" value="{{ number_format($order->additional_charge, 0, ',', '.') }}" class="format-ribuan w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
            </x-form.group>
            @endbolehHarga
            <div class="md:col-span-3">
                <x-form.group label="Catatan">
                    <textarea name="notes" rows="2" class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">{{ $order->notes }}</textarea>
                </x-form.group>
            </div>
            <div class="md:col-span-3 flex justify-end">
                <x-button type="submit" id="btn-save-order">Simpan Perubahan</x-button>
            </div>
        </form>
        <script>
            (function () {
                const form = document.querySelector('form[action="{{ route('jersey.orders.update', $order->id) }}"]');
                if (!form) return;
                window.toggleModeDiskon = function () {
                    const m = document.getElementById('mode-diskon')?.value || 'manual';
                    const w = document.getElementById('wrapper-diskon-tier');
                    if (w) w.classList.toggle('hidden', m !== 'tier');
                    if (m === 'manual') {
                        const el = document.querySelector('input[name="discount"]');
                        if (el) el.focus();
                    } else {
                        const el = document.getElementById('potongan-per-pcs');
                        if (el) el.focus();
                    }
                };
                window.terapkanDiskonDariTier = function () {
                    const pot = (function (v) { return parseInt(String(v).replace(/\D/g, '')) || 0; })(document.getElementById('potongan-per-pcs')?.value || '0');
                    const qty = parseInt(document.getElementById('total-qty-order')?.value || '0') || 0;
                    const disc = pot * qty;
                    const el = form.querySelector('input[name="discount"]');
                    if (el) el.value = disc.toLocaleString('id-ID');
                };
                document.addEventListener('DOMContentLoaded', toggleModeDiskon);
            })();
                const awal = snapshot();
                form.addEventListener('submit', function (e) {
                    if (snapshot() === awal) {
                        e.preventDefault();
                        if (window.Swal) Swal.fire('Tidak ada perubahan', 'Data belum diubah - tidak ada yang perlu disimpan.', 'info');
                        return;
                    }
                    if (window.Swal) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Simpan perubahan?',
                            text: 'Data order akan diperbarui, termasuk status (status "Proses"/"Selesai" ikut memotong stok kain).',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Simpan',
                            cancelButtonText: 'Batal'
                        }).then(r => { if (r.isConfirmed) form.submit(); });
                    }
                });
            })();
        </script>
    </x-card>

    <x-modal id="tambah-susulan" title="Tambah Barang Susulan" maxWidth="4xl">
        <p class="text-xs text-slate-400 mb-4 -mt-2">Barang lama pada order ini <b class="text-slate-500">tidak akan berubah/terhapus</b> - cocok untuk kasus qty/jenis barang bertambah setelah order awal berjalan (mis. panitia bertambah, butuh jersey tambahan).</p>
        <form id="form-tambah-susulan" action="{{ route('jersey.orders.lines.susulan', $order->id) }}" method="POST">
            @csrf
            <div class="jersey-line-card bg-slate-50/60 rounded-2xl border border-slate-200 overflow-hidden mb-5" data-prefix="lines[0]">
                @include('jersey.orders._line-card-fields', ['idx' => 0, 'line' => null, 'productTypes' => $productTypes, 'categories' => $categories, 'materials' => $materials, 'sizes' => $sizes, 'sleeveTypes' => $sleeveTypes, 'collarTypes' => $collarTypes])
            </div>
            <div class="flex items-center justify-between gap-4 flex-wrap mb-4 bg-indigo-600 rounded-2xl px-6 py-4">
                <span class="text-[10px] font-medium text-indigo-200 uppercase tracking-widest">Perkiraan Harga Barang Susulan Ini</span>
                <span id="preview-total-susulan" class="text-xl font-medium text-white">Rp 0</span>
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="closeModal('tambah-susulan')" class="flex-1 py-3 rounded-xl bg-slate-100 text-slate-500 text-xs font-medium uppercase">Batal</button>
                <button type="submit" class="flex-[2] py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium uppercase">Simpan Barang Susulan</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="tambah-pembayaran" title="Catat Pembayaran" maxWidth="md">
        <form id="form-tambah-pembayaran" action="{{ route('jersey.keuangan.bayar.store', $order->id) }}" method="POST">
            @csrf
            <div class="mb-4 p-3 bg-rose-50 rounded-xl text-xs text-rose-600 font-medium">
                Sisa tagihan order ini: Rp {{ number_format($order->sisa_tagihan, 0, ',', '.') }}
            </div>
            <div class="grid grid-cols-2 gap-3 mb-4">
                <x-form.group label="Tanggal Bayar" dalam>
                    <x-form.input type="date" name="tanggal_bayar" value="{{ now()->format('Y-m-d') }}" required />
                </x-form.group>
                <x-form.group label="Jumlah (Rp)" dalam>
                    <input type="text" inputmode="numeric" name="jumlah_display" id="pembayaran-jumlah-display" required
                        class="format-ribuan w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 "
                        placeholder="0">
                    <input type="hidden" name="jumlah" id="pembayaran-jumlah">
                </x-form.group>
            </div>
            {{-- Hitungan langsung: sisa setelah pembayaran ini, atau kembalian bila uangnya lebih. --}}
            <div id="pembayaran-hitung" class="mb-4 flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-sm" data-sisa="{{ (float) $order->sisa_tagihan }}" aria-live="polite">
                <span id="pembayaran-hitung-label" class="text-slate-500">Sisa setelah pembayaran ini</span>
                <span id="pembayaran-hitung-nilai" class="font-medium text-slate-700">Rp {{ number_format($order->sisa_tagihan, 0, ',', '.') }}</span>
            </div>
            <x-form.group label="Metode Bayar" class="mb-4" dalam>
                <x-form.select name="metode_bayar">
                    @foreach(['Tunai', 'Transfer', 'QRIS', 'Lainnya'] as $m)
                        <option value="{{ $m }}">{{ $m }}</option>
                    @endforeach
                </x-form.select>
            </x-form.group>
            <div class="mb-4">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2">Bukti Bayar (opsional)</label>
                <div class="bukti-dropzone relative border-2 border-dashed border-slate-200 rounded-xl p-4 text-center cursor-pointer hover:border-indigo-300" tabindex="0" onclick="document.getElementById('bukti-file-input').click()">
                    <img loading="lazy" decoding="async" class="bukti-preview hidden mx-auto max-h-32 rounded-lg mb-2" 
                    <p class="bukti-placeholder text-[11px] text-slate-400"><i class="fas fa-receipt mb-1 block text-lg text-slate-500"></i>Klik, Tempel (Ctrl+V), atau Pilih File</p>
                    <input type="file" id="bukti-file-input" class="hidden" accept="image/*">
                </div>
                <input type="hidden" name="bukti_path" id="bukti-path">
                <p class="bukti-status text-[10px] mt-1"></p>
            </div>
            <x-form.group label="Keterangan (opsional)" class="mb-4" dalam>
                <input type="text" name="keterangan" placeholder="mis. Cicilan ke-2" class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
            </x-form.group>
            <div class="pt-2 flex gap-3">
                <button type="button" onclick="closeModal('tambah-pembayaran')" class="flex-1 py-3 rounded-xl bg-slate-100 text-slate-500 text-xs font-medium uppercase">Batal</button>
                <button type="submit" class="flex-1 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium uppercase">Simpan Pembayaran</button>
            </div>
        </form>
    </x-modal>
</div>

{{-- Template baris pemain kosong - dipakai tombol "+ Tambah Pemain" di
     dalam modal Tambah Barang Susulan (pola sama seperti create.blade.php). --}}
<template id="template_baris_pemain_jersey">
    @include('jersey.orders._player-row-fields', ['prefix' => '__PREFIX__', 'itemIdx' => '__ITEM__', 'item' => null, 'sizes' => $sizes, 'sleeveTypes' => $sleeveTypes, 'collarTypes' => $collarTypes])
</template>

@push('scripts')
@include('jersey.orders._size-helper')
{{-- Import CSV Daftar Pemain (modal susulan): parser + validasi master (sekali per halaman). --}}
@include('jersey.orders._csv-pemain-tools')
<script>
    function togglePrintMenuRingkasan(event) {
        event.stopPropagation();
        const menu = document.getElementById('print-menu-ringkasan');
        menu.classList.toggle('hidden');
    }
    document.addEventListener('click', () => document.getElementById('print-menu-ringkasan')?.classList.add('hidden'));

    function confirmHapusOrderJersey() {
        Swal.fire({
            title: 'Hapus / batalkan Pesanan Jersey?',
            html: 'Order <b>{{ $order->order_number }}</b> beserta seluruh barang & rinciannya akan dihapus. Jika order sudah diakui sebagai pendapatan atau sudah ada pembayaran, ini hanya MENGAJUKAN pembatalan ke Administrator (jurnal dibalik setelah disetujui).',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            confirmButtonText: 'Ya, Lanjutkan',
            cancelButtonText: 'Batal',
            customClass: { confirmButton: 'rounded-lg px-6 py-2.5', cancelButton: 'rounded-lg px-6 py-2.5 text-slate-600' },
        }).then((result) => {
            if (result.isConfirmed) document.getElementById('delete-form-jersey-order').submit();
        });
    }

    function confirmHapusPembayaran(id) {
        Swal.fire({
            title: 'Ajukan void pembayaran?',
            html: 'Pembayaran baru di-void dan jurnalnya dibalik setelah disetujui Administrator di Alur Persetujuan.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            confirmButtonText: 'Ya, Lanjutkan',
            cancelButtonText: 'Batal',
            customClass: { confirmButton: 'rounded-lg px-6 py-2.5', cancelButton: 'rounded-lg px-6 py-2.5 text-slate-600' },
        }).then((result) => {
            if (result.isConfirmed) document.getElementById(`delete-payment-form-${id}`).submit();
        });
    }

    // ---- Modal Tambah Pembayaran: bukti bayar via klik-pilih-file ATAU
    // tempel (Ctrl+V). Paste dipasang GLOBAL di document (bukan di elemen
    // dropzone) dengan alasan sama seperti upload thumbnail: klik dropzone
    // membuka dialog pilih file OS lewat <input type=file>, dan fokus
    // browser tidak kembali dengan andal ke dropzone setelah dialog itu
    // ditutup/dibatalkan, sehingga listener 'paste' berbasis fokus tidak
    // pernah kepicu. ----
    document.addEventListener('DOMContentLoaded', () => {
        const dropzone = document.querySelector('.bukti-dropzone');
        if (!dropzone) return;
        document.getElementById('bukti-file-input').addEventListener('change', (e) => {
            if (e.target.files && e.target.files[0]) prosesUploadBukti(e.target.files[0]);
        });
    });
    document.addEventListener('paste', (e) => {
        const dropzone = document.querySelector('.bukti-dropzone');
        if (!dropzone || document.getElementById('modal-tambah-pembayaran')?.classList.contains('hidden')) return;
        const items = (e.clipboardData || window.clipboardData)?.items;
        if (!items) return;
        for (const item of items) {
            if (item.type.startsWith('image/')) {
                prosesUploadBukti(item.getAsFile());
                e.preventDefault();
                return;
            }
        }
    });

    async function prosesUploadBukti(file) {
        const statusEl = document.querySelector('.bukti-status');
        statusEl.textContent = 'Memampatkan gambar...';
        statusEl.classList.remove('text-rose-500');
        // Target & dimensi lebih besar daripada thumbnail desain (200KB,
        // sampai 1200px) - ini bukti transfer/struk, nominal & tanggalnya
        // harus tetap kebaca jelas, tidak boleh dikompres seagresif
        // thumbnail yang murni acuan visual.
        let fileUpload = file;
        try {
            const compressed = await compressImage(file, 200 * 1024, [1200, 900, 700, 500, 350]);
            fileUpload = new File([compressed], file.name.replace(/\.[^./]+$/, '') + '.jpg', { type: 'image/jpeg' });
        } catch (e) { /* gagal kompres (jarang) - lanjut pakai file asli */ }
        statusEl.textContent = 'Mengunggah...';
        const formData = new FormData();
        formData.append('bukti', fileUpload);
        formData.append('_token', '{{ csrf_token() }}');
        try {
            const res = await fetch('{{ route("jersey.keuangan.upload-bukti") }}', { method: 'POST', headers: { 'Accept': 'application/json' }, body: formData });
            const data = await res.json();
            if (data.status === 'success') {
                document.getElementById('bukti-path').value = data.path;
                const img = document.querySelector('.bukti-preview');
                img.src = data.url;
                img.classList.remove('hidden');
                document.querySelector('.bukti-placeholder').classList.add('hidden');
                statusEl.textContent = 'Bukti siap. Klik kotak untuk mengganti.';
            } else {
                statusEl.textContent = data.message || 'Gagal mengunggah, coba lagi.';
                statusEl.classList.add('text-rose-500');
            }
        } catch (e) {
            statusEl.textContent = 'Gagal mengunggah, coba lagi.';
            statusEl.classList.add('text-rose-500');
        }
    }

    // Hitungan langsung pembayaran: kurang, pas, atau kembalian.
    (function () {
        const kotak = document.getElementById('pembayaran-hitung');
        const label = document.getElementById('pembayaran-hitung-label');
        const nilai = document.getElementById('pembayaran-hitung-nilai');
        const isian = document.getElementById('pembayaran-jumlah-display');
        const metode = document.querySelector('#form-tambah-pembayaran [name="metode_bayar"]');
        const sisa = parseFloat(kotak.dataset.sisa) || 0;
        const rp = (n) => 'Rp ' + Math.round(n).toLocaleString('id-ID');
        function hitung() {
            const bayar = cleanRupiah(isian.value) || 0;
            const selisih = bayar - sisa;
            nilai.className = 'font-medium';
            if (selisih > 0 && metode.value === 'Tunai') {
                label.textContent = 'Kembalian untuk pelanggan';
                nilai.textContent = rp(selisih);
                nilai.classList.add('text-emerald-600');
            } else if (selisih > 0) {
                label.textContent = 'Melebihi sisa tagihan (non-tunai tidak ada kembalian)';
                nilai.textContent = rp(selisih);
                nilai.classList.add('text-rose-600');
            } else {
                label.textContent = selisih === 0 ? 'Lunas, tanpa kembalian' : 'Sisa setelah pembayaran ini';
                nilai.textContent = rp(Math.abs(selisih));
                nilai.classList.add(selisih === 0 ? 'text-emerald-600' : 'text-slate-700');
            }
        }
        isian.addEventListener('input', hitung);
        metode.addEventListener('change', hitung);
    })();

    document.getElementById('form-tambah-pembayaran').addEventListener('submit', (e) => {
        document.getElementById('pembayaran-jumlah').value = cleanRupiah(document.getElementById('pembayaran-jumlah-display').value);
    });

    // ---- Modal Tambah Barang Susulan - dukungan mode order (toggle),
    // daftar pemain, & upload thumbnail sama persis dengan Pesanan Jersey
    // Baru / Kelola Barang (satu kartu barang saja, idx tetap 0). ----
    // ---- Pengunci optimis perpindahan mode order (sama dengan
    // create.blade.php): input milik mode lama DIBUANG saat mode pindah,
    // bukan cuma disembunyikan - browser tetap mengirim input tersembunyi,
    // jadi kalau dibiarkan qty/pemain lama ikut terhitung ke harga di
    // server (penumpukan perkiraan harga). ----
    async function toggleLineType(radio) {
        const card = radio.closest('.jersey-line-card');
        const tipe = radio.value;
        const modeLama = card.dataset.modeAktif
            || [...card.querySelectorAll('input[name$="[order_type]"]')].find(r => r.closest('label')?.classList.contains('border-indigo-500'))?.value
            || 'quantity_only';

        const adaQty = [...card.querySelectorAll('.input-qty-ukuran')].some(i => parseInt(i.value) > 0);
        const adaPemain = [...card.querySelectorAll('.baris-pemain')].some(r => r.querySelector('[name*="player_name"]').value.trim() !== '');

        const pemainAkanDibuang = tipe === 'quantity_only' && adaPemain;
        const qtyAkanDibuang = tipe === 'player_list' && adaQty;

        if (pemainAkanDibuang || qtyAkanDibuang) {
            const konfirmasi = await Swal.fire({
                title: 'Ganti Mode Order?',
                html: pemainAkanDibuang
                    ? 'Daftar pemain yang sudah diisi akan <b>dikosongkan</b> - mode <b>Qty per Ukuran</b> tidak memakai daftar pemain. Kalau dibiarkan, pemain itu tetap terkirim &amp; ikut terhitung ke harga.'
                    : 'Qty per ukuran yang sudah diisi akan <b>dikosongkan</b> - mode <b>Daftar Pemain</b> tidak memakai qty per ukuran. Kalau dibiarkan, qty itu tetap terkirim &amp; ikut terhitung ke harga (penumpukan harga).',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#93481A',
                confirmButtonText: 'Ya, Kosongkan & Ganti Mode',
                cancelButtonText: 'Batal',
                customClass: { confirmButton: 'rounded-lg px-6 py-2.5', cancelButton: 'rounded-lg px-6 py-2.5 text-slate-600' },
            });
            if (!konfirmasi.isConfirmed) {
                card.querySelectorAll('input[name$="[order_type]"]').forEach(r => { r.checked = r.value === modeLama; });
                sinkronkanTampilanMode(card);
                return;
            }
            if (pemainAkanDibuang) card.querySelectorAll('.baris-pemain').forEach(tr => tr.remove());
            if (qtyAkanDibuang) card.querySelectorAll('.input-qty-ukuran').forEach(i => { i.value = 0; });
            if (typeof hitungUlangQtyUkuran === 'function') hitungUlangQtyUkuran();
        }

        // Mode Daftar Pemain: qty per ukuran TIDAK dipakai sama sekali, jadi
        // inputnya dinonaktifkan - bukan cuma disembunyikan. Wrapper `hidden`
        // hanya menyembunyikan tampilan; input di dalamnya tetap terkirim ke
        // server, dan itulah sumber bug qty 35 -> 70 (35 pemain + sisa
        // size_quantities 35 dijumlahkan). Disable = tidak terkirim sama sekali.
        // Mode Campuran tetap memakai keduanya, jadi tidak dinonaktifkan.
        const wrapperQty = card.querySelector('.wrapper-qty-ukuran');
        if (wrapperQty) {
            const bukanModePemain = tipe === 'player_list';
            wrapperQty.classList.toggle('hidden', bukanModePemain);
            wrapperQty.querySelectorAll('input, select').forEach(el => {
                el.disabled = bukanModePemain;
            });
        }
        card.querySelector('.wrapper-daftar-pemain')?.classList.toggle('hidden', tipe === 'quantity_only');
        if (tipe !== 'quantity_only' && card.querySelectorAll('.tbody-pemain .baris-pemain').length === 0) {
            tambahBarisPemain(card.querySelector('.wrapper-daftar-pemain button'));
        }
        sinkronkanTampilanMode(card);
        perbaruiVisibilitasLenganKerah(card);
        if (typeof jadwalkanPreviewHarga === 'function') jadwalkanPreviewHarga();
        if (typeof jadwalkanPreviewHargaSusulan === 'function') jadwalkanPreviewHargaSusulan();
    }

    function sinkronkanTampilanMode(card) {
        const terpilih = card.querySelector('input[name$="[order_type]"]:checked');
        if (terpilih) card.dataset.modeAktif = terpilih.value;
        card.querySelectorAll('.line-type-option').forEach(label => {
            const checked = label.querySelector('input').checked;
            label.classList.toggle('border-indigo-500', checked);
            label.classList.toggle('bg-indigo-50', checked);
            label.classList.toggle('text-indigo-700', checked);
            label.classList.toggle('border-slate-100', !checked);
            label.classList.toggle('text-slate-400', !checked);
        });
    }

    function tambahBarisPemain(btn) {
        const card = btn.closest('.jersey-line-card');
        const tbody = card.querySelector('.tbody-pemain');
        const itemIdx = tbody.querySelectorAll('.baris-pemain').length;
        const html = document.getElementById('template_baris_pemain_jersey').innerHTML
            .replaceAll('__PREFIX__', 'lines[0]')
            .replaceAll('__ITEM__', itemIdx);
        tbody.insertAdjacentHTML('beforeend', html);
        perbaruiVisibilitasLenganKerah(card);
        jadwalkanPreviewHargaSusulan();
    }

    // ---- Perkiraan harga LIVE untuk modal Tambah Barang Susulan - pola
    // sama persis dengan create.blade.php/edit-lines.blade.php, cuma target
    // form & elemen totalnya beda (satu barang saja, idx tetap 0). ----
    let timerPreviewHargaSusulan = null;
    function jadwalkanPreviewHargaSusulan() {
        clearTimeout(timerPreviewHargaSusulan);
        timerPreviewHargaSusulan = setTimeout(jalankanPreviewHargaSusulan, 400);
    }

    async function jalankanPreviewHargaSusulan() {
        const form = document.getElementById('form-tambah-susulan');
        if (!form) return;
        let data;
        try {
            const fd = new FormData(form);
            fd.delete('_method');
            const res = await fetch('{{ route("jersey.orders.preview-harga") }}', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: fd,
            });
            data = await res.json();
        } catch (e) {
            return;
        }

        const card = form.querySelector('.jersey-line-card');
        const info = data.lines?.['0'];
        const nilaiEl = card?.querySelector('.line-price-preview-value');
        const detailEl = card?.querySelector('.line-price-preview-detail');
        if (nilaiEl) {
            if (!info) {
                nilaiEl.textContent = 'Rp 0';
                if (detailEl) detailEl.textContent = 'Isi qty & pilihan barang untuk lihat perkiraan harga.';
            } else {
                // Finishing jahit ikut dijumlahkan - rumusnya sama dengan saat
                // susulan disimpan (simpanFinishingBaris).
                nilaiEl.textContent = 'Rp ' + Math.round(info.subtotal + (info.finishing_biaya || 0)).toLocaleString('id-ID');
                // Teks detail hanya menyimpan CATATAN - angka rinciannya di
                // daftar line-price-preview-list (satu baris per komponen harga).
                const catatan = [];
                if (!info.ada_tier) catatan.push('belum ada tier harga yang cocok');
                if (info.pemain_tanpa_ukuran > 0) catatan.push(`${info.pemain_tanpa_ukuran} pemain belum pilih ukuran (dihitung harga dewasa)`);
                if (info.finishing_tidak_lengkap) catatan.push('ukuran barang jahit belum lengkap');
                if (detailEl) detailEl.textContent = catatan.join(', ') || (info.tier_label ? `Tier ${info.tier_label}` : '');
            }
        }
        // Daftar rincian harga pada modal/Tambah Susulan - pola sama dengan
        // halaman create/edit-lines: createElement/textContent, bukan string HTML.
        const listEl = card?.querySelector('.line-price-preview-list');
        if (listEl) {
            listEl.innerHTML = '';
            const barisRincian = Array.isArray(info?.rincian) ? info.rincian : [];
            for (const r of barisRincian) {
                if (r?.amount == null) continue;
                const li = document.createElement('li');
                li.className = 'flex items-center justify-between gap-3 py-1';
                const kiri = document.createElement('span');
                kiri.className = 'min-w-0';
                kiri.textContent = r.label;
                const kanan = document.createElement('span');
                kanan.className = 'font-medium tabular-nums shrink-0';
                const nominal = Math.round(r.amount);
                kanan.textContent = (nominal < 0 ? '−Rp ' : 'Rp ') + Math.abs(nominal).toLocaleString('id-ID');
                li.append(kiri, kanan);
                listEl.appendChild(li);
            }
            // Baris rincian finishing jahit - nominal dihitung server supaya sama
            // persis dengan yang tersimpan saat susulan disubmit.
            const finNominal = info ? Math.round(info.finishing_biaya || 0) : 0;
            if (finNominal !== 0) {
                const li = document.createElement('li');
                li.className = 'flex items-center justify-between gap-3 py-1';
                const kiri = document.createElement('span');
                kiri.className = 'min-w-0';
                kiri.textContent = 'Finishing jahit';
                const kanan = document.createElement('span');
                kanan.className = 'font-medium tabular-nums shrink-0';
                kanan.textContent = 'Rp ' + finNominal.toLocaleString('id-ID');
                li.append(kiri, kanan);
                listEl.appendChild(li);
            }
            listEl.classList.toggle('hidden', barisRincian.length === 0 || finNominal !== 0);
        }

        const totalEl = document.getElementById('preview-total-susulan');
        if (totalEl) totalEl.textContent = 'Rp ' + Math.round(data.total_order || 0).toLocaleString('id-ID');
    }

    document.getElementById('form-tambah-susulan').addEventListener('input', (e) => {
        if (e.target.closest('.jersey-line-card')) jadwalkanPreviewHargaSusulan();
    });
    document.getElementById('form-tambah-susulan').addEventListener('change', (e) => {
        if (e.target.closest('.jersey-line-card')) jadwalkanPreviewHargaSusulan();
    });

    // Lengan/Kerah default (level barang) tidak relevan untuk mode Daftar
    // Pemain (selalu pakai pilihan tiap pemain). Dropdown Lengan/Kerah
    // TIDAK lagi disembunyikan untuk tipe produk tanpa lengan/kerah (mis.
    // Celana) - master "Tanpa Lengan"/"Tanpa Kerah" WAJIB dipilih eksplisit
    // supaya selalu tercatat di database, sama seperti di create.blade.php.
    function perbaruiVisibilitasLenganKerah(source) {
        const card = source.closest ? source.closest('.jersey-line-card') : source;
        if (!card) return;
        const orderType = card.querySelector('input[name$="[order_type]"]:checked')?.value || 'quantity_only';

        const selectProduk = card.querySelector('select[name$="[jersey_product_type_id]"]');
        const opsiProduk = selectProduk?.selectedOptions?.[0];
        const hasSleeveCollar = opsiProduk?.dataset?.hasSleeveCollar !== '0';

        card.classList.toggle('tanpa-lengan-kerah', !hasSleeveCollar);

        const tampilkanDefault = orderType !== 'player_list' && hasSleeveCollar;
        card.querySelectorAll('.wrapper-lengan-kerah-default').forEach(el => {
            el.classList.toggle('hidden', !tampilkanDefault);
            el.querySelectorAll('select, input').forEach(f => {
                f.required = tampilkanDefault;
                if (!hasSleeveCollar) {
                    f.disabled = true;
                    f.value = '';
                } else {
                    f.disabled = false;
                }
            });
        });
        card.querySelectorAll('.baris-pemain').forEach(row => {
            row.querySelectorAll('select[name*="[jersey_sleeve_type_id]"], select[name*="[jersey_collar_type_id]"]').forEach(f => {
                f.required = hasSleeveCollar;
                f.disabled = !hasSleeveCollar;
                if (!hasSleeveCollar) f.value = '';
            });
        });
    }

    // CATATAN PENTING: paste TIDAK dipasang langsung ke elemen dropzone
    // (dulu begitu, dan gagal). Kotak dropzone punya onclick yang memicu
    // .click() pada <input type=file> tersembunyi di dalamnya untuk buka
    // dialog pilih file OS - begitu dialog itu terbuka lalu ditutup/
    // dibatalkan, fokus browser TIDAK kembali ke kotak dropzone, jadi
    // listener 'paste' yang butuh fokus pada dropzone itu sendiri tidak
    // pernah kepicu - persis gejala yang dilaporkan: paste gambar dari
    // WhatsApp tidak berfungsi. Solusi: satu listener 'paste' GLOBAL di
    // document, mengarah ke kartu yang terakhir disentuh mouse (hover/klik)
    // pada dropzone-nya - tidak bergantung pada fokus DOM yang rapuh
    // setelah dialog file native.
    let kartuAktifUntukPasteThumbnail = null;
    function pasangPasteThumbnail(card) {
        const dropzone = card.querySelector('.thumbnail-dropzone');
        dropzone.addEventListener('mouseenter', () => { kartuAktifUntukPasteThumbnail = card; });
        dropzone.addEventListener('click', () => { kartuAktifUntukPasteThumbnail = card; });
    }
    document.addEventListener('paste', (e) => {
        if (!kartuAktifUntukPasteThumbnail || !document.body.contains(kartuAktifUntukPasteThumbnail)) return;
        const items = (e.clipboardData || window.clipboardData)?.items;
        if (!items) return;
        for (const item of items) {
            if (item.type.startsWith('image/')) {
                prosesUploadThumbnailBarang(kartuAktifUntukPasteThumbnail, item.getAsFile());
                e.preventDefault();
                return;
            }
        }
    });

    async function uploadThumbnailLine(input) {
        if (!input.files || !input.files[0]) return;
        await prosesUploadThumbnailBarang(input.closest('.jersey-line-card'), input.files[0]);
    }

    async function prosesUploadThumbnailBarang(card, file) {
        const statusEl = card.querySelector('.thumbnail-status');
        statusEl.textContent = 'Memampatkan gambar...';
        statusEl.classList.remove('text-rose-500');
        // Dikompres di browser dulu - lihat catatan di
        // jersey/orders/create.blade.php::prosesUploadThumbnail(). PDF
        // dirender jadi gambar; format berkas desain lain (CDR, AI, EPS)
        // dikirim apa adanya.
        let fileUpload = file;
        try {
            const prepared = await siapkanBerkasUntukUnggah(file, file.name);
            fileUpload = prepared.file;
        } catch (e) { /* gagal kompres (jarang) - lanjut pakai file asli */ }
        statusEl.textContent = 'Mengunggah...';
        const formData = new FormData();
        formData.append('thumbnail', fileUpload);
        formData.append('_token', '{{ csrf_token() }}');
        try {
            const res = await fetch('{{ route("jersey.orders.upload-thumbnail") }}', { method: 'POST', headers: { 'Accept': 'application/json' }, body: formData });
            const data = await res.json();
            if (data.status === 'success') {
                card.querySelector('.thumbnail-path').value = data.path;
                const img = card.querySelector('.thumbnail-preview');
                if (data.bisa_ditampilkan) {
                    img.src = data.url;
                    img.classList.remove('hidden');
                    card.querySelectorAll('.thumbnail-placeholder').forEach(el => el.classList.add('hidden'));
                    statusEl.textContent = 'Thumbnail siap. Klik kotak untuk mengganti.';
                } else {
                    // Berkas desain non-gambar: kotak pratinjau menampilkan
                    // ikon, bukan gambar rusak.
                    img.removeAttribute('src');
                    img.classList.add('hidden');
                    const ext = (data.nama_asli || '').split('.').pop() || '';
                    statusEl.textContent = 'Berkas desain siap' + (ext ? ' (' + ext.toUpperCase() + ')' : '') + '. File ada di server, dibuka tim produksi.';
                }
            } else {
                // Pesan galat validasi ada di errors.thumbnail[0] (mis.
                // "Ukuran file terlalu besar (maksimal 10 MB).") - data.message
                // cuma kalimat generik bahasa Inggris dari Laravel.
                const pesan = (data.errors && data.errors.thumbnail && data.errors.thumbnail[0]) || data.message || data.msg || 'Gagal mengunggah, coba lagi.';
                statusEl.textContent = pesan;
                statusEl.classList.add('text-rose-500');
                Swal.fire('Berkas desain belum terunggah', pesan, 'error');
            }
        } catch (e) {
            // Respons bukan JSON (res.json() gagal) - hampir selalu karena body
            // POST melewati post_max_size PHP (40 MB): PHP membuang seluruh
            // unggahan dan menjawab halaman galat, bukan JSON.
            if (fileUpload.size > 30 * 1024 * 1024) {
                statusEl.textContent = 'Ukuran berkas ' + Math.round(fileUpload.size / (1024 * 1024)) + ' MB melampaui batas server (39 MB). Kecilkan dokumen, lalu unggah lagi.';
                Swal.fire('Berkas terlalu besar', statusEl.textContent, 'error');
            } else {
                statusEl.textContent = 'Gagal mengunggah, coba lagi.';
                Swal.fire('Gagal mengunggah', 'Periksa jaringan lalu coba lagi.', 'error');
            }
            statusEl.classList.add('text-rose-500');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const card = document.querySelector('#modal-tambah-susulan .jersey-line-card');
        if (card) {
            pasangPasteThumbnail(card);
            perbaruiVisibilitasLenganKerah(card);
        }
    });

    // Penanda submit sedang berjalan untuk modal Tambah Barang Susulan. Handler
    // di bawah mengambil alih submit, jadi penaga global di layouts/app.blade.php
    // tidak berlaku - dan penaga server tidak ada di jalur ini karena form
    // disubmit lewat form.submit() tanpa submit_id baru setiap klik.
    let sedangSimpanSusulan = false;

    document.getElementById('form-tambah-susulan').addEventListener('submit', async function (e) {
        e.preventDefault();

        if (sedangSimpanSusulan) return;

        document.querySelectorAll('#modal-tambah-susulan .baris-pemain').forEach(row => {
            if (row.querySelector('[name*="player_name"]').value.trim() === '') row.remove();
        });

        const card = document.querySelector('#modal-tambah-susulan .jersey-line-card');
        if (!card || card.querySelector('.thumbnail-path').value.trim() === '') {
            Swal.fire('Thumbnail Belum Diisi', 'Barang susulan wajib ada thumbnail desain.', 'warning');
            return;
        }

        // Audit kelengkapan (sama seperti create.blade.php): dropdown wajib
        // + ukuran pemain + qty minimal - blokir simpan dengan daftar
        // kekurangan & tandai field kosong.
        card.querySelectorAll('input, select').forEach(el => el.classList.remove('ring-2', 'ring-rose-400', 'bg-rose-50/40'));
        const kurang = [];
        const namaSusulan = card.querySelector('[name*="[nama_barang]"]').value.trim() || 'Barang susulan';

        const wajibPilih = [
            ['[name*="[jersey_product_type_id]"]', 'Tipe Produk'],
            ['[name*="[jersey_category_id]"]', 'Kategori'],
            ['[name*="[jersey_material_id]"]', 'Bahan'],
        ];
        for (const [selector, label] of wajibPilih) {
            const el = card.querySelector(selector);
            if (el && !el.value) {
                kurang.push(`${namaSusulan}: ${label} belum dipilih`);
                el.classList.add('ring-2', 'ring-rose-400', 'bg-rose-50/40');
            }
        }

        const pemainSusulan = [...card.querySelectorAll('.baris-pemain')].filter(r => r.querySelector('[name*="player_name"]').value.trim() !== '');
        const pemainTanpaUkuran = pemainSusulan.filter(r => !r.querySelector('[name*="[size]"]').value);
        pemainTanpaUkuran.forEach(r => r.querySelector('[name*="[size]"]').classList.add('ring-2', 'ring-rose-400', 'bg-rose-50/40'));
        if (pemainSusulan.length > 0 && pemainTanpaUkuran.length > 0) {
            kurang.push(`${namaSusulan}: ${pemainTanpaUkuran.length} pemain belum memilih Ukuran`);
        }

        if (kurang.length > 0) {
            const daftar = kurang.map(k => `<li>${escHtml(k)}</li>`).join('');
            Swal.fire({
                title: 'Lengkapi Data Pesanan Lebih Dulu',
                html: `<ul style="text-align:left; font-size:12.5px; padding-left:1.2em;">${daftar}</ul>`,
                icon: 'warning',
                confirmButtonText: 'Ya, Saya Lengkapi',
                customClass: { confirmButton: 'rounded-lg px-6 py-2.5' },
            });
            return;
        }

        let peringatan = [];
        try {
            const res = await fetch('{{ route("jersey.orders.cek-kelengkapan") }}', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: new FormData(this),
            });
            const data = await res.json();
            peringatan = data.peringatan || [];
        } catch (err) {
            peringatan = [];
        }

        if (peringatan.length > 0) {
            const daftar = peringatan.map(p => `<li>${escHtml(p)}</li>`).join('');
            const konfirmasi = await Swal.fire({
                title: 'Ada Komponen Belum Lengkap',
                html: `<ul style="text-align:left; font-size:12.5px; padding-left:1.2em;">${daftar}</ul>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#93481A',
                confirmButtonText: 'Ya, Simpan Sekarang',
                cancelButtonText: 'Batal, Saya Cek Lagi',
                customClass: { confirmButton: 'rounded-lg px-6 py-2.5', cancelButton: 'rounded-lg px-6 py-2.5 text-slate-600' },
            });
            if (!konfirmasi.isConfirmed) return;
        }

        // ---- Penaga kirim ganda + loading state ----
        // Disetel tepat sebelum submit supaya tidak pernah tertahan kalau ada
        // validasi di atas yang menolak.
        sedangSimpanSusulan = true;
        const btnSimpan = this.querySelector('button[type="submit"]');
        if (btnSimpan) {
            btnSimpan.disabled = true;
            btnSimpan.classList.add('opacity-60', 'cursor-not-allowed');
            btnSimpan.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Menyimpan...';
        }

        // Pagar pengaman: kalau navigasi tidak terjadi, tombol jangan terkunci.
        setTimeout(() => {
            sedangSimpanSusulan = false;
            if (!btnSimpan) return;
            btnSimpan.disabled = false;
            btnSimpan.classList.remove('opacity-60', 'cursor-not-allowed');
            btnSimpan.innerHTML = 'Simpan Barang Susulan';
        }, 20000);

        this.submit();
    });
</script>
@endpush
@endsection
