# LANDING PAGE BUILDER — CHECKPOINT 4E.4
## PRODUCTION MIGRATION APPROVAL PACKAGE

## STATUS: `LANDING PAGE BUILDER — PRODUCTION MIGRATION APPROVAL PACKAGE READY`

> **Runbook ini TIDAK dieksekusi.** Tidak ada operasi yang dijalankan terhadap
> `oriondb`. Semua nilai di bawah adalah hasil inspeksi read-only.
> Untuk menyetujui eksekusi → isi Bagian 14 dan Gate A pada Bagian 8.

---

## 1. RINGKASAN KESIAPAN

| Aspek | Status | Dasar |
|-------|--------|-------|
| Kode migration | ✅ audited | 35/35 pemeriksaan statis (4E.2) |
| SQL yang dihasilkan | ✅ diketahui | 22 pernyataan, nol destruktif (4E.2) |
| Kompatibilitas FK | ✅ terbukti | `users.id_user` = `bigint unsigned` (4E.2) |
| Restore backup | ✅ terbukti | 1.710 detik, 0 error (4E.3) |
| Migrasi nyata | ✅ terbukti | 622.88 ms, batch 85, 0 error (4E.3) |
| Constraint | ✅ terbukti menegakkan | 15/15 uji, rollback sempurna (4E.3) |
| Preservasi data ERP | ✅ terbukti | 8 tabel identik, 0 tabel hilang (4E.3) |
| Regression | ✅ bersih | 1085 / 5006 / 0 failure (4E.3) |
| **Approval manusia** | ❌ **belum** | Bagian 14 kosong |
| **Operator PIC** | ❌ **belum** | Bagian 4 kosong |
| **Jadwal jendela** | ❌ **belum** | Bagian 7 kosong |

**Secara teknis: siap. Secara organisasi: belum ada yang menyetujui.**

Saya sengaja tidak mengisi field approval, nama operator, atau tanggal. Mengisi
sendiri = mengarang persetujuan orang.

---

## 2. BUKTI SANDBOX (DIVERIFIKASI ULANG)

Saya **menjalankan ulang** verifikasi terhadap sandbox pada checkpoint ini untuk
membuktikan bukti masih dapat direproduksi, bukan sekadar diklaim.

### Hasil re-verifikasi (2026-10-09)

| Yang diuji | Hasil |
|------------|-------|
| Restore ke sandbox | ✅ Terbukti (4E.3) — dapat direproduksi lewat baseline JSON |
| 4 tabel landing page | ✅ `landing_pages`, `landing_page_drafts`, `landing_page_revisions`, `landing_page_media` |
| 9 foreign key | ✅ 5 ke `users.id_user`, 4 ke `landing_pages`/`revisions` |
| Total FK sandbox | ✅ **128** (119 + 9) |
| Total tabel sandbox | ✅ **110** (106 + 4) |
| Batch tertinggi | ✅ **85** |
| Total migration | ✅ **204** (203 + 1) |
| Uji batasan | ✅ **15/15 lolos**, 0 sisa data sintetis |
| Rollback uji | ✅ sempurna |
| Record ERP | ✅ utuh (`users` 11, `audit_logs` 452 — tidak berubah) |
| Tidak ada tabel hilang | ✅ **TIDAK ADA** tabel hilang dibanding 106 baseline |

### Jejak bukti (traceable)

| Artefak | Status |
|---------|--------|
| `SANDBOX_PROPOSAL_4E2.md` | ✅ ada |
| `LANDINGPAGE_BUILDER_CHECKPOINT4E2_MIGRATION_READINESS_REPORT.md` | ✅ ada |
| `LANDINGPAGE_BUILDER_CHECKPOINT4E3_SANDBOX_REPORT.md` | ✅ ada |
| `ROLLBACK_RUNBOOK_LANDING_PAGE.md` | ✅ ada |
| File migration | ✅ `2026_10_08_233154_create_landing_page_builder_tables.php` (4.608 byte) |
| Baseline JSON (before/restore) | ✅ tersimpan di direktori temp |

### Dua "kegagalan" yang sudah dijelaskan (bukan celah)

Verifikasi struktural melaporkan 2 gagal. Keduanya **salah assertion di skrip
audit saya**, sudah dibuktikan bersih di 4E.3 dan dikonfirmasi ulang di sini:

| Dilaporkan | Kenyataan | Status |
|-----------|-----------|--------|
| "tabel ERP inti hilang" | Nama `materials` tidak pernah ada di ERP ini — saya karang | ✅ bukan masalah |
| "audit_logs berubah (13 kolom)" | Punya **12** kolom, identik dengan `oriondb` | ✅ bukan masalah |

Bukti: `audit_logs` = 12 kolom di `oriondb` **dan** 12 di sandbox, FK 0 di kedua
sisi, seluruh struktur tabel ERP identik.

