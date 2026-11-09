# LANDING PAGE BUILDER — CHECKPOINT 4E.2
## PRODUCTION DATABASE MIGRATION READINESS

## STATUS AKHIR: `LANDING PAGE BUILDER — MIGRATION READINESS BLOCKED`

**Alasan status:** Static audit LULOS 35/35 dan dry-run menghasilkan 22
pernyataan SQL yang bersih. **TAPI** bukti keberhasilan migrasi yang sebenarnya
(restore + apply di sandbox) **belum dijalankan** karena membutuhkan persetujuan
untuk `CREATE DATABASE` yang belum diberikan. Tanpa itu, saya tidak boleh
mengklaim migration readiness terverifikasi.

**`oriondb`:** READ-ONLY, **tidak dimodifikasi** (terverifikasi setelah semua
pekerjaan). **Feature flag:** `false`, `.env` tidak disentuh.

---

## PEMBEDAAN JENIS BUKTI

Checkpoint ini meminta ketiga jenis bukti dibedakan secara tegas. Berikut status
masing-masing:

| Jenis bukti | Status | Arti |
|-------------|--------|------|
| **Audit statis** (baca kode) | ✅ **SELESAI, LULOS 35/35** | Migration aman secara struktural |
| **Dry-run SQL** (SQL dibangun, tidak dieksekusi) | ✅ **SELESAI, 22 pernyataan** | Kita tahu persis SQL yang akan jalan |
| **Restore aktual** | ❌ **BELUM** | Butuh sandbox + approval |
| **Migrasi aktual** | ❌ **BELUM** | Butuh sandbox + approval |

Dua status terakhir **belum** tercapai. Karena itu status akhir **BLOCKED**,
bukan VERIFIED.

---

## 1. INSPEKSI SCHEMA PRODUKSI (READ-ONLY)

Sumber: `information_schema` via PDO. **Hanya SELECT/SHOW. Tidak ada satu pun
perintah tulis. Kredensial tidak pernah dicetak.**

### Server

| Item | Nilai |
|------|-------|
| Database | `oriondb` |
| **Versi** | **10.4.32-MariaDB** (bukan MySQL) |
| Karakter set | `utf8mb4` |
| Kolasi | `utf8mb4_unicode_ci` |
| `sql_mode` | `NO_ZERO_IN_DATE, NO_ZERO_DATE, NO_ENGINE_SUBSTITUTION` |
| `lower_case_table_names` | 1 |
| Engine | InnoDB (semua tabel) |
| Jumlah tabel | **106** |
| Total foreign key | **119** |

### Konvensi penamaan

Semua `snake_case`, jamak (`landing_pages`, `transaksi`, `pembayaran_riwayat`).
Toleran terhadap huruf besar (`lower_case_table_names=1`).

### Tabel Landing Page Builder di produksi

```
landing_pages             BELUM ADA
landing_page_drafts       BELUM ADA
landing_page_revisions    BELUM ADA
landing_page_media        BELUM ADA
```

**0 dari 4.** Migrasi memang belum pernah menyentuh produksi.

### Tabel yang dibutuhkan

| Tabel | Status |
|-------|--------|
| `users` | ADA |
| `audit_logs` | ADA |

### Primary key `users` — kompatibilitas FK

```
id_user   bigint(20) unsigned  auto_increment   NOT NULL
```

Cocok **persis** dengan `$table->unsignedBigInteger('created_by')` di migration.
Kalau PK-nya `int unsigned` (4 byte), FK `bigint` akan gagal — kasus ini tidak
terjadi. **FK aman.**

### Struktur `audit_logs` (kompatibilitas tanpa perubahan schema)

| Kolom | Tipe | Nullable |
|-------|------|----------|
| `id` | `bigint unsigned` | NO |
| `user_id` | `bigint unsigned` | YES |
| `user_label` | `varchar(100)` | YES |
| **`event`** | **`varchar(20)`** | **NO** |
| `auditable_type` | `varchar(100)` | NO |
| `auditable_id` | `bigint unsigned` | YES |
| `keterangan` | `varchar(255)` | YES |
| `old_values` / `new_values` | `longtext` | YES |
| `url` | `varchar(255)` | YES |
| `ip_address` | `varchar(45)` | YES |
| `created_at` | `timestamp` | YES |

