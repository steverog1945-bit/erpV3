@extends('layouts.app')

@section('content')
@include('partials.rupiah-format-script')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Penjualan dan Jasa"
                   subtitle="Jual bahan baku, barang lain, atau jasa langsung ke konsumen - tanpa proses cetak/produksi. Harga diisi manual per transaksi." />

    @if (session('error'))
        <div class="bg-rose-50 text-rose-600 p-4 rounded-2xl mb-4 font-medium">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="bg-rose-50 text-rose-600 p-4 rounded-2xl mb-4 font-medium">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('penjualan-barang.store') }}" method="POST" id="form-penjualan-barang">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <div class="lg:col-span-7 xl:col-span-8">
        <x-card class="mb-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-form.group label="Nomor Nota (contoh)">
                    <x-form.input type="text" value="{{ $no_nota }}" disabled />
                </x-form.group>
                <div class="w-full">
                    <div class="flex items-center justify-between mb-2 ml-2 mr-1">
                        <label class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Konsumen <span class="text-rose-500">*</span></label>
                        <button type="button" onclick="openModal('konsumen-baru')" class="text-indigo-600 hover:text-indigo-700 font-medium text-[11px] flex items-center gap-1">
                            <i class="fas fa-plus text-[9px]"></i> Baru
                        </button>
                    </div>
                    <select id="select_konsumen" name="id_konsumen" class="select2-konsumen-barang w-full">
                        <option value="">-- Cari Nama / No. HP --</option>
                        @foreach($konsumen as $k)
                            <option value="{{ $k->id_konsumen }}" @selected((string) old('id_konsumen', request('id_konsumen')) === (string) $k->id_konsumen)>{{ $k->nama_konsumen }}{{ $k->telepon ? ' - ' . $k->telepon : '' }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-card>

        <x-card class="mb-5">
            <h3 class="text-sm font-medium text-slate-800 mb-4">Tambah Barang</h3>

            <div class="flex gap-2 mb-5">
                <label class="flex-1 flex items-center justify-center gap-2 rounded-xl border border-slate-200 p-2.5 text-xs font-medium cursor-pointer has-[:checked]:bg-indigo-50 has-[:checked]:border-indigo-300 has-[:checked]:text-indigo-600 text-slate-500">
                    <input type="radio" name="tipe_tambah_barang" value="bahan" checked onchange="toggleTipeTambahBarang(this.value)" class="accent-indigo-600"> Bahan Baku (Stok)
                </label>
                <label class="flex-1 flex items-center justify-center gap-2 rounded-xl border border-slate-200 p-2.5 text-xs font-medium cursor-pointer has-[:checked]:bg-emerald-50 has-[:checked]:border-emerald-300 has-[:checked]:text-emerald-600 text-slate-500">
                    <input type="radio" name="tipe_tambah_barang" value="jasa" onchange="toggleTipeTambahBarang(this.value)" class="accent-emerald-600"> Jasa (dari Master)
                </label>
                <label class="flex-1 flex items-center justify-center gap-2 rounded-xl border border-slate-200 p-2.5 text-xs font-medium cursor-pointer has-[:checked]:bg-amber-50 has-[:checked]:border-amber-300 has-[:checked]:text-amber-600 text-slate-500">
                    <input type="radio" name="tipe_tambah_barang" value="custom" onchange="toggleTipeTambahBarang(this.value)" class="accent-amber-600"> Barang Lain (Custom)
                </label>
            </div>

            {{-- BAHAN BAKU (dari master data, stok ikut berkurang) --}}
            <div id="blok-tambah-bahan">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-start">
                    <x-form.group label="Kategori">
                        <x-form.select id="filter_kategori_bahan" onchange="filterBahanByKategori(this.value)">
                            <option value="">-- Pilih Kategori --</option>
                            @foreach($kategori as $kat)
                                <option value="{{ $kat->id_kategori }}">{{ $kat->nama_kategori }}</option>
                            @endforeach
                        </x-form.select>
                    </x-form.group>
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Bahan Baku</label>
                        <select id="select_bahan" class="select2-bahan-barang w-full" disabled>
                            <option value="">-- Ketik Nama Bahan --</option>
                            @foreach($bahan as $b)
                                <option value="{{ $b->id_bahan }}" data-kategori="{{ $b->id_kategori }}" data-nama="{{ $b->nama_bahan }}" data-satuan="{{ $b->nama_satuan }}" data-stok="{{ angka($b->stok, 4) }}">{{ $b->nama_bahan }} ({{ number_format($b->stok, 2, ',', '.') }} {{ $b->nama_satuan }})</option>
                            @endforeach
                        </select>
                        <p id="hint_kunci_bahan" class="text-[11px] text-amber-600 mt-1.5 ml-2">
                            <i class="fas fa-lock text-[10px]"></i> Pilih Kategori dulu untuk mengaktifkan pilihan bahan.
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Qty</label>
                            <input type="number" id="input_qty_bahan" min="0.01" step="0.01" value="1" class="w-full bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium text-center focus:ring-2 focus:ring-indigo-500/20 shadow-inner">
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end mt-4">
                    <div class="md:col-span-3">
                        <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Harga Satuan (Rp) - manual, sesuaikan harga bahan saat ini</label>
                        <input type="text" inputmode="numeric" id="input_harga_bahan" placeholder="0" class="w-full bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium focus:ring-2 focus:ring-indigo-500/20 shadow-inner format-ribuan">
                    </div>
                    <x-button type="button" onclick="tambahBahanKeDaftar()" class="w-full">
                        <i class="fas fa-plus"></i> Tambah ke Daftar
                    </x-button>
                </div>
            </div>

            {{-- JASA (dari Master Jasa, tidak memotong stok)

                 Dipisah dari "Barang Lain (Custom)" karena sumbernya
                 berbeda: di sini jasanya HARUS ada di Master Jasa, dan
                 price-nya bisa mengambil harga borongan/tier dari master.
                 Yang diketik manual hanya kalau memang perlu berbeda -
                 kolom harga tetap bisa ditimpa.

                 Kolom "Biaya" sengaja ditampilkan: harga jual dikurangi
                 biaya operasional + tinta adalah margin sebenarnya.
                 Kalau tidak terlihat, kasir akan menjual tanpa tahu
                 marginnya berapa. --}}
            <div id="blok-tambah-jasa" class="hidden">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-start">
                    <x-form.group label="Divisi">
                        <x-form.select id="filter_divisi_jasa" onchange="filterJasaByDivisi(this.value)">
                            <option value="">-- Semua Divisi --</option>
                            @foreach($mesinJasa as $m)
                                <option value="{{ $m->id_mesin }}">{{ $m->nama_mesin }}</option>
                            @endforeach
                        </x-form.select>
                    </x-form.group>
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-medium text-slate-400 mb-2 ml-2">Jasa</label>
                        <select id="select_jasa_barang" class="w-full" onchange="onJasaBarangChange()">
                            <option value="">-- Ketik Nama Jasa --</option>
                            @foreach($jasa as $j)
                                <option value="{{ $j->id_jasa }}"
                                        data-divisi="{{ $j->id_mesin }}"
                                        data-nama="{{ $j->nama_jasa }}"
                                        data-satuan="{{ $j->nama_satuan }}"
                                        data-tipe-satuan="{{ $j->tipe_satuan }}"
                                        data-harga="{{ $j->harga_default ?? 0 }}"
                                        data-biaya="{{ (float) $j->biaya_operasional + (float) $j->biaya_tinta }}">{{ $j->nama_jasa }}</option>
                            @endforeach
                        </select>
                        <p id="hint_kunci_jasa" class="text-[11px] text-slate-400 mt-1.5 ml-2 hidden">
                            <i class="fas fa-filter text-[10px]"></i> Disaring ke satu divisi. Pilih "Semua Divisi" untuk melihat semua jasa.
                        </p>
                        <p id="jasa-tersaring-kosong" class="hidden text-[11px] text-amber-600 mt-1.5 ml-2">
                            <i class="fas fa-exclamation-triangle text-[10px]"></i>
                            Divisi ini belum punya jasa. Setel divisi di Master Jasa, atau pilih "Semua Divisi".
                        </p>
                    </div>
                    <div>
                        <label class="block text-[10px] font-medium text-slate-400 mb-2 ml-2" id="label_qty_jasa">Qty</label>
                        <input type="number" id="input_qty_jasa" min="0.01" step="0.01" value="1" oninput="hitungQtyJasa()" class="w-full bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium text-center focus:ring-2 focus:ring-emerald-500/20 shadow-inner">
                    </div>
                </div>
                {{-- Ukuran untuk jasa bersatuan luas (m2, cm2) atau panjang (m, cm), mengikuti tipe satuan di Master Satuan. --}}
                <div id="blok_ukuran_jasa" class="hidden grid grid-cols-1 md:grid-cols-4 gap-4 items-end mt-4">
                    <div>
                        <label class="block text-[10px] font-medium text-slate-400 mb-2 ml-2" id="label_p_jasa">Panjang</label>
                        <input type="number" id="input_p_jasa" min="0" step="0.01" oninput="hitungQtyJasa()" class="w-full bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium text-center focus:ring-2 focus:ring-emerald-500/20 shadow-inner">
                    </div>
                    <div id="wrap_l_jasa">
                        <label class="block text-[10px] font-medium text-slate-400 mb-2 ml-2" id="label_l_jasa">Lebar</label>
                        <input type="number" id="input_l_jasa" min="0" step="0.01" oninput="hitungQtyJasa()" class="w-full bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium text-center focus:ring-2 focus:ring-emerald-500/20 shadow-inner">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-medium text-slate-400 mb-2 ml-2">Total dihitung</label>
                        <div id="info_qty_jasa" class="w-full bg-slate-50 rounded-xl py-3 px-4 text-sm font-medium text-slate-700">-</div>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end mt-4">
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-medium text-slate-400 mb-2 ml-2">Harga Satuan (Rp) - terisi dari master, bisa diubah</label>
                        <input type="text" inputmode="numeric" id="input_harga_jasa" placeholder="0" class="w-full bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium focus:ring-2 focus:ring-emerald-500/20 shadow-inner format-ribuan">
                    </div>
                    <div>
                        <label class="block text-[10px] font-medium text-slate-400 mb-2 ml-2">Biaya (operasional + tinta)</label>
                        <div id="info_biaya_jasa" class="w-full bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-500">-</div>
                    </div>
                    <x-button type="button" onclick="tambahJasaKeDaftar()" class="w-full">
                        <i class="fas fa-plus"></i> Tambah ke Daftar
                    </x-button>
                </div>
            </div>

            {{-- BARANG CUSTOM / LAINNYA (bukan dari master bahan baku, tidak memotong stok) --}}
            <div id="blok-tambah-custom" class="hidden">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-start">
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Nama Barang / Jasa</label>
                        <input type="text" id="input_nama_custom" placeholder="mis. Spanduk bekas, jasa titipan, dll" class="w-full bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium focus:ring-2 focus:ring-amber-500/20 shadow-inner">
                    </div>
                    <div>
                        <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Qty</label>
                        <input type="number" id="input_qty_custom" min="0.01" step="0.01" value="1" class="w-full bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium text-center focus:ring-2 focus:ring-amber-500/20 shadow-inner">
                    </div>
                    <div>
                        <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Satuan</label>
                        <input type="text" id="input_satuan_custom" value="Pcs" class="w-full bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium text-center focus:ring-2 focus:ring-amber-500/20 shadow-inner">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end mt-4">
                    <div class="md:col-span-3">
                        <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Harga Satuan (Rp) - manual</label>
                        <input type="text" inputmode="numeric" id="input_harga_custom" placeholder="0" class="w-full bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium focus:ring-2 focus:ring-amber-500/20 shadow-inner format-ribuan">
                    </div>
                    <x-button type="button" onclick="tambahCustomKeDaftar()" variant="warning" class="w-full">
                        <i class="fas fa-plus"></i> Tambah ke Daftar
                    </x-button>
                </div>
                <p class="text-[11px] text-amber-600 mt-2 ml-2">
                    <i class="fas fa-info-circle text-[10px]"></i> Barang/jasa custom TIDAK ada di data master bahan baku dan TIDAK memotong stok - dipakai untuk barang titipan, jasa tambahan, atau barang sekali jual lainnya.
                </p>
            </div>
        </x-card>

        </div>

        <div class="lg:col-span-5 xl:col-span-4 lg:sticky lg:top-6">
        <x-card class="mb-5">
            <h3 class="text-sm font-medium text-slate-800 mb-4">Daftar Barang Dijual</h3>
            <div id="daftar-bahan-kosong" class="text-center py-10 text-slate-400 text-sm">Belum ada barang ditambahkan.</div>
            <div id="daftar-bahan-list" class="divide-y divide-slate-50"></div>
            <div id="wrapper-input-items"></div>

            <div class="flex items-center justify-between gap-4 flex-wrap mt-5 bg-indigo-600 rounded-2xl px-6 py-4">
                <span class="text-xs font-medium text-indigo-100">Total</span>
                <span id="grand-total-bahan" class="text-2xl font-medium text-white">Rp 0</span>
            </div>
        </x-card>

        <x-button type="submit" id="btn-submit-penjualan-barang" class="w-full !py-4" disabled>Simpan &amp; Lanjut Bayar</x-button>
        </div>
        </div>
    </form>
</div>

{{-- MODAL KONSUMEN BARU --}}
<x-modal id="konsumen-baru" title="Daftar Konsumen Baru" maxWidth="md">
    <div class="space-y-5">
        <x-form.group label="Nama lengkap" required dalam>
            <x-form.input type="text" id="new_nama" maxlength="100" />
        </x-form.group>
        <x-form.group label="Telepon/WA" required dalam>
            <x-form.input type="text" id="new_hp" inputmode="tel" maxlength="20" />
        </x-form.group>
        <x-form.group label="Golongan" required dalam>
            <x-form.select id="new_id_golongan">
                @foreach($golongan as $g)
                    <option value="{{ $g->id_golongan }}">{{ $g->nama_golongan }}</option>
                @endforeach
            </x-form.select>
        </x-form.group>
        <x-form.group label="Tipe konsumen">
            <div class="flex gap-2">
                <label class="flex-1 flex items-center justify-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-medium cursor-pointer has-[:checked]:bg-indigo-50 has-[:checked]:border-indigo-300 has-[:checked]:text-indigo-600 text-slate-500">
                    <input type="radio" name="new_tipe_konsumen" value="temporary" checked class="accent-indigo-600"> Guest (sekali pesan)
                </label>
                <label class="flex-1 flex items-center justify-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-medium cursor-pointer has-[:checked]:bg-emerald-50 has-[:checked]:border-emerald-300 has-[:checked]:text-emerald-600 text-slate-500">
                    <input type="radio" name="new_tipe_konsumen" value="permanent" class="accent-emerald-600"> Member (pelanggan tetap)
                </label>
            </div>
        </x-form.group>

        <x-button onclick="saveKonsumen()" class="w-full">
            Simpan Konsumen
        </x-button>
    </div>
</x-modal>

@push('scripts')
<script>
    $(document).ready(function () {
        $('.select2-konsumen-barang').select2({ placeholder: '-- Cari Nama / No. HP --', width: '100%' });

        // Select yang disembunyikan select2 tidak bisa menampilkan pesan "wajib diisi" bawaan browser,
        // jadi tombol simpan tampak tidak bereaksi. Periksa sendiri dan beri tahu dengan jelas.
        document.getElementById('form-penjualan-barang').addEventListener('submit', function (e) {
            if (!$('#select_konsumen').val()) {
                e.preventDefault();
                e.stopImmediatePropagation();
                Swal.fire('Data belum lengkap', 'Pilih konsumen dulu.', 'warning');
                $('#select_konsumen').select2('open');
            }
        }, true);
        $('.select2-bahan-barang').select2({
            width: '100%',
            matcher: function (params, data) {
                const el = data.element;
                const katId = el ? el.getAttribute('data-kategori') : null;

                // GERBANG KATEGORI - bagian yang bikin dropdown ini
                // benar-benar terkunci.
                //
                // Versi lama hanya menulis:
                //     if (katId !== null && kategoriBahanAktif && katId != kategoriBahanAktif) return null;
                // Syaratnya bernilai false saat kategori BELUM dipilih,
                // jadi tidak ada yang tersaring - semua 83 bahan tetap
                // muncul dan dropdown terbuka, padahal select-nya sudah
                // disabled. Kasir bisa memilih bahan tanpa pernah memilih
                // kategori, persis yang seharusnya dicegah.
                //
                // select2 mengabaikan atribut disabled pada <select>
                // setelah inisialisasi (elemen aslinya yang disembunyikan),
                // jadi andalnya bukan pada disabled - andalnya pada
                // matcher ini: tanpa kategori sungguhan, TIDAK ADA bahan
                // yang boleh lolos. Pola sama dengan select_produk di
                // Input Order.
                if (data.id === '' || data.id === undefined) {
                    return data; // baris placeholder
                }
                if (!kategoriBahanAktif || katId === null || String(katId) !== String(kategoriBahanAktif)) {
                    return null;
                }

                if (!params.term || params.term.trim() === '') return data;
                if (data.text.toUpperCase().indexOf(params.term.toUpperCase()) > -1) return data;
                return null;
            }
        });
    });

    // 1. TOGGLE Bahan Baku <-> Jasa <-> Custom
    //
    // Ketiga blok disembunyikan satu per satu, bukan dengan if/else yang
    // bertumpuk. Kalau ada blok yang lupa disembunyikan, isinya tampil
    // bersamaan dengan blok lain dan kasir bisa menambahkan item dari
    // dua sumber sekaligus tanpa sadar.
    function toggleTipeTambahBarang(v) {
        document.getElementById('blok-tambah-bahan').classList.toggle('hidden', v !== 'bahan');
        document.getElementById('blok-tambah-jasa').classList.toggle('hidden', v !== 'jasa');
        document.getElementById('blok-tambah-custom').classList.toggle('hidden', v !== 'custom');
    }

    // Filter jasa per divisi. "Semua Divisi" (value kosong) berarti semua,
    // jadi select JASA TIDAK dikunci di kasus ini, berbeda dari bahan
    // baku yang WAJIB punya kategori. Jasa boleh dijual tanpa ditugaskan
    // ke divisi mana pun, jadi mengunci di sini akan memblokir penjualan
    // yang sah.
    function filterJasaByDivisi(v) {
        const sel = document.getElementById('select_jasa_barang');
        const hint = document.getElementById('hint_kunci_jasa');
        if (!sel) return;

        Array.prototype.forEach.call(sel.options, function (o) {
            if (!o.value) return;
            o.hidden = v !== '' && o.dataset.divisi !== v;
            o.disabled = v !== '' && o.dataset.divisi !== v;
        });

        // Kalau nilai terpilih jadi tersembunyi, bersihkan - kalau tidak,
        // kasir melihat "Jasa Print" di kotak pencarian padahal opsi yang
        // akan terkirim adalah yang berbeda.
        if (sel.selectedOptions[0] && sel.selectedOptions[0].disabled) {
            sel.value = '';
        }

        // Dua hint, satu untuk dua keadaan yang berbeda:
        //   - divisiDipilih : sedang menyaring ke satu divisi
        //   - hasilKosong    : divisi itu tidak punya jasa
        // Tanpa pemisahan ini, kasir melihat dropdown kosong dan tidak
        // tahu apakah jasanya memang habis atau filter-nya yang salah.
        const ada = Array.prototype.some.call(sel.options, function (o) {
            return o.value && !o.disabled;
        });
        const kosongEl = document.getElementById('jasa-tersaring-kosong');
        if (kosongEl) kosongEl.classList.toggle('hidden', !(v !== '' && !ada));
        if (hint) hint.classList.toggle('hidden', v === '' || !ada);
    }

    // Harga + biaya ikut berubah saat jasanya diganti, jadi kasir
    // langsung melihat marginnya sebelum menekan Tambah.
    function onJasaBarangChange() {
        const sel = document.getElementById('select_jasa_barang');
        if (!sel || !sel.value) return;
        const opt = sel.options[sel.selectedIndex];
        const harga = parseFloat(opt.dataset.harga) || 0;
        document.getElementById('input_harga_jasa').value = harga > 0 ? formatRupiah(harga) : '';
        hitungBiayaJasa();
        aturUkuranJasa();
    }

    // Satuan jasa menentukan isian: Luas -> panjang x lebar x jumlah, Panjang -> panjang x jumlah, lainnya qty saja.
    function satuanJasaTerpilih() {
        const sel = document.getElementById('select_jasa_barang');
        const opt = sel && sel.value ? sel.options[sel.selectedIndex] : null;
        const satuan = opt ? (opt.dataset.satuan || 'pcs') : 'pcs';
        return { tipe: opt ? (opt.dataset.tipeSatuan || 'Barang') : 'Barang', satuan, dasar: /^cm/i.test(satuan) ? 'cm' : 'm' };
    }

    function aturUkuranJasa() {
        const s = satuanJasaTerpilih();
        const berukuran = s.tipe === 'Luas' || s.tipe === 'Panjang';
        document.getElementById('blok_ukuran_jasa').classList.toggle('hidden', !berukuran);
        document.getElementById('wrap_l_jasa').classList.toggle('hidden', s.tipe !== 'Luas');
        document.getElementById('label_p_jasa').textContent = 'Panjang (' + s.dasar + ')';
        document.getElementById('label_l_jasa').textContent = 'Lebar (' + s.dasar + ')';
        document.getElementById('label_qty_jasa').textContent = berukuran ? 'Jumlah (pcs)' : 'Qty (' + s.satuan + ')';
        hitungQtyJasa();
    }

    // Qty yang ditagihkan dalam satuan jasa. Untuk satuan biasa sama dengan isian Qty.
    function hitungQtyJasa() {
        const s = satuanJasaTerpilih();
        const jumlah = parseFloat(document.getElementById('input_qty_jasa').value) || 0;
        const p = parseFloat(document.getElementById('input_p_jasa').value) || 0;
        const l = parseFloat(document.getElementById('input_l_jasa').value) || 0;
        let qty = jumlah;
        if (s.tipe === 'Luas') qty = p * l * jumlah;
        if (s.tipe === 'Panjang') qty = p * jumlah;
        qty = Math.round(qty * 10000) / 10000;
        const info = document.getElementById('info_qty_jasa');
        if (info) {
            info.textContent = s.tipe === 'Luas'
                ? p.toLocaleString('id-ID') + ' x ' + l.toLocaleString('id-ID') + ' ' + s.dasar + ' x ' + jumlah.toLocaleString('id-ID') + ' pcs = ' + qty.toLocaleString('id-ID') + ' ' + s.satuan
                : p.toLocaleString('id-ID') + ' ' + s.dasar + ' x ' + jumlah.toLocaleString('id-ID') + ' pcs = ' + qty.toLocaleString('id-ID') + ' ' + s.satuan;
        }
        return { qty, p, l, jumlah, tipe: s.tipe, dasar: s.dasar };
    }

    function hitungBiayaJasa() {
        const sel = document.getElementById('select_jasa_barang');
        const kotak = document.getElementById('info_biaya_jasa');
        if (!sel || !kotak) return;
        const biaya = parseFloat(sel.options[sel.selectedIndex]?.dataset.biaya || 0) || 0;
        kotak.textContent = biaya > 0 ? 'Rp ' + formatRupiah(Math.round(biaya)) : '-';
    }

    // Tambah jasa ke daftar. Bentuk datanya sama dengan bahan & custom
    // supaya renderDaftar() dan submitForm() tidak perlu percabangan
    // tambahan - satu-satunya pembeda adalah field id_jasa (dipakai
    // server untuk mengisi hpp dari biaya master).
    function tambahJasaKeDaftar() {
        // Id field konsumen di halaman ini select_konsumen (bukan id_konsumen seperti di Input Pesanan).
        if (!$('#select_konsumen').val()) return Swal.fire('Data belum lengkap', 'Pilih konsumen dulu.', 'warning');

        const sel = document.getElementById('select_jasa_barang');
        const idJasa = sel.value;
        if (!idJasa) return Swal.fire('Data belum lengkap', 'Pilih jasa dulu.', 'warning');

        const opt = sel.options[sel.selectedIndex];
        const ukuran = hitungQtyJasa();
        const qty = ukuran.qty;
        const harga = cleanRupiah(document.getElementById('input_harga_jasa').value);
        const biaya = parseFloat(opt.dataset.biaya || 0) || 0;

        if (ukuran.tipe === 'Luas' && (ukuran.p <= 0 || ukuran.l <= 0)) return Swal.fire('Data belum lengkap', 'Isi panjang dan lebar.', 'warning');
        if (ukuran.tipe === 'Panjang' && ukuran.p <= 0) return Swal.fire('Data belum lengkap', 'Isi ukuran panjang.', 'warning');
        if (qty <= 0) return Swal.fire('Data belum lengkap', 'Qty jasa harus lebih dari 0.', 'warning');
        if (harga <= 0) return Swal.fire('Data belum lengkap', 'Harga satuan jasa harus diisi.', 'warning');

        // Peringatan margin, bukan pemblokir: kasir yang tahu situasinya
        // boleh tetap menjual. Tapi harga jual di bawah biaya hampir
        // selalu salah input harga, jadi layak diperingatkan.
        if (biaya > 0 && harga < biaya) {
            Swal.fire({
                icon: 'warning',
                title: 'Harga di bawah biaya',
                html: 'Harga satuan (Rp ' + formatRupiah(Math.round(harga)) + ') lebih kecil dari biaya operasional + tinta (Rp ' +
                      formatRupiah(Math.round(biaya)) + '). Tetap ditambahkan?',
                showCancelButton: true,
                confirmButtonText: 'Ya, Tetap Tambahkan',
                cancelButtonText: 'Batal'
            }).then(function (r) {
                if (r.isConfirmed) dorongJasaKeDaftar();
            });
            return;
        }

        dorongJasaKeDaftar();
    }

    function dorongJasaKeDaftar() {
        const sel = document.getElementById('select_jasa_barang');
        const opt = sel.options[sel.selectedIndex];
        const ukuran = hitungQtyJasa();
        const qty = ukuran.qty;
        const harga = cleanRupiah(document.getElementById('input_harga_jasa').value);
        const berukuran = ukuran.tipe === 'Luas' || ukuran.tipe === 'Panjang';

        daftarBahan.push({
            tipe: 'jasa',
            id_jasa: sel.value,
            nama: opt.dataset.nama,
            satuan: opt.dataset.satuan || 'Pcs',
            // Keterangan ukuran untuk daftar, mis. "2 x 1,5 m x 3 pcs".
            ukuran: berukuran ? document.getElementById('info_qty_jasa').textContent.split(' = ')[0] : '',
            p: berukuran ? ukuran.p : '',
            l: ukuran.tipe === 'Luas' ? ukuran.l : '',
            jumlah: berukuran ? ukuran.jumlah : '',
            qty: qty,
            harga: harga,
            biaya: parseFloat(opt.dataset.biaya || 0) || 0,
            divisi: opt.dataset.divisi || ''
        });
        // renderDaftarBahan(), bukan renderDaftar() - nama fungi yang
        // benar di berkas ini. Memanggil yang salah akan melempar
        // ReferenceError dan item tidak pernah masuk daftar, padahal
        // kasir sudah menekan tombol Tambah.
        renderDaftarBahan();

        $('#select_jasa_barang').val('');
        document.getElementById('input_qty_jasa').value = 1;
        document.getElementById('input_p_jasa').value = '';
        document.getElementById('input_l_jasa').value = '';
        aturUkuranJasa();
        document.getElementById('input_harga_jasa').value = '';
        document.getElementById('info_biaya_jasa').textContent = '-';

        Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 1800 })
            .fire({ icon: 'success', title: 'Jasa ditambahkan ke daftar' });
    }

    // 2. MODAL KONSUMEN BARU (pola sama dengan penjualan/create.blade.php)
    async function saveKonsumen() {
        const nama = document.getElementById('new_nama').value;
        const hp = document.getElementById('new_hp').value;
        const idGol = document.getElementById('new_id_golongan').value;
        const tipeKonsumen = document.querySelector('input[name="new_tipe_konsumen"]:checked').value;

        if (!nama) return Swal.fire('Data belum lengkap', 'Nama wajib diisi!', 'warning');

        try {
            const response = await fetch('{{ url('/konsumen/store-ajax') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: JSON.stringify({
                    nama_konsumen: nama,
                    telepon: hp,
                    id_golongan: idGol,
                    tipe_konsumen: tipeKonsumen
                })
            });

            const res = await response.json();

            if (res.status === 'success') {
                let newOption = new Option(res.data.nama_konsumen + (res.data.telepon ? ' - ' + res.data.telepon : ''), res.data.id_konsumen, true, true);
                $('#select_konsumen').append(newOption).trigger('change');
                closeModal('konsumen-baru');
                Swal.fire('Berhasil', 'Pelanggan Baru Terpilih', 'success');
            } else {
                Swal.fire('Gagal', res.message || 'Data tidak dapat disimpan.', 'error');
            }
        } catch (e) {
            Swal.fire('Gagal menyimpan', 'Data belum tersimpan. Periksa jaringan lalu coba lagi.', 'error');
        }
    }

    // 3. Bahan TERKUNCI sampai Kategori dipilih - pola sama dengan Input Pesanan
    // (penjualan/create.blade.php), supaya kasir tidak asal pilih bahan
    // tanpa sadar kategorinya dulu.
    let kategoriBahanAktif = '';
    function filterBahanByKategori(idKat) {
        kategoriBahanAktif = idKat;
        $('#select_bahan').val('').trigger('change');
        const terkunci = !idKat;
        $('#select_bahan').prop('disabled', terkunci);
        document.getElementById('hint_kunci_bahan').classList.toggle('hidden', !terkunci);
    }

    let daftarBahan = [];

    function tambahBahanKeDaftar() {
        const idBahan = document.getElementById('select_bahan').value;
        if (!idBahan) {
            Swal.fire('Pilih Bahan Dulu', 'Pilih kategori & bahan baku yang mau dijual.', 'warning');
            return;
        }
        const opt = document.querySelector(`#select_bahan option[value="${idBahan}"]`);
        const qty = parseFloat(document.getElementById('input_qty_bahan').value) || 0;
        const harga = cleanRupiah(document.getElementById('input_harga_bahan').value);

        if (qty <= 0) {
            Swal.fire('Qty Tidak Valid', 'Qty harus lebih dari 0.', 'warning');
            return;
        }
        if (harga <= 0) {
            Swal.fire('Harga Belum Diisi', 'Isi harga satuan bahan ini dulu.', 'warning');
            return;
        }

        const stokTersedia = parseFloat(opt.dataset.stok) || 0;
        if (qty > stokTersedia) {
            Swal.fire({
                title: 'Qty Melebihi Stok',
                html: `Stok tersedia cuma <b>${stokTersedia}</b> ${escHtml(opt.dataset.satuan)}, tapi qty diisi <b>${qty}</b>. Tetap lanjutkan?<br><small class="text-slate-400">Stok bisa jadi minus - pastikan barang fisiknya memang ada.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Lanjutkan',
                cancelButtonText: 'Batal',
            }).then((r) => { if (r.isConfirmed) prosesTambahBahan(idBahan, opt, qty, harga); });
            return;
        }
        prosesTambahBahan(idBahan, opt, qty, harga);
    }

    function prosesTambahBahan(idBahan, opt, qty, harga) {
        daftarBahan.push({ tipe: 'bahan', id_bahan: idBahan, nama: opt.dataset.nama, satuan: opt.dataset.satuan, qty, harga });
        renderDaftarBahan();
        document.getElementById('select_bahan').value = '';
        $('#select_bahan').trigger('change');
        document.getElementById('input_qty_bahan').value = 1;
        document.getElementById('input_harga_bahan').value = '';
    }

    function tambahCustomKeDaftar() {
        const nama = document.getElementById('input_nama_custom').value.trim();
        const qty = parseFloat(document.getElementById('input_qty_custom').value) || 0;
        const satuan = document.getElementById('input_satuan_custom').value.trim() || 'Pcs';
        const harga = cleanRupiah(document.getElementById('input_harga_custom').value);

        if (!nama) {
            Swal.fire('Nama Belum Diisi', 'Isi nama barang/jasa custom ini dulu.', 'warning');
            return;
        }
        if (qty <= 0) {
            Swal.fire('Qty Tidak Valid', 'Qty harus lebih dari 0.', 'warning');
            return;
        }
        if (harga <= 0) {
            Swal.fire('Harga Belum Diisi', 'Isi harga satuan barang ini dulu.', 'warning');
            return;
        }

        daftarBahan.push({ tipe: 'custom', nama, satuan, qty, harga });
        renderDaftarBahan();
        document.getElementById('input_nama_custom').value = '';
        document.getElementById('input_qty_custom').value = 1;
        document.getElementById('input_satuan_custom').value = 'Pcs';
        document.getElementById('input_harga_custom').value = '';
    }

    function hapusBahanDariDaftar(index) {
        daftarBahan.splice(index, 1);
        renderDaftarBahan();
    }

    function renderDaftarBahan() {
        const listEl = document.getElementById('daftar-bahan-list');
        const kosongEl = document.getElementById('daftar-bahan-kosong');
        const wrapInput = document.getElementById('wrapper-input-items');
        listEl.innerHTML = '';
        wrapInput.innerHTML = '';

        kosongEl.classList.toggle('hidden', daftarBahan.length > 0);

        let grandTotal = 0;
        daftarBahan.forEach((item, idx) => {
            const subtotal = item.qty * item.harga;
            grandTotal += subtotal;

            const baris = document.createElement('div');
            baris.className = 'flex items-center gap-3 py-3';
            const namaEl = document.createElement('div');
            namaEl.className = 'flex-1 min-w-0';
            const namaTitle = document.createElement('p');
            namaTitle.className = 'text-sm font-medium text-slate-700 truncate';
            namaTitle.textContent = item.nama;
            if (item.tipe === 'custom') {
                const badge = document.createElement('span');
                badge.className = 'text-[9px] font-medium text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded ml-1.5 align-middle';
                badge.textContent = 'CUSTOM';
                namaTitle.appendChild(badge);
            }
            if (item.tipe === 'jasa') {
                const badge = document.createElement('span');
                badge.className = 'text-[9px] font-medium text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded ml-1.5 align-middle';
                badge.textContent = 'JASA';
                namaTitle.appendChild(badge);

                // Margin ditulis di baris daftar, bukan cuma di form
                // penambahan. Kalau tidak, kasir tidak akan pernah tahu
                // bahwa ada jasa yang dijual di bawah biaya - padahal
                // peringatan di form hanya muncul sesaat saat menambah.
                if (item.biaya > 0) {
                    const margin = item.harga - item.biaya;
                    const marginEl = document.createElement('p');
                    const persen = item.harga > 0 ? Math.round((margin / item.harga) * 100) : 0;
                    // Warna ikut tandanya: merah kalau rugi, abu-abu
                    // kalau tipis, hijau kalau sehat.
                    marginEl.className = 'text-[11px] ' + (
                        margin <= 0 ? 'text-rose-500'
                            : (persen < 20 ? 'text-amber-600' : 'text-emerald-600')
                    );
                    marginEl.textContent = 'Biaya Rp ' + item.biaya.toLocaleString('id-ID') + ' - margin ' + persen + '%';
                    namaEl.appendChild(marginEl);
                }
            }
            const namaSub = document.createElement('p');
            namaSub.className = 'text-[11px] text-slate-400';
            namaSub.textContent = (item.ukuran ? item.ukuran + ' = ' : '') + `${item.qty.toLocaleString('id-ID')} ${item.satuan} x Rp ${item.harga.toLocaleString('id-ID')}`;
            namaEl.append(namaTitle, namaSub);

            const subtotalEl = document.createElement('span');
            subtotalEl.className = 'text-sm font-medium text-slate-600 shrink-0';
            subtotalEl.textContent = 'Rp ' + subtotal.toLocaleString('id-ID');

            const btnHapus = document.createElement('button');
            btnHapus.type = 'button';
            btnHapus.className = 'w-8 h-8 rounded-lg text-rose-400 hover:bg-rose-50 shrink-0';
            btnHapus.innerHTML = '<i class="far fa-trash-alt text-xs"></i>';
            btnHapus.onclick = () => hapusBahanDariDaftar(idx);

            baris.append(namaEl, subtotalEl, btnHapus);
            listEl.appendChild(baris);

            // Field yang dikirim ke server per tipe item. Jasa memakai
            // id_jasa, BUKAN id_bahan dan BUKAN nama bebas - server
            // mengambil ulang nama & harganya dari Master Jasa.
            const fields = item.tipe === 'custom'
                ? ['tipe', 'nama', 'qty', 'harga']
                : item.tipe === 'jasa'
                    ? ['tipe', 'id_jasa', 'qty', 'harga', 'p', 'l', 'jumlah']
                    : ['tipe', 'id_bahan', 'qty', 'harga'];
            fields.forEach(field => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `items[${idx}][${field}]`;
                input.value = item[field];
                wrapInput.appendChild(input);
            });
        });

        document.getElementById('grand-total-bahan').textContent = 'Rp ' + grandTotal.toLocaleString('id-ID');
        document.getElementById('btn-submit-penjualan-barang').disabled = daftarBahan.length === 0;
    }
</script>
@endpush
@endsection
