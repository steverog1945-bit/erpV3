# ORION GRAFIKA — MOBILE POS PHASE 2

## Tujuan dan status

Meningkatkan keterbacaan POS dan pembayaran pada viewport mobile tanpa mengubah alur transaksi. **Status: Selesai untuk implementasi UI dan validasi aman.** PR #15 masih Draft dan belum memiliki keputusan review; gate tersebut belum dinyatakan terpenuhi.

## Branch dan base

- Branch: `feat/mobile-pos-phase2`
- Base: `origin/main` (`aee1d5d01b0de3c3c6fd6a0fe33545e6f6b082f6`)
- Draft PR: [#18](https://github.com/steverog1945-bit/percetakan-app/pull/18), base `main`.
- Commit implementasi awal: `cd40b5685ed2e95cefbf3aaabcbac6cf882b420a`.

## Implementasi

- `resources/css/mobile-pos.css`: styling khusus sampai 767.98px untuk header POS, tata letak input/keranjang, batas scroll daftar keranjang, total tabular yang menonjol, dan target sentuh. Seluruh warna tetap berasal dari CSS existing; aturan desktop tidak ditambahkan.
- `resources/css/mobile-ui.css`: memuat stylesheet POS setelah fondasi mobile.
- `resources/views/penjualan/create.blade.php`: menambahkan class scope presentasional pada halaman, form, dan keranjang.
- `resources/views/kasir/form_pembayaran.blade.php`: menambahkan class scope presentasional untuk layar pembayaran.

ID, nama field, nilai, izin, route, form action, kalkulasi, handler, dan data bisnis tidak diubah.

## Validasi

- **PASS** — instalasi dependensi terkunci dengan `npm ci --ignore-scripts --no-audit --no-fund`.
- **PASS** — `npm run build` (Vite).
- **PASS** — `git diff --check`.
- **PASS** — pemeriksaan PostCSS bahwa stylesheet POS hanya aktif di breakpoint mobile; selector POS/pembayaran hadir pada hasil build.
- **PASS** — preview browser statis pada 320, 360, 390, 430, 767, 768, 1024, dan 1280 px: tidak ada horizontal overflow; navigasi bawah fixed dan solid pada mobile, tersembunyi mulai 768 px; padding/ukuran total kembali ke nilai desktop.
- **PASS** — seluruh 11 berkas screenshot PNG valid dan memiliki lebar viewport yang diminta.
- **FAIL** — tidak ada pada validasi akhir.
- **NOT TESTED** — render Laravel/Blade asli, integrasi database, serta alur simpan/bayar. Dependency `vendor` tidak tersedia; runtime/aplikasi dan database tidak dijalankan.

## Bukti visual

Semua gambar berikut adalah **STATIC SOURCE-DERIVED PREVIEW**: struktur POS/pembayaran diturunkan dari Blade dan memakai CSS Vite hasil build. Ini bukan screenshot ERP/Laravel asli; nilai dinamis dan runtime tidak dimuat.
Sembilan screenshot utama juga dilampirkan sebagai GitHub-hosted assets pada deskripsi PR agar tidak bergantung pada localhost.

| Tampilan | Bukti |
|---|---|
| POS 320 px | [pos-320.png](assets/mobile-pos-phase2/pos-320.png) |
| POS 360 px | [pos-360.png](assets/mobile-pos-phase2/pos-360.png) |
| POS 390 px | [pos-390.png](assets/mobile-pos-phase2/pos-390.png) |
| POS 430 px | [pos-430.png](assets/mobile-pos-phase2/pos-430.png) |
| POS 767 px | [pos-767.png](assets/mobile-pos-phase2/pos-767.png) |
| POS 430 px — keranjang/total | [pos-430-cart.png](assets/mobile-pos-phase2/pos-430-cart.png) |
| POS desktop 1024 px | [pos-1024.png](assets/mobile-pos-phase2/pos-1024.png) |
| POS desktop 1280 px | [pos-1280.png](assets/mobile-pos-phase2/pos-1280.png) |
| Pembayaran 390 px | [payment-390.png](assets/mobile-pos-phase2/payment-390.png), [ringkasan](assets/mobile-pos-phase2/payment-390-summary.png) |
| Pembayaran desktop 1024 px | [payment-1024.png](assets/mobile-pos-phase2/payment-1024.png) |

## Risiko, keamanan, dan produksi

Perilaku dinamis Blade dan transaksi belum diuji di runtime, sehingga tetap perlu verifikasi aplikasi pada lingkungan uji sintetis. PR #15 masih Draft tanpa keputusan review; approval tidak diklaim. Tidak membaca `.env`, tidak mengakses database, dan tidak menyentuh folder/layanan produksi (**dampak produksi: ZERO**).

## Commit dan PR

- Commit implementasi: `cd40b5685ed2e95cefbf3aaabcbac6cf882b420a`.
- Draft PR: [#18](https://github.com/steverog1945-bit/percetakan-app/pull/18).
- Merge/deploy: tidak dilakukan.
