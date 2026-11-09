# LANDING PAGE BUILDER — CHECKPOINT 4E.5
## FINAL PRODUCTION MIGRATION PRE-EXECUTION GATE

## STATUS: `LANDING PAGE BUILDER — PRODUCTION MIGRATION PRE-EXECUTION VERIFIED / AWAITING FINAL EXECUTION AUTHORIZATION`

> **TIDAK ADA YANG DIJALANKAN terhadap `oriondb`.**
> Tidak ada backup baru dibuat. Tidak ada migration. Tidak ada flag change.
> Checkpoint ini berhenti setelah verifikasi.

---

# PRODUCTION MIGRATION EXECUTION: NOT AUTHORIZED

```
╔═══════════════════════════════════════════════════════════════════════╗
║                                                                       ║
║   PRODUCTION MIGRATION EXECUTION: NOT AUTHORIZED                     ║
║                                                                       ║
║   Checkpoint ini HANYA melakukan verifikasi read-only.                 ║
║   Menyetujui dokumen ini BUKAN persetujuan eksekusi.                 ║
║   Diperlukan pesan eksplisit terpisah dari pengguna untuk             ║
║   menjalankan migrasi ke oriondb.                                     ║
║                                                                       ║
╚═══════════════════════════════════════════════════════════════════════╝
```

---

## 1. APPROVAL RECORD

### Informasi yang-disubmit pengguna

| Peran | Nama disubmit |
|-------|---------------|
| Operator | `shin` |
| Verifier | `shin` |
| Business owner / approver | `shin` |

| Item | Nilai disubmit |
|------|----------------|
| Jadwal diminta | `2026-10-09 12:00 WIB` |

> **Keterangan penting:** ini adalah **informasi yang disubmit pengguna**, bukan
> tanda tangan yang diverifikasi independen. Saya tidak dapat memverifikasi
> apakah `shin` benar-benarlh/IP yang diberikan, apakah berwenang, atau apakah
> persetujuan diberikan di luar percakapan ini. Formulir di Bagian 12 masih
> kosong dan harus diisi tangan.

---

## 2. PEMBATASAN PEMISAHAN TUGAS (SEPARATION OF DUTIES)

> **Operator dan verifier adalah orang yang sama: `shin`.**

Ini **pelanggaran pemisahan tugas**. Dalam praktik, pemisahan tugas berarti
seseorang yang menjalankan perubahan harus berbeda dari orang yang memeriksa
hasilnya, agar ada-eyed independen.

### Apa risikonya

| Yang hilang | Akibatnya |
|-------------|-----------|
| Verifikasi independen | Tidak ada mata kedua yang mengonfirmasi "106+4 tabel, batch 85, 0 error" |
| Deteksi kesalahan operator | Satu orang bisa salah baca output dan tetap menyatakan "sukses" |
| Jejak audit |，若 nama sama, tidak bisa dibuktikan siapa melakukan apa |

### Yang TIDAK saya lakukan

- **Tidak** mengarang verifier kedua.
- **Tidak** mengklaim ada persetujuan independen.
- **Tidak** menyatakan pemisahan tugas terpenuhi.

### Mitigasi yang bisa dilakukan

| Opsi | feasibilitas |
|------|--------------|
| Ganti verifier jadi orang lain | Paling bersih — **disarankan** |
| Verifikasi otomatis terprogram (query基) menggantikan mata kedua | Sudah disiapkan (Bagian 9) — mengurangi subjective |
| Setuju dengan verifikasi yang sama orang, dicatat sebagai kelemahan | Jika tak ada pilihan, catat eksplisit di formulir |

**Rekomendasi saya: ganti verifier.** Formulir Bagian 12 menyediakan baris
terpisah; diisi oleh orang yang **bukan** `shin`.

---

## 3. JENDELA WAKTU — SUDAH MELEWATI BATAS PERSIAPAN

### Waktu aktual saat pemeriksaan

| Sumber | Nilai |
|--------|-------|
| Waktu sistem | `2026-10-09 12:02:48 +07:00` |
| Waktu WIB | `2026-10-09 12:03:37` |
| Jendela diminta | `2026-10-09 12:00 WIB` |