**`audit_logs` KOMPATIBEL tanpa perubahan schema.** Semua event yang ditulis
`LandingPageBuilderController` sudah dihitung panjangnya:

| Event | Panjang | Batas varchar(20) |
|-------|---------|-------------------|
| `draft_saved` | 11 | ✅ |
| `published` | 9 | ✅ |
| `revision_restored` | **17** | ✅ (terpanjang) |
| `media_uploaded` | 14 | ✅ |
| `media_deleted` | 13 | ✅ |

`auditable_type` = `'LandingPage'` (11 char) muat di `varchar(100)`.

### Riwayat migration produksi

| Item | Nilai |
|------|-------|
| Migration tercatat | **203** |
| Total batch unik | **84** (batch tertinggi) |
| Migration landing page | **0** |

6 terakhir: batch 80–84, termasuk
`2026_10_08_131556_add_status_order_to_transaksi` dan `..._131557_backfill_...`
(batch 84).

---

## 1b. TEMUAN PENTING — HANYA 1 MIGRATION PENDING

Ini pemeriksaan yang **paling menentukan** untuk runbook:

```
Total file migration di repo  : 203
Tercatat di tabel migrations  : 203
PENDING (belum jalan di prod) : 1   ← hanya yang landing page!
Pending milik LAINNYA        : 0
```

```
[LANDING PAGE] 2026_10_08_233154_create_landing_page_builder_tables
```

Artinya `php artisan migrate` di produksi **akan aman** — tidak ada migration
lain yang ikut terbawa. Kalau ternyata ada yang pending, perintah itu akan
menjalankan semuanya dan bisa merusak. **Selalu periksa ini sebelum migrate.**

Catatan: 1 migration tercatat di produksi tapi file-nya hilang dari repo
(`2026_09_28_000001_add_tgl_dibutuhkan_pr_and_catatan_po_details`) — historis,
tidak berpengaruh.

### Urutan nama vs urutan batch

File migration landing page berada di **posisi 168 dari 203** berdasarkan nama
file (`2026_10_08_233154`), sementara produksi sudah menjalankan migration
bertanggal lebih akhir (`2026_10_31_*`, `2026_11_01_*`).

Ini **aman**, karena Laravel melacak lewat tabel `migrations`, bukan posisi nama.
Saat `migrate` dijalankan, hanya entri yang belum tercatat yang dieksekusi —
yaitu 1 migration kita, sebagai batch 85.

---

## 2. AUDIT STATIS MIGRATION

Berkas: `database/migrations/2026_10_08_233154_create_landing_page_builder_tables.php`
(102 baris).

**Catatan:** ini **satu** berkas migration yang membuat **empat** tabel — bukan
empat berkas terpisah.

### Hasil: 35 / 35 pemeriksaan LULOS

| Kelompok | Pemeriksaan | Hasil |
|----------|-------------|-------|
| **Keamanan `up()`** | tidak menghapus tabel | ✅ |
| | tidak `TRUNCATE` | ✅ |
| | tidak `DROP` kolom | ✅ |
| | tidak `->change()` | ✅ |
| | tidak `DELETE` data | ✅ |
| | tidak query mentah (`DB::statement`) | ✅ |
| **Keamanan `down()`** | hanya drop tabel & FK landing page | ✅ |
| | tidak menyentuh `users`/`audit_logs`/`transaksi` | ✅ |
| | tidak `DELETE` | ✅ |
| **Isi** | 4 tabel dibuat | ✅ |
| **Foreign key** | FK ke `users.id_user` (bukan `id`) | ✅ |
| | `updated_by` → `users` | ✅ |
| | `created_by` → `users` | ✅ |
| | `uploaded_by` → `users` | ✅ |
| | draft → `landing_pages` CASCADE | ✅ |
| | `published_revision_id` → revisions SET NULL | ✅ |
| | semua FK ke `users` RESTRICT | ✅ |
| **Kunci & index** | `slug` unik | ✅ |
| | `landing_page_id` draft unik | ✅ |
| | `(landing_page_id, revision_number)` unik | ✅ |
| | `path` (500) unik | ✅ |
| | `published_revision_id` nullable | ✅ |
| | `version` default 1 | ✅ |
| | `status` enum draft/published | ✅ |
| | `schema_version` default `1.0.0` | ✅ |
| | index `status` | ✅ |
| | index `(landing_page_id, mime_type)` | ✅ |
| **Kompatibilitas MariaDB** | memakai `$table->json()` | ✅ |
| | `created_at` `useCurrent()` | ✅ |
| | tanpa fitur MySQL 8-only (`->after()`) | ✅ |

