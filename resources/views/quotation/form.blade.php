@extends('layouts.app')

@section('content')
@php
    $editable = !$quotation || $quotation->status === 'Draft';
    $efektif = $quotation?->status_efektif;
    $variantMap = ['Draft' => 'info', 'Terkirim' => 'warning', 'Disetujui' => 'success', 'Ditolak' => 'danger', 'Menjadi Order' => 'success', 'Kedaluwarsa' => 'danger'];
    $rows = old('items', $quotation ? $quotation->items->map(fn ($i) => [
        'tipe' => $i->tipe, 'id_produk' => $i->id_produk, 'id_jasa' => $i->id_jasa, 'id_finishing' => $i->id_finishing,
        'p' => (float) $i->p_ukuran, 'l' => (float) $i->l_ukuran, 'qty' => (float) $i->qty, 'harga_satuan' => (float) $i->harga_satuan,
        'biaya_finishing' => (float) $i->biaya_finishing, 'biaya_desain' => (float) $i->biaya_desain, 'catatan' => $i->catatan, 'subtotal' => (float) $i->subtotal, 'nama_item' => $i->nama_item,
    ])->all() : [['tipe' => 'Produk']]);

    $wa = null;
    if ($quotation && $quotation->telepon) {
        $no = preg_replace('/\D+/', '', $quotation->telepon);
        $no = str_starts_with($no, '0') ? '62' . substr($no, 1) : $no;
        $pesan = "Yth. {$quotation->nama_pelanggan},\nBerikut penawaran {$quotation->nomor} sebesar Rp " . number_format($quotation->total, 0, ',', '.') . ", berlaku sampai " . $quotation->berlaku_sampai->translatedFormat('d M Y') . ". Terima kasih.";
        $wa = 'https://wa.me/' . $no . '?text=' . rawurlencode($pesan);
    }
