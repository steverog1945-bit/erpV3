@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Product Engineering">
        <x-slot:subtitle>
            <i class="fas fa-microchip text-slate-500"></i>
            Engineering <span class="text-slate-500">/</span> Spesifikasi & Kalkulasi Stok
        </x-slot:subtitle>
        <x-slot:actions>
            <x-button onclick="openTambahModal()">
                <i class="fas fa-plus-circle"></i>
                Tambah Produk
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- QUICK SEARCH & FILTER --}}
    <x-card class="mb-8">
        <form action="{{ route('produk.index') }}" method="GET" class="flex flex-col lg:flex-row gap-4 lg:items-end">
            <div class="flex-1">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari Produk</label>
                <x-form.input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Cari Produk atau Barcode..." />
            </div>

            <div class="w-full lg:w-72">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Mesin/Divisi</label>
                <x-form.select name="category" onchange="this.form.submit()">
                    <option value="">Semua Mesin</option>
                    @foreach($mesin as $m)
                        <option value="{{ $m->id_mesin }}" {{ request('category') == $m->id_mesin ? 'selected' : '' }}>
                            {{ strtoupper($m->nama_mesin) }}
                        </option>
                    @endforeach
                </x-form.select>
            </div>

            <div class="w-full lg:w-48">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Status</label>
                <x-form.select name="status" onchange="this.form.submit()">
                    <option value="aktif" {{ request('status', 'aktif') == 'aktif' ? 'selected' : '' }}>Aktif</option>
                    <option value="nonaktif" {{ request('status') == 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
                    <option value="semua" {{ request('status') == 'semua' ? 'selected' : '' }}>Semua Status</option>
                </x-form.select>
            </div>

            @if(request()->anyFilled(['search', 'category']) || request('status', 'aktif') !== 'aktif')
                <a href="{{ route('produk.index') }}"
                   class="h-[38px] px-4 flex items-center justify-center bg-slate-100 text-slate-400 rounded-xl hover:bg-rose-50 hover:text-rose-500 transition-all"
                   title="Reset Filter">
                    <i class="fas fa-undo-alt text-sm"></i>
                </a>
            @endif
        </form>
    </x-card>

    <x-table>
        <thead>
            <tr>
                <th class="px-4 py-4 text-center w-10">#</th>
                <th class="px-6 py-4 text-left">Detail Produk</th>
                <th class="px-4 py-4 text-center">Metode Hitung</th>
                <th class="px-4 py-4 text-left">Bahan Baku (BOM)</th>
                <th class="px-4 py-4 text-center">Logic Pemakaian</th>
                <th class="px-6 py-4 text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($produk as $i => $p)
            <tr class="group hover:bg-indigo-50/40 transition-all {{ $p->status === 'nonaktif' ? 'opacity-50' : '' }}">
                <td class="px-4 py-3.5 text-center text-[11px] text-slate-400">{{ $produk->firstItem() + $i }}</td>
                <td class="px-6 py-3.5">
                    <div class="font-medium text-slate-800 text-[14px] flex items-center gap-2">
                        {{ $p->nama_produk }}
                        @if($p->status === 'nonaktif')
                            <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-400 text-[9px] font-medium uppercase tracking-widest">Nonaktif</span>
                        @endif
                    </div>
                    <div class="text-[10px] text-indigo-500 font-medium mt-1 uppercase tracking-widest flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span>
                        {{ $p->nama_kategori ?? 'Umum' }}
                    </div>
                </td>

                <td class="px-4 py-3.5 text-center">
                    <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-[10px] font-medium uppercase tracking-widest {{ $p->metode_hitung == 'ukuran' ? 'bg-orange-50 text-orange-600 border border-orange-100' : 'bg-blue-50 text-blue-600 border border-blue-100' }}">
                        <i class="fas {{ $p->metode_hitung == 'ukuran' ? 'fa-ruler-combined' : 'fa-layer-group' }} mr-1.5"></i>
                        {{ $p->metode_hitung }}
                    </span>
                </td>

                <td class="px-4 py-3.5">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600 shrink-0">
                            <i class="fas fa-boxes text-[11px]"></i>
                        </div>
                        <span class="text-sm font-medium text-slate-600">
                            {{ $p->daftar_bahan ?? 'Belum ada bahan' }}
                        </span>
                    </div>
                </td>

                <td class="px-4 py-3.5 text-center">
                    {{-- Bukan <code> (browser render monospace) - badge biasa
                         senada dengan chip Metode Hitung di kolom sebelah. --}}
                    <span class="inline-flex items-center px-3 py-1.5 rounded-xl bg-slate-50 text-indigo-600 text-[10px] font-medium uppercase tracking-widest border border-slate-100">
                        {{ $p->cara_hitung_pemakaian ?? '-' }}
                    </span>
                </td>

                <td class="px-6 py-3.5 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <x-button size="icon" variant="ghost" onclick="editProduk({{ json_encode($p) }})" class="border border-slate-100 hover:text-amber-500 hover:border-amber-200" aria-label="Ubah" title="Ubah">
                            <i class="far fa-edit text-sm"></i>
                        </x-button>

                        <form action="{{ route('produk.toggle-status', $p->id_produk) }}" method="POST" class="inline" id="toggle-form-{{ $p->id_produk }}">
                            @csrf @method('PATCH')
                            <x-button type="button" size="icon" variant="ghost" onclick="confirmToggleStatus('{{ $p->id_produk }}', '{{ $p->status }}', {{ json_encode($p->nama_produk) }})"
                                class="border border-slate-100 {{ $p->status === 'aktif' ? 'hover:text-slate-600' : 'hover:text-emerald-500 hover:border-emerald-200' }}"
                                title="{{ $p->status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan kembali' }}">
                                <i class="far {{ $p->status === 'aktif' ? 'fa-eye-slash' : 'fa-eye' }} text-sm"></i>
                            </x-button>
                        </form>

                        <form action="{{ route('produk.destroy', $p->id_produk) }}" method="POST" class="inline" id="delete-form-{{ $p->id_produk }}">
                            @csrf @method('DELETE')
                            <x-button type="button" size="icon" variant="ghost" onclick="confirmDelete('{{ $p->id_produk }}', '{{ $p->nama_produk }}')" class="border border-slate-100 hover:text-rose-600 hover:border-rose-200">
                                <i class="far fa-trash-alt text-sm"></i>
                            </x-button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6">
                    <x-empty-state title="Database produk masih kosong" />
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$produk" />

    {{-- MODAL PRODUK + BOM - pakai header bawaan x-modal (title prop) seperti
         semua modal lain di app ini (bahan_baku, finishing, jasa, dst),
         bukan lagi header kustom (kotak ikon indigo + judul 2 baris) yang
         cuma dipakai di halaman ini sendiri. id elemen judul yang
         dihasilkan x-modal ("modal-produk-title") persis sama dengan yang
         sudah dipakai JS di bawah, jadi toggle judul Tambah/Edit tetap
         jalan tanpa perlu ubah apa pun di sana. --}}
    <x-modal id="produk" title="Product Engineering" maxWidth="6xl">
        <form id="formProduk" action="{{ route('produk.store') }}" method="POST">
            @csrf
            <div id="methodField"></div>

            {{-- INFO PRODUK - full-width (dulu kolom sempit 4/12 di samping BOM,
                 sekarang selebar modal supaya field-nya mengalir jelas dari atas
                 ke bawah sebelum masuk ke komposisi BOM, mengikuti alur pada
                 referensi tampilan yang diberikan). Panel & field dipadatkan
                 (padding/radius/jarak antar-field diturunkan) mengikuti gaya
                 field di halaman Input Order - bukan lagi kartu besar berjarak lega. --}}
            <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100 space-y-3 mb-5">
                <p class="text-[11px] font-medium text-indigo-600 uppercase tracking-widest flex items-center gap-2">
                    <i class="fas fa-info-circle"></i> Info Produk
                </p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-2.5">
                    <div class="md:col-span-2">
                        <x-form.group label="Nama Produk" dalam>
                            <x-form.input type="text" name="nama_produk" id="input_nama_produk" required class="!bg-white !shadow-sm !py-2.5" />
                        </x-form.group>
                    </div>
                    <x-form.group label="Barcode" dalam>
                        <x-form.input type="text" name="barcode" placeholder="ID-001" class="!bg-white !shadow-sm !py-2.5" />
                    </x-form.group>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                    <x-form.group label="Mesin / Divisi Produksi" dalam>
                        <x-form.select name="id_mesin" id="input_id_mesin" onchange="filterBahanByMesin(this.value)" class="!bg-white !shadow-sm !text-indigo-600 !py-2.5">
                            <option value="">-- Pilih Mesin --</option>
                            @foreach($mesin as $m)
                                <option value="{{ $m->id_mesin }}">{{ $m->nama_mesin }}</option>
                            @endforeach
                        </x-form.select>
                    </x-form.group>
                    <x-form.group label="Metode Hitung Order" dalam>
                        <x-form.select name="metode_hitung" onchange="toggleMinOrderFields()" class="!bg-white !shadow-sm !py-2.5">
                            <option value="ukuran">Berdasarkan Ukuran Cetak (m²)</option>
                            <option value="quantity">Berdasarkan Satuan (Pcs)</option>
                        </x-form.select>
                    </x-form.group>
                </div>

                {{-- Toggle eksplisit "pakai minimal order atau tidak" - sebelumnya
                     field min order cuma disembunyikan/tampil berdasarkan Metode
                     Hitung, tanpa cara eksplisit bilang "produk ini memang tidak
                     punya batas minimal" (mengandalkan konvensi implisit isi 0 =
                     tanpa minimum). Sekarang eksplisit seperti pada referensi -
                     pilih Tidak akan menyembunyikan & mengosongkan field ukuran/qty
                     minimumnya (tersimpan 0, sama artinya "tanpa minimum"). --}}
                <x-form.group label="Minimal Order" dalam>
                    <x-form.select id="input_pakai_min_order" onchange="toggleMinOrderFields()" class="!bg-white !shadow-sm !py-2.5">
                        <option value="ya">Menggunakan Minimal Order</option>
                        <option value="tidak">Tanpa Minimal Order</option>
                    </x-form.select>
                </x-form.group>

                <div id="field_min_ukuran" class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                    <x-form.group label="Ukuran Min Order - Panjang (cm)" dalam>
                        <x-form.input type="number" name="min_p" step="0.01" min="0" placeholder="0" class="!bg-white !shadow-sm !py-2.5" />
                    </x-form.group>
                    <x-form.group label="Ukuran Min Order - Lebar (cm)" dalam>
                        <x-form.input type="number" name="min_l" step="0.01" min="0" placeholder="0" class="!bg-white !shadow-sm !py-2.5" />
                    </x-form.group>
                </div>

                <div id="field_min_qty">
                    <x-form.group label="Min Order (Pcs)" dalam>
                        <x-form.input type="number" name="min_order_qty" step="1" min="0" placeholder="0" class="!bg-white !shadow-sm !py-2.5" />
                    </x-form.group>
                </div>

                {{-- Minimum luas PER KELOMPOK (hybrid T4-A): total luas semua baris
                     order dalam satu kelompok_minimum paling sedikit sebesar nilai
                     ini; selisihnya digulung ke subtotal baris item TERBESAR
                     kelompok (tanpa baris minimum terpisah di nota/SPK). 0 = produk
                     ini tanpa minimum kelompok. Bebas dari toggle "Minimal Order"
                     di atas - itu aturan per-baris (min_panjang/min_lebar), ini
                     aturan akumulasi order. --}}
                <div id="field_min_luas" class="grid grid-cols-1 md:grid-cols-2 gap-2.5 hidden">
                    <x-form.group label="Min. Luas Total Kelompok (m²)" dalam>
                        <x-form.input type="number" name="min_luas_total" step="0.01" min="0" placeholder="0" class="!bg-white !shadow-sm !py-2.5" />
                    </x-form.group>
                    <x-form.group label="Nama Kelompok Minimum" dalam>
                        <x-form.input type="text" name="kelompok_minimum" placeholder="mis. FLS280 Outdoor" class="!bg-white !shadow-sm !py-2.5" />
                    </x-form.group>
                </div>

                <label class="flex items-start gap-3 cursor-pointer rounded-xl bg-white shadow-sm px-4 py-3 mb-4">
                    <input type="checkbox" name="spk_lengkap" value="1" id="input_spk_lengkap" class="mt-0.5 w-4 h-4 rounded accent-indigo-600">
                    <span><span class="block text-[11px] font-medium text-slate-600 uppercase tracking-wide">Cetak SPK lengkap</span><span class="block text-[11px] text-slate-400">SPK memuat gambar, konsumen, desainer, kebutuhan bahan, dan finishing (seperti SPK order jersey) - untuk cetakan tertentu mis. Century + bahan TC yang ada finishing jahit.</span></span>
                </label>

                <x-form.group label="Istilah Publik (kamus asisten chat landing)" dalam>
                    <textarea name="alias_publik" rows="2" placeholder="mis. banner, spanduk, baliho - pisahkan dengan koma" class="w-full bg-white border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-600 shadow-sm focus:ring-2 focus:ring-indigo-500/20"></textarea>
                    <span class="block text-[11px] text-slate-400 mt-1">Istilah percetakan yang dipakai konsumen di chat publik untuk produk ini. Dipisah koma, tanpa hardcode - daftar ini yang dibaca asisten landing.</span>
                </x-form.group>

                <x-form.group label="Keterangan" dalam>
                    <textarea name="keterangan" rows="2" placeholder="Catatan tambahan produk (opsional)" class="w-full bg-white border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-600 shadow-sm focus:ring-2 focus:ring-indigo-500/20"></textarea>
                </x-form.group>
            </div>

            {{-- KOMPOSISI: dua tab - Bahan Baku Penyusun (bom_detail, sudah ada
                 sejak lama) & Finishing Penyusun (bom_finishing - tabel & endpoint
                 lamanya, BomFinishingController, sudah ada di database tapi TIDAK
                 PERNAH disambungkan ke tampilan mana pun. Sekarang disatukan ke
                 form ini, disimpan atomik bareng data produk & bahan baku lewat
                 syncBomFinishing() di ProdukController, bukan panggilan terpisah). --}}
            <div class="bg-white rounded-2xl border-2 border-dashed border-slate-200 overflow-hidden">
                <div class="flex border-b border-slate-100">
                    <button type="button" onclick="switchBomTab('bahan')" id="tab-btn-bahan" class="flex-1 py-3 text-[11px] font-medium uppercase tracking-widest transition-all bg-indigo-50 text-indigo-600 border-b-2 border-indigo-500">
                        <i class="fas fa-boxes mr-2"></i> Bahan Baku Penyusun
                    </button>
                    <button type="button" onclick="switchBomTab('finishing')" id="tab-btn-finishing" class="flex-1 py-3 text-[11px] font-medium uppercase tracking-widest transition-all text-slate-400 border-b-2 border-transparent hover:text-slate-600">
                        <i class="fas fa-layer-group mr-2"></i> Finishing Penyusun
                    </button>
                </div>

                <div id="tab-panel-bahan" class="p-5">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2.5 mb-4 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                        <div class="ld md:col-span-1">
                            <select id="temp_id_bahan" class="w-full bg-white border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-600 shadow-sm focus:ring-2 focus:ring-indigo-500/20">
                                <option value="">-- Pilih Bahan --</option>
                                @foreach($bahan_baku as $bb)
                                    <option value="{{ $bb->id_bahan }}"
                                            data-nama="{{ $bb->nama_bahan }}"
                                            data-satuan="{{ $bb->satuan_stok }}"
                                            data-mesin="{{ $bb->id_mesin }}"
                                            class="bahan-opt hidden"> [{{ $bb->nama_kategori }}] {{ $bb->nama_bahan }}
                                    </option>
                                @endforeach
                            </select>
                            <label class="ld-label" for="temp_id_bahan">Pilih Bahan</label>
                        </div>
                        <div class="ld md:col-span-1">
                            <select id="temp_rumus" class="w-full bg-white border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-600 shadow-sm focus:ring-2 focus:ring-indigo-500/20">
                                <option value="(P*L)/10000">Luas (m²) - [P*L/10000]</option>
                                <option value="Q">Quantity (1:1) - [Satuan]</option>
                                <option value="2*(P+L)/100">Keliling (m) - [2*(P+L)/100]</option>
                                <option value="(P*L)/10000*2">Bolak Balik (x2)</option>
                                <option value="Q/N">Bagi per lembar - [Q/N] (isi N barang per lembar)</option>
                                <option value="Q*K">Kali faktor - [Q*K] (K bahan per barang)</option>
                            </select>
                            <input type="number" id="temp_faktor" step="0.0001" min="0.0001" placeholder="Angka N atau K" class="hidden mt-2 w-full bg-white border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-600 shadow-sm focus:ring-2 focus:ring-indigo-500/20">
                        </div>
                        <div class="md:col-span-1 flex items-end">
                            <x-button type="button" variant="success" onclick="addBahanToTable()" class="w-full">
                                <i class="fas fa-plus"></i> Tambahkan
                            </x-button>
                        </div>
                    </div>

                    <div class="max-h-[360px] overflow-y-auto rounded-xl">
                    <x-table bare class="table-fixed !min-w-0">
                        <thead>
                            <tr>
                                <th class="p-3 text-left">Bahan Baku Penyusun</th>
                                <th class="p-3 text-left">Cara Hitung Pemakaian</th>
                                <th class="p-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="bom_table_body" class="divide-y divide-slate-50 text-[12px] font-medium text-slate-600">
                            <tr id="empty_state">
                                <td colspan="3" class="p-5 text-center text-slate-500 italic">Belum ada bahan baku yang ditambahkan.</td>
                            </tr>
                        </tbody>
                    </x-table>
                    </div>
                </div>

                <div id="tab-panel-finishing" class="p-5 hidden">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2.5 mb-4 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                        <div class="md:col-span-1">
                            <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Pilih Finishing</label>
                            <select id="temp_id_finishing" class="w-full bg-white border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-600 shadow-sm focus:ring-2 focus:ring-indigo-500/20">
                                <option value="">-- Pilih Finishing --</option>
                                @foreach($finishing as $f)
                                    <option value="{{ $f->id_finishing }}" data-nama="{{ $f->nama_finishing }}">{{ $f->nama_finishing }}</option>
                                @endforeach
                            </select>
                            <label class="ld-label" for="temp_rumus">Cara Hitung Pemakaian</label>
                        </div>
                        <div class="ld md:col-span-1">
                            <input placeholder=" " type="number" id="temp_finishing_qty" value="1" min="0.01" step="0.01" class="w-full bg-white border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-600 shadow-sm focus:ring-2 focus:ring-indigo-500/20">
                            <label class="ld-label" for="temp_finishing_qty">Qty per Produk</label>
                        </div>
                        <div class="md:col-span-1 flex items-end">
                            <x-button type="button" variant="success" onclick="addFinishingToTable()" class="w-full">
                                <i class="fas fa-plus"></i> Tambahkan
                            </x-button>
                        </div>
                    </div>

                    <div class="max-h-[360px] overflow-y-auto rounded-xl">
                    <x-table bare class="table-fixed !min-w-0">
                        <thead>
                            <tr>
                                <th class="p-3 text-left">Finishing Penyusun</th>
                                <th class="p-3 text-left">Qty</th>
                                <th class="p-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="finishing_table_body" class="divide-y divide-slate-50 text-[12px] font-medium text-slate-600">
                            <tr id="empty_state_finishing">
                                <td colspan="3" class="p-5 text-center text-slate-500 italic">Belum ada finishing yang ditambahkan.</td>
                            </tr>
                        </tbody>
                    </x-table>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 mt-5">
                <x-button type="button" variant="secondary" onclick="closeProdukModal()" class="flex-1">Batal</x-button>
                <x-button type="submit" class="flex-[2]">Simpan Semua Data Produk</x-button>
            </div>
        </form>
    </x-modal>

</div>

@push('scripts')
<script>
    // Reset + tutup modal produk (menggantikan bagian "else" dari toggleModal() lama)
    function closeProdukModal() {
        document.getElementById('modal-produk-title').innerText = "Product Engineering";
        document.getElementById('formProduk').reset();
        document.getElementById('methodField').innerHTML = '';
        closeModal('produk');
    }

    function openTambahModal() {
        const form = document.getElementById('formProduk');
        document.getElementById('modal-produk-title').innerText = "Product Engineering";
        form.action = "{{ route('produk.store') }}";
        document.getElementById('methodField').innerHTML = '';
        form.reset();
        toggleMinOrderFields();

        document.getElementById('bom_table_body').innerHTML = `
            <tr id="empty_state">
                <td colspan="3" class="p-5 text-center text-slate-500 italic">Belum ada bahan baku yang ditambahkan.</td>
            </tr>`;
        document.getElementById('finishing_table_body').innerHTML = `
            <tr id="empty_state_finishing">
                <td colspan="3" class="p-5 text-center text-slate-500 italic">Belum ada finishing yang ditambahkan.</td>
            </tr>`;
        switchBomTab('bahan');

        openModal('produk');
    }

    // Tab "Bahan Baku Penyusun" / "Finishing Penyusun" - murni tampilan,
    // kedua tabel tetap ikut ter-submit bersamaan lewat form yang sama
    // (input hidden id_bahan[]/id_finishing[] ada di kedua tab, tab yang
    // tidak aktif cuma disembunyikan lewat class hidden, bukan dihapus).
    function switchBomTab(tab) {
        document.getElementById('tab-panel-bahan').classList.toggle('hidden', tab !== 'bahan');
        document.getElementById('tab-panel-finishing').classList.toggle('hidden', tab !== 'finishing');

        const aktif = 'bg-indigo-50 text-indigo-600 border-b-2 border-indigo-500';
        const nonaktif = 'text-slate-400 border-b-2 border-transparent hover:text-slate-600';
        document.getElementById('tab-btn-bahan').className = 'flex-1 py-3 text-[11px] font-medium uppercase tracking-widest transition-all ' + (tab === 'bahan' ? aktif : nonaktif);
        document.getElementById('tab-btn-finishing').className = 'flex-1 py-3 text-[11px] font-medium uppercase tracking-widest transition-all ' + (tab === 'finishing' ? aktif : nonaktif);
    }

    // Tampilkan input Min Order sesuai metode_hitung: Panjang/Lebar (m²) untuk
    // 'ukuran', atau Qty (Pcs) untuk 'quantity' - keduanya disimpan di kolom
    // berbeda (min_panjang/min_lebar vs min_order) jadi hanya satu yang relevan
    // untuk diisi tergantung metode yang dipilih.
    function toggleMinOrderFields() {
        const method = document.querySelector('select[name="metode_hitung"]').value;
        const pakaiMinOrder = document.getElementById('input_pakai_min_order').value === 'ya';

        document.getElementById('field_min_ukuran').classList.toggle('hidden', !pakaiMinOrder || method !== 'ukuran');
        document.getElementById('field_min_qty').classList.toggle('hidden', !pakaiMinOrder || method === 'ukuran');

        // Minimum luas per kelompok hanya bermakna untuk produk 'ukuran'. Tidak
        // digotong ke pembersihan toggle di bawah - toggle itu milik aturan
        // per-baris, bukan aturan akumulasi order.
        document.getElementById('field_min_luas').classList.toggle('hidden', method !== 'ukuran');

        // "Tanpa Minimal Order" mengosongkan field yang disembunyikan (tersimpan
        // 0 di server, sama artinya tanpa batas minimum) - supaya angka lama
        // tidak diam-diam ikut tersubmit padahal sudah dipilih "Tidak".
        if (!pakaiMinOrder) {
            document.querySelector('input[name="min_p"]').value = '';
            document.querySelector('input[name="min_l"]').value = '';
            document.querySelector('input[name="min_order_qty"]').value = '';
        }
    }

    // EDIT PRODUK (AJAX FETCH)
    async function editProduk(dataAsli) {
        const id = dataAsli.id_produk;

        Swal.fire({
            title: 'Menyiapkan Data...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading() }
        });

        try {
            const response = await fetch(`{{ url('/produk') }}/${id}`);
            const data = await response.json();

            const form = document.getElementById('formProduk');
            form.action = `{{ url('/produk') }}/${id}`;
            // _versi = updated_at saat form dibuka (optimistic locking, lihat VersiBaris) - ditolak bila ada yang mengubah lebih dulu.
            document.getElementById('methodField').innerHTML = '<input type="hidden" name="_method" value="PUT"><input type="hidden" name="_versi" value="' + (data.produk.updated_at || '') + '">';

            document.getElementById('modal-produk-title').innerText = "Edit: " + data.produk.nama_produk;
            document.getElementById('input_nama_produk').value = data.produk.nama_produk;
            document.getElementById('input_id_mesin').value = data.produk.id_mesin || '';
            form.querySelector('select[name="metode_hitung"]').value = data.produk.metode_hitung;
            form.querySelector('input[name="barcode"]').value = data.produk.barcode || '';
            document.getElementById('input_spk_lengkap').checked = !!Number(data.produk.spk_lengkap);
            form.querySelector('input[name="min_p"]').value = data.produk.min_panjang || '';
            form.querySelector('input[name="min_l"]').value = data.produk.min_lebar || '';
            form.querySelector('input[name="min_order_qty"]').value = data.produk.metode_hitung === 'quantity' ? (data.produk.min_order || '') : '';
            form.querySelector('input[name="min_luas_total"]').value = (parseFloat(data.produk.min_luas_total) > 0) ? data.produk.min_luas_total : '';
            form.querySelector('input[name="kelompok_minimum"]').value = data.produk.kelompok_minimum || '';
            form.querySelector('textarea[name="keterangan"]').value = (data.produk.keterangan && data.produk.keterangan !== '-') ? data.produk.keterangan : '';
            form.querySelector('textarea[name="alias_publik"]').value = data.alias_publik || '';

            // Tidak ada kolom tersendiri utk toggle "pakai minimal order?" -
            // disimpulkan dari data lama: ada angka minimum > 0 berarti "Ya",
            // kalau semuanya 0/kosong berarti "Tidak" (konvensi yang sama
            // dipakai sebelum toggle ini ada). WAJIB diisi SEBELUM
            // toggleMinOrderFields() di bawah supaya field yang baru saja
            // diisi tidak ikut dikosongkan kalau ternyata simpulannya "Tidak".
            const adaMinOrder = (parseFloat(data.produk.min_panjang) > 0) || (parseFloat(data.produk.min_lebar) > 0) || (parseFloat(data.produk.min_order) > 0);
            document.getElementById('input_pakai_min_order').value = adaMinOrder ? 'ya' : 'tidak';

            toggleMinOrderFields();

            // Set value mesin di atas TIDAK memicu event 'change' (cuma
            // assignment .value biasa), jadi filterBahanByMesin() harus
            // dipanggil manual di sini - sebelumnya terlewat, akibatnya saat
            // edit dropdown Bahan selalu kosong/tersembunyi semua sampai
            // operator sengaja mengubah-ubah Mesin/Divisi dulu (padahal
            // sudah benar) supaya listener onchange-nya ikut terpicu.
            filterBahanByMesin(data.produk.id_mesin || '');

            const tbody = document.getElementById('bom_table_body');
            tbody.innerHTML = '';

            if (data.bom && data.bom.length > 0) {
                data.bom.forEach(item => {
                    renderBahanRow(item.id_bahan, item.nama_bahan, item.cara_hitung_pemakaian, item.satuan_stok);
                });
            } else {
                tbody.innerHTML = '<tr id="empty_state"><td colspan="3" class="p-5 text-center text-slate-500 italic">Belum ada bahan baku.</td></tr>';
            }

            const tbodyFinishing = document.getElementById('finishing_table_body');
            tbodyFinishing.innerHTML = '';

            if (data.bom_finishing && data.bom_finishing.length > 0) {
                data.bom_finishing.forEach(item => {
                    renderFinishingRow(item.id_finishing, item.nama_finishing, item.qty);
                });
            } else {
                tbodyFinishing.innerHTML = '<tr id="empty_state_finishing"><td colspan="3" class="p-5 text-center text-slate-500 italic">Belum ada finishing.</td></tr>';
            }

            switchBomTab('bahan');

            Swal.close();
            openModal('produk');

        } catch (error) {
            console.error(error);
            Swal.fire('Gagal memuat', 'Data produk tidak bisa dimuat. Muat ulang halaman lalu coba lagi.', 'error');
        }
    }

    function filterBahanByMesin(idMesin) {
        const selectBahan = document.getElementById('temp_id_bahan');
        const options = selectBahan.querySelectorAll('.bahan-opt');
        selectBahan.value = "";

        options.forEach(opt => {
            const mesinBahan = opt.getAttribute('data-mesin');
            if (idMesin === "" || mesinBahan == idMesin) {
                opt.classList.remove('hidden');
                opt.disabled = false;
            } else {
                opt.classList.add('hidden');
                opt.disabled = true;
            }
        });
    }

    function addBahanToTable() {
        const selectBahan = document.getElementById('temp_id_bahan');
        const selectRumus = document.getElementById('temp_rumus');

        if (!selectBahan.value) {
            Swal.fire('Pilih Bahan!', 'Silahkan pilih bahan baku.', 'error');
            return;
        }

        const optionSelected = selectBahan.options[selectBahan.selectedIndex];
        const idBahan = selectBahan.value;
        const namaBahan = optionSelected.getAttribute('data-nama');
        const satuan = optionSelected.getAttribute('data-satuan');
        let rumus = selectRumus.value;
        if (rumus === 'Q/N' || rumus === 'Q*K') {
            const faktor = parseFloat(document.getElementById('temp_faktor').value);
            if (!(faktor > 0)) { Swal.fire('Info', 'Isi angka N atau K untuk rumus ini.', 'info'); return; }
            rumus = rumus.replace(rumus === 'Q/N' ? 'N' : 'K', String(faktor));
        }

        if (namaBahan.toLowerCase().includes('jahit')) {
            rumus = "2*(P+L)/100";
        }

        renderBahanRow(idBahan, namaBahan, rumus, satuan);
        selectBahan.value = "";
    }

    document.getElementById('temp_rumus')?.addEventListener('change', function () {
        document.getElementById('temp_faktor').classList.toggle('hidden', !(this.value === 'Q/N' || this.value === 'Q*K'));
    });

    function renderBahanRow(id, nama, rumus, satuan) {
        const tbody = document.getElementById('bom_table_body');
        const emptyState = document.getElementById('empty_state');
        if (emptyState) emptyState.remove();

        const existing = tbody.querySelector(`input[value="${id}"][name="id_bahan[]"]`);
        if (existing) {
            Swal.fire('Info', 'Bahan sudah ada di list.', 'info');
            return;
        }

        const rowId = 'row_' + Math.random().toString(36).substr(2, 9);
        const tr = document.createElement('tr');
        tr.id = rowId;
        tr.className = "hover:bg-slate-50 border-b border-slate-50";

        tr.innerHTML = `
            <td class="p-3">
                <div class="flex items-center gap-3">
                    <div class="w-2 h-2 rounded-full bg-emerald-400"></div>
                    <input type="hidden" name="id_bahan[]" value="${id}">
                    <span class="font-medium">${escapeHtml(nama)}</span>
                    <small class="text-slate-400">${satuan ? '('+satuan+')' : ''}</small>
                </div>
            </td>
            <td class="p-3">
                <input type="hidden" name="cara_hitung_pemakaian[]" value="${rumus}">
                <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 text-[10px] font-medium uppercase tracking-widest">${rumus}</span>
            </td>
            <td class="p-3 text-center">
                <button type="button" onclick="document.getElementById('${rowId}').remove(); checkEmpty();" class="text-slate-500 hover:text-rose-500" aria-label="Hapus" title="Hapus">
                    <i class="far fa-trash-alt"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    }

    function checkEmpty() {
        const tbody = document.getElementById('bom_table_body');
        if (tbody.children.length === 0) {
            tbody.innerHTML = `<tr id="empty_state"><td colspan="3" class="p-5 text-center text-slate-500 italic">Belum ada bahan baku.</td></tr>`;
        }
    }

    // Finishing Penyusun - pola identik dengan Bahan Baku Penyusun di atas
    // (addBahanToTable/renderBahanRow/checkEmpty), cuma tanpa filter per
    // mesin (finishing tidak terikat divisi produksi seperti bahan baku).
    function addFinishingToTable() {
        const selectFinishing = document.getElementById('temp_id_finishing');
        const qtyInput = document.getElementById('temp_finishing_qty');

        if (!selectFinishing.value) {
            Swal.fire('Pilih Finishing!', 'Silahkan pilih finishing.', 'error');
            return;
        }

        const namaFinishing = selectFinishing.options[selectFinishing.selectedIndex].getAttribute('data-nama');
        const idFinishing = selectFinishing.value;
        const qty = parseFloat(qtyInput.value) || 1;

        renderFinishingRow(idFinishing, namaFinishing, qty);
        selectFinishing.value = "";
        qtyInput.value = "1";
    }

    function renderFinishingRow(id, nama, qty) {
        const tbody = document.getElementById('finishing_table_body');
        const emptyState = document.getElementById('empty_state_finishing');
        if (emptyState) emptyState.remove();

        const existing = tbody.querySelector(`input[value="${id}"][name="id_finishing[]"]`);
        if (existing) {
            Swal.fire('Info', 'Finishing sudah ada di list.', 'info');
            return;
        }

        const rowId = 'row_finishing_' + Math.random().toString(36).substr(2, 9);
        const tr = document.createElement('tr');
        tr.id = rowId;
        tr.className = "hover:bg-slate-50 border-b border-slate-50";

        tr.innerHTML = `
            <td class="p-3">
                <div class="flex items-center gap-3">
                    <div class="w-2 h-2 rounded-full bg-purple-400"></div>
                    <input type="hidden" name="id_finishing[]" value="${id}">
                    <span class="font-medium">${escapeHtml(nama)}</span>
                </div>
            </td>
            <td class="p-3">
                <input type="hidden" name="finishing_qty[]" value="${qty}">
                <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-purple-50 text-purple-600 text-[10px] font-medium uppercase tracking-widest">${qty}</span>
            </td>
            <td class="p-3 text-center">
                <button type="button" onclick="document.getElementById('${rowId}').remove(); checkEmptyFinishing();" class="text-slate-500 hover:text-rose-500" aria-label="Hapus" title="Hapus">
                    <i class="far fa-trash-alt"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    }

    function checkEmptyFinishing() {
        const tbody = document.getElementById('finishing_table_body');
        if (tbody.children.length === 0) {
            tbody.innerHTML = `<tr id="empty_state_finishing"><td colspan="3" class="p-5 text-center text-slate-500 italic">Belum ada finishing.</td></tr>`;
        }
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function confirmToggleStatus(id, currentStatus, namaProduk) {
        const mauNonaktifkan = currentStatus === 'aktif';
        Swal.fire({
            title: mauNonaktifkan ? 'Nonaktifkan produk ini?' : 'Aktifkan kembali produk ini?',
            html: mauNonaktifkan
                ? `<b>${escapeHtml(namaProduk)}</b> tidak akan muncul lagi di pilihan produk saat membuat nota baru.<br><small class="text-slate-400">Riwayat transaksi lama tidak berubah, dan bisa diaktifkan lagi kapan saja.</small>`
                : `<b>${escapeHtml(namaProduk)}</b> akan muncul lagi di pilihan produk saat membuat nota baru.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: mauNonaktifkan ? '#5D6A85' : '#10b981',
            confirmButtonText: mauNonaktifkan ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'rounded-lg px-6 py-2.5',
                cancelButton: 'rounded-lg px-6 py-2.5 text-slate-600'
            }
        }).then((result) => {
            if (result.isConfirmed) document.getElementById(`toggle-form-${id}`).submit();
        });
    }

    function confirmDelete(id, name) {
        Swal.fire({
            title: 'Hapus Produk?',
            html: `Menghapus <b>${escapeHtml(name)}</b>?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) document.getElementById(`delete-form-${id}`).submit();
        });
    }
</script>
@endpush
@endsection