### 3 kegagalan awal adalah BUG AUDIT SAYA, bukan bug migrasi

Audit versi pertama melaporkan 3 kegagalan. Setelah diverifikasi terhadap isi
berkas, **semuanya keliru**:

| Dilaporkan gagal | Kenyataan | Status |
|------------------|-----------|--------|
| "FK `uploaded_by` tidak ada" | Baris 73: `$table->foreign('uploaded_by')->references('id_user')...restrict` | ❌ false positive (regex) |
| "tidak memakai `$table->json()`" | Baris 68: `$table->json('metadata')->nullable()` | ❌ false positive (regex `json\(\)` vs `json('metadata')`) |
| "kolasi utf8 di backup" | Semua 106 tabel `utf8mb4` | ❌ false positive (`utf8(?!8)` cocok di `utf8m`) |

Saya laporkan ketiganya karena **menyembunyikan kegagalan audit adalah praktik
berbahaya** — kalau saya diamkan, kita akan mengira migrasi bermasalah padahal
mungkin bukan. Setelah pola regex diperbaiki, hasilnya 35/35.

---

## 3. VERIFIKASI BACKUP

### Konfigurasi

| Item | Nilai |
|------|-------|
| Service | `App\Services\BackupService` |
| Lokasi | `storage/app/backups/{daily,weekly,monthly,manual}/` |
| **Offsite** | **`salinan_backup`** (aktif — `BACKUP_SECONDARY_DISK`) |
| Retention | daily 7, weekly 4, monthly 12, manual 10 |
| Jumlah file | **19** |

### Inventaris + checksum SHA-256

| File | Ukuran | SHA-256 (12) |
|------|--------|--------------|
| `backup_daily_20261009_010059.sql` | 630.810 | `ef815551da06` |
| `backup_weekly_20261005_013100.sql` | 546.452 | `4dc9801dd67b` |
| `backup_daily_20261005_010059.sql` | 546.218 | `c2ac4b52c992` |
| `backup_daily_20261004_010100.sql` | 557.678 | `890cd68863e3` |
| `backup_daily_20261003_010059.sql` | 556.490 | `5e2412e6834d` |
| `backup_daily_20261002_010000.sql` | 533.743 | `3503413cad61` |

(13 file lain juga di checksum; lengkap ada di output eksekusi.)

### Verifikasi detail backup terbaru

| Pemeriksaan | Hasil |
|-------------|-------|
| Tidak kosong | ✅ |
| Header MariaDB dump | ✅ |
| Ada `CREATE TABLE` | ✅ **106** |
| Marker akhir (`UNLOCK TABLES`) | ✅ |
| Tidak terpotong | ✅ |
| Database asal terbaca | ✅ `oriondb` |
| Versi mysqldump | ✅ `10.4.32-MariaDB` (**identik dengan server**) |
| Karakter set | ✅ `utf8mb4` — **106/106** |
| Kolasi | ✅ `utf8mb4_unicode_ci` — **106/106**, tanpa variasi |
| Tabel ERP inti | ✅ users, transaksi, produk, roles, audit_logs, pembayaran_riwayat |
| Data | ✅ mengandung `INSERT INTO` |
| Stored routine/trigger | ✅ tidak ada |

### ⚠️ TEMUAN: BACKUP TIDAK MEMUAT TABEL LANDING PAGE

```
landing_pages / drafts / revisions / media  →  TIDAK ADA
```

**Konsekuensi:** backup yang ada dibuat **sebelum** migrasi masuk produksi.
Setelah migrasi produksi nanti, **WAJIB ambil backup baru** — kalau tidak,
tidak ada backup yang bisa memulihkan data landing page karena datanya belum
pernah ada.

### Restore: **UNVERIFIED**

Belum ada restore sungguhan. Status ini **tidak boleh** dianggap "siap" hanya
karena dokumen dan checksum sudah bagus.

---

## 4. DRY RUN SQL

Metode: `DB::connection()->pretend()` — SQL **dibangun** oleh Schema builder
Laravel, **tidak dieksekusi**. Dijalankan dengan `DB_DATABASE=percetakan_erp_testing`.