**Jendela sudah terbuka hanya ~3 menit ketika pre-flight dimulai.**

### ⚠️ Temuan timezone yang relevan untuk audit

```
PHP CLI timezone : Europe/Berlin (+02:00)
WIB              : Asia/Jakarta (+07:00)
config/app.php   : 'timezone' => 'Asia/Jakarta'
```

PHP CLI berjalan di **Europe/Berlin**, bukan Asia/Jakarta. Artinya timestamp
yang dicetak oleh skrip PHP (seperti pada laporan 4E.3: restore `05:44:04`) are
**waktu Berlin** — setara `10:44:04 WIB`. Sementara timestamp yang dicetak
PowerShell adalah WIB.

> **Dampak audit:** laporan 4E.3 mencampur dua timezone — restore dicatat
> `05:44:04` (Berlin) sedangkan migration dicatat `10:45:20` (WIB). Keduanya
> sebenarnya **momen yang sama** (selisih 5 jam = selisih timezone). Angka
>ampledurasi tetap valid, tetapi **label waktunya membingungkan**. Ini
> dicatat terbuka di sini agar audit tidak keliru.

### Apakah jendela masih bisa dipakai?

**Tidak.** Alasannya:

| Yang dibutuhkan | Waktu |
|-----------------|-------|
| Jendela dibuka | 12:00 WIB |
| Pre-flight + verifikasi | sudah makan ~3 menit dan masih berjalan |
| Backup pra-migrasi + validasi | ~2–5 menit |
| **Restore-verify ke DB terisolasi** | **~2–5 menit** |
| Approval final bertanda tangan | belum ada |

Butuh **minimal 15–30 menit** dari awal untuk窗口 yang aman. Jendela 12:00 sudah
tidak realistis — **perlu dijadwalkan ulang**.

> **Permintaan eksplisit: jadwalkan ulang ke slot yang memungkinkan.**
> Contoh:错 reservation window dengan minimal 30 menit dan operator + verifier
> yang sudah terkonfirmasi ada.

---

## 4. ENVIRONMENT AKTUAL

### Direktori & Git

| Item | Nilai |
|------|-------|
| Project | `C:\xampp\htdocs\grafikaorion` ✅ |
| Branch | `main` |
| Commit | `a66b11384f42944752c97e62976821e8f2afa373` |
| Working tree | 164 entri (59 modified, 105 untracked) |

> Working tree sangat kotor — banyak file belum di-commit. **Ini bukan blocker**
> untuk migrasi (kode tidak berubah saat migrasi), tapi **commit `a66b113`
> tidak mewakili keadaan sebenarnya sistem saat migrasi dijalankan**. Untuk
> audit, catat bahwa repo tidak bersih.

### Laravel

| Item | Nilai |
|------|-------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `DB_CONNECTION` | `mysql` |
| `DB_DATABASE` | `oriondb` ✅ |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `3306` |
| User DB | `root` (password **tidak ditampilkan**) |

> `--force` **WAJIB** karena `APP_ENV=production`.

### MariaDB

| Item | Nilai |
|------|-------|
| Versi | **10.4.32-MariaDB** ✅ |
| Charset | `utf8mb4` |
| Collation | `utf8mb4_unicode_ci` |
| `sql_mode` | `NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION` |
| Engine | InnoDB (semua tabel) ✅ |

### Kredensial

Tidak ditampilkan di mana pun dalam laporan ini.

---

## 5. SCHEMA PRODUKSI (PRE-FLIGHT)

### Hasil

| Item | Nilai | Status |
|------|-------|--------|
| Jumlah tabel | **106** | ✅ sesuai baseline |
| Jumlah foreign key | **119** | ✅ sesuai baseline |
| Tabel landing page | **0** | ✅ aman |
| Total migration | **203** | ✅ |
| Batch tertinggi | **84** | ✅ |
| Batch berikutnya | **85** | ✅ |
| Transaksi aktif | **0** | ✅ aman |
| `users.id_user` | `bigint(20) unsigned` | ✅ kompatibel FK |
| `audit_logs` | 12 kolom | ✅ tidak berubah |
| `audit_logs.event` | `varchar(20)` | ✅ kompatibel |

