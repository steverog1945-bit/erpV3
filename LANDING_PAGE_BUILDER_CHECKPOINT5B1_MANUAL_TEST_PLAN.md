# LANDING PAGE BUILDER — CHECKPOINT 5B.1
## PANDUAN PENGUJIAN BROWSER MANUAL TERISOLASI

**Tanggal:** 2026-10-09
**Status:** `LANDING PAGE BUILDER — MANUAL BROWSER TEST PLAN READY`

---

## 1. KEBUTUHAN LINGKUNGAN PENGUJIAN

### 1.1 Database Terisolasi

| Properti | Nilai |
|----------|-------|
| Nama database | `landing_builder_e2e` |
| Engine | MariaDB 10.4.32 |
| Host | 127.0.0.1:3306 |
| Charset | utf8mb4 / utf8mb4_unicode_ci |
| Wajib berbeda dari | `oriondb`, `percetakan_erp_testing`, `oriondb_migration_sandbox` |

**Aturan:**
- Database ini HANYA untuk pengujian browser.
- Tidak boleh berisi data operasional.
- Tidak boleh disambungkan ke aplikasi produksi.
- Struktur tabel ERP dibuat sintetis (minimum untuk autentikasi).

### 1.2 Tabel Minimum untuk Autentikasi

Aplikasi memerlukan tabel berikut untuk login dan otorisasi:

| Tabel | Kebutuhan |
|-------|-----------|
| `users` | Akun Administrator testing |
| `roles` | Role "Administrator" + role lain untuk uji penolakan |
| `role_permissions` | Izin minimal untuk testing |

Tabel ERP lain (kasir, SPK, produksi, collecting) **tidak wajib** untuk pengujian editor.

### 1.3 File Environment Testing

Buat `.env.testing` (terpisah dari `.env` produksi):

```env
APP_ENV=testing
APP_DEBUG=true
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=landing_builder_e2e
DB_USERNAME=root
DB_PASSWORD=
LANDING_PAGE_BUILDER_ENABLED=false
```

**Aturan:**
- File ini TIDAK boleh disalin dari `.env` produksi.
- Password database diisi oleh operator.
- `LANDING_PAGE_BUILDER_ENABLED` tetap `false` selama pengujian editor.

---

## 2. CARA ISOLASI DARI ERP PRODUKSI

### 2.1 Daftar Larangan

| No | Larangan | Alasan |
|----|----------|--------|
| 1 | Mengakses `oriondb` | Database operasional |
| 2 | Membaca `.env` produksi | Melindungi kredensial |
| 3 | Menggunakan akun Administrator produksi | Melindungi data operasional |
| 4 | Menjalankan migration produksi | Risiko perubahan skema |
| 5 | Mengaktifkan feature flag produksi | Mengubah perilaku halaman publik |
| 6 | Mengubah modul ERP | Di luar ruang lingkup |

### 2.2 Verifikasi Isolasi

Sebelum memulai pengujian, operator memastikan:

```
[ ] Database testing bernama landing_builder_e2e (bukan oriondb)
[ ] File .env.testing menunjuk ke database testing
[ ] Tidak ada koneksi ke oriondb di konfigurasi testing
[ ] Aplikasi testing berjalan di port/URL terpisah dari produksi
```

---

## 3. CARA MEMBUAT AKUN TESTING

### 3.1 Melalui Tinker (Recommended)

Jalankan di terminal:

```bash
php artisan tinker --env=testing
```

Kode untuk membuat role dan akun:

```php
// Buat role Administrator
$adminRole = \App\Models\Role::firstOrCreate(
    ['nama_role' => 'Administrator']
);

// Buat role lain untuk uji penolakan
$kasirRole = \App\Models\Role::firstOrCreate(
    ['nama_role' => 'Kasir']
);
$operatorRole = \App\Models\Role::firstOrCreate(
    ['nama_role' => 'Operator']
);

// Buat akun Administrator testing
$admin = \App\Models\User::create([
    'nama' => 'Admin Testing',
    'email' => 'admin.testing@example.com',
    'password' => bcrypt('PASSWORD_DIBUAT_OPERATOR'),
    'id_role' => $adminRole->id_role,
]);

// Buat akun non-Administrator untuk uji penolakan
$kasir = \App\Models\User::create([
    'nama' => 'Kasir Testing',
    'email' => 'kasir.testing@example.com',
    'password' => bcrypt('PASSWORD_DIBUAT_OPERATOR'),
    'id_role' => $kasirRole->id_role,
]);
```

### 3.2 Persyaratan Akun

| Properti | Aturan |
|----------|--------|
| Hanya berlaku di environment testing | ✅ |
| Password dibuat oleh operator | ✅ |
| Password tidak ditulis di laporan | ✅ |
| Role Administrator sesuai aturan aplikasi | ✅ |
| Tidak menggunakan akun produksi | ✅ |
| Tidak mengubah users pada oriondb | ✅ |

### 3.3 Catatan Keamanan

- Password testing **tidak boleh sama** dengan password produksi.
- Password testing **tidak boleh ditulis** di dokumen ini atau laporan.
- Akun testing **hapus** setelah pengujian selesai.