> **Limitasi yang harus jujur:** `pretend` **tidak membuktikan** SQL akan
> berhasil di server. Ia tidak menjalankan engine MariaDB, tidak mengecek
> benturan nama, tidak memvalidasi FK. Bukti keberhasilan hanya bisa didapat
> dari eksekusi nyata di sandbox.

### 22 pernyataan SQL yang akan dijalankan

**4 × CREATE TABLE:**

```sql
[01] create table `landing_pages` (
  `id` bigint unsigned not null auto_increment primary key,
  `slug` varchar(255) not null,
  `title` varchar(255) not null,
  `status` enum('draft','published') not null default 'draft',
  `published_revision_id` bigint unsigned null,
  `created_by` bigint unsigned not null,
  `updated_by` bigint unsigned not null,
  `created_at` timestamp null, `updated_at` timestamp null)
  default character set utf8mb4 collate 'utf8mb4_unicode_ci'

[05] create table `landing_page_drafts` (... `version` int unsigned not null default '1' ...)
[10] create table `landing_page_revisions` (... `schema_version` varchar(10) not null default '1.0.0',
     `created_at` timestamp not null default CURRENT_TIMESTAMP)
[15] create table `landing_page_media` (... `path` varchar(500), `metadata` json null ...)
```

**18 × ALTER TABLE** — semuanya `add index`, `add unique`, atau `add constraint`:
- 9 foreign key (4 → `landing_pages`, 4 → `users(id_user)`, 1 → `landing_page_revisions`)
- 5 unique constraint
- 4 index

### Analisis keamanan SQL

| Metrik | Nilai |
|--------|-------|
| `CREATE TABLE` | 4 |
| `ALTER TABLE` | 18 (semua `ADD`, tanpa `MODIFY`/`CHANGE`/`DROP`) |
| Mengandung `DROP` | **0** |
| Mengandung `TRUNCATE` | **0** |
| Mengandung `DELETE` sebagai pernyataan | **0** |
| Menyentuh tabel ERP | **Tidak** — `users` hanya sebagai **target** FK |

**Catatan false positive:** klasifikasi otomatis awalnya melaporkan
"`DELETE`: YA (BAHAYA)". Setelah diperiksa, itu cocok dengan frasa
`on delete cascade` / `on delete restrict` di dalam definisi FK — **bukan**
pernyataan `DELETE`. Diverifikasi ulang dengan pola yang membedakan keduanya:
**0 DELETE sungguhan.**

### Kompatibilitas charset

Tabel baru akan dibuat dengan `utf8mb4 collate 'utf8mb4_unicode_ci'` —
**identik** dengan 106 tabel produksi. Tidak ada risiko karakter multibyte rusak
saat dipindah ke tabel baru nanti.

---

## 5. STATUS SANDBOX: BELUM DISIAPKAN

**Tidak ada yang dieksekusi.** `CREATE DATABASE`, `DROP DATABASE`, dan `restore`
semuanya menunggu persetujuan eksplisit sesuai instruksi checkpoint.

Rencana lengkap ada di **`SANDBOX_PROPOSAL_4E2.md`**.

### Ringkasan yang perlu disetujui

| # | Operasi | Perintah inti | Risiko |
|---|---------|---------------|--------|
| A | Buat sandbox | `CREATE DATABASE oriondb_migration_sandbox ... utf8mb4` | Rendah |
| B | Restore backup | `mysql … oriondb_migration_sandbox < backup_daily_20261009_010059.sql` | Rendah |
| C | Jalankan 1 migration | `DB_DATABASE=oriondb_migration_sandbox php artisan migrate --path=…` | Rendah |
| D | Verifikasi | Query `information_schema` + `SELECT` | Sangat rendah |

Estimasi total: **± 3–5 menit**, tambahan ± 2 MB.

### Yang dijamin tidak dijalankan

| Larangan | Status |
|----------|--------|
| `CREATE/ALTER/DROP` di `oriondb` | ❌ tidak |
| Restore ke `oriondb` | ❌ tidak |
| `migrate:fresh` / `migrate:refresh` | ❌ tidak |
| `migrate:rollback` ke DB operasional | ❌ tidak |
| `DROP DATABASE` sandbox otomatis | ❌ **tidak akan dihapus otomatis** |
| PHPUnit terhadap sandbox | ❌ **tidak** — `RefreshDatabase` akan `migrate:fresh` dan **menghancurkan data produksi yang dipulihkan** |

