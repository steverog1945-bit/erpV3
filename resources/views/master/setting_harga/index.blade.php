@extends('layouts.app')

@section('content')
@include('partials.rupiah-format-script')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Daftar Harga & Tingkat" subtitle="Harga jual produk per golongan konsumen, bisa bertingkat berdasarkan qty.">
        <x-slot:actions>
            <x-button href="{{ route('setting-harga.cetak', request()->only(['golongan', 'kategori'])) }}" variant="secondary">
                <i class="fas fa-print"></i>
                Cetak Daftar Harga
            </x-button>
            <x-button href="{{ route('harga-promo.index') }}" variant="secondary">
                <i class="fas fa-fire"></i>
                Harga Promo
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- FILTER & SEARCH --}}
    <x-card class="mb-6">
        <form action="{{ route('setting-harga.index') }}" method="GET" class="flex flex-wrap items-end gap-4">
            <div class="w-full md:w-64 lg:w-72">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari Produk</label>
                <x-form.input type="text" name="search" value="{{ request('search') }}" placeholder="Nama produk..." />
            </div>

            <div class="w-full md:w-52">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Mesin/Divisi</label>
                <x-form.select name="category">
                    <option value="">Semua Mesin</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id_mesin }}" {{ request('category') == $cat->id_mesin ? 'selected' : '' }}>{{ strtoupper($cat->nama_mesin) }}</option>
                    @endforeach
                </x-form.select>
            </div>

            <x-button type="submit">
                <i class="fas fa-filter"></i>
                Filter
            </x-button>

            @if(request()->anyFilled(['search', 'category']))
                <a href="{{ route('setting-harga.index') }}"
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
                <th class="px-6 py-4 text-left">Produk</th>
                @foreach($golongan as $g)
                <th class="px-4 py-4 text-center">{{ $g->nama_golongan }}</th>
                @endforeach
                <th class="px-6 py-4 text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($produk as $i => $p)
            @php
                $hargaProduk = $all_harga[$p->id_produk] ?? collect();
                $satuanRentangRow = $p->metode_hitung === 'ukuran' ? 'mÃ‚Â²' : 'Pcs';
                // rtrim ganda: buang nol desimal berlebih (2.00 -> 2, 0.50 -> 0.5)
                // supaya tier bilangan bulat tetap tampil bersih, bukan "2.00".
                $fmtRentang = fn ($v) => angka_polos((float) $v, 2);
            @endphp
            <tr class="group hover:bg-slate-50/50 transition-all">
                <td class="px-4 py-3.5 text-center text-[11px] text-slate-400">{{ $produk->firstItem() + $i }}</td>
                <td class="px-6 py-3.5">
                    <div class="text-[14px] font-medium text-slate-700 uppercase">{{ $p->nama_produk }}</div>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="text-[9px] bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded font-medium uppercase border border-indigo-100">
                            <i class="fas fa-print mr-1"></i>{{ $p->nama_mesin ?? 'Tanpa Mesin' }}
                        </span>
                        <span class="text-[9px] text-slate-400 font-medium uppercase tracking-widest">{{ $p->metode_hitung }} &middot; {{ $satuanRentangRow }}</span>
                    </div>
                </td>

                @foreach($golongan as $g)
                <td class="px-4 py-3.5 text-center">
                    @php $tiersGol = $hargaProduk->where('id_golongan', $g->id_golongan); @endphp
                    <span id="harga-summary-{{ $p->id_produk }}-{{ $g->id_golongan }}">
                        @if($tiersGol->isEmpty())
                            <span class="text-slate-500 italic text-[10px]">Belum Diatur</span>
                        @elseif($tiersGol->count() === 1)
                            @php $satu = $tiersGol->first(); @endphp
                            <span class="text-slate-700 font-medium">Rp{{ number_format($satu->harga_jual, 0, ',', '.') }}</span>
                            @if($satu->range_min > 0.01 || $satu->range_max)
                                <div class="text-[9px] text-slate-400">{{ $fmtRentang($satu->range_min) }}-{{ $satu->range_max !== null ? $fmtRentang($satu->range_max) : 'Ã¢Ë†Å¾' }} {{ $satuanRentangRow }}</div>
                            @endif
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-indigo-50 text-indigo-600 border border-indigo-100">{{ $tiersGol->count() }} TIER</span>
                            <div class="text-[9px] text-slate-400 mt-0.5">Rp{{ number_format($tiersGol->min('harga_jual'), 0, ',', '.') }} - Rp{{ number_format($tiersGol->max('harga_jual'), 0, ',', '.') }}</div>
                        @endif
                    </span>
                </td>
                @endforeach

                <td class="px-6 py-3.5 text-right">
                    <x-button size="icon" variant="ghost" onclick="openModal('harga-produk-{{ $p->id_produk }}'); perbaruiSemuaSaranMinTier({{ $p->id_produk }})" class="border border-slate-100 hover:text-indigo-600 hover:border-indigo-200" title="Kelola Harga">
                        <i class="fas fa-sliders-h text-sm"></i>
                    </x-button>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="{{ count($golongan) + 3 }}">
                    <x-empty-state title="Produk tidak ditemukan" description="Sesuaikan filter di atas." />
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$produk" />

    {{-- MODAL KELOLA HARGA - satu per produk, DIROMBAK TOTAL: dulu satu
         daftar tier datar dengan dropdown Golongan yang harus dipilih ULANG
         di setiap kali tambah tier (menyusahkan kalau mau isi banyak tier
         sekaligus, gampang salah pilih golongan). Sekarang golongan jadi
         TAB - pilih golongan SEKALI lewat tab, semua tier yang ditambahkan
         selama tab itu aktif otomatis untuk golongan tersebut, tidak perlu
         pilih ulang sama sekali. Tetap satu form/aksi per baris tier
         (add/edit/hapus satu tier tidak pernah menyentuh tier lain). --}}
    @foreach($produk as $p)
    @php
        $hargaProdukModal = $all_harga[$p->id_produk] ?? collect();
        // Satuan rentang Min/Max HARUS eksplisit - mÃ‚Â² untuk metode 'ukuran',
        // Pcs untuk 'quantity', ikut definisi yang sama dengan
        // HargaService::getHargaEfektif().
        $satuanRentang = $p->metode_hitung === 'ukuran' ? 'mÃ‚Â²' : 'Pcs';
        $stepRentang = $p->metode_hitung === 'ukuran' ? '0.01' : '1';
        $defaultMin = $p->metode_hitung === 'ukuran' ? '0.01' : '1';
        $fmtNilai = fn ($v) => angka_polos((float) $v, 2);
    @endphp
    {{-- maxWidth 6xl (bukan 4xl) - modal ini padat secara horizontal (tab
         golongan + baris tier 3 kolom + tombol aksi), melebarkan modal
         memberi ruang supaya baris tier tidak perlu membungkus/menumpuk di
         resolusi kecil (itu yang bikin modal jadi tinggi & butuh banyak
         scroll - melebarkan ke samping mengurangi kebutuhan memanjang ke
         bawah). Sama dengan lebar modal Produk & BOM untuk konsistensi. --}}
    <x-modal id="harga-produk-{{ $p->id_produk }}" title="Kelola Harga: {{ $p->nama_produk }}"
         subtitle="Harga dasar dan harga bertingkat yang dipakai saat membuat nota"
         icon="fa-tags" maxWidth="6xl">
        <p class="text-xs text-slate-400 mb-5 -mt-2">
            Rentang dalam <b class="text-slate-500">{{ $satuanRentang }}</b>{{ $p->metode_hitung === 'ukuran' ? ' (luas = panjang Ãƒâ€” lebar, boleh pecahan - mis. 0,09 mÃ‚Â²)' : '' }}. Kosongkan <b class="text-slate-500">Max</b> untuk rentang tak terbatas. Harga dipilih otomatis di Input Order sesuai golongan &amp; {{ $satuanRentang }} order.
        </p>

        {{-- TAB GOLONGAN --}}
        <div class="rounded-2xl border-2 border-dashed border-slate-200 overflow-hidden">
            <div class="flex border-b border-slate-100 overflow-x-auto">
                @foreach($golongan as $i => $g)
                @php $jumlahTierGol = $hargaProdukModal->where('id_golongan', $g->id_golongan)->count(); @endphp
                <button type="button" onclick="switchHargaTab({{ $p->id_produk }}, {{ $g->id_golongan }})"
                    id="harga-tab-btn-{{ $p->id_produk }}-{{ $g->id_golongan }}"
                    class="flex-1 min-w-[110px] py-3 px-2 text-[11px] font-medium uppercase tracking-widest transition-all whitespace-nowrap {{ $i === 0 ? 'bg-indigo-50 text-indigo-600 border-b-2 border-indigo-500' : 'text-slate-400 border-b-2 border-transparent hover:text-slate-600' }}">
                    {{ $g->nama_golongan }}
                    <span class="ml-1 text-[9px] {{ $jumlahTierGol > 0 ? 'text-indigo-600' : 'text-slate-500' }}">({{ $jumlahTierGol }})</span>
                </button>
                @endforeach
            </div>

            @foreach($golongan as $i => $g)
            @php $tiersProdukGol = $hargaProdukModal->where('id_golongan', $g->id_golongan); @endphp
            <div id="harga-tab-panel-{{ $p->id_produk }}-{{ $g->id_golongan }}" class="p-5 {{ $i === 0 ? '' : 'hidden' }}">
                {{-- Copy SELURUH tier golongan ini ke semua golongan lain -
                     dipakai kalau harga memang sama rata di semua golongan,
                     supaya tidak perlu mengetik ulang tier satu-satu di
                     tiap tab. Aksi ini MENGGANTI TOTAL tier golongan lain
                     (bukan digabung) - selalu dikonfirmasi dulu. --}}
                <div class="flex justify-end mb-3">
                    <button type="button" onclick="confirmCopyHargaGolongan({{ $p->id_produk }}, {{ $g->id_golongan }}, '{{ $g->nama_golongan }}')" class="text-[11px] font-medium text-indigo-600 hover:text-indigo-700 flex items-center gap-1.5">
                        <i class="fas fa-clone text-[10px]"></i> Copy Harga {{ $g->nama_golongan }} ke Semua Golongan Lain
                    </button>
                </div>

                {{-- Tambah tier - TANPA pemilihan golongan, sudah tersirat
                     dari tab yang sedang aktif (id_golongan dikirim lewat
                     hidden input). --}}
                <form action="{{ route('setting-harga.tier.store', $p->id_produk) }}"
                    class="tier-add-form-produk grid grid-cols-1 md:grid-cols-4 gap-2.5 mb-4 bg-emerald-50/50 p-4 rounded-2xl border border-emerald-100"
                    data-id-produk="{{ $p->id_produk }}" data-id-golongan="{{ $g->id_golongan }}" data-satuan-rentang="{{ $satuanRentang }}">
                    @csrf
                    <input type="hidden" name="id_golongan" value="{{ $g->id_golongan }}">
                    <div class="ld">
                        <input type="number" name="range_min" placeholder="Min" value="{{ $defaultMin }}" min="{{ $stepRentang }}" step="{{ $stepRentang }}" required class="w-full border-none rounded-xl py-2.5 px-3 text-sm font-medium shadow-sm">
                        <label class="ld-label">Min ({{ $satuanRentang }})<span class="ld-wajib" aria-hidden="true">*</span></label>
                    </div>
                    <div class="ld">
                        <input type="number" name="range_max" placeholder="~ (tak terbatas)" min="{{ $stepRentang }}" step="{{ $stepRentang }}" class="w-full border-none rounded-xl py-2.5 px-3 text-sm font-medium shadow-sm">
                        <label class="ld-label">Max ({{ $satuanRentang }})</label>
                    </div>
                    <div class="ld">
                        <input type="text" inputmode="numeric" name="harga_jual" placeholder="0" required class="w-full border-none rounded-xl py-2.5 px-3 text-sm font-medium shadow-sm format-ribuan">
                        <label class="ld-label">Harga (Rp)<span class="ld-wajib" aria-hidden="true">*</span></label>
                    </div>
                    <div class="flex items-end">
                        <x-button type="submit" variant="success" class="w-full !py-2.5">
                            <i class="fas fa-plus"></i> Tambahkan
                        </x-button>
                    </div>
                </form>

                {{-- max-h + overflow-y-auto pada WRAPPER (bukan <tbody> - overflow
                     tidak reliable di elemen tbody lintas browser) supaya golongan
                     dengan banyak tier scroll SENDIRI di area ini, bukan mendorong
                     seluruh modal jadi tinggi tak terbatas. --}}
                <div class="max-h-[360px] overflow-y-auto rounded-xl">
                {{-- table-fixed + !min-w-0 WAJIB. Akar masalahnya: class
                     .modern-table (dipakai bersama SEMUA tabel di app ini)
                     punya "min-width: 1000px" bawaan - sengaja untuk tabel
                     data lebar asli (biar kolom tetap presisi & scroll
                     horizontal alih-alih terjepit). Tabel INI bukan tabel
                     data biasa (satu sel colspan=4 berisi form/grid custom),
                     jadi lantai 1000px itu justru memaksa baris jadi lebar
                     tak wajar & terpotong di layar sempit - persis yang
                     dikeluhkan. !min-w-0 meniadakan lantai itu KHUSUS di
                     sini, table-fixed mencegah tabel melebar lagi ikut
                     lebar konten internalnya. --}}
                <x-table bare class="table-fixed !min-w-0">
                    <thead>
                        <tr>
                            <th class="p-3 text-left">Min ({{ $satuanRentang }})</th>
                            <th class="p-3 text-left">Max ({{ $satuanRentang }})</th>
                            <th class="p-3 text-left">Harga (Rp)</th>
                            <th class="p-3 text-center w-20">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tier-tbody-produk-{{ $p->id_produk }}-gol-{{ $g->id_golongan }}" class="divide-y divide-slate-50" data-id-produk="{{ $p->id_produk }}" data-id-golongan="{{ $g->id_golongan }}">
                        <tr class="tier-empty-msg-produk" {{ $tiersProdukGol->isNotEmpty() ? 'hidden' : '' }}>
                            <td colspan="4" class="p-5 text-center text-slate-500 italic">Belum ada harga untuk golongan ini - order akan ditolak di Kasir sampai diisi.</td>
                        </tr>
                        @foreach($tiersProdukGol as $t)
                        <tr class="tier-row-produk" data-id="{{ $t->id_harga }}">
                            <td colspan="4" class="p-2">
                                {{-- flex-col di layar sempit (form 3 input BERTUMPUK penuh
                                     lebar, bukan mengecil berdesakan jadi 3 kolom sempit) ->
                                     flex-row lagi mulai breakpoint sm supaya baris tetap
                                     ringkas di layar normal. Tombol Simpan sengaja DI LUAR
                                     <form> (dihubungkan lewat atribut form="...") supaya
                                     kolom Harga tidak perlu berdesakan dengan tombol di
                                     dalamnya - itu sumber utama baris "terlalu panjang"/
                                     membungkus tak beraturan di resolusi kecil sebelumnya. --}}
                                <div class="flex flex-col sm:flex-row sm:items-center gap-2 bg-white border border-slate-100 rounded-xl p-2">
                                    <form id="tier-edit-form-{{ $t->id_harga }}" action="{{ route('setting-harga.tier.update', $t->id_harga) }}" class="tier-edit-form-produk grid grid-cols-1 sm:grid-cols-3 gap-2 flex-1">
                                        @csrf
                                        <input type="hidden" name="_versi" value="{{ \App\Support\VersiBaris::token($t) }}">
                                        <input type="number" name="range_min" value="{{ $fmtNilai($t->range_min) }}" min="{{ $stepRentang }}" step="{{ $stepRentang }}" required class="text-sm text-center bg-slate-50 border-none rounded-lg py-2 font-medium">
                                        <input type="number" name="range_max" value="{{ $t->range_max !== null ? $fmtNilai($t->range_max) : '' }}" min="{{ $stepRentang }}" step="{{ $stepRentang }}" placeholder="~" class="text-sm text-center bg-slate-50 border-none rounded-lg py-2 font-medium">
                                        <div class="relative">
                                            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-[10px] text-slate-400 font-medium pointer-events-none">Rp</span>
                                            <input type="text" inputmode="numeric" name="harga_jual" value="{{ number_format((int) $t->harga_jual, 0, '', '.') }}" required class="w-full pl-7 text-sm bg-slate-50 border-none rounded-lg py-2 font-medium format-ribuan">
                                        </div>
                                    </form>
                                    <div class="flex items-center gap-2 shrink-0 self-end sm:self-auto">
                                        <button type="submit" form="tier-edit-form-{{ $t->id_harga }}" class="w-9 h-9 flex items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all shrink-0" title="Simpan">
                                            <i class="fas fa-save text-xs"></i>
                                        </button>
                                        <button type="button" onclick="confirmHapusTierHarga({{ $t->id_harga }})" class="w-9 h-9 flex items-center justify-center rounded-lg text-rose-400 hover:bg-rose-50 transition-all shrink-0" title="Hapus tier ini">
                                            <i class="far fa-trash-alt text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </x-table>
                </div>
            </div>
            @endforeach
        </div>

        <div class="pt-5 flex justify-end">
            <x-button type="button" variant="secondary" onclick="closeModal('harga-produk-{{ $p->id_produk }}')">Tutup</x-button>
        </div>
    </x-modal>
    @endforeach

