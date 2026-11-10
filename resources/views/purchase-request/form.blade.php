@extends('layouts.app')

@section('content')
@php
    $editable = !$pr || $pr->status === 'Draft';
    $rows = old('items', $pr ? $pr->details->map(fn ($d) => ['id_bahan' => $d->id_bahan, 'qty' => $d->qty, 'catatan' => $d->catatan])->all() : [['id_bahan' => '', 'qty' => '', 'catatan' => '']]);
    $variantMap = ['Draft' => 'info', 'Diajukan' => 'warning', 'Disetujui' => 'success', 'Ditolak' => 'danger', 'Diproses PO' => 'success'];
    $statusInfo = [
        'Diajukan'     => 'Sedang menunggu persetujuan di Alur Persetujuan.',
        'Disetujui'    => 'Sudah disetujui - lanjutkan dengan menekan tombol Buat PO di daftar Permintaan Pembelian.',
        'Ditolak'      => 'Ditolak oleh persetujuan. Buat Permintaan Pembelian baru bila masih diperlukan.',
        'Diproses PO'  => 'Sudah dijadikan Pesanan Pembelian - ubah qty/harga lewat PO-nya.',
    ][$pr->status ?? ''] ?? null;
@endphp
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1100px] mx-auto">

    <x-page-header title="{{ $pr ? 'Permintaan Pembelian ' . $pr->nomor : 'Permintaan Pembelian Baru' }}">
        <x-slot:subtitle>
            <i class="fas fa-clipboard-list text-amber-400"></i>
            {{ $editable ? 'Isi bahan yang perlu dibeli, simpan sebagai Draft, lalu ajukan untuk persetujuan' : 'Hanya-baca - sudah tidak berstatus Draft' }}
        </x-slot:subtitle>
        @if($pr)
        <x-slot:actions>
            <x-badge :status="$pr->status" :variant="$variantMap[$pr->status] ?? 'info'" />
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

    @if(!$editable && $statusInfo)
        <div class="mb-6 p-4 rounded-xl bg-amber-50 border border-amber-100 text-amber-700 text-sm font-medium flex items-start gap-3">
            <i class="fas fa-lock mt-0.5"></i>
            <span>Document ini hanya-baca karena berstatus <b>{{ $pr->status }}</b>. {{ $statusInfo }}</span>
        </div>
    @endif

    <form id="form-pr" method="POST" action="{{ $pr ? route('purchase-request.update', $pr->id) : route('purchase-request.store') }}">
        @csrf
        @if($pr) @method('PATCH') @endif

        <x-card class="mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <x-form.group label="Tanggal" name="tanggal" dalam>
                    {{-- Input polos (bukan x-form.input): echo kondisional di dalam
                         tag komponen terbukti membuat input tidak dirender - pola
                         input polos ini yang dipakai qty/harga dan tampil normal. --}}
                    <input type="date" name="tanggal" value="{{ old('tanggal', $pr ? $pr->tanggal->toDateString() : now()->toDateString()) }}" required
                        class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 "
                        {{ $editable ? '' : 'readonly' }} />
                </x-form.group>
                <x-form.group label="Rencana supplier (opsional)" name="id_supplier_rencana" dalam>
                    <select name="id_supplier_rencana" class="w-full rounded-xl border-slate-200 text-sm p-2" {{ $editable ? '' : 'disabled' }}>
                        <option value="">Belum ditentukan</option>
                        @foreach($supplierList as $s)
                            <option value="{{ $s->id_supplier }}" {{ (string) old('id_supplier_rencana', $pr->id_supplier_rencana ?? '') === (string) $s->id_supplier ? 'selected' : '' }}>{{ $s->nama_supplier }}</option>
                        @endforeach
                    </select>
                </x-form.group>
                <x-form.group label="Catatan (opsional)" name="catatan" dalam>
                    <input type="text" name="catatan" value="{{ old('catatan', $pr->catatan ?? '') }}" placeholder="Mis. stok Flexi Korea tinggal 15 m2"
                        class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 "
                        {{ $editable ? '' : 'readonly' }} />
                </x-form.group>
            </div>
        </x-card>

        <x-table>
            <thead>
                <tr>
                    <th class="px-6 py-4 text-left">Bahan</th>
                    <th class="px-4 py-4 text-right w-40">Qty</th>
                    <th class="px-4 py-4 text-left w-64">Catatan</th>
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
                                <option value="{{ $b->id_bahan }}" data-satuan="{{ $b->satuan_stok }}" {{ ($r['id_bahan'] ?? '') == $b->id_bahan ? 'selected' : '' }}>{{ $b->nama_bahan }} (stok {{ angka($b->stok, 2) }} {{ $b->satuan_stok }})</option>
                            @endforeach
                        </select>
                        @if(!$editable)<input type="hidden" name="items[{{ $i }}][id_bahan]" value="{{ $r['id_bahan'] }}">{{-- nilai tetap terkirim meski select disabled --}}@endif
                    </td>
                    <td class="px-4 py-3"><input type="number" step="0.01" min="0.01" name="items[{{ $i }}][qty]" value="{{ $r['qty'] ?? '' }}" class="w-full rounded-xl border-slate-200 text-sm p-2 text-right" required {{ $editable ? '' : 'readonly' }}><span class="pr-unit block mt-1 text-right text-[10px] font-medium text-indigo-600 uppercase"></span></td>
                    <td class="px-4 py-3"><input type="text" maxlength="255" name="items[{{ $i }}][catatan]" value="{{ $r['catatan'] ?? '' }}" class="w-full rounded-xl border-slate-200 text-xs p-2" {{ $editable ? '' : 'readonly' }}></td>
                    @if($editable)<td class="px-4 py-3 text-center"><button type="button" onclick="this.closest('tr').remove()" class="text-slate-500 hover:text-rose-500" aria-label="Hapus" title="Hapus"><i class="fas fa-trash-alt"></i></button></td>@endif
                </tr>
                @endforeach
            </tbody>
        </x-table>

        @if($editable)
        <div class="mt-4">
            <x-button type="button" variant="outline" size="sm" onclick="tambahBaris()"><i class="fas fa-plus"></i> Tambah Item</x-button>
        </div>
        <div class="mt-8 flex flex-wrap gap-3 justify-end">
            @if($pr)
                <x-button type="submit" form="form-hapus-pr" variant="danger"><i class="fas fa-trash"></i> Hapus</x-button>
            @endif
            <x-button type="submit" variant="secondary"><i class="fas fa-save"></i> Simpan Draft</x-button>
            @if($pr)
                <x-button type="submit" form="form-ajukan-pr" variant="primary"><i class="fas fa-paper-plane"></i> Ajukan untuk Persetujuan</x-button>
            @endif
        </div>
        @endif
    </form>

    @if($pr && $editable)
        <form id="form-ajukan-pr" method="POST" action="{{ route('purchase-request.ajukan', $pr->id) }}">@csrf @method('PATCH')</form>
        <form id="form-hapus-pr" method="POST" action="{{ route('purchase-request.destroy', $pr->id) }}" data-konfirmasi="Hapus Permintaan Pembelian ini?">@csrf @method('DELETE')</form>
    @endif

    <template id="row-template">
        <tr data-row>
            <td class="px-6 py-3">
                <select name="items[__I__][id_bahan]" class="w-full rounded-xl border-slate-200 text-sm p-2" required>
                    <option value="">-- Pilih Bahan --</option>
                    @foreach($bahanList as $b)
                        <option value="{{ $b->id_bahan }}" data-satuan="{{ $b->satuan_stok }}">{{ $b->nama_bahan }} (stok {{ angka($b->stok, 2) }} {{ $b->satuan_stok }})</option>
                    @endforeach
                </select>
            </td>
            <td class="px-4 py-3"><input type="number" step="0.01" min="0.01" name="items[__I__][qty]" class="w-full rounded-xl border-slate-200 text-sm p-2 text-right" required><span class="pr-unit block mt-1 text-right text-[10px] font-medium text-indigo-600 uppercase"></span></td>
            <td class="px-4 py-3"><input type="text" maxlength="255" name="items[__I__][catatan]" class="w-full rounded-xl border-slate-200 text-xs p-2"></td>
            <td class="px-4 py-3 text-center"><button type="button" onclick="this.closest('tr').remove()" class="text-slate-500 hover:text-rose-500" aria-label="Hapus" title="Hapus"><i class="fas fa-trash-alt"></i></button></td>
        </tr>
    </template>
</div>

@push('scripts')
<script>
    // Satuan di bawah kolom qty mengikuti bahan yang dipilih pada baris itu.
    function segarkanSatuanPR(sel) {
        const u = sel.closest('tr').querySelector('.pr-unit');
        if (u) u.textContent = sel.selectedOptions[0]?.dataset.satuan || '';
    }
    document.addEventListener('change', e => { if (e.target.matches('select[name$="[id_bahan]"]')) segarkanSatuanPR(e.target); });
    document.querySelectorAll('select[name$="[id_bahan]"]').forEach(segarkanSatuanPR);
    let nextIndex = {{ count($rows) + 100 }};
    function tambahBaris() {
        const html = document.getElementById('row-template').innerHTML.replaceAll('__I__', nextIndex++);
        document.getElementById('item-rows').insertAdjacentHTML('beforeend', html);
    }
</script>
@endpush
@endsection
