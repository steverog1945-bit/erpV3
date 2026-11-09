# LANDING PAGE BUILDER — CHECKPOINT 4D.2
## FINAL ACCEPTANCE, LOGIN ROUTE AUDIT & RELEASE GATE

## STATUS AKHIR: `LANDING PAGE BUILDER — PUBLIC CUTOVER NO-GO`

**Public activation: TIDAK dilakukan. Flag: TIDAK diaktifkan (terverifikasi `false`).**
**`oriondb`: READ-ONLY, tidak dimodifikasi.**

---

## KEPUTUSAN SINGKAT

Checkpoint ini menemukan **dua blocker kritis** yang tidak terlihat di checkpoint
sebelumnya. Keduanya terbukti lewat test yang bisa direproduksi, bukan dugaan.

| # | Blocker | Dampak |
|---|---------|--------|
| **B1** | **`oriondb` tidak punya satu pun dari 4 tabel Landing Page Builder** | Fitur di produksi tidak akan berfungsi. Setiap request homepage akan melempar error DB lalu fallback — situs tetap hidup tapi fitur mati dan log penuh warning. |
| **B2** | **Homepage builder tidak punya jalan masuk (form/link login)** | Saat flag ON, staf yang ter-redirect dari halaman terlindungi mendarat di halaman tanpa form login → **totalkunci**. |

Ditambah 6 gate lain yang belum terpenuhi (lihat bagian 10).

---

## 1. PRE-FLIGHT

| Item | Nilai aktual |
|------|--------------|
| Branch | `main` |
| Commit | `a66b11384f42944752c97e62976821e8f2afa373` — *"Docs: panduan deploy ke VPS dengan subdomain Hostinger"* |
|APP_ENV (CLI) | `production` |
| DB (CLI) | `oriondb` — **tidak disentuh** |
| DB (test) | `percetakan_erp_testing` (`phpunit.xml` hardcode) |
| Feature flag | `false` |
| `.env` punya `LANDING_PAGE_BUILDER_ENABLED`? | **Tidak** → default OFF |
| Config cache | `config:cache` → `'enabled' => false` tersimpan benar di `bootstrap/cache/config.php:525` |
| Route homepage | `routes/web.php:46` `Route::get('/', [AuthController::class,'showLogin'])->name('login');` |
| Route `/login` (GET) | **Tidak ada** — hanya `POST /login` (line 49) |
| Working tree | Tidak ada file `oriondb` atau `.env` yang dimodifikasi |

### Verifikasi aplikasi nyata (bukan hanya test)

`DocumentRoot` Apache = `C:/xampp/htdocs`, jadi aplikasi berada di **subfolder**:

```
http://localhost/                 → halaman default XAMPP (bukan aplikasi)
http://localhost/grafikaorion/    → aplikasi Laravel
```

Bukti nyata dengan flag OFF:

```
STATUS: 200
LEN   : 32233 byte
dialog-masuk  : ada
data-section-type (builder) : tidak ada
```

Sesuai harapan: homepage existing utuh, tidak ada konten builder.

---

## 2. AUDIT ROUTE / LOGIN (13 SKENARIO)

File: `tests/Feature/LandingPageLoginRouteAuditTest.php` — **21 tests / 69 assertions, 0 failure**.

| # | Skenario | Hasil | Test |
|---|----------|-------|------|
| 1 | Anonymous GET `/`, flag OFF | ✅ 200, view `login`, ada "Masuk" | `skenario_01_...` |
| 2 | Anonymous GET `/`, flag ON | ✅ 200, view `landing-page-public`, hero tampil | `skenario_02_...` |
| 3 | Authenticated GET `/`, flag OFF | ✅ redirect ke `dashboard` | `skenario_03_...` |
| 4 | Authenticated GET `/`, flag ON | ✅ redirect ke `dashboard`, builder **tidak** bocor | `skenario_04_...` |
| 5 | GET `/login` | ✅ Route `login` → URI `/`; `GET /login` → 404/405 | `skenario_05_...` |
| 6 | POST login sukses | ✅ redirect `dashboard`, user terautentikasi | `skenario_06_...` |
| 7 | POST login gagal | ✅ kembali + error, tetap guest | `skenario_07_...` |
| 7b | POST login, flag ON | ✅ tetap berfungsi | `skenario_07b_...` |
| 8 | Logout | ✅ session bersih, redirect `/` | `skenario_08_...`, `08b` |
| 9 | Expiry: tamu buka `/dashboard` (flag OFF) | ✅ redirect `/` + form masuk tersedia | `skenario_09_...` |
| 9b | Expiry: tamu buka `/dashboard` (flag ON) | ⚠️ redirect `/` tapi **tidak ada jalan masuk** | `skenario_09b_...` |
| 10 | Redirect setelah auth sukses | ✅ `url.intended` basi diabaikan | `skenario_10_...` |
| 10b | `bukaMasuk` saat middleware | ✅ dialog masuk ada | `skenario_10b_...` |
| 11 | Kompatibilitas nama route `login` | ✅ tepat 1 route, URI `/`, method GET | `skenario_11_...`, `11b` |
| 12 | CSRF | ✅ form bawa `name="_token"` + `action=url('/login')`; builder tidak punya form | `skenario_12_...`, `12b` |
| 13 | Public asset tanpa auth | ✅ `/` dan `/baca/{slug}` dapat diakses tamu | `skenario_13_...`, `13b` |

