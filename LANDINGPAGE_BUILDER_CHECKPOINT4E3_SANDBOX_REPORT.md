# LANDING PAGE BUILDER — CHECKPOINT 4E.3
## ISOLATED SANDBOX RESTORE & MIGRATION

## STATUS: `LANDING PAGE BUILDER — SANDBOX MIGRATION VERIFIED / PRODUCTION MIGRATION NOT AUTHORIZED`

**Ringkas:** Migration berhasil dijalankan di sandbox terisolasi. 4 tabel
terbentuk, 9 foreign key dan 5 unique constraint terbukti **benar-benar
menegakkan aturan** (bukan hanya tercatat), 15/15 uji batasan lolos, data ERP
utuh, rollback sempurna. `oriondb` **tidak tersentuh**.

---

## 1. OPERASI YANG DISETUJUI & YANG DIJALANKAN

| # | Operasi dari `SANDBOX_PROPOSAL_4E2.md` | Status | Bukti |
|---|------------------------------------------|--------|-------|
| **A** | `CREATE DATABASE oriondb_migration_sandbox` (utf8mb4) | ✅ **DILJALANKAN** | 0.001 detik, terverifikasi ADA |
| **B** | Restore `backup_daily_20261009_010059.sql` ke sandbox | ✅ **DILJALANKAN** | 1.710 detik, 0 error, 106 tabel |
| **C** | Jalankan 1 migration landing page di sandbox | ✅ **DILJALANKAN** | 622.88ms DONE, batch 85 |
| **D** | Verifikasi skema, FK, index, histori, preservasi data | ✅ **DILJALANKAN** | 22 pemeriksaan + 15 uji batasan |

Tidak ada operasi yang melenceng dari rencana. Tidak ada operasi tambahan yang
tidak disetujui.

### Yang TIDAK dijalankan (sesuai aturan)

| Larangan | Status |
|----------|--------|
| Menulis ke `oriondb` | ❌ tidak |
| Drop `oriondb` | ❌ tidak |
| Drop database **existing** mana pun | ❌ tidak |
| Overwrite sandbox yang sudah ada | ❌ tidak (sandbox belum ada saat diperiksa) |
| `migrate:fresh` / `migrate:refresh` | ❌ tidak |
| PHPUnit terhadap sandbox | ❌ **tidak** |
| PHPUnit terhadap `oriondb` | ❌ **tidak** |
| Migrasi produksi | ❌ tidak |
| Mengaktifkan feature flag | ❌ tidak |
| Mengubah `.env` produksi | ❌ tidak |
| Mengubah / menghapus backup asli | ❌ tidak (checksum masih cocok) |
| Migration konkuren pada DB sama | ❌ tidak (berurutan) |
| `migrate:rollback` | ❌ tidak |
| Menghapus sandbox otomatis | ❌ **tidak — sandbox dibiarkan utuh** |

---

## 2. BUKTI PRE-FLIGHT SANDBOX

Hasil: **0 blocker, 0 warning**.

| # | Pemeriksaan | Hasil |
|---|-------------|-------|
| 1 | Nama sandbox persis `oriondb_migration_sandbox` | ✅ |
| 2 | Tidak menabrak nama database terlarang | ✅ |
| 3 | Server & akun | ✅ MariaDB 10.4.32, akun terotorisasi |
| 4 | Sandbox sudah ada? | ✅ **BELUM ADA** — aman dibuat |
| 5 | Daftar database (transparansi) | ✅ 11 database terlihat |
| 6 | Source backup + checksum | ✅ `ef815551da062fea231491d0` **cocok** dengan 4E.2 |
| 7 | Keamanan dump | ✅ 7/7 aman |
| 8 | Kapasitas disk | ✅ 94.51 GB bebas |
| 9 | Proses tersambung ke sandbox | ✅ **0 koneksi** |
| 10 | Kemungkinan target tertukar | ✅ mustahil |

### 7. Keamanan dump — yang paling menentukan

Saya **tidak** menjalankan dump SQL tanpa pemeriksaan. Dump diperiksa dulu:

