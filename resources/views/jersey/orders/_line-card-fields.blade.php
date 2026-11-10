{{--
    Isi satu kartu "Barang" pada form order jersey - dipakai di DUA tempat:
    1. create.blade.php: dirender dengan $line=null (kosong, __LINE__ jadi
       placeholder) di dalam <template>, diisi manual lewat JS saat "Tambah
       Jenis Barang" diklik.
    2. edit-lines.blade.php: dirender server-side per barang yang SUDAH ADA
       ($line diisi, $idx = id barang) supaya form langsung terisi data
       lama - operator tinggal ubah yang perlu.
    Satu partial ini menjaga markup & nama field kedua form itu selalu
    identik, supaya submit-nya bisa diproses lewat logika yang sama persis
    (JerseyOrderController::simpanBarisOrder()).
--}}
@php
    $otIsi = $line->order_type ?? 'quantity_only';
    $sizeQtyMap = $line ? $line->sizeQuantities->pluck('quantity', 'jersey_size_id') : collect();
@endphp
<div class="px-5 py-3 bg-indigo-50/50 border-b border-indigo-100 flex items-center justify-between">
    <span class="text-xs font-medium text-indigo-600 uppercase tracking-widest">Barang #<span class="line-number">1</span></span>
    <button type="button" onclick="this.closest('.jersey-line-card').remove(); if (typeof jadwalkanPreviewHarga === 'function') jadwalkanPreviewHarga(); if (typeof jadwalkanPreviewHargaSusulan === 'function') jadwalkanPreviewHargaSusulan();" class="w-8 h-8 rounded-lg text-rose-400 hover:bg-rose-50" aria-label="Hapus" title="Hapus"><i class="far fa-trash-alt text-xs"></i></button>