### Apakah early-return aman? — Jawaban: **AMAN secara teknis, TIDAK aman secara fungsional**

Secara teknis early-return ini benar dan tidak merusak apa pun:

- `Auth::check()` tetap dicek **lebih dulu**, jadi user login tidak pernah melihat halaman builder.
- Tidak menyentuh `PublikController`, tidak mengubah route, tidak mengubah view lama.
- Login, logout, CSRF, session handling semuanya tidak tersentuh — terbukti 19 dari 21 test hijau.

### TEMUAN KRITIS B2 — Tidak ada jalan masuk saat flag ON

Diverifikasi dengan grep pada view publik:

```
resources/views/landing-page-public.blade.php
  → 0 kemunculan: login / masuk / form / dialog / csrf
```

Dua test membuktikannya secara eksplisit (`skenario_09b` dan `temuan_builder_homepage_tidak_punya_jalan_masuk`) — keduanya **lulus** karena assertion-nya menyatakan temuan itu benar.

**Rantai lockout:**

```
Staf buka /kasir (atau /dashboard)
  → middleware 'auth' menolak
  → redirect ke route('login') yang Bernilai URI "/"
  → '/' dilayani resolver
  → flag ON + published revision valid
  → render landing-page-public.blade.php
  → TIDAK ADA form login
  → staf tidak bisa masuk dari mana pun
```

Tidak ada halaman login lain: `GET /login` tidak terdaftar. Form login hanya ada
sebagai `<dialog>` di dalam `login.blade.php`.

### Koreksi arsitektural minimum (usulan — BELUM diimplementasi)

Checkpoint ini melarang refactor autentikasi tanpa approval, jadi saya **tidak**
mengimplementasikan perbaikan ini. Usulan, dari yang paling kecil:

**Opsi A — fallback untuk staf yang ter-redirect (paling kecil, tanpa UI baru).**
Di resolver/controller: bila flag ON **dan** pengunjung adalah tamu yang punya
`url.intended` di session (berarti baru saja dibounced dari halaman terlindungi),
sajikan homepage existing. Pengunjung umum (tanpa `url.intended`) tetap dapat
builder homepage.
- Perubahan: ~5 baris di `LandingPageHomepageResolver::resolve()`.
- Tidak menambah route, tidak menambah UI publik, tidak menyentuh kode auth.
- **Sisa celah:** staf yang membuka `/` secara manual tetap tidak menemukan form login.

**Opsi B — lengkapi (disarankan untuk 4D.3).**
Ekstrak blok `<dialog>` masuk dari `login.blade.php` ke partial, lalu sertakan
partial itu di `landing-page-public.blade.php` dengan tombol "Masuk".
- Menggunakan markup & endpoint POST `/login` yang sudah ada dan sudah teruji.
- Bukan menulis ulang autentikasi, tapi menyentuh view login → tetap perlu approval.

**Opsi C — paling aman sekarang.** Biarkan flag OFF sampai B2 selesai di 4D.3.
Tidak ada risiko lockout sama sekali.

Rekomendasi: **Opsi A sekarang sebagai jaring pengaman, Opsi B sebelum 4D.3.**
，我没看漏 — Opsi A saja tidak menutup celah "/ dibuka manual".

---

## 3. ISOLASI DATABASE UJI

### Insiden sebelumnya & akar masalahnya

Insiden checkpoint 4D.1 (417 lalu 405 error palsu) punya dua sebab yang saling
memperkuat:

1. **Test destruktif.** Saya menulis test yang memakai `DROP TABLE landing_pages`
   untuk menyimulasikan kegagalan database.
2. **Run bersamaan pada database yang sama.** Full-suite berjalan di background
   sementara test itu dieksekusi di foreground. Keduanya memanggil
   `RefreshDatabase` → `migrate:fresh` pada `percetakan_erp_testing` yang sama.
   Satu proses menghapus tabel ketika proses lain sedang memakainya.

Root cause sebenarnya adalah **guard yang terlalu lemah**, bukan hanya kelalaian.

### Safeguard yang diimplementasikan

#### Safeguard A — Guard di `tests/TestCase.php` (diperkuat)

Guard lama hanya:
```php
if (!str_contains($db, 'testing') && $db !== ':memory:') { exit(1); }
```
Celah: database bernama `oriondb_testing` **lolos**, karena mengandung "testing".

Guard baru:
```php
private const FORBIDDEN_DB_FRAGMENTS = ['oriondb'];
// 1. kosong → batal
// 2. mengandung 'oriondb' (case-insensitive, substring) → batal
// 3. tidak mengandung 'testing' → batal, sarankan config:clear
```

**Diverifikasi secara langsung:**

```
DB_DATABASE=oriondb            → TEST DIBATALKAN: mengandung 'oriondb'
DB_DATABASE=oriondb_testing    → TEST DIBATALKAN: mengandung 'oriondb'
```

Yang kedua inilah yang **lolos** di guard lama. Keduanya berhenti sebelum
koneksi database dibuka.

#### Safeguard B — Static guard anti-SQL-destruktif

`tests/Feature/TestDatabaseIsolationGuardTest.php` — **6 tests / 18 assertions, hijau**.

Deteksi v1 naïf langsung salah positif pada 5 tempat:
- `version_looking_like_numbers_is_not_truncated` → kata *"truncated"* cocok pola `TRUNCATE`
- `RawSqlAuditTest` → payload injeksi `"x'; DROP TABLE transaksi;--"` adalah **string**, bukan perintah
- komentar dokumentasi
- file penjaga menunjuk dirinya sendiri