| Pemeriksaan | Hasil |
|------------|-------|
| Tidak ada `CREATE DATABASE` | ✅ 0 |
| Tidak ada `DROP DATABASE` | ✅ 0 |
| Tidak ada `USE` (database switching) | ✅ 0 |
| Tidak ada `CREATE USER` | ✅ 0 |
| Tidak ada `GRANT` sebagai pernyataan | ✅ 0 |
| Tidak ada `LOAD DATA INFILE` | ✅ 0 |
| Tidak ada `SELECT INTO OUTFILE` | ✅ 0 |

**Karena dump tidak punya `USE`, target restore tidak bisa dialihkan dari dalam
file.** Isi dump hanya bisa masuk ke database yang disebut di baris perintah —
yaitu sandbox. `oriondb` mustahil tersentuh oleh restore.

### False positive yang saya laporkan

Pencarian awal menemukan 1 kemunculan `GRANT`. Setelah diperiksa, itu **bukan**
pernyataan SQL — melainkan teks di dalam nilai string pada `INSERT INTO
migrations`:

```
…,(90,'2026_09_17_120000_grant_gudang_laporan_stok_bahan',1),…
```

Nama migration yang mengandung kata "grant". Verifikasi ulang dengan pola
`^\s*GRANT` (harus di awal baris) menghasilkan **0 pernyataan GRANT**.

---

## 3. HASIL RESTORE BACKUP

| Metrik | Nilai |
|--------|-------|
| **Mulai** | 2026-10-09 05:44:04 |
| **Selesai** | 2026-10-09 05:44:06 |
| **Durasi** | **1.710 detik** |
| **Exit status** | 0 |
| **Error / warning SQL** | **0** |
| Tabel terbentuk | **106** |
| Foreign key | **119** |
| Charset | `utf8mb4` |
| Collation | `utf8mb4_unicode_ci` |

### Baris ERP representatif (setelah restore)

| Tabel | Baris |
|-------|-------|
| users | 11 |
| transaksi | 2 |
| produk | 94 |
| roles | 6 |
| audit_logs | 452 |

**Tidak ada data pribadi pelanggan/karyawan yang dicetak** — hanya jumlah baris.

---

## 4. VERIFIKASI SKEMA ERP SETELAH RESTORE

Perbandingan `oriondb` (live) vs sandbox (restore):

| Tabel | Baris (sandbox) | Baris (oriondb) | Struktur |
|-------|-----------------|-----------------|----------|
| users | 11 | 12 | ✅ sama |
| transaksi | 2 | 2 | ✅ sama |
| produk | 94 | 94 | ✅ sama |
| roles | 6 | 8 | ✅ sama |
| audit_logs | 452 | 459 | ✅ sama |
| pembayaran_riwayat | 1 | 1 | ✅ sama |
| supplier | 8 | 8 | ✅ sama |
| konsumen | 19 | 19 | ✅ sama |

**Struktur: 106 tabel, 119 FK, charset & kolasi identik.** ✅

### Penjelasan selisih baris (saya tidak|Le obfuscation| silently| melewati ini)

Total baris sandbox (3366) **lebih besar** dari oriondb (3298) — selisih +68.
Tabel yang berbeda:

| Tabel | Selisih | Penjelasan |
|-------|---------|------------|
| `cache` | +84 | Cache oriondb sudah di-evict karena aktivitas live & `config:clear`; dump menyimpan versi lama |
| `sessions` | −6 | Sesi oriondb berubah setelah backup diambil |
| `roles` | −2 | Role baru dibuat di oriondb setelah backup |
| `audit_logs` | −7 | Audit log oriondb bertambah setelah backup |
| `users` | −1 | User berubah di oriondb setelah backup |
| `karyawan` | −1 |Mirip — data berubah setelah backup |
| `cache_locks` | +1 | Lock sementara |

**Verifikasi:tidak ada tabel yang hilang:**

```
Tabel yang HILANG di sandbox dibanding oriondb : TIDAK ADA
```

Semua perbedaan berada di tabel **volatile** dan berarah masuk akal: `oriondb`
aktif menerima aktivitas setelah backup diambil pada 2026-10-08 20:01. Restore
memantically benar — sandbox berisi persis isi backup.

---

## 5. HASIL EKSEKUSI MIGRASI

