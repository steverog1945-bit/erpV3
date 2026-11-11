@extends('layouts.app')

@section('content')
@include('partials.rupiah-format-script')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Data Master Jersey" subtitle="Tipe produk, kategori, ukuran, bahan, estimasi bahan & tier harga untuk modul Pesanan Jersey. Pelanggan diambil langsung dari Master Konsumen.">
        <x-slot:actions>
            <x-button href="{{ route('jersey.orders.index') }}" variant="secondary">
                <i class="fas fa-arrow-left"></i> Kembali ke Order
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-wrap gap-2 mb-6" id="jersey-master-tabs">
        @foreach(['tipe' => 'Tipe Produk', 'kategori' => 'Kategori', 'ukuran' => 'Ukuran', 'bahan' => 'Bahan', 'lengan' => 'Tipe Lengan', 'kerah' => 'Tipe Kerah', 'estimasi' => 'Estimasi Bahan', 'harga' => 'Tier Harga'] as $key => $label)
            <button type="button" onclick="switchJerseyTab('{{ $key }}')" id="tab-btn-{{ $key }}"
                class="jersey-tab-btn px-5 py-2.5 rounded-xl text-xs font-medium uppercase tracking-widest transition-all {{ $loop->first ? 'bg-indigo-50 text-indigo-600 border border-indigo-100' : 'bg-white text-slate-400 border border-slate-200' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- TIPE PRODUK --}}
    <div id="tab-panel-tipe" class="jersey-tab-panel">
        <x-card>
            <form id="form-add-tipe" class="flex flex-col sm:flex-row gap-3 mb-6 bg-indigo-50/40 p-4 rounded-2xl border border-indigo-100">
                <div class="flex-1"><input type="text" name="name" required placeholder="Nama tipe produk (mis. Jersey, Celana, Sublime Lainnya)" class="w-full bg-white border border-slate-200 rounded-xl py-3 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"></div>
                <label class="flex items-center gap-2 text-xs font-medium text-slate-500 bg-white border border-slate-200 rounded-xl py-3 px-4 whitespace-nowrap cursor-pointer">
                    <input type="checkbox" name="has_sleeve_collar" checked class="rounded text-indigo-600 focus:ring-indigo-500/20">
                    Punya Lengan &amp; Kerah
                </label>
                <x-button type="submit" variant="success">Tambah</x-button>
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-200 text-slate-500 text-[10px] font-medium cursor-help shrink-0 self-center" title="Matikan centang &quot;Punya Lengan &amp; Kerah&quot; untuk tipe produk tanpa lengan/kerah (mis. Celana) - pilihan Lengan/Kerah otomatis disembunyikan di form Order untuk tipe ini.">?</span>
            </form>
            @include('jersey.master._filter-toolbar', ['searchId' => 'search-tipe', 'statusId' => 'filter-status-tipe', 'placeholder' => 'Cari nama tipe produk...'])
            <x-table bare>
                <thead><tr><th class="p-2.5 text-left">Nama</th><th class="p-2.5 text-center w-20">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-50" id="tbody-tipe" data-empty-colspan="2" data-empty-text="Belum ada tipe produk">
                    @forelse($productTypes as $pt)
                        @include('jersey.master._row-simple', ['row' => $pt, 'entity' => 'product-types'])
                    @empty
                    <tr class="row-empty"><td colspan="2"><x-empty-state title="Belum ada tipe produk" /></td></tr>
                    @endforelse
                </tbody>
            </x-table>
        </x-card>
    </div>

    {{-- KATEGORI --}}
    <div id="tab-panel-kategori" class="jersey-tab-panel hidden">
        <x-card>
            <form id="form-add-kategori" class="flex flex-col sm:flex-row gap-3 mb-6 bg-indigo-50/40 p-4 rounded-2xl border border-indigo-100">
                <div class="flex-1"><input type="text" name="name" required placeholder="Nama kategori (mis. Jersey Futsal)" class="w-full bg-white border border-slate-200 rounded-xl py-3 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"></div>
                <x-button type="submit" variant="success">Tambah</x-button>
            </form>
            @include('jersey.master._filter-toolbar', ['searchId' => 'search-kategori', 'statusId' => 'filter-status-kategori', 'placeholder' => 'Cari nama kategori...'])
            <x-table bare>
                <thead><tr><th class="p-2.5 text-left">Nama</th><th class="p-2.5 text-center w-20">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-50" id="tbody-kategori" data-empty-colspan="2" data-empty-text="Belum ada kategori">
                    @forelse($categories as $cat)
                        @include('jersey.master._row-simple', ['row' => $cat, 'entity' => 'categories'])
                    @empty
                    <tr class="row-empty"><td colspan="2"><x-empty-state title="Belum ada kategori" /></td></tr>
                    @endforelse
                </tbody>
            </x-table>
        </x-card>
    </div>

    {{-- UKURAN --}}
    <div id="tab-panel-ukuran" class="jersey-tab-panel hidden">
        <x-card>
            <form id="form-add-ukuran" class="grid grid-cols-1 md:grid-cols-6 gap-3 mb-6 bg-indigo-50/40 p-4 rounded-2xl border border-indigo-100">
                <input type="text" name="name" required placeholder="Nama ukuran (mis. XXL)" class="bg-white border border-slate-200 rounded-xl py-3 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                <input type="number" name="sort_order" placeholder="Urutan tampil" class="bg-white border border-slate-200 rounded-xl py-3 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                <select name="kelompok" class="bg-white border border-slate-200 rounded-xl py-3 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"><option value="dewasa">Dewasa</option><option value="anak">Anak</option></select>
                <input type="text" inputmode="numeric" name="price_surcharge" placeholder="Tambahan harga (Rp)" class="format-ribuan bg-white border border-slate-200 rounded-xl py-3 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                <input type="number" step="0.5" min="-90" max="100" name="selisih_persen" placeholder="Selisih harga % (mis. -20)" class="bg-white border border-slate-200 rounded-xl py-3 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                <x-button type="submit" variant="success">Tambah Ukuran</x-button>
            </form>
            @include('jersey.master._filter-toolbar', ['searchId' => 'search-ukuran', 'statusId' => 'filter-status-ukuran', 'placeholder' => 'Cari nama ukuran...'])
            <x-table bare>
                <thead><tr><th class="p-2.5 text-left">Nama</th><th class="p-2.5 text-center">Kelompok</th><th class="p-2.5 text-center">Urutan</th><th class="p-2.5 text-right">Tambahan Harga</th><th class="p-2.5 text-right">Selisih %</th><th class="p-2.5 text-center w-20">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-50" id="tbody-ukuran" data-empty-colspan="6" data-empty-text="Belum ada ukuran">
                    @forelse($sizes as $s)
                        @include('jersey.master._row-size', ['row' => $s])
                    @empty
                    <tr class="row-empty"><td colspan="6"><x-empty-state title="Belum ada ukuran" /></td></tr>
                    @endforelse
                </tbody>
            </x-table>
        </x-card>
    </div>

    {{-- BAHAN --}}
    <div id="tab-panel-bahan" class="jersey-tab-panel hidden">
        <x-card>
            <form id="form-add-bahan" class="flex flex-col sm:flex-row gap-3 mb-6 bg-indigo-50/40 p-4 rounded-2xl border border-indigo-100">
                <div class="flex-1"><input type="text" name="name" required placeholder="Nama bahan (mis. Dryfit)" class="w-full bg-white border border-slate-200 rounded-xl py-3 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"></div>
                <x-button type="submit" variant="success">Tambah</x-button>
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-200 text-slate-500 text-[10px] font-medium cursor-help shrink-0 self-center" title="Daftar bahan riil (mis. Dryfit, Cotton Combed) yang dipilih saat membuat baris barang di order - bukan lagi diketik bebas.">?</span>
            </form>
            @include('jersey.master._filter-toolbar', ['searchId' => 'search-bahan', 'statusId' => 'filter-status-bahan', 'placeholder' => 'Cari nama bahan...'])
            <x-table bare>
                <thead><tr><th class="p-2.5 text-left">Nama</th><th class="p-2.5 text-center w-20">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-50" id="tbody-bahan" data-empty-colspan="2" data-empty-text="Belum ada bahan">
                    @forelse($materials as $m)
                        @include('jersey.master._row-simple', ['row' => $m, 'entity' => 'materials'])
                    @empty
                    <tr class="row-empty"><td colspan="2"><x-empty-state title="Belum ada bahan" /></td></tr>
                    @endforelse
                </tbody>
            </x-table>
        </x-card>

        {{-- SINKRON dengan Master Bahan Baku: stok & harga per kg satu sumber --}}
        <x-card class="mt-6">
            <h3 class="text-sm font-medium text-slate-700 mb-1 flex items-center gap-1.5">Sinkron dengan Master Bahan Baku <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-200 text-slate-500 text-[10px] font-medium cursor-help shrink-0" title="Hubungkan tiap bahan jersey ke bahan baku di gudang, sehingga stok dan harga beli per kg mengikuti Master Bahan (satu sumber).">?</span></h3>
            <div class="divide-y divide-slate-100">
                @foreach($materials as $m)
                <div class="py-3 flex flex-wrap items-center gap-3 {{ $m->is_active ? '' : 'opacity-50' }}">
                    <span class="w-40 text-sm font-medium text-slate-700">{{ $m->name }}</span>
                    <select onchange="sinkronBahanJersey({{ $m->id }}, this.value)" class="flex-1 min-w-[240px] bg-white border border-slate-200 rounded-md py-2 px-4 text-sm ">
                        <option value="">- Belum disinkronkan -</option>
                        @foreach($bahanGudang as $bg)
                            <option value="{{ $bg->id_bahan }}" {{ (int) $m->id_bahan === (int) $bg->id_bahan ? 'selected' : '' }}>{{ $bg->nama_bahan }} ({{ $bg->kode_bahan }})</option>
                        @endforeach
                    </select>
                    <span id="info-sinkron-{{ $m->id }}" class="text-xs text-slate-500 min-w-[220px]">
                        @if($m->bahan)
                            Stok <b>{{ angka((float) $m->bahan->stok, 2) }} {{ $m->bahan->satuan_stok }}</b> &middot; Rp {{ number_format((float) $m->bahan->harga_rata_rata, 0, ',', '.') }}/{{ $m->bahan->satuan_stok }}
                        @endif
                    </span>
                </div>
                @endforeach
            </div>
        </x-card>
    </div>

    {{-- TIPE LENGAN --}}
    <div id="tab-panel-lengan" class="jersey-tab-panel hidden">
        <x-card>
            <form id="form-add-lengan" class="flex flex-col sm:flex-row gap-3 mb-6 bg-indigo-50/40 p-4 rounded-2xl border border-indigo-100">
                <div class="flex-1"><input type="text" name="name" required placeholder="Nama tipe lengan (mis. 3/4 Lengan)" class="w-full bg-white border border-slate-200 rounded-xl py-3 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"></div>
                <div class="sm:w-48"><input type="text" inputmode="numeric" name="price_surcharge" placeholder="Tambahan harga (Rp)" class="format-ribuan w-full bg-white border border-slate-200 rounded-xl py-3 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"></div>
                <x-button type="submit" variant="success">Tambah</x-button>
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-200 text-slate-500 text-[10px] font-medium cursor-help shrink-0 self-center" title="Tipe lengan riil (mis. Pendek, Panjang, 3/4) yang dipilih saat membuat order - dipakai sebagai acuan estimasi bahan, bukan lagi pilihan tetap.">?</span>
            </form>
            @include('jersey.master._filter-toolbar', ['searchId' => 'search-lengan', 'statusId' => 'filter-status-lengan', 'placeholder' => 'Cari tipe lengan...'])
            <x-table bare>
                <thead><tr><th class="p-2.5 text-left">Nama</th><th class="p-2.5 text-center w-20">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-50" id="tbody-lengan" data-empty-colspan="2" data-empty-text="Belum ada tipe lengan">
                    @forelse($sleeveTypes as $st)
                        @include('jersey.master._row-simple', ['row' => $st, 'entity' => 'sleeve-types'])
                    @empty
                    <tr class="row-empty"><td colspan="2"><x-empty-state title="Belum ada tipe lengan" /></td></tr>
                    @endforelse
                </tbody>
            </x-table>
        </x-card>
    </div>

    {{-- TIPE KERAH --}}
    <div id="tab-panel-kerah" class="jersey-tab-panel hidden">
        <x-card>
            <form id="form-add-kerah" class="flex flex-col sm:flex-row gap-3 mb-6 bg-indigo-50/40 p-4 rounded-2xl border border-indigo-100">
                <div class="flex-1"><input type="text" name="name" required placeholder="Nama tipe kerah (mis. V-Neck)" class="w-full bg-white border border-slate-200 rounded-xl py-3 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"></div>
                <div class="sm:w-48"><input type="text" inputmode="numeric" name="price_surcharge" placeholder="Tambahan harga (Rp)" class="format-ribuan w-full bg-white border border-slate-200 rounded-xl py-3 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"></div>
                <x-button type="submit" variant="success">Tambah</x-button>
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-200 text-slate-500 text-[10px] font-medium cursor-help shrink-0 self-center" title="Tipe kerah riil (mis. O-Neck, V-Neck) yang dipilih saat membuat order - dipakai sebagai acuan estimasi bahan, bukan lagi diketik bebas.">?</span>
            </form>
            @include('jersey.master._filter-toolbar', ['searchId' => 'search-kerah', 'statusId' => 'filter-status-kerah', 'placeholder' => 'Cari tipe kerah...'])
            <x-table bare>
                <thead><tr><th class="p-2.5 text-left">Nama</th><th class="p-2.5 text-center w-20">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-50" id="tbody-kerah" data-empty-colspan="2" data-empty-text="Belum ada tipe kerah">
                    @forelse($collarTypes as $ct)
                        @include('jersey.master._row-simple', ['row' => $ct, 'entity' => 'collar-types'])
                    @empty
                    <tr class="row-empty"><td colspan="2"><x-empty-state title="Belum ada tipe kerah" /></td></tr>
                    @endforelse
                </tbody>
            </x-table>
        </x-card>
    </div>

    {{-- ESTIMASI BAHAN (matriks ukuran x komponen) --}}
    <div id="tab-panel-estimasi" class="jersey-tab-panel hidden">
        <x-card>
            <form id="form-add-komponen" class="flex flex-col sm:flex-row gap-3 mb-3 bg-indigo-50/40 p-4 rounded-2xl border border-indigo-100">
                <div class="flex-1"><input type="text" name="name" required placeholder="Nama komponen baru (mis. Kerah)" class="w-full bg-white border border-slate-200 rounded-xl py-3 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"></div>
                <select name="jersey_product_type_id" class="sm:w-44 bg-white border border-slate-200 rounded-xl py-3 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    <option value="">-- Semua Tipe Produk --</option>
                    @foreach($productTypes->where('is_active', true) as $pt)<option class="opsi-tipe" value="{{ $pt->id }}">{{ $pt->name }}</option>@endforeach
                </select>
                <select name="jersey_category_id" class="sm:w-44 bg-white border border-slate-200 rounded-xl py-3 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    <option value="">-- Semua Kategori --</option>
                    @foreach($categories->where('is_active', true) as $cat)<option class="opsi-kategori" value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach
                </select>
                <x-button type="submit" variant="success">Tambah Komponen</x-button>
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-200 text-slate-500 text-[10px] font-medium cursor-help shrink-0 self-center" title="Estimasi kebutuhan bahan per ukuran, per komponen (Body/Lengan/Rib/Celana, dst) - dalam meter DAN berat (gram/kg) - dipakai untuk menghitung otomatis &quot;Spesifikasi Order&quot; saat order dibuat. Murni estimasi, bukan pemotongan stok riil. Kosongkan sel yang belum diketahui angkanya. Kolom Total menjumlahkan seluruh komponen aktif per ukuran - perkiraan total bahan untuk SATU baju ukuran itu.">?</span>
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-200 text-slate-500 text-[10px] font-medium cursor-help shrink-0 self-center" title="Kosongkan Tipe Produk/Kategori kalau komponen berlaku untuk SEMUA barang (mis. Body). Isi salah satu/keduanya kalau komponen cuma relevan untuk barang tertentu (mis. komponen &quot;Celana&quot; cuma untuk Tipe Produk Celana) - supaya SPK cuma menampilkan estimasi yang benar-benar dipakai barangnya.">?</span>
            </form>
            @include('jersey.master._filter-toolbar', ['searchId' => 'search-estimasi', 'placeholder' => 'Cari nama ukuran...'])

            <p class="text-[11px] font-medium text-indigo-600 uppercase tracking-widest mb-2 mt-2">Estimasi Meter</p>
            <div class="modern-table-wrapper overflow-x-auto mb-6">
                <table class="modern-table w-full border-collapse" id="tabel-estimasi">
                    <thead>
                        <tr>
                            <th class="p-2.5 text-left sticky left-0 bg-white z-10">Ukuran</th>
                            @foreach($components->where('is_active', true) as $comp)
                            <th class="p-2.5 text-center whitespace-nowrap" data-component-th="{{ $comp->id }}">
                                {{ $comp->name }} <span class="text-slate-500 font-normal">(m)</span>
                                <button type="button" onclick="bukaEditScopeKomponen({{ $comp->id }}, {{ $comp->jersey_product_type_id ?? 'null' }}, {{ $comp->jersey_category_id ?? 'null' }})" class="ml-1 text-slate-500 hover:text-indigo-500" title="Atur cakupan Tipe Produk/Kategori"><i class="fas fa-sliders-h"></i></button>
                                <button type="button" onclick="nonaktifkanKomponen({{ $comp->id }})" class="ml-1 text-slate-500 hover:text-rose-500" title="Nonaktifkan komponen ini"><i class="fas fa-times-circle"></i></button>
                                <div class="text-[9px] font-normal normal-case text-slate-400 mt-0.5" data-component-scope="{{ $comp->id }}">{{ $comp->productType->name ?? 'Semua Tipe' }} @if($comp->category)&middot; {{ $comp->category->name }}@endif</div>
                            </th>
                            @endforeach
                            <th class="p-2.5 text-center whitespace-nowrap bg-indigo-50 text-indigo-600">Total (m)</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-estimasi-meter">
                        @foreach($sizes as $s)
                        <tr data-size-id="{{ $s->id }}">
                            <td class="p-2.5 font-medium sticky left-0 bg-white">{{ $s->name }}</td>
                            @foreach($components->where('is_active', true) as $comp)
                            @php $nilaiUsage = optional(optional($usageMatrix[$s->id] ?? null)[$comp->id] ?? null)->usage_meter; @endphp
                            <td class="p-1" data-component-td="{{ $comp->id }}">
                                <input type="number" step="0.01" min="0" value="{{ $nilaiUsage }}"
                                    onchange="simpanEstimasi({{ $s->id }}, {{ $comp->id }}, 'meter', this.value)"
                                    class="input-meter-cell w-20 bg-slate-50 border-none rounded-lg p-2 text-xs text-center focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            </td>
                            @endforeach
                            <td class="p-2.5 text-center font-medium text-indigo-600 bg-indigo-50/40 total-meter-cell">{{ $totalPerSize[$s->id]['meter_text'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="text-[11px] font-medium text-indigo-600 uppercase tracking-widest mb-2">Estimasi Berat (gram)</p>
            <div class="modern-table-wrapper overflow-x-auto">
                <table class="modern-table w-full border-collapse" id="tabel-estimasi-berat-tbl">
                    <thead>
                        <tr>
                            <th class="p-2.5 text-left sticky left-0 bg-white z-10">Ukuran</th>
                            @foreach($components->where('is_active', true) as $comp)
                            <th class="p-2.5 text-center whitespace-nowrap" data-component-th-berat="{{ $comp->id }}">
                                {{ $comp->name }} <span class="text-slate-500 font-normal">(g)</span>
                            </th>
                            @endforeach
                            <th class="p-2.5 text-center whitespace-nowrap bg-indigo-50 text-indigo-600">Total (Berat)</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-estimasi-berat">
                        @foreach($sizes as $s)
                        <tr data-size-id="{{ $s->id }}">
                            <td class="p-2.5 font-medium sticky left-0 bg-white">{{ $s->name }}</td>
                            @foreach($components->where('is_active', true) as $comp)
                            @php $nilaiBerat = optional(optional($usageMatrix[$s->id] ?? null)[$comp->id] ?? null)->usage_weight_gram; @endphp
                            <td class="p-1" data-component-td-berat="{{ $comp->id }}">
                                <input type="number" step="1" min="0" value="{{ $nilaiBerat }}"
                                    onchange="simpanEstimasi({{ $s->id }}, {{ $comp->id }}, 'weight', this.value)"
                                    class="input-berat-cell w-20 bg-slate-50 border-none rounded-lg p-2 text-xs text-center focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            </td>
                            @endforeach
                            <td class="p-2.5 text-center font-medium text-indigo-600 bg-indigo-50/40 total-berat-cell">{{ $totalPerSize[$s->id]['berat_text'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-100 mt-6 pt-6">
                <p class="text-xs text-slate-400 mb-4">Estimasi bahan untuk Tipe Lengan & Tipe Kerah - kelola daftar tipenya di tab <b class="text-slate-500">Tipe Lengan</b>/<b class="text-slate-500">Tipe Kerah</b>, isi angka pemakaiannya di sini per ukuran. Order mode Daftar Pemain/Campuran memakai pilihan lengan/kerah masing-masing pemain; mode Qty per Ukuran memakai pilihan default di level barang.</p>

                <p class="text-[11px] font-medium text-indigo-600 uppercase tracking-widest mb-2">Estimasi Lengan (meter &amp; gram)</p>
                <div class="modern-table-wrapper overflow-x-auto mb-6">
                    <table class="modern-table w-full border-collapse" id="tabel-estimasi-lengan">
                        <thead>
                            <tr>
                                <th class="p-2.5 text-left sticky left-0 bg-white z-10">Ukuran</th>
                                @foreach($sleeveTypes->where('is_active', true) as $st)
                                <th class="p-2.5 text-center whitespace-nowrap" data-type-th="{{ $st->id }}">{{ $st->name }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sleeveTypes->where('is_active', true) as $x)@break @empty
                            <tr><td class="p-3 text-center text-slate-500 italic">Belum ada Tipe Lengan aktif - tambah dulu di tab Tipe Lengan.</td></tr>
                            @endforelse
                            @if($sleeveTypes->where('is_active', true)->isNotEmpty())
                            @foreach($sizes as $s)
                            <tr data-size-id="{{ $s->id }}">
                                <td class="p-2.5 font-medium sticky left-0 bg-white">{{ $s->name }}</td>
                                @foreach($sleeveTypes->where('is_active', true) as $st)
                                @php $u = optional($sleeveUsageMatrix[$s->id] ?? null)[$st->id] ?? null; @endphp
                                <td class="p-1" data-type-td="{{ $st->id }}">
                                    <input type="number" step="0.01" min="0" value="{{ $u->usage_meter ?? '' }}" placeholder="m"
                                        onchange="simpanEstimasiLengan({{ $s->id }}, {{ $st->id }}, 'meter', this.value)"
                                        class="w-16 bg-slate-50 border-none rounded-lg p-2 text-xs text-center focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 mb-1">
                                    <input type="number" step="1" min="0" value="{{ $u->usage_weight_gram ?? '' }}" placeholder="g"
                                        onchange="simpanEstimasiLengan({{ $s->id }}, {{ $st->id }}, 'weight', this.value)"
                                        class="w-16 bg-slate-50 border-none rounded-lg p-2 text-xs text-center focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                </td>
                                @endforeach
                            </tr>
                            @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>

                <p class="text-[11px] font-medium text-indigo-600 uppercase tracking-widest mb-2">Estimasi Kerah (meter &amp; gram)</p>
                <div class="modern-table-wrapper overflow-x-auto">
                    <table class="modern-table w-full border-collapse" id="tabel-estimasi-kerah">
                        <thead>
                            <tr>
                                <th class="p-2.5 text-left sticky left-0 bg-white z-10">Ukuran</th>
                                @foreach($collarTypes->where('is_active', true) as $ct)
                                <th class="p-2.5 text-center whitespace-nowrap" data-type-th="{{ $ct->id }}">{{ $ct->name }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($collarTypes->where('is_active', true) as $x)@break @empty
                            <tr><td class="p-3 text-center text-slate-500 italic">Belum ada Tipe Kerah aktif - tambah dulu di tab Tipe Kerah.</td></tr>
                            @endforelse
                            @if($collarTypes->where('is_active', true)->isNotEmpty())
                            @foreach($sizes as $s)
                            <tr data-size-id="{{ $s->id }}">
                                <td class="p-2.5 font-medium sticky left-0 bg-white">{{ $s->name }}</td>
                                @foreach($collarTypes->where('is_active', true) as $ct)
                                @php $u = optional($collarUsageMatrix[$s->id] ?? null)[$ct->id] ?? null; @endphp
                                <td class="p-1" data-type-td="{{ $ct->id }}">
                                    <input type="number" step="0.01" min="0" value="{{ $u->usage_meter ?? '' }}" placeholder="m"
                                        onchange="simpanEstimasiKerah({{ $s->id }}, {{ $ct->id }}, 'meter', this.value)"
                                        class="w-16 bg-slate-50 border-none rounded-lg p-2 text-xs text-center focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 mb-1">
                                    <input type="number" step="1" min="0" value="{{ $u->usage_weight_gram ?? '' }}" placeholder="g"
                                        onchange="simpanEstimasiKerah({{ $s->id }}, {{ $ct->id }}, 'weight', this.value)"
                                        class="w-16 bg-slate-50 border-none rounded-lg p-2 text-xs text-center focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                </td>
                                @endforeach
                            </tr>
                            @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </x-card>
    </div>

    {{-- TIER HARGA --}}
    <div id="tab-panel-harga" class="jersey-tab-panel hidden">
        <x-card>
            {{-- INPUT TIER HARGA (satu pintu) --}}
            <form id="form-add-harga" class="grid grid-cols-1 md:grid-cols-6 gap-3 mb-6 bg-indigo-50/40 p-4 rounded-2xl border border-indigo-100">
                <div class="md:col-span-2">
                    <label class="block text-[9px] font-medium text-indigo-600 uppercase tracking-widest mb-1.5">Bahan</label>
                    <select name="jersey_material_id" id="harga-select-bahan" class="w-full bg-white border border-slate-200 rounded-xl py-3 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        <option value="">-- Semua Bahan --</option>
                        @foreach($materials->where('is_active', true) as $m)<option class="opsi-bahan" value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[9px] font-medium text-slate-400 uppercase tracking-widest mb-1.5">Tipe Produk</label>
                    <select name="jersey_product_type_id" id="harga-select-tipe" class="w-full bg-white border border-slate-200 rounded-xl py-3 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        <option value="">-- Semua Tipe --</option>
                        @foreach($productTypes->where('is_active', true) as $pt)<option class="opsi-tipe" value="{{ $pt->id }}">{{ $pt->name }}</option>@endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[9px] font-medium text-slate-400 uppercase tracking-widest mb-1.5">Kategori</label>
                    <select name="jersey_category_id" id="harga-select-kategori" class="w-full bg-white border border-slate-200 rounded-xl py-3 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        <option value="">-- Semua Kategori --</option>
                        @foreach($categories->where('is_active', true) as $cat)<option class="opsi-kategori" value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[9px] font-medium text-slate-400 uppercase tracking-widest mb-1.5">Kelompok</label>
                    <select name="kelompok" class="w-full bg-white border border-slate-200 rounded-xl py-3 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        <option value="dewasa">Dewasa</option>
                        <option value="anak">Anak</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[9px] font-medium text-slate-400 uppercase tracking-widest mb-1.5">Golongan (opsional)</label>
                    <select name="id_golongan" class="w-full bg-white border border-slate-200 rounded-xl py-3 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        <option value="">-- Semua Golongan --</option>
                        @foreach(\App\Models\GolonganKonsumen::orderBy('nama_golongan')->get() as $g)<option value="{{ $g->id_golongan }}">{{ $g->nama_golongan }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[9px] font-medium text-slate-400 uppercase tracking-widest mb-1.5">Qty Min</label>
                    <input type="number" name="qty_min" placeholder="1" required min="1" class="w-full bg-white border border-slate-200 rounded-xl py-3 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-[9px] font-medium text-slate-400 uppercase tracking-widest mb-1.5">Qty Max</label>
                    <input type="number" name="qty_max" placeholder="~ (kosongkan)" min="1" class="w-full bg-white border border-slate-200 rounded-xl py-3 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[9px] font-medium text-slate-400 uppercase tracking-widest mb-1.5">Harga per Pcs (Rp)</label>
                    <input type="text" inputmode="numeric" name="price" placeholder="0" required class="format-ribuan w-full bg-white border border-slate-200 rounded-xl py-3 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                </div>
                <div class="md:col-span-2 flex items-end">
                    <x-button type="submit" variant="success" class="w-full">
                        <i class="fas fa-plus"></i> Tambah Tier
                    </x-button>
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-200 text-slate-500 text-[10px] font-medium cursor-help shrink-0 self-center" title="Harga sekarang berpusat pada Bahan - tiap bahan bisa punya tier qty & harga sendiri karena selisih harga belanja antar bahan bisa sangat jauh. Kosongkan Bahan/Tipe Produk/Kategori untuk membuat tier &quot;berlaku umum&quot; (fallback kalau tidak ada tier spesifik yang cocok). Pencarian harga otomatis memilih tier paling spesifik yang cocok dengan bahan & qty tiap baris barang.">?</span>
                </div>
            </form>
            <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 mb-3">
                <div class="flex-1 min-w-0 relative">
                    <i class="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" id="cari-harga-text" placeholder="Cari bahan, tipe, kategori atau harga..." autocomplete="off" class="w-full bg-white border border-slate-200 rounded-xl h-10 pl-9 pr-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                </div>
                <button type="button" id="btn-sort-harga" class="shrink-0 inline-flex items-center gap-2 h-10 px-3 rounded-xl bg-slate-100 text-slate-500 border border-slate-200 text-xs font-semibold hover:bg-slate-200 transition-colors" title="Urutkan harga dari terkecil (klik lagi: terbesar)"><i class="fas fa-arrow-down-short-wide text-xs"></i><span>Urut Harga</span></button>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                <select id="filter-harga-bahan" class="bg-white border border-slate-200 rounded-xl py-2.5 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    <option value="">Semua Bahan</option>
                    @foreach($materials->where('is_active', true) as $m)<option class="opsi-bahan" value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
                </select>
                <select id="filter-harga-tipe" class="bg-white border border-slate-200 rounded-xl py-2.5 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    <option value="">Semua Tipe</option>
                    @foreach($productTypes->where('is_active', true) as $pt)<option class="opsi-tipe" value="{{ $pt->id }}">{{ $pt->name }}</option>@endforeach
                </select>
                <select id="filter-harga-kategori" class="bg-white border border-slate-200 rounded-xl py-2.5 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    <option value="">Semua Kategori</option>
                    @foreach($categories->where('is_active', true) as $cat)<option class="opsi-kategori" value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach
                </select>
                <button type="button" onclick="resetFilterHarga()" class="flex items-center justify-center gap-2 bg-slate-100 text-slate-500 rounded-xl py-2.5 px-3 text-xs font-medium uppercase tracking-widest hover:bg-rose-50 hover:text-rose-500 transition-all">
                    <i class="fas fa-undo-alt text-[10px]"></i> Reset Filter
                </button>
            </div>
            <x-table bare>
                <thead><tr>
                    <th class="p-2.5 text-left cursor-pointer select-none hover:text-slate-600" data-sort-th="bahan">Bahan <i class="fas fa-sort text-[10px] text-slate-400 ml-0.5"></i></th>
                    <th class="p-2.5 text-left cursor-pointer select-none hover:text-slate-600" data-sort-th="tipe">Tipe <i class="fas fa-sort text-[10px] text-slate-400 ml-0.5"></i></th>
                    <th class="p-2.5 text-left cursor-pointer select-none hover:text-slate-600" data-sort-th="kategori">Kategori <i class="fas fa-sort text-[10px] text-slate-400 ml-0.5"></i></th>
                    <th class="p-2.5 text-center cursor-pointer select-none hover:text-slate-600" data-sort-th="qty">Qty <i class="fas fa-sort text-[10px] text-slate-400 ml-0.5"></i></th>
                    <th class="p-2.5 text-right cursor-pointer select-none hover:text-slate-600" data-sort-th="harga">Harga/pcs <i class="fas fa-sort text-[10px] text-slate-400 ml-0.5"></i></th>
                    <th class="p-2.5 text-center w-20">Aksi</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-50" id="tbody-harga" data-empty-colspan="6" data-empty-text="Belum ada tier harga">
                    @forelse($priceSettings as $p)
                        @include('jersey.master._row-harga', ['row' => $p])
                    @empty
                    <tr class="row-empty"><td colspan="6"><x-empty-state title="Belum ada tier harga" description="Order tetap bisa dibuat, harga akan Rp 0 sampai tier diisi." /></td></tr>
                    @endforelse
                </tbody>
            </x-table>
        </x-card>
    </div>

    {{-- MODAL EDIT (dipakai bersama untuk desainer/tipe/kategori/bahan - field-nya sama, cuma "nama") --}}
    <x-modal id="edit-simple" title="Ubah Data" maxWidth="sm">
        <form id="form-edit-simple" class="space-y-4">
            <input type="hidden" id="edit-simple-entity">
            <input type="hidden" id="edit-simple-id">
            <div class="ld">
                <input placeholder=" " type="text" id="edit-simple-name" required class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
                <label class="ld-label" for="edit-simple-name">Nama<span class="ld-wajib" aria-hidden="true">*</span></label>
            </div>
            <div id="edit-simple-has-sleeve-collar-wrapper" class="hidden">
                <label class="flex items-center gap-2 text-xs font-medium text-slate-500 cursor-pointer">
                    <input type="checkbox" id="edit-simple-has-sleeve-collar" class="rounded text-indigo-600 focus:ring-indigo-500/20">
                    Punya Lengan &amp; Kerah
                </label>
            </div>
            <div id="edit-simple-price-surcharge-wrapper" class="ld hidden">
                <input placeholder=" " type="text" inputmode="numeric" id="edit-simple-price-surcharge" class="format-ribuan w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
                <label class="ld-label" for="edit-simple-price-surcharge">Tambahan Harga (Rp)</label>
            </div>
            <div class="pt-2 flex gap-3">
                <button type="button" onclick="closeModal('edit-simple')" class="flex-1 py-3 rounded-xl bg-slate-100 text-slate-500 text-xs font-medium uppercase">Batal</button>
                <button type="submit" class="flex-1 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium uppercase">Simpan</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="edit-komponen-scope" title="Cakupan Komponen" maxWidth="sm">
        <form id="form-edit-komponen-scope" class="space-y-4">
            <input type="hidden" id="edit-komponen-scope-id">
            <p class="text-xs text-slate-400">Kosongkan supaya komponen ini berlaku untuk semua barang. Isi salah satu/keduanya supaya komponen ini cuma dihitung &amp; ditampilkan di SPK barang dengan Tipe Produk/Kategori yang cocok.</p>
            <div class="ld">
                <select id="edit-komponen-scope-tipe" class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
                    <option value="">-- Semua Tipe Produk --</option>
                    @foreach($productTypes->where('is_active', true) as $pt)<option class="opsi-tipe" value="{{ $pt->id }}">{{ $pt->name }}</option>@endforeach
                </select>
                <label class="ld-label" for="edit-komponen-scope-tipe">Tipe Produk</label>
            </div>
            <div class="ld">
                <select id="edit-komponen-scope-kategori" class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
                    <option value="">-- Semua Kategori --</option>
                    @foreach($categories->where('is_active', true) as $cat)<option class="opsi-kategori" value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach
                </select>
                <label class="ld-label" for="edit-komponen-scope-kategori">Kategori</label>
            </div>
            <div class="pt-2 flex gap-3">
                <button type="button" onclick="closeModal('edit-komponen-scope')" class="flex-1 py-3 rounded-xl bg-slate-100 text-slate-500 text-xs font-medium uppercase">Batal</button>
                <button type="submit" class="flex-1 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium uppercase">Simpan</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="edit-ukuran" title="Ubah Ukuran" maxWidth="sm">
        <form id="form-edit-ukuran" class="space-y-4">
            <input type="hidden" id="edit-ukuran-id">
            <div class="ld">
                <input placeholder=" " type="text" id="edit-ukuran-name" required class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
                <label class="ld-label" for="edit-ukuran-name">Nama<span class="ld-wajib" aria-hidden="true">*</span></label>
            </div>
            <div class="ld">
                <input placeholder=" " type="number" id="edit-ukuran-sort" class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
                <label class="ld-label" for="edit-ukuran-sort">Urutan Tampil</label>
            </div>
            <div class="ld">
                <select id="edit-ukuran-kelompok" class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 "><option value="dewasa">Dewasa</option><option value="anak">Anak</option></select>
                <label class="ld-label" for="edit-ukuran-kelompok">Kelompok</label>
            </div>
            <div class="ld">
                <input type="number" step="0.5" min="-90" max="100" id="edit-ukuran-percent" placeholder="mis. -20 untuk diskon 20%" class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
                <label class="ld-label" for="edit-ukuran-percent">Selisih Harga (%) dari harga tier</label>
            </div>
            <div class="ld">
                <input placeholder=" " type="text" inputmode="numeric" id="edit-ukuran-surcharge" class="format-ribuan w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
                <label class="ld-label" for="edit-ukuran-surcharge">Tambahan Harga (Rp)</label>
            </div>
            <div class="pt-2 flex gap-3">
                <button type="button" onclick="closeModal('edit-ukuran')" class="flex-1 py-3 rounded-xl bg-slate-100 text-slate-500 text-xs font-medium uppercase">Batal</button>
                <button type="submit" class="flex-1 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium uppercase">Simpan</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="edit-harga" title="Ubah Tier Harga" maxWidth="sm">
        <form id="form-edit-harga" class="space-y-4">
            <input type="hidden" id="edit-harga-id">
            <div class="ld">
                <select id="edit-harga-bahan" class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
                    <option value="">-- Semua Bahan --</option>
                    @foreach($materials->where('is_active', true) as $m)<option class="opsi-bahan" value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
                </select>
                <label class="ld-label" for="edit-harga-bahan">Bahan</label>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="ld">
                    <select id="edit-harga-tipe" class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
                        <option value="">-- Semua --</option>
                        @foreach($productTypes->where('is_active', true) as $pt)<option class="opsi-tipe" value="{{ $pt->id }}">{{ $pt->name }}</option>@endforeach
                    </select>
                    <label class="ld-label" for="edit-harga-tipe">Tipe Produk</label>
                </div>
                <div class="ld">
                    <select id="edit-harga-kategori" class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
                        <option value="">-- Semua --</option>
                        @foreach($categories->where('is_active', true) as $cat)<option class="opsi-kategori" value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach
                    </select>
                    <label class="ld-label" for="edit-harga-kategori">Kategori</label>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="ld">
                    <input placeholder=" " type="number" id="edit-harga-qty-min" min="1" required class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
                    <label class="ld-label" for="edit-harga-qty-min">Qty Min<span class="ld-wajib" aria-hidden="true">*</span></label>
                </div>
                <div class="ld">
                    <input type="number" id="edit-harga-qty-max" min="1" placeholder="~ (kosongkan)" class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
                    <label class="ld-label" for="edit-harga-qty-max">Qty Max</label>
                </div>
            </div>
            <div class="ld">
                <input placeholder=" " type="text" inputmode="numeric" id="edit-harga-price" required class="format-ribuan w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
                <label class="ld-label" for="edit-harga-price">Harga per Pcs (Rp)<span class="ld-wajib" aria-hidden="true">*</span></label>
            </div>
            <div class="pt-2 flex gap-3">
                <button type="button" onclick="closeModal('edit-harga')" class="flex-1 py-3 rounded-xl bg-slate-100 text-slate-500 text-xs font-medium uppercase">Batal</button>
                <button type="submit" class="flex-1 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium uppercase">Simpan</button>
            </div>
        </form>
    </x-modal>
</div>

@push('scripts')
<script>
    function switchJerseyTab(key) {
        document.querySelectorAll('.jersey-tab-panel').forEach(el => el.classList.add('hidden'));
        document.getElementById('tab-panel-' + key).classList.remove('hidden');
        document.querySelectorAll('.jersey-tab-btn').forEach(btn => {
            btn.classList.remove('bg-indigo-50', 'text-indigo-600', 'border-indigo-100');
            btn.classList.add('bg-white', 'text-slate-400', 'border-slate-200');
        });
        const activeBtn = document.getElementById('tab-btn-' + key);
        activeBtn.classList.add('bg-indigo-50', 'text-indigo-600', 'border-indigo-100');
        activeBtn.classList.remove('bg-white', 'text-slate-400', 'border-slate-200');
    }

    const csrfTokenJerseyMaster = document.querySelector('meta[name="csrf-token"]').content;

    // Toast mengambang (tidak mendorong tabel naik-turun seperti sebelumnya)
    // - pola sama dengan Toast yang sudah dipakai di Kasir/Penjualan.
    const ToastJersey = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2200, timerProgressBar: true });
    function tampilkanToastJersey(message, sukses = true) {
        ToastJersey.fire({ icon: sukses ? 'success' : 'error', title: message });
    }

    async function kirimJson(url, method, payload) {
        const res = await fetch(url, {
            method,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfTokenJerseyMaster },
            body: JSON.stringify(payload),
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data || data.success === false) {
            tampilkanToastJersey((data && data.message) || 'Terjadi kesalahan.', false);
            return null;
        }
        return data;
    }

    function hapusBarisKosong(tbodyId) {
        const tbody = document.getElementById(tbodyId);
        const empty = tbody.querySelector('.row-empty');
        if (empty) empty.remove();
    }

    function escapeHtmlJersey(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    // ---- Entitas sederhana (desainer, tipe produk, kategori, bahan) - satu
    // fungsi dipakai untuk semuanya karena strukturnya identik. Baris pakai
    // opacity saat nonaktif + tombol ikon (edit pensil, toggle mata) -
    // konsisten dengan pola Master Bahan Baku yang sudah ada di aplikasi,
    // BUKAN badge status berwarna terpisah (makan tempat & beda gaya). ----
    const entityConfig = {
        'product-types': { tbody: 'tbody-tipe', form: 'form-add-tipe', routeBase: '{{ url("/jersey/master/product-types") }}', selectTarget: 'harga-select-tipe', search: 'search-tipe', status: 'filter-status-tipe', colspan: 2 },
        categories: { tbody: 'tbody-kategori', form: 'form-add-kategori', routeBase: '{{ url("/jersey/master/categories") }}', selectTarget: 'harga-select-kategori', search: 'search-kategori', status: 'filter-status-kategori', colspan: 2 },
        materials: { tbody: 'tbody-bahan', form: 'form-add-bahan', routeBase: '{{ url("/jersey/master/materials") }}', selectTarget: 'harga-select-bahan', search: 'search-bahan', status: 'filter-status-bahan', colspan: 2 },
        'sleeve-types': { tbody: 'tbody-lengan', form: 'form-add-lengan', routeBase: '{{ url("/jersey/master/sleeve-types") }}', search: 'search-lengan', status: 'filter-status-lengan', colspan: 2 },
        'collar-types': { tbody: 'tbody-kerah', form: 'form-add-kerah', routeBase: '{{ url("/jersey/master/collar-types") }}', search: 'search-kerah', status: 'filter-status-kerah', colspan: 2 },
    };

    // ---- Filter & pencarian (semua tab) - murni client-side, data tiap tab
    // sudah dimuat semua di halaman jadi tidak perlu roundtrip ke server
    // cuma untuk mencari/menyaring. Baris yang tidak cocok disembunyikan
    // (class hidden), bukan dihapus, supaya baris hasil tambah/ubah/toggle
    // lewat AJAX tidak perlu dibangun ulang. ----
    function terapkanFilterTabel(tbodyId, predicate, colspan) {
        const tbody = document.getElementById(tbodyId);
        if (!tbody) return;
        const rows = Array.from(tbody.querySelectorAll('tr[data-id], tr[data-size-id]'));
        let visibleCount = 0;
        rows.forEach(row => {
            const cocok = predicate(row);
            row.classList.toggle('hidden', !cocok);
            if (cocok) visibleCount++;
        });
        let noMatchRow = tbody.querySelector('.row-no-match');
        if (rows.length > 0 && visibleCount === 0) {
            if (!noMatchRow) {
                noMatchRow = document.createElement('tr');
                noMatchRow.className = 'row-no-match';
                noMatchRow.innerHTML = `<td colspan="${colspan}" class="p-6 text-center text-slate-500 text-xs italic"><i class="fas fa-search mr-2"></i>Tidak ada data yang cocok dengan pencarian/filter.</td>`;
                tbody.appendChild(noMatchRow);
            }
        } else if (noMatchRow) {
            noMatchRow.remove();
        }
    }

    function terapkanFilterEntitas(entity) {
        const cfg = entityConfig[entity];
        if (!cfg || !cfg.search) return;
        const q = (document.getElementById(cfg.search).value || '').toLowerCase().trim();
        const status = cfg.status ? document.getElementById(cfg.status).value : 'semua';
        terapkanFilterTabel(cfg.tbody, (row) => {
            const nama = row.querySelector('td')?.textContent.toLowerCase() ?? '';
            const cocokNama = !q || nama.includes(q);
            const isActive = !row.classList.contains('opacity-50');
            const cocokStatus = status === 'semua' || (status === 'aktif' && isActive) || (status === 'nonaktif' && !isActive);
            return cocokNama && cocokStatus;
        }, cfg.colspan);
    }

    Object.entries(entityConfig).forEach(([entity, cfg]) => {
        if (cfg.search) document.getElementById(cfg.search).addEventListener('input', () => terapkanFilterEntitas(entity));
        if (cfg.status) document.getElementById(cfg.status).addEventListener('change', () => terapkanFilterEntitas(entity));
    });

    function baseRowHtml(entity, row) {
        const badgeLenganKerah = (entity === 'product-types' && row.has_sleeve_collar === false)
            ? '<span class="ml-2 text-[9px] font-medium uppercase tracking-wide text-slate-400 bg-slate-100 rounded px-1.5 py-0.5">Tanpa Lengan/Kerah</span>' : '';
        const badgeSurcharge = (['sleeve-types', 'collar-types'].includes(entity) && Number(row.price_surcharge) > 0)
            ? `<span class="ml-2 text-[9px] font-medium uppercase tracking-wide text-emerald-600 bg-emerald-50 rounded px-1.5 py-0.5">+Rp ${Number(row.price_surcharge).toLocaleString('id-ID')}</span>` : '';
        return `
        <tr data-id="${row.id}" class="${row.is_active ? '' : 'opacity-50'}">
            <td class="p-2.5 font-medium">${escapeHtmlJersey(row.name)}${badgeLenganKerah}${badgeSurcharge}</td>
            <td class="p-2.5 text-center">
                <div class="flex items-center justify-center gap-1">
                    <button type="button" onclick="bukaEditSimple('${entity}', ${row.id}, ${JSON.stringify(row.name).replace(/"/g, '&quot;')}, ${row.has_sleeve_collar !== false}, ${Number(row.price_surcharge) || 0})" class="w-8 h-8 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50" title="Ubah"><i class="far fa-edit text-xs"></i></button>
                    <button type="button" onclick="toggleEntitas('${entity}', ${row.id})" class="w-8 h-8 rounded-lg text-slate-400 ${row.is_active ? 'hover:text-slate-600 hover:bg-slate-100' : 'hover:text-emerald-500 hover:bg-emerald-50'}" title="${row.is_active ? 'Nonaktifkan' : 'Aktifkan'}"><i class="far ${row.is_active ? 'fa-eye-slash' : 'fa-eye'} text-xs"></i></button>
                </div>
            </td>
        </tr>`;
    }

    Object.entries(entityConfig).forEach(([entity, cfg]) => {
        document.getElementById(cfg.form).addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const name = form.querySelector('[name="name"]').value.trim();
            if (!name) return;
            const payload = { name };
            if (entity === 'product-types') {
                payload.has_sleeve_collar = form.querySelector('[name="has_sleeve_collar"]').checked;
            }
            if (entity === 'sleeve-types' || entity === 'collar-types') {
                payload.price_surcharge = cleanRupiah(form.querySelector('[name="price_surcharge"]').value || '0');
            }
            const data = await kirimJson(cfg.routeBase, 'POST', payload);
            if (!data) return;
            hapusBarisKosong(cfg.tbody);
            document.getElementById(cfg.tbody).insertAdjacentHTML('beforeend', baseRowHtml(entity, data.row));
            form.reset();
            tampilkanToastJersey(data.message);
            terapkanFilterEntitas(entity);

            // Tipe Produk/Kategori baru langsung muncul di dropdown tier
            // harga TANPA reload - sebelumnya harus reload dulu baru
            // kelihatan (keluhan langsung dari user).
            sinkronOpsiSelect(entity, data.row.id, data.row.name, true);

            // Tipe Lengan/Kerah baru langsung jadi kolom baru di matriks
            // Estimasi Lengan/Kerah TANPA reload - sama seperti "Tambah
            // Komponen" di matriks Estimasi Bahan.
            if (entity === 'sleeve-types') tambahKolomEstimasiTipe('lengan', data.row);
            if (entity === 'collar-types') tambahKolomEstimasiTipe('kerah', data.row);
        });
    });

    // Tambah satu kolom (th + td berisi 2 input meter/gram per baris
    // ukuran) ke tabel Estimasi Lengan atau Estimasi Kerah - dipanggil
    // begitu Tipe Lengan/Kerah baru berhasil ditambahkan.
    function tambahKolomEstimasiTipe(jenis, row) {
        const table = document.getElementById(`tabel-estimasi-${jenis}`);
        if (!table) return;
        const fkField = jenis === 'lengan' ? 'jersey_sleeve_type_id' : 'jersey_collar_type_id';
        const fnName = jenis === 'lengan' ? 'simpanEstimasiLengan' : 'simpanEstimasiKerah';

        table.querySelector('thead tr').insertAdjacentHTML('beforeend', `<th class="p-2.5 text-center whitespace-nowrap" data-type-th="${row.id}">${escapeHtmlJersey(row.name)}</th>`);

        let tbody = table.querySelector('tbody');
        const belumAdaBarisUkuran = tbody.querySelectorAll('tr[data-size-id]').length === 0;
        if (belumAdaBarisUkuran) {
            // Matriks sebelumnya kosong ("Belum ada Tipe X aktif") - bangun
            // ulang seluruh baris ukuran sekarang tipe pertama sudah ada.
            const ukuranRows = @json($sizes->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]));
            tbody.innerHTML = ukuranRows.map(u => `
                <tr data-size-id="${u.id}">
                    <td class="p-2.5 font-medium sticky left-0 bg-white">${escapeHtmlJersey(u.name)}</td>
                </tr>`).join('');
        }
        tbody.querySelectorAll('tr[data-size-id]').forEach(tr => {
            const sizeId = tr.dataset.sizeId;
            tr.insertAdjacentHTML('beforeend', `
                <td class="p-1" data-type-td="${row.id}">
                    <input type="number" step="0.01" min="0" value="" placeholder="m"
                        onchange="${fnName}(${sizeId}, ${row.id}, 'meter', this.value)"
                        class="w-16 bg-slate-50 border-none rounded-lg p-2 text-xs text-center focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 mb-1">
                    <input type="number" step="1" min="0" value="" placeholder="g"
                        onchange="${fnName}(${sizeId}, ${row.id}, 'weight', this.value)"
                        class="w-16 bg-slate-50 border-none rounded-lg p-2 text-xs text-center focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                </td>`);
        });
    }

    function hapusKolomEstimasiTipe(jenis, id) {
        const table = document.getElementById(`tabel-estimasi-${jenis}`);
        if (!table) return;
        table.querySelector(`[data-type-th="${id}"]`)?.remove();
        table.querySelectorAll(`[data-type-td="${id}"]`).forEach(td => td.remove());
    }

    function bukaEditSimple(entity, id, name, hasSleeveCollar, priceSurcharge) {
        document.getElementById('edit-simple-entity').value = entity;
        document.getElementById('edit-simple-id').value = id;
        document.getElementById('edit-simple-name').value = name;
        const wrapperLenganKerah = document.getElementById('edit-simple-has-sleeve-collar-wrapper');
        wrapperLenganKerah.classList.toggle('hidden', entity !== 'product-types');
        if (entity === 'product-types') {
            document.getElementById('edit-simple-has-sleeve-collar').checked = hasSleeveCollar !== false;
        }
        const wrapperSurcharge = document.getElementById('edit-simple-price-surcharge-wrapper');
        const tampilkanSurcharge = entity === 'sleeve-types' || entity === 'collar-types';
        wrapperSurcharge.classList.toggle('hidden', !tampilkanSurcharge);
        if (tampilkanSurcharge) {
            document.getElementById('edit-simple-price-surcharge').value = formatRupiah(Math.round(parseFloat(priceSurcharge) || 0));
        }
        openModal('edit-simple');
    }

    document.getElementById('form-edit-simple').addEventListener('submit', async (e) => {
        e.preventDefault();
        const entity = document.getElementById('edit-simple-entity').value;
        const id = document.getElementById('edit-simple-id').value;
        const name = document.getElementById('edit-simple-name').value.trim();
        const cfg = entityConfig[entity];
        const payload = { name };
        if (entity === 'product-types') {
            payload.has_sleeve_collar = document.getElementById('edit-simple-has-sleeve-collar').checked;
        }
        if (entity === 'sleeve-types' || entity === 'collar-types') {
            payload.price_surcharge = cleanRupiah(document.getElementById('edit-simple-price-surcharge').value || '0');
        }
        const data = await kirimJson(`${cfg.routeBase}/${id}`, 'PUT', payload);
        if (!data) return;
        const row = document.querySelector(`#${cfg.tbody} tr[data-id="${id}"]`);
        if (row) row.outerHTML = baseRowHtml(entity, data.row);
        sinkronOpsiSelect(entity, id, data.row.name, !!data.row.is_active);
        closeModal('edit-simple');
        tampilkanToastJersey(data.message);
        terapkanFilterEntitas(entity);
    });

    // Semua dropdown Tipe Produk/Kategori/Bahan (tier harga, input cepat, edit,
    // filter, estimasi) ditandai kelas opsi-*; satu fungsi menyinkronkan semuanya
    // saat entitas ditambah, diubah nama, atau di-nonaktifkan/diaktifkan.
    const kelasOpsiEntitas = { 'product-types': 'opsi-tipe', categories: 'opsi-kategori', materials: 'opsi-bahan' };
    function sinkronOpsiSelect(entity, id, nama, aktif) {
        const kelas = kelasOpsiEntitas[entity];
        if (!kelas) return;
        document.querySelectorAll(`option.${kelas}[value="${id}"]`).forEach(o => { aktif ? (o.textContent = nama) : o.remove(); });
        if (!aktif) return;
        document.querySelectorAll('select').forEach(sel => {
            if (sel.querySelector(`option.${kelas}`) && !sel.querySelector(`option.${kelas}[value="${id}"]`)) {
                sel.insertAdjacentHTML('beforeend', `<option class="${kelas}" value="${id}">${escapeHtmlJersey(nama)}</option>`);
            }
        });
    }

    async function toggleEntitas(entity, id) {
        const cfg = entityConfig[entity];
        const data = await kirimJson(`${cfg.routeBase}/${id}/toggle`, 'PATCH', {});
        if (!data) return;
        const row = document.querySelector(`#${cfg.tbody} tr[data-id="${id}"]`);
        if (row) row.outerHTML = baseRowHtml(entity, data.row);
        // Tipe/Kategori yang dinonaktifkan langsung hilang dari pilihan tier
        // harga (sebelumnya tetap muncul walau sudah nonaktif - keluhan
        // langsung dari user), yang diaktifkan lagi langsung muncul lagi.
        sinkronOpsiSelect(entity, id, data.row.name, !!data.row.is_active);
        tampilkanToastJersey(data.message);
        terapkanFilterEntitas(entity);

        // Tipe Lengan/Kerah yang dinonaktifkan langsung hilang dari matriks
        // estimasi, yang diaktifkan lagi langsung muncul lagi - tanpa reload.
        if (entity === 'sleeve-types' || entity === 'collar-types') {
            const jenis = entity === 'sleeve-types' ? 'lengan' : 'kerah';
            if (data.row.is_active) {
                tambahKolomEstimasiTipe(jenis, data.row);
            } else {
                hapusKolomEstimasiTipe(jenis, id);
            }
        }
    }

    // ---- Sinkron bahan jersey <-> Master Bahan Baku ----
    async function sinkronBahanJersey(idMaterial, idBahan) {
        const data = await kirimJson(`{{ url('/jersey/master/materials') }}/${idMaterial}/bahan`, 'PATCH', { id_bahan: idBahan || null });
        if (!data) return;
        const info = document.getElementById('info-sinkron-' + idMaterial);
        info.innerHTML = data.bahan ? `Stok <b>${data.bahan.stok.toLocaleString('id-ID')} ${escHtml(data.bahan.satuan)}</b> &middot; Rp ${Math.round(data.bahan.harga).toLocaleString('id-ID')}/${escHtml(data.bahan.satuan)}` : '';
        tampilkanToastJersey(data.message);
    }

    // ---- Ukuran ----
    document.getElementById('form-add-ukuran').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const payload = {
            name: form.querySelector('[name="name"]').value.trim(),
            sort_order: form.querySelector('[name="sort_order"]').value || 0,
            kelompok: form.querySelector('[name="kelompok"]').value,
            price_percent: form.querySelector('[name="selisih_persen"]').value || 0,
            price_surcharge: cleanRupiah(form.querySelector('[name="price_surcharge"]').value),
        };
        if (!payload.name) return;
        const data = await kirimJson('{{ url("/jersey/master/sizes") }}', 'POST', payload);
        if (!data) return;
        hapusBarisKosong('tbody-ukuran');
        document.getElementById('tbody-ukuran').insertAdjacentHTML('beforeend', baseRowSizeHtml(data.row));
        form.reset();
        tampilkanToastJersey(data.message);
        terapkanFilterUkuran();
    });

    function terapkanFilterUkuran() {
        const q = (document.getElementById('search-ukuran').value || '').toLowerCase().trim();
        const status = document.getElementById('filter-status-ukuran').value;
        terapkanFilterTabel('tbody-ukuran', (row) => {
            const nama = row.querySelector('td')?.textContent.toLowerCase() ?? '';
            const cocokNama = !q || nama.includes(q);
            const isActive = !row.classList.contains('opacity-50');
            const cocokStatus = status === 'semua' || (status === 'aktif' && isActive) || (status === 'nonaktif' && !isActive);
            return cocokNama && cocokStatus;
        }, 4);
    }
    document.getElementById('search-ukuran').addEventListener('input', terapkanFilterUkuran);
    document.getElementById('filter-status-ukuran').addEventListener('change', terapkanFilterUkuran);

    function baseRowSizeHtml(row) {
        return `
        <tr data-id="${row.id}" class="${row.is_active ? '' : 'opacity-50'}">
            <td class="p-2.5 font-medium">${escapeHtmlJersey(row.name)}</td>
            <td class="p-2.5 text-center"><span class="px-2 py-0.5 rounded-md text-[10px] font-medium uppercase ${row.kelompok === 'anak' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-500'}">${row.kelompok === 'anak' ? 'Anak' : 'Dewasa'}</span></td>
            <td class="p-2.5 text-center">${row.sort_order}</td>
            <td class="p-2.5 text-right">Rp ${formatRupiah(Math.round(parseFloat(row.price_surcharge)))}</td>
            <td class="p-2.5 text-right ${parseFloat(row.price_percent) < 0 ? 'text-emerald-600 font-medium' : ''}">${parseFloat(row.price_percent) ? (parseFloat(row.price_percent) > 0 ? '+' : '') + parseFloat(row.price_percent) + '%' : '-'}</td>
            <td class="p-2.5 text-center">
                <div class="flex items-center justify-center gap-1">
                    <button type="button" onclick="bukaEditUkuran(${JSON.stringify(row).replace(/"/g, '&quot;')})" class="w-8 h-8 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50" title="Ubah"><i class="far fa-edit text-xs"></i></button>
                    <button type="button" onclick="toggleUkuran(${row.id})" class="w-8 h-8 rounded-lg text-slate-400 ${row.is_active ? 'hover:text-slate-600 hover:bg-slate-100' : 'hover:text-emerald-500 hover:bg-emerald-50'}" title="${row.is_active ? 'Nonaktifkan' : 'Aktifkan'}"><i class="far ${row.is_active ? 'fa-eye-slash' : 'fa-eye'} text-xs"></i></button>
                </div>
            </td>
        </tr>`;
    }

    function bukaEditUkuran(row) {
        document.getElementById('edit-ukuran-id').value = row.id;
        document.getElementById('edit-ukuran-name').value = row.name;
        document.getElementById('edit-ukuran-sort').value = row.sort_order;
        document.getElementById('edit-ukuran-kelompok').value = row.kelompok || 'dewasa';
        document.getElementById('edit-ukuran-percent').value = parseFloat(row.price_percent) || 0;
        document.getElementById('edit-ukuran-surcharge').value = formatRupiah(Math.round(parseFloat(row.price_surcharge)));
        openModal('edit-ukuran');
    }

    document.getElementById('form-edit-ukuran').addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('edit-ukuran-id').value;
        const payload = {
            name: document.getElementById('edit-ukuran-name').value.trim(),
            sort_order: document.getElementById('edit-ukuran-sort').value || 0,
            kelompok: document.getElementById('edit-ukuran-kelompok').value,
            price_percent: document.getElementById('edit-ukuran-percent').value || 0,
            price_surcharge: cleanRupiah(document.getElementById('edit-ukuran-surcharge').value),
        };
        const data = await kirimJson(`{{ url("/jersey/master/sizes") }}/${id}`, 'PUT', payload);
        if (!data) return;
        const row = document.querySelector(`#tbody-ukuran tr[data-id="${id}"]`);
        if (row) row.outerHTML = baseRowSizeHtml(data.row);
        closeModal('edit-ukuran');
        tampilkanToastJersey(data.message);
        terapkanFilterUkuran();
    });

    async function toggleUkuran(id) {
        const data = await kirimJson(`{{ url("/jersey/master/sizes") }}/${id}/toggle`, 'PATCH', {});
        if (!data) return;
        const row = document.querySelector(`#tbody-ukuran tr[data-id="${id}"]`);
        if (row) row.outerHTML = baseRowSizeHtml(data.row);
        tampilkanToastJersey(data.message);
        terapkanFilterUkuran();
    }

    // ---- Estimasi Bahan (matriks ukuran x komponen, meter & berat) ----
    // Label cakupan Tipe Produk/Kategori untuk badge di bawah nama komponen
    // - "Semua Tipe" kalau keduanya kosong, sama seperti teks server-render.
    function labelCakupanKomponen(row) {
        const namaTipe = row.jersey_product_type_id
            ? (document.querySelector(`#form-add-komponen [name="jersey_product_type_id"] option[value="${row.jersey_product_type_id}"]`)?.textContent ?? 'Tipe tertentu')
            : 'Semua Tipe';
        const namaKategori = row.jersey_category_id
            ? (document.querySelector(`#form-add-komponen [name="jersey_category_id"] option[value="${row.jersey_category_id}"]`)?.textContent ?? null)
            : null;
        return namaKategori ? `${escHtml(namaTipe)} &middot; ${escHtml(namaKategori)}` : escHtml(namaTipe);
    }

    document.getElementById('form-add-komponen').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const name = form.querySelector('[name="name"]').value.trim();
        if (!name) return;
        const payload = {
            name,
            jersey_product_type_id: form.querySelector('[name="jersey_product_type_id"]').value || null,
            jersey_category_id: form.querySelector('[name="jersey_category_id"]').value || null,
        };
        const data = await kirimJson('{{ url("/jersey/master/components") }}', 'POST', payload);
        if (!data) return;
        form.reset();
        tampilkanToastJersey(data.message);

        // Tambah kolom baru ke KEDUA tabel matriks (meter & berat) TANPA
        // reload - satu <th> di header + satu <td> berisi input di tiap
        // baris ukuran yang sudah ada, di masing-masing tabel.
        const headRowMeter = document.getElementById('tabel-estimasi').querySelector('thead tr');
        headRowMeter.insertAdjacentHTML('beforeend', `
            <th class="p-2.5 text-center whitespace-nowrap" data-component-th="${data.row.id}">
                ${escapeHtmlJersey(data.row.name)} <span class="text-slate-500 font-normal">(m)</span>
                <button type="button" onclick="bukaEditScopeKomponen(${data.row.id}, ${data.row.jersey_product_type_id ?? 'null'}, ${data.row.jersey_category_id ?? 'null'})" class="ml-1 text-slate-500 hover:text-indigo-500" title="Atur cakupan Tipe Produk/Kategori"><i class="fas fa-sliders-h"></i></button>
                <button type="button" onclick="nonaktifkanKomponen(${data.row.id})" class="ml-1 text-slate-500 hover:text-rose-500" title="Nonaktifkan komponen ini"><i class="fas fa-times-circle"></i></button>
                <div class="text-[9px] font-normal normal-case text-slate-400 mt-0.5" data-component-scope="${data.row.id}">${labelCakupanKomponen(data.row)}</div>
            </th>`);
        document.querySelectorAll('#tbody-estimasi-meter tr[data-size-id]').forEach(row => {
            const sizeId = row.dataset.sizeId;
            row.querySelector('.total-meter-cell')?.insertAdjacentHTML('beforebegin', `
                <td class="p-1" data-component-td="${data.row.id}">
                    <input type="number" step="0.01" min="0" value=""
                        onchange="simpanEstimasi(${sizeId}, ${data.row.id}, 'meter', this.value)"
                        class="input-meter-cell w-20 bg-slate-50 border-none rounded-lg p-2 text-xs text-center focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                </td>`);
        });

        const headRowBerat = document.getElementById('tabel-estimasi-berat-tbl').querySelector('thead tr');
        headRowBerat.insertAdjacentHTML('beforeend', `
            <th class="p-2.5 text-center whitespace-nowrap" data-component-th-berat="${data.row.id}">
                ${escapeHtmlJersey(data.row.name)} <span class="text-slate-500 font-normal">(g)</span>
            </th>`);
        document.querySelectorAll('#tbody-estimasi-berat tr[data-size-id]').forEach(row => {
            const sizeId = row.dataset.sizeId;
            row.querySelector('.total-berat-cell')?.insertAdjacentHTML('beforebegin', `
                <td class="p-1" data-component-td-berat="${data.row.id}">
                    <input type="number" step="1" min="0" value=""
                        onchange="simpanEstimasi(${sizeId}, ${data.row.id}, 'weight', this.value)"
                        class="input-berat-cell w-20 bg-slate-50 border-none rounded-lg p-2 text-xs text-center focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                </td>`);
        });
    });

    async function nonaktifkanKomponen(id) {
        const konfirmasi = await Swal.fire({
            title: 'Nonaktifkan komponen ini?',
            html: 'Kolomnya akan hilang dari matriks (meter & berat). <small class="text-slate-400">Data tersimpan, bisa dikembalikan lewat database bila perlu.</small>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#5D6A85',
            confirmButtonText: 'Ya, Nonaktifkan',
            cancelButtonText: 'Batal',
            customClass: { confirmButton: 'rounded-lg px-6 py-2.5', cancelButton: 'rounded-lg px-6 py-2.5 text-slate-600' },
        });
        if (!konfirmasi.isConfirmed) return;
        const data = await kirimJson(`{{ url("/jersey/master/components") }}/${id}/toggle`, 'PATCH', {});
        if (!data) return;
        document.querySelector(`[data-component-th="${id}"]`)?.remove();
        document.querySelectorAll(`[data-component-td="${id}"]`).forEach(td => td.remove());
        document.querySelector(`[data-component-th-berat="${id}"]`)?.remove();
        document.querySelectorAll(`[data-component-td-berat="${id}"]`).forEach(td => td.remove());
        document.querySelectorAll('#tbody-estimasi-meter tr[data-size-id]').forEach(row => recalcTotalEstimasi(row.dataset.sizeId));
        tampilkanToastJersey(data.message);
    }

    function bukaEditScopeKomponen(id, productTypeId, categoryId) {
        document.getElementById('edit-komponen-scope-id').value = id;
        document.getElementById('edit-komponen-scope-tipe').value = productTypeId ?? '';
        document.getElementById('edit-komponen-scope-kategori').value = categoryId ?? '';
        openModal('edit-komponen-scope');
    }

    document.getElementById('form-edit-komponen-scope').addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('edit-komponen-scope-id').value;
        const payload = {
            jersey_product_type_id: document.getElementById('edit-komponen-scope-tipe').value || null,
            jersey_category_id: document.getElementById('edit-komponen-scope-kategori').value || null,
        };
        const data = await kirimJson(`{{ url("/jersey/master/components") }}/${id}/scope`, 'PUT', payload);
        if (!data) return;
        document.querySelectorAll(`[data-component-scope="${id}"]`).forEach(el => {
            el.innerHTML = labelCakupanKomponen(data.row);
        });
        closeModal('edit-komponen-scope');
        tampilkanToastJersey(data.message);
    });

    function terapkanFilterEstimasi() {
        const q = (document.getElementById('search-estimasi').value || '').toLowerCase().trim();
        const kolomMeter = document.getElementById('tabel-estimasi').querySelector('thead tr').children.length;
        const kolomBerat = document.getElementById('tabel-estimasi-berat-tbl').querySelector('thead tr').children.length;
        const cocok = (row) => {
            const nama = row.querySelector('td')?.textContent.toLowerCase() ?? '';
            return !q || nama.includes(q);
        };
        terapkanFilterTabel('tbody-estimasi-meter', cocok, kolomMeter);
        terapkanFilterTabel('tbody-estimasi-berat', cocok, kolomBerat);
    }
    document.getElementById('search-estimasi').addEventListener('input', terapkanFilterEstimasi);

    // Format gram jadi teks siap tampil - cerminan dari
    // JerseyMaterialEstimationService::formatBeratGram() di PHP, supaya
    // total yang dihitung ulang secara live di browser (tanpa reload)
    // konsisten dengan yang dihitung server saat halaman dimuat pertama.
    function formatBeratJs(gram) {
        if (!gram || gram <= 0) return '-';
        if (gram >= 1000) return (gram / 1000).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' kg';
        return Math.round(gram).toLocaleString('id-ID') + ' g';
    }

    // Hitung ulang kolom Total (meter & berat) untuk satu baris ukuran,
    // langsung dari nilai input yang sedang tampil di layar - instan, tidak
    // menunggu respons server (server tetap jadi sumber kebenaran lewat
    // simpanEstimasi() yang berjalan terpisah di belakang layar).
    function recalcTotalEstimasi(sizeId) {
        let totalMeter = 0;
        document.querySelectorAll(`#tbody-estimasi-meter tr[data-size-id="${sizeId}"] .input-meter-cell`).forEach(i => {
            totalMeter += parseFloat(i.value) || 0;
        });
        const meterCell = document.querySelector(`#tbody-estimasi-meter tr[data-size-id="${sizeId}"] .total-meter-cell`);
        if (meterCell) meterCell.textContent = totalMeter > 0 ? totalMeter.toFixed(2).replace('.', ',') + ' m' : '-';

        let totalGram = 0;
        document.querySelectorAll(`#tbody-estimasi-berat tr[data-size-id="${sizeId}"] .input-berat-cell`).forEach(i => {
            totalGram += parseFloat(i.value) || 0;
        });
        const beratCell = document.querySelector(`#tbody-estimasi-berat tr[data-size-id="${sizeId}"] .total-berat-cell`);
        if (beratCell) beratCell.textContent = formatBeratJs(totalGram);
    }

    // Timer debounce PER SEL (bukan satu timer global) - supaya mengedit
    // meter lalu buru-buru mengedit berat pada sel lain tidak saling
    // membatalkan simpan otomatis masing-masing.
    const simpanEstimasiTimers = {};
    function simpanEstimasi(sizeId, componentId, field, value) {
        recalcTotalEstimasi(sizeId);
        const key = `${sizeId}-${componentId}-${field}`;
        clearTimeout(simpanEstimasiTimers[key]);
        simpanEstimasiTimers[key] = setTimeout(async () => {
            const payload = { jersey_size_id: sizeId, jersey_component_id: componentId };
            if (field === 'meter') payload.usage_meter = value === '' ? null : value;
            if (field === 'weight') payload.usage_weight_gram = value === '' ? null : value;
            const data = await kirimJson('{{ url("/jersey/master/usage") }}', 'POST', payload);
            if (data) tampilkanToastJersey(data.message);
        }, 400);
    }

    // ---- Estimasi Lengan & Kerah (ukuran x tipe -> meter/berat) - pola
    // debounce sama seperti simpanEstimasi() Komponen, tapi tanpa kolom
    // Total live (matriksnya lebih kecil, tipe dikelola di tab terpisah
    // jadi tidak butuh tambah-kolom-dinamis lewat JS). ----
    const simpanEstimasiLenganTimers = {};
    function simpanEstimasiLengan(sizeId, sleeveTypeId, field, value) {
        const key = `${sizeId}-${sleeveTypeId}-${field}`;
        clearTimeout(simpanEstimasiLenganTimers[key]);
        simpanEstimasiLenganTimers[key] = setTimeout(async () => {
            const payload = { jersey_size_id: sizeId, jersey_sleeve_type_id: sleeveTypeId };
            if (field === 'meter') payload.usage_meter = value === '' ? null : value;
            if (field === 'weight') payload.usage_weight_gram = value === '' ? null : value;
            const data = await kirimJson('{{ url("/jersey/master/sleeve-usage") }}', 'POST', payload);
            if (data) tampilkanToastJersey(data.message);
        }, 400);
    }

    const simpanEstimasiKerahTimers = {};
    function simpanEstimasiKerah(sizeId, collarTypeId, field, value) {
        const key = `${sizeId}-${collarTypeId}-${field}`;
        clearTimeout(simpanEstimasiKerahTimers[key]);
        simpanEstimasiKerahTimers[key] = setTimeout(async () => {
            const payload = { jersey_size_id: sizeId, jersey_collar_type_id: collarTypeId };
            if (field === 'meter') payload.usage_meter = value === '' ? null : value;
            if (field === 'weight') payload.usage_weight_gram = value === '' ? null : value;
            const data = await kirimJson('{{ url("/jersey/master/collar-usage") }}', 'POST', payload);
            if (data) tampilkanToastJersey(data.message);
        }, 400);
    }

    // ---- Input Cepat Tier Harga per Tipe Produk ----
    @php
        $tierHargaUmum = $priceSettings->whereNull('jersey_category_id')->map(function ($p) {
            return ['tipe' => $p->jersey_product_type_id, 'bahan' => $p->jersey_material_id, 'kelompok' => $p->kelompok ?? 'dewasa', 'qty_min' => $p->qty_min, 'qty_max' => $p->qty_max, 'price' => (float) $p->price];
        })->values();
    @endphp
    const tierHargaAda = @js($tierHargaUmum);

    function barisCepatHtml(t) {
        const v = (x) => (x === null || x === undefined) ? '' : x;
        return `<div class="cepat-row grid grid-cols-3 gap-2 items-center">
            <input type="number" min="1" placeholder="Qty min" value="${v(t.qty_min)}" class="cepat-min w-full bg-white border border-slate-200 rounded-xl py-2.5 px-3 text-sm font-medium text-slate-600">
            <input type="number" min="1" placeholder="Qty max (kosong = ~)" value="${v(t.qty_max)}" class="cepat-max w-full bg-white border border-slate-200 rounded-xl py-2.5 px-3 text-sm font-medium text-slate-600">
            <div class="flex gap-2">
                <input type="text" inputmode="numeric" placeholder="Harga/pcs" value="${t.price ? Number(t.price).toLocaleString('id-ID') : ''}" oninput="this.value = this.value.replace(/[^0-9]/g, '').replace(/\\B(?=(\\d{3})+(?!\\d))/g, '.')" class="cepat-harga w-full bg-white border border-slate-200 rounded-xl py-2.5 px-3 text-sm font-medium text-slate-600">
                <button type="button" onclick="this.closest('.cepat-row').remove()" class="w-10 rounded-xl text-slate-400 hover:text-rose-500 hover:bg-rose-50" title="Hapus baris"><i class="far fa-trash-alt text-xs"></i></button>
            </div>
        </div>`;
    }

    function muatTierCepat() {
        const tipe = document.getElementById('cepat-tipe').value;
        const bahan = document.getElementById('cepat-bahan').value;
        const wadah = document.getElementById('cepat-baris');
        wadah.innerHTML = '';
        if (!tipe) return;
        const ada = tierHargaAda
            .filter(t => String(t.tipe) === tipe && String(t.bahan ?? '') === bahan && t.kelompok === document.getElementById('cepat-kelompok').value)
            .sort((a, b) => a.qty_min - b.qty_min);
        (ada.length ? ada : [{}]).forEach(t => wadah.insertAdjacentHTML('beforeend', barisCepatHtml(t)));
    }

    function tambahBarisCepat() {
        if (!document.getElementById('cepat-tipe').value) { tampilkanToastJersey('Pilih Tipe Produk dulu.', false); return; }
        const rows = document.querySelectorAll('#cepat-baris .cepat-row');
        const terakhir = rows[rows.length - 1];
        const maxTerakhir = terakhir ? parseInt(terakhir.querySelector('.cepat-max').value || '0', 10) : 0;
        document.getElementById('cepat-baris').insertAdjacentHTML('beforeend', barisCepatHtml({ qty_min: maxTerakhir ? maxTerakhir + 1 : 1 }));
    }

    function isiPitaStandar() {
        if (!document.getElementById('cepat-tipe').value) { tampilkanToastJersey('Pilih Tipe Produk dulu.', false); return; }
        const wadah = document.getElementById('cepat-baris');
        const hargaAwal = wadah.querySelector('.cepat-harga')?.value || '';
        wadah.innerHTML = '';
        [[1, 11], [12, 23], [24, 49], [50, null]].forEach(([min, max]) => {
            wadah.insertAdjacentHTML('beforeend', barisCepatHtml({ qty_min: min, qty_max: max }));
        });
        if (hargaAwal) wadah.querySelectorAll('.cepat-harga').forEach(i => { i.value = hargaAwal; });
    }

    async function simpanCepat() {
        const tipe = document.getElementById('cepat-tipe').value;
        if (!tipe) { tampilkanToastJersey('Pilih Tipe Produk dulu.', false); return; }
        const tiers = [...document.querySelectorAll('#cepat-baris .cepat-row')].map(r => ({
            qty_min: r.querySelector('.cepat-min').value,
            qty_max: r.querySelector('.cepat-max').value || null,
            price: cleanRupiah(r.querySelector('.cepat-harga').value),
        })).filter(t => t.qty_min !== '' && t.price !== '');
        if (!tiers.length) { tampilkanToastJersey('Isi minimal satu tier (qty min & harga).', false); return; }
        const data = await kirimJson('{{ url("/jersey/master/price-settings/bulk") }}', 'POST', {
            jersey_product_type_id: tipe, jersey_material_id: document.getElementById('cepat-bahan').value || null, kelompok: document.getElementById('cepat-kelompok').value, tiers,
        });
        if (!data) return;
        tampilkanToastJersey(data.message);
        setTimeout(() => { sessionStorage.setItem('jerseyTabAktif', 'harga'); location.reload(); }, 600);
    }

    document.getElementById('cepat-tipe').addEventListener('change', muatTierCepat);
    document.getElementById('cepat-bahan').addEventListener('change', muatTierCepat);
    document.getElementById('cepat-kelompok').addEventListener('change', muatTierCepat);
    try { const tab = sessionStorage.getItem('jerseyTabAktif'); if (tab) { sessionStorage.removeItem('jerseyTabAktif'); switchJerseyTab(tab); } } catch (e) {}

    // ---- Tier Harga ----
    document.getElementById('form-add-harga').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const payload = {
            jersey_material_id: form.querySelector('[name="jersey_material_id"]').value || null,
            jersey_product_type_id: form.querySelector('[name="jersey_product_type_id"]').value || null,
            jersey_category_id: form.querySelector('[name="jersey_category_id"]').value || null,
            qty_min: form.querySelector('[name="qty_min"]').value,
            qty_max: form.querySelector('[name="qty_max"]').value || null,
            price: cleanRupiah(form.querySelector('[name="price"]').value),
        };
        const data = await kirimJson('{{ url("/jersey/master/price-settings") }}', 'POST', payload);
        if (!data) return;
        hapusBarisKosong('tbody-harga');
        document.getElementById('tbody-harga').insertAdjacentHTML('beforeend', rowHargaHtml(data.row));
        form.reset();
        tampilkanToastJersey(data.message);
        terapkanFilterHarga();
    });

    function terapkanFilterHarga() {
        const bahan = document.getElementById('filter-harga-bahan').value;
        const tipe = document.getElementById('filter-harga-tipe').value;
        const kategori = document.getElementById('filter-harga-kategori').value;
        const q = (document.getElementById('cari-harga-text')?.value || '').trim().toLowerCase();
        terapkanFilterTabel('tbody-harga', (row) => {
            const teks = (row.textContent || '').toLowerCase();
            const cocokBahan = !bahan || row.dataset.materialId === bahan;
            const cocokTipe = !tipe || row.dataset.typeId === tipe;
            const cocokKategori = !kategori || row.dataset.categoryId === kategori;
            const cocokCari = !q || teks.includes(q);
            return cocokBahan && cocokTipe && cocokKategori && cocokCari;
        }, 6);
    }

    // Urutkan kolom kapan pun header diklik: asc -> desc -> asc.
    let sortArah = {};
    function urutkanKolom(key) {
        const tbody = document.getElementById('tbody-harga');
        if (!tbody) return;
        const arah = (sortArah[key] === 'asc' ? 'desc' : 'asc');
        sortArah = { [key]: arah };
        document.querySelectorAll('[data-sort-th]').forEach(th => {
            const ic = th.querySelector('i');
            if (ic) ic.className = 'fas fa-sort text-[10px] text-slate-400 ml-0.5';
        });
        const aktif = document.querySelector(`[data-sort-th="${key}"] i`);
        if (aktif) aktif.className = `fas fa-sort-${arah === 'asc' ? 'up' : 'down'} text-[10px] text-indigo-500 ml-0.5`;
        const rows = Array.from(tbody.querySelectorAll('tr:not(.row-empty)'));
        rows.sort((a, b) => {
            const A = a.children, B = b.children;
            if (key === 'qty') {
                const an = parseFloat((A[3].textContent || '').split('-')[0].replace(/[^0-9.]/g, '')) || 0;
                const bn = parseFloat((B[3].textContent || '').split('-')[0].replace(/[^0-9.]/g, '')) || 0;
                return arah === 'asc' ? an - bn : bn - an;
            }
            if (key === 'harga') {
                const an = parseFloat((A[4].textContent || '').replace(/[^0-9.,]/g, '').replace(/\./g, '').replace(',', '.')) || 0;
                const bn = parseFloat((B[4].textContent || '').replace(/[^0-9.,]/g, '').replace(/\./g, '').replace(',', '.')) || 0;
                return arah === 'asc' ? an - bn : bn - an;
            }
            const idx = { bahan: 0, tipe: 1, kategori: 2 }[key] ?? 0;
            return arah === 'asc'
                ? (A[idx].textContent || '').localeCompare(B[idx].textContent || '')
                : (B[idx].textContent || '').localeCompare(A[idx].textContent || '');
        });
        rows.forEach(r => tbody.appendChild(r));
    }
    document.querySelectorAll('[data-sort-th]').forEach(th => {
        th.addEventListener('click', () => urutkanKolom(th.dataset.sortTh));
    });
    const btnSort = document.getElementById('btn-sort-harga');
    if (btnSort) btnSort.addEventListener('click', () => urutkanKolom('harga'));

    function resetFilterHarga() {
        document.getElementById('filter-harga-bahan').value = '';
        document.getElementById('filter-harga-tipe').value = '';
        document.getElementById('filter-harga-kategori').value = '';
        const cari = document.getElementById('cari-harga-text');
        if (cari) cari.value = '';
        sortArah = {};
        document.querySelectorAll('[data-sort-th] i').forEach(i => {
            i.className = 'fas fa-sort text-[10px] text-slate-400 ml-0.5';
        });
        terapkanFilterHarga();
    }

    document.getElementById('cari-harga-text')?.addEventListener('input', terapkanFilterHarga);

    ['filter-harga-bahan', 'filter-harga-tipe', 'filter-harga-kategori'].forEach(id => {
        document.getElementById(id).addEventListener('change', terapkanFilterHarga);
    });

    function rowHargaHtml(row) {
        return `
        <tr data-id="${row.id}" data-material-id="${row.jersey_material_id ?? ''}" data-type-id="${row.jersey_product_type_id ?? ''}" data-category-id="${row.jersey_category_id ?? ''}">
            <td class="p-2.5 font-medium text-indigo-600">${row.material ? escapeHtmlJersey(row.material.name) : 'Semua Bahan'}${row.kelompok === 'anak' ? '<span class="ml-2 px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[9px] font-medium uppercase">Anak</span>' : ''}</td>
            <td class="p-2.5">${row.product_type ? escapeHtmlJersey(row.product_type.name) : 'Semua'}</td>
            <td class="p-2.5">${row.category ? escapeHtmlJersey(row.category.name) : 'Semua'}</td>
            <td class="p-2.5 text-center">${row.qty_min} - ${row.qty_max ?? '∞'}</td>
            <td class="p-2.5 text-right font-medium">Rp ${formatRupiah(Math.round(parseFloat(row.price)))}</td>
            <td class="p-2.5 text-center">
                <div class="flex items-center justify-center gap-1">
                    <button type="button" onclick="bukaEditHarga(${JSON.stringify(row).replace(/"/g, '&quot;')})" class="w-8 h-8 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50" title="Ubah"><i class="far fa-edit text-xs"></i></button>
                    <button type="button" onclick="hapusHarga(${row.id})" class="w-8 h-8 rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-50" title="Hapus"><i class="far fa-trash-alt text-xs"></i></button>
                </div>
            </td>
        </tr>`;
    }

    function bukaEditHarga(row) {
        document.getElementById('edit-harga-id').value = row.id;
        // Tier lama milik tipe/kategori/bahan yang sudah nonaktif: tampilkan
        // sementara (bertanda nonaktif) supaya tersimpan ulang tidak berubah jadi "Semua".
        const pilihDenganNonaktif = (idSelect, nilai, kelas) => {
            const sel = document.getElementById(idSelect);
            sel.querySelectorAll('option.opsi-sementara').forEach(o => o.remove());
            if (nilai && !sel.querySelector(`option[value="${nilai}"]`)) {
                sel.insertAdjacentHTML('beforeend', `<option class="opsi-sementara" value="${nilai}">(nonaktif) #${nilai}</option>`);
            }
            sel.value = nilai ?? '';
        };
        pilihDenganNonaktif('edit-harga-bahan', row.jersey_material_id);
        pilihDenganNonaktif('edit-harga-tipe', row.jersey_product_type_id);
        pilihDenganNonaktif('edit-harga-kategori', row.jersey_category_id);
        document.getElementById('edit-harga-qty-min').value = row.qty_min;
        document.getElementById('edit-harga-qty-max').value = row.qty_max ?? '';
        document.getElementById('edit-harga-price').value = formatRupiah(Math.round(parseFloat(row.price)));
        openModal('edit-harga');
    }

    document.getElementById('form-edit-harga').addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('edit-harga-id').value;
        const payload = {
            jersey_material_id: document.getElementById('edit-harga-bahan').value || null,
            jersey_product_type_id: document.getElementById('edit-harga-tipe').value || null,
            jersey_category_id: document.getElementById('edit-harga-kategori').value || null,
            qty_min: document.getElementById('edit-harga-qty-min').value,
            qty_max: document.getElementById('edit-harga-qty-max').value || null,
            price: cleanRupiah(document.getElementById('edit-harga-price').value),
        };
        const data = await kirimJson(`{{ url("/jersey/master/price-settings") }}/${id}`, 'PUT', payload);
        if (!data) return;
        const row = document.querySelector(`#tbody-harga tr[data-id="${id}"]`);
        if (row) row.outerHTML = rowHargaHtml(data.row);
        closeModal('edit-harga');
        tampilkanToastJersey(data.message);
        terapkanFilterHarga();
    });

    async function hapusHarga(id) {
        const konfirmasi = await Swal.fire({
            title: 'Hapus Tier Harga?',
            html: 'Tier harga ini akan dihapus permanen.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
            customClass: { confirmButton: 'rounded-lg px-6 py-2.5', cancelButton: 'rounded-lg px-6 py-2.5 text-slate-600' },
        });
        if (!konfirmasi.isConfirmed) return;
        const data = await kirimJson(`{{ url("/jersey/master/price-settings") }}/${id}`, 'DELETE', {});
        if (!data) return;
        document.querySelector(`#tbody-harga tr[data-id="${id}"]`)?.remove();
        tampilkanToastJersey(data.message);
        terapkanFilterHarga();
    }
</script>
@endpush
@endsection