Poin terakhir penting: regression PHPUnit **harus tetap** di
`percetakan_erp_testing`, tidak boleh diarahkan ke sandbox.

---

## 6. RISIKO DESAIN YANG TERIDENTIFIKASI

### Risiko 1 — Foreign key melingkar

```
landing_pages.published_revision_id      →  landing_page_revisions.id
landing_page_revisions.landing_page_id  →  landing_pages.id
```

MariaDB mengizinkan FK melingkar, tapi **INSERT baru ke `landing_pages` wajib
`published_revision_id = NULL`** — kolomnya memang `nullable`, jadi aman.
Kode aplikasi sudah membuat halaman dulu lalu `update()` pointer-nya terpisah.
Tidak perlu perubahan.

### Risiko 2 — `down()` menghapus seluruh data landing page

`down()` menjalankan `Schema::dropIfExists` untuk keempat tabel. Menjalankan
`migrate:rollback` akan **menghapus semua draft, revision, dan media** secara
permanen.

**Rekomendasi tegas: JANGAN pernah menjalankan `migrate:rollback` di produksi
untuk migration ini.** Kalau ada masalah, perbaiki maju (forward fix) atau
restore dari backup yang sudah diverifikasi.

### Risiko 3 — `audit_logs.event` varchar(20)

Teratasi: event terpanjang 17 karakter. Tidak perlu ubah schema.

---

## 7. FULL ERP REGRESSION

```bash
php vendor/bin/phpunit -c phpunit.xml
```

Dijalankan **berurutan** terhadap `percetakan_erp_testing` (bukan sandbox).

| Metrik | Baseline 4E.1 | **4E.2** | Delta |
|--------|---------------|-----------|-------|
| Tests | 1085 | **1085** | **0** |
| Assertions | 5006 | **5006** | **0** |
| **Failures** | 0 | **0** | — |
| **Errors** | 0 | **0** | — |
| Skipped | 1 | **1** | — |
| Risky | 0 | **0** | — |
| Deprecations | 194 | **194** | — |
| Durasi | 8:33 | 8:07 | — |

**Identik dengan baseline.** 4E.2 tidak mengubah kode aplikasi sama sekali —
hanya inspeksi, dry-run, dan verifikasi backup.

Skip: `RawSqlAuditTest` (1), sudah ada sebelumnya dan tidak terkait.

Modul ERP (Kasir, SPK, Produksi, Collecting, Pembayaran, Stok, Pembelian,
Jurnal, Login, RBAC) — **semua hijau**.

---

## 8. VERIFIKASI KEAMANAN DATABASE

Setelah seluruh pekerjaan:

```
$ php check_schema_readonly.php oriondb
landing_pages           TIDAK ADA
landing_page_drafts     TIDAK ADA
landing_page_revisions  TIDAK ADA
landing_page_media      TIDAK ADA
users                   ADA

$ git status --short .env
(kosong — .env tidak diubah)
```

**`oriondb` tidak memiliki satu pun tabel landing page → tidak dimodifikasi.**

---

## 9. RUNBOOK PRODUKSI (DISIAPKAN, TIDAK DIJALANKAN)

> ⚠️ **Jangan jalankan tanpa persetujuan.** Backup wajib diverifikasi lebih dulu.

### 9.1 Pra-migrasi

```bash
# 1. Pastikan tidak ada proses PHP lain
tasklist | findstr php

# 2. Pastikan hanya 1 migration pending  ← WAJIB, cegah migrasi liar
php artisan migrate:status

# 3. Backup manual SEBELUM migrasi (bukan terjadwal)
#    lewat UI: /backup → "Jalankan Backup Manual"

# 4. Catat checksum + waktu backup baru
```

### 9.2 Jendela pemeliharaan

1. **Fitur flag tetap OFF** saat migrasi — tidak ada dampak pengguna.
2. Aktifkan mode pemeliharaan (`php artisan down`).
3. Migration berjalan (± 1 detik untuk 4 tabel).
4. `php artisan up`.

### 9.3 Perintah migrasi

