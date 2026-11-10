{{--
    Tooltip global: (1) tombol/ikon tanpa teks otomatis diberi penjelasan dari ikon/aksinya, (2) singkatan umum pada
    judul kolom & label diberi arti, (3) tampilan tooltip halus & cepat (menggantikan title bawaan browser yang lambat).
    Berlaku juga untuk elemen yang dibuat lewat JavaScript (delegasi + MutationObserver).
    Tulis title="..." di elemen mana pun untuk tooltip khusus; data-no-tip menonaktifkan otomatisasi.
--}}
<style>
    #app-tooltip { position: fixed; z-index: 100000; max-width: 280px; padding: 6px 10px; border-radius: 8px; background: #1E2A4A; color: #F5F8FF; font-size: 11px; font-weight: 500; line-height: 1.4; pointer-events: none; opacity: 0; transform: translateY(2px); transition: opacity .12s ease, transform .12s ease; box-shadow: 0 6px 18px rgba(15, 23, 42, .25); }
    #app-tooltip.show { opacity: 1; transform: translateY(0); }
    @media print { #app-tooltip { display: none !important; } }
</style>
<script>
(function () {
    // ikon (kelas FontAwesome) -> penjelasan
    const IKON = [
        [/fa-(trash|trash-alt|trash-can)\b/, 'Hapus'], [/fa-(edit|pen|pen-to-square|pencil|pencil-alt)\b/, 'Ubah / edit'],
        [/fa-(times|xmark|times-circle|circle-xmark)\b/, 'Tutup'], [/fa-(search|magnifying-glass)\b/, 'Cari'],
        [/fa-print\b/, 'Cetak'], [/fa-(eye|eye-open)\b/, 'Lihat detail'], [/fa-eye-slash\b/, 'Sembunyikan'],
        [/fa-(plus|plus-circle|circle-plus)\b/, 'Tambah'], [/fa-(save|floppy-disk|check|check-circle|circle-check)\b/, 'Simpan / setujui'],
        [/fa-(undo|undo-alt|rotate-left|redo|rotate-right)\b/, 'Kembalikan / reset'], [/fa-(sync|sync-alt|rotate|arrows-rotate)\b/, 'Muat ulang'],
        [/fa-(download|file-download)\b/, 'Unduh'], [/fa-(upload|file-upload|cloud-upload-alt)\b/, 'Unggah berkas'],
        [/fa-(copy|clone)\b/, 'Salin'], [/fa-(info|info-circle|circle-info|question-circle|circle-question)\b/, 'Informasi'],
        [/fa-(bell)\b/, 'Notifikasi'], [/fa-(exclamation-triangle|triangle-exclamation)\b/, 'Perhatian'],
        [/fa-(arrow-right-to-bracket|sign-in-alt)\b/, 'Buka / lanjut'], [/fa-(right-from-bracket|sign-out-alt)\b/, 'Keluar'],
        [/fa-(filter)\b/, 'Terapkan filter'], [/fa-(ban)\b/, 'Batalkan / tolak'], [/fa-(lock)\b/, 'Terkunci'], [/fa-(unlock)\b/, 'Buka kunci'],
        [/fa-(qrcode|barcode)\b/, 'Pindai kode'], [/fa-(camera)\b/, 'Kamera / foto'], [/fa-(image|images)\b/, 'Gambar'],
        [/fa-(link)\b/, 'Tautan'], [/fa-(external-link-alt|up-right-from-square)\b/, 'Buka di tab baru'],
        [/fa-(chevron-left|angle-left|arrow-left)\b/, 'Sebelumnya / kembali'], [/fa-(chevron-right|angle-right|arrow-right)\b/, 'Berikutnya'],
        [/fa-(history|clock-rotate-left)\b/, 'Riwayat'], [/fa-(cog|gear)\b/, 'Pengaturan'], [/fa-(ellipsis|ellipsis-v|ellipsis-h)\b/, 'Menu lainnya'],
        [/fa-(file-invoice|file-invoice-dollar|receipt)\b/, 'Nota / faktur'], [/fa-(file-pdf)\b/, 'Berkas PDF'], [/fa-(hand-holding-dollar)\b/, 'Bayar'],
    ];
    // isi onclick/aksi -> penjelasan (untuk tombol berikon svg / tanpa kelas ikon)
    const AKSI = [
        [/(hapus|remove|delete|destroy)/i, 'Hapus'], [/(edit|ubah)/i, 'Ubah / edit'], [/(close|tutup|toggleModal|dismiss)/i, 'Tutup'],
        [/(reset|kosongkan|clear)/i, 'Kosongkan / reset'], [/(print|cetak)/i, 'Cetak'], [/(cari|search)/i, 'Cari'], [/(simpan|save|submit)/i, 'Simpan'],
    ];
    // singkatan umum -> arti (judul kolom & label)
    const SINGKATAN = {
        'hpp': 'Harga Pokok Penjualan - biaya bahan/produksi per item', 'dp': 'Down Payment - uang muka', 'po': 'Pesanan Pembelian - pesanan pembelian ke supplier',
        'pr': 'Permintaan Pembelian - permintaan pembelian', 'bom': 'Bill of Materials - daftar bahan yang dipakai sebuah produk', 'spk': 'Surat Perintah Kerja untuk produksi',
        'qc': 'Quality Control - pemeriksaan mutu hasil', 'sj': 'Surat Jalan barang datang', 'no. sj': 'Nomor Surat Jalan', 'tempo': 'Pembelian dibayar nanti (kredit) sampai jatuh tempo',
        'piutang': 'Tagihan yang belum dibayar konsumen', 'hutang': 'Kewajiban yang belum dibayar ke supplier', 'margin': 'Selisih harga jual dan HPP', 'margin %': 'Persentase selisih harga jual dan HPP',
        'krg. bayar': 'Kurang bayar - sisa tagihan', 'closing': 'Penutupan sesi kas dan pencocokan uang fisik', 'saldo awal': 'Saldo pada saat mulai dicatat',
        'stok minimal': 'Batas stok - di bawah angka ini bahan dianggap menipis', 'konversi': 'Berapa satuan hitung dalam 1 satuan stok', 'nilai konversi': '1 satuan stok = berapa satuan hitung',
        'satuan stok': 'Satuan saat membeli/menyimpan bahan', 'satuan hitung': 'Satuan saat bahan dipakai produksi', 'harga rata-rata': 'Harga rata-rata tertimbang dari semua pembelian bahan',
        'rework': 'Pengerjaan ulang karena hasil tidak lolos QC', 'waste': 'Bahan terbuang/rusak saat produksi', 'collecting': 'Pengumpulan/serah terima hasil ke konsumen',
        'urgent': 'Order diprioritaskan', 'very urgent': 'Order paling diprioritaskan', 'stock opname': 'Penghitungan stok fisik untuk mencocokkan dengan sistem',
        'kartu stok': 'Riwayat keluar-masuk stok per bahan', 'jurnal': 'Catatan akuntansi debet-kredit', 'debet': 'Sisi kiri jurnal (menambah aset/beban)', 'kredit': 'Sisi kanan jurnal (menambah pendapatan/hutang)',
        'periode': 'Rentang tanggal laporan', 'kelas': 'Tingkat desainer yang menentukan persen komisi', 'komisi': 'Bagian desainer dari biaya desain',
        'tier': 'Tingkat harga berdasarkan jumlah/ukuran order', 'golongan': 'Kelompok konsumen dengan harga khusus', 'urgensi': 'Tingkat prioritas pengerjaan',
        'divisi': 'Bagian produksi/mesin yang mengerjakan', 'thumbnail': 'Gambar kecil sebagai acuan desain', 'no. po': 'Nomor Pesanan Pembelian dari konsumen',
    };

    function teksLangsung(el) { return (el.textContent || '').replace(/\s+/g, ' ').trim(); }

    function tebakIkon(el) {
        const ikon = el.querySelector('i[class*="fa-"]');
        if (ikon) {
            for (const [re, teks] of IKON) if (re.test(ikon.className)) return teks;
        }
        const aksi = (el.getAttribute('onclick') || '') + ' ' + (el.getAttribute('href') || '') + ' ' + (el.className || '');
        for (const [re, teks] of AKSI) if (re.test(aksi)) return teks;
        if (el.getAttribute('type') === 'submit') return 'Simpan';
        return null;
    }

    function pasang(el, teks) {
        el.setAttribute('title', teks);
        if (!el.getAttribute('aria-label')) el.setAttribute('aria-label', teks);
    }

    function proses(root) {
        // 1) tombol/tautan tanpa teks (hanya ikon)
        root.querySelectorAll('button:not([title]):not([aria-label]):not([data-no-tip]):not([data-tip]), a:not([title]):not([aria-label]):not([data-no-tip]):not([data-tip]), [role="button"]:not([title]):not([aria-label]):not([data-tip])').forEach(el => {
            if (teksLangsung(el) !== '' || el.closest('.select2-container, .swal2-container')) return;
            if (!el.querySelector('i, svg')) return;
            const t = tebakIkon(el);
            if (t) pasang(el, t);
        });
        // 2) singkatan pada judul kolom & label kecil
        root.querySelectorAll('th:not([title]):not([data-tip]), label:not([title]):not([data-tip])').forEach(el => {
            const k = teksLangsung(el).toLowerCase().replace(/\s*\*$/, '');
            if (k && k.length <= 22 && SINGKATAN[k]) el.setAttribute('title', SINGKATAN[k]);
        });
    }

    // tampilan tooltip
    let tip, timer, target;
    function elTip() {
        if (!tip) { tip = document.createElement('div'); tip.id = 'app-tooltip'; tip.setAttribute('role', 'tooltip'); document.body.appendChild(tip); }
        return tip;
    }
    function sembunyi() { clearTimeout(timer); if (tip) tip.classList.remove('show'); if (target && target.dataset.tip) { target.setAttribute('title', target.dataset.tip); delete target.dataset.tip; } target = null; }
    function tampil(el) {
        const teks = el.dataset.tip; if (!teks) return;
        const t = elTip(); t.textContent = teks; t.classList.remove('show'); t.style.left = '0px'; t.style.top = '0px';
        const r = el.getBoundingClientRect(), w = t.offsetWidth, h = t.offsetHeight;
        let x = Math.min(Math.max(8, r.left + r.width / 2 - w / 2), window.innerWidth - w - 8);
        let y = r.top - h - 8; if (y < 8) y = r.bottom + 8;
        t.style.left = x + 'px'; t.style.top = y + 'px';
        requestAnimationFrame(() => t.classList.add('show'));
    }
    document.addEventListener('mouseover', function (e) {
        const el = e.target.closest ? e.target.closest('[title]') : null;
        if (!el || el === target) return;
        if (el.tagName === 'IFRAME' || el.tagName === 'HTML' || el.tagName === 'BODY') return;
        sembunyi();
        target = el; el.dataset.tip = el.getAttribute('title'); el.removeAttribute('title');
        timer = setTimeout(() => tampil(el), 250);
    });
    document.addEventListener('mouseout', function (e) { if (target && !target.contains(e.relatedTarget)) sembunyi(); });
    ['click', 'scroll', 'keydown', 'touchstart'].forEach(ev => document.addEventListener(ev, sembunyi, true));

    function mulai() {
        proses(document);
        let jadwal = null;
        new MutationObserver(function () { clearTimeout(jadwal); jadwal = setTimeout(() => proses(document), 250); })
            .observe(document.body, { childList: true, subtree: true });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mulai); else mulai();
})();
</script>
