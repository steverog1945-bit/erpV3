{{--
    Gaya cetak TABEL LAPORAN - dipakai bersama oleh semua halaman laporan
    (Rekap Order, Stok Bahan, Produksi, Kas, Piutang, Hutang, HPP, Job
    Costing, Biaya Desain, Evaluasi Master, Order Jersey, Belanja Bahan,
    Jurnal, Buku Besar, Laba Rugi, Neraca, Neraca Saldo, Perbandingan,
    Kas Kecil, Audit Bulanan).

    Dipanggil dari partials/print-report-header.blade.php, jadi satu file ini
    mengatur seluruh laporan - tidak ada laporan yang bisa lupa ikut aturan
    karena tiap halaman punya style sendiri.

    MASALAH YANG DIPERBAIKI
    -----------------------
    Beberapa tabel laporan punya kolom lebih banyak dari yang muat di lebar
    A4. Browser TIDAK memotong tabel, tapi MEMPERLEBARNA - jadi kolom paling
    kanan terdorong keluar area cetak dan isinya tidak pernah keluar di
    kertas. Memperkecil huruf sendirian tidak menyelesaikannya: yang
    menyelesaikan adalah membiarkan teks membungkus di dalam kolom, lalu
    ukuran hurufnya dikembalikan ke ukuran yang sama untuk semua tabel.

    ATURANNYA
    ---------
    1. min-width: 0 pada sel - melepas lebar minimum per sel (mis.
       min-w-[220px]) yang membuat tabel memaksa lebar sendiri.
    2. word-break / overflow-wrap - teks panjang (nama produk, keterangan)
       membungkus di dalam kolom, bukan mendorong kolom jadi lebar.
    3. white-space: normal - melepas nowrap yang menahan teks supaya tidak
       pernah membungkus.
    4. font-size: var(--fs-tabel) - SATU ukuran huruf untuk SELURUH isi
       laporan, tanpa tingkat per jumlah kolom maupun per kelas utilitas.
       Sebelumnya ukuran huruf ikut jumlah kolom (10px untuk tabel 4 kolom,
       8px untuk tabel 13 kolom) DAN isi sel ber classed text-[...] tetap
       ikut aturan 13px milik ui-refresh.css - jadi halaman cetak bercampur:
       ada tabel 8px, tabel 10px, dan sel 13px di antaranya.
    5. font-size: inherit untuk elemen di dalam sel - isi sel yang dibungkus
       <span>/<div> ikut ukuran selnya, bukan ukuran sendiri.
    6. thead { display: table-header-group } - header tabel diulang di
       setiap halaman, jadi halaman kedua tidak mulai dengan baris data
       tanpa judul kolom.
    7. tr { break-inside: avoid } - satu baris tidak terbelah antar halaman.

    UKURAN HURUF PADA CETAK
    ------------------------
    Yang tersisa setelah semua aturan di atas: judul dokumen (nama perusahaan
    & judul laporan) dan kop. Ukurannya memang lebih besar - judul harus
    dibaca lebih dulu, itu bukan ketidakseragaman. Yang penting: di dalam
    isi laporan TIDAK ada lagi huruf yang lebih besar atau lebih kecil dari
    yang lain; hierarki antar bagian dibuat dari huruf besar (uppercase),
    tebal, dan spasi - bukan dari ukuran huruf.

    Ukuran huruf memakai px (bukan pt) supaya sama persis dengan kelas
    utilitas Tailwind yang dipakai di markup laporan - jadi tidak ada
    lompatan visual antara huruf dalam tabel dan huruf di luar tabel.
--}}
<style>
    @media print {
        :root {
            /* Ukuran huruf isi laporan - seragam untuk semua halaman
               laporan. Kalau someday perlu diubah, cukup angka ini - tidak
               perlu menyentuh setiap halaman laporan. */
            --fs-tabel: 9px;
        }

        table { width: 100% !important; max-width: 100% !important; }

        table th, table td {
            min-width: 0 !important;
            max-width: 100% !important;
            white-space: normal !important;
            word-break: break-word;
            overflow-wrap: anywhere;
            padding: 1.5px 3px !important;
            line-height: 1.25;
        }

        {{-- DASAR: teks yang tidak punya kelas utilitas huruf mewarisi
             ukuran ini, jadi ikut seragam. --}}
        main { font-size: var(--fs-tabel) !important; }

        {{-- TEKS BERKELAS UTILITAS HURUF dikembalikan ke satu ukuran.

             ui-refresh.css memaksa kelas huruf kecil (text-[8px] sampai
             text-xs) menjadi 13px !important. Itu keputusan untuk LAYAR
             supaya teks kecil tetap terbaca. Tapi aturan itu juga berlaku
             saat CETAK - dan di situ justru merusak: 13px per kolom membuat
             tabel laporan melebar melebihi A4 (kolom paling kanan terdorong
             keluar area cetak), dan halaman cetak jadi bercampur huruf.

             Selector memakai 'body .kelas' (dua tingkat) karena aturan 13px
             di ui-refresh.css juga memakai !important - dan pada!important
             yang sama, selector dengan tingkat lebih tinggi yang menang.

             Ukuran yang dikembalikan bukan 13px, tapi ukuran isi laporan
             yang sama untuk semua - termasuk kelas ukuran besar (text-xl
             dan friends), karena di halaman cetak isinya harus seragam.
             --}}
        body .text-\[8px\], body .text-\[9px\], body .text-\[10px\], body .text-\[10\.5px\],
        body .text-\[11px\], body .text-\[12px\], body .text-\[13px\], body .text-\[14px\],
        body .text-\[15px\], body .text-\[16px\], body .text-\[17px\], body .text-\[18px\],
        body .text-\[20px\],
        body .text-xs, body .text-sm, body .text-base, body .text-lg, body .text-xl,
        body .text-2xl, body .text-3xl {
            font-size: var(--fs-tabel) !important;
        }

        {{-- Halaman Audit Bulanan punya kelas tipografi sendiri (lembar-e,
             k-judul, k-ket, pil, bab-*). Semuanya ikut ukuran yang sama -
             kalau tidak, lembar audit tercetak dengan huruf 10/11/12/13px
             di antara laporan lain yang 9px. --}}
        body .lembar-e .l, body .lembar-e .v, body .lembar-e .v small,
        body .k-judul, body .k-ket, body .pil, body .bab-judul, body .bab-no {
            font-size: var(--fs-tabel) !important;
        }

        {{-- ISI TABEL: satu ukuran, tidak ada yang lebih besar atau lebih
             kecil. Selector memakai table[class] karena aturan 13px di
             ui-refresh.css bertingkat KELAS - dan selector yang hanya
             berisi elemen kalah dari aturan itu walau sudah !important. --}}
        table[class] th, table[class] td {
            font-size: var(--fs-tabel) !important;
        }

        {{-- Elemen di dalam sel ikut ukuran selnya.

             Aturan 13px di ui-refresh.css berlaku juga untuk <span>,
             <div>, atau <p> di dalam sel (mis. badge status, catatan
             kecil). Kalau selnya 9px tapi isinya 13px, tabel tercetak
             dengan huruf yang campur - persis yang harus dihindari.
             font-size: inherit membuat isi sel mengikuti ukuran sel. --}}
        table[class] th *, table[class] td * {
            font-size: inherit !important;
        }

        table thead { display: table-header-group; }
        table tr { break-inside: avoid; }
    }
</style>