---

## 4. CHECKLIST PENGUJIAN BROWSER (24 SKENARIO)

### 4.1 Cara Penggunaan

1. Buka Microsoft Edge.
2. Navigasi ke URL aplikasi testing.
3. Login sebagai Administrator testing.
4. Buka halaman Landing Page Builder.
5. Jalankan setiap skenario secara berurutan.
6. Isi kolom "Hasil Aktual" dan "PASS/FAIL".
7. Ambil screenshot untuk setiap skenario.

### 4.2 Template Tabel

| No | Skenario | Langkah | Hasil Diharapkan | Hasil Aktual | Status | Screenshot |
|----|----------|---------|------------------|--------------|--------|------------|
| 1 | Login Administrator | Buka /masuk, masukkan kredensi testing, klik Login | Berhasil login, redirect ke halaman beranda | | | |
| 2 | Buka Landing Page Builder | Klik menu "Landing Page Builder" atau navigasi ke /setting/landing-page-builder | Editor terbuka, canvas kosong, panel properti kosong | | | |
| 3 | Tambah section | Klik "Hero" di Component Library | Section baru muncul di canvas, panel properti menampilkan field | | | |
| 4 | Edit teks | Klik section di canvas, ketik judul baru di field "Judul" | Teks di canvas berubah mengikuti input | | | |
| 5 | Ubah warna | Pilih tab "Style", pilih warna teks dari dropdown | Warna teks di canvas berubah | | | |
| 6 | Ubah background | Pilih tab "Style", pilih background dari dropdown | Background section di canvas berubah | | | |
| 7 | Ubah padding | Pilih tab "Layout", ubah nilai padding atas/bawah | Padding section berubah | | | |
| 8 | Ubah margin | Pilih tab "Layout", ubah nilai margin atas/bawah | Margin section berubah | | | |
| 9 | Ubah tipografi | Pilih tab "Style", ubah ukuran font | Ukuran font di canvas berubah | | | |
| 10 | Ubah alignment | Pilih tab "Layout", pilih alignment (Kiri/Tengah/Kanan) | Alignment konten berubah | | | |
| 11 | Duplikasi section | Klik tombol "Gandakan" pada section | Section baru muncul dengan konten sama, ID berbeda | | | |
| 12 | Hapus section | Klik tombol "Hapus" pada section, konfirmasi | Section hilang dari canvas | | | |
| 13 | Ubah urutan | Klik tombol "Naik" atau "Turun" pada section | Urutan section berubah | | | |
| 14 | Sembunyikan section | Klik tombol "Sembunyikan" pada section | Section ditandai tersembunyi, label tombol berubah jadi "Tampilkan" | | | |
| 15 | Undo | Lakukan perubahan, lalu klik tombol "Undo" atau Ctrl+Z | Perubahan terakhir dibatalkan | | | |
| 16 | Redo | Setelah undo, klik tombol "Redo" atau Ctrl+Y | Perubahan yang di-undo diterapkan kembali | | | |
| 17 | Media picker | Klik "Pilih dari Media" pada field gambar | Modal media terbuka, daftar gambar tampil | | | |
| 18 | Preview desktop | Klik tombol "Desktop" di toolbar canvas | Canvas menampilkan lebar desktop (100%) | | | |
| 19 | Preview tablet | Klik tombol "Tablet" di toolbar canvas | Canvas menampilkan lebar tablet (768px) | | | |
| 20 | Preview mobile | Klik tombol "Mobile" di toolbar canvas | Canvas menampilkan lebar mobile (375px) | | | |
| 21 | Simpan draft | Klik tombol "Save Draft" | Indikator "Tersimpan" muncul, tidak ada error | | | |
| 22 | Refresh browser | Tekan F5 setelah menyimpan draft | Draft tetap tersimpan, section yang dibuat masih ada | | | |
| 23 | Validasi input | Masukkan nilai tidak valid (misal font size 9999) | Nilai di-clamp atau pesan error muncul | | | |
| 24 | Logout dan akses editor | Logout, lalu coba buka /setting/landing-page-builder | Redirect ke halaman login | | | |

### 4.3 Screenshot

Simpan screenshot dengan format nama:

```
screenshot_01_login.png
screenshot_02_buka_editor.png
screenshot_03_tambah_section.png
...
screenshot_24_logout.png
```

Lokasi penyimpanan: `screenshots/checkpoint5b/`

---

## 5. CHECKLIST KEAMANAN ADMINISTRATOR

### 5.1 Otorisasi

| No | Skenario | Langkah | Hasil Diharapkan | Hasil Aktual | Status |
|----|----------|---------|------------------|--------------|--------|
| 1 | Guest ditolak | Buka /setting/landing-page-builder tanpa login | Redirect ke /masuk | | |
| 2 | Role Kasir ditolak | Login sebagai Kasir, buka editor | Halaman 403 atau redirect | | |
| 3 | Role Operator ditolak | Login sebagai Operator, buka editor | Halaman 403 atau redirect | | |
| 4 | Administrator diterima | Login sebagai Administrator, buka editor | Editor terbuka normal | | |
| 5 | Endpoint API dilindungi | Kirim request PUT /setting/landing-page-builder/draft sebagai non-Admin | Response 403 | | |
| 6 | Media endpoint dilindungi | Kirim request POST /setting/landing-page-builder/media sebagai non-Admin | Response 403 | | |