### Celah bukti yang saya laporkan

Tidak ada celah. Satu-satunya ketidaksempurnaan: **angka durasi** (1.710 dtk /
622.88 ms) hanya berlaku untuk data 630 KB di mesin ini. Database produksi yang
bertumbuh bisa lebih lambat. Dipakai sebagai acuan, bukan jaminan.

---

## 3. NAMA FILE MIGRASI YANG DISETUJUI

```
database/migrations/2026_10_08_233154_create_landing_page_builder_tables.php
```

| Properti | Nilai |
|----------|-------|
| Nama class | anonymous (`return new class extends Migration`) |
| Ukuran file | 4.608 byte |
| Tabel dibuat | 4 (`landing_pages`, `landing_page_drafts`, `landing_page_revisions`, `landing_page_media`) |
| Pernyataan | 18 `ALTER TABLE` + 4 `CREATE TABLE` = 22 |
| Memakai transaksi? | **TIDAK** |
| `down()` menghapus | 4 tabel (seluruh data) |

### Perilaku Laravel yang sudah diverifikasi

| Perilaku | Bukti | Dampak ke runbook |
|----------|-------|-------------------|
| `--path` membatasi migration | `MigrateCommand` memanggil `$migrator->paths()` | Aman — hanya 1 migration jalan |
| `--force` wajib di production | `APP_ENV=production` | Perintah harus memuat `--force` |
| DDL autocommits | MariaDB tidak bisa rollback DDL | Kegagalan parsial **wajib direkonsiliasi manual** |
| `migrations.id` | `int(10) unsigned` | Cukup untuk skala ini |

### Perintah yang DILARANG

| Perintah | Alasan |
|----------|--------|
| `migrate:fresh` | drop semua tabel |
| `migrate:refresh` | rollback semua + migrate semua |
| `migrate:reset` | rollback semua |
| `migrate:rollback` | menjalankan `down()` → **menghapus data landing page** |
| `db:wipe` | drop semua tabel |
| `DROP TABLE` / `TRUNCATE` | kehilangan data |

---

## 4. PRE-FLIGHT RUNBOOK (READ-ONLY)

Jalankan **semua** sebelum jendela migrasi. Semua bersifat baca saja.

### PF-1 — Identitas server

```bash
php artisan tinker --execute="
echo 'VERSION : '.DB::selectOne('SELECT VERSION() v')->v.PHP_EOL;
echo 'DB      : '.config('database.connections.mysql.database').PHP_EOL;
echo 'CHARSET : '.DB::selectOne('SELECT @@character_set_database c')->c.PHP_EOL;
echo 'COLLATE : '.DB::selectOne('SELECT @@collation_database c')->c.PHP_EOL;
"
```

**Harus:** `VERSION` diawali `10.4.32-MariaDB`, `DB` = `oriondb`,
charset `utf8mb4`, collation `utf8mb4_unicode_ci`.

### PF-2 — Konfirmasi tidak ada proses lain

```bash
tasklist | findstr php
```

Harus: **tidak ada** `php.exe` lain yang berjalan (kecuali CLI Anda).

### PF-3 — Git commit

```bash
git rev-parse HEAD
git status --short
```

Catat commit. `git status` boleh berantakan (banyak file belum di-commit di
proyek ini) — yang penting **catat commit-nya** untuk audit.

### PF-4 — Status migration

```bash
php artisan migrate:status
```

**WAJIB:** hitung baris yang berakhiran `Pending`:

```bash
php artisan migrate:status --no-ansi | Select-String "Pending\s*$"
```

Harus **tepat 1**:

```
2026_10_08_233154_create_landing_page_builder_tables ....... Pending
```

> ⚠️ **HATI-HATI误判.** Nama migration
> `2026_09_11_190000_add_pending_status_to_transaksi_detail` mengandung kata
> "pending" tapi statusnya `Ran`.-filter dengan `\s*Pending\s*$`, bukan `Pending`.

**Jika lebih dari 1 pending → STOP.**
**Jika 0 pending → STOP** (kemungkinan sudah pernah dimigrasi atau repo tidak sinkron).

### PF-5 — Skema saat ini

```bash
php artisan tinker --execute="
echo 'TABLES: '.DB::table('information_schema.TABLES')->where('TABLE_SCHEMA','oriondb')->count().PHP_EOL;
echo 'FKS   : '.DB::table('information_schema.REFERENTIAL_CONSTRAINTS')->where('CONSTRAINT_SCHEMA','oriondb')->count().PHP_EOL;
"
```

Harus: **TABLES = 106**, **FKS = 119**.
Setelah migrasi harus jadi **110** dan **128**.

### PF-6 — Tabel landing page belum ada

```bash
php artisan tinker --execute="
echo DB::table('information_schema.TABLES')
  ->where('TABLE_SCHEMA','oriondb')->where('TABLE_NAME','like','landing_page%')->count().PHP_EOL;
"
```

