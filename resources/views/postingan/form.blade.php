@extends('layouts.app')

@section('content')
@php
    $edit = $post->exists;
    $terbitAtInput = old('terbit_at', optional($post->terbit_at)->format('Y-m-d\TH:i'));
@endphp
{{-- Font gaya majalah (Nunito, Lora, Playfair) dilayani sendiri.
     Hanya halaman Postingan yang memakainya, jadi CSS-nya dimuat di
     sini dan bukan di layout - lihat catatan lengkap di
     public/vendor/fonts/majalah.css. --}}
<link href="{{ asset('vendor/fonts/majalah.css') }}" rel="stylesheet">
<link href="{{ asset('vendor/quill/quill.snow.css') }}" rel="stylesheet">
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1300px] mx-auto">

    <x-page-header :title="$edit ? 'Edit Postingan' : 'Tulis Postingan'">
        <x-slot:subtitle>Info &amp; Panduan <span class="mx-1 text-slate-500">/</span> {{ $edit ? 'Ubah isi, gambar, dan pengaturan terbit' : 'Buat pengumuman, SOP, tips, atau hasil karya' }}</x-slot:subtitle>
        <x-slot:actions>
            <x-button href="{{ $edit ? route('postingan.show', $post->slug) : route('postingan.index') }}" variant="secondary"><i class="fas fa-arrow-left"></i> Kembali</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($errors->any())
        <div class="bg-rose-50 text-rose-600 p-4 rounded-2xl mb-6 font-medium">
            <ul class="list-disc list-inside space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" id="form-postingan" enctype="multipart/form-data" action="{{ $edit ? route('postingan.update', $post->id) : route('postingan.store') }}">
        @csrf
        @if($edit) @method('PUT') @endif
        <input type="hidden" name="isi" id="isi-input">

        <div class="flex flex-col lg:flex-row gap-6 items-start">
            {{-- ============ KIRI: JUDUL & EDITOR ============ --}}
            <div class="w-full lg:w-[68%] space-y-6">
                <x-card>
                    <x-form.group label="Judul" required dalam>
                        <x-form.input type="text" name="judul" value="{{ old('judul', $post->judul) }}" required maxlength="200" placeholder="Judul yang jelas dan singkat" class="!text-lg !font-medium" />
                    </x-form.group>
                    <x-form.group label="Ringkasan (tampil di daftar; kosongkan untuk otomatis)" class="mt-4" dalam>
                        <textarea name="ringkasan" rows="2" maxlength="300" placeholder="Satu-dua kalimat pembuka..." class="w-full bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 shadow-inner">{{ old('ringkasan', $post->ringkasan) }}</textarea>
                    </x-form.group>
                </x-card>

                {{-- Tanpa overflow-hidden agar toolbar bisa menempel (sticky) saat halaman digulir --}}
                <x-card :padding="false" class="editor-kartu">
                    <div class="px-6 pt-5 pb-3 flex items-center justify-between">
                        <label class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Isi Postingan <span class="text-rose-500">*</span></label>
                        <span class="text-[11px] text-slate-400" id="hitung-kata">0 kata</span>
                    </div>
                    <div id="toolbar-postingan" class="toolbar-postingan">
                        <span class="ql-formats">
                            <select class="ql-header" title="Gaya paragraf"><option value="2">Judul Bagian</option><option value="3">Sub Judul</option><option value="4">Judul Kecil</option><option selected>Paragraf</option></select>
                            <select class="ql-font" title="Jenis huruf"><option value="" selected>Nunito (lembut)</option><option value="inter">Inter</option><option value="lora">Lora (serif)</option><option value="playfair">Playfair (judul)</option><option value="mono">Monospace</option></select>
                            <select class="ql-size" title="Ukuran huruf"><option value="small">Kecil</option><option value="" selected>Normal</option><option value="large">Besar</option><option value="huge">Sangat besar</option></select>
                            <select class="ql-spasi" title="Jarak baris &amp; paragraf"><option value="rapat">Rapat</option><option value="" selected>Normal</option><option value="longgar">Longgar</option><option value="lega">Lega</option></select>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-bold" title="Tebal (Ctrl+B)"></button><button class="ql-italic" title="Miring (Ctrl+I)"></button><button class="ql-underline" title="Garis bawah (Ctrl+U)"></button><button class="ql-strike" title="Coret"></button><button class="ql-script" value="sub" title="Subskrip"></button><button class="ql-script" value="super" title="Superskrip"></button>
                            <select class="ql-color" title="Warna huruf"><option selected></option><option value="red"></option><option value="orange"></option><option value="green"></option><option value="blue"></option><option value="purple"></option><option value="gray"></option></select><select class="ql-background" title="Sorot teks"><option selected></option><option value="yellow"></option><option value="lightgreen"></option><option value="lightblue"></option><option value="pink"></option></select>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-align" value="" title="Rata kiri"></button><button class="ql-align" value="center" title="Rata tengah"></button><button class="ql-align" value="right" title="Rata kanan"></button><button class="ql-align" value="justify" title="Rata kiri-kanan"></button>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-list" value="ordered" title="Daftar bernomor"></button><button class="ql-list" value="bullet" title="Daftar poin"></button>
                            <button class="ql-indent" value="-1" title="Kurangi menjorok"></button><button class="ql-indent" value="+1" title="Tambah menjorok"></button>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-blockquote" title="Kutipan / catatan penting"></button><button class="ql-link" title="Tautan"></button><button class="ql-image" title="Sisipkan gambar"></button>
                        </span>
                        <span class="ql-formats">
                            <button type="button" id="btn-undo" title="Urungkan (Ctrl+Z)"><i class="fas fa-rotate-left text-[11px]"></i></button><button type="button" id="btn-redo" title="Ulangi (Ctrl+Y)"><i class="fas fa-rotate-right text-[11px]"></i></button>
                            <button class="ql-clean" title="Hapus format teks terpilih"></button>
                        </span>
                        <span class="ql-formats">
                            <button type="button" id="btn-rapikan" class="btn-rapikan" title="Rapikan otomatis: hapus spasi &amp; baris kosong berlebih, ubah '-' / '1.' menjadi daftar, deteksi judul, sambung baris terpotong"><i class="fas fa-align-left"></i> Rapikan</button>
                        </span>
                    </div>
                    <div id="editor-postingan">{!! \App\Support\HtmlBersih::bersihkan(old('isi', $post->isi_html)) !!}</div>
                    <div class="px-6 py-3 text-[11px] text-slate-400 border-t border-slate-100">Isi yang ditempel dari Word/web/PDF otomatis dibersihkan ke format standar (font, warna, dan ukuran asal dibuang). Tempel tanpa format sama sekali: <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>V</kbd>.</div>
                </x-card>
            </div>

            {{-- ============ KANAN: PENGATURAN ============ --}}
            <div class="w-full lg:w-[32%] space-y-6 lg:sticky lg:top-24">
                <x-card>
                    <h3 class="text-sm font-medium text-slate-800 mb-4">Penerbitan</h3>
                    <x-form.group label="Status" required dalam>
                        <x-form.select name="status" id="status-post">
                            <option value="draft" {{ old('status', $post->status) === 'draft' ? 'selected' : '' }}>Draft (belum terlihat orang lain)</option>
                            <option value="terbit" {{ old('status', $post->status) === 'terbit' ? 'selected' : '' }}>Terbit</option>
                        </x-form.select>
                    </x-form.group>
                    <x-form.group label="Jadwal terbit (opsional)" class="mt-4" help="Isi waktu di masa depan agar terbit otomatis pada saat itu (status harus Terbit)." dalam>
                        <x-form.input type="datetime-local" name="terbit_at" value="{{ $terbitAtInput }}" />
                    </x-form.group>
                    <label class="mt-4 flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3 cursor-pointer" title="Postingan disematkan selalu tampil paling atas dan di dashboard">
                        <input type="checkbox" name="disematkan" value="1" class="accent-indigo-600" {{ old('disematkan', $post->disematkan) ? 'checked' : '' }}>
                        <span><span class="block text-xs font-medium text-slate-600 uppercase tracking-wide"><i class="fas fa-thumbtack text-amber-500"></i> Sematkan di atas</span><span class="block text-[11px] text-slate-400">Selalu tampil paling atas &amp; di dashboard</span></span>
                    </label>
                    <label class="mt-3 flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3 cursor-pointer" title="Tulisan ini bisa dibaca siapa saja di halaman depan, tanpa login">
                        <input type="checkbox" name="tampil_publik" value="1" class="accent-indigo-600" {{ old('tampil_publik', $post->tampil_publik) ? 'checked' : '' }}>
                        <span><span class="block text-xs font-medium text-slate-600"><i class="fas fa-globe text-slate-500"></i> Tampil di halaman depan</span><span class="block text-[11px] text-slate-400">Bisa dibaca tanpa login. Jangan dicentang untuk SOP atau pengumuman internal.</span></span>
                    </label>
                </x-card>

                <x-card>
                    <h3 class="text-sm font-medium text-slate-800 mb-4">Kategori &amp; Tag</h3>
                    <x-form.group label="Kategori" dalam>
                        <x-form.select name="id_kategori">
                            <option value="">-- Tanpa kategori --</option>
                            @foreach($kategoriList as $k)<option value="{{ $k->id }}" {{ (string) old('id_kategori', $post->id_kategori) === (string) $k->id ? 'selected' : '' }}>{{ $k->nama }}</option>@endforeach
                        </x-form.select>
                    </x-form.group>
                    <x-form.group label="atau buat kategori baru" class="mt-4" dalam>
                        <x-form.input type="text" name="kategori_baru" value="{{ old('kategori_baru') }}" maxlength="60" placeholder="mis. Info Pemasok" />
                    </x-form.group>
                    <x-form.group label="Tag (pisahkan koma)" class="mt-4" dalam>
                        <x-form.input type="text" name="tags" value="{{ old('tags', $post->tags) }}" maxlength="255" placeholder="banner, laminasi, sop" />
                    </x-form.group>
                </x-card>

                <x-card>
                    <h3 class="text-sm font-medium text-slate-800 mb-4">Gambar Sampul</h3>
                    <div id="pratinjau-sampul" class="rounded-2xl overflow-hidden bg-slate-100 aspect-[16/9] mb-3 {{ $post->url_sampul ? '' : 'hidden' }}">
                        <img loading="lazy" decoding="async" id="img-sampul" src="{{ $post->url_sampul }}" alt="Sampul" class="w-full h-full object-cover">
                    </div>
                    <input type="file" name="sampul" id="input-sampul" accept="image/png,image/jpeg,image/webp" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-[10px] file:font-medium file:uppercase file:bg-indigo-50 file:text-indigo-600">
                    <p class="text-[11px] text-slate-400 mt-2">JPG/PNG/WebP, maks. 5 MB. Rasio 16:9 paling pas.</p>
                    @if($post->gambar_sampul)
                    <label class="mt-3 flex items-center gap-2 text-xs font-medium text-slate-500"><input type="checkbox" name="hapus_sampul" value="1" class="accent-rose-500"> Hapus sampul saat ini</label>
                    @endif
                </x-card>

                <div class="flex gap-3">
                    <x-button type="submit" id="btn-simpan-post" class="flex-1" title="Simpan postingan"><i class="fas fa-check"></i> {{ $edit ? 'Simpan Perubahan' : 'Simpan' }}</x-button>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script src="{{ asset('vendor/quill/quill.min.js') }}"></script>