</div>

@push('scripts')
<script>
    // Aksi tier harga (tambah/edit/hapus) dikirim lewat fetch() dengan body
    // JSON - modal tetap terbuka, tabel update di tempat tanpa reload
    // halaman (sebelumnya submit form biasa = reload penuh tiap satu tier,
    // lambat kalau mau memasukkan beberapa tier berturut-turut). Body
    // HARUS JSON (bukan FormData/multipart) untuk method PATCH/DELETE -
    // PHP tidak otomatis mem-parse body multipart untuk method selain POST
    // asli, jadi kalau tetap FormData request sampai ke Laravel dengan
    // input kosong dan validasi selalu gagal walau datanya kelihatan benar.
    //
    const csrfTokenHarga = document.querySelector('meta[name="csrf-token"]').content;

    // Tab Golongan di dalam modal Kelola Harga - dulu golongan cuma dropdown
    // di form Tambah (harus dipilih ULANG di setiap tier baru), sekarang tab:
    // pilih golongan sekali, tabel & form Tambah di bawahnya otomatis sudah
    // untuk golongan itu sampai tab-nya diganti.
    function switchHargaTab(idProduk, idGolongan) {
        document.querySelectorAll(`[id^="harga-tab-panel-${idProduk}-"]`).forEach(panel => {
            panel.classList.toggle('hidden', panel.id !== `harga-tab-panel-${idProduk}-${idGolongan}`);
        });
        document.querySelectorAll(`[id^="harga-tab-btn-${idProduk}-"]`).forEach(btn => {
            const aktif = btn.id === `harga-tab-btn-${idProduk}-${idGolongan}`;
            btn.classList.toggle('bg-indigo-50', aktif);
            btn.classList.toggle('text-indigo-600', aktif);
            btn.classList.toggle('border-indigo-500', aktif);
            btn.classList.toggle('text-slate-400', !aktif);
            btn.classList.toggle('border-transparent', !aktif);
        });
        perbaruiSaranMinTier(idProduk, idGolongan);
    }

    // Saran otomatis Min tier berikutnya - dulu form "Tambah Tier" SELALU
    // default ke Min=1/0.01 apa pun isi tier yang sudah ada, jadi begitu
    // sudah ada tier "1 s/d 10", operator ketemu error "tumpang tindih"
    // pas coba tambah tier berikutnya dari Min=1 lagi, dan berakhir coba-
    // coba sampai ke angka aneh semacam 1,01/10,01 - padahal cukup 11.
    // Disarankan otomatis: Max tier TERTINGGI (yang punya Max, bukan tak
    // terbatas) + 1 untuk Pcs, atau +0.01 untuk mÃ‚Â² (boleh pecahan). Kalau
    // belum ada tier sama sekali, atau semua tier yang ada tak terbatas
    // (Max kosong), tidak ada saran yang masuk akal - biarkan default HTML.
    function perbaruiSaranMinTier(idProduk, idGolongan) {
        const form = document.querySelector(`.tier-add-form-produk[data-id-produk="${idProduk}"][data-id-golongan="${idGolongan}"]`);
        const tbody = document.getElementById(`tier-tbody-produk-${idProduk}-gol-${idGolongan}`);
        if (!form || !tbody) return;
        const minInput = form.querySelector('[name="range_min"]');
        const isPcs = (minInput.step || '1') === '1';
        const rows = Array.from(tbody.querySelectorAll('.tier-row-produk'));
        let maxTertinggi = null;
        rows.forEach(r => {
            const maxVal = r.querySelector('[name="range_max"]').value;
            if (maxVal === '') return;
            const v = parseFloat(maxVal);
            if (maxTertinggi === null || v > maxTertinggi) maxTertinggi = v;
        });
        if (maxTertinggi === null) return;
        minInput.value = isPcs ? (Math.round(maxTertinggi) + 1) : (Math.round((maxTertinggi + 0.01) * 100) / 100);
    }

    function perbaruiSemuaSaranMinTier(idProduk) {
        document.querySelectorAll(`.tier-add-form-produk[data-id-produk="${idProduk}"]`).forEach(form => {
            perbaruiSaranMinTier(idProduk, form.dataset.idGolongan);
        });
    }

    function buildHargaTierRowHtml(t, stepRentang) {
        const formId = `tier-edit-form-${t.id_harga}`;
        return `
            <tr class="tier-row-produk" data-id="${t.id_harga}">
                <td colspan="4" class="p-2">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 bg-white border border-slate-100 rounded-xl p-2">
                        <form id="${formId}" action="{{ url('/setting-harga-tier') }}/${t.id_harga}" class="tier-edit-form-produk grid grid-cols-1 sm:grid-cols-3 gap-2 flex-1">
                            <input type="hidden" name="_token" value="${csrfTokenHarga}">
                            <input type="hidden" name="_versi" value="${t.updated_at || ''}">
                            <input type="number" name="range_min" value="${t.range_min}" min="${stepRentang}" step="${stepRentang}" required class="text-sm text-center bg-slate-50 border-none rounded-lg py-2 font-medium">
                            <input type="number" name="range_max" value="${t.range_max ?? ''}" min="${stepRentang}" step="${stepRentang}" placeholder="~" class="text-sm text-center bg-slate-50 border-none rounded-lg py-2 font-medium">
                            <div class="relative">
                                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-[10px] text-slate-400 font-medium pointer-events-none">Rp</span>
                                <input type="text" inputmode="numeric" name="harga_jual" value="${formatRupiah(parseInt(t.harga_jual))}" required class="w-full pl-7 text-sm bg-slate-50 border-none rounded-lg py-2 font-medium format-ribuan">
                            </div>
                        </form>
                        <div class="flex items-center gap-2 shrink-0 self-end sm:self-auto">
                            <button type="submit" form="${formId}" class="w-9 h-9 flex items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all shrink-0" title="Simpan">
                                <i class="fas fa-save text-xs"></i>
                            </button>
                            <button type="button" onclick="confirmHapusTierHarga(${t.id_harga})" class="w-9 h-9 flex items-center justify-center rounded-lg text-rose-400 hover:bg-rose-50 transition-all shrink-0" title="Hapus tier ini">
                                <i class="far fa-trash-alt text-xs"></i>
                            </button>
                        </div>
                    </div>
                </td>
            </tr>`;
    }

    // Kolom ringkasan di TABEL UTAMA (di belakang modal) dihitung ulang dari
    // baris-baris yang SEDANG ada di tab golongan terkait - dipanggil tiap
    // kali tambah/edit/hapus tier sukses, supaya begitu modal ditutup tabel
    // utama sudah akurat tanpa reload halaman sama sekali.
    function refreshHargaSummary(idProduk, idGolongan) {
        const cell = document.getElementById(`harga-summary-${idProduk}-${idGolongan}`);
        const tbody = document.getElementById(`tier-tbody-produk-${idProduk}-gol-${idGolongan}`);
        if (!cell || !tbody) return;

        const rows = Array.from(tbody.querySelectorAll('.tier-row-produk'));
        const satuanRentang = document.querySelector(`.tier-add-form-produk[data-id-produk="${idProduk}"][data-id-golongan="${idGolongan}"]`)?.dataset.satuanRentang || '';

        // Badge jumlah tier di tab juga ikut diperbarui di sini - satu-
        // satunya tempat "jumlah tier golongan ini berubah" terjadi.
        const tabBtn = document.getElementById(`harga-tab-btn-${idProduk}-${idGolongan}`);
        if (tabBtn) {
            const badge = tabBtn.querySelector('span');
            if (badge) {
                badge.textContent = `(${rows.length})`;
                badge.classList.toggle('text-indigo-600', rows.length > 0);
                badge.classList.toggle('text-slate-500', rows.length === 0);
            }
        }

        if (rows.length === 0) {
            cell.innerHTML = '<span class="text-slate-500 italic text-[10px]">Belum Diatur</span>';
            return;
        }

        const hargaList = rows.map(r => cleanRupiah(r.querySelector('[name="harga_jual"]').value));
        const fmt = (n) => 'Rp' + new Intl.NumberFormat('id-ID').format(n);

        if (rows.length === 1) {
            const min = rows[0].querySelector('[name="range_min"]').value;
            const max = rows[0].querySelector('[name="range_max"]').value;
            let html = `<span class="text-slate-700 font-medium">${fmt(hargaList[0])}</span>`;
            if (parseFloat(min) > 0.01 || max) {
                html += `<div class="text-[9px] text-slate-400">${min}-${max || '&infin;'} ${satuanRentang}</div>`;
            }
            cell.innerHTML = html;
        } else {
            const minHarga = Math.min(...hargaList);
            const maxHarga = Math.max(...hargaList);
            cell.innerHTML = `
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-indigo-50 text-indigo-600 border border-indigo-100">${rows.length} TIER</span>
                <div class="text-[9px] text-slate-400 mt-0.5">${fmt(minHarga)} - ${fmt(maxHarga)}</div>`;
        }
    }

    async function submitHargaTierFormAjax(form, method) {
        // Form ini dikirim lewat fetch() (bukan submit native) supaya modal
        // tidak reload - tapi itu juga berarti validasi "step" HTML native
        // browser (mis. step=1 untuk Pcs) TIDAK PERNAH dicek, operator bisa
        // saja mengetik manual "10.5" walau field-nya untuk satuan Pcs.
        // Dibulatkan paksa di sini kalau step field ini "1" (Pcs) supaya
        // rentang Pcs selalu bilangan bulat - satuan luas (step 0.01) tidak
        // disentuh, memang boleh pecahan.
        const minEl = form.querySelector('[name="range_min"]');
        const isPcs = minEl && (minEl.step || '1') === '1';
        if (isPcs) {
            const maxEl = form.querySelector('[name="range_max"]');
            if (minEl.value !== '') minEl.value = Math.round(parseFloat(minEl.value));
            if (maxEl && maxEl.value !== '') maxEl.value = Math.round(parseFloat(maxEl.value));
        }
        const formData = new FormData(form);
        const payload = {};
        formData.forEach((v, k) => { if (k !== '_token') payload[k] = v; });
        try {
            const res = await fetch(form.action, {
                method,
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfTokenHarga },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                Swal.fire('Gagal menyimpan', data.message || 'Gagal menyimpan.', 'error');
                return null;
            }
            return data;
        } catch (e) {
            Swal.fire('Koneksi terputus', 'Terjadi kesalahan koneksi.', 'error');
            return null;
        }
    }

    document.addEventListener('submit', async function (e) {
        if (e.target.matches('.tier-add-form-produk')) {
            e.preventDefault();
            const form = e.target;
            // Pagar klik ganda. Form ini dikirim lewat fetch() JSON jadi tidak
            // pernah membawa submit_id dan tidak terlindungi SubmitSekaliJalan.
            // Pagar sesungguhnya ada di server (CekRentangHarga menolak rentang
            // bertabrakan); yang ini supaya operator tidak mengirim dua kali dan
            // tidak melihat pesan "rentang tumpang tindih" untuk kliknya sendiri.
            const tombol = form.querySelector('button[type="submit"]');
            if (tombol) { tombol.disabled = true; tombol.classList.add('opacity-60'); }
            const data = await submitHargaTierFormAjax(form, 'POST');
            // Tombol hidup lagi baik sukses maupun gagal: form di-reset setelah
            // sukses supaya operator bisa menambah tier lagi.
            if (tombol) { tombol.disabled = false; tombol.classList.remove('opacity-60'); }
            if (!data) return;
            const idProduk = form.dataset.idProduk;
            const idGolongan = form.dataset.idGolongan;
            const tbody = document.getElementById(`tier-tbody-produk-${idProduk}-gol-${idGolongan}`);
            tbody.querySelector('.tier-empty-msg-produk').hidden = true;
            const stepRentang = form.querySelector('[name="range_min"]').step || '1';
            tbody.insertAdjacentHTML('beforeend', buildHargaTierRowHtml(data.row, stepRentang));
            // form.reset() kembali ke nilai default HTML-nya (range_min
            // terisi default, sisanya kosong) - id_golongan (hidden) & tab
            // yang sedang aktif tidak ikut berubah, jadi bisa langsung isi
            // tier berikutnya untuk golongan yang sama tanpa aksi tambahan.
            form.reset();
            refreshHargaSummary(idProduk, idGolongan);
            perbaruiSaranMinTier(idProduk, idGolongan);
            Toast.fire({ icon: 'success', title: data.message });
        } else if (e.target.matches('.tier-edit-form-produk')) {
            e.preventDefault();
            const row = e.target.closest('.tier-row-produk');
            const tbody = row.closest('tbody');
            const data = await submitHargaTierFormAjax(e.target, 'PATCH');
            if (!data) return;
            // Versi baru untuk simpan berikutnya di halaman yang sama (agar tidak dianggap bentrok dengan diri sendiri).
            const inputVersi = e.target.querySelector('[name="_versi"]');
            if (inputVersi && data.versi) inputVersi.value = data.versi;
            refreshHargaSummary(tbody.dataset.idProduk, tbody.dataset.idGolongan);
            perbaruiSaranMinTier(tbody.dataset.idProduk, tbody.dataset.idGolongan);
            Toast.fire({ icon: 'success', title: data.message });
        }
    });

    // Copy SELURUH tier satu golongan ke semua golongan lain untuk produk
    // yang sama - lihat SettingHargaController::copyKeSemuaGolongan().
    // Aksi destruktif (mengganti total tier golongan tujuan), jadi selalu
    // dikonfirmasi dulu dengan peringatan eksplisit.
    function confirmCopyHargaGolongan(idProduk, idGolongan, namaGolongan) {
        Swal.fire({
            title: `Copy Harga "${namaGolongan}" ke Semua Golongan Lain?`,
            html: `Seluruh tier harga golongan <b>${escHtml(namaGolongan)}</b> akan disalin ke SEMUA golongan lain untuk produk ini.<br><small class="text-slate-400">Tier harga yang sudah ada di golongan lain akan DIGANTI TOTAL, bukan digabung.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#2563EB',
            cancelButtonColor: '#F1F5F1',
            cancelButtonText: '<span style="color: #5D6A85">Batal</span>',
            confirmButtonText: 'Ya, Salin ke Semua',
        }).then(async (result) => {
            if (!result.isConfirmed) return;
            try {
                const res = await fetch(`{{ url('/setting-harga') }}/${idProduk}/copy-golongan`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfTokenHarga },
                    body: JSON.stringify({ id_golongan_sumber: idGolongan }),
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    Swal.fire('Gagal menyalin', data.message || 'Gagal menyalin harga.', 'error');
                    return;
                }
                terapkanSalinanHargaKeSemuaTab(idProduk, data.tiers || {});
                Toast.fire({ icon: 'success', title: data.message });
            } catch (e) {
                Swal.fire('Koneksi terputus', 'Terjadi kesalahan koneksi.', 'error');
            }
        });
    }

    // Gambar ulang SEMUA tab golongan produk ini (bukan cuma golongan
    // sumber) berdasarkan data tier terbaru dari server - dipanggil sekali
    // setelah copy sukses, supaya modal langsung mencerminkan hasil salinan
    // tanpa reload halaman.
    function terapkanSalinanHargaKeSemuaTab(idProduk, tiersByGolongan) {
        document.querySelectorAll(`[id^="tier-tbody-produk-${idProduk}-gol-"]`).forEach(tbody => {
            const idGolongan = tbody.dataset.idGolongan;
            const daftarTier = tiersByGolongan[idGolongan] || [];
            const form = document.querySelector(`.tier-add-form-produk[data-id-produk="${idProduk}"][data-id-golongan="${idGolongan}"]`);
            const stepRentang = form ? (form.querySelector('[name="range_min"]').step || '1') : '1';

            const emptyRowHtml = `<tr class="tier-empty-msg-produk" ${daftarTier.length ? 'hidden' : ''}><td colspan="4" class="p-5 text-center text-slate-500 italic">Belum ada harga untuk golongan ini - order akan ditolak di Kasir sampai diisi.</td></tr>`;
            const rowsHtml = daftarTier.map(t => buildHargaTierRowHtml(t, stepRentang)).join('');
            tbody.innerHTML = emptyRowHtml + rowsHtml;

            refreshHargaSummary(idProduk, idGolongan);
            perbaruiSaranMinTier(idProduk, idGolongan);
        });
    }

    function confirmHapusTierHarga(id) {
        Swal.fire({
            title: 'Hapus Tier Harga Ini?',
            text: 'Order dengan qty di rentang ini tidak akan punya harga lagi sampai diatur ulang.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            cancelButtonColor: '#F1F5F1',
            cancelButtonText: '<span style="color: #5D6A85">Batal</span>',
            confirmButtonText: 'Ya, Hapus!'
        }).then(async (result) => {
            if (!result.isConfirmed) return;
            try {
                const res = await fetch(`{{ url('/setting-harga-tier') }}/${id}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfTokenHarga },
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    Swal.fire('Gagal menghapus', data.message || 'Gagal menghapus.', 'error');
                    return;
                }
                const row = document.querySelector(`.tier-row-produk[data-id="${id}"]`);
                const tbody = row.closest('tbody');
                const idProduk = tbody.dataset.idProduk;
                const idGolongan = tbody.dataset.idGolongan;
                row.remove();
                if (tbody.querySelectorAll('.tier-row-produk').length === 0) {
                    tbody.querySelector('.tier-empty-msg-produk').hidden = false;
                }
                refreshHargaSummary(idProduk, idGolongan);
                perbaruiSaranMinTier(idProduk, idGolongan);
                Toast.fire({ icon: 'success', title: data.message });
            } catch (e) {
                Swal.fire('Koneksi terputus', 'Terjadi kesalahan koneksi.', 'error');
            }
        });
    }
</script>
@endpush
@endsection