Diperbaiki jadi deteksi **konteks eksekusi**: pola hanya dianggap pelanggaran
bila muncul di dalam `DB::statement()`, `DB::unprepared()`, `DB::raw()`,
`DB::select()`, `Schema::drop*()`, `->statement()`, `->unprepared()` — dengan
word boundary dan file penjaga dikecualikan.

#### Safeguard C — Guard phpunit.xml
`phpunit.xml` harus berisi `percкетakan_erp_testing` dan tidak boleh berisi
`value="oriondb"`.

#### Safeguard D — Larangan menyebut `oriondb` di kode test
Setiap file test diperiksa; file penjaga (`tests/TestCase.php`) dikecualikan
karena **wajib** menyebut `oriondb` untuk memblokirnya.

### Safeguard yang MASIH perlu perubahan CI (diajukan, belum diimplementasi)

Tidak ada CI di repo ini. Yang berikut akanaxo ditambahkan tanpa persetujuan —
sayaitações usulannya saja:

| Usulan | Tujuan |
|--------|--------|
| Job test **single-threaded**, tanpa `parallel`/matrix | Mencegah dua `migrate:fresh` pada DB sama |
| Nama DB unik per job (`percetakan_erp_testing_${JOB_ID}`) | Isolasi penuh antar job |
| `php artisan config:clear` sebelum test | Mencegah `config:cache` mengarahkan test ke produksi |
| Step eksplisit: `php artisan migrate --database=<test-db>` | Migrasi terkontrol per job |
| Direktori khusus `tests/Destructive/` dengan DB sekali pakai | Test yang butuh `DROP`/`TRUNCATE` |

### Prosedur operasi (dokumen ini yang dipakai)

```
1. JANGAN pernah menjalankan dua PHPUnit pada DB uji yang sama.
2. Cek dulu: tasklist | findstr php
3. JANGAN pernah menjalankan DROP/TRUNCATE di DB uji bersama.
   (Guard B akan menolak, tapi jangan Nogueira andalkan.)
4. JANGAN pernah menjalankan migrate:fresh di oriondb.
   (Guard A akan menolak, tapi jangan andalkan.)
5. Kalau butuh test destruktif: database sekali pakai, bukan DB uji bersama.
6. Setelah config:cache, WAJIB config:clear sebelum test.
```

---

## 4. BROWSER E2E — B1 s.d. B13

### Status: **PENDING — 0 SKENARIO DIJALANKAN**

Tidak ada satu pun skenario yang dieksekusi. Klaim E2E PASS **tidak diklaim**.

### Ketersediaan tool

| Tool | Status |
|------|--------|
| Playwright | ❌ tidak terpasang |
| Cypress | ❌ tidak terpasang |
| Selenium / WebDriver | ❌ tidak terpasang |
| Laravel Dusk | ❌ tidak terpasang |
| Chrome DevTools Protocol | ❌ tidak tersedia di harness |

Tidak ada dependency baru dipasang.

### Temuan yang membantu prosedur manual

Aplikasi **tidak** berada di root Apache. URL yang benar untuk pengujian:

```
http://localhost/grafikaorion/
```

(jangan `http://localhost` — itu halaman default XAMPP).

### Prosedur acceptance manual (siap dieksekusi, belum diisi)

Kolom Actual / PASS-FAIL / bukti / penguji / tanggal **wajib** diisi. Kosong = belum diuji.

#### Bagian A — Flag OFF (baseline)

| ID | Prasyarat | Langkah | Expected | Actual | PASS/FAIL | Bukti | Penguji | Tanggal |
|----|-----------|---------|----------|--------|-----------|-------|---------|---------|
| B1 | Flag OFF | Buka `/grafikaorion/` sebagai tamu | Katalog layanan + tulisan + tombol Masuk | | | | | |
| B2 | Flag OFF | Buka di lebar 375px | 1 kolom, tanpa scroll horizontal | | | | | |
| B3 | Flag OFF | Buka di lebar 768px | Layout tidak rusak | | | | | |
| B4 | Flag OFF | Lihat tab title & `<head>` | Sama seperti sebelum checkpoint | | | | | |
| B5 | Flag OFF | Klik "Masuk" → isi → submit | Masuk ke dashboard | | | | | |
| B6 | Flag OFF | Klik "Keluar" | Kembali ke homepage, tidak bisa buka `/dashboard` | | | | | |

#### Bagian B — Flag ON di isolated testing

| ID | Prasyarat | Langkah | Expected | Actual | PASS/FAIL | Bukti | Penguji | Tanggal |
|----|-----------|---------|----------|--------|-----------|-------|---------|---------|
| B7 | Flag ON lokal + published revision valid | Buka `/grafikaorion/` | Section dari revision tampil | | | | | |
| B8 | Flag ON | **Cari tombol/form "Masuk"** | **Harus ada — saat ini TIDAK ADA (B2)** | | | | | |
| B9 | Flag ON | Masuk ke `/dashboard` tanpa login | Tidak mungkin diam-diam — harus ada jalan masuk | | | | | |
| B10 | Flag ON | Lihat `<head>` | Canonical = URL homepage, bukan URL preview | | | | | |
| B11 | Flag ON | Buka URL preview (logged in) | Banner PREVIEW MODE, `noindex` | | | | | |
| B12 | Flag ON | 375px / 768px / desktop | Tidak ada overflow horizontal | | | | | |
| B13 | Flag ON | Buka Console browser | Tidak ada error kritikal | | | | | |

