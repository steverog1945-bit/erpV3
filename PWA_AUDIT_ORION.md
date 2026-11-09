# PWA AUDIT — ORION GRAFIKA

Tanggal audit: 22 Oktober 2026 · Mode: READ-ONLY (tidak ada berkas, database, atau konfigurasi operasional yang diubah)
Penguji: Desktop Chrome (localhost, sesi login Administrator nyata) + Lighthouse + HTTP CLI + pemeriksaan kode.
Perangkat Android/iOS fisik: tidak tersedia pada sesi ini, sehingga semua hasil mereka ditandai NOT TESTED.

Legenda status: PASS (terbukti dari uji nyata) · FAIL (terbukti bermasalah) · BLOCKED (terhalang dari luar cakupan) · NOT TESTED (alat/perangkat tidak tersedia) · SEMI (sebagian terbukti diuji, sisanya bukti kode).

---

## 1. RINGKASAN EKSEKUTIF

| Area | Status | Catatan inti |
|---|---|---|
| Manifest PWA | PASS | 200 `application/manifest+json`, field lengkap, 4 ikon semua 200, start_url/scope mengikuti host perangkat |
| Service worker | PASS (dengan temuan P1) | Terdaftar dan aktif, scope benar, kebijakan cache benar; tetapi cache memegang salinan gambar desain pelanggan (temuan P1-1) |
| HTTPS LAN | BLOCKED | Aplikasi HTTP murni (`APP_URL=http://192.168.1.17`). Chrome Android menolak memasang PWA tanpa HTTPS |
| Android Chrome | NOT TESTED (perangkat) | Kesimpulan dari spesifikasi Chrome: instalasi pasti gagal selama masih HTTP |
| iPhone Safari | NOT TESTED (perangkat) | `apple-touch-icon` sudah siap; Add to Home Screen bisa tanpa HTTPS, tetapi tanpa service worker (tidak ada offline) |
| Desktop Chrome | SEMI PASS | SW aktif dan manifest sahih diverifikasi; menu Install Chrome tidak bisa diklik dari harness |
| Peran (RBAC UI) | SEMI PASS | Administrator diuji nyata di peramban; lima peran lain NOT TESTED (tidak ada kredensial), middleware-nya terbukti di kode |
| Keamanan dan integritas | PASS (dengan temuan P1/P2) | RBAC berlapis, login throttle, throttled write, idempotensi submit, transaksi berlock; cache SW bersih dari HTML/JSON |
| UX mobile | SEMI PASS | Lighthouse a11y 0,85 dan best-practices 1,0; enam temuan a11y; tanpa indikator koneksi di layar |

Kesimpulan satu kalimat: aplikasi ini PWA yang dipasang dengan benar pada lapisan kode, tetapi rencana digunakan semua karyawan lewat LAN berhenti di satu keputusan: tanpa HTTPS, HP Android tidak akan pernah bisa memasang PWA ini.

---

## 2. BUKTI KONFIGURASI

### 2.1 Manifest

- Dilayani lewat route, bukan berkas statis: `routes/web.php:73` menuju `PwaController::manifest()`.
- Uji CLI nyata: `GET /grafikaorion/manifest.webmanifest` menghasilkan 200 dengan tipe `application/manifest+json`.
- Uji isi aktual (data persis dari peramban):
  - `name: ORION GRAFIKA`, `short_name: ORION`, `lang: id`, `dir: ltr` — PASS.
  - `display: standalone`, `orientation: portrait-primary` — PASS.
  - `background_color` dan `theme_color` bernilai `#1A231F`, sama dengan `<meta name=theme-color>` di halaman — PASS.
  - `start_url` menuju `http://localhost/grafikaorion/dashboard` dan `scope` menuju `http://localhost/grafikaorion` (mengikuti host perangkat; di HP otomatis menjadi `http://192.168.1.17/grafikaorion/...`) — PASS.
  - `icons`: `icon-192.png`, `icon-512.png`, `icon-maskable-512.png` (purpose maskable), `apple-touch-icon.png`. Keempat berkas ada di `public/icons/` (9-38 KB) dan semuanya HTTP 200 saat di-HEAD dari peramban — PASS.
- Catatan manifest satu-satunya: `start_url` menunjuk `/dashboard` yang mewajibkan login, jadi buka pertama selalu dialihkan ke `/masuk`. Chrome memvalidasi host dan scope (bukan status), jadi ini tidak merusak pemasangan. Nilai P3.

### 2.2 Service worker

