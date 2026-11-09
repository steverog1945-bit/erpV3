# LANDING PAGE BUILDER — CHECKPOINT 5B.2
## LAPORAN PENENTUAN URL TESTING DAN PENYIAPAN LINGKUNGAN

**Tanggal:** 2026-10-09
**Status:** `LANDING PAGE BUILDER — TEST URL PREPARATION READY`

---

## A. LINGKUNGAN TESTING YANG TERSEDIA

### A.1 Infrastruktur

| Komponen | Nilai | Status |
|----------|-------|--------|
| PHP | 8.2.12 (ZTS Visual C++ 2019 x64) | ✅ |
| Laravel | 12.53.0 | ✅ |
| `vendor/autoload.php` | Ada | ✅ |
| `bootstrap/app.php` | Ada | ✅ |
| `public/index.php` | Ada | ✅ |
| Aset build (`public/build/`) | `app-DYpd5sEB.css`, `app-ff41jNsz.js`, `manifest.json` | ✅ |
| MySQL | MariaDB 10.4.32 (port 3306) | ✅ |
| Microsoft Edge | Terinstall di `C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe` | ✅ |

### A.2 Port yang Tersedia

| Port | Status |
|------|--------|
| 3306 | Dipakai MySQL |
| 80 | Dipakai Apache (ERP produksi) |
| 443 | Dipakai Apache |
| **8000** | **Bebas** |
| 8001 | Bebas |
| 8080 | Bebas |

### A.3 Status Aplikasi Saat Ini

- Aplikasi ERP produksi berjalan di `http://localhost/grafikaorion/` (Apache port 80).
- `APP_ENV=production`.
- Database produksi: `oriondb`.
- **TIDAK BOLEH** digunakan untuk pengujian editor.

---

## B. STATUS ISOLASI WORKSPACE

| Aspek | Status |
|-------|--------|
| Workspace builder terisolasi di `C:\xampp\htdocs\grafikaorion` | ✅ |
| Tidak ada akses ke file pribadi | ✅ |
| Tidak membaca `.env` produksi | ✅ |
| Tidak mengakses `oriondb` untuk pengujian | ✅ |
| Tidak mengubah modul ERP | ✅ |

---

## C. URL TESTING YANG DIUSULKAN

### C.1 Kandidat URL

| Kandidat | URL | Status |
|----------|-----|--------|
| Utama | `http://127.0.0.1:8000/grafikaorian/` | **Diusulkan** |
| Alternatif 1 | `http://127.0.0.1:8001/grafikaorion/` | Cadangan |
| Alternatif 2 | `http://127.0.0.1:8080/grafikaorion/` | Cadangan |

### C.2 Alasan Pemilihan Port 8000

1. Port 8000 bebas dan tidak bentrok dengan layanan yang ada.
2. `php artisan serve` secara default menggunakan port 8000.
3. Loopback `127.0.0.1` menjamin tidak terkspos ke jaringan eksternal.

### C.3 Bukti URL Dapat Diakses

**URL belum dapat diakses.** Alasan:

1. Server testing belum dijalankan.
2. Database `landing_builder_e2e` belum dibuat.
3. File `.env.testing` belum dibuat.
4. Operator belum memberikan izin untuk menjalankan server testing.

**Oleh karena itu, status URL adalah PREPARATION READY, bukan VERIFIED.**

---

## D. DAFTAR ROUTE LANDING PAGE BUILDER

Semua route builder terletak di `routes/web.php` baris 719–743, di dalam group middleware `hanya.admin`.

| No | Method | URI | Nama Route | Fungsi |
|----|--------|-----|------------|--------|
| 1 | GET | `/setting/landing-page-builder` | `landing-page-builder.index` | Halaman editor |
| 2 | GET | `/setting/landing-page-builder/draft` | `landing-page-builder.draft.show` | Muat draft |
| 3 | PUT | `/setting/landing-page-builder/draft` | `landing-page-builder.draft.save` | Simpan draft |
| 4 | GET | `/setting/landing-page-builder/revisions` | `landing-page-builder.revisions.list` | Daftar revisi |
| 5 | GET | `/setting/landing-page-builder/revisions/{revisionNumber}` | `landing-page-builder.revisions.show` | Lihat revisi |
| 6 | POST | `/setting/landing-page-builder/publish` | `landing-page-builder.publish` | Publikasikan |
| 7 | POST | `/setting/landing-page-builder/revisions/{revisionNumber}/restore` | `landing-page-builder.revisions.restore` | Restore revisi |
| 8 | POST | `/setting/landing-page-builder/media` | `landing-page-builder.media.upload` | Upload media |
| 9 | GET | `/setting/landing-page-builder/media` | `landing-page-builder.media.list` | Daftar media |
| 10 | DELETE | `/setting/landing-page-builder/media/{id}` | `landing-page-builder.media.delete` | Hapus media |
| 11 | GET | `/setting/landing-page-builder/preview/draft` | `landing-page-builder.preview.draft` | Preview draft |
| 12 | GET | `/setting/landing-page-builder/preview/published` | `landing-page-builder.preview.published` | Preview published |
| 13 | GET | `/setting/landing-page-builder/preview/revision/{revisionNumber}` | `landing-page-builder.preview.revision` | Preview revisi |