#### Bagian C — Editor, draft, media, publish

| ID | Prasyarat | Langkah | Expected | Actual | PASS/FAIL | Bukti | Penguji | Tanggal |
|----|-----------|---------|----------|--------|-----------|-------|---------|---------|
| B14 | Login admin | Tambah Hero + Services | Section masuk canvas | | | | | |
| B15 | " | Edit judul/subtitle/deskripsi/label/URL | Teks & href berubah realtime | | | | | |
| B16 | " | Ubah layout/style/motion | Visual berubah sesuai preset | | | | | |
| B17 | " | Duplicate → cek ID baru | ID unik `s-xxxxxxxxx` | | | | | |
| B18 | " | Reorder + toggle visibility | Urutan & visibilitas berubah | | | | | |
| B19 | " | Undo / Redo | State sebelum/sesuai | | | | | |
| B20 | " | Delete + konfirmasi | Section hilang, undo mengembalikan | | | | | |
| B21 | " | Save Draft → Refresh | Data tetap | | | | | |
| B22 | " | Edit → tunggu autosave → Refresh | Perubahan terakhir tersimpan | | | | | |
| B23 | DevTools offline | Edit saat network mati → online | Retry, state lokal tidak hilang | | | | | |
| B24 | Dua tab admin | Tab A & B edit bersamaan | 409 + pilihan "copy local changes" | | | | | |
| B25 | Login admin | Upload gambar valid | Gambar masuk media picker | | | | | |
| B26 | " | Upload `.php` / `.jpg` palsu / 11MB | Ditolak (415/422) | | | | | |
| B27 | " | Pakai media di Hero → Save → Reload | Gambar tetap tampil | | | | | |
| B28 | " | Hapus media yang SEDANG dipakai | Ditolak | | | | | |
| B29 | " | Hapus media tidak terpakai | Berhasil | | | | | |
| B30 | " | Publish | Revision dibuat, status published | | | | | |
| B31 | " | Version History | Daftar revision tampil | | | | | |
| B32 | " | Restore revision lama | Draft kembali; media historis tidak broken | | | | | |

#### Bagian D — Fallback & rollback

| ID | Prasyarat | Langkah | Expected | Actual | PASS/FAIL | Bukti | Penguji | Tanggal |
|----|-----------|---------|----------|--------|-----------|-------|---------|---------|
| B33 | Flag ON | Unpublish / null-kan `published_revision_id` | Otomatis fallback ke homepage existing | | | | | |
| B34 | Flag ON | Buka homepage saat flag ON | Tidak error | | | | | |
| B35 | Flag ON | Matikan flag + `config:clear` | Kembali ke homepage existing | | | | | |
| B36 | Flag OFF | `php scripts/check_public_homepage.php ... existing` | `HASIL: SEHAT` | | | | | |
| B37 | — | Alur ERP正常: kasir → SPK → produksi → pembayaran | Tidak ada regresi | | | | | |

---

## 5. VERIFIKASI FEATURE FLAG

### Dengan config cache AKTIF

```
php artisan config:cache
→ bootstrap/cache/config.php:523  'landing_page_builder' =>
  :523 'enabled' => false
```

Config cache menyelesaikan flag dengan benar. Tidak ada masalah `env()` yang
bocor ke runtime.

### Matriks perilaku

| Kondisi | Perilaku | Test | Status |
|---------|----------|------|--------|
| Flag OFF | Homepage existing | 32-test resolver suite | ✅ |
| Flag ON + revision valid | Builder homepage | ✅ |
| Flag ON + halaman tidak ada | Fallback | ✅ |
| Flag ON + draft saja | Fallback | ✅ |
| Flag ON + struktur rusak | Fallback | ✅ |
| Flag ON + schema version tak didukung | Fallback | ✅ |
| Flag ON + pointer milik halaman lain | Fallback | ✅ |
| Flag ON + JSON rusak | Fallback, tidak 500 | ✅ |
| Flag ON + DB gagal | Fallback, exception tidak bocor | ✅ |
| Flag ON + tabel tidak ada (**oriondb**) | Fallback (via catch) — **tapi fitur mati & noisy** | ✅ terverifikasi secara logis, **tidak diuji langsung** | ⚠️ |

Baris terakhir penting: di `oriondb` tabelnya belum ada, jadi query akan
melempar exception, ditangkap `try/catch`, lalu fallback. Situs tidak rusak,
tetapi **fitur tidak berfungsi** dan setiap request menulis warning.

### `.env` produksi tidak diubah

`.env` tidak pernah disentuh. Flag tetap OFF via default config.

---

## 6. VERIFIKASI BACKUP

### Prosedur & lokasi

| Item | Nilai |
|------|-------|
| Service | `App\Services\BackupService` |
| Pemicu | UI `/backup` (Administrator), plus scheduler |
| Lokasi lokal | `storage/app/backups/{daily\|weekly\|monthly\|manual}/` |
| Lokasi kedua | `config('backup.secondary_disk')` → env `BACKUP_SECONDARY_DISK` |
| Retention | daily 7, weekly 4, monthly 12, manual 10 |
| Catatan audit | tabel `backup_logs` (status: Berjalan / Sukses / Gagal) |
| Metode | `mysqldump --single-transaction --routines --triggers` |

### Status file backup saat ini

19 file, terbaru `backup_daily_20261009_010059.sql` (630.810 byte, ~8 jam lalu).