```bash
# Verifikasi target SEBELUM menjalankan
php artisan tinker --execute="echo config('database.connections.mysql.database');"
# WAJIB keluar: oriondb     ← kalau tidak lain, STOP

php artisan migrate --path=database/migrations/2026_10_08_233154_create_landing_page_builder_tables.php --force
```

### 9.4 Verifikasi pasca-migrasi

```sql
SELECT TABLE_NAME FROM information_schema.TABLES
 WHERE TABLE_SCHEMA='oriondb' AND TABLE_NAME LIKE 'landing_page%';
-- harus: 4 baris

SELECT COUNT(*) FROM users;          -- harus sama dengan sebelum migrasi
SELECT COUNT(*) FROM transaksi;      -- harus sama
SELECT COUNT(*) FROM audit_logs;     -- harus sama

SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME, DELETE_RULE
  FROM information_schema.REFERENTIAL_CONSTRAINTS
 WHERE CONSTRAINT_SCHEMA='oriondb' AND TABLE_NAME LIKE 'landing_page%';
-- harus: 9 FK
```

### 9.5 Smoke test

```bash
php scripts/check_public_homepage.php https://<domain> existing
# flag masih OFF -> homepage existing, wajib SEHAT

php artisan tinker --execute="echo config('landing_page_builder.enabled');"
# harus: false
```

### 9.6 Backup ulang (WAJIB)

Setelah migrasi sukses, ambil backup baru supaya tabel landing page ikut
ter-backup. Backup lama **TIDAK** memuat tabel tersebut.

### 9.7 Kriteria keputusan rollback

| Skenario | Tindakan |
|----------|----------|
| Migration gagal (error SQL) | **STOP.** DDL MariaDB tidak transactional — periksa dulu apakah ada tabel yang sudah terlanjur dibuat. |
| 4 tabel tidak lengkap | **Jangan rollback.** Periksa manual, buat sisa tabel atau jalankan ulang migration. |
| Migration sukses, aplikasi error | Investigasi log. **Jangan rollback.** |
| Konten rusak | Matikan flag (tetap OFF). |
| 500 error setelah migrasi | Restore dari backup pra-migrasi (verified). |

### 9.8 Prosedur pemulihan

**Aturan: TIDAK pernah menjalankan `migrate:rollback` untuk migration ini di
produksi** — `down()` menghapus seluruh data landing page secara permanen.

| Skenario | Pemulihan |
|----------|-----------|
| Migrasi parsial (1–3 tabel dibuat) | Periksa `information_schema`, buat sisa tabel manual atau hapus tabel yang terlanjur dibuat **hanya jika kosong**, lalu jalankan ulang |
| Migrasi berhasil, ada bug aplikasi | **Forward fix** — perbaiki kode, deploy ulang |
| Data hilang/korup | Restore dari backup pra-migrasi yang **sudah diverifikasi** |
| Butuh batalkan seluruh fitur | Set flag `false` + `config:clear`. **Migrasi boleh tetap ada** — tidak mengganggu apa pun. |

Poin penting: **membiarkan tabel ada lebih aman daripada menghapusnya.**
4 tabel kosong tidak merugikan, sedangkan `down()` menghapus data.

### 9.9 Operator bertanggung jawab

| Peran | Status |
|-------|--------|
| Administrator sistem | **BELUM DITETAPKAN** |
| Stakeholder approval | **BELUM ADA** |

---

## 10. BLOCKER YANG TERSISA

### Hard blocker

| # | Blocker | Kenapa penting | Solusi |
|---|---------|----------------|--------|
| **M1** | **Sandbox belum dibuat** | Bukti migrasi sukses tidak ada. Static audit + dry-run **tidak cukup** | Setujui Operasi A–D |
| **M2** | **Restore belum diverifikasi** | Backup integrity ≠ restore berhasil | Restore ke sandbox setelah M1 |
| **M3** | **Migrasi belum diuji ke DB produksi-set** | Kita tidak tahu apakah MariaDB 10.4 produksi menerima 22 pernyataan itu | Jalankan di sandbox (Operasi C) |
| **M4** | Tidak ada operator/PIC | Migrasi produksi tanpa pemilik = risiko | Tetapkan nama |
| **M5** | Tidak ada sign-off | Gerbang mandiri | Kumpulkan tanda tangan |

### Soft blocker