- Registrasi pada kedua layout (privat dan publik): `resources/views/layouts/app.blade.php:30` dan `resources/views/publik/layout-masuk.blade.php:43`.
- Terbukti dari halaman hidup: `getRegistrations()` mengembalikan 1 registrasi, state `activated`, scope `http://localhost/grafikaorion/` (persis prefix aplikasi). Header `Service-Worker-Allowed: /` ada pada respons sw.js (`PwaController.php:148`).
- Kebijakan cache (sumber `PwaController.php:102-139`, dibaca penuh):
  - Navigasi HTML: network-first, offline jatuh ke `offline.html`. PASS (halaman data tidak pernah dilayani basi).
  - `POST/PATCH/PUT` diabaikan SW dan dipaksa ke jaringan. PASS (tidak ada antrean submit offline tersembunyi).
  - Aset statis: cache-first. Isi cache hidup mengonfirmasinya: 27 entri, semuanya aset statis di `public/` ditambah `offline.html`; aset build memakai nama hashing (`app-BPr-F1EB.css`, `app-ff41jNsz.js`).
- Temuan pada isi cache hidup (bukti langsung dari peramban, 27 entri):
  - `public/storage/order_thumbnails/*.jpg|png` — empat berkas gambar desain pesanan pelanggan tersimpan di cache terpasang (temuan P1-1).
  - `storage/company_logo/*`, `storage/posts/sampul/*`, `landing-media/*` — aset halaman yang wajar.
- `offline.html` dibaca penuh: halaman ringan, tanpa data privat, tombol Coba Lagi — PASS.
- Pembaruan cache: kunci cache bernilai `'orion-pwa-' . config('app.version','v1')`, sementara `config('app.version')` NULL, sehingga kunci di lapangan selalu `orion-pwa-v1` dan tidak pernah berganti. Header `Cache-Control: no-cache` pada sw.js cukup untuk menarik SW baru, tetapi cache aset lama hanya ikut terhapus bila kunci berubah (pembersihan hanya terjadi di event activate). Nilai P2-2.

### 2.3 Vite, base URL, mixed content

- `vite.config.js` standar (laravel-vite-plugin + tailwindcss), dipanggil di layout: `@vite(['resources/css/app.css','resources/js/app.js'])` (`layouts/app.blade.php:47`). Aset hasil build dengan hash terbukti ada di isi cache SW (`build/assets/*`). SEMI PASS: tidak ada uji `npm.cmd run build` selama audit karena itu akan menulis ulang berkas produksi (di luar batas read-only).
- Aplikasi dilayani dari folder proyek (bukan docroot langsung); manifest dan sw.js sengaja dilayani lewat route karena alasan itulah `URL` di dalamnya ikut menyesuaikan bila aplikasi pindah (`PwaController.php:8-12`). PASS.
- Mixed content untuk masa depan HTTPS: tidak ada URL `http://` yang hardcode di layouts dan blade publik (greep: nol hasil). URL ikon dan manifest memakai `asset()`/relative, aman saat naik HTTPS. PASS.

### 2.4 HTTPS LAN (temuan terbesar)

- `APP_URL = http://192.168.1.17` (dikonfirmasi lewat konfigurasi; `.env` tidak diubah).
- Server: `netstat` menunjukkan Apache mendengarkan `0.0.0.0:80` (IPv4 dan IPv6, PID 3784) — layanan siap menerima klien LAN.
- Dari server sendiri, `http://192.168.1.17/grafikaorion/masuk` menghasilkan 200, jadi alamat LAN merespons dengan baik.
- Spesifikasi Chrome: pemasangan PWA dan registrasi SW membutuhkan secure context; koneksi `http://<ip-LAN>` BUKAN secure context (pembebasan hanya untuk `localhost`). Konsekuensinya, belum terverifikasi perangkat karena perangkat tidak tersedia:
  - `navigator.serviceWorker` di HP Chrome tidak ada, sehingga SW gagal registrasi.
  - Banner atau tombol Install tidak pernah muncul.
  - `offline.html` tidak pernah dilayani di HP.
- Firewall Windows: enumerasi `netsh` menampilkan banyak aturan aplikasi aktif; tidak ada aturan blok eksplisit untuk port 80, dan koneksi dari IP LAN sendiri sukses. Akses dari perangkat lain: NOT TESTED.
- Sertifikat: tidak ada HTTPS apa pun di Apache (HTTP murni), sehingga target pemasangan PWA di HP karyawan terhenti.

Jalan keluar minimum (rekomendasi, dilakukan hanya dengan persetujuan):