### Integritas berkas — **TERVERIFIKASI**

Pemeriksaan read-only (hanya membaca file):

| Pemeriksaan | Hasil |
|-------------|-------|
| Tidak kosong | ✅ |
| Header dump ada (MySQL/MariaDB) | ✅ MariaDB 10.19 dump |
| Ada `CREATE TABLE` | ✅ 106 tabel |
| Tidak terpotong | ✅ |
| Ukuran wajar | ✅ 630 KB |
| Marker akhir dump ada | ✅ |
| SHA-256 tiap file dicatat | ✅ 19 checksum |

### Restore — **UNVERIFIED**

Restore sungguhan **tidak** dijalankan, karena:

- Restore ke `oriondb` dilarang oleh checkpoint.
- Restore ke database lain berarti menjalankan `CREATE DATABASE`/`DROP DATABASE`
  yang juga dilarang ("do not run destructive database commands").

**Saya tidak mengklaim kesiapan restore hanya dari dokumentasi.** Statusnya
**UNVERIFIED**.

### TEMUAN KRITIS B1 — `oriondb` belum punya tabel Landing Page Builder

Backup terbaru adalah dump `oriondb`. Pemeriksaan read-only `information_schema`
pada `oriondb` (106 tabel):

```
landing_pages             TIDAK ADA
landing_page_drafts       TIDAK ADA
landing_page_revisions    TIDAK ADA
landing_page_media        TIDAK ADA
```

Dari 106 tabel di `oriondb`, **nol** bertipe landing page.

Konsekuensi untuk cutover:

- Migrasi `2026_10_08_233154_create_landing_page_builder_tables` **pernah
  dijalankan hanya di `percetakan_erp_testing`**, tidak di `oriondb`.
- Menyalakan flag di produksi tanpa migrasi = fitur tidak berfungsi + log warning
  pada setiap request homepage.
- Migrasi produksi adalah keputusan tersendiri yang **tidak diotorisasi** pada
  checkpoint ini.

Artinya: **backup yang ada saat ini tidak memuat tabel landing page.** Kalau
cutover proceeded tanpa migrasi, tidak ada backup yang bisa memulihkan data
landing page karena datanya belum ada.

### Konsekuensi untuk backup

Sebelum cutover, diperlukan:

1. Migrasi disetujui & dijalankan ke `oriondb`.
2. Backup baru setelah migrasi (agar tabel landing page ikut ter-backup).
3. Restore test ke database terisolasi (mis. `orion_restore_verify`), lalu
   hapus. **Perlu approval karena berupa operasi destruktif.**

### Operatorresponsible

**Belum ditetapkan.** Ini gate yang belum terpenuhi.

---

## 7. MONITORING

### Yang sudah disiapkan

#### A. Health check homepage — `scripts/check_public_homepage.php`

Sudah diuji terhadap aplikasi nyata:

```
$ php scripts/check_public_homepage.php http://localhost/grafikaorion/ existing
HTTP status: 200
Durasi     : 191 ms
Ukuran body: 32233 byte
HASIL: SEHAT
```

Dan terhadap kondisi rusak (negative test):

```
$ php scripts/check_public_homepage.php .../halaman-tidak-ada-xyz existing
HTTP status: 404
HASIL: TIDAK SEHAT
  - HTTP 404 (halaman tidak ditemukan / forbidden)
  - Homepage existing tanpa dialog/form masuk - staf tidak bisa login
```

Yang dideteksi: HTTP 5xx, HTTP 4xx, timeout/host mati, kebocoran `PREVIEW MODE`
ke halaman publik, flag OFF tapi halaman builder, flag ON tapi fallback, dan
homepage tanpa form login. Exit code 1 saat tidak sehat → siap dipakai cron/CI.

#### B. Sinyal log resolver

`LandingPageHomepageResolver` sudah menulis `Log::warning` yang **terbukti
tereksPOS di log harian**:

```
[2026-10-09 08:29:54] testing.WARNING: Landing page resolver: invalid content structure {"revision_id":3}
[2026-10-09 08:29:54] testing.WARNING: Landing page resolver: unsupported schema version {"revision_id":4,"schema_version":99}
```

Tiga sinyal yang bisa dipantau:

| Pesan log | Arti | Tindakan |
|-----------|------|----------|
| `unsupported schema version` | Versi schema publish ≠ allowlist | Publish ulang dengan versi benar |
| `invalid content structure` | `content_json` rusak | Perbaiki revision |
| `read failure, fallback to existing` | DB tidak terbaca / **tabel tidak ada** | Cek migrasi produksi |

Log: `storage/logs/laravel-YYYY-MM-DD.log` (rotasi harian, `LOG_LEVEL=debug`).

### Prosedur review log

```bash
grep "Landing page resolver" storage/logs/laravel-$(date +%Y-%m-%d).log
grep -c "read failure, fallback to existing" storage/logs/...   # harus 0
```

### Yang BELUM ada (tidak dipasang tanpa approval)

- Alert service eksternal (Uptime monitor, Sentry, dsb.) — **belum**.
- Dashboard metrik (request rate, p95 latency) — **belum**.
- Alert otomatis (Slack/Telegram) — **belum**.

Yang ada saat ini hanyalah **skrip manual**, belum ada yang berjalan otomatis
dan belum ada yang mengirim notifikasi.

### Kriteria trigger rollback