### 5.2 Keamanan Konten

| No | Skenario | Langkah | Hasil Diharapkan | Hasil Aktual | Status |
|----|----------|---------|------------------|--------------|--------|
| 1 | XSS melalui teks | Masukkan `<script>alert(1)</script>` ke judul section | Teks tampil sebagai teks biasa, tidak dieksekusi | | |
| 2 | XSS melalui gambar | Masukkan `javascript:alert(1)` ke URL gambar | Ditolak atau disanitasi | | |
| 3 | URL javascript: | Masukkan `javascript:alert(1)` ke URL tombol | Ditolak dengan pesan error | | |
| 4 | Input style di luar batas | Masukkan font size 9999 | Di-clamp ke nilai maksimum (96) | | |
| 5 | Upload file non-gambar | Upload file .exe atau .php | Ditolak dengan pesan error | | |
| 6 | CSRF aktif | Kirim request tanpa token CSRF | Ditolak (419) | | |
| 7 | Preview aman | Buka preview draft berisi konten XSS | Script tidak dieksekusi | | |

---

## 6. CARA MENCATAT SCREENSHOT

### 6.1 Microsoft Edge

1. Buka halaman yang akan diuji.
2. Tekan `F12` untuk membuka Developer Tools.
3. Klik ikon kamera di toolbar DevTools, atau tekan `Ctrl+Shift+S`.
4. Pilih "Capture full size screenshot".
5. Simpan ke folder `screenshots/checkpoint5b/`.

### 6.2 Alternatif

- Gunakan `Win+Shift+S` untuk screenshot area tertentu.
- Simpan dengan nama sesuai nomor skenario.

---

## 7. CARA LAPORAN ERROR

### 7.1 Format Laporan Error

```
SKENARIO: [nomor dan nama]
LANGKAH: [langkah yang dilakukan]
HASIL DIHARAPKAN: [yang seharusnya terjadi]
HASIL AKTUAL: [yang sebenarnya terjadi]
SCREENSHOT: [nama file]
CONSOLE ERROR: [pesan error dari browser console, jika ada]
NETWORK ERROR: [pesan error dari tab Network, jika ada]
```

### 7.2 Cara Melihat Console Error

1. Tekan `F12` di Edge.
2. Pilih tab "Console".
3. Lakukan aksi yang menyebabkan error.
4. Catat pesan error berwarna merah.

### 7.3 Cara Melihat Network Error

1. Tekan `F12` di Edge.
2. Pilih tab "Network".
3. Centang "Preserve log".
4. Lakukan aksi yang menyebabkan request gagal.
5. Klik request yang berwarna merah.
6. Catat status code dan pesan error.

---

## 8. KRITERIA EDITOR SIAP DIGUNAKAN

Editor dinyatakan **SIAP** jika dan hanya jika:

| No | Kriteria | Status |
|----|----------|--------|
| 1 | Semua 24 skenario browser PASS | |
| 2 | Semua 6 skenario otorisasi PASS | |
| 3 | Semua 7 skenario keamanan konten PASS | |
| 4 | Tidak ada console error saat editor dimuat | |
| 5 | Tidak ada network error saat operasi normal | |
| 6 | Regression PHPUnit tetap 0 kegagalan | |

**Jika ada satu pun skenario FAIL atau BLOCKED, editor dinyatakan `NEEDS FIXES`.**

---

## 9. PROSEDUR PENGUJIAN

### 9.1 Sebelum Pengujian

```
[ ] Database landing_builder_e2e sudah dibuat
[ ] .env.testing sudah dikonfigurasi
[ ] Akun Administrator testing sudah dibuat
[ ] Akun non-Administrator testing sudah dibuat
[ ] Aplikasi testing berjalan dan bisa diakses
[ ] Folder screenshots/checkpoint5b/ sudah dibuat
```

### 9.2 Saat Pengujian

1. Login sebagai Administrator testing.
2. Buka Landing Page Builder.
3. Jalankan skenario 1–24 secara berurutan.
4. Isi tabel hasil untuk setiap skenario.
5. Ambil screenshot untuk setiap skenario.
6. Catat console error dan network error.

### 9.3 Setelah Pengujian

1. Logout.
2. Coba akses editor tanpa login (harus redirect ke /masuk).
3. Login sebagai non-Administrator, coba akses editor (harus 403).
4. Kumpulkan semua screenshot.
5. Isi laporan hasil.

---

## 10. STATUS AKHIR

# `LANDING PAGE BUILDER — MANUAL BROWSER TEST PLAN READY`

**Catatan:** Dokumen ini adalah panduan untuk operator. Browser E2E belum dijalankan. Status `EDITOR BROWSER VERIFIED` hanya diberikan setelah semua skenario PASS dan dokumentasi screenshot terlampir.