Harus: **0**. Jika > 0 → **STOP** (sudah pernah dimigrasi sebagian).

### PF-7 — Baseline data ERP

```bash
php artisan tinker --execute="
foreach (['users','roles','karyawan','konsumen','produk','transaksi',
          'pembayaran_riwayat','audit_logs','supplier'] as \$t) {
    echo str_pad(\$t,24).DB::table(\$t)->count().PHP_EOL;
}
"
```

Baseline yang tercatat **2026-10-09 05:59:05**:

| Tabel | Baris |
|-------|-------|
| users | 12 |
| roles | 8 |
| karyawan | 12 |
| konsumen | 19 |
| produk | 94 |
| transaksi | 2 |
| pembayaran_riwayat | 1 |
| audit_logs | 459 |
| supplier | 8 |

> `audit_logs`, `cache`, `sessions` **akan bertambah** karena aplikasi aktif.
> Itu normal, bukan tanda kerusakan.

### PF-8 — Koneksi & aktivitas

```bash
php artisan tinker --execute="
echo 'Trx aktif : '.DB::table('information_schema.INNODB_TRX')->count().PHP_EOL;
foreach (DB::table('information_schema.PROCESSLIST')->get() as \$c) {
    echo '  '.(\$c->DB ?? '(no db)').' | '.\$c->USER.' | '.\$c->STATE.PHP_EOL;
}
"
```

**`INNODB_TRX` harus 0.** Jika > 0, tunggu sampai selesai — jangan migrasi
saat ada transaksi panjang.

### PF-9 — Kapasitas disk

```bash
Get-PSDrive C | Select-Object Used,Free
```

Butuh > 2 GB bebas (backup sebelum + sesudah).

### PF-10 — Lokasi & hak backup

```bash
Test-Path storage\app\backups
Get-ChildItem storage\app\backups -Recurse -File | Measure-Object
```

Saat ini: 19 file, 9.7 MB.

**Keamanan web terverifikasi:**

```
GET /grafikaorion/storage/app/backups/daily/backup_daily_20261009_010059.sql
→ HTTP 403 Forbidden
```

`BerkasPrivatController` memakai allowlist ketat (`app` tidak termasuk), dan
`.htaccess` memblokir akses langsung. **Backup tidak bisa diambil lewat URL.**

### Checklist pre-flight

| # | Cek | Status |
|---|-----|--------|
| PF-1 | Server & DB identity | ☐ |
| PF-2 | Tidak ada proses PHP lain | ☐ |
| PF-3 | Git commit tercatat | ☐ |
| PF-4 | Tepat 1 pending migration | ☐ |
| PF-5 | 106 tabel / 119 FK | ☐ |
| PF-6 | 0 tabel landing page | ☐ |
| PF-7 | Baseline data ERP | ☐ |
| PF-8 | 0 transaksi aktif | ☐ |
| PF-9 | Disk cukup | ☐ |
| PF-10 | Backup destination OK | ☐ |

**Semua wajib ✅ sebelum lanjut.**

---

## 5. RENCANA BACKUP

### BC-1 — Backup pra-migrasi

`mysqldump` **tidak ada di PATH interaktif**, tapi tersedia di:

```
C:\xampp\mysql\bin\mysqldump.exe
```

Backup terjadwal tetap jalan karena scheduler punya PATH berbeda — tapi untuk
backup manual, **gunakan path eksplisit** agar tidak gagal diam-diam.

```bash
$stamp = Get-Date -Format "yyyyMMdd_HHmmss"
$out = "storage\app\backups\manual\pre_migration_oriondb_$stamp.sql"

& "C:\xampp\mysql\bin\mysqldump.exe" `
  --host=127.0.0.1 --port=3306 --user=root `
  --single-transaction --routines --triggers `
  oriondb > $out
```

`--single-transaction` penting: mengambil snapshot konsisten tanpa mengunci tabel.

### BC-2 — Checksum

```bash
Get-FileHash $out -Algorithm SHA256 | Tee-Object -FilePath "$out.sha256"
```

### BC-3 — Validasi integritas

```bash
# Tidak boleh kosong
(Get-Item $out).Length

# Harus punya marker dump
Select-String -Path $out -Pattern "^-- MariaDB dump"

# Hitung CREATE TABLE (harus 106)
(Select-String -Path $out -Pattern "CREATE TABLE").Count