### Ringkasan: **0 blocker, 0 warning**

### Baseline data ERP — semua cocok

| Tabel | Baris | Baseline | Status |
|-------|-------|----------|--------|
| users | 12 | 12 | ✅ |
| roles | 8 | 8 | ✅ |
| karyawan | 12 | 12 | ✅ |
| konsumen | 19 | 19 | ✅ |
| produk | 94 | 94 | ✅ |
| transaksi | 2 | 2 | ✅ |
| pembayaran_riwayat | 1 | 1 | ✅ |
| audit_logs | 459 | 459 | ✅ |
| supplier | 8 | 8 | ✅ |

---

## 6. CHECKSUM MIGRASI & IDENTIFIKASI

### File

```
database/migrations/2026_10_08_233154_create_landing_page_builder_tables.php
```

| Properti | Nilai |
|----------|-------|
| SHA-256 | `f95149726de3decf885df21bf49ae1abf8b338fa66ff0b23bd08573f23f1179e` |
| Ukuran | **4.608 byte** (cocok dengan catatan 4E.4) |

### ⚠️ Celah audit: file belum di-commit

```
git status → ?? database/migrations/2026_10_08_233154_...
```

File migration ini **untracked** — belum pernah `git add`/`commit`. Artinya:

> **Git tidak bisa membuktikan file ini tidak berubah.** Checksum hari ini
> (4E.5) dicatat, tetapi **tidak ada checksum yang tersimpan dari saat
> verifikasi sandbox (4E.3)** untuk dibandingkan.

Saya tidak mengklaim "checksum cocok dengan artefak yang disetujui" karena
artefak checksum itu tidak pernah dibuat. Yang saya lakukan sebagai gantinya:
**membandingkan skema aktual** (lihat di bawah).

### Metode verifikasi alternatif (fungsional, bukan checksum)

Saya membandingkan **langsung**: SQL yang dihasilkan file *sekarang* vs skema
yang benar-benar ada di `oriondb_migration_sandbox` (hasil eksekusi nyata file
yang sama di 4E.3).

| Aspek | Hasil |
|-------|-------|
| Index yang dideklarasikan | **9 / 9 ada di sandbox** ✅ |
| Foreign key | **9 / 9 identik** (nama + target) ✅ |
| Selisih index | **0** ✅ |
| Selisih FK | **0** ✅ |
| Kolom | cocok persis (9/9/9/10) ✅ |

**Kesimpulan: file migration tidak berubah secara fungsional.**

> 5 index tambahan di sandbox (`*_foreign`) adalah index pendukung yang
> **dibuat otomatis MariaDB** untuk kolom FK — bukan dideklarasikan via
> `$table->index()`. Kehadirannya normal, bukan selisih.

### Verifikasi lain

| Aspek | Status |
|-------|--------|
| 4 tabel landing page belum ada | ✅ 0 |
| Tepat 1 migration pending | ✅ (lihat Bagian 7) |
| Tidak ada migration lain pending | ✅ |
| FK cocok `users.id_user` | ✅ 5 dari 9 FK |
| SQL additive-only | ✅ 22 pernyataan, nol DROP/TRUNCATE |

---

## 7. BUKTI PENDING MIGRATION (PARSING AKURAT)

Checkpoint ini memperingatkan: **jangan menghitung pending dengan mencari kata
"pending" di nama file.**

### Jebakan yang dikonfirmasi

```
2026_09_11_190000_add_pending_status_to_transaksi_detail ... [1] Ran
```

Kata "pending" ada di **nama file**, tapi statusnya `Ran`. Hitungan naif
menghasilkan **2 pending** — salah.

### Metode benar

```bash
php artisan migrate:status --no-ansi | Select-String "Pending\s*$"
```

Mencocokkan status di **akhir baris**. Hasil: **1**.

