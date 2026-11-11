{{--
    print-chrome.blade.php
    SATU sumber aturan "buang chrome aplikasi saat cetak" untuk SEMUA
    halaman yang memakai layouts.app. Dipanggil sekali dari layouts/app.blade.php.

    Bukan untuk DIKOSONGKAN: laporan yang butuh huruf serif/format khusus
    tetap boleh menambahkan style sendiri di bawah ini (keduanya menggabungkan
    secara aditif). Yang disatukan di sini hanya:

      1. @page A4, margin 12mm.
      2. print-color-adjust: exact (background ikut tercetak, tidak putih polos).
      3. Semua elemen chrome/aplikasi disembunyikan (.pro-topbar, .pro-bar1,
         .pro-bar2, .pro-menu-btn, .pro-footer, #mainSidebar, #sidebarOverlay,
         #proMega, aside, .no-print, tombol, overlay dll).
      4. #mainContent/.content-wrapper di-nol-kan dari padding/margin/max-width
         supaya konten A4 memenuhi teks, bukan kiri-tengah.

    Sebelumnya aturan ini ada di app.css/ui-refresh.css dengan kelas usang
    `.topbar` (tidak ada elemen itu di layout sekarang) - jadi topbar ikut
    tercetak di semua laporan yang layouts.app.
--}}
<style>
    @page { size: A4; margin: 12mm; }

    @media print {
        :root { color-scheme: light; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { background: #fff !important; }

        /* Chrome aplikasi yang tidak boleh ikut tercetak */
        .pro-topbar,
        .pro-bar1,
        .pro-bar2,
        .pro-logo,
        .pro-wordmark,
        .pro-search,
        .pro-premium,
        .pro-premium-teks,
        .pro-icon-btn,
        .pro-menu-btn,
        .pro-live,
        .pro-meta,
        .pro-footer,
        .pro-mobilebar,
        .mobile-bar,
        #mainSidebar,
        #sidebarOverlay,
        #proMega,
        aside,
        .no-print,
        #app-tooltip,
        #cmdk,
        .cmdk,
        .toast-notifications,
        .pv2-toast {
            display: none !important;
        }

        /* Main: penuh lebar, tanpa margin/padding kontainer aplikasi */
        main#mainContent,
        .content-wrapper {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            overflow: visible !important;
        }
    }
</style>