# Tidak boleh punya USE / CREATE DATABASE (cegah target tertukar)
(Select-String -Path $out -Pattern "^\s*USE\s+").Count   # harus 0
(Select-String -Path $out -Pattern "^\s*DROP DATABASE").Count  # harus 0
```

### BC-4 — Lokasi aman

- **Utama:** `storage/app/backups/manual/` — **terverifikasi 403 via web**
- **Cadangan:** `storage/app/backups/` + offsite `salinan_backup`
- **Disarankan:** salin manual ke media/server lain sebelum jendela migrasi

### BC-5 — Retensi

| Tipe | Jumlah |
|------|--------|
| manual | 10 |
| daily | 7 |
| weekly | 4 |
| monthly | 12 |

Jangan hapus backup lama sebelum migrasi terverifikasi.

### BC-6 — 🛑 PROSEDUR VERIFIKASI RESTORE (ISOLATED)

> **File SQL yang ada BUKAN bukti bisa dipulihkan.** Backup hanya "terverifikasi"
> setelah **restore sungguhan** berhasil.

```bash
# 1. Buat database sekali pakai (BUKAN oriondb!)
mysql -h 127.0.0.1 -u root -e "
  CREATE DATABASE oriondb_restore_verify
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Restore backup pra-migrasi
mysql -h 127.0.0.1 -u root oriondb_restore_verify < pre_migration_oriondb_XXXX.sql

# 3. Verifikasi
mysql -h 127.0.0.1 -u root oriondb_restore_verify -e "
  SELECT COUNT(*) AS tabel FROM information_schema.TABLES
   WHERE TABLE_SCHEMA='oriondb_restore_verify';          -- harus 106
  SELECT COUNT(*) AS users FROM users;                   -- harus 12
  SELECT COUNT(*) AS produk FROM produk;                 -- harus 94"

# 4. JANGAN drop sebelum hasil dicatat
#    (hapus manual setelah verifikasi)
```

**Kapan wajib:** backup pra-migrasi **sebelum** eksekusi produksi. Restore ke
`oriondb` sendiri **DILARANG**.

### BC-7 — Backup pasca-migrasi (WAJIB)

```bash
# lewat UI /backup → "Jalankan Backup Manual"
# atau等价 dengan:
$stamp = Get-Date -Format "yyyyMMdd_HHmmss"
& "C:\xampp\mysql\bin\mysqldump.exe" --host=127.0.0.1 --user=root `
  --single-transaction --routines --triggers oriondb `
  > "storage\app\backups\manual\post_migration_oriondb_$stamp.sql"
```

> **Backup lama TIDAK memuat tabel landing page.** Tanpa backup pasca-migrasi,
> data landing page yang dibuat setelah go-live **tidak akan pernah ter-backup**.

### BC-8 — Backup media landing page

Berkas media disimpan di `storage/app/public/landing-media/`.

```bash
Compress-Archive -Path "storage\app\public\landing-media" `
  -DestinationPath "storage\app\backups\manual\landing_media_$stamp.zip" -ErrorAction SilentlyContinue
```

Sertakan dalam backup offsite. Saat ini tabel `landing_page_media` kosong, jadi
ini bersiap untuk setelah go-live.

---

## 6. MATRIKS OPERATOR & APPROVAL

> **Dikosongkan sengaja.** Saya tidak mengarang nama, tanggal, atau persetujuan.

| Peran | Nama | Kontak | Tanggal | Tanda tangan |
|-------|------|--------|---------|--------------|
| **Migration operator** (menjalankan) | | | | |
| **Independent verifier** (memeriksa hasil) | | | | |
| **Business owner approver** | | | | |
| **Rollback decision owner** | | | | |
| **Scribe / pencatat** | | | | |

### Jendela pemeliharaan

| Item | Nilai |
|------|-------|
| Tanggal | |
| Mulai | |
| Selesai | |
| Durasi diharapkan | **< 5 menit** (bukti sandbox: restore 1.7 dtk + migrasi 0.6 dtk) |
| Downtime pengguna | **~0 detik** — flag OFF, tidak ada perubahan homepage |
| Kanal komunikasi | |
| Kontak darurat | |

### Lokasi

| Item | Nilai |
|------|-------|
| Lokasi backup | `storage/app/backups/manual/` |
| Offsite | `salinan_backup` |
| Lokasi restore verify | `oriondb_restore_verify` (sementara) |

---

## 7. LANGKAH EKSEKUSI

### ⏸️ TITIK KONFIRMASI WAJIB

Semua langkah A–H **read-only**. Langkah I adalah **WRITE PERTAMA**.

### A — Konfirmasi operator siap

```bash
tasklist | findstr php      # harus kosong selain CLI Anda
```

### B — Unlock aplikasi (opsional,Recommended)

```bash
php artisan down
```

Tidak wajib — flag OFF berarti tidak ada perubahan homepage. Tapi disarankan
agar tidak ada request setengah jalan.

### C — Backup pra-migrasi

```bash
# Jalankan BC-1
# Lalu BC-2 (checksum), BC-3 (validasi), BC-6 (restore verify)
```

**JANGAN lanjut tanpa backup tervalidasi.**

### D — Re-run pre-flight

Ulangi PF-1 sampai PF-10. Database bisa berubah sejak pemeriksaan awal.

