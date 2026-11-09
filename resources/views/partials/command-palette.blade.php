{{-- COMMAND PALETTE (Ctrl+K)

  Kenapa membaca menu dari DOM, bukan dari daftar terpisah di sini:

  Sidebar sudah_permission check. Setiap tautan di dalamnya ada hanya
  kalau $cekAkses() mengizinkan role yang sedang login, dan ikut hilang
  kalau menu-nya dinonaktifkan di Menu Editor. Kalau palette punya
  daftarnya sendiri, akan ada dua tempat yang harus dijaga sinkron, dan
  begitu ada yang berbeda, user bisa membuka halaman yang tidak
  deserved aksesnya dari palette sementara tidak bisa dari sidebar.

  Jadi yang dilakukan di sini: kumpulkan tautan yang SUDAH ada di
  sidebar saat palette dibuka. Apa yang terlihat di sidebar itu yang bisa
  diketik. Tidak ada daftar menu yang ditulis ulang di file ini.

  Letak: partial, bukan di dalam layout langsung, supaya bisa dipakai
  ulang (mis. di halaman cetak yang tidak punya sidebar) dan supaya
  layout tidak makin panjang.
--}}

<div id="palette" hidden
     class="fixed inset-0 z-[70] items-start justify-center px-4"
     style="padding-top: max(3rem, 10vh)">
    {{-- Latar gelap. Elemen terpisah supaya klik di dalamnya menutup,
         sementara klik di panel tidak. Sama seperti <x-modal>. --}}
    <div data-palette-backdrop class="absolute inset-0 bg-slate-900/50"></div>

    <div data-palette-panel role="dialog" aria-modal="true" aria-label="Cari menu"
         class="relative bg-white w-full max-w-xl rounded-lg border border-slate-200 shadow-xl overflow-hidden flex flex-col max-h-[70vh]">

        {{-- Kolom isian. Ikon cari di kiri. Placeholder-nya tetap
             terlihat saat input difokuskan karena placeholder
             menyingkir ketika ada teks - jadi tidak perlu teks
             tambahan sebagai petunjuk. --}}
        <div class="flex items-center gap-3 px-4 h-12 border-b border-slate-200 shrink-0">
            <i class="fas fa-search text-slate-400 text-sm shrink-0"></i>
            <input type="text" data-palette-input
                   class="flex-1 bg-transparent border-0 outline-none text-sm text-slate-900 placeholder:text-slate-400 p-0"
                   placeholder="Cari menu..."
                   role="combobox" aria-expanded="true" aria-controls="palette-daftar" aria-autocomplete="list" autocomplete="off">
            {{-- Ctrl+K sebagai pemicu. Ditampilkan supaya orang tahu ada
                 jalan pintas, bukan perlu ditebak. --}}
            <kbd class="hidden sm:flex items-center gap-0.5 text-[10px] text-slate-400 bg-slate-100 border border-slate-200 rounded px-1.5 py-0.5 font-medium">Ctrl K</kbd>
        </div>

        {{-- Hasil. Ditambah dari JS, jadi kosong di server. --}}
        <div id="palette-daftar" data-palette-daftar role="listbox" aria-label="Hasil pencarian"
             class="overflow-y-auto grow min-h-0 py-1.5"></div>

        {{-- Bilah bawah: tombol keyboard dan jumlah hasil. --}}
        <div data-palette-kaki class="flex items-center gap-4 px-4 h-9 border-t border-slate-200 bg-slate-50 text-[10px] text-slate-500 shrink-0">
            <span class="flex items-center gap-1"><kbd class="px-1 py-0.5 bg-white border border-slate-200 rounded">↑↓</kbd> pindah</span>
            <span class="flex items-center gap-1"><kbd class="px-1 py-0.5 bg-white border border-slate-200 rounded">Enter</kbd> buka</span>
            <span class="flex items-center gap-1"><kbd class="px-1 py-0.5 bg-white border border-slate-200 rounded">Esc</kbd> tutup</span>
            <span data-palette-hitung class="ml-auto tabular-nums"></span>
        </div>
    </div>