### Bukti

```
Total migration : 203
Sudah dijalankan : 203
Pending         : 1   ← hanya 2026_10_08_233154_create_landing_page_builder_tables
Batch berikutnya: 85
Landing page migration di DB : 0
```

---

## 8. KESIAPAN BACKUP

### Verifikasi

| Item | Nilai | Status |
|------|-------|--------|
| Direktori tujuan | `storage/app/backups/` | ✅ ada |
| Isi | 19 file, 9.7 MB | ✅ |
| **Akses web** | **HTTP 403** | ✅ tidak publik |
| Disk bebas | 94.49 GB | ✅ cukup |
| **mysqldump** | `C:\xampp\mysql\bin\mysqldump.exe` | ✅ ada |
| Versi mysqldump | `10.19 Distrib 10.4.32-MariaDB` | ✅ cocok server |
| Retensi | daily 7, weekly 4, monthly 12, manual 10 | ✅ terdokumentasi |
| Restore teruji | 4E.3: 1.710 detik, 0 error | ✅ |

### Perintah backup pra-migrasi (DISIAPKAN, TIDAK DIJALANKAN)

```powershell
$stamp = Get-Date -Format "yyyyMMdd_HHmmss"
$out   = "storage\app\backups\manual\pre_migration_oriondb_$stamp.sql"

& "C:\xampp\mysql\bin\mysqldump.exe" `
  --host=127.0.0.1 --port=3306 --user=root `
  --single-transaction --routines --triggers `
  oriondb > $out
```

**Password TIDAK ditulis di argumen baris perintah.**>XAMPP lokal `root`
umumnya tanpa password. Bila ada password, gunakan env var — **jangan** `--password=` di argumen (akan muncul di daftar proses OS).

### ⚠️ Tidak ada backup baru di checkpoint ini

Instruksi melarang pembuatan backup tanpa otorisasi terpisah. **Backup yang
ada berasal dari checkpoint sebelumnya dan SEBELUM migrasi** — jadi belum
mencerminkan kondisi produksi terkini. Backup pra-migrasi **wajib** dibuat di
checkpoint eksekusi.

### Validasi wajib setelah backup

```powershell
(Get-Item $out).Length                              # > 0
(Select-String -Path $out -Pattern "^-- MariaDB dump")  # header ada
(Select-String -Path $out -Pattern "CREATE TABLE").Count  # harus 106
(Select-String -Path $out -Pattern "^\s*USE\s+").Count     # harus 0
Get-FileHash $out -Algorithm SHA256
```

### Restore-verify (wajib sebelum eksekusi)

```bash
mysql -h 127.0.0.1 -u root -e "CREATE DATABASE oriondb_restore_verify
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

mysql -h 127.0.0.1 -u root oriondb_restore_verify < pre_migration_oriondb_XXXX.sql

mysql -h 127.0.0.1 -u root oriondb_restore_verify -e "
  SELECT COUNT(*) FROM information_schema.TABLES
   WHERE TABLE_SCHEMA='oriondb_restore_verify';   -- harus 106
  SELECT COUNT(*) FROM users;                    -- harus 12"
```

> **File SQL yang ada BUKAN bukti bisa dipulihkan.** Restore harus benar-benar
> dijalankan. Restore ke `oriondb` sendiri **DILARANG**.

---

## 9. KEAMANAN MIGRAMASI

### Perintah yangresses usulan

```bash
php artisan migrate --no-ansi --force `
  --path=database/migrations/2026_10_08_233154_create_landing_page_builder_tables.php
```

### Mengapa perintah ini aman

| Aspek | Bukti |
|-------|-------|
| `--path` membatasi | `MigrateCommand` memanggil `$migrator->paths()` — hanya 1 migration yang cocok pola |
| Tidak ada migration liar | Only 1 pending (Bagian 7) |
| `--force` wajib | `APP_ENV=production` |
| DDL additive-only | 4 CREATE + 18 ALTER, nol DROP/TRUNCATE/DELETE |
| Est. durasi | ~0,6 detik (bukti sandbox 622,88 ms) |
| Expected batch | 85 |