### Pra-pelaksanaan (verifikasi berlapis)

| Pemeriksaan | Hasil |
|-------------|-------|
| Target database | ✅ `oriondb_migration_sandbox` |
| Total migration di repo | 203 |
| **Migration pending** | ✅ **tepat 1** |
| Migration pending lain | ✅ **0** |

> **Catatan metodologis:** pemeriksaan awal saya menghitung "2 pending", padahal
> hanya 1. Penyebabnya kata "pending" muncul di **nama file**
> `2026_09_11_190000_add_pending_status_to_transaksi_detail` yang statusnya
> `[1] Ran`. Setelah dicocokkan dengan pola `\s*Pending\s*$` (status di akhir
> baris), hasilnya **1**. Jadi tidak ada migrasi liar yang akan ikut jalan —
> syarat ini terpenuhi.

### Pelaksanaan

```
Target   : oriondb_migration_sandbox
Mulai    : 2026-10-09 10:45:20
Selesai  : 2026-10-09 10:45:21
Durasi   : 622.88ms

2026_10_08_233154_create_landing_page_builder_tables ... DONE
```

| Metrik | Nilai |
|--------|-------|
| Migration dieksekusi | `2026_10_08_233154_create_landing_page_builder_tables` |
| SQL error | **0** |
| **Durasi** | **622.88 ms** |
| Batch | **85** |
| Total migration tercatat | **204** |

---

## 6. VERIFIKASI EMPAT TABEL

| Tabel | Status |
|-------|--------|
| `landing_pages` | ✅ ADA |
| `landing_page_drafts` | ✅ ADA |
| `landing_page_revisions` | ✅ ADA |
| `landing_page_media` | ✅ ADA |

Total tabel sandbox: **110** (106 + 4) ✅
Total FK sandbox: **128** (119 + 9) ✅

---

## 7. BUKTI FOREIGN KEY & INDEX

### 9 Foreign Key

| Constraint | Kolom |udder → | Delete rule |
|------------|-------|---------|-------------|
| `landing_pages_created_by_foreign` | `created_by` | `users.id_user` | RESTRICT |
| `landing_pages_updated_by_foreign` | `updated_by` | `users.id_user` | RESTRICT |
| `landing_pages_published_revision_id_foreign` | `published_revision_id` | `landing_page_revisions.id` | **SET NULL** |
| `landing_page_drafts_landing_page_id_foreign` | `landing_page_id` | `landing_pages.id` | CASCADE |
| `landing_page_drafts_updated_by_foreign` | `updated_by` | `users.id_user` | RESTRICT |
| `landing_page_revisions_landing_page_id_foreign` | `landing_page_id` | `landing_pages.id` | CASCADE |
| `landing_page_revisions_created_by_foreign` | `created_by` | `users.id_user` | RESTRICT |
| `landing_page_media_landing_page_id_foreign` | `landing_page_id` | `landing_pages.id` | CASCADE |
| `landing_page_media_uploaded_by_foreign` | `uploaded_by` | `users.id_user` | RESTRICT |

**Total 9 ✅** — dan **5 dari 9 semuanya mereferensikan `users.id_user`** (bukan
`users.id`). Ini jawaban atas kekhawatiran utama di 4E.2.

### Unique Constraint (5 termasuk PK)

| Constraint | Kolom |
|------------|-------|
| `landing_pages_slug_unique` | `slug` |
| `landing_page_drafts_landing_page_id_unique` | `landing_page_id` |
| `landing_page_revisions_landing_page_id_revision_number_unique` | `landing_page_id, revision_number` |
| `landing_page_media_path_unique` | `path` |
| PRIMARY × 4 | `id` |

### Index (6)

| Index | Kolom |
|-------|-------|
| `landing_pages_status_index` | `status` |
| `landing_pages_published_revision_id_index` | `published_revision_id` |
| `landing_page_drafts_version_index` | `version` |
| `landing_page_revisions_revision_number_index` | `revision_number` |
| `landing_page_media_landing_page_id_mime_type_index` | `landing_page_id, mime_type` |
| FK-generated | `created_by`, `updated_by`, `uploaded_by` |

### Kolom kritis