### Middleware

| Middleware | Lokasi | Fungsi |
|------------|--------|--------|
| `hanya.admin` | `routes/web.php` baris 718 | Membatasi akses hanya untuk role Administrator |
| `HanyaAdministrator` | `app/Http/Middleware/HanyaAdministrator.php` | Pemeriksaan role di level request |

---

## E. STATUS DATABASE E2E

| Properti | Nilai |
|----------|-------|
| Nama database yang direkomendasikan | `landing_builder_e2e` |
| Status | **BELUM ADA** |
| Apakah sudah dibuat | Tidak |
| Apakah sudah ada izin membuat | **BELUM** |

### E.1 Prosedur Pembuatan Database (Untuk Operator)

```sql
CREATE DATABASE landing_builder_e2e
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### E.2 Prosedur Migration (Untuk Operator, Setelah DB Dibuat)

```bash
php artisan migrate --env=testing --database=mysql
```

### E.3 Prosedur Seeder (Untuk Operator)

Data sintetis yang diperlukan:

```php
// Role
Role::create(['nama_role' => 'Administrator']);
Role::create(['nama_role' => 'Kasir']);
Role::create(['nama_role' => 'Operator']);

// Akun Administrator testing (password dibuat operator)
User::create([
    'nama' => 'Admin Testing',
    'email' => 'admin.testing@example.com',
    'password' => bcrypt('PASSWORD_DIBUAT_OPERATOR'),
    'id_role' => Role::where('nama_role', 'Administrator')->first()->id_role,
]);
```

**Aturan:**
- Password **tidak boleh** ditulis di laporan.
- Password **tidak boleh** sama dengan password produksi.
- Akun testing **hapus** setelah pengujian selesai.

---

## F. STATUS ADMINISTRATOR TESTING

| Properti | Status |
|----------|--------|
| Akun Administrator testing | **BELUM ADA** |
| Mekanisme pembuatan | Melalui `php artisan tinker --env=testing` |
| Role | `Administrator` |
| Password | Dibuat operator, tidak ditulis di laporan |
| Akun non-Administrator (untuk uji 403) | Belum ada |

---

## G. KETERSEDIAAN BROWSER DAN ALAT PENGUJIAN

| Alat | Status |
|------|--------|
| Microsoft Edge | ✅ Terinstall |
| Playwright | ❌ Tidak terinstal (dan dilarang menginstal tanpa izin) |
| Puppeteer | ❌ Tidak terinstal |
| Cypress | ❌ Tidak terinstal |
| Selenium | ❌ Tidak terinstal |
| Dusk | ❌ Tidak terinstal |

**Kesimpulan:** Browser E2E otomatis **tidak tersedia**. Pengujian harus dilakukan secara **manual** melalui Microsoft Edge.

---

## H. DAFTAR PENGUJIAN YANG BENAR-BENAR DIJALANKAN

| No | Pengujian | Status |
|----|-----------|--------|
| 1 | Sintaks JavaScript valid (`node --check`) | ✅ Lulus |
| 2 | Logika editor via DOM shim (17 skenario) | ✅ 17/17 lulus |
| 3 | Validasi server-side via PHPUnit (27 tes) | ✅ 27/27 lulus |
| 4 | Regression PHPUnit (1112 tes) | ✅ 0 kegagalan |
| 5 | Browser E2E manual (24 skenario) | ❌ **BELUM DIJALANKAN** |
| 6 | Otorisasi Administrator (6 skenario) | ❌ **BELUM DIJALANKAN** |
| 7 | Keamanan konten (7 skenario) | ❌ **BELUM DIJALANKAN** |

---

## I. PENGUJIAN YANG MASIH BLOCKED

| No | Pengujian | Penyeblokir |
|----|-----------|-------------|
| 1 | Browser E2E 24 skenario | Database testing belum dibuat, akun testing belum ada, server testing belum dijalankan |
| 2 | Otorisasi Administrator | Membutuhkan akun testing (Administrator + non-Administrator) |
| 3 | Keamanan konten | Membutuhkan lingkungan testing terisolasi |
| 4 | URL testing | Server testing belum dijalankan |

---

## J. INSTRUKSI SINGKAT BAGI OPERATOR UNTUK MEMBUKA EDITOR DI EDGE

### J.1 Persiapan

```bash
# 1. Buat database
mysql -u root -e "CREATE DATABASE landing_builder_e2e CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Buat .env.testing (di root workspace)
#    DB_DATABASE=landing_builder_e2e
#    APP_ENV=testing
#    LANDING_PAGE_BUILDER_ENABLED=false