### Perintah yang DILARANG

| Perintah | Alasan |
|----------|--------|
| `migrate:fresh` | drop semua tabel |
| `migrate:refresh` | rollback semua + migrate semua |
| `migrate:reset` | rollback semua |
| `migrate:rollback` | menjalankan `down()` → **menghapus 4 tabel + semua data** |
| `db:wipe` | drop semua |
| `DROP TABLE` / `TRUNCATE` | kehilangan data |

### ⚠️ MariaDB DDL autocommits

Migration ini **tidak memakai transaksi**. Konsekuensi:

> **Skema parsial TIDAK bisa diasumsikan reversible.**
> Jika 2 dari 4 tabelMadewere berhasil lalu gagal, kedua tabel itu **tetap ada**.
> `migrate:rollback` akan menghapus keempatnya — termasuk yang berhasil.
> **Rollback otomatis TIDAK boleh dipakai.**

---

## 10. DAMPAK OPERASIONAL

### Status saat ini (semua read-only)

| Komponen | Status | Bukti |
|----------|--------|-------|
| Homepage existing | ✅ aktif | `GET /grafikaorion/` → 200 |
| Login route `/masuk` | ✅ aktif | `GET /grafikaorion/masuk` → 200 |
| Route `/baca/{slug}` | ✅ aktif | 404 untuk slug tidak ada (route hidup) |
| Feature flag | ✅ **OFF** | `LANDING_PAGE_BUILDER_ENABLED = false` |
| Route `login` | ✅ → `/masuk` | `AuthController@formLogin` |

### Dampak migrasi terhadap tiap modul

| Modul | Dampak | Alasan |
|-------|--------|--------|
| Kasir | ✅ nol | Migration hanya tambah tabel; tidak ada ALTER ke tabel ERP |
| SPK | ✅ nol | idem |
| Produksi | ✅ nol | idem |
| Collecting | ✅ nol | idem |
| Login | ✅ nol | idem |
| Homepage publik | ✅ nol | Flag OFF → resolver tidak pernah dipanggil |
| Tabel ERP | ✅ nol | Tidak satu pun `ALTER` ke tabel existing |

### Dampak ke pengguna

> **≈ 0 detik.** Feature flag OFF berarti homepage tetap served view `login`.
> Migrasi hanya menambah 4 tabel kosong yang tidak dibaca kode apa pun
> selama flag OFF.

### ⚠️ Jangan klaim kesiapan operasional dari PHPUnit saja

Phase 9 menjalankan regression suite. Itu **bukti skema & kode**, **bukan** bukti
operasional. Yang tetap perlu dilakukan manual sebelum go-live:

- [ ] Login manual lewat browser
- [ ] Buka `/kasir/monitoring` — harus normal
- [ ] Buka `/spk` — harus normal
- [ ] Buka `/produksi` — harus normal
- [ ] Buka `/collecting` — harus normal
- [ ] Buka `/setting/landing-page-builder` — harus normal
- [ ] `php artisan up` terverifikasi
- [ ] Health check: `php scripts/check_public_homepage.php <domain> existing`

---

## 11. RENCANA EKSEKUSI (RENCANA SAJA)

Untuk checkpoint eksekusi terpisah yang **belum diotorisasi**:

```
 1. Konfirmasi operator + verifier + jadwal (verifier ≠ operator)
 2. Verifikasi identitas target produksi        → harus oriondb
 3. Backup pra-migrasi                          → mysqldump + checksum
 4. Validasi integritas backup                 → 106 CREATE TABLE
 5. Restore-verify ke DB terisolasi            → oriondb_restore_verify
 6. Cek ulang pending migrations               → HARUS tepat 1
 7. Konfirmasi feature flag OFF                → harus false
 8. Ambil approval eksekusi eksplisit          → pesan terpisah
 ─────────────────────────────────────────────────────────────────
 9. 🛑 KONFIRMASI TERAKHIR — baca ulang sebelum write pertama
10. Eksekusi HANYA migration yang disetujui    → 622 ms
11. Verifikasi 4 tabel + constraint            → 110 tabel, 128 FK
12. Verifikasi skema ERP + login                → 106 → 110
13. Verifikasi homepage existing                → tetap view login
14. Backup pasca-migrasi                       → WAJIB
15. Verifikasi backup pasca-migrasi            → 110 CREATE TABLE
16. Dokumentasikan bukti                       → waktu, output, tanda tangan
17. Observasi kesehatan sistem                  → 3–7 hari
```