</div>

@push('scripts')
<script>
/* ==========================================================================
   COMMAND PALETTE

   Sumber data: <a href> yang ada di sidebar. Sidebar sudah memfilter
   berdasarkan role dan menu aktif, jadi palette otomatis mengikuti
   permission yang sama - tidak ada daftar kedua yang bisa berbeda.

   Pencarian: subsequence, bukan substring. Mengetik "kbu" menemukan
   "Kartu Stok" karena setiap huruf berurutan dalam kata itu, tidak
   harus berdampingan. Untuk 76 menu, ini membuat palette berguna pada
   2-3 huruf, bukan 5-6. Urutan hasil: yang semua hurufnya berurutan
   didahulukan yang hanya cocok di dalam kata, lalu yang di luar kata.
   ========================================================================== */
(function () {
    const root = document.getElementById('palette');
    if (!root) return;

    const input = root.querySelector('[data-palette-input]');
    const daftar = root.querySelector('[data-palette-daftar]');
    const panel = root.querySelector('[data-palette-panel]');
    const hitung = root.querySelector('[data-palette-hitung]');
    const panelEl = root.querySelector('[data-palette-panel]');

    let semua = [];       // [{ teks, grup, href, ikon }]
    let cocok = [];       // hasil filter + skor
    let indeks = 0;

    /* ----------------------------------------------------------------------
       Kumpulkan menu dari sidebar
       ---------------------------------------------------------------------- */
    function kumpulkan() {
        const nav = document.getElementById('mainSidebar');
        if (!nav) return [];

        const hasil = [];
        // Grup = toggle di atas daftar. Menu datar (tanpa grup) diletakkan
        // di grup "" supaya tetap bisa dicari, hanya tidak diberi label.
        const grup = document.createElement('span');

        Array.from(nav.querySelectorAll('a[href]')).forEach((a) => {
            // Abaikan tautan yang bukan navigasi (logout, tab lain, dll)
            const href = a.getAttribute('href');
            if (!href || href === '#' || href === 'javascript:void(0)') return;
            if (a.target === '_blank') return;

            // Label: span pertama yang bukan badge.
            //
            // Versi awal memakai span TERAKHIR, karena di sebagian besar
            // menu nama memang span terakhir. Tapi menu yang punya lencana
            // notifikasi menaruh badge di span paling akhir, jadi hasilnya
            // label "3" dan "35" - bukan nama menunya. Menu berlabel
            // angka juga tidak bisa diketik orang, jadi palette jadi
            // tidak berguna untuk kelompok menu itu.
            //
            // Badge dikenali dari class-nya, bukan dari isinya: nama menu
            // bisa secara teknis berupa angka, dan lebih aman menandai
            // berdasarkan class elemen tersebut.
            const semuaSpan = Array.from(a.querySelectorAll('span'));
            const badge = (el) =>
                el.classList.contains('notif-badge') ||
                /(^|[\s-])badge([\s-]|$)/.test(el.className || '') ||
                el.hasAttribute('data-badge');

            const spanLabel = semuaSpan.find((s) => !badge(s) && s.textContent.trim());
            const label = (spanLabel || a).textContent.trim();
            if (!label) return;

            // Grup: cari tombol grup terdekat sebelumnya di DOM.
            // Nama grup diambil dari span yang bukan badge, karena tombol
            // grup juga punya lencana notifikasi di samping chevron.
            const namaGrupDari = (btn) => {
                const s = btn.querySelector('span:not(.notif-badge)');
                return s ? s.textContent.trim() : '';
            };

            let labelGrup = '';
            let n = a.previousElementSibling;
            while (n) {
                if (n.classList && n.classList.contains('menu-group-toggle')) {
                    labelGrup = namaGrupDari(n);
                    break;
                }
                // Sibling bisa berupa blok Blade yang sudah dirender jadi
                // teks kosong. NOTA: nama directive Blade tidak boleh
                // ditulis di komentar ini - Blade membacanya sebagai
                // directive asli dan halaman jadi 500.
                if (n.classList && n.classList.contains('menu-group-items')) {
                    const btn = n.previousElementSibling;
                    if (btn && btn.classList.contains('menu-group-toggle')) {
                        labelGrup = namaGrupDari(btn);
                        break;
                    }
                }
                n = n.previousElementSibling;
            }

            // Ikon: ambil SVG pertama supaya bentuknya sama dengan sidebar
            let ikon = '';
            const svg = a.querySelector('svg');
            if (svg) ikon = svg.outerHTML;

            hasil.push({
                teks: label,
                grup: labelGrup,
                href: a.href,
                // Path tanpa domain dan tanpa garis miring depan/akhir,
                // dipakai sebagai lapisan pencarian ketiga. Lihat
                // catatan di atas filter().
                path: (a.pathname || '').replace(/^\/+|\/+$/g, ''),
                ikon: ikon,
                aktif: a.classList.contains('sb-active')
            });
        });

        return hasil;
    }

    /* ----------------------------------------------------------------------
       Pencarian subsequence dengan skor

       Subsequence saja ternyata tidak cukup. "akun" cocok dengan
       "Data Konsumen" (a di Data, k-u-n di Konsumen) dan "laba" cocok
       dengan "Laporan Belanja Bahan" - dan karena skor lamanya hanya
       punishments besar untuk urutan, kedua hasil itu mendahului menu
       yang memang benar. Jadi skornya melihat KATA: berapa banyak kata
       berbeda yang harus dilewati, dan apakah urutannya putus.

       Urutan nilai (kecil lebih baik):
         0            urutan, satu kata, mulai dari awal  -> "kartu" -> Kartu Stok
         +25 per kata tambahan yang harus dilewati      -> "akun"  -> Data Konsumen
         +40           tidak berurutan                    -> "bhn"   -> Bahan Baku
         +3 per huruf sebelum huruf pertama yang cocok
         + panjang/100                                 penyama agar nama lebih pendek tidak selalu menang
    ---------------------------------------------------------------------- */
    function skor(teks, kueri) {
        const t = teks.toLowerCase();
        const k = kueri.toLowerCase().replace(/\s+/g, '');
        if (!k) return { cocok: true, nilai: 0 };

        // Peta: setiap huruf -> indeks kata. Spasi dan tanda hubung
        // dihitung sebagai pemisah kata, jadi "Bahan Baku" punya 2 kata
        // dan "Bahan-Baku" juga 2.
        const kataOf = new Array(t.length).fill(0);
        let wi = 0;
        for (let i = 0; i < t.length; i++) {
            if (t[i] === ' ' || t[i] === '-' || t[i] === '&' || t[i] === '/') {
                wi += 1;
                continue;
            }
            kataOf[i] = wi;
        }

        const tRapis = t.replace(/[\s\-&/]/g, '');
        let pos = 0;
        let berurutan = true;
        let awal = -1;
        const kataPemakai = new Set();

        for (let i = 0; i < k.length; i++) {
            const ditemukan = tRapis.indexOf(k[i], pos);
            if (ditemukan < 0) return { cocok: false };
            if (awal < 0) awal = ditemukan;
            if (ditemukan !== pos) berurutan = false;
            kataPemakai.add(kataOf[ditemukan]);
            pos = ditemukan + 1;
        }

        let nilai = 0;
        // Melompati batas kata jauh lebih penicillin daripada tidak
        // berurutan di dalam satu kata: "akun" harus bisa menemukan
        // "Akun dan Kas", tapi tidak boleh mendahului "Akun".
        if (kataPemakai.size > 1) nilai += 25 * (kataPemakai.size - 1);
        if (!berurutan) nilai += 40;
        if (awal > 0) nilai += awal * 3;
        nilai += teks.length / 100;
        return { cocok: true, nilai: nilai };
    }

    /* Pencarian tiga lapis, dari yang paling kuat ke yang terlemah:

       1. Nama menu        -> "kartu" menemukan Kartu Stok
       2. Nama grup        -> "kasir" menemukan menu di grup Kasir meski
                             namanya tidak mengandung kata itu
       3. Path URL         -> "jurnal" menemukan /akuntansi/jurnal, dan
                             "akun" menemukan /akun

       Lapis 3 itu perlu karena beberapa menu punya nama yang tidak
       mengandungi kata kuncinya: /akuntansi/jurnal berlabel "Laporan
       Keuangan", dan /akun berlabel "Daftar Akun". Orang yang
       mengetik "jurnal" atau "akun" sedang mengingat URL-nya, bukan
       menebak nama menunya - menolak pencarian itu berarti palette
       tidak menemukan apa yang justru dicari.

       Yang hanya cocok lewat URL diberi penalti 50 supaya tetap
       ditemukan, tapi tidak mendahului menu yang namanya benar-benar
       mengandung kata itu.
    */
    function filter(kueri) {
        const hasil = [];
        for (const m of semua) {
            const byName = skor(m.teks, kueri);
            if (byName.cocok) {
                let nilai = byName.nilai;
                if (kueri && m.grup) {
                    const sg = skor(m.grup, kueri);
                    if (sg.cocok) nilai += 40;
                }
                hasil.push({ m: m, nilai: nilai });
                continue;
            }

            if (!kueri) continue;

            let nilai = null;
            if (m.grup) {
                const sg = skor(m.grup, kueri);
                if (sg.cocok) nilai = sg.nilai + 40;
            }
            if (nilai === null) {
                const su = skor(m.path, kueri);
                if (su.cocok) nilai = su.nilai + 50;
            }
            if (nilai !== null) hasil.push({ m: m, nilai: nilai });
        }
        hasil.sort((a, b) => a.nilai - b.nilai || a.m.teks.localeCompare(b.m.teks));
        return hasil;
    }

    /* ----------------------------------------------------------------------
       Render
       ---------------------------------------------------------------------- */
    const IKON_CARI = '<i class="fas fa-search text-slate-500"></i>';

    function render() {
        if (!cocok.length) {
            daftar.innerHTML = `
                <div class="px-4 py-10 text-center">
                    <div class="text-slate-500 mb-2">${IKON_CARI}</div>
                    <p class="text-sm text-slate-600">Tidak ada menu yang cocok</p>
                    <p class="text-xs text-slate-500 mt-1">
                        ${input.value.trim()
                            ? 'Coba kata yang lebih pendek, atau sebagian dari nama menu.'
                            : 'Sidebar sedang tidak menampilkan menu.'}
                    </p>
                </div>`;
            if (hitung) hitung.textContent = '0 hasil';
            return;
        }

        // Kelompokkan berdasarkan grup, tapi JAGA urutan yang sudah diurutkan
        // di atas - jadi grup ditampilkan sesuai urutan hasil terbaik, bukan
        // abjad.
        let html = '';
        let grupTeral = null;
        cocok.forEach((c, i) => {
            if (c.m.grup !== grupTeral) {
                grupTeral = c.m.grup;
                if (grupTeral) {
                    html += `<div class="px-4 pt-3 pb-1 text-[10px] font-medium text-slate-400 uppercase tracking-wide">${escapeHtml(grupTeral)}</div>`;
                }
            }
            const aktif = i === indeks;
            html += `
                <button type="button" role="option" aria-selected="${aktif}" data-palette-item="${i}"
                        class="w-full flex items-center gap-3 px-4 py-2 text-left transition-colors ${aktif ? 'bg-indigo-50' : 'hover:bg-slate-50'}">
                    <span class="w-[18px] h-[18px] shrink-0 flex items-center justify-center text-slate-400">${c.m.ikon || ''}</span>
                    <span class="flex-1 text-sm ${aktif ? 'text-indigo-700' : 'text-slate-700'} truncate">${escapeHtml(c.m.teks)}</span>
                    ${c.m.aktif ? '<span class="text-[10px] text-slate-400 shrink-0">halaman ini</span>' : ''}
                </button>`;
        });
        daftar.innerHTML = html;
        if (hitung) hitung.textContent = cocok.length + ' menu';
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[c]);
    }

    function sorot() {
        Array.from(daftar.querySelectorAll('[data-palette-item]')).forEach((el) => {
            const aktif = Number(el.getAttribute('data-palette-item')) === indeks;
            el.classList.toggle('bg-indigo-50', aktif);
            el.classList.toggle('hover:bg-slate-50', !aktif);
            el.setAttribute('aria-selected', String(aktif));
            const span = el.querySelector('span.text-sm');
            if (span) {
                span.className = 'flex-1 text-sm truncate ' + (aktif ? 'text-indigo-700' : 'text-slate-700');
            }
            if (aktif) el.scrollIntoView({ block: 'nearest' });
        });
    }

    function terapkan() {
        const kueri = input.value.trim();
        cocok = filter(kueri);
        indeks = 0;
        render();
    }

    /* Status terbuka dibaca dari ATRIBUT `hidden`, bukan dari class
       `hidden`. Elemennya memang diberi atribut itu di markup supaya
       benar-benar hilang dari accessibility tree saat tertutup.

       Awalnya pemeriksaan memakai class, dan itu salah: elemen tidak
       pernah punya class `hidden`, jadi kondisinya selalu terbaca
       "sudah terbuka" - Ctrl+K pertama kali justru MENUTUP, dan tidak
       ada yang terjadi sama sekali setelahnya. Karena itu semua
       pengecekan di sini memakai `isTerbuka()`.
    */
    function isTerbuka() {
        return !root.hidden;
    }

    function buka() {
        // Kumpulkan ulang setiap kali dibuka: permission bisa berubah (mis.
        // role baru diberikan admin) tanpa perlu reload.
        semua = kumpulkan();
        input.value = '';
        cocok = [];
        indeks = 0;
        terapkan();

        root.hidden = false;
        root.classList.add('flex');
        document.body.classList.add('overflow-hidden');
        input.focus();
    }

    function tutup() {
        root.hidden = true;
        root.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    }

    /* ----------------------------------------------------------------------
       Keyboard
       ---------------------------------------------------------------------- */
    input.addEventListener('input', terapkan);

    input.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (!cocok.length) return;
            indeks = (indeks + 1) % cocok.length;
            sorot();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (!cocok.length) return;
            indeks = (indeks - 1 + cocok.length) % cocok.length;
            sorot();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const pilih = cocok[indeks];
            if (pilih) window.location.href = pilih.m.href;
        } else if (e.key === 'Escape') {
            e.preventDefault();
            tutup();
        }
    });

    // Klik pada item
    daftar.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-palette-item]');
        if (!btn) return;
        const pilih = cocok[Number(btn.getAttribute('data-palette-item'))];
        if (pilih) window.location.href = pilih.m.href;
    });

    // Latar gelap / Escape global
    root.querySelector('[data-palette-backdrop]').addEventListener('click', tutup);
    document.addEventListener('keydown', (e) => {
        // Ctrl+K / Cmd+K. Dicek juga saat fokus ada di dalam input, karena
        // beberapa browser lama menuliskan karakter ke input kalau tidak
        // dicegah.
        if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
            e.preventDefault();
            if (isTerbuka()) tutup(); else buka();
            return;
        }
        if (e.key === 'Escape' && isTerbuka()) {
            tutup();
        }
    });

    // Ekspos supaya bisa dipanggil dari tempat lain (mis. tombol di topbar)
    window.bukaPalette = buka;
    window.tutupPalette = tutup;
})();
</script>
@endpush