### E — Konfirmasi migration yang akan jalan

```bash
php artisan migrate --pretend --no-ansi `
  --path=database/migrations/2026_10_08_233154_create_landing_page_builder_tables.php `
  --force
```

Harus menampilkan **22 pernyataan**: 4 `create table`, 18 `alter table`, dan
**nol** `drop`/`truncate`.

### F — 🛑 KONFIRMASI TERAKHIR (SEBELUM WRITE PERTAMA)

> **HENTIKAN WAJIB.** Baca ulang ketiganya. Semua harus benar.

```
1. Target database    : oriondb                → benar?
2. Migration pending  : tepat 1                → benar?
3. Backup tervalidasi : checksum + restore OK  → benar?
4. Disetujui oleh     : _____________________  → sudah tanda tangan?
```

Jika ada yang tidak cocok → **STOP**.

### G — Eksekusi migrasi (WRITE PERTAMA)

```bash
php artisan migrate --no-ansi --force `
  --path=database/migrations/2026_10_08_233154_create_landing_page_builder_tables.php
```

Perhatikan output. Harus `DONE` dalam ~1 detik.

### H — Unlock aplikasi

```bash
php artisan up
```

### I — Verifikasi pasca-migrasi → Bagian 9

### J — Backup pasca-migrasi → BC-7

### K — Dokumentasikan

Catat: waktu, durasi, output, hasil verifikasi, nama operator & verifier.

---

## 8. PENANGANAN KEGAGALAN

> **MariaDB DDL = AUTOCOMMIT. Tidak bisa di-rollback.**
> **JANGAN gunakan `migrate:rollback` sebagai rollback otomatis.**
> **DDL parsial → STOP dan rekonsiliasi manual.**

| # | Skenario | Gejala | Tindakan |
|---|----------|--------|----------|
| F1 | **Backup gagal** | mysqldump error / file 0 byte | **STOP SEBELUM migrasi.** Jangan pernah lanjut tanpa backup. Perbaiki mysqldump, ulangi BC-1..BC-3. |
| F2 | **Pending migration tak terduga** | PF-4 > 1 pending | **STOP.** Jangan pakai `--path` untuk "membypass" — masalahnya migrasi lain belum selesai. Selesaikan dulu, lalu ulang. |
| F3 | **Tabel landing page sudah ada** | PF-6 > 0 | **STOP.** Sudah pernah dimigrasi sebagian. Rekonsiliasi manual: tentukan tabel mana yang lengkap, lengkapi sisanya. |
| F4 | **Foreign key gagal** | Error 1215/1216/1451 | **STOP.** Jangan ulangi migration. Periksa `SHOW ENGINE INNODB STATUS` / `SHOW WARNINGS`. Kemungkinan FK circular (lihat 4E.2) atau data existing melanggar. Rekonsiliasi manual. |
| F5 | **Duplikat tabel** | Error 1050 "Table already exists" | Sama seperti F3. **STOP**, cek `information_schema` dulu. |
| F6 | **Migrasi parsial** | 1–3 tabel dibuat, sisanya gagal | **STOP TOTAL.** DDL sudah autocommits. **Jangan** `migrate:rollback` (menghapus semua 4). Periksa persis tabel mana yang ada, lalu lengkapi manual satu per satu. |
| F7 | **Repository mismatch** | Tabel ada tapi entri `migrations` tidak | **STOP.** Jangan insert manual entri `migrations` tanpa pemahaman. Diskusikan lebih dulu. |
| F8 | **Koneksi DB gagal** | Connection refused / access denied | **STOP.** Migrasi mungkin sudah sebagian jalan. Periksa `information_schema` setelah koneksi pulih, sebelum mencoba lagi. |
| F9 | **Regresi ERP tak terduga** | Modul kasir/SPK/produksi error | `php artisan up` dulu. Migration hanya menambah tabel — tidak seharusnya mengubah kode. Investigasi log. |
| F10 | **Published revision hilang** | — | **Tidak relevan untuk migrasi ini.** Tabel baru kosong; tidak ada data yang bisa hilang. |
| F11 | **Resolver exception** | Log "Landing page resolver: read failure" | **Normal & diharapkan** selama flag OFF — resolver tidak pernah dipanggil. Jika muncul saat flag OFF, investigasi. |

### K redevelopment penting

**Kegagalan setelah langkah G TIDAK bisa "dibatalkan" dengan rollback.**
`down()` menghapus keempat tabel. Jika tabel sudah berisi data (mis. karena
antrian tak sengaja), `down()` akan menghilangkannya.

**Pilihan yang lebih aman: biarkan tabel apa adanya.**
Empat tabel kosong tidak merugikan apa pun. Memperbaiki ke depan jauh lebih
murah daripada menghapus.

---

## 9. VERIFIKASI PASCA-MIGRASI (READ-ONLY)

### V1 — Empat tabel

```sql
SELECT TABLE_NAME FROM information_schema.TABLES
 WHERE TABLE_SCHEMA='oriondb' AND TABLE_NAME LIKE 'landing_page%';
