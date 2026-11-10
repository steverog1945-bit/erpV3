{{--
    CSS bersama untuk semua dokumen print (nota, closing kas, SPK).

    Include sekali per halaman SETELAH variabel $format ('thermal',
    'continuous', atau ukuran A4/A5) sudah didefinisikan, karena
    beberapa nilai di sini berbeda per format.

    KETIKAAN YANG MENGATUR SELURUH BERKAS INI:

    1. Font = font stack OpenCode, bukan Inter. Alasan praktis, bukan
       sekadar selera: halaman print TIDAK memuat stylesheet Inter
       sama sekali (file Inter hanya di-<link> di layout aplikasi),
       jadi `Inter` di stack print selama ini tidak pernah ada -
       browser langsung jatuh ke Segoe UI atau Arial. Menulis Inter
       di sini hanya menipu. Stack di bawah adalah milik OpenCode persis.

    2. Bobot hanya 500 (dan 400 untuk isi). OpenCode tidak punya
       600 ke atas. Untuk thermal ada alasan teknis yang lebih
       penting, ada di catatan tabel di bawah.

    3. Warna diambil dari token OpenCode (lihat theme.css di
       packages/ui/src/styles/theme.css repo sst/opencode):
         teks utama #171717, teks sekunder #6f6f6f, teks tersier #8f8f8f,
         garis #e5e5e5, aksen #034cff, sukses #2dba26, kritis #ed4831.
       Sebelumnya dokumen cetak memakai indigo (#2563EB) dan abu-abu
       kebiruan yang tidak ada di palet itu sama sekali. Untuk dokumen
       yang keluar dari printer, warna yang tidak ada di palet berarti
       tinta yang tidak disengaja.

    4. Minimalis: garis putus-putus dihapus ( became hairline solid),
       badge status berbentuk pil diganti teks, dan kotak catatan
       berbingkai diganti satu garis pemisah. Semua itu dekorasi yang
       tidak membawa informasi - di struk yang sempit, garis putus
       hanya menambah titik-titik yang membuat bacaan terasa lebih berat.
--}}
<style>
    * { box-sizing: border-box; }

    body {
        font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        margin: 0;
        padding: {{ $format === 'thermal' ? '8px' : '16px' }};
        color: #171717;
        background: #ffffff;
        font-size: {{ $format === 'thermal' ? '11px' : '12px' }};
        line-height: 1.5;
        {{-- THERMAL: halaman di layar DISAMPAI jadi selebar kertas (80mm),
             bukan selebar jendela browser. Gunanya: yang dilihat operator
             persis yang akan keluar dari printer, dan "Save as PDF" dari
             dialog cetak menghasilkan halaman selebar kertas - bukan A4
             dengan ruang kosong excessive. Lebar halaman saat CETAK diatur
             oleh aturan @page di bawah. --}}
        {{ $format === 'thermal' ? 'width: 80mm; margin: 0 auto;' : '' }}
    }

    {{-- Lebar kertas. 'continuous' (kertas sambung / dot-matrix) memakai
         ukuran font yang SAMA dengan A4 di seluruh style ini - dua-duanya
         "bukan thermal", hanya lebar kertasnya berbeda. --}}
    .sheet {
        max-width: {{ $format === 'thermal' ? '80mm' : ($format === 'continuous' ? '190mm' : '480px') }};
        /* THERMAL: blok nota ditarik ke KIRI, bukan dipusatkan di halaman.

           Alasan (permintaan pemilik): printer thermal kadang kena error
           koneksi dan yang keluar hanya bagian kiri kertas. Kalau nota
           dipusatkan, bagian kiri yang tercetak bisa jadi memotong
           kepala struk - nama toko, nomor nota, dan item pertama - yaitu
           bagian yang justru paling dibutuhkan saat CETAK ULANG.

           Yang dipindah hanya LETAK bloknya. Isi di dalamnya tetap
           terpusat persis seperti sebelumnya (kepala dokumen center, tabel
           tetap kiri-kanan), jadi tidak ada yang berubah secara visual
           selain posisinya di halaman. Format lain (A4 / continuous)
           tetap dipusatkan. */
        margin: {{ $format === 'thermal' ? '0' : '0 auto' }};
    }

    /* ----------------------------------------------------------------------
       Kepala dokumen
       ---------------------------------------------------------------------- */
    .print-header { text-align: center; margin-bottom: {{ $format === 'thermal' ? '10px' : '14px' }}; }

    .print-header img {
        max-height: {{ $format === 'thermal' ? '28px' : '40px' }};
        margin-bottom: 6px;
        object-fit: contain;
    }

    /* 500, bukan 700/800. Nama perusahaan adalah satu-satunya hal yang
       harus terbaca pertama kali di struk, dan pada bobot 500 dengan
       ukuran 13px (thermal) / 17px (A4) itu sudah cukup menonjol
       tanpa tebal. */
    .print-header h1 {
        font-size: {{ $format === 'thermal' ? '13px' : '17px' }};
        margin: 0;
        font-weight: 500;
        letter-spacing: -0.01em;
        color: #171717;
    }

    .print-header .company-info {
        font-size: {{ $format === 'thermal' ? '9.5px' : '11px' }};
        color: #6f6f6f;
        margin: 3px 0 0;
        line-height: 1.45;
    }

    /* Judul dokumen tidak lagi huruf besar dengan letter-spacing lebar.
       Di struk thermal itu menambah lebar tanpa menambah hierarki;
       hierarki di sini datang dari ukuran dan warna saja. */
    .print-header .doc-title {
        font-size: {{ $format === 'thermal' ? '10px' : '11.5px' }};
        color: #034cff;
        font-weight: 500;
        margin: 7px 0 0;
    }

    .center { text-align: center; }
    .sub { font-size: 11px; color: #6f6f6f; margin: 0 0 10px; }

    /* ----------------------------------------------------------------------
       Tabel

       CATATAN THERMAL YANG HARAP TIDAK DIABAIKAN.

       Printer thermal merender 1-bit: tidak ada anti-alias abu-abu
       seperti di layar, hanya penuh atau kosong per piksel. Kombinasi
       "font kecil + bobot tebal + letter-spacing" membuat tepi huruf
       pecah jadi titik-titik, bukan garis utuh - keluhan yang sudah
       pernah masuk.

       Solusinya bukan mempertebal, tapi MEMBESARKAN. Ukuran 11px
       dengan bobot 400 memberi tepi yang jauh lebih utuh daripada
       10px dengan bobot 600, dan hasilnya juga lebih mudah dibaca
       dari jarak jauh. Karena itu header tabel thermal dinaikkan ke
       10.5px bobot 500, bukan diturunkan ke 9px bobot 700.
    ---------------------------------------------------------------------- */
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: {{ $format === 'thermal' ? '11px' : '12px' }};
    }

    th, td { padding: {{ $format === 'thermal' ? '2.5px' : '3px' }} 4px; text-align: left; vertical-align: top; }

    th {
        border-bottom: 1px solid #e5e5e5;
        font-size: {{ $format === 'thermal' ? '10.5px' : '10.5px' }};
        font-weight: 500;
        color: #6f6f6f;
    }

    td.right, th.right { text-align: right; }

    /* Garis pemisah. Garis putus-putus dihapus: pada printer thermal
       garis putus digambar ulang tiap beberapa piksel sehingga menjadi
       noise, bukan batas yang terbaca. */
    .divider { border-top: 1px solid #e5e5e5; margin: {{ $format === 'thermal' ? '5px' : '7px' }} 0; }

    /* Varian rapat, tetap dipertahankan karena beberapa halaman
       memakainya untuk bagian tertentu saja. */
    .table-compact th, .table-compact td { padding: 2px 4px; }
    .divider-tight { margin: 4px 0; }

    .total-row td {
        font-weight: 500;
        font-size: {{ $format === 'thermal' ? '12px' : '13.5px' }};
        color: #171717;
    }

    /* Badge status. Sebelumnya pil berwarna; sekarang teks biasa
       dengan warna status. Bentuk pil tidak menambah informasi apa pun
       pada kertas, dan menambah tinggi baris pada struk sempit. */
    .status-badge {
        display: inline;
        padding: 0;
        border-radius: 0;
        background: none;
        color: #171717;
        font-weight: 500;
        font-size: {{ $format === 'thermal' ? '10px' : '11px' }};
    }
    .status-badge[data-status="success"] { color: #2dba26; }
    .status-badge[data-status="critical"] { color: #ed4831; }
    .status-badge[data-status="info"] { color: #034cff; }

    /* Catatan. Bingkai putus-putus + latar diganti satu garis pemisah
       di atas: isi catatan tetap terbaca, tapi tidak menambah kotak
       yang harus dicetak. */
    .catatan {
        border-top: 1px solid #e5e5e5;
        padding: {{ $format === 'thermal' ? '6px' : '8px' }} 0 0;
        margin-top: {{ $format === 'thermal' ? '6px' : '8px' }};
        font-size: {{ $format === 'thermal' ? '10px' : '11px' }};
        color: #6f6f6f;
    }

    .signature { display: flex; justify-content: space-between; margin-top: {{ $format === 'thermal' ? '24px' : '32px' }}; }
    .signature div { text-align: center; width: 45%; }
    .signature .line {
        border-top: 1px solid #8f8f8f;
        margin-top: {{ $format === 'thermal' ? '28px' : '36px' }};
        padding-top: 5px;
        font-size: {{ $format === 'thermal' ? '9.5px' : '11px' }};
        color: #6f6f6f;
    }

    .selisih-negatif { color: #ed4831; }
    .selisih-positif { color: #2dba26; }

    /* Catatan kaki memakai #6f6f6f, bukan #94a3b8. Yang kedua hanya
       berkontras 2,3:1 terhadap putih - di layar masih terbaca, tapi di
       printer yang sering menghasilkan abu-abu tipis, teks seperti itu
       hilang. */
    .footer-note {
        text-align: center;
        font-size: {{ $format === 'thermal' ? '9.5px' : '10.5px' }};
        color: #6f6f6f;
        margin-top: 8px;
    }

    /* ----------------------------------------------------------------------
       Tombol cetak (tidak ikut tercetak)
       ---------------------------------------------------------------------- */
    .no-print { text-align: center; margin-top: 20px; }

    .no-print button {
        padding: 8px 20px;
        font-family: inherit;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        /* Tombol utama OpenCode: #171717, bukan indigo. */
        background: #171717;
        color: #fcfcfc;
        border: none;
        border-radius: 6px;
    }
    .no-print button:hover { background: #000000; }

    @media print {
        .no-print { display: none !important; }
        body { padding: 0; }
        {{-- THERMAL: halaman cetak = ukuran kertasnya sendiri (80mm), margin
             nol. Tanpa aturan ini, browser mencetak di kertas default (A4)
             dan nota thermal cuma occupying 80mm di pojok kiri atas - sisa
             kertas jadi ruang kosong yang ikut ter-simpan ke PDF.

             "size: 80mm auto" = lebar 80mm, tinggi ikut isi (struk thermal
             memang kertas kontinu, bukan kertas fixed). --}}
        @page {
            size: {{ $format === 'thermal' ? '80mm auto' : 'auto' }};
            margin: 0;
        }
    }
</style>

{{-- THERMAL: langsung buka dialog cetak begitu halaman selesai dimuat.

     Tanpa preview: operator buka halaman cetak, dialognya sudah muncul,
     jadi cukup menekan Enter (atau pilih printer + Print). Tujuannya
     memperpendek jalan dari "buka nota -> klik cetak -> pilih printer ->
     print" menjadi "buka nota -> Enter".

     Delay 300ms supaya logo/gambar sempat selesai dimuat - kalau langsung
     dipanggil, logo bisa belum tampil di kertas. --}}
@if($format === 'thermal')
<script>
    window.addEventListener('load', function () {
        // Hanya kalau halaman ini dibuka langsung (bukan di dalam iframe).
        // Pratinjau nota di Pengaturan Aplikasi memakai iframe - kalau
        // auto-cetak ikut jalan di sana, membuka Pengaturan Aplikasi akan
        // memunculkan dialog cetak yang tidak diminta, dan dialog itu
        // memblokir halaman sampai ditutup.
        if (window.top !== window.self) return;
        setTimeout(function () { window.print(); }, 300);
    });
</script>
@endif