<script>
    const URL_UNGGAH = @json(route('postingan.unggah'));
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;

    // Toolbar sticky menempel tepat di bawah topbar (tingginya berbeda di desktop/mobile)
    const ukurTopbar = () => document.documentElement.style.setProperty('--tinggi-topbar', (document.querySelector('.topbar')?.offsetHeight || 78) + 'px');
    ukurTopbar(); window.addEventListener('resize', ukurTopbar);

    // ---- Format kustom: jarak baris/paragraf (kelas ql-spasi-*, lolos daftar putih server)
    const Parchment = Quill.import('parchment');
    Quill.register(new Parchment.Attributor.Class('spasi', 'ql-spasi', { scope: Parchment.Scope.BLOCK, whitelist: ['rapat', 'longgar', 'lega'] }), true);
    Quill.register(Quill.import('attributors/class/align'), true);
    const Font = Quill.import('attributors/class/font'); Font.whitelist = ['nunito', 'inter', 'lora', 'playfair', 'mono']; Quill.register(Font, true);
    const Ukuran = Quill.import('attributors/class/size'); Ukuran.whitelist = ['small', 'large', 'huge']; Quill.register(Ukuran, true);
    const Warna = Quill.import('attributors/class/color'); Warna.whitelist = ['red', 'orange', 'green', 'blue', 'purple', 'gray']; Quill.register(Warna, true);
    const Latar = Quill.import('attributors/class/background'); Latar.whitelist = ['yellow', 'lightgreen', 'lightblue', 'pink']; Quill.register(Latar, true);
    const Delta = Quill.import('delta');

    const quill = new Quill('#editor-postingan', {
        theme: 'snow',
        placeholder: 'Tulis isi postingan di sini... (bisa tempel atau seret gambar)',
        formats: ['font', 'size', 'color', 'background', 'script', 'header', 'bold', 'italic', 'underline', 'strike', 'list', 'indent', 'align', 'spasi', 'blockquote', 'link', 'image'],
        modules: {
            toolbar: { container: '#toolbar-postingan', handlers: { image: pilihGambar } },
            history: { delay: 800, maxStack: 200, userOnly: true },
        },
    });

    // ---- Tempel: hanya format standar yang lolos. h1 -> Judul Bagian, h5/h6 -> Judul Kecil, spasi keras (nbsp) & spasi ganda dibuang.
    quill.clipboard.addMatcher(Node.ELEMENT_NODE, (node, delta) => {
        if (node.tagName === 'P' && !node.textContent.replace(/[\s\u00a0]/g, '') && !node.querySelector('img')) return new Delta();   // paragraf kosong tidak ikut
        const out = new Delta();
        delta.ops.forEach(op => {
            const at = op.attributes ? { ...op.attributes } : null;
            if (at && at.header) { at.header = at.header <= 2 ? 2 : (at.header === 3 ? 3 : 4); }
            if (at) { delete at.color; delete at.background; delete at.font; delete at.size; }   // format asal (Word/web) dibuang; pilih ulang lewat toolbar
            out.push(at && Object.keys(at).length ? { insert: op.insert, attributes: at } : { insert: op.insert });
        });
        return out;
    });
    quill.clipboard.addMatcher(Node.TEXT_NODE, (node, delta) => {
        const out = new Delta();
        delta.ops.forEach(op => out.push(typeof op.insert === 'string' ? { ...op, insert: op.insert.replace(/[\u00a0\u2007\u202f]/g, ' ').replace(/ {2,}/g, ' ') } : op));
        return out;
    });

    // ---- Rapikan: normalisasi isi ke "format standar" (tanpa baris kosong sebagai spasi, daftar, judul, baris terpotong)
    const KODE_POIN = /^[\-\u2022\u25cf\u25aa\u25e6\u2013*\u2192>]\s+/;
    const KODE_NOMOR = /^(\d{1,2}|[a-zA-Z])[.)]\s+/;
    function rapikanHtml(html) {
        const wadah = document.createElement('div'); wadah.innerHTML = html;
        const hasil = [];
        const teksDari = el => el.textContent.replace(/[\u00a0\u2007\u202f]/g, ' ').replace(/\s+/g, ' ').trim();
        let daftar = null;

        [...wadah.children].forEach(el => {
            const tag = el.tagName;
            if (tag !== 'P') { daftar = null; hasil.push(el); return; }
            if (el.querySelector('img')) { daftar = null; hasil.push(el); return; }
            if (teksDari(el) === '') { daftar = null; return; }                              // baris kosong dibuang: jarak diatur gaya
            el.innerHTML = el.innerHTML.replace(/&nbsp;/g, ' ').replace(/ {2,}/g, ' ').trim().replace(/(<br\s*\/?>\s*)+$/i, '');

            const t = teksDari(el);
            const poin = KODE_POIN.test(t), nomor = KODE_NOMOR.test(t);
            if (poin || nomor) {                                                              // "- a", "1. a" -> daftar sungguhan
                const jenis = poin ? 'UL' : 'OL';
                if (!daftar || daftar.tagName !== jenis) { daftar = document.createElement(jenis); hasil.push(daftar); }
                const li = document.createElement('li');
                li.innerHTML = el.innerHTML.replace(poin ? KODE_POIN : KODE_NOMOR, '');
                daftar.appendChild(li);
                return;
            }
            daftar = null;
            const sebelum = hasil[hasil.length - 1];
            const semuaTebal = el.children.length === 1 && /^(STRONG|B)$/.test(el.firstElementChild.tagName) && teksDari(el.firstElementChild) === t;
            if (t.length <= 70 && !/[.,;:!?]$/.test(t) && (semuaTebal || (t.length >= 4 && t === t.toUpperCase() && /[A-Z]/.test(t)))) {
                const h = document.createElement(semuaTebal ? 'h3' : 'h2');                   // judul terdeteksi
                h.textContent = t; hasil.push(h); return;
            }
            // baris terpotong (hasil salin PDF/e-mail): sambung ke paragraf sebelumnya
            if (sebelum && sebelum.tagName === 'P' && teksDari(sebelum).length > 40 && !/[.!?:;)"\u201d]$/.test(teksDari(sebelum)) && /^[a-z]/.test(t) && !el.className) {
                sebelum.innerHTML += ' ' + el.innerHTML; return;
            }
            hasil.push(el);
        });
        return hasil.map(e => e.outerHTML).join('');
    }
    function rapikan(senyap) {
        const sebelum = quill.root.innerHTML;
        const sesudah = rapikanHtml(sebelum);
        if (sesudah === sebelum) { if (!senyap) toast('Sudah rapi, tidak ada yang perlu diperbaiki', 'info'); return; }
        // Quill menyisipkan baris kosong di antara paragraf & daftar saat konversi - dibuang agar hasilnya stabil
        const delta = quill.clipboard.convert(sesudah), bersih = new Delta();
        delta.ops.forEach(op => bersih.push(typeof op.insert === 'string' && !op.attributes ? { insert: op.insert.replace(/\n{2,}/g, '\n') } : op));
        quill.setContents(bersih, 'user');
        toast(senyap ? 'Hasil tempel dirapikan otomatis (urungkan dengan Ctrl+Z)' : 'Format dirapikan', 'success');
    }
    function toast(t, ikon) { Swal.fire({ toast: true, position: 'top-end', icon: ikon, title: t, timer: 2400, showConfirmButton: false }); }

    // Dokumen kosong lalu ditempel isi panjang -> rapikan otomatis (menghindari lompatan kursor saat menyisipkan di tengah tulisan)
    quill.root.addEventListener('paste', () => {
        const kosongSebelum = quill.getText().trim() === '';
        setTimeout(() => { if (kosongSebelum && quill.getText().trim().length > 80) rapikan(true); else hapusBarisKosong(); }, 60);
    });
    // Baris kosong beruntun hasil tempel (Enter ganda dari Word/web/teks polos) dibuang: jarak antarparagraf diatur gaya, bukan Enter.
    function hapusBarisKosong() {
        let idx = 0, awalBaris = true; const hapus = [];
        const akhir = quill.getLength() - 1;
        quill.getContents().ops.forEach(op => {
            if (typeof op.insert !== 'string') { idx += 1; awalBaris = false; return; }
            for (const c of op.insert) {
                if (c === '\n') { if (awalBaris && !op.attributes && idx !== akhir) hapus.push(idx); awalBaris = true; }
                else awalBaris = false;
                idx += 1;
            }
        });
        hapus.reverse().forEach(i => quill.deleteText(i, 1, 'user'));
    }
    document.getElementById('btn-rapikan').addEventListener('click', () => rapikan(false));
    document.getElementById('btn-undo').addEventListener('click', () => quill.history.undo());
    document.getElementById('btn-redo').addEventListener('click', () => quill.history.redo());

    function hitungKata() {
        const t = quill.getText().trim();
        document.getElementById('hitung-kata').textContent = (t ? t.split(/\s+/).length : 0) + ' kata';
    }
    quill.on('text-change', hitungKata); hitungKata();

    // ---- Unggah gambar ke server, lalu sisipkan ke editor
    async function unggah(file) {
        const fd = new FormData();
        fd.append('gambar', file);
        const r = await fetch(URL_UNGGAH, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, credentials: 'same-origin', body: fd });
        if (!r.ok) throw new Error('Unggah gagal (' + r.status + ')');

        return (await r.json()).url;
    }
    function pilihGambar() {
        const input = document.createElement('input');
        input.type = 'file'; input.accept = 'image/png,image/jpeg,image/webp,image/gif'; input.multiple = true;
        input.onchange = async () => {
            for (const f of input.files) {
                try { const url = await unggah(f); const pos = (quill.getSelection(true) || { index: quill.getLength() }).index; quill.insertEmbed(pos, 'image', url, 'user'); quill.setSelection(pos + 1); }
                catch (e) { Swal.fire('Gagal', 'Gambar ' + f.name + ' tidak dapat diunggah (maks. 5 MB).', 'error'); }
            }
        };
        input.click();
    }

    // ---- Pratinjau sampul
    document.getElementById('input-sampul').addEventListener('change', function () {
        const f = this.files[0]; if (!f) return;
        document.getElementById('img-sampul').src = URL.createObjectURL(f);
        document.getElementById('pratinjau-sampul').classList.remove('hidden');
    });

    // ---- Simpan: gambar tempel (data:) diunggah dulu agar tidak hilang, lalu isi dikirim
    document.getElementById('form-postingan').addEventListener('submit', async function (e) {
        if (this.__siap) return;
        e.preventDefault();
        const btn = document.getElementById('btn-simpan-post'); btn.disabled = true;
        try {
            for (const img of quill.root.querySelectorAll('img[src^="data:"]')) {
                const blob = await (await fetch(img.src)).blob();
                img.src = await unggah(new File([blob], 'tempel.' + (blob.type.split('/')[1] || 'png'), { type: blob.type }));
            }
        } catch (err) { btn.disabled = false; Swal.fire('Gagal', 'Ada gambar tempel yang tidak dapat diunggah. Coba sisipkan lewat tombol gambar.', 'error'); return; }
        document.getElementById('isi-input').value = quill.root.innerHTML;
        this.__siap = true; this.submit();
    });
</script>
@endpush
@endsection