1. mkcert di server XAMPP: buat CA lokal, sertifikat untuk `192.168.1.17`, aktifkan HTTPS:443 di Apache, lalu pasang CA mkcert ke tiap perangkat karyawan. Ini adalah jalur yang membuat Chrome Android menganggap PWA installable di LAN.
2. Alternatif: domain internal plus sertifikat korporat yang dikelola perangkat (lebih berat).
3. iOS: tanpa HTTPS tetap bisa Add to Home Screen berserta standalone, tetapi tanpa SW (tidak ada offline). iOS juga butuh CA lokal untuk HTTPS.

### 2.5 Sesi, CSRF, cookie, cache privat

| Pemeriksaan | Hasil | Bukti |
|---|---|---|
| Cookie sesi `http_only` | `true`; JS tidak bisa membacanya | `document.cookie` dari halaman hidup hanya memuat `XSRF-TOKEN` (perilaku standar Laravel untuk header AJAX); cookie sesi tidak muncul |
| `session.same_site` | `lax` | sesuai LAN same-origin |
| `session.secure` | NULL | siapkan `true` saat HTTPS aktif (P2-1; kabar .env, butuh persetujuan) |
| `session.lifetime` | 120 menit | standar; pertimbangkan perpanjangan bila kasir sering putus koneksi |
| Cache header halaman privat | `no-cache, private` (diambil dari halaman kasir hidup) | proksi dan bfcache tidak menyimpan HTML |
| CSRF | middleware web standar Laravel aktif; form mengirim token, AJAX membaca `XSRF-TOKEN`; Apache meneruskan header `X-XSRF-TOKEN` (`public/.htaccess:32-34`) | PASS |
| Logout | `POST /logout` standar; SW tidak menyimpan POST; navigasi network-first | setelah logout, salinan HTML tidak dilayani ulang; kecuali kasus P1-1 (gambar desain di cache) |
| `GET /login` menghasilkan 405 | memang hanya `POST /login` yang didefinisikan; halaman login ada di `/masuk` (`routes/web.php:58-67`) | P3 kosmetik; tidak menghalangi siapa pun |

---

## 3. HASIL PERANGKAT

| Perangkat | Status | Bukti |
|---|---|---|
| Android Chrome asli | NOT TESTED | Tidak ada perangkat di sesi ini. Kesimpulan spesifikasi (instalasi gagal pada HTTP LAN) tertulis di bagian 2.4 |
| iPhone Safari asli | NOT TESTED | Perangkat tidak tersedia. `apple-touch-icon.png` 180x180 ada, Add to Home Screen siap |
| Desktop Windows Chrome | SEMI PASS | SW `activated`, manifest sahih, ikon 200; display-mode di tab browser adalah browser (bukan standalone) karena belum dipasang; klik menu Install tidak bisa dieksekusi oleh harness |

---

## 4. HASIL PER ROLE DAN MODUL

Sesi uji: Administrator (Admin Utama), halaman hidup `penjualan/kasir`. Sidebar merender penuh: 45 menu dengan grup Produksi dan Logistik (9), Relasi Bisnis (1), Keuangan dan SDM, Laporan (35), Akuntansi, Pesanan Jersey, Pengaturan Sistem, dilengkapi pencarian menu (Ctrl K).

| Role | Status | Metode |
|---|---|---|
| Owner (dashboard dan laporan) | NOT TESTED (UI asli) | tidak ada kredensial; mekanisme `menu.access` dan `config/menu_access.php` (peta role ke menu, `roles_baca_saja`, `post_baca`) terbukti di kode, tapi UI per role tidak diuji |
| Kasir (pelanggan, order, keranjang, pembayaran, nota) | SEMI PASS | halaman kasir dan keranjang diuji nyata, tetapi dengan sesi Administrator; uji sebagai Kasir sungguhan NOT TESTED |
| Desain (pekerjaan/SPK) | NOT TESTED (UI) | menu SPK dan Produksi ada; uji dengan role tersebut tidak dilakukan |
| Operator (antrean dan produksi) | NOT TESTED (UI) | sama |
| Collecting (pengambilan) | NOT TESTED (UI) | sama |
| Admin (navigasi dan monitoring) | PASS | sesi nyata: 45 menu, monitoring kasir, audit bulanan; halaman kasir hidup dan keranjang bekerja |

Modul yang terverifikasi di layar kasir: Select2 konsumen (AJAX `konsumen.search-ajax`), Select2 produk dengan matcher kategori, badge angka tetap satu baris (`app.blade.php:340`), sidebar slide-over penuh (`app.blade.php:158`).