# 3. Jalankan migration
php artisan migrate --env=testing

# 4. Buat akun testing via tinker
php artisan tinker --env=testing
#    (kode seeder lihat Bagian E.3)

# 5. Jalankan server testing
php artisan serve --env=testing --host=127.0.0.1 --port=8000
```

### J.2 Buka di Edge

1. Buka Microsoft Edge.
2. Navigasi ke `http://127.0.0.1:8000/masuk`.
3. Login dengan akun Administrator testing.
4. Klik menu "Landing Page Builder" atau navigasi ke `http://127.0.0.1:8000/setting/landing-page-builder`.
5. Jalankan 24 skenario pengujian dari `LANDING_PAGE_BUILDER_CHECKPOINT5B1_MANUAL_TEST_PLAN.md`.

---

## K. PERSETUJUAN TAMBAHAN YANG DIPERLUKAN

| No | Persetujuan | Keperluan |
|----|-------------|-----------|
| 1 | Membuat database `landing_builder_e2e` | Lingkungan testing terisolasi |
| 2 | Membuat file `.env.testing` | Konfigurasi environment testing |
| 3 | Menjalankan migration testing | Membuat tabel yang diperlukan |
| 4 | Menjalankan `php artisan serve --port=8000` | Server testing |
| 5 | Membuat akun Administrator testing | Login untuk pengujian |
| 6 | Membuat akun non-Administrator testing | Uji penolakan 403 |

---

## L. REKOMENDASI LANGKAH PALING SINGKAT MENUJU BROWSER VERIFICATION

### L.1 Langkah Minimum

```
1. Operator membuat database landing_builder_e2e
2. Operator membuat .env.testing
3. Operator menjalankan migration
4. Operator membuat akun testing via tinker
5. Operator menjalankan php artisan serve --env=testing --port=8000
6. Operator membuka http://127.0.0.1:8000/masuk di Edge
7. Operator login dan membuka editor
8. Operator menjalankan 24 skenario manual
9. Operator mengisi checklist dan mengambil screenshot
```

### L.2 Estimasi Waktu

| Langkah | Estimasi |
|---------|----------|
| Buat database + .env.testing | 5 menit |
| Migration | 2 menit |
| Buat akun testing | 3 menit |
| Jalankan server | 1 menit |
| Pengujian 24 skenario | 45–60 menit |
| Dokumentasi screenshot | 15 menit |
| **Total** | **~1.5 jam** |

### L.3 Risiko

| Risiko | Mitigasi |
|--------|----------|
| Lupa hapus akun testing | Checklist pasca-pengujian |
| Server testing bentrok dengan produksi | Gunakan port 8000 yang bebas |
| Lupa set `LANDING_PAGE_BUILDER_ENABLED=false` | Verifikasi sebelum pengujian |
| Akun testing bocor ke produksi | Password berbeda, hapus setelah pengujian |

---

## STATUS AKHIR

# `LANDING PAGE BUILDER — TEST URL PREPARATION READY`

**Alasan:**
- Konfigurasi dan prosedur sudah siap.
- Port testing sudah ditentukan (8000).
- Perintah server testing sudah disiapkan.
- **Namun** database testing belum dibuat, server belum dijalankan, dan URL belum diverifikasi.

**Langkah selanjutnya:** Operator memberikan izin dan menjalankan Langkah 1–5 dari Bagian L.1, kemudian menjalankan pengujian manual sesuai `LANDING_PAGE_BUILDER_CHECKPOINT5B1_MANUAL_TEST_PLAN.md`.