### 🛑 Konfirmasi sebelum write pertama

```
┌────────────────────────────────────────────────────────────┐
│ STOP - KONFIRMASI WAJIB                                     │
├────────────────────────────────────────────────────────────┤
│ 1. Target database   : oriondb            → benar?         │
│ 2. Migration pending : tepat 1            → benar?         │
│ 3. Backup tervalidasi: checksum + restore → benar?         │
│ 4. Verifier terpisah dari operator?       → ya/tidak?     │
│ 5. Approval eksplisit sudah diminta?     → ya/tidak?     │
└────────────────────────────────────────────────────────────┘
```

---

## 12. KONDISI STOP

| # | Kondisi | Tindakan |
|---|---------|----------|
| S1 | Pending migration ≠ 1 | **STOP** |
| S2 | Tabel landing page sudah ada | **STOP** — sudah pernah dimigrasi sebagian |
| S3 | Target database ≠ `oriondb` | **STOP** — jangan jalankan |
| S4 | Backup gagal / 0 byte | **STOP** |
| S5 | Restore-verify gagal | **STOP** |
| S6 | Transaksi aktif > 0 saat eksekusi | Tunggu sampai selesai |
| S7 | Error SQL saat migrasi | **STOP — rekonsiliasi manual. JANGAN rollback** |
| S8 | Migrasi parsial | **STOP — lengkapi manual. JANGAN rollback** |
| S9 | Error ERP setelah migrasi | `php artisan up` dulu, investigasi |
| S10 | Verifikasi tidak bisa dilakukan | **STOP** |

---

## 13. FORMULIR PERSETUJUAN

> **Dikosongkan.** Persetujuan tidak boleh diisi otomatis.