### Layar 390 / 430 / 768 px

- Harness browser tidak menyediakan emulasi viewport, sehingga pengujian lebar nyata tidak bisa dijalankan. Yang diperoleh: Lighthouse dengan profil seluler (CPU ter-throttle, profil perangkat seluler) yang hasilnya tersimpan di `lighthouse.html`.
- Kode: breakpoint Tailwind dipakai; komentar `app.blade.php:158/340/389` menunjukkan layout mobile diseriusi (slide-over sidebar, badge satu baris, perbaikan scroll sidebar).
- Status: NOT TESTED pada 390/430/768 secara nyata; tersedia bukti kode dan Lighthouse mobile.

---

## 5. KEAMANAN DAN INTEGRITAS

### 5.1 Pemeriksaan terbukti

- RBAC: `Route::middleware(['auth','menu.access','throttle.write'])` menutup seluruh grup aplikasi (`routes/web.php:119`). Alias `menu.access` terdaftar dan di-prepend ke grup web (`bootstrap/app.php:25,48`). Konfigurasi `menu_access.roles_baca_saja` dan `post_baca` memberi aturan baca-saja dan POST-baca. PASS.
- Rate limit: `throttle:login` pada `POST /login` (`web.php:67`); `throttle.write` menutup semua POST/PUT/PATCH/DELETE ber-auth (`web.php:116-119`). PASS.
- CSRF: token per halaman plus forwarding header `X-XSRF-TOKEN` via Apache. SW tidak menyentuh POST. PASS.
- XSS: `X-Content-Type-Options: nosniff` untuk `storage/` (`public/.htaccess:24`) dan CSP `default-src 'none'; script-src 'none'` untuk tipe aktif seperti html/svg/js (`public/.htaccess:25`). Skrip PHP di jalur `storage/` dikarantina keras (`public/.htaccess:16`). PASS.
- Berkas privat: uji CLI tanpa login pada `GET /public/storage/order_thumbnails/{berkas}.jpg` menghasilkan 302 ke halaman login, bukan isi gambar. `public/.htaccess:19` meneruskan folder privat ke `BerkasPrivatController` ber-middleware auth (`routes/web.php:110`). PASS. Catatan keras: angka 200 dari uji pertama ternyata hanya Invoke-WebRequest mengikuti redirect ke halaman login; issukannya bukan bocor.
- Idempotensi submit dan koneksi putus:
  - Middleware global `SubmitSekaliJalan` tergantung di grup web (`bootstrap/app.php:21-22`): `submit_id` hanya diproses SEKALI, jadi kirim ulang setelah jaringan putus tidak menambah nota kedua.
  - Klien kasir: `submitIdNota = crypto.randomUUID()` bendera `sedangMenyimpan` pada `create.blade.php:3094-3097,3119` — guard klik dobel nyata (terbukti di halaman hidup: variabel ada).
  - Race condition: `DB::transaction` plus `lockForUpdate()` untuk pelanggan, transaksi, batalkan nota, approve/reject (`PenjualanController.php:578,881,1065,2189-2193,2292-2296`; komentar anti dobel 2409-2413). PASS.
  - Tidak ada antrean pembayaran atau order offline: isi cache SW membuktikan nol antrean; kebijakan API network-only tertulis (`PwaController.php:138`). PASS.
- Database uji: seluruh CLI dan suite uji berjalan pada database testing terisolasi; `PwaTest` 4 lulus (35 assertions). oriondb tidak disentuh. PASS.

### 5.2 Temuan utama

P1-1 — Cache SW memegang gambar desain pelanggan
- Lokasi: `PwaController.php:102-105`. `isAsetStatis()` menampung `.png/.jpg/webp` untuk semua URL, termasuk `storage/order_thumbnails/` dan folder bukti bayar.
- Bukti hidup: empat berkas `order_thumbnails/*` menempel di cache terpasang; `fetch(url)` dari halaman menghasilkan 200 lewat cache-first.
- Risiko: perangkat kerja dipakai bergantian. Setelah logout, salinan gambar desain pelanggan tertinggal di memori SW selama cache `orion-pwa-v1` tidak dibuang. Halaman pasca-logout tidak menampilkannya (HTML privat tidak di-cache), tetapi salinan lokal tetap ada di perangkat tanpa membaca izin akun mana pun.
- Rekomendasi patch SW (kecil dan aman, tunggu persetujuan): larang cache untuk URL prefix `/storage/` di `isAsetStatis`, lalu ganti kunci cache ke `orion-pwa-v2` supaya salinan lama ikut dihapus saat activate.
- Status: TEMUAN; belum diubah sesuai batas read-only.