@endphp
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1200px] mx-auto">

    <x-page-header title="{{ $quotation ? 'Penawaran ' . $quotation->nomor : 'Penawaran Baru' }}">
        <x-slot:subtitle>
            <i class="fas fa-file-signature text-amber-400"></i>
            {{ $editable ? 'Draft - harga dihitung otomatis dari Daftar Harga, bisa ditimpa per baris' : 'Hanya-baca' }}
        </x-slot:subtitle>
        @if($quotation)
        <x-slot:actions>
            <x-badge :status="$efektif" :variant="$variantMap[$efektif] ?? 'info'" />
        </x-slot:actions>
        @endif
    </x-page-header>

    @if($errors->any())
        <div class="mb-6 p-4 rounded-xl bg-rose-50 text-rose-600 text-sm font-medium">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    <form id="form-qt" method="POST" action="{{ $quotation ? route('quotation.update', $quotation->id) : route('quotation.store') }}">
        @csrf
        @if($quotation) @method('PATCH') @endif

        <x-card class="mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <x-form.group label="Pelanggan terdaftar (opsional)" dalam>
                    <x-form.select name="id_konsumen" id="id_konsumen" :disabled="! $editable">
                        <option value="">-- Prospek / belum terdaftar --</option>
                        @foreach($konsumenList as $k)
                            <option value="{{ $k->id_konsumen }}" data-nama="{{ $k->nama_konsumen }}" data-telp="{{ $k->telepon }}" data-gol="{{ $k->id_golongan }}" {{ old('id_konsumen', $quotation->id_konsumen ?? '') == $k->id_konsumen ? 'selected' : '' }}>{{ $k->nama_konsumen }} - {{ $k->telepon }}</option>
                        @endforeach
                    </x-form.select>
                </x-form.group>
                <x-form.group label="Golongan harga" dalam>
                    <x-form.select name="id_golongan" id="id_golongan" :disabled="! $editable">
                        @foreach($golonganList as $g)
                            <option value="{{ $g->id_golongan }}" {{ old('id_golongan', $quotation->id_golongan ?? '') == $g->id_golongan ? 'selected' : '' }}>{{ $g->nama_golongan }}</option>
                        @endforeach
                    </x-form.select>
                </x-form.group>
                <x-form.group label="Nama pelanggan" dalam>
                    <x-form.input type="text" name="nama_pelanggan" id="nama_pelanggan" value="{{ old('nama_pelanggan', $quotation->nama_pelanggan ?? '') }}" required :disabled="! $editable" />
                </x-form.group>
                <x-form.group label="Telepon / WhatsApp" dalam>
                    <x-form.input type="text" name="telepon" id="telepon" value="{{ old('telepon', $quotation->telepon ?? '') }}" :disabled="! $editable" />
                </x-form.group>
                <x-form.group label="Tanggal" dalam>
                    <x-form.input type="date" name="tanggal" value="{{ old('tanggal', $quotation ? $quotation->tanggal->toDateString() : now()->toDateString()) }}" required :disabled="! $editable" />
                </x-form.group>
                <x-form.group label="Berlaku sampai" dalam>
                    <x-form.input type="date" name="berlaku_sampai" value="{{ old('berlaku_sampai', $quotation ? $quotation->berlaku_sampai->toDateString() : now()->addDays(7)->toDateString()) }}" required :disabled="! $editable" />
                </x-form.group>
            </div>
        </x-card>

        <div id="item-rows" class="space-y-4">
            @foreach($rows as $i => $r)
            <x-card data-row>
                <div class="grid grid-cols-2 md:grid-cols-6 gap-3 items-end">
                    <div>
                        <label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">Tipe</label>
                        <select name="items[{{ $i }}][tipe]" data-tipe onchange="ubahTipe(this)" class="w-full rounded-xl border-slate-200 text-sm p-2" {{ $editable ? '' : 'disabled' }}>
                            <option value="Produk" {{ ($r['tipe'] ?? 'Produk') === 'Produk' ? 'selected' : '' }}>Produk</option>
                            <option value="Jasa" {{ ($r['tipe'] ?? '') === 'Jasa' ? 'selected' : '' }}>Jasa</option>
                        </select>
                    </div>
                    <div class="col-span-2 md:col-span-2" data-blok-produk>
                        <label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">Produk</label>
                        <select name="items[{{ $i }}][id_produk]" class="w-full rounded-xl border-slate-200 text-sm p-2" {{ $editable ? '' : 'disabled' }}>
                            <option value="">-- Pilih --</option>
                            @foreach($produkList as $p)<option value="{{ $p->id_produk }}" data-metode="{{ $p->metode_hitung }}" {{ ($r['id_produk'] ?? '') == $p->id_produk ? 'selected' : '' }}>{{ $p->nama_produk }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-span-2 md:col-span-2" data-blok-jasa style="display:none">
                        <label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">Jasa</label>
                        <select name="items[{{ $i }}][id_jasa]" class="w-full rounded-xl border-slate-200 text-sm p-2" {{ $editable ? '' : 'disabled' }}>
                            <option value="">-- Pilih --</option>
                            @foreach($jasaList as $j)<option value="{{ $j->id_jasa }}" {{ ($r['id_jasa'] ?? '') == $j->id_jasa ? 'selected' : '' }}>{{ $j->nama_jasa }}</option>@endforeach
                        </select>
                    </div>
                    <div data-blok-produk data-blok-ukuran><label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">P (cm)</label>
                        <input type="number" step="0.01" min="0" name="items[{{ $i }}][p]" value="{{ $r['p'] ?? '' }}" class="w-full rounded-xl border-slate-200 text-sm p-2 text-right" {{ $editable ? '' : 'disabled' }}></div>
                    <div data-blok-produk data-blok-ukuran><label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">L (cm)</label>
                        <input type="number" step="0.01" min="0" name="items[{{ $i }}][l]" value="{{ $r['l'] ?? '' }}" class="w-full rounded-xl border-slate-200 text-sm p-2 text-right" {{ $editable ? '' : 'disabled' }}></div>
                    <div><label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">Qty</label>
                        <input type="number" step="0.01" min="0.01" name="items[{{ $i }}][qty]" value="{{ $r['qty'] ?? '' }}" required class="w-full rounded-xl border-slate-200 text-sm p-2 text-right" {{ $editable ? '' : 'disabled' }}></div>
                    <div><label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">Harga satuan (kosong = otomatis)</label>
                        <input type="text" inputmode="decimal" autocomplete="off" name="items[{{ $i }}][harga_satuan]" value="{{ ($r['harga_satuan'] ?? '') === '' ? '' : angka_input($r['harga_satuan'], 2) }}" class="w-full rounded-xl border-slate-200 text-sm p-2 text-right format-ribuan-desimal" {{ $editable ? '' : 'disabled' }}></div>
                    <div data-blok-produk><label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">Finishing</label>
                        <select name="items[{{ $i }}][id_finishing]" class="w-full rounded-xl border-slate-200 text-sm p-2" {{ $editable ? '' : 'disabled' }}>
                            <option value="">-</option>
                            @foreach($finishingList as $f)<option value="{{ $f->id_finishing }}" {{ ($r['id_finishing'] ?? '') == $f->id_finishing ? 'selected' : '' }}>{{ $f->nama_finishing }}</option>@endforeach
                        </select></div>
                    <div data-blok-produk><label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">Biaya finishing</label>
                        <input type="text" inputmode="decimal" autocomplete="off" name="items[{{ $i }}][biaya_finishing]" value="{{ ($r['biaya_finishing'] ?? 0) ? angka_input($r['biaya_finishing'], 2) : '' }}" class="w-full rounded-xl border-slate-200 text-sm p-2 text-right format-ribuan-desimal" {{ $editable ? '' : 'disabled' }}></div>
                    <div data-blok-produk><label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">Biaya desain</label>
                        <input type="text" inputmode="decimal" autocomplete="off" name="items[{{ $i }}][biaya_desain]" value="{{ ($r['biaya_desain'] ?? 0) ? angka_input($r['biaya_desain'], 2) : '' }}" class="w-full rounded-xl border-slate-200 text-sm p-2 text-right format-ribuan-desimal" {{ $editable ? '' : 'disabled' }}></div>
                    <div class="col-span-2 md:col-span-2"><label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">Catatan baris</label>
                        <input type="text" maxlength="255" name="items[{{ $i }}][catatan]" value="{{ $r['catatan'] ?? '' }}" class="w-full rounded-xl border-slate-200 text-xs p-2" {{ $editable ? '' : 'disabled' }}></div>
                    <div class="text-right">
                        @if(isset($r['subtotal']))<div class="text-[10px] font-medium text-slate-400 uppercase">Subtotal</div><div class="text-sm font-medium text-indigo-600">Rp {{ number_format($r['subtotal'], 0, ',', '.') }}</div>@endif
                        @if($editable)<button type="button" onclick="this.closest('[data-row]').remove()" class="mt-1 text-slate-500 hover:text-rose-500 text-xs"><i class="fas fa-trash-alt"></i> Hapus baris</button>@endif
                    </div>
                </div>
            </x-card>
            @endforeach
        </div>

        @if($editable)
        <div class="mt-4"><x-button type="button" variant="outline" size="sm" onclick="tambahBaris()"><i class="fas fa-plus"></i> Tambah Item</x-button></div>
        @endif

        <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-card>
                <x-form.group label="Catatan / syarat (opsional)" dalam>
                    <textarea name="catatan" rows="4" class="w-full rounded-xl border-slate-200 text-sm p-3" {{ $editable ? '' : 'disabled' }}>{{ old('catatan', $quotation->catatan ?? '') }}</textarea>
                </x-form.group>
            </x-card>
            <x-card>
                <div class="space-y-3 text-sm">
                    @if($quotation)<div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span class="font-medium">Rp {{ number_format($quotation->subtotal, 0, ',', '.') }}</span></div>@endif
                    <div class="flex justify-between items-center gap-4"><span class="text-slate-500">Diskon (Rp)</span>
                        <input type="text" inputmode="decimal" autocomplete="off" name="diskon" value="{{ angka_input(old('diskon', $quotation->diskon ?? 0), 2) }}" class="w-40 rounded-xl border-slate-200 text-sm p-2 text-right format-ribuan-desimal" {{ $editable ? '' : 'disabled' }}></div>
                    @if($quotation)<div class="flex justify-between pt-3 border-t border-slate-100 text-base"><span class="font-medium text-slate-700">Total</span><span class="font-medium text-indigo-600">Rp {{ number_format($quotation->total, 0, ',', '.') }}</span></div>
                    @else<p class="text-[11px] text-slate-400">Subtotal & total dihitung server setelah disimpan.</p>@endif
                </div>
            </x-card>
        </div>

        @if($editable)
        <div class="mt-8 flex flex-wrap gap-3 justify-end">
            @if($quotation)<x-button type="submit" form="form-hapus-qt" variant="danger"><i class="fas fa-trash"></i> Hapus</x-button>@endif
            <x-button type="submit" variant="primary"><i class="fas fa-save"></i> Simpan Draft</x-button>
        </div>
        @endif
    </form>

    @if($quotation)
        @if($editable)
            <form id="form-hapus-qt" method="POST" action="{{ route('quotation.destroy', $quotation->id) }}" data-konfirmasi="Hapus penawaran ini?">@csrf @method('DELETE')</form>
        @endif

        <x-card class="mt-8">
            <h3 class="text-xs font-medium text-slate-800 uppercase tracking-widest mb-4">Tindak Lanjut</h3>
            <div class="flex flex-wrap gap-3">
                <x-button :href="route('quotation.cetak', $quotation->id)" target="_blank" variant="outline"><i class="fas fa-print"></i> Cetak / Simpan PDF</x-button>
                @if($wa)<x-button :href="$wa" target="_blank" variant="success"><i class="fab fa-whatsapp"></i> Kirim WhatsApp</x-button>@endif

                @if($quotation->status === 'Draft')
                    <form method="POST" action="{{ route('quotation.kirim', $quotation->id) }}">@csrf @method('PATCH')
                        <x-button type="submit" variant="primary"><i class="fas fa-paper-plane"></i> Tandai Terkirim</x-button></form>
                @endif

                @if($quotation->status === 'Terkirim' && $efektif !== 'Kedaluwarsa')
                    <form method="POST" action="{{ route('quotation.tanggapi', $quotation->id) }}">@csrf @method('PATCH')<input type="hidden" name="keputusan" value="Disetujui">
                        <x-button type="submit" variant="success"><i class="fas fa-check"></i> Pelanggan Setuju</x-button></form>
                    <form method="POST" action="{{ route('quotation.tanggapi', $quotation->id) }}">@csrf @method('PATCH')<input type="hidden" name="keputusan" value="Ditolak">
                        <x-button type="submit" variant="danger"><i class="fas fa-times"></i> Pelanggan Menolak</x-button></form>
                @endif

                @if($quotation->status === 'Disetujui')
                    <form method="POST" action="{{ route('quotation.buat-nota', $quotation->id) }}">@csrf
                        <x-button type="submit" variant="primary"><i class="fas fa-receipt"></i> Buat Nota dari Penawaran</x-button></form>
                @endif

                @if($quotation->status === 'Menjadi Order' && $quotation->id_transaksi)
                    <x-button :href="route('penjualan.show', $quotation->id_transaksi)" variant="success"><i class="fas fa-receipt"></i> Lihat Nota Order</x-button>
                @endif
            </div>
            @if($efektif === 'Kedaluwarsa')<p class="text-xs text-rose-500 mt-3">Penawaran sudah melewati masa berlaku - buat penawaran baru bila pelanggan masih berminat.</p>@endif
        </x-card>
    @endif

    <template id="row-template">
        <x-card data-row>
            <div class="grid grid-cols-2 md:grid-cols-6 gap-3 items-end">
                <div><label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">Tipe</label>
                    <select name="items[__I__][tipe]" data-tipe onchange="ubahTipe(this)" class="w-full rounded-xl border-slate-200 text-sm p-2"><option value="Produk">Produk</option><option value="Jasa">Jasa</option></select></div>
                <div class="col-span-2 md:col-span-2" data-blok-produk><label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">Produk</label>
                    <select name="items[__I__][id_produk]" class="w-full rounded-xl border-slate-200 text-sm p-2"><option value="">-- Pilih --</option>@foreach($produkList as $p)<option value="{{ $p->id_produk }}" data-metode="{{ $p->metode_hitung }}">{{ $p->nama_produk }}</option>@endforeach</select></div>
                <div class="col-span-2 md:col-span-2" data-blok-jasa style="display:none"><label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">Jasa</label>
                    <select name="items[__I__][id_jasa]" class="w-full rounded-xl border-slate-200 text-sm p-2"><option value="">-- Pilih --</option>@foreach($jasaList as $j)<option value="{{ $j->id_jasa }}">{{ $j->nama_jasa }}</option>@endforeach</select></div>
                <div data-blok-produk data-blok-ukuran><label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">P (cm)</label><input type="number" step="0.01" min="0" name="items[__I__][p]" class="w-full rounded-xl border-slate-200 text-sm p-2 text-right"></div>
                <div data-blok-produk data-blok-ukuran><label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">L (cm)</label><input type="number" step="0.01" min="0" name="items[__I__][l]" class="w-full rounded-xl border-slate-200 text-sm p-2 text-right"></div>
                <div><label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">Qty</label><input type="number" step="0.01" min="0.01" name="items[__I__][qty]" required class="w-full rounded-xl border-slate-200 text-sm p-2 text-right"></div>
                <div><label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">Harga satuan (kosong = otomatis)</label><input type="text" inputmode="decimal" autocomplete="off" name="items[__I__][harga_satuan]" class="w-full rounded-xl border-slate-200 text-sm p-2 text-right format-ribuan-desimal"></div>
                <div data-blok-produk><label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">Finishing</label>
                    <select name="items[__I__][id_finishing]" class="w-full rounded-xl border-slate-200 text-sm p-2"><option value="">-</option>@foreach($finishingList as $f)<option value="{{ $f->id_finishing }}">{{ $f->nama_finishing }}</option>@endforeach</select></div>
                <div data-blok-produk><label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">Biaya finishing</label><input type="text" inputmode="decimal" autocomplete="off" name="items[__I__][biaya_finishing]" class="w-full rounded-xl border-slate-200 text-sm p-2 text-right format-ribuan-desimal"></div>
                <div data-blok-produk><label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">Biaya desain</label><input type="text" inputmode="decimal" autocomplete="off" name="items[__I__][biaya_desain]" class="w-full rounded-xl border-slate-200 text-sm p-2 text-right format-ribuan-desimal"></div>
                <div class="col-span-2 md:col-span-2"><label class="block text-[10px] font-medium text-slate-400 uppercase mb-1">Catatan baris</label><input type="text" maxlength="255" name="items[__I__][catatan]" class="w-full rounded-xl border-slate-200 text-xs p-2"></div>
                <div class="text-right"><button type="button" onclick="this.closest('[data-row]').remove()" class="text-slate-500 hover:text-rose-500 text-xs"><i class="fas fa-trash-alt"></i> Hapus baris</button></div>
            </div>
        </x-card>
    </template>
</div>

@push('scripts')
<script>
    let nextIndex = {{ count($rows) + 100 }};

    function ubahTipe(sel) {
        const jasa = sel.value === 'Jasa', row = sel.closest('[data-row]');
        row.querySelectorAll('[data-blok-produk]').forEach(e => e.style.display = jasa ? 'none' : '');
        row.querySelectorAll('[data-blok-jasa]').forEach(e => e.style.display = jasa ? '' : 'none');
        aturUkuran(row);
    }

    // Kolom P dan L hanya untuk produk yang dihitung per ukuran (spanduk, stiker meteran). Produk yang
    // dihitung per qty cukup qty saja; nilai P/L yang tersisa dikosongkan supaya tidak ikut tersimpan.
    function aturUkuran(row) {
        const jasa = row.querySelector('[data-tipe]').value === 'Jasa';
        const opsi = row.querySelector('select[name$="[id_produk]"]').selectedOptions[0];
        const perluUkuran = !jasa && !!opsi && opsi.dataset.metode === 'ukuran';
        row.querySelectorAll('[data-blok-ukuran]').forEach(function (e) {
            e.style.display = perluUkuran ? '' : 'none';
            const isian = e.querySelector('input');
            isian.required = perluUkuran && !isian.disabled;
            if (!perluUkuran) isian.value = '';
        });
    }
    document.addEventListener('change', function (e) {
        if (e.target.matches('select[name$="[id_produk]"]')) aturUkuran(e.target.closest('[data-row]'));
    });

    function tambahBaris() {
        document.getElementById('item-rows').insertAdjacentHTML('beforeend', document.getElementById('row-template').innerHTML.replaceAll('__I__', nextIndex++));
        aturUkuran(document.getElementById('item-rows').lastElementChild);
    }

    document.querySelectorAll('[data-tipe]').forEach(ubahTipe);

    document.getElementById('id_konsumen')?.addEventListener('change', function () {
        const o = this.selectedOptions[0];
        if (!o || !o.value) return;
        document.getElementById('nama_pelanggan').value = o.dataset.nama || '';
        document.getElementById('telepon').value = o.dataset.telp || '';
        if (o.dataset.gol) document.getElementById('id_golongan').value = o.dataset.gol;
    });
</script>
@endpush
@endsection