</div>
<div class="p-5 space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="md:col-span-2">
            <label for="jersey-{{ $idx }}-nama-barang" class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1.5">Nama Barang <span class="text-rose-500">*</span></label>
            <input id="jersey-{{ $idx }}-nama-barang" type="text" name="lines[{{ $idx }}][nama_barang]" value="{{ $line->nama_barang ?? '' }}" required placeholder="Deskripsikan barang pesanan" class="w-full bg-white border border-slate-200 rounded-md py-2 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
        </div>
        <div>
            <label for="jersey-{{ $idx }}-tipe-produk" class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1.5">Tipe Produk <span class="text-rose-500">*</span></label>
            <select id="jersey-{{ $idx }}-tipe-produk" name="lines[{{ $idx }}][jersey_product_type_id]" required onchange="perbaruiVisibilitasLenganKerah(this)" class="w-full bg-white border border-slate-200 rounded-md py-2 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
                <option value="">-- Pilih --</option>
                @foreach($productTypes as $pt)<option value="{{ $pt->id }}" data-has-sleeve-collar="{{ $pt->has_sleeve_collar ? 1 : 0 }}" @selected(($line->jersey_product_type_id ?? null) == $pt->id)>{{ $pt->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="jersey-{{ $idx }}-kategori" class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1.5">Kategori <span class="text-rose-500">*</span></label>
            <select id="jersey-{{ $idx }}-kategori" name="lines[{{ $idx }}][jersey_category_id]" required class="w-full bg-white border border-slate-200 rounded-md py-2 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
                <option value="">-- Pilih --</option>
                @foreach($categories as $cat)<option value="{{ $cat->id }}" @selected(($line->jersey_category_id ?? null) == $cat->id)>{{ $cat->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="jersey-{{ $idx }}-bahan" class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1.5">Bahan <span class="text-rose-500">*</span></label>
            <select id="jersey-{{ $idx }}-bahan" name="lines[{{ $idx }}][jersey_material_id]" required class="w-full bg-white border border-slate-200 rounded-md py-2 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
                <option value="">-- Pilih Bahan --</option>
                @foreach($materials as $m)<option value="{{ $m->id }}" @selected(($line->jersey_material_id ?? null) == $m->id)>{{ $m->name }}</option>@endforeach
            </select>
            @if($materials->isEmpty())
                <p class="text-[10px] text-rose-500 mt-1">Belum ada data Bahan - <a href="{{ route('jersey.master.index') }}" target="_blank" class="underline">tambah dulu di Master Data</a>.</p>
            @endif
        </div>
        <div class="wrapper-lengan-kerah-default md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label for="jersey-{{ $idx }}-lengan" class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1.5 flex items-center gap-1.5">Lengan (default) <span class="text-rose-500">*</span> <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-slate-200 text-slate-500 text-[9px] font-medium cursor-help shrink-0" title="Lengan &amp; Kerah hanya muncul untuk tipe produk yang memilikinya (Jersey). Untuk Celana/tipe tanpa lengan-kerah, field ini disembunyikan otomatis dan tidak dikirim ke server.">?</span></label>
                <select id="jersey-{{ $idx }}-lengan" name="lines[{{ $idx }}][jersey_sleeve_type_id]" required class="w-full bg-white border border-slate-200 rounded-md py-2 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
                    <option value="">-- Pilih --</option>
                    @foreach($sleeveTypes as $st)<option value="{{ $st->id }}" @selected(($line->jersey_sleeve_type_id ?? null) == $st->id)>{{ $st->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="jersey-{{ $idx }}-kerah" class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1.5">Kerah (default) <span class="text-rose-500">*</span></label>
                <select id="jersey-{{ $idx }}-kerah" name="lines[{{ $idx }}][jersey_collar_type_id]" required class="w-full bg-white border border-slate-200 rounded-md py-2 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
                    <option value="">-- Pilih --</option>
                    @foreach($collarTypes as $ct)<option value="{{ $ct->id }}" @selected(($line->jersey_collar_type_id ?? null) == $ct->id)>{{ $ct->name }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="md:col-span-2">
            <label for="jersey-{{ $idx }}-keterangan" class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1.5">Keterangan Desain</label>
            <textarea id="jersey-{{ $idx }}-keterangan" name="lines[{{ $idx }}][keterangan]" rows="2" placeholder="mis. Lengan kanan bendera, dada kiri logo klub..." class="w-full bg-white border border-slate-200 rounded-md py-2 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">{{ $line->keterangan ?? '' }}</textarea>
        </div>
        <div class="md:col-span-2">
            <label for="jersey-{{ $idx }}-thumbnail" class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1.5">Thumbnail Desain <span class="text-rose-500">*</span></label>
            <div class="flex items-center gap-3">
                <div class="thumbnail-dropzone w-14 h-14 rounded-lg bg-slate-50 border-2 border-dashed border-slate-300 flex items-center justify-center overflow-hidden shrink-0 cursor-pointer transition-all hover:border-indigo-400 hover:bg-indigo-50/30" tabindex="0" onclick="this.querySelector('.thumbnail-file-input').click()" title="Klik, tempel (Ctrl+V), atau pilih file">
                    @php
                        $punyaBerkas = ($line->thumbnail ?? null) !== null;
                        $thumbTampil = $punyaBerkas && \App\Support\BerkasDesain::bisaDitampilkan($line->thumbnail);
                        $extThumb = $punyaBerkas ? strtoupper(\App\Support\BerkasDesain::ekstensi($line->thumbnail)) : '';
                    @endphp
                    <img loading="lazy" decoding="async" class="w-full h-full object-cover thumbnail-preview {{ $thumbTampil ? '' : 'hidden' }}" src="{{ $thumbTampil ? url('storage/' . $line->thumbnail) : '' }}" alt="">
                    <img loading="lazy" decoding="async" class="w-full h-full object-cover thumbnail-placeholder {{ ($punyaBerkas && !$thumbTampil) ? '' : 'hidden' }}" src="{{ asset('storage/default-thumbnail.png') }}" alt="Berkas desain" style="opacity: 0.5;">
                    <input type="file" accept="image/png,image/jpeg,image/webp,image/gif,image/bmp,image/svg+xml,image/tiff,application/pdf,.pdf,.cdr,.ai,.eps,.psd,.tif,.tiff,.heic" onchange="uploadThumbnailLine(this)" class="hidden thumbnail-file-input">
                </div>
                <div class="flex-1 min-w-0">
                    <input id="jersey-{{ $idx }}-thumbnail" type="hidden" name="lines[{{ $idx }}][thumbnail]" value="{{ $line->thumbnail ?? '' }}" class="thumbnail-path" required>
                    <p class="text-[11px] text-slate-400 thumbnail-status leading-snug">{{ $punyaBerkas ? ($thumbTampil ? 'Thumbnail siap. Klik kotak untuk mengganti.' : 'Berkas desain siap (' . $extThumb . '). File ada di server, dibuka tim produksi.') : 'Klik atau tempel (Ctrl+V) gambar desain di sini.' }}</p>
                </div>
            </div>
        </div>
    </div>{{-- /grid utama kartu barang --}}
    <p class="text-[10px] text-slate-400 leading-snug flex items-center gap-1.5"><span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-slate-200 text-slate-500 text-[9px] font-medium cursor-help shrink-0" title="Lengan/Kerah default dipakai untuk mode Qty per Ukuran & Campuran - Daftar Pemain pakai pilihan masing-masing pemain sendiri.">?</span></p>

    {{--
        pb-5 saja, TANPA px-5. Kartu ini tidak punya padding sendiri
        (padding kartu = 0), jadi pemilih mode berdiri tepat di tepi
        kartu. Konten di bawahnya (wrapper-qty-ukuran /
        wrapper-daftar-pemain) mendapat px-5 sendiri - keduanya mulai
        dari titik yang sama, itulah yang membuatnya "lurus".

        Versi sebelumnya memberi px-5 di sini, yang mendorong pemilih
        mode 20px lebih dalam daripada konten - kesalahan berlawanan
        arah, tapi sama salah.
    --}}
    <div class="pb-5">
        <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1.5">Mode Order</label>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            @foreach(['quantity_only' => 'Qty per Ukuran', 'player_list' => 'Daftar Pemain', 'mixed' => 'Campuran'] as $val => $label)
            <label class="line-type-option relative flex items-center justify-center py-2 px-3 rounded-xl border-2 font-medium text-[11px] cursor-pointer {{ $otIsi === $val ? 'border-indigo-500 bg-indigo-50 text-indigo-800' : 'border-slate-200 text-slate-600' }}">
                <input type="radio" name="lines[{{ $idx }}][order_type]" value="{{ $val }}" @checked($otIsi === $val) onchange="toggleLineType(this)" class="absolute top-1.5 right-1.5">
                {{ $label }}
            </label>
            @endforeach
        </div>
    </div>

    <div class="wrapper-qty-ukuran {{ $otIsi === 'player_list' ? 'hidden' : '' }}">
        <div class="flex items-center justify-between mb-2">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Qty per Ukuran</p>
            <span class="px-3 py-1 rounded-full bg-indigo-600 text-white text-[11px] font-medium">Total: <span class="qty-total-line">0</span> pcs</span>
        </div>
        @foreach($sizes->groupBy(fn ($sz) => ($sz->kelompok ?? 'dewasa') === 'anak' ? 'anak' : 'dewasa') as $kelompok => $daftarSize)
        <div class="mb-3 rounded-2xl border {{ $kelompok === 'anak' ? 'border-amber-100 bg-amber-50/40' : 'border-slate-100 bg-slate-50/50' }} p-3 grup-ukuran" data-kelompok="{{ $kelompok }}">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-medium uppercase tracking-widest {{ $kelompok === 'anak' ? 'text-amber-600' : 'text-slate-500' }}"><i class="fas {{ $kelompok === 'anak' ? 'fa-child' : 'fa-user' }} mr-1"></i>{{ $kelompok === 'anak' ? 'Ukuran Anak' : 'Ukuran Dewasa' }}</span>
                <span class="text-[10px] font-medium text-slate-400"><span class="qty-total-grup">0</span> pcs</span>
            </div>
            <div class="grid grid-cols-3 sm:grid-cols-4 {{ $kelompok === 'anak' ? 'md:grid-cols-6' : 'md:grid-cols-6 lg:grid-cols-8' }} gap-2">
                @foreach($daftarSize as $s)
                <div>
                    @php
                        $bagianNama = explode(' (', rtrim($s->name, ')'), 2);
                        $namaUkuran = $bagianNama[0];
                        $usiaUkuran = $bagianNama[1] ?? null;
                        $persenUkuran = (float) ($s->price_percent ?? 0);
                    @endphp
                    {{--
                        Persentase harga dihapus dari sini. Angka itu tidak
                        dibutuhkan saat memilih ukuran, dan karena
                        panjangnya berbeda-beda (-3% vs -10%), baris
                        ukurannya tidak pernah lurus - yang satu turun
                        lebih dalam daripada yang lain. Kalau nanti
                        ingin ditampilkan lagi, taruh di tempat yang
                        tidak mengubah tinggi baris.
                    --}}
                    {{--
                        Nilai size_quantities SELALU diisi dari database,
                        termasuk saat mode-nya Daftar Pemain - sengaja, supaya
                        berpindah ke mode Campuran tidak menghapus data yang
                        sudah ada.

                        Tapi di mode Daftar Pemain inputnya DISABLE, bukan
                        sekadar disembunyikan. Wrapper `hidden` hanya
                        menyembunyikan tampilan; input di dalamnya tetap
                        terkirim ke server. Itulah sumber bug qty 35 -> 70:
                        35 pemain + sisa size_quantities 35 dijumlahkan.
                        Disable = tidak terkirim sama sekali, jadi tidak ada
                        data basi yang bisa ikut terhitung.
                    --}}
                    <label class="block text-[10px] font-medium text-slate-400 uppercase text-center mb-1 leading-tight break-words min-h-[24px]">
                        {{ $namaUkuran }}
                        @if($usiaUkuran)<span class="block text-[9px] text-amber-600 normal-case font-medium">{{ $usiaUkuran }}</span>@endif
                    </label>
                    <input type="number" inputmode="numeric" name="lines[{{ $idx }}][size_quantities][{{ $s->id }}]" min="0" value="{{ $sizeQtyMap[$s->id] ?? 0 }}" onfocus="this.select()" {{ $otIsi === 'player_list' ? 'disabled' : '' }} class="input-qty-ukuran w-full bg-white border border-slate-100 rounded-lg p-2 text-xs font-medium text-center focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-300 disabled:opacity-50 disabled:cursor-not-allowed">
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
        <p class="text-[10px] text-slate-400 flex items-center gap-1.5"><span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-slate-200 text-slate-500 text-[9px] font-medium cursor-help shrink-0" title="Tekan Enter untuk pindah ke ukuran berikutnya. Ukuran anak otomatis memakai selisih harga yang diatur di Master Data.">?</span></p>
    </div>

    <div class="wrapper-daftar-pemain {{ $otIsi === 'quantity_only' ? 'hidden' : '' }}">
        <div class="flex items-center justify-between mb-2">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Daftar Pemain</p>
            <div class="flex items-center gap-3">
                @include('jersey.orders._csv-pemain-card-buttons')
                <button type="button" onclick="tambahBarisPemain(this)" class="text-[10px] font-medium text-indigo-600 uppercase underline">+ Tambah Pemain</button>
            </div>
        </div>
        @include('jersey.orders._csv-pemain-hasil-panel')
        <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead><tr class="text-[10px] text-slate-400 uppercase">
                <th class="p-1 text-left w-32 md:w-40">Nama</th><th class="p-1 text-left w-12">No.</th><th class="p-1 text-left w-40">Ukuran <span class="text-rose-500">*</span></th>
                <th class="p-1 text-left w-14">Gender</th><th class="p-1 text-left w-24 col-lengan-kerah">Lengan <span class="text-rose-500">*</span></th><th class="p-1 text-left w-28 col-lengan-kerah">Kerah <span class="text-rose-500">*</span></th><th class="p-1 w-8"></th>
            </tr></thead>
            <tbody class="tbody-pemain divide-y divide-slate-50">
                @if($line)
                    @foreach($line->items as $itemIdx => $item)
                        @include('jersey.orders._player-row-fields', ['prefix' => "lines[{$idx}]", 'itemIdx' => $itemIdx, 'item' => $item, 'sizes' => $sizes, 'sleeveTypes' => $sleeveTypes, 'collarTypes' => $collarTypes])
                    @endforeach
                @endif
            </tbody>
        </table>
        </div>
    </div>

    {{-- Panel input "Finishing Jahit" DIHAPUS dari seluruh form input order
         jersey (create / edit-lines / modal susulan) atas permintaan owner.
         Data finishing yang sudah tersimpan lama tetap tampil di halaman
         detail/nota/SPK; controller masih menerima field-nya (nullable) agar
         komunikasi lama tidak rusak, hanya antarmuka input yang ditutup. --}}

    <div class="line-price-preview rounded-xl bg-emerald-50 border border-emerald-100 p-3 space-y-2">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div>
                <span class="text-[10px] font-medium text-emerald-700 uppercase tracking-widest">Perkiraan Harga Barang Ini</span>
                <p class="text-[10px] text-emerald-600/80 mt-0.5 line-price-preview-detail">Isi qty &amp; pilihan barang untuk lihat perkiraan harga.</p>
            </div>
            <span class="text-xl font-medium text-emerald-700 line-price-preview-value">Rp 0</span>
        </div>
        <ul class="line-price-preview-list hidden text-[11px] text-emerald-800 divide-y divide-emerald-100/80"></ul>
    </div>

    <p class="text-[10px] text-slate-400 flex items-center gap-1.5">
        <i class="fas fa-info-circle text-slate-500"></i>
        Kebutuhan bahan dihitung otomatis dari qty x estimasi Master Data.
    </p>
</div>

<script>
(function () {
    if (window.__panelFinishingInit) return;
    window.__panelFinishingInit = true;

    // Satu helper global untuk SEMUA kartu barang (create / edit-lines /
    // modal susulan): perbarui visibilitas panel Finishing + jadwalkan
    // preview harga yang tersedia di halaman itu.
    window.perbaruiPanelFinishing = function (el) {
        const panel = el && el.closest ? el.closest('[data-panel-finishing]') : null;
        if (!panel) return;
        const sel = panel.querySelector('select[name$="[id_finishing]"]');
        const opsi = sel && sel.value ? sel.selectedOptions[0] : null;
        const metode = opsi ? (opsi.dataset.metode || '') : '';
        const statusSel = panel.querySelector('select[name$="[status_finishing]"]');
        const custom = !!statusSel && statusSel.value === 'Custom';

        const dimensi = panel.querySelector('.wrapper-finishing-dimensi');
        if (dimensi) dimensi.classList.toggle('hidden', !sel.value);
        const kepingWrap = panel.querySelector('.wrapper-finishing-keping');
        if (kepingWrap) kepingWrap.classList.toggle('hidden', metode !== 'KEPING_ROLL');
        const customWrap = panel.querySelector('.wrapper-finishing-custom');
        if (customWrap) customWrap.classList.toggle('hidden', !custom);
        const hint = panel.querySelector('.wrapper-finishing-hint');
        if (hint) {
            hint.textContent = !sel.value
                ? 'Opsional - pilih finishing kalau pesanan perlu jahit/potong tambahan.'
                : (metode === 'KEPING_ROLL'
                    ? 'Meter jahit = jumlah keping � keliling ukuran barang (2�(L+P)/100) � qty barang - ukuran media/nota tidak ikut jahit.'
                    : (metode === 'luas'
                        ? 'Qty = (L�P)/10.000 m� � qty barang.'
                        : (metode === 'quantity' ? 'Qty finishing = qty barang.' : 'Biaya dihitung server dari tarif master � qty.')));
        }
    };

    window.jadwalkanPreviewHargaSemua = function (el) {
        window.perbaruiPanelFinishing(el);
        if (typeof window.jadwalkanPreviewHarga === 'function') window.jadwalkanPreviewHarga();
        if (typeof window.jadwalkanPreviewHargaSusulan === 'function') window.jadwalkanPreviewHargaSusulan();
    };

    // Selain onchange inline di tiap field, pasang juga listener global
    // supaya panel finishing ikut menyesuaikan pilihan (mis. kartu susulan).
    document.addEventListener('change', function (e) {
        if (e.target && e.target.name && e.target.name.indexOf('[finishing]') !== -1) {
            window.perbaruiPanelFinishing(e.target);
        }
    });
})();
</script>
