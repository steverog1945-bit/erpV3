<?php

/**
 * Peta akses per-URL untuk middleware CheckMenuAccess. Ini adalah SUMBER
 * KEBENARAN penegakan hak akses di level route - sebelumnya role_permissions
 * di database hanya dipakai untuk menyembunyikan/menampilkan link sidebar
 * (lihat layouts/app.blade.php), TIDAK pernah dicek ulang di controller/route,
 * jadi siapa pun yang login bisa membuka URL apa pun langsung.
 *
 * 'rules': prefix URL => daftar id_menu yang boleh mengakses (cukup salah
 * satu id_menu tersebut ada di role_permissions milik role user).
 * Middleware memilih prefix TERPANJANG yang cocok, jadi urutan penulisan di
 * sini tidak masalah (mis. 'konsumen/store-ajax' otomatis lebih diutamakan
 * daripada 'konsumen' walau keduanya cocok).
 *
 * 'always_allow': prefix yang boleh diakses siapa pun yang sudah login,
 * tanpa perlu menu apa pun (dashboard, logout, dsb).
 *
 * Administrator SELALU lolos tanpa dicek daftar ini (lihat middleware) -
 * supaya admin tidak pernah terkunci sendiri akibat mapping yang kurang.
 */
return [
    'rules' => [
        'bahan-baku' => [1],
        'kartu-stok' => [1],
        // Stock Opname - sama level akses dengan bahan-baku/kartu-stok
        // (staff gudang yang menghitung fisik). Persetujuan hasil hitung
        // (baru benar-benar mengubah stok) tetap lewat /approval yang
        // sudah khusus Administrator - bukan di sini.
        'stock-opname' => [1, 53],

        // 'penjualan/riwayat' & 'penjualan/edit-nota' sengaja HANYA [3]
        // (bukan ikut [41]) - role yang cuma dikasih id_menu 41 ("input
        // order saja", mis. Operator Desain) harus tetap 403 di sini,
        // walau base 'penjualan' di bawah dia dapat akses (untuk
        // create/store/show - show WAJIB tetap terbuka karena itu halaman
        // redirect sukses setelah submit order baru). Route mutasi yang
        // path-nya '/{id}/aksi' (batalkan-nota, request-edit) tidak bisa
        // dipisah lewat prefix di sini sama sekali (id ada SEBELUM segmen
        // pembeda) - itu digembok lewat middleware restrict.input-only
        // langsung di route-nya (lihat routes/web.php).
        'penjualan/riwayat' => [3, 61],
        'penjualan/edit-nota' => [3],
        'penjualan' => [3, 41],
        // Penjualan Barang HANYA Kasir (3) - beda dari Input Order biasa
        // yang juga dibuka untuk Operator Desain (41), jual bahan baku
        // langsung bukan bagian dari alur kerja desainer.
        'penjualan-barang' => [3, 48],
        // Quotation: kasir (3) & operator input order (41), sama seperti Input Order.
        'quotation' => [3, 41, 58],
        // 'api/penjualan/produk/{id}' dipanggil AJAX oleh halaman Kasir
        // Utama sendiri (ambil detail harga produk saat dipilih) - WAJIB
        // ikut [41], kalau tidak pemilihan produk gagal total untuk role
        // yang cuma dikasih akses input-order (ditemukan langsung lewat
        // uji coba nyata: "Gagal ambil produk", 403).
        'api/penjualan' => [3, 41],
        'konsumen/store-ajax' => [3, 41],
        'konsumen/search-ajax' => [3, 41],
        'konsumen/cek-nama' => [3, 41, 7],

        'setting-akses' => [4],
        'menu-editor' => [4],
        'pengaturan-jam-kerja' => [37, 63],
        'karyawan' => [5],
        'users' => [6],
        'konsumen' => [7],
        'golongan' => [8, 12, 18],
        // Tambah supplier cepat dari form Kedatangan (17) / PO & Pembelian (20) - sama pola dengan konsumen/store-ajax.
        'supplier/store-ajax' => [9, 17, 20],
        'supplier' => [9],
        'platform-belanja' => [9, 62],
        'satuan' => [10],
        'bank' => [11],
        'produk' => [13],
        'setting-harga' => [14],
        // Route tier harga per-baris memakai segmen "xxx-harga/{id}" (bukan
        // "xxx/{id}/..."), jadi TIDAK tertangkap prefix induknya - tanpa
        // baris ini PATCH/DELETE-nya jatuh ke fail-closed 403 untuk semua
        // role non-Administrator (akar bug "Jasa tidak bisa diedit/disimpan").
        'setting-harga-tier' => [14],
        'finishing-harga' => [21],
        'jasa-harga' => [32],
        'harga-siap-cetak' => [14],
        'kategori' => [15],

        'kasir' => [16],
        // Pengaturan Aplikasi (identitas toko, logo, nota) bisa diberikan ke peran lain lewat menu 37.
        'pengaturan-aplikasi' => [37],
        // Cetak ulang nota (baca saja) dari Riwayat/Detail Penjualan dan dari Penjualan dan Jasa.
        // JANGAN pernah menambahkan id_menu 61 ("Riwayat Nota") ke daftar ini.
        // 61 dipakai supaya role bisa MELIHAT daftar nota, dan role itu tidak
        // otomatis berhak mencetak nota - Operator Desain punya 61 (butuh untuk
        // membaca riwayat order) tapi tidak boleh mencetak ulang nota yang memuat
        // nilai. Ikut-ikutan seperti ini muncul karena dua hal ini digabung dalam
        // satu daftar id_menu, padahal itu dua izin berbeda. Role yang memang
        // boleh mencetak nota diberi menu Kasir (16) atau Transaksi Baru (3).
        'kasir/print-opsi' => [3, 16, 48],

        'kedatangan-bahan' => [17],
        'ajax/bahan' => [17],

        'mesin' => [27],

        'pembelian-bahan' => [20],
        // Purchase Request boleh dibuat staf gudang (menu bahan-baku [1])
        // maupun pembelian [20] - alur di dokumen audit: gudang meminta,
        // owner menyetujui lewat /approval (admin-only), baru pembelian
        // membuat PO. Purchase Order sendiri tetap khusus pembelian [20].
        'purchase-request' => [1, 20, 54],
        'purchase-order' => [20, 55],
        'finishing' => [21],
        'spk' => [22],
        // SPK Desain (lengkap): operator desain / input order, tanpa perlu menu SPK produksi.
        'spk-desain' => [3, 22, 23, 41, 64],
        'produksi' => [23],
        // QC & Rework (LEVEL 2): operator produksi/desain yang punya menu produksi.
        'qc' => [23, 56],
        'berkas-order' => [3, 23, 41],
        // Waste Management: staf gudang (bahan-baku [1]) atau produksi [23].
        'waste' => [1, 23, 57],
        'collecting' => [24],
        'laporan/order' => [25],
        'laporan/produksi' => [26],
        'laporan/kas' => [29],
        'laporan/belanja-bahan' => [40],
        'laporan/hutang' => [40],
        'laporan/order-jersey' => [43],
        'laporan/piutang' => [44],
        'laporan/stok-bahan' => [46],
        'laporan/hpp' => [47],
        'laporan/biaya-desain' => [65],
        'postingan' => [67],
        'pengaturan-desainer' => [66],
        'laporan/job-costing' => [47],
        'laporan/profitabilitas' => [47],
        'laporan/pemakaian-bahan' => [46],
        'laporan/biaya-tinta' => [47],
        'laporan/evaluasi-master' => [47],
        // Dashboard Eksekutif: khusus Administrator (owner) - berisi angka laba & kas.
        'eksekutif' => [60],
        // Audit Bulanan Owner (baca-saja) - role Owner (menu 60) & Administrator.
        'audit-owner' => [60],
        'harga-promo' => [28],

        'kas-kecil' => [39],
        'kategori-kas-kecil' => [45],

        'merk' => [30],
        'jenis-bahan' => [31],
        'jasa' => [32],
        // Jasa Jahit Borongan (menus.id_menu 68). Tanpa baris ini seluruh
        // /jasa-jahit/* jatuh ke fail-closed 403 untuk role non-Administrator
        // (tambah/ubah/simpan ditolak) walau menunya sudah dicentang.
        'jasa-jahit' => [68],
        'gudang' => [33],
        'konsumen-deposit' => [34],

        'akun' => [35],
        'akuntansi' => [36],
        // Log Aktivitas (Audit Trail) - khusus Administrator, tidak ada
        // id_menu lain yang diberi akses (Administrator selalu lolos
        // CheckMenuAccess tanpa perlu masuk daftar ini sama sekali, jadi
        // baris ini murni jaring pengaman kalau suatu saat perlu dibuka
        // untuk role lain juga).
        'log-aktivitas' => [60, 49],
        'approval' => [50],
        'accounting-period' => [51],
        'backup' => [52],

        // Sama pola dengan 'penjualan' di atas: 'jersey/master' &
        // 'jersey/keuangan' HANYA [38] (bukan ikut [42]). Route mutasi
        // yang path-nya '/{order}/aksi' (update, destroy, edit-lines,
        // update-lines, lines/susulan) tidak bisa dipisah lewat prefix
        // (order id ada SEBELUM segmen pembeda) - digembok lewat
        // middleware restrict.input-only langsung di route-nya.
        'jersey/master' => [38],
        'jersey/keuangan' => [38],
        'jersey/create' => [38, 42],
        'jersey/store' => [38, 42],
        'jersey/cek-kelengkapan' => [38, 42],
        'jersey/preview-harga' => [38, 42],
        'jersey/upload-thumbnail' => [38, 42],
        // Base 'jersey' menaungi GET / (daftar order - index) DAN
        // GET/{order} (show, dibutuhkan sebagai halaman redirect sukses
        // setelah submit order baru) - role id_menu 42 saja otomatis bisa
        // juga membuka index & show barang orang lain (kompromi yang
        // disadari, lihat catatan di RestrictInputOnlyRoles), tapi TIDAK
        // bisa update/hapus/edit-lines/susulan karena route itu digembok
        // middleware terpisah di atas.
        'jersey' => [38, 42],
    ],

    // Halaman yang memuat harga/nilai uang - ditutup (403) untuk role dengan boleh_lihat_harga = 0.
    // Pola: awalan path, atau wildcard fnmatch.
    'khusus_harga' => [
        'finishing',
        'finishing-harga',
        'quotation',
        'harga-siap-cetak',
        'konsumen/*/timeline',
    ],

    // Role yang HANYA boleh membaca: semua request selain GET/HEAD ditolak (403) di CheckMenuAccess.
    'roles_baca_saja' => ['Owner'],

    // Role yang HANYA boleh melihat pada modul (id menu) berikut - tambah/kelola/hapus ditolak walau hak aksesnya dicentang.
    // Operator Desain: modul produksi (SPK, Produksi, Collecting, QC, Waste) hanya untuk dipantau, tidak boleh mengganggu tim produksi.
    'hanya_lihat' => [
        'Operator Desain' => [22, 23, 24, 56, 57, 1, 65],
    ],

    // POST yang hanya membaca/menghitung (bukan menyimpan) - diperlakukan sebagai "lihat".
    'post_baca' => [
        'jersey/cek-kelengkapan',
        'jersey/preview-harga',
    ],

    'always_allow' => [
        'sinyal',
        'dashboard',
        'logout',
        'storage',
        'up',
        // Ganti foto profil sendiri (self-service): controller hanya pernah
        // menulis ke users.foto_profil milik pengguna yang sedang login, jadi
        // tidak ada alasan mengunci endpoint ini ke menu tertentu. Sebelumnya
        // "Rute ini belum terdaftar di peta akses" (403) untuk non-Administrator.
        'profil',
        // Halaman Premium (showcase + fitur non-paywall) terbuka untuk
        // semua role yang login. Ini masih gratis-penggunaan; saat fitur
        // premium di-lock nanti, kontrolnya pindah ke middleware premium:*
        // - bukan ke sini.
        'premium',
    ],
];