| Trigger | Ambang | Tindakan |
|---------|--------|----------|
| HTTP 5xx di homepage | ≥ 1 | **Rollback** |
| `read failure, fallback to existing` | ≥ 1 | **Rollback** + investigasi migrasi |
| Homepage tanpa form login | ≥ 1 | **Rollback** |
| Banner `PREVIEW MODE` bocor | ≥ 1 | **Rollback** + insiden keamanan |
| Latency homepage | > 3 detik rata-rata | Investigasi dulu |
| Gambar hero broken | ≥ 1 | Perbaiki konten, publish ulang |

---

## 8. CACHE STRATEGY

### Cache yang ada saat ini

| Cache | Status | Catatan |
|-------|--------|---------|
| **Config cache** | Digunakan di produksi (`config:cache`) | Flag ter-resolve ke `false` dengan benar |
| **Route cache** | Ada di repo (`bootstrap/cache/routes-v7.php`) | Tidak diubah 4D.1/4D.2 |
| **View cache** | Standar Laravel | Tidak diubah |
| **App settings** | `Cache::rememberForever('app_settings_current')` | Di-invalidate via `AppSetting::clearCache()` |
| **Published revision** | **TIDAK di-cache** | Resolver query DB tiap request |
| **Homepage (flag OFF)** | `no-store, no-cache, must-revalidate` | Tidak diubah |
| **Homepage (flag ON)** | `public, max-age=60` | 60 detik, hanya untuk tamu |

### Keputusan: tidak ada aggressive caching

Published revision **sengaja tidak di-cache**. Alasannya:

- Homepage adalah halaman publik dengan traffic tinggi, jadi caching memang
 midtahedral. Tapi belum ada **bukti** (tidak ada load test, tidak ada data
  traffic) yang membenarkan aggressive caching.
- Menempelkan cache層 di `LandingPageRevision` berisiko menyajikan konten basi
  setelah publish — dan belum ada mekanisme invalidasi yang teruji.
- 60 detik `max-age` pada respons HTTP adalah titik tengah yang wajar:visitor
 Neighboran tidak”时强求 60 detik, dan rollback (perubahan env) tetap langsung
  berlaku karena `config:clear`.

Kalau nanti perlu aggressive caching, prasyaratnya: load test + mekanisme
invalidasi pada publish + tes.

### Invalidasi cache

| Aksi | Perlu invalidasi? | Alasan |
|------|-------------------|--------|
| Publish revision baru | **Ya, untuk visited cache** | Konten berubah. TTL 60 detik menangani sementara ini. |
| Rollback flag OFF | `config:clear` / `config:cache` | WAJIB — kalau tidak, flag lama masih berlaku dari cache. |
| Ubah `.env` (apa pun) | `config:clear` | WAJIB. |
| Restore revision lama | Tidak | Draft saja yang berubah, tidak ada cache publik. |

### Peringatan

Kalau produksi memakai `config:cache`, **tanpa `config:clear` perubahan `.env`
akan tidak berefek sama sekali** dan rollback akan gagal diam-diam. Ini-risk
tersesar di prosedur rollback. Sudah dicatat di runbook.

### View cache

`landing-page-public.blade.php` dan partial mengikuti cache view Laravel
standar. Kalau `view:cache` dipakai,rememberm untuk menjalankan
`php artisan view:clear` setelah mengubah Blade.

---

## 9. FULL REGRESSION

### Perintah

```bash
php vendor/bin/phpunit -c phpunit.xml
```

Dijalankan **berurutan**, tanpa proses PHP lain yang menyentuh database
(sesuai prosedur bagian 3).

### Hasil aktual

| Metrik | Nilai |
|--------|-------|
| Tests | **1049** |
| Assertions | **4841** |
| **Failures** | **0** |
| **Errors** | **0** |
| Skipped | **1** |
| Risky | **0** |
| Deprecations | **160** (metadata `/** @test */` PHPUnit 11) |
| Durasi | 8 menit 7 detik |

### Perbandingan dengan baseline 4D.1

| | Baseline (4D.1) | 4D.2 | Delta |
|---|---|---|---|
| Tests | 1022 | 1049 | **+27** |
| Assertions | 4754 | 4841 | **+87** |

Delta **reconcile sempurna**: +21 test audit login + 6 test penjaga isolasi
= **+27**. Tidak ada test yang hilang.

### Test yang di-skip

`RawSqlAuditTest` (1) — *"Route laporan penjualan tidak ditemukan - test runtime
not applicable"*. Sudah ada sebelum checkpoint ini, **bukan** dari pekerjaan
Landing Page Builder. **Tidak ada suppress** terhadap skip maupun failure.

### Modul ERP

Semua modul tetap hijau: Kasir, SPK, Produksi, Collecting, Pembayaran, Stok,
Pembelian, Jurnal, Login, RBAC. Nol regresi.

---

## 10. RELEASE GATES