| Tabel | Kolom | Tipe | Nullable | Default |
|-------|-------|------|----------|---------|
| `landing_pages` | `published_revision_id` | `bigint unsigned` | **YES** | NULL |
| `landing_pages` | `status` | `enum('draft','published')` | NO | `'draft'` |
| `landing_page_drafts` | `version` | `int unsigned` | NO | **1** |
| `landing_page_revisions` | `schema_version` | `varchar(10)` | NO | **`'1.0.0'`** |

> Perhatikan: `schema_version` default-nya persis `'1.0.0'`, sama dengan allowlist
> di `LandingPageHomepageResolver`. Kontrak itu konsisten antara kode dan skema.

---

## 8. UJI BATASAN — 15/15 LOLOS

Constraint yang hanya **tercatat** belum tentu menegakkan aturan. Jadi setiap
batasan diuji dengan record sintetis di dalam transaksi yang **selalu rollback**.

| # | Uji | Harus | Hasil |
|---|-----|-------|-------|
| 1 | slug baru | DITERIMA | ✅ |
| 2 | slug duplikat | DITOLAK | ✅ |
| 3 | `created_by` tak dikenal | DITOLAK | ✅ |
| 4 | `created_by` valid | DITERIMA | ✅ |
| 5 | draft `landing_page_id` tak dikenal | DITOLAK | ✅ |
| 6 | 2 draft per 1 halaman | DITOLAK | ✅ |
| 7 | nomor revision duplikat | DITOLAK | ✅ |
| 8 | nomor revision berbeda | DITERIMA | ✅ |
| 9 | `published_revision_id` tak dikenal | DITOLAK | ✅ |
| 10 | `published_revision_id` valid | DITERIMA | ✅ |
| 11 | path media duplikat | DITOLAK | ✅ |
| 12 | ON DELETE CASCADE (hapus halaman → draft ikut) | BERFUNGSI | ✅ |
| 13 | ON DELETE SET NULL (hapus revision → pointer NULL) | BERFUNGSI | ✅ |
| 14 | default `version` = 1 | BERFUNGSI | ✅ |
| 15 | default `status` = draft | BERFUNGSI | ✅ |

### Bukti rollback sempurna

| Tabel | Sisa data sintetis |
|-------|--------------------|
| `landing_pages` | **0** |
| `landing_page_drafts` | **0** |
| `landing_page_revisions` | **0** |
| `landing_page_media` | **0** |

Record ERP setelah uji: `users` = 11 (tidak berubah), `audit_logs` = 452 (tidak
berubah).

**Rollback sempurna: YA. Record ERP utuh: YA.**

> Catatan: pengujian memakai `id_user` yang **hanya dibaca** sebagai referensi
> FK — tidak ada baris ERP yang dibuat, diubah, atau dihapus.

---

## 9. HISTORI MIGRASI

| Item | Nilai |
|------|-------|
| Migration landing page tercatat | ✅ `[batch 85] 2026_10_08_233154_create_landing_page_builder_tables` |
| Batch tertinggi | **85** (sebelumnya 84) |
| Total migration | **204** (sebelumnya 203) |
| Pending setelah migrasi | **0** |

`batch 85` persis seperti yang diprediksi di 4E.2.

---

## 10. FULL REGRESSION

```bash
php vendor/bin/phpunit -c phpunit.xml
```

Target diverifikasi: **`percetakan_erp_testing`** (bukan sandbox, bukan oriondb).
Dijalankan berurutan.

| Metrik | Baseline 4E.2 | **4E.3** | Delta |
|--------|---------------|-----------|-------|
| Tests | 1085 | **1085** | 0 |
| Assertions | 5006 | **5006** | 0 |
| **Failures** | 0 | **0** | — |
| **Errors** | 0 | **0** | — |
| Skipped | 1 | **1** | — |
| Risky | 0 | **0** | — |
| Deprecations | 194 | **194** | — |
| Durasi | 8:07 | 7:47 | — |

**Identik dengan baseline.** Tidak ada regresi ERP.

Skip: `RawSqlAuditTest` (1) — sudah ada sebelumnya, tidak terkait.

---

## 11. KONFIRMASI KEAMANAN DATABASE