| # | Blocker | Severity |
|---|---------|----------|
| S1 | Browser E2E belum dieksekusi (dari 4D.2) | SEDANG |
| S2 | Monitoring otomatis belum ada (dari 4D.2) | SEDANG |
| S3 | Backup lama tidak memuat tabel landing page | SEDANG — selesai setelah migrasi + backup baru |
| S4 | Tidak ada staging soak test | SEDANG |
| S5 | `--pretend` tidak memvalidasi eksekusi nyata | Permanen — hanya sandbox yang bisa |

---

## 11. REKOMENDASI GO / NO-GO

# ❌ **NO-GO untuk migrasi produksi**

### Yang sudah kuat

- Audit statis **35/35**.
- Dry-run menghasilkan **22 pernyataan bersih** — nol DROP/TRUNCATE/DELETE.
- `users.id_user` **cocok** untuk FK.
- `audit_logs` **kompatibel tanpa ubah schema**.
- Hanya **1 migration pending** — `php artisan migrate` tidak akan menarik
  migration lain.
- Backup **19 file**, checksum tercatat, karakter set seragam, versi dump
  identik dengan server.
- Karakter set tabel baru **identik** dengan produksi.
- Regression **1085/5006, 0 failure**.

### Kenapa tetap NO-GO

Semua bukti di atas bersifat **statis**. Kita belum pernah menjalankan satu
pun pernyataan SQL itu terhadap database yang berisi data produksi nyata.

Risiko yang belum tertutup:

1. **Bentrokan nama** — kalau `oriondb` punya objek bernama `landing_pages`
   di luar tabel (view, trigger), migrasi gagal.
2. **Perbedaan perilaku MariaDB** — `json` dipetakan ke `LONGTEXT` + `CHECK`,
   bisa berbeda dari asumsi.
3. **Foreign key melingkar** belum pernah dicoba di data nyata.
4. **Restore belum pernah dicoba** — checksum tidak menjamin file bisa direstore.

Migrasi produksi berdasarkan bukti statis saja adalah tebakan — dan migrasi yang
gagal di produksi membutuhkan recovery yang lebih mahal daripada yang kita
hemat.

### Langkah berikutnya

1. **Setujui operasi sandbox** (A–D) — 3–5 menit, risiko rendah.
2. Jika restore + migrasi di sandbox **sukses**, saya laporkan dengan bukti
   nyata (FK, index, jumlah baris ERP sebelum/sesudah).
3. Baru PREPARASI migrasi produksi lewat Checkpoint 4E.3.

Kalau Anda hanya ingin dry-run saja tanpa sandbox, saya **tetap** menahankan
migrasi produksi menunggu — karena proses ini belum punya bukti eksekusi sama
sekali.

---

## LAMPIRAN A — File yang dibuat

| File | Isi |
|------|-----|
| `LANDINGPAGE_BUILDER_CHECKPOINT4E2_MIGRATION_READINESS_REPORT.md` | Laporan ini |
| `SANDBOX_PROPOSAL_4E2.md` | Rencana operasi sandbox (menunggu persetujuan) |

**Tidak ada file aplikasi yang diubah.** 4E.2 murni inspeksi + verifikasi.

---

## LAMPIRAN B — Reproduksi

```bash
# Inspeksi schema produksi (read-only)
php <temp>/phase1_schema_inspection.php

# Bandingkan migration repo vs produksi
php <temp>/phase2_migration_diff.php

# Verifikasi backup (read-only)
php <temp>/phase3_backup_verify.php

# Dry-run SQL (pretend, tidak dieksekusi)
DB_DATABASE=percetakan_erp_testing php <temp>/phase5_dryrun.php

# Verifikasi oriondb tidak berubah
php <temp>/check_schema_readonly.php oriondb
# → landing_page* TIDAK ADA

# Regression penuh
php vendor/bin/phpunit -c phpunit.xml
```

---

## STATUS AKHIR

# `LANDING PAGE BUILDER — MIGRATION READINESS BLOCKED`

Static audit **LULOS 35/35**. Dry-run SQL **BERSIH** (22 pernyataan, nol
destruktif). Backup **INTEGRIT** (19 file, checksum, charset seragam).
Regression **BERSIH** (1085/5006, 0 failure).

**Restore aktual: BELUM. Migrasi aktual: BELUM.** Keduanya menunggu
persetujuan operasi sandbox.

`oriondb` **tidak dimodifikasi**. Feature flag **tetap `false`**.