| # | Gate | Status | Bukti |
|---|------|--------|-------|
| 1 | Login/homepage audit PASS | ⚠️ **PARTIAL** | 19/21 skenario PASS; 2 skenario **membuktikan blocker B2** |
| 2 | Full regression PASS | ✅ **PASS** | 1049 tests, 0 failure, 0 error |
| 3 | Browser E2E PASS (B1–B13) | ❌ **FAIL** | 0 skenario dieksekusi, status PENDING |
| 4 | Media security PASS | ✅ **PASS** | 25 test media, 0 skip (GD aktif) |
| 5 | Published renderer PASS | ✅ **PASS** | 32 test resolver, semua fallback matrix hijau |
| 6 | Feature flag rollback PASS | ⚠️ **PARTIAL** | Rollback teruji di test; **belum** diuji di browser nyata |
| 7 | Monitoring prepared | ⚠️ **PARTIAL** | Health check + log signal ada & teruji; **tidak ada** alerting otomatis |
| 8 | Backup restore verified | ❌ **FAIL** | Integritas berkas OK, **restore UNVERIFIED** |
| 9 | Stakeholder sign-off recorded | ❌ **FAIL** | Belum ada |
| 10 | Staging soak test completed | ❌ **FAIL** | Tidak ada environment staging |
| 11 | **Migrasi produksi oriondb** | ❌ **FAIL** | **Tabel tidak ada di oriondb** |

### Gate tambahan yang belum ada di daftar asli tetapi wajib

| Gate | Status |
|------|--------|
| **Jalan masuk tersedia saat flag ON** | ❌ FAIL (B2) |
| **Health check otomatis + alert** | ❌ FAIL |
| **PIC rollback ditetapkan** | ❌ FAIL |
| **Prosedur CI terisolasi** | ❌ FAIL |

### Ringkasan gate

- ✅ Lulus: 3 (regression, media security, renderer)
- ⚠️ Sebagian: 3 (audit login, rollback, monitoring)
- ❌ Gagal: 6 (E2E, backup restore, sign-off, staging soak, migrasi oriondb, jalan masuk)

---

## 11. STAKEHOLDER SIGN-OFF

| Peran | Nama | Tanggal | Status |
|-------|------|---------|--------|
| Administrator sistem | — | — | **BELUM** |
| Pemilik bisnis / pemberi persetujuan homepage | — | — | **BELUM** |
| Operasional (rollback PIC) | — | — | **BELUM** |

**Tidak ada sign-off yang tercatat.** Ini gate yang berdiri sendiri.

---

## 12. KEPUTUSAN GO / NO-GO

# ❌ **NO-GO**

### Alasan

1. **B1 — `oriondb` tidak punya tabel landing page.** Fitur tidak akan berfungsi di produksi. Menyalakan flag tanpa migrasi = fitur mati + log penuh. Gate ini berdiri sendiri dan **tidak diotorisasi** pada checkpoint ini.
2. **B2 — Tidak ada jalan masuk saat flag ON.** Menyalakan flag menyebabkan **totalkunci staf**. Ini risiko operasional serius, terbukti oleh test, bukan dugaan.
3. **Browser E2E nol eksekusi.** 37 skenario Acceptance belum dijalankan.
4. **Restore backup UNVERIFIED.** Backup yang ada bahkan **tidak memuat** tabel landing page.
5. **Tidak ada sign-off stakeholder.**
6. **Tidak ada staging soak test.**
7. **Tidak ada alerting otomatis** — rollback bergantung pada orang yang noticing.

Poin 1 dan 2 adalah pemblokir **hard**: meskipun semua gate lain lolos, dua
hal ini saja sudah cukup untuk NO-GO.

### Apa yang sudah benar

- Infrastruktur cutover (flag, resolver, fallback, renderer reuse) solid dan
  teruji 32 skenario.
- Regresi ERP bersih: 1049 test, 0 failure.
- Isolasi database uji sudah diperkuat dan terbukti memblokir `oriondb`.
- Rollback didokumentasikan dan runbook-nya siap.
- Monitoring dasar sudah disiapkan dan diuji terhadap server nyata.

Masalahnya bukan kualitas kode — masalahnya **belum ada yang mengujinya di
browser, dan dua prasyarat produksi belum terpenuhi**.

---

## 13. PROSEDUR ROLLBACK (AKURAT)

Dokumen lengkap: `ROLLBACK_RUNBOOK_LANDING_PAGE.md`.

### Ringkas

```bash
# 1. Pastikan tidak ada proses PHP lain
tasklist | findstr php

# 2. Matikan flag di .env (hapus baris LANDING_PAGE_BUILDER_ENABLED=true)

# 3. WAJIB - tanpa ini flag tidak berubah karena config cache
php artisan config:clear

# 4. Kalau produksi memakai config cache
php artisan config:cache

# 5. (opsional)
php artisan cache:clear
```

### Verifikasi

```bash
php scripts/check_public_homepage.php http://localhost/grafikaorion/ existing
# harus: HASIL: SEHAT, HTTP 200, ada dialog masuk
```

Manual: buka homepage → katalog layanan + tulisan + tombol "Masuk" →
staf bisa login seperti biasa.

### Yang tidak terjadi

Tidak ada tabel dihapus, draft/revision tidak hilang, media tidak terhapus,
tidak perlu rollback migration, tidak perlu restore backup, `routes/web.php`
dan `login.blade.php` tidak berubah. Rollback **fully reversible**.

### ⚠️ Peringatan terbesar

Dengan `config:cache` aktif, **tanpa `config:clear` rollback akan gagal
diam-diam** — flag lama masih terbaca dari cache dan homepage tetap builder.
Langkah `config:clear` **bukan opsional**.

### Jalur alternatif (tanpa sentuh `.env`)

```sql
UPDATE landing_pages SET published_revision_id = NULL, updated_at = NOW() WHERE slug = 'home';
-- lalu: php artisan cache:clear
```

Mengubah data produksi — gunakan hanya sebagai jalan terakhir, dengan
kredensial benar dan pencatatan audit.