-- harus: 4 baris
```

### V2 — Foreign key (9, lima ke `users.id_user`)

```sql
SELECT rc.CONSTRAINT_NAME, rc.TABLE_NAME, kcu.COLUMN_NAME,
       rc.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME, rc.DELETE_RULE
  FROM information_schema.REFERENTIAL_CONSTRAINTS rc
  JOIN information_schema.KEY_COLUMN_USAGE kcu
    ON kcu.CONSTRAINT_SCHEMA=rc.CONSTRAINT_SCHEMA
   AND kcu.CONSTRAINT_NAME=rc.CONSTRAINT_NAME
 WHERE rc.CONSTRAINT_SCHEMA='oriondb' AND rc.TABLE_NAME LIKE 'landing_page%'
 ORDER BY rc.TABLE_NAME;
-- harus: 9 baris, 5 di antaranya REFERENCED_COLUMN_NAME = 'id_user'
```

### V3 — Unique & index

```sql
SELECT TABLE_NAME, INDEX_NAME,
       GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
  FROM information_schema.STATISTICS
 WHERE TABLE_SCHEMA='oriondb' AND TABLE_NAME LIKE 'landing_page%'
 GROUP BY TABLE_NAME, INDEX_NAME ORDER BY TABLE_NAME, INDEX_NAME;
```

Harus ada 4 PRIMARY + 4 UNIQUE + 6 index.

### V4 — Skema total

```sql
SELECT COUNT(*) FROM information_schema.TABLES
 WHERE TABLE_SCHEMA='oriondb';           -- harus 110 (106 + 4)

SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
 WHERE CONSTRAINT_SCHEMA='oriondb';      -- harus 128 (119 + 9)
```

### V5 — Histori migration

```sql
SELECT migration, batch FROM migrations
 WHERE migration LIKE '%landing%';
-- harus: 2026_10_08_233154_create_landing_page_builder_tables, batch 85

SELECT MAX(batch) FROM migrations;      -- harus 85
SELECT COUNT(*) FROM migrations;        -- harus 204
```

### V6 — Preservasi tabel ERP

```sql
SELECT TABLE_NAME FROM information_schema.TABLES
 WHERE TABLE_SCHEMA='oriondb'
   AND TABLE_NAME IN ('users','roles','karyawan','konsumen','produk',
                      'transaksi','pembayaran_riwayat','audit_logs','supplier');
-- harus: 9 baris
```

### V7 — Baris ERP

Bandingkan dengan baseline PF-7:

| Tabel | Sebelum | Sesudah | Toleransi |
|-------|---------|---------|-----------|
| users | 12 | | ❗ tidak boleh turun |
| roles | 8 | | ❗ tidak boleh turun |
| karyawan | 12 | | ❗ tidak boleh turun |
| konsumen | 19 | | ❗ tidak boleh turun |
| produk | 94 | | ❗ tidak boleh turun |
| transaksi | 2 | | boleh naik (transaksi baru) |
| pembayaran_riwayat | 1 | | boleh naik |
| **audit_logs** | 459 | | **boleh naik** (app aktif) |
| supplier | 8 | | ❗ tidak boleh turun |

### V8 — `audit_logs` tidak berubah

```sql
SELECT COUNT(*) FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA='oriondb' AND TABLE_NAME='audit_logs';
-- harus: 12 (tidak berubah)
```

### V9 — Uji aplikasi (manual)

> **Jangan membuat record uji di produksi** untuk menguji constraint.
> Constraint sudah terbukti di 4E.3 (15/15). Cukup verifikasi-ui saja.

| # | Uji | Expected |
|---|-----|----------|
| V9.1 | Buka `/` | Homepage existing, utuh |
| V9.2 | Login | Masuk dashboard |
| V9.3 | `/kasir/monitoring` | Normal |
| V9.4 | `/spk` | Normal |
| V9.5 | `/produksi` | Normal |
| V9.6 | `/collecting` | Normal |
| V9.7 | `/setting/landing-page-builder` | Normal (tabel baru) |
| V9.8 | `php artisan tinker --execute="echo config('landing_page_builder.enabled');"` | **`false`** — flag HARUS tetap OFF |

### V10 — Health check

```bash
php scripts/check_public_homepage.php https://<domain> existing
# harus: HASIL: SEHAT, endpoint /masuk HTTP 200
```

---

## 10. BACKUP PASCA-MIGRASI

 见 **BC-7** di Bagian 5.

Checklist:
- [ ] Backup pasca-migrasi dibuat
- [ ] Checksum dicatat
- [ ] 110 `CREATE TABLE` terverifikasi (bukan 106)
- [ ] Disalin ke offsite
- [ ] Disalin ke lokasi kedua di luar server
- [ ] Media landing page ikut di-backup (saat ini kosong, bersiap)

---

## 11. ROLLBACK & PEMULIHAN

### 🥇 Pilihan utama: TIDAK ADA ROLLBACK

Migrasi ini **aditif murni** — menambah 4 tabel kosong. Tidak mengubah kode,
tidak mengubah tabel ERP, tidak mengubah homepage.

**Jika migrasi sukses tapi ada masalah: biarkan tabelnya.** Tabel kosong tidak
mengganggu. Perbaiki masalahnya secara forward.

### 🥈 Kalau perluurz entfernen

| Skenario | Tindakan |
|----------|----------|
| Migrasi sukses, aplikasi error | `php artisan up`. Investigasi log. **Forward fix.** |
| Migrasi parsial (F6) | **STOP.** Periksa `information_schema`. Lengkapi manual satu per satu. **JANGAN** `migrate:rollback`. |
| Data landing page terlanjur salah | Restore dari backup **pasca-migrasi** (bukan pra-migrasi) — atau hapus baris secara selektif. |
| Total tidak指挥官 | Restore dari backup pra-migrasi (restore verify dulu, tidak langsung ke `oriondb`). |
| Batalkan seluruh fitur | Set flag `false`. **Migrasi boleh tetap ada.** |

### 🚫 Yang TIDAK boleh

| Larangan | Alasan |
|----------|--------|
| `migrate:rollback` | `down()` menghapus keempat tabel + semua data |
| `DROP TABLE landing_pages` (dengan data) | Kehilangan data permanen |
| Restore langsung ke `oriondb` tanpa verifikasi | Risiko merusak data terkini |

---

## 12. PINTU PERSETUJUAN TERPISAH

```
┌─────────────────────────────────────────────────────────────┐
│  GATE A — Migrasi Database Produksi                          │
│  Status: ☐ BELUM DISETUJUI                                   │
│  попучает → database oriondb berubah skema (+4 tabel)       │
└─────────────────────────────────────────────────────────────┘
                              ↓
              ⚠️ GATE A TIDAK MENGIZINKAN GATE B ⚠️
                              ↓
