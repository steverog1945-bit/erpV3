<?php

// API eksternal (Android/iOS/integrasi) belum siap dipakai operasional.
// Secara default OFF agar tidak mengekspos data dan jalur transaksi yang
// masih menunggu audit RBAC, rate limiting, dan kesetaraan checkout.
return [
    'enabled' => (bool) env('ORION_PUBLIC_API_ENABLED', false),

    /*
    | Gate khusus CHECKOUT API (Api\TransaksiController::store) - TERPISAH
    | dari 'enabled' di atas (yang menggerakkan gate baca ApiPreparationGate):
    | bahkan saat ORION_PUBLIC_API_ENABLED sudah dibuka, POST /api/transaksi
    | tetap 503 fail-closed sampai flag ini pula persilakan. Default OFF.
    */
    'checkout' => [
        'enabled' => (bool) env('ORION_API_CHECKOUT_ENABLED', false),
    ],

    /*
    | Idempotensi X-Submit-Id (tabel api_checkout_idempotensi, bukan cache):
    | tunggu_detik  = masa permintaan lain menunggu hasil checkout in-flight
    |                 sebelum tunduk dgn 409 "masih diproses".
    | tua_detik     = row 'diproses' lebih tua dari ini diasumsikan klaim
    |                 orphan (runner mati) dan boleh direbut ulang.
    */
    'idempotensi_tunggu_detik' => 10,
    'idempotensi_tua_detik' => 300,

    /*
    | API-5: masa berlaku token Bearer dalam jam. Rotasi via POST
    | /api/auth/token selalu menghasilkan kedaluwarsa baru dicacah dari
    | "sekarang" di sini; kedaluwarsa kadaluwarsa/NULL => 401 fail-closed.
    */
    'token_masa_aktif_jam' => (int) env('ORION_API_TOKEN_MASA_AKTIF_JAM', 24),


    /*
    | Peta RBAC endpoint API: prefix path (TANPA 'api/') => daftar id_menu
    | yang boleh mengakses. Sumber kebenarannya SAMA dengan web: tabel
    | role_permissions/user_weights lewat App\Support\Izin::boleh(), jadi
    | satu hak akses berlaku konsisten di web dan di API - tidak ada sistem
    | izin kedua yang bisa saling mengalahkan.
    |
    | Nilai biasa  = daftar id_menu (semua metode HTTP).
    | Nilai peta   = ['GET' => [...], 'POST' => [...]] bila hak lihat dan hak
    |                tulis terpisah (mirip pemisahan 'penjualan' vs
    |                'penjualan/riwayat' di config/menu_access.php).
    |
    | Endpoint yang BELUM ada di daftar ini ditolak 403 (fail-closed) untuk
    | semua role non-Administrator - endpoint baru tidak bocor begitu saja
    | sebelum dipetakan. Aksi diambil dari metode HTTP: GET/view, POST/add,
    | PUT-PATCH/edit, DELETE/delete (Izin::aksiDariMetode).
    */
    // Rute dinamis yang haknya berbeda dari prefix induk.
    'route_menus' => [
        'api.transaksi.pembayaran' => ['GET' => [16]],
    ],

    'menus' => [
        // Master data untuk integrasi kasir/input order - selaras dengan
        // 'api/penjualan' dan 'konsumen/store-ajax' => [3, 41] di web:
        // pemegang Transaksi Baru (3) / Input Order (41) boleh membaca
        // daftar produk & konsumen, belum berhak menu master (13/7).
        'konsumen' => [3, 41],
        'produk' => [3, 41],

        // Nota: controller index/show/store ter-scope id_user_kasir
        // (anti-IDOR), jadi role di sini hanya melihat nota miliknya.
        'transaksi' => [
            // Lihat nota ≈ 'penjualan/riwayat' => [3, 61] di web.
            'GET' => [3, 61],
            // Buat nota ≈ create/store 'penjualan' => [3, 41] di web.
            'POST' => [3, 41],
        ],

        // Ledger pembayaran bagian modul kasir ('kasir' => [16]).
        'pembayaran' => [16],

        // Antrean produksi ('spk' => [22]).
        'spk' => [22],

        // Stok bahan: gudang [1] / laporan stok [46].
        'stok' => [1, 46],

        // Laporan keuangan - selaras web tanpa pelonggaran apa pun.
        'laporan/penjualan' => [25],
        'laporan/piutang' => [44],
        'laporan/kas' => [29],

        // Angka agregat keuangan (omzet, kas, piutang dalam dashboard)
        // setara Dashboard Eksekutif => [60]: khusus Administrator/owner
        // sampai owner secara eksplisit memberi menu 60 ke role lain.
        'dashboard/kpi' => [60],
    ],
];
