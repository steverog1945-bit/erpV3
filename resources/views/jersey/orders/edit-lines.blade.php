@extends('layouts.app')

@section('content')
@include('partials.rupiah-format-script')
@include('partials.image-compress-script')
<div class="jersey-mobile-form-page w-full px-4 sm:px-6 lg:px-8 py-6 max-w-[1400px] mx-auto">

    <x-page-header title="Kelola Barang - {{ $order->order_number }}" subtitle="Ubah, tambah, atau hapus barang pada order ini. Harga, tambahan ukuran, & estimasi bahan dihitung ulang otomatis.">
        <x-slot:actions>
            <x-button href="{{ route('jersey.orders.show', $order->id) }}" variant="outline">
                <i class="fas fa-arrow-left"></i> Kembali ke Detail
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="bg-amber-50 text-amber-700 p-3 rounded-2xl mb-4 text-xs font-medium flex items-start gap-2">
        <i class="fas fa-triangle-exclamation mt-0.5"></i>
        <span>Menyimpan perubahan di sini akan menghitung ulang SEMUA barang pada order ini - id barang lama berubah, jadi tautan SPK yang sudah dicetak sebelumnya perlu dicetak ulang dari halaman Detail Order setelah disimpan.</span>
    </div>

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

    <form action="{{ route('jersey.orders.update-lines', $order->id) }}" method="POST" id="jersey-order-form">
        @csrf @method('PUT')
        @versi($order)

        {{-- Total & tombol Simpan dipindah ke kolom kanan STICKY - selalu
             kelihatan tanpa perlu scroll ke paling bawah, sama seperti
             Order Jersey Baru & Keranjang Pesanan Input Order. --}}
        <div class="flex flex-col lg:flex-row gap-5 items-start">
            <div class="w-full lg:w-[64%] space-y-4">
                <div id="wrapper-lines" class="space-y-4">
                    @foreach($order->lines as $line)
                    <div class="jersey-line-card bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden" data-prefix="lines[{{ $line->id }}]">
                        @include('jersey.orders._line-card-fields', ['idx' => $line->id, 'line' => $line, 'productTypes' => $productTypes, 'categories' => $categories, 'materials' => $materials, 'sizes' => $sizes, 'sleeveTypes' => $sleeveTypes, 'collarTypes' => $collarTypes])
                    </div>
                    @endforeach
                </div>

                <button type="button" onclick="tambahBarisBarang()" class="w-full py-3 rounded-2xl border-2 border-dashed border-slate-300 text-slate-500 font-medium text-xs uppercase tracking-widest hover:border-indigo-400 hover:text-indigo-600 hover:bg-indigo-50/50 transition-all">
                    <i class="fas fa-plus"></i> Tambah Jenis Barang
                </button>
            </div>

            <div class="w-full lg:w-[36%] lg:sticky lg:top-6 space-y-4">
                <x-card>
                    <h3 class="text-xs font-medium text-slate-400 uppercase tracking-widest mb-3">Ringkasan Barang</h3>
                    <div id="ringkasan-daftar-barang" class="divide-y divide-slate-50">
                        <p class="text-xs text-slate-500 italic text-center py-5">Belum ada barang.</p>
                    </div>
                </x-card>

                <div class="flex items-center justify-between gap-4 flex-wrap bg-indigo-600 rounded-2xl px-6 py-4">
                    <span class="text-xs font-medium text-indigo-200 uppercase tracking-widest">Perkiraan Total Barang</span>
                    <span id="preview-total-order" class="text-2xl font-medium text-white">Rp 0</span>
                </div>

                <x-button type="submit" class="w-full !py-4">Simpan Perubahan Barang</x-button>
            </div>
        </div>
    </form>
</div>

{{-- ===================== TEMPLATE BARIS PEMAIN (kosong) ===================== --}}
<template id="template_baris_pemain_jersey">
    @include('jersey.orders._player-row-fields', ['prefix' => '__PREFIX__', 'itemIdx' => '__ITEM__', 'item' => null, 'sizes' => $sizes, 'sleeveTypes' => $sleeveTypes, 'collarTypes' => $collarTypes])