---

## 14. REMAINING BLOCKERS

### Hard blocker (wajib selesai sebelum 4D.3)

| ID | Blocker | Solusi yang diusulkan |
|----|---------|-------------------------|
| **B1** | Tabel landing page tidak ada di `oriondb` | Approval + jalankan migrasi, lalu backup ulang |
| **B2** | Tidak ada jalan masuk saat flag ON | Opsi A (fallback `url.intended`) + Opsi B (partial login dialog) |
| B3 | Browser E2E 0/37 skenario | Jalankan prosedur bagian 4 |
| B4 | Restore backup UNVERIFIED | Restore test ke DB terisolasi (perlu approval) |
| B5 | Tidak ada sign-off stakeholder | Kumpulkan tanda tangan |
| B6 | Tidak ada staging soak test | Siapkan clone + 24–48 jam observasi |
| B7 | Tidak ada alerting otomatis | Pasang uptime monitor (perlu approval) |
| B8 | Rollback PIC belum ditetapkan | Tetapkan nama & kontak |
| B9 | Isolasi CI belum ada | Terapkan usulan bagian 3 |

### Non-blocking (boleh setelah 4D.3)

| Item | Severity |
|------|----------|
| Lazy loading juga dipakai untuk gambar hero (b Ideally tidak) | Rendah |
| Gambar tidak di-resize saat upload | Rendah |
| Multi-page support (slug `home` saja) | Rendah |
| Resolver belum menegakkan regex ID section | Rendah |
| Preview masih inline CSS | Rendah |

---

## 15. REKOMENDASI

### 4D.3 — TIDAK AKTIVASI. Urutan yang saya sarankan:

1. **Approval migrasi** `oriondb` → jalankan migrasi, **lalu** backup ulang
   (backup lama tidak mengcover tabel baru).
2. **Perbaiki B2** — implementasikan Opsi A sebagai jaring pengaman, Opsi B
   sebagai solusi tuntas. Butuh approval karena menyentuh view login.
3. **Jalankan browser E2E** B1–B37 terhadap `percetakan_erp_testing` dengan
   flag ON di lokal. Isi semua kolom.
4. **Restore test** ke DB terisolasi.
5. **Pasang monitoring** + alerting, tetapkan rollback PIC.
6. **Soak test di staging** 24–48 jam dengan flag ON.
7. **Kumpulkan sign-off.**
8. Baru **4D.4 — ACTIVATION** menyalakan flag di produksi dengan satu revision
   yang sudah ditinjau.

### Aktifkan lebih awal?

Tidak我自己 sarankan. Alasannya bukan 代码质量 — semuanya hijau. Alasan-nya
adalah dua hal yang **belum diuji dengan cara yang sama seperti pengguna
memakai aplikasi**: browser sungguhan, dan database produksi. Menyalakan flag
sebelum keduanya terbukti berarti_optimasi buta untuk masalah yang belum pernah
dialami.

---

## LAMPIRAN A — Berkas yang dibuat/diubah di 4D.2

### Baru

| Berkas | Isi |
|--------|-----|
| `tests/Feature/LandingPageLoginRouteAuditTest.php` | 21 test audit login (bagian 2) |
| `tests/Feature/TestDatabaseIsolationGuardTest.php` | 6 test penjaga isolasi (bagian 3) |
| `scripts/check_public_homepage.php` | Health check homepage (bagian 7) |
| `ROLLBACK_RUNBOOK_LANDING_PAGE.md` | Runbook rollback (bagian 13) |
| `LANDINGPAGE_BUILDER_CHECKPOINT4D2_RELEASE_GATE_REPORT.md` | Laporan ini |

### Diubah

| Berkas | Perubahan |
|--------|-----------|
| `tests/TestCase.php` | Guard database diperkuat (denylist `oriondb`, pesan jelas) |

### Tidak disentuh

`routes/web.php` · `App\Http\Controllers/AuthController.php` ·
`LandingPageHomepageResolver.php` · `config/landing_page_builder.php` ·
`login.blade.php` · `.env` · modul ERP mana pun · `oriondb`.

---

## LAMPIRAN B — Reproduksi

```bash
# Flag harus OFF
php artisan config:clear
php artisan tinker --execute="echo var_export(config('landing_page_builder.enabled'), true);"
# → false

# Audit login + penjaga isolasi
php vendor/bin/phpunit tests/Feature/LandingPageLoginRouteAuditTest.php
php vendor/bin/phpunit tests/Feature/TestDatabaseIsolationGuardTest.php

# Guard menolak oriondb (Windows PowerShell)
$env:DB_DATABASE="oriondb"
php vendor/bin/phpunit --filter phpunit_xml_menunjuk tests/Feature/TestDatabaseIsolationGuardTest.php
# → TEST DIBATALKAN
Remove-Item Env:\DB_DATABASE

# Health check aplikasi nyata
php scripts/check_public_homepage.php http://localhost/grafikaorion/ existing
```

---

## STATUS AKHIR

# `LANDING PAGE BUILDER — PUBLIC CUTOVER NO-GO`

Flag tetap **OFF**. `oriondb` **tidak dimodifikasi**. Homepage existing
**tidak berubah**. Sistem autentikasi **tidak direfaktor**.

Dua blocker keras ditemukan dan terbukti: **`oriondb` belum punya tabel landing
page**, dan **homepage builder tidak menyediakan jalan masuk**. Keduanya harus
beres sebelum flag boleh dinyalakan.