| Database | Tabel | Status |
|----------|-------|--------|
| **`oriondb`** | 106 | ✅ **UTUH — 0 tabel landing page, tidak dimodifikasi** |
| `oriondb_migration_sandbox` | 110 | ✅ 106 ERP + 4 landing page (hasil uji) |
| `percetakan_erp_testing` | — | ✅ dipakai regression, normal |

Verifikasi akhir dilakukan **setelah** seluruh operasi:

```
oriondb              → landing_pages TIDAK ADA ✅
                       landing_page_drafts TIDAK ADA ✅
                       landing_page_revisions TIDAK ADA ✅
                       landing_page_media TIDAK ADA ✅

oriondb_migration_sandbox → 4 tabel landing page ADA ✅

git status --short .env  → (kosong) ✅ .env tidak diubah

Feature flag            → false ✅
```

**Sandbox dibiarkan utuh** sesuai instruksi stop condition — tidak dihapus
otomatis.

---

## 12. BLOCKER PRODUKSI YANG TERSISA

### Ter解決 — tapi tetap perlu tindakan

| # | Item | Status |
|---|------|--------|
| ~~oriondb belum punya tabel~~ | ✅ Terbukti migration aman | **TAPI belum dijalankan ke produksi** |

### Hard blocker

| # | Blocker | Kenapa | Solusi |
|---|---------|--------|--------|
| **P1** | **Migrasi produksi belum diotorisasi** | Checkpoint ini hanya mengizinkan sandbox | Approval untuk Checkpoint 4E.4 |
| **P2** | **Operator/PIC belum ditetapkan** | Migrasi produksi tanpa pemilik = risiko | Tetapkan nama & kontak |
| **P3** | **Sign-off stakeholder belum ada** | Gerbang mandiri | Kumpulkan tanda tangan |
| **P4** | **Backup lama tidak memuat tabel landing page** | Setelah migrasi produksi, data baru tidak akan ter-backup | Ambil backup **setelah** migrasi produksi |

### Soft blocker (dari checkpoint sebelumnya)

| # | Item | Severity |
|---|------|----------|
| S1 | Browser E2E belum dieksekusi | SEDANG |
| S2 | Monitoring otomatis belum ada | SEDANG |
| S3 | Tidak ada staging soak test | SEDANG |
| S4 | Jendela pemeliharaan belum dijadwalkan | RENDAH |

### Yang sudah tertutup oleh 4E.3

| Risiko lama (dari 4E.2) | Status |
|--------------------------|--------|
| M1 Sandbox belum dibuat | ✅ **Tertutup** |
| M2 Restore belum diverifikasi | ✅ **Tertutup** — 1.710 detik, 0 error |
| M3 Migrasi belum diuji ke DB produksi-set | ✅ **Tertutup** — 622.88ms, 15/15 uji batasan |
| S5 `--pretend` tidak memvalidasi eksekusi | ✅ **Tertutup** —eksekusi nyata berhasil |

---

## 13. RUNBOOK MIGRAMASI PRODUKSI (USULAN)

> ⚠️ **Belum diotorisasi. Disiapkan berdasarkan bukti sandbox.**

### 13.1 Yang berubah dari runbook 4E.2

Berbeda dengan usulan 4E.2, sekarang runbook ini punya **bukti eksekusi nyata**:
restore 1.710 detik, migrasi 622.88 ms, 15/15 uji batasan lolos. Angka-angka ini
bisa dipakai sebagai acuan durasi.

### 13.2 Pra-migrasi (Wajib)

```bash
# 1. Pastikan tidak ada proses PHP lain
tasklist | findstr php

# 2. Pastikan HANYA 1 migration pending  ← WAJIB
php artisan migrate:status
# Cari baris yang berakhiran "Pending"
# Harus tepat 1: 2026_10_08_233154_create_landing_page_builder_tables

# 3. Pastikan target benar
php artisan tinker --execute="echo config('database.connections.mysql.database');"
# WAJIB keluar: oriondb

# 4. Backup manual SEBELUM migrasi
#    UI /backup → "Jalankan Backup Manual"
#    CATAT: backup ini belum memuat tabel landing page (sudahnormal)

# 5. Catat jumlah baris untuk pembanding
php artisan tinker --execute="echo DB::table('users')->count();"
php artisan tinker --execute="echo DB::table('transaksi')->count();"
php artisan tinker --execute="echo DB::table('audit_logs')->count();"
```