```
═══════════════════════════════════════════════════════════════
  PERSETUJUAN EKSEKUSI MIGRASI PRODUKSI (GATE A)
  Checkpoint 4E.5 — TIDAK ADA YANG DIJALANKAN
═══════════════════════════════════════════════════════════════

Target database      : oriondb
Migration            : 2026_10_08_233154_create_landing_page_builder_tables
SHA-256              : f95149726de3decf885df21bf49ae1abf8b338fa66ff0b23bd08573f23f1179e
Ukuran file          : 4608 byte
Batch yang diharapkan: 85
Tabel sebelum/sesudah: 106 → 110
FK sebelum/sesudah   : 119 → 128
Git commit           : a66b11384f42944752c97e62976821e8f2afa373

───────────────────────────────────────────────────────────────
CATATAN KENDALA (wajib dibaca)
───────────────────────────────────────────────────────────────
[!] Operator dan verifier SUBMIT orang yang sama: shin
    → Pemisahan tugas TIDAK terpenuhi. Verifikasi independen
      tidak ada. Risiko: kesalahan operator tidak terdeteksi
      oleh pihak kedua.
    → Tindakan yang disarankan: verifier diganti orang lain.

[!] Jendela 12:00 WIB SUDAH MELEWATI BATAS PERSIAPAN
    → Perlu dijadwalkan ulang ke slot ≥ 30 menit

[!] File migration BELUM DI-COMMIT (untracked)
    → Git tidak bisa membuktikan file tidak berubah
    → Verifikasi diganti: perbandingan skema aktual (9/9 FK,
      9/9 index cocok dengan sandbox terverifikasi)

───────────────────────────────────────────────────────────────
PRE-FLIGHT TEKNIS (semua ✅ dari 4E.5)
───────────────────────────────────────────────────────────────
[✅] Database oriondb, MariaDB 10.4.32, InnoDB
[✅] 106 tabel, 119 FK, 0 tabel landing page
[✅] Tepat 1 pending migration
[✅] users.id_user = bigint unsigned (FK kompatibel)
[✅] audit_logs 12 kolom, event varchar(20)
[✅] 0 transaksi aktif
[✅] Flag OFF
[✅] Backup destination 403 (tidak publik)
[✅] mysqldump tersedia
[✅] Baseline data ERP tercatat

───────────────────────────────────────────────────────────────
WAJIB DIISI SEBELUM EKSEKUSI
───────────────────────────────────────────────────────────────
Jadwal baru (tanggal)        : _______________________
Jadwal baru (waktu)          : _______________________
Migration operator           : _______________________
Independent verifier (≠ op)  : _______________________
Business owner approver      : _______________________
Rollback decision owner      : _______________________

Backup file baru             : _______________________
Backup SHA-256               : _______________________
Restore-verify DB            : oriondb_restore_verify
Restore-verify hasil         : _______________________
Offsite backup disalin       : [ ] Ya

───────────────────────────────────────────────────────────────
TANDA TANGAN
───────────────────────────────────────────────────────────────
Operator        : ______________  Tanggal : __________
Verifier        : ______________  Tanggal : __________
Approver        : ______________  Tanggal : __________

───────────────────────────────────────────────────────────────
KEPUTUSAN
───────────────────────────────────────────────────────────────
[ ] EXECUTION APPROVED  — jadwal baru: _______________________
[ ] NOT APPROVED — alasan: ___________________________________

[ ] GATE B (homepage publik) — TETAP TERBLOKIR
    Persetujuan Gate A TIDAK mengaktifkan Gate B.

Catatan:
_______________________________________________________________
═══════════════════════════════════════════════════════════════
```

---

## 14. FULL REGRESSION

```bash
php vendor/bin/phpunit -c phpunit.xml
```

**Target: `percetakan_erp_testing`.** Bukan `oriondb`, bukan sandbox.
Dijalankan berurutan, tanpa proses PHP paralel.

| Metrik | Baseline | **4E.5** | Status |
|--------|----------|-----------|--------|
| Tests | 1085 | **1085** | ✅ |
| Assertions | 5006 | **5006** | ✅ |
| **Failures** | 0 | **0** | ✅ |
| **Errors** | 0 | **0** | ✅ |
| Skipped | 1 | **1** | ✅ |
| Risky | 0 | **0** | ✅ |
| Deprecations | 194 | **194** | ✅ |
| Durasi | ~7:47 | 7:42 | ✅ |

Identik dengan baseline. Skip: `RawSqlAuditTest` (1) — pre-existing, tidak
terkait.

---

## 15. REKOMENDASI GO / NO-GO

# 🟡 **NO-GO untuk EKSEKUSI SEKARANG — GO CONDITIONAL setelah 3 hal diperbaiki**

### ❌ Alasan NO-GO sekarang

| # | Masalah | Severity |
|---|---------|----------|
| **N1** | **Pemisahan tugas tidak terpenuhi** — operator = verifier = `shin` | **TINGGI** |
| **N2** | **Jendela 12:00 WIB sudah terlewat** — hanya 3 menit sejak dibuka, butuh ≥30 menit | **TINGGI** |
| **N3** | **Tidak ada backup pra-migrasi baru** | **TINGGI** |
| N4 | Tidak ada restore-verify untuk backup baru | TINGGI |
| N5 | File migration belum di-commit → celah audit | SEDANG |
| N6 | Working tree sangat kotor (164 entri) | RENDAH |

### ✅ Yang sudah terbukti

| Aspek | Bukti |
|-------|-------|
| Pre-flight teknis | **0 blocker, 0 warning** |
| File migration | Skema identik dengan sandbox terverifikasi (9/9 FK, 9/9 index) |
| Exactly 1 pending | Dibuktikan dengan parsing akurat |
| Backup destination | 403 — tidak publik |
| mysqldump | Tersedia, versi cocok |
| Restore capability | Terbukti di 4E.3 (1,710 detik, 0 error) |
| Regression | 1085/5006, 0 failure |
| Dampak operasional | ≈ 0 detik (flag OFF) |