</template>

{{-- ===================== TEMPLATE 1 BARIS BARANG (kosong, untuk "+ Tambah Jenis Barang") ===================== --}}
<template id="template_baris_barang">
    <div class="jersey-line-card bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        @include('jersey.orders._line-card-fields', ['idx' => '__LINE__', 'line' => null, 'productTypes' => $productTypes, 'categories' => $categories, 'materials' => $materials, 'sizes' => $sizes, 'sleeveTypes' => $sleeveTypes, 'collarTypes' => $collarTypes])
    </div>
</template>

@push('scripts')
@include('jersey.orders._size-helper')
{{-- Import CSV Daftar Pemain: parser + validasi master (sekali per halaman). --}}
@include('jersey.orders._csv-pemain-tools')
<script>
    // jerseyLineIndex dimulai dari angka besar (timestamp) supaya barang
    // BARU yang ditambah lewat "+ Tambah Jenis Barang" di halaman edit ini
    // tidak pernah bentrok dengan id barang lama yang sudah dipakai sebagai
    // index array (lines[{id}]) pada kartu-kartu yang sudah dirender server.
    let jerseyLineIndex = Date.now();

    function tambahBarisBarang() {
        const idx = jerseyLineIndex++;
        const html = document.getElementById('template_baris_barang').innerHTML.replaceAll('__LINE__', idx);
        document.getElementById('wrapper-lines').insertAdjacentHTML('beforeend', html);
        const card = document.getElementById('wrapper-lines').lastElementChild;
        card.dataset.prefix = `lines[${idx}]`;
        pasangPasteThumbnail(card);
        perbaruiNomorBarang();
        perbaruiVisibilitasLenganKerah(card);
        jadwalkanPreviewHarga();
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function perbaruiNomorBarang() {
        document.querySelectorAll('.jersey-line-card').forEach((card, i) => {
            card.querySelector('.line-number').textContent = i + 1;
        });
    }

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
            .replaceAll('__PREFIX__', card.dataset.prefix)
            .replaceAll('__ITEM__', itemIdx);
        tbody.insertAdjacentHTML('beforeend', html);
        perbaruiVisibilitasLenganKerah(card);
        jadwalkanPreviewHarga();
    }

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

    // ---- Thumbnail: upload via klik-pilih-file ATAU tempel (Ctrl+V) ----
    // CATATAN PENTING: paste TIDAK dipasang langsung ke elemen dropzone
    // (dulu begitu, dan gagal). Kotak dropzone punya onclick yang memicu
    // .click() pada <input type=file> tersembunyi di dalamnya untuk buka
    // dialog pilih file OS - begitu dialog itu terbuka lalu ditutup/
    // dibatalkan, fokus browser TIDAK kembali ke kotak dropzone (kadang
    // hilang ke body, kadang ke elemen lain), jadi listener 'paste' yang
    // butuh fokus pada dropzone itu sendiri tidak pernah kepicu - persis
    // gejala yang dilaporkan: paste gambar dari WhatsApp tidak berfungsi.
    // Solusi: satu listener 'paste' GLOBAL di document, mengarah ke kartu
    // yang terakhir disentuh mouse (hover/klik) pada dropzone-nya - tidak
    // bergantung pada fokus DOM yang rapuh setelah dialog file native.
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
                prosesUploadThumbnail(kartuAktifUntukPasteThumbnail, item.getAsFile());
                e.preventDefault();
                return;
            }
        }
    });

    async function uploadThumbnailLine(input) {
        if (!input.files || !input.files[0]) return;
        const card = input.closest('.jersey-line-card');
        await prosesUploadThumbnail(card, input.files[0]);
    }

    async function prosesUploadThumbnail(card, file) {
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
        document.querySelectorAll('.jersey-line-card').forEach(card => {
            pasangPasteThumbnail(card);
            perbaruiVisibilitasLenganKerah(card);
        });
        perbaruiNomorBarang();
        jalankanPreviewHarga();
    });

    // ---- Perkiraan harga LIVE - dihitung ulang di server (bukan ditiru di
    // JS) tiap field yang memengaruhi harga berubah, supaya angka yang
    // dilihat operator SELALU sama dengan yang akan benar-benar tersimpan.
    // Di-debounce supaya tidak nembak request tiap ketikan. ----
    let timerPreviewHarga = null;
    function jadwalkanPreviewHarga() {
        clearTimeout(timerPreviewHarga);
        timerPreviewHarga = setTimeout(jalankanPreviewHarga, 400);
    }

    async function jalankanPreviewHarga() {
        const form = document.getElementById('jersey-order-form');
        if (!form) return;
        let data;
        try {
            // Form ini punya hidden _method=PUT (untuk submit sungguhan ke
            // update-lines) - kalau ikut terkirim, request preview yang
            // aslinya POST akan di-spoof jadi PUT oleh Laravel dan malah
            // kena route PUT /jersey/{order} (update order), bukan endpoint
            // preview. Dibuang dulu khusus untuk request preview ini.
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

        const ringkasanEl = document.getElementById('ringkasan-daftar-barang');
        ringkasanEl.innerHTML = '';

        document.querySelectorAll('.jersey-line-card').forEach((card, idx) => {
            const m = (card.dataset.prefix || '').match(/\[(.+)\]/);
            const info = m ? data.lines?.[m[1]] : null;
            const nilaiEl = card.querySelector('.line-price-preview-value');
            const detailEl = card.querySelector('.line-price-preview-detail');
            if (nilaiEl) {
                if (!info) {
                    nilaiEl.textContent = 'Rp 0';
                    if (detailEl) detailEl.textContent = 'Isi qty & pilihan barang untuk lihat perkiraan harga.';
                } else {
                    // Finishing jahit ikut dijumlahkan - sama persis dengan cara
                    // server menghitung order saat disimpan (simpanFinishingBaris).
                    nilaiEl.textContent = 'Rp ' + Math.round(info.subtotal + (info.finishing_biaya || 0)).toLocaleString('id-ID');
                    // Teks detail hanya menyimpan CATATAN (tier belum cocok,
                    // pemain belum pilih ukuran) - angka rinciannya di daftar
                    // line-price-preview-list (marka biru atas).
                    const catatan = [];
                    if (!info.ada_tier) catatan.push('belum ada tier harga yang cocok');
                    if (info.pemain_tanpa_ukuran > 0) catatan.push(`${info.pemain_tanpa_ukuran} pemain belum pilih ukuran (dihitung harga dewasa)`);
                    if (info.finishing_tidak_lengkap) catatan.push('ukuran barang jahit belum lengkap');
                    if (detailEl) detailEl.textContent = catatan.join(', ') || (info.tier_label ? `Tier ${info.tier_label}` : '');
                }
            }

            // Daftar rincian harga - satu baris per komponen harga (sama seperti
            // halaman create). createElement/textContent, bukan innerHTML string.
            const listEl = card.querySelector('.line-price-preview-list');
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
                // Baris rincian finishing jahit - nominal dihitung server supaya
                // sama persis dengan yang tersimpan.
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

            // Baris ringkasan di sidebar - via createElement/textContent (bukan
            // innerHTML string) supaya nama barang yang diketik operator tidak
            // pernah dianggap markup.
            const namaBarang = card.querySelector('input[name$="[nama_barang]"]')?.value.trim() || `Barang #${idx + 1}`;
            const thumbSrc = card.querySelector('.thumbnail-preview:not(.hidden)')?.src || '';
            const qty = info ? info.qty : 0;
            const subtotal = info ? Math.round(info.subtotal + (info.finishing_biaya || 0)) : 0;

            const baris = document.createElement('div');
            baris.className = 'flex items-center gap-2.5 py-2';

            const thumbWrap = document.createElement('div');
            thumbWrap.className = 'w-9 h-9 rounded-lg bg-slate-100 overflow-hidden shrink-0 flex items-center justify-center';
            if (thumbSrc) {
                const img = document.createElement('img');
                img.src = thumbSrc;
                img.className = 'w-full h-full object-cover';
                thumbWrap.appendChild(img);
            } else {
                thumbWrap.innerHTML = '<i class="fas fa-tshirt text-slate-500 text-xs"></i>';
            }
            baris.appendChild(thumbWrap);

            const infoWrap = document.createElement('div');
            infoWrap.className = 'flex-1 min-w-0';
            const namaEl = document.createElement('p');
            namaEl.className = 'text-xs font-medium text-slate-700 truncate';
            namaEl.textContent = namaBarang;
            const qtyEl = document.createElement('p');
            qtyEl.className = 'text-[10px] text-slate-400';
            qtyEl.textContent = `${qty} pcs`;
            infoWrap.append(namaEl, qtyEl);
            baris.appendChild(infoWrap);

            const subtotalEl = document.createElement('span');
            subtotalEl.className = 'text-xs font-medium text-slate-600 shrink-0';
            subtotalEl.textContent = 'Rp ' + subtotal.toLocaleString('id-ID');
            baris.appendChild(subtotalEl);

            ringkasanEl.appendChild(baris);
        });

        if (!ringkasanEl.children.length) {
            ringkasanEl.innerHTML = '<p class="text-xs text-slate-500 italic text-center py-5">Belum ada barang.</p>';
        }

        const totalEl = document.getElementById('preview-total-order');
        if (totalEl) totalEl.textContent = 'Rp ' + Math.round(data.total_order || 0).toLocaleString('id-ID');
    }

    document.getElementById('jersey-order-form').addEventListener('input', (e) => {
        if (e.target.closest('.jersey-line-card')) jadwalkanPreviewHarga();
    });
    document.getElementById('jersey-order-form').addEventListener('change', (e) => {
        if (e.target.closest('.jersey-line-card')) jadwalkanPreviewHarga();
    });

    // Penanda submit sedang berjalan. Handler form di bawah mengambil alih
    // submit, jadi penaga global di layouts/app.blade.php tidak berlaku.
    let sedangMenyimpan = false;

    document.getElementById('jersey-order-form').addEventListener('submit', async function (e) {
        e.preventDefault();

        if (sedangMenyimpan) return;

        document.querySelectorAll('.baris-pemain').forEach(row => {
            if (row.querySelector('[name*="player_name"]').value.trim() === '') row.remove();
        });

        const cards = document.querySelectorAll('.jersey-line-card');
        if (cards.length === 0) {
            Swal.fire('Belum Ada Barang', 'Order harus punya minimal 1 jenis barang.', 'warning');
            return;
        }

        // Audit kelengkapan (sama seperti create.blade.php): dropdown wajib
        // + ukuran pemain + qty minimal - blokir simpan dengan daftar
        // kekurangan & tandai field kosong.
        const kurang = [];
        document.querySelectorAll('.jersey-line-card').forEach(card => {
            card.querySelectorAll('input, select').forEach(el => el.classList.remove('ring-2', 'ring-rose-400', 'bg-rose-50/40'));
            const nomor = card.querySelector('.line-number').textContent;
            const namaBarang = card.querySelector('[name*="[nama_barang]"]').value.trim() || `Barang #${nomor}`;

            const wajibPilih = [
                ['[name*="[jersey_product_type_id]"]', 'Tipe Produk'],
                ['[name*="[jersey_category_id]"]', 'Kategori'],
                ['[name*="[jersey_material_id]"]', 'Bahan'],
            ];
            for (const [selector, label] of wajibPilih) {
                const el = card.querySelector(selector);
                if (el && !el.value) {
                    kurang.push(`${namaBarang}: ${label} belum dipilih`);
                    el.classList.add('ring-2', 'ring-rose-400', 'bg-rose-50/40');
                }
            }

            const tipe = card.querySelector('input.line-type-option:checked')?.value || 'quantity_only';
            const adaQtyUkuran = [...card.querySelectorAll('.input-qty-ukuran')].some(i => parseInt(i.value) > 0);
            const pemain = [...card.querySelectorAll('.baris-pemain')].filter(r => r.querySelector('[name*="player_name"]').value.trim() !== '');
            const pemainTanpaUkuran = pemain.filter(r => !r.querySelector('[name*="[size]"]').value);
            pemainTanpaUkuran.forEach(r => r.querySelector('[name*="[size]"]').classList.add('ring-2', 'ring-rose-400', 'bg-rose-50/40'));

            if (pemain.length > 0 && pemainTanpaUkuran.length > 0) {
                kurang.push(`${namaBarang}: ${pemainTanpaUkuran.length} pemain belum memilih Ukuran`);
            }
            if (tipe !== 'player_list' && !adaQtyUkuran && pemain.length === 0) {
                kurang.push(`${namaBarang}: belum ada qty ukuran maupun daftar pemain`);
            }
        });

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

        for (const card of cards) {
            if (card.querySelector('.thumbnail-path').value.trim() === '') {
                const nomor = card.querySelector('.line-number').textContent;
                Swal.fire('Thumbnail Belum Diisi', `Barang #${nomor} belum ada thumbnail desain - wajib diisi sebagai acuan warna & bentuk produksi.`, 'warning');
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
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

        const konfirmasiSimpan = await Swal.fire({
            title: 'Simpan Perubahan Barang?',
            html: 'Semua barang pada order ini akan dihitung ulang (harga, tambahan ukuran, estimasi bahan). Tautan SPK lama untuk barang yang berubah perlu dicetak ulang.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563EB',
            confirmButtonText: 'Ya, Simpan',
            cancelButtonText: 'Batal',
            customClass: { confirmButton: 'rounded-lg px-6 py-2.5', cancelButton: 'rounded-lg px-6 py-2.5 text-slate-600' },
        });
        if (!konfirmasiSimpan.isConfirmed) return;

        this.querySelectorAll('input.format-ribuan').forEach(input => {
            input.value = cleanRupiah(input.value);
        });

        // ---- Penaga kirim ganda + loading state ----
        // Handler ini memanggil e.preventDefault(), jadi penaga global di
        // layouts/app.blade.php (yang keluar pada e.defaultPrevented) TIDAK
        // berlaku di sini. Tanpa penaga di bawah, klik ganda pada "Simpan
        // Barang" menghasilkan dua POST ke jersey.orders.lines.update -
        // order lama dihapus lalu dibuat ulang dua kali.
        //
        // Pagar server tetap ada (SubmitSekaliJalan + submit_id + lockForUpdate),
        // tapi penaga di sini mencegah pesan "sudah diproses" yang membingungkan
        // dan menahan klik kedua sebelum selesai.
        if (sedangMenyimpan) return;
        sedangMenyimpan = true;
        const btnSimpan = this.querySelector('button[type="submit"]');
        if (btnSimpan) {
            btnSimpan.disabled = true;
            btnSimpan.classList.add('opacity-60', 'cursor-not-allowed');
            btnSimpan.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Menyimpan...';
        }

        // Pagar pengaman: kalau navigasi somehow tidak terjadi, tombol jangan
        // terkunci selamanya.
        setTimeout(() => {
            sedangMenyimpan = false;
            if (!btnSimpan) return;
            btnSimpan.disabled = false;
            btnSimpan.classList.remove('opacity-60', 'cursor-not-allowed');
            btnSimpan.innerHTML = 'Simpan Barang';
        }, 20000);

        this.submit();
    });
</script>
@endpush
@endsection