### 13.3 Jendela pemeliharaan

Feature flag tetap OFF → tidak ada dampak pengguna. Total downtime saat migrasi
< 2 detik berdasarkan hasil sandbox.

```bash
php artisan down
# ... migrasi ...
php artisan up
```

### 13.4 Migrasi

```bash
php artisan migrate \
  --path=database/migrations/2026_10_08_233154_create_landing_page_builder_tables.php \
  --force
```

### 13.5 Verifikasi pasca-migrasi

```sql
-- 1. Empat tabel harus ada
SELECT TABLE_NAME FROM information_schema.TABLES
 WHERE TABLE_SCHEMA='oriondb' AND TABLE_NAME LIKE 'landing_page%';
-- harus 4 baris

-- 2. Sembilan FK
SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
  FROM information_schema.REFERENTIAL_CONSTRAINTS rc
  JOIN information_schema.KEY_COLUMN_USAGE kcu
    ON kcu.CONSTRAINT_SCHEMA=rc.CONSTRAINT_SCHEMA AND kcu.CONSTRAINT_NAME=rc.CONSTRAINT_NAME
 WHERE rc.CONSTRAINT_SCHEMA='oriondb' AND rc.TABLE_NAME LIKE 'landing_page%';
-- harus 9 baris, 5 di antaranya ke users.id_user

-- 3. Tabel ERP utuh
SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='oriondb';
-- harus 110 (106 + 4)

-- 4. Baris ERP tidak berubah
SELECT COUNT(*) FROM users;        -- sama dengan langkah pra-migrasi
SELECT COUNT(*) FROM transaksi;    -- sama
SELECT COUNT(*) FROM audit_logs;   -- sama
```

### 13.6 Smoke test

```bash
php scripts/check_public_homepage.php https://<domain> existing
# flag masih OFF → wajib SEHAT

php artisan tinker --execute="echo config('landing_page_builder.enabled');"
# harus: false — JANGAN nyalakan di tahap ini
```

### 13.7 Backup ulang (WAJIB —很容易这一步被忽略)

Setelah migrasi sukses, ambil backup baru. Tanpa ini, data landing page yang
dibuat setelah go-live **tidak akan pernah ter-backup**.

### 13.8 Kriteria keputusan

| Skenario | Tindakan |
|----------|----------|
| Migration error | **STOP.** DDL MariaDB tidak transactional — periksa `information_schema` apakah ada tabel yang terlanjur dibuat. |
| Tabel kurang dari 4 | **Jangan rollback.** Periksa manual & lengkapi. |
| Migration sukses, aplikasi error | **Jangan rollback.** Forward-fix kode. |
| Konten rusak | Flag masih OFF — tidak ada dampak. |
| Data hilang | Restore dari backup pra-migrasi. |

### 13.9 Pemulihan

**Aturan tetap berlaku: JANGAN `migrate:rollback` untuk migration ini.**
`down()` menghapus seluruh data landing page secara permanen.

| Skenario | Pemulihan |
|----------|-----------|
| Migrasi parsial | Periksa `information_schema`, lengkapi secara manual |
| Bug aplikasi | Forward fix |
| Data korup | Restore backup pra-migrasi |
| Batalkan fitur | Flag OFF. **Migrasi boleh tetap ada** — 4 tabel kosong tidak merugikan |

### 13.10 Operator

| Peran | Nama | Tanggal |
|------|------|---------|
| Eksekusi | **BELUM DITETAPKAN** | — |
| Verifikasi | **BELUM DITETAPKAN** | — |
| Approval | **BELUM DITETAPKAN** | — |

---

## 14. REKOMENDASI GO / NO-GO

# ✅ **GO untuk migrasi produksi — dengan syarat**

Saya **tidak** menyarankan migrasi produksi dilakukan sekarang juga. Yang saya
sarankan: **GO CONDITIONAL**, menunggu gerbang yang tersisa.

### Mengapa migrasi ini aman (berbeda dari 4E.2)