### 3 langkah untuk menjadi GO

```
1. Ganti verifier menjadi orang LAIN dari operator
   (atau setujui secara eksplisit bahwa verifikasi orang sama,
    dan catat kelemahannya di formulir)

2. Jadwalkan ulang ke slot ≥ 30 menit dengan operator
   + verifier + approver semuanya tersedia

3. Ambil authorization terpisah untuk membuat backup
   pra-migrasi + restore-verify (checkpoint ini tidak
   berwenang melakukannya)
```

### Setelah 3 hal itu?

**GO** — dengan prosedur:

```
Backup → validasi → restore-verify → cek pending = 1
→ konfirmasi final → migrate --path=<file> → verifikasi
→ backup pasca → observasi 3-7 hari
```

Estimasi durasi: **< 5 menit** termasuk backup.
Dampak pengguna: **≈ 0 detik**.

### Gate B tetap TERBLOKIR

> Migrasi hari ini **tidak boleh** diikuti menyalakan
> `LANDING_PAGE_BUILDER_ENABLED`. Itu checkpoint tersendiri dengan syarat
> yang belum terpenuhi (browser E2E, monitoring, sign-off, soak test).

---

## LAMPIRAN A — Verifikasi环境

```
$ git rev-parse HEAD
a66b11384f42944752c97e62976821e8f2afa373

$ (Get-FileHash database\migrations\2026_10_08_233154_create_landing_page_builder_tables.php -Algorithm SHA256).Hash
F95149726DE3DECF885DF21BF49AE1ABF8B338FA66FF0B23BD08573F23F1179E

$ Get-Content .env | Select-String "DB_DATABASE"
DB_DATABASE=oriondb

$ php artisan tinker --execute="echo config('landing_page_builder.enabled');"
false

$ curl -s -o /dev/null -w "%{http_code}" http://localhost/grafikaorion/
200

$ curl -s -o /dev/null -w "%{http_code}" http://localhost/grafikaorion/masuk
200

$ curl -s -o /dev/null -w "%{http_code}" http://localhost/grafikaorion/storage/app/backups/daily/backup_daily_20261009_010059.sql
403
```

## LAMPIRAN B — Reproduksi

```bash
# Pre-flight (READ-ONLY)
php <temp>/4e5_preflight.php

# Integritas migration (READ-ONLY)
php <temp>/4e5_migration_integrity.php
php <temp>/4e5_direct_compare.php
php <temp>/phase5_dryrun.php          # dengan DB_DATABASE=percetakan_erp_testing

# Baseline produksi (READ-ONLY)
php <temp>/4e4_prod_baseline.php

# Regression (WAJIB ke percetakan_erp_testing)
php vendor/bin/phpunit -c phpunit.xml
```

---

## STATUS AKHIR

# `LANDING PAGE BUILDER — PRODUCTION MIGRATION PRE-EXECUTION VERIFIED / AWAITING FINAL EXECUTION AUTHORIZATION`

- **Pre-flight teknis**: 0 blocker, 0 warning
- **File migration**: terbukti tidak berubah fungsional
- **Pending migration**: tepat 1, terbukti dengan parsing akurat
- **Backup readiness**: siap, tapi backup baru belum dibuat
- **Regression**: 1085 / 5006 / 0 failure / 0 error

> **PRODUCTION MIGRATION EXECUTION: NOT AUTHORIZED**
>
> Tidak ada yang dieksekusi terhadap `oriondb`. Tidak ada backup baru dibuat.
> Feature flag tetap `false`. Tidak ada database yang dihapus.
>
> **Menunggu:** (1) verifier terpisah dari operator, (2) jadwal baru,
> (3) otorisasi backup, (4) pesan eksplisit dari pengguna untuk eksekusi.
>
> **Saya akan berhenti di sini dan tidak melanjutkan secara otomatis.**