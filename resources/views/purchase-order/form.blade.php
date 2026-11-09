@extends('layouts.app')

@section('content')
@php
    $editable = !$po || $po->status === 'Draft';
    $idPr = old('id_purchase_request', $po->id_purchase_request ?? ($prefill['id_purchase_request'] ?? null));
    $rows = old('items', $po
        ? $po->details->map(fn ($d) => ['id_bahan' => $d->id_bahan, 'qty' => $d->qty, 'harga' => $d->harga, 'catatan' => $d->catatan, 'qty_diterima' => $d->qty_diterima])->all()
        : ($prefill['items'] ?? [['id_bahan' => '', 'qty' => '', 'harga' => '', 'catatan' => '']]));
    $nomorPr = $po?->purchaseRequest?->nomor ?? ($prefill['nomor_pr'] ?? null);
    $labelPr = $nomorPr ? "Dari {$nomorPr} - " : '';
    $variantMap = ['Draft' => 'info', 'Dipesan' => 'warning', 'Diterima Sebagian' => 'warning', 'Selesai' => 'success', 'Dibatalkan' => 'danger'];
    $statusInfo = [
        'Dipesan'           => 'Sudah diterbitkan ke supplier - tunggu barang datang, lalu catat kedatangannya di bawah.',
        'Diterima Sebagian' => 'Sebagian barang sudah diterima - catat sisa kedatangan di bawah sampai lengkap.',
        'Selesai'           => 'Semua item sudah diterima.',
        'Dibatalkan'        => 'PO ini dibatalkan - buat PO baru bila masih diperlukan.',
    ][$po->status ?? ''] ?? null;
@endphp
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1200px] mx-auto">

    <x-page-header title="{{ $po ? 'Pesanan Pembelian ' . $po->nomor : 'Pesanan Pembelian Baru' }}">
        <x-slot:subtitle>
            <i class="fas fa-file-invoice text-amber-400"></i>
            {{ $labelPr }}
            {{ $editable ? 'Draft - bisa diubah sampai diterbitkan ke supplier' : 'Hanya-baca - sudah diterbitkan' }}
        </x-slot:subtitle>
        @if($po)
        <x-slot:actions>
            <x-badge :status="$po->status" :variant="$variantMap[$po->status] ?? 'info'" />
        </x-slot:actions>
        @endif
    </x-page-header>

    @if($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-100 bg-rose-50/80 p-4 flex items-start gap-3">
            <span class="w-8 h-8 shrink-0 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center"><i class="fas fa-exclamation-triangle text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-sm font-medium text-rose-700">Form belum bisa disimpan</p>
                <ul class="mt-1 text-[13px] font-medium text-rose-600 space-y-0.5">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        </div>
    @endif

    @if($po && !$editable && $statusInfo)
        <div class="mb-6 p-4 rounded-xl bg-amber-50 border border-amber-100 text-amber-700 text-sm font-medium flex items-start gap-3">
            <i class="fas fa-lock mt-0.5"></i>
            <span>Purchase Order ini hanya-baca karena berstatus <b>{{ $po->status }}</b>. {{ $statusInfo }}</span>
        </div>
    @endif

    <form id="form-po" method="POST" action="{{ $po ? route('purchase-order.update', $po->id) : route('purchase-order.store') }}">
        @csrf
        @if($po) @method('PATCH') @versi($po) @endif
        @if($idPr && !$po)<input type="hidden" name="id_purchase_request" value="{{ $idPr }}">@endif

        <x-card class="mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <x-form.group dalam>
                    <div class="flex items-center justify-between mb-2 ml-2 mr-1">
                        <label class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Pemasok</label>
                        @if($editable)
                        <button type="button" onclick="openModal('supplier-baru')" class="text-indigo-600 hover:text-indigo-700 font-medium text-[11px] flex items-center gap-1">
                            <i class="fas fa-plus text-[9px]"></i> Baru
                        </button>
                        @endif
                    </div>
                    <x-form.select name="id_supplier" id="id_supplier" required :disabled="! $editable">
                        <option value="">-- Pilih Supplier --</option>
                        @foreach($supplierList as $s)
                            {{-- PO dari PR: supplier otomatis dari histori pembelian bahan-bahan PR itu (masih bisa diganti). --}}
                            <option value="{{ $s->id_supplier }}" {{ old('id_supplier', $po->id_supplier ?? ($prefill['id_supplier'] ?? '')) == $s->id_supplier ? 'selected' : '' }}>{{ $s->nama_supplier }}</option>
                        @endforeach
                    </x-form.select>
                </x-form.group>
                <x-form.group label="Tanggal PO" name="tanggal" dalam>
                    {{-- Input polos (bukan x-form.input): echo kondisional di dalam
                         tag komponen terbukti membuat input tidak dirender. --}}
                    <input type="date" name="tanggal" max="{{ now()->toDateString() }}" value="{{ old('tanggal', $po ? $po->tanggal->toDateString() : now()->toDateString()) }}" required
                        class="w-full bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 shadow-inner"
                        {{ $editable ? '' : 'readonly' }} />
                </x-form.group>
                <x-form.group label="Estimasi Kedatangan" name="tgl_pengiriman_estimasi" dalam>
                    <input type="date" name="tgl_pengiriman_estimasi" value="{{ old('tgl_pengiriman_estimasi', $po?->tgl_pengiriman_estimasi?->toDateString()) }}"
                        class="w-full bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 shadow-inner"
                        {{ $editable ? '' : 'readonly' }} />
                </x-form.group>
                <x-form.group label="Termin Pembayaran" name="termin_pembayaran" dalam>
                    <input type="text" name="termin_pembayaran" value="{{ old('termin_pembayaran', $po->termin_pembayaran ?? '') }}" placeholder="Mis. Net 30 / Tunai"
                        class="w-full bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 shadow-inner"
                        {{ $editable ? '' : 'readonly' }} />
                </x-form.group>
            </div>
        </x-card>

        <x-table>
            <thead>
                <tr>
                    <th class="px-6 py-4 text-left">Bahan</th>
                    <th class="px-4 py-4 text-right w-32">Qty</th>
                    <th class="px-4 py-4 text-right w-44">Harga Satuan</th>
                    <th class="px-4 py-4 text-right w-44">Subtotal</th>
                    <th class="px-4 py-4 text-left w-56">Catatan</th>
                    @if($po && !$editable)<th class="px-4 py-4 text-right w-28">Diterima</th>@endif
                    @if($editable)<th class="px-4 py-4 w-12"></th>@endif
                </tr>
            </thead>
            <tbody id="item-rows" class="divide-y divide-slate-50">
                @foreach($rows as $i => $r)
                <tr data-row>
                    <td class="px-6 py-3">
                        <select name="items[{{ $i }}][id_bahan]" class="w-full rounded-xl border-slate-200 text-sm p-2" required {{ $editable ? '' : 'disabled' }}>
                            <option value="">-- Pilih Bahan --</option>
                            @foreach($bahanList as $b)
                                <option value="{{ $b->id_bahan }}" {{ ($r['id_bahan'] ?? '') == $b->id_bahan ? 'selected' : '' }}>{{ $b->nama_bahan }} ({{ $b->satuan_stok }})</option>
                            @endforeach
                        </select>
                    </td>
                    <td class="px-4 py-3"><input type="number" step="0.01" min="0.01" name="items[{{ $i }}][qty]" value="{{ $r['qty'] ?? '' }}" data-qty class="w-full rounded-xl border-slate-200 text-sm p-2 text-right" required {{ $editable ? '' : 'readonly' }}></td>
                    <td class="px-4 py-3"><input type="text" inputmode="decimal" autocomplete="off" name="items[{{ $i }}][harga]" value="{{ ($r['harga'] ?? '') === '' ? '' : angka_input($r['harga'], 2) }}" data-harga class="w-full rounded-xl border-slate-200 text-sm p-2 text-right format-ribuan-desimal" required {{ $editable ? '' : 'readonly' }}></td>
                    <td class="px-4 py-3 text-right"><span class="text-sm font-medium text-slate-700" data-subtotal>0</span></td>
                    <td class="px-4 py-3"><input type="text" maxlength="255" name="items[{{ $i }}][catatan]" value="{{ $r['catatan'] ?? '' }}" class="w-full rounded-xl border-slate-200 text-xs p-2" placeholder="mis. merk sama dengan PR" {{ $editable ? '' : 'readonly' }}></td>
                    @if($po && !$editable)<td class="px-4 py-3 text-right text-sm text-slate-500">{{ angka($r['qty_diterima'] ?? 0, 2) }}</td>@endif
                    @if($editable)<td class="px-4 py-3 text-center"><button type="button" onclick="this.closest('tr').remove(); hitungTotal()" class="text-slate-500 hover:text-rose-500" aria-label="Hapus" title="Hapus"><i class="fas fa-trash-alt"></i></button></td>@endif
                </tr>
                @endforeach
            </tbody>
        </x-table>

        @if($editable && !empty($prefill['id_purchase_request']) && collect($rows)->contains(fn ($r) => (float) ($r['harga'] ?? 0) > 0))
            <p class="mt-3 text-[11px] text-emerald-600 font-medium flex items-center gap-1.5">
                <i class="fas fa-clock-rotate-left"></i>
                Harga satuan diambil otomatis dari PO terakhir untuk bahan yang pernah dibeli - tetap bisa diubah bila harga berubah.
            </p>
        @endif

        @if($editable)
        <div class="mt-4"><x-button type="button" variant="outline" size="sm" onclick="tambahBaris()"><i class="fas fa-plus"></i> Tambah Item</x-button></div>
        @endif

        <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-card>
                <x-form.group label="Catatan (opsional)" dalam>
                    <textarea name="catatan" rows="4" class="w-full rounded-xl border-slate-200 text-sm p-3" {{ $editable ? '' : 'readonly' }}>{{ old('catatan', $po->catatan ?? '') }}</textarea>
                </x-form.group>
            </x-card>
            <x-card>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span class="font-medium" id="t-subtotal">0</span></div>
                    <div class="flex justify-between items-center gap-4"><span class="text-slate-500">Diskon (Rp)</span>
                        <input type="text" inputmode="decimal" autocomplete="off" name="diskon" id="in-diskon" value="{{ angka_input(old('diskon', $po->diskon ?? 0), 2) }}" class="w-40 rounded-xl border-slate-200 text-sm p-2 text-right format-ribuan-desimal" {{ $editable ? '' : 'readonly' }}></div>
                    <div class="flex justify-between items-center gap-4"><span class="text-slate-500">Pajak (%)</span>
                        <input type="number" step="0.01" min="0" max="100" name="pajak_persen" id="in-pajak" value="{{ old('pajak_persen', $po->pajak_persen ?? 0) }}" class="w-40 rounded-xl border-slate-200 text-sm p-2 text-right" {{ $editable ? '' : 'readonly' }}></div>
                    <div class="flex justify-between items-center gap-4"><span class="text-slate-500">Ongkir (Rp)</span>
                        <input type="text" inputmode="decimal" autocomplete="off" name="ongkir" id="in-ongkir" value="{{ angka_input(old('ongkir', $po->ongkir ?? 0), 2) }}" class="w-40 rounded-xl border-slate-200 text-sm p-2 text-right format-ribuan-desimal" {{ $editable ? '' : 'readonly' }}></div>
                    <div class="flex justify-between pt-3 border-t border-slate-100 text-base"><span class="font-medium text-slate-700">Total</span><span class="font-medium text-indigo-600" id="t-total">0</span></div>
                </div>
            </x-card>
        </div>

        @if($editable)
        <div class="mt-8 flex flex-wrap gap-3 justify-end">
            @if($po)
                <x-button type="submit" form="form-hapus-po" variant="danger"><i class="fas fa-trash"></i> Hapus</x-button>
            @endif
            <x-button type="submit" variant="secondary"><i class="fas fa-save"></i> Simpan Draft</x-button>
            @if($po)
                <x-button type="submit" form="form-terbit-po" variant="primary"><i class="fas fa-paper-plane"></i> Terbitkan ke Supplier</x-button>
            @endif
        </div>
        @endif
    </form>

    @if($po)
        @if($editable)
            <form id="form-terbit-po" method="POST" action="{{ route('purchase-order.terbitkan', $po->id) }}" data-konfirmasi="Terbitkan PO ini ke supplier? Setelah diterbitkan tidak bisa diubah.">@csrf @method('PATCH')</form>
            <form id="form-hapus-po" method="POST" action="{{ route('purchase-order.destroy', $po->id) }}" data-konfirmasi="Hapus Pesanan Pembelian ini?">@csrf @method('DELETE')</form>
        @endif
        @if(in_array($po->status, ['Draft', 'Dipesan']))
            <form method="POST" action="{{ route('purchase-order.batalkan', $po->id) }}" data-konfirmasi="Batalkan Pesanan Pembelian ini?" class="mt-4 flex justify-end">@csrf @method('PATCH')
                <x-button type="submit" variant="outline" size="sm"><i class="fas fa-ban"></i> Batalkan PO</x-button>
            </form>
        @endif
    @endif

    @if($po && in_array($po->status, ['Dipesan', 'Diterima Sebagian', 'Selesai']))
    <x-card class="mt-8">
        <h3 class="text-xs font-medium text-slate-800 uppercase tracking-widest mb-4">Penerimaan Barang</h3>

        @if($po->kedatangan->isNotEmpty())
        <div class="mb-6 space-y-2">
            @foreach($po->kedatangan as $k)
                <div class="flex items-center justify-between text-sm p-3 rounded-xl bg-slate-50">
                    <span class="font-medium text-slate-700">SJ {{ $k->no_surat_jalan }} - {{ $k->tgl_datang?->translatedFormat('d M Y') }}</span>
                    <span class="flex items-center gap-3">
                        <x-badge :status="$k->status" :variant="$k->status === 'Selesai' ? 'success' : 'info'" />
                        <a href="{{ route('kedatangan-bahan.edit', $k->id_kedatangan) }}" class="text-indigo-600 text-xs font-medium uppercase">Buka</a>
                    </span>
                </div>
            @endforeach
        </div>
        @endif

        @if($po->status !== 'Selesai')
        <form method="POST" action="{{ route('purchase-order.kedatangan', $po->id) }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            @csrf
            <x-form.group label="No. Surat Jalan" dalam>
                <x-form.input type="text" name="no_surat_jalan" required placeholder="SJ-001" />
            </x-form.group>
            <x-form.group label="Tanggal Datang" dalam>
                <x-form.input type="date" name="tgl_datang" value="{{ now()->toDateString() }}" required />
            </x-form.group>
            <x-form.group label="Gudang" dalam>
                <x-form.select name="id_gudang">
                    @foreach($gudangList as $g)<option value="{{ $g->id_gudang }}">{{ $g->nama_gudang }}</option>@endforeach
                </x-form.select>
            </x-form.group>
            <x-button type="submit" variant="success"><i class="fas fa-truck-loading"></i> Catat Kedatangan</x-button>
        </form>
        <p class="text-[11px] text-slate-400 mt-3">Kedatangan dibuat sebagai Draft berisi sisa qty yang belum diterima. Stok baru bertambah setelah kedatangan divalidasi.</p>
        @endif
    </x-card>
    @endif

    <template id="row-template">
        <tr data-row>
            <td class="px-6 py-3">
                <select name="items[__I__][id_bahan]" class="w-full rounded-xl border-slate-200 text-sm p-2" required>
                    <option value="">-- Pilih Bahan --</option>
                    @foreach($bahanList as $b)<option value="{{ $b->id_bahan }}">{{ $b->nama_bahan }} ({{ $b->satuan_stok }})</option>@endforeach
                </select>
            </td>
            <td class="px-4 py-3"><input type="number" step="0.01" min="0.01" name="items[__I__][qty]" data-qty class="w-full rounded-xl border-slate-200 text-sm p-2 text-right" required></td>
            <td class="px-4 py-3"><input type="text" inputmode="decimal" autocomplete="off" name="items[__I__][harga]" data-harga class="w-full rounded-xl border-slate-200 text-sm p-2 text-right format-ribuan-desimal" required></td>
            <td class="px-4 py-3 text-right"><span class="text-sm font-medium text-slate-700" data-subtotal>0</span></td>
            <td class="px-4 py-3"><input type="text" maxlength="255" name="items[__I__][catatan]" class="w-full rounded-xl border-slate-200 text-xs p-2" placeholder="mis. merk sama dengan PR"></td>
            <td class="px-4 py-3 text-center"><button type="button" onclick="this.closest('tr').remove(); hitungTotal()" class="text-slate-500 hover:text-rose-500" aria-label="Hapus" title="Hapus"><i class="fas fa-trash-alt"></i></button></td>
        </tr>
    </template>
</div>

@push('scripts')
<script>
    let nextIndex = {{ count($rows) + 100 }};
    const rp = n => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(n);

    // Tampilan saja - total sebenarnya SELALU dihitung ulang di server.
    function hitungTotal() {
        let subtotal = 0;
        document.querySelectorAll('[data-row]').forEach(row => {
            const s = (parseFloat(row.querySelector('[data-qty]').value) || 0) * cleanUangDesimal(row.querySelector('[data-harga]').value);
            row.querySelector('[data-subtotal]').textContent = rp(s);
            subtotal += s;
        });
        const diskon = cleanUangDesimal(document.getElementById('in-diskon').value);
        const pajak = (subtotal - diskon) * (parseFloat(document.getElementById('in-pajak').value) || 0) / 100;
        const ongkir = cleanUangDesimal(document.getElementById('in-ongkir').value);
        document.getElementById('t-subtotal').textContent = rp(subtotal);
        document.getElementById('t-total').textContent = 'Rp ' + rp(subtotal - diskon + pajak + ongkir);
    }

    function tambahBaris() {
        document.getElementById('item-rows').insertAdjacentHTML('beforeend', document.getElementById('row-template').innerHTML.replaceAll('__I__', nextIndex++));
        hitungTotal();
    }

    document.getElementById('form-po').addEventListener('input', hitungTotal);
    hitungTotal();
</script>
@endpush
@if($editable)
@include('partials.supplier-baru-modal', ['selectId' => 'id_supplier'])
@endif
@endsection