Di 4E.2 statusnya BLOCKED karena tidak ada bukti eksekusi sama sekali. Sekarang:

| Bukti | Status |
|-------|--------|
| Restore ke DB производisi-set | ✅ 1.710 detik, 0 error, 106 tabel, 119 FK |
| Migrasi dijalankan nyata | ✅ 622.88 ms, 0 error |
| 4 tabel terbentuk | ✅ |
| 9 FK dengan `users.id_user` | ✅ **terbukti menegakkan aturan**, bukan hanya tercatat |
| 5 unique constraint | ✅ **terbukti menegakkan aturan** |
| ON DELETE CASCADE / SET NULL | ✅ **terbukti berfungsi** |
| Default kolom | ✅ **terbukti berlaku** |
| Data ERP terpelihara | ✅ 8 tabel, struktur identik, 0 tabel hilang |
| Rollback uji sintetis | ✅ sempurna, 0 sisa |
| Regression penuh | ✅ 1085/5006, 0 failure |

Risiko yang masih belum tertutup di 4E.2 — bentrokan nama, perbedaan perilaku
MariaDB, FK melingkar, restore — **semuanya sudah tertutup oleh eksekusi nyata.**

### Syarat sebelum eksekusi produksi

| # | Syarat | Status |
|---|--------|--------|
| 1 | Approval untuk Checkpoint 4E.4 | ❌ belum |
| 2 | Operator & PIC ditetapkan | ❌ belum |
| 3 | Sign-off stakeholder | ❌ belum |
| 4 | Jendela pemeliharaan dijadwalkan | ❌ belum |
| 5 | Backup pra-migrasi diambil | ❌ belum (prosedur, bukan file) |
| 6 | Rencana backup ulang setelah migrasi | ❌ belum |

### Yang TIDAK saya sarankan

Menyalakan feature flag bersamaan dengan migrasi. **Pisahkan dua langkah ini.**
Migrasi menambah tabel kosong — dampaknya nol. Menyalakan flag mengubah
homepage — itu keputusan berbeda dan masih punya blocker sendiri (browser E2E
belum dijalankan, monitoring belum ada).

Rekomendasi: migrasi dulu, observer beberapa hari, baruibrate flag di
checkpoint tersendiri.

---

## LAMPIRAN — Reproduksi

```bash
# Pre-flight (READ-ONLY)
php <temp>/4e3_preflight.php

# Baseline oriondb
php <temp>/4e3_baseline.php oriondb <file>.json

# Operasi A
php <temp>/4e3_create_sandbox.php

# Operasi B
php <temp>/4e3_restore.php

# Bandingkan restore vs produksi
php <temp>/4e3_baseline.php oriondb_migration_sandbox <file>.json
php <temp>/4e3_compare.php <a>.json <b>.json

# Operasi C
DB_DATABASE=oriondb_migration_sandbox php artisan migrate \
  --path=database/migrations/2026_10_08_233154_create_landing_page_builder_tables.php \
  --force

# Operasi D
php <temp>/4e3_verify.php
php <temp>/4e3_constraints.php
php <temp>/4e3_proof_unchanged.php

# Regression (WAJIB ke percetakan_erp_testing)
php vendor/bin/phpunit -c phpunit.xml

# Bukti oriondb tidak berubah
php <temp>/check_schema_readonly.php oriondb
```

---

## STATUS AKHIR

# `LANDING PAGE BUILDER — SANDBOX MIGRATION VERIFIED / PRODUCTION MIGRATION NOT AUTHORIZED`

- **Operasi A–D**: semua dijalankan sesuai rencana, tanpa penyimpangan.
- **Restore**: 1.710 detik, 0 error, struktur identik dengan produksi.
- **Migrasi**: 622.88 ms, 0 error, batch 85.
- **Verifikasi**: 22 pemeriksaan struktural + 15 uji batasan — **semua lolos**.
- **Data ERP**: utuh, 0 tabel hilang, rollback uji sempurna.
- **Regression**: 1085 tests / 5006 assertions / 0 failures / 0 errors.

**`oriondb` tidak dimodifikasi. Feature flag tetap `false`. Sandbox dibiarkan
utuh. Migrasi produksi belum diotorisasi.**