P1-2 — Tanpa HTTPS, Chrome Android tidak pernah memasang PWA
- Lihat bagian 2.4. Ini jalan pintas semua rekomendasi: PK diplomasi mkcert dulu.

P2 — temuan sedang
- P2-1: `session.secure` masih NULL. Set `true` saat HTTPS naik (butuh persetujuan karena menyentuh `.env`).
- P2-2: kunci cache SW tidak pernah berganti (`app.version` NULL), sehingga prasya cache aset tidak pernah dihanguskan otomatis. Bump kunci ke `orion-pwa-v2` sekaligus menutup P1-1.
- P2-3: enam temuan Lighthouse: kontras warna, heading-order, label input dan select, accessible-name mismatch, meta description. Akses melalui `$impeccable clarify` plus satu pass a11y pada layout dan blade.

P3 — temuan ringan
- P3-1: tidak ada indikator koneksi di layar aplikasi; pengguna baru sadar offline setelah aksi gagal. Tambahkan badge Offline kecil di header.
- P3-2: `GET /login` menghasilkan 405 dan start_url memakai `/dashboard` (redirect ke `/masuk` setiap buka pertama). Bisa dibereskan dengan mengganti start_url ke `/masuk`.

---

## 6. UX DAN PERFORMA

- Lighthouse (emulasi seluler): Accessibility 0,85, Best Practices 1,0, SEO 0,8; tidak ada masalah best-practices. Performance tidak dikeluarkan Lighthouse pada run ini, sehingga tidak diklaim.
- Input numerik dan telepon sudah memakai `inputmode` yang tepat (`jasa-jahit/index.blade.php:298,318,351`, `finishing/index.blade.php:181` dan lainnya), sehingga keyboard HP tampil numeric/tel bila diuji nyata.
-_File picker/kamera_: tidak diuji pada perangkat (NOT TESTED); jalur unggahan desain sudah teruji pada mesin desktop di sesi audit ini.
- Offline state: `offline.html` ringan (tanpa aset eksternal) dengan tombol Coba Lagi.
- Konsistensi visual: warna tema satu pendapat dari manifest, meta tag, latar offline, hingga latar aplikasi (`#1A231F`). PASS.
- Tidak ada redesign yang dilakukan dalam sesi ini; ini audit satu-lewatan.

Audit Health Score (kelima dimensi audit terstandar):

| # | Dimensi | Skor | Catatan |
|---|---|---|---|
| 1 | Accessibility | 3 | enam temuan a11y ringan-sedang |
| 2 | Performance | 3 | aset sudah dibangun dan di-hash; tanpa uji throughput LAN nyata |
| 3 | Theming | 4 | satu sistem warna konsisten lintas lapisan |
| 4 | Responsive | 2 | kode mobile siap; pengujian layar nyata 390/430/768 belum terjadi |
| 5 | Implementation Integrity | 4 | satu sumber kebenaran (`PwaController`); komentar didasarkan kasus nyata |
| | Total | 16 dari 20 (Good: kerjakan dimensi lemah) | |

---

## 7. TINDAKAN MINIMAL (urutan pengerjaan, semua menunggu persetujuan)

1. [P0] mkcert: buat CA lokal dan sertifikat `192.168.1.17`, aktifkan HTTPS:443 pada Apache XAMPP, pasang CA ke HP karyawan; uji ulang pemasangan di Android Chrome dan iPhone Safari.
2. [P1] Patch SW: larang cache `/storage/` DI `isAsetStatis`, ganti kunci ke `orion-pwa-v2` di `PwaController.php` agar salinan desain pelanggan ikut terhapus dari perangkat.
3. [P2] Set `SESSION_SECURE=true` bersamaan naiknya HTTPS, lalu uji pasang logout-login.
4. [P2] Satu pass a11y (kontras, label, accessible name) pada blade utama sesuai keluaran Lighthouse.
5. [P3] Tambah indikator offline; pertimbangkan start_url ke `/masuk`.
6. Uji perangkat nyata Android Chrome dan iPhone Safari pada 390/430/768: pasang, buang pasang, tukar user, putus Wi-Fi saat submit, pulihkan koneksi, cek tidak ada nota ganda. NOT TESTED saat ini; perangkat mengikuti begitu HTTPS siap.

Catatan batas: seluruh pemeriksaan dilakukan read-only. Tidak ada berkas produksi, database, stok, RBAC, harga, SPK, atau `.env`/konfigurasi Apache yang diubah demi audit ini.