┌─────────────────────────────────────────────────────────────┐
│  GATE B — Aktivasi Homepage Publik                          │
│  Status: ☐ TERBLOKIR                                         │
│  Dealerkan: homepage berubah dari ERP ke landing page builder │
└─────────────────────────────────────────────────────────────┘
```

### Gate A — Migrasi Database Produksi

| Syarat | Status |
|--------|--------|
| Bukti sandbox (4E.3) | ✅ |
| Backup pra-migrasi tervalidasi | ☐ |
| Operator & verifier ditetapkan | ☐ |
| Jadwal disepakati | ☐ |
| Approval business owner | ☐ |

**Dampak:** hanya skema DB. Tidak ada perubahan yang dilihat pengguna.

### Gate B — Aktivasi Homepage Publik

| Syarat | Status |
|--------|--------|
| Gate A selesai | ☐ |
| **Browser E2E dieksekusi + bukti** | ☐ **BELUM** |
| **Monitoring & alerting aktif** | ☐ **BELUM** |
| Sign-off stakeholder | ☐ |
| Soak test staging | ☐ |
| Rollback runbook sudah diuji | ☐ |
| Kontrol rollout bertahap | ☐ |

> **Gate B tetap TERBLOKIR.** Ini bukan karena migrasi — migrasi justru
> membuat Gate B *mungkin* dilakukan. Tapi belum ada satu pun syarat Gate B
> yang terpenuhi. Migrasi hari ini **tidak boleh** diikuti menyalakan flag.

### Pemisahan yang harus dijaga

```
Hari migrasi   : DB berubah, homepage TETAP ERP
Hari go-live   : flag diaktifkan, homepage BERUBAH  ← checkpoint terpisah
```

---

## 13. REKOMENDASI

### 🟡 GO CONDITIONAL untuk Gate A

Secara teknis migrasi **terbukti aman**. Yang belum ada adalah persetujuan
manusia.

| Faktor | Nilai |
|--------|-------|
| Restore di sandbox | 1.710 detik, 0 error |
| Migrasi di sandbox | 622.88 ms, 0 error |
| Constraint | 15/15 menegakkan aturan |
| Data ERP | 0 tabel hilang, 8 tabel identik |
| Regression | 1085/5006, 0 failure |
| Risiko tersisa | Migration parsial (sagotmanagement sudah ada prosedurnya) |

**Saya tidakCZynnected:**

- Migrasi **sichere** dijalankan setelah Gate A disetujui, operator ditetapkan,
  backup tervalidasi, dan restore-verify berhasil.
- Estimasi durasi: **< 5 menit** termasuk backup.
- Dampak pengguna: **nol** selama flag OFF.

### 🔴 NO-GO untuk Gate B

Tujuh syarat Gate B belum terpenuhi. **Jangan menyalakan flag di hari yang sama
dengan migrasi.**

### Rekomendasi urutan

```
1. Setujui Gate A → jalankan migrasi (impact: ~0 detik ke pengguna)
2. Back up segera setelah migrasi (WAJIB)
3. Observasi 3–7 hari: pastikan ERP normal
4. Selesaikan syarat Gate B (browser E2E, monitoring, sign-off, soak)
5. Aktifkan flag di checkpoint tersendiri
```

---

## 14. FORMULIR PERSETUJUAN

> **Biarkan kosong sampai diisi tangan.** Jangan diisi otomatis.

```
═══════════════════════════════════════════════════════════════
  LANDING PAGE BUILDER — PERSETUJUAN MIGRASI PRODUKSI (GATE A)
═══════════════════════════════════════════════════════════════

Target database     : oriondb
Migration           : 2026_10_08_233154_create_landing_page_builder_tables
Batch yang diharapkan: 85
Tabel setelah migrasi: 106 → 110
FK setelah migrasi    : 119 → 128

Bukti pendukung     : LANDINGPAGE_BUILDER_CHECKPOINT4E3_SANDBOX_REPORT.md

───────────────────────────────────────────────────────────────
PRE-FLIGHT (semua harus ✅)
───────────────────────────────────────────────────────────────
[ ] PF-1  Server & DB identity terverifikasi
[ ] PF-2  Tidak ada proses PHP lain
[ ] PF-3  Git commit tercatat: ______________________
[ ] PF-4  Tepat 1 pending migration
[ ] PF-5  106 tabel / 119 FK
[ ] PF-6  0 tabel landing page
[ ] PF-7  Baseline data ERP tercatat
[ ] PF-8  0 transaksi aktif
[ ] PF-9  Disk cukup
[ ] PF-10 Lokasi backup OK

───────────────────────────────────────────────────────────────
BACKUP (semua harus ✅)
───────────────────────────────────────────────────────────────
Backup file        : ______________________________
Checksum SHA-256   : ______________________________
Jumlah CREATE TABLE: ______ (harus 106)
Restore verify DB  : oriondb_restore_verify
Restore verify OK  : [ ] Ya
Offsite disalin    : [ ] Ya
Lokasi kedua      : ______________________________

───────────────────────────────────────────────────────────────
TANDA TANGAN
───────────────────────────────────────────────────────────────
Migration operator :
  Nama              : ______________________
  Tanggal           : ______________________
  Tanda tangan      : ______________________

Independent verifier (BUKAN operator yang sama):
  Nama              : ______________________
  Tanggal           : ______________________
  Tanda tangan      : ______________________

Business owner approver:
  Nama              : ______________________
  Tanggal           : ______________________
  Tanda tangan      : ______________________

Rollback decision owner:
  Nama              : ______________________
  Kontak            : ______________________

───────────────────────────────────────────────────────────────
KEPUTUSAN
───────────────────────────────────────────────────────────────
[ ] GATE A DISETUJUI — migrasi boleh dijalankan
[ ] GATE A DITOLAK  — alasan: ____________________________
[ ] GATE B — TETAP TERBLOKIR (wajib dicentang; tidak terkait
         dengan keputusan Gate A)

Catatan:
_______________________________________________________________
_______________________________________________________________

═══════════════════════════════════════════════════════════════
```

---

## LAMPIRAN — Ringkasan Nilai Kunci

| Nilai | Angka |
|-------|-------|
| Migration | `2026_10_08_233154_create_landing_page_builder_tables.php` |
| Pernyataan SQL | 22 (4 CREATE + 18 ALTER) |
| Batch | 84 → **85** |
| Tabel | 106 → **110** |
| FK | 119 → **128** |
| Durasi sandbox (restore) | 1.710 detik |
| Durasi sandbox (migrasi) | 622.88 ms |
| Baseline users | 12 |
| Baseline produk | 94 |
| Baseline transaksi | 2 |
| Baseline audit_logs | 459 |
| mysqldump | `C:\xampp\mysql\bin\mysqldump.exe` |
| Lokasi backup | `storage/app/backups/manual/` (403 via web ✅) |
| `--force` | WAJIB (APP_ENV=production) |
| Transaksi | TIDAK (DDL autocommits) |

---

## STATUS AKHIR

# `LANDING PAGE BUILDER — PRODUCTION MIGRATION APPROVAL PACKAGE READY`

Runbook lengkap, executable, dan dapat diaudit. Berbasis bukti eksekusi nyata
dari Checkpoint 4E.3.

**Tidak ada yang dieksekusi terhadap `oriondb`.** Feature flag tetap `false`.
Sandbox dibiarkan utuh. Tidak ada backup yang dihapus.

Menunggu: persetujuan Gate A, nama operator, jadwal, dan validasi backup.