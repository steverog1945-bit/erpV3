# CHECKPOINT 4E.2 — RENCANA SANDBOX (MENUNGGU PERSETUJUAN)

> **BELUM ADA YANG DIJALANKAN.** Dokumen ini hanya-usulan operasi.
> Tidak ada `CREATE DATABASE`, `DROP DATABASE`, atau `restore` yang dieksekusi.
> Menunggu persetujuan eksplisit sebelum Phase 6 dan Phase 7 dijalankan.

---

## 1. TUJUAN

Membuktikan bahwa migration Landing Page Builder **dapat diterapkan ke database
yang identik dengan produksi tanpa merusak data ERP yang ada**, sebelum
migration itu dijalankan ke `oriondb`.

---

## 2. OPERASI YANG DIUSULKAN (SEMUA BUTUH PERSETUJUAN)

### Operasi A — Buat database sandbox

```sql
CREATE DATABASE `oriondb_migration_sandbox`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

| Aspek | Nilai |
|-------|-------|
| Nama | `oriondb_migration_sandbox` |
| Karakter set | `utf8mb4` / `utf8mb4_unicode_ci` (identik dengan `oriondb`) |
| Engine | default server (InnoDB) |
| Risiko | **RENDAH** — database baru, kosong |
| Reversibel | Ya, `DROP DATABASE` (hanya setelah paket 4E.2 selesai) |

### Operasi B — Restore backup produksi ke sandbox

```bash
mysql -h 127.0.0.1 -u root oriondb_migration_sandbox \
  < storage/app/backups/daily/backup_daily_20261009_010059.sql
```

| Aspek | Nilai |
|-------|-------|
| Sumber | `backup_daily_20261009_010059.sql` (630.810 byte, SHA256 `ef815551da06…`) |
| Asal dump | `oriondb`, MariaDB 10.4.32 — **versi sama persis dengan server** |
| Isi | 106 tabel, karakter set & kolasi seragam |
| Target | sandbox, **BUKAN** `oriondb` |
| Perkiraan durasi | < 30 detik (630 KB) |
| Risiko | **RENDAH** — hanya menulis ke sandbox |
| Tidak menyentuh | `oriondb`, `percetakan_erp_testing`, file backup |

### Operasi C — Jalankan migration di sandbox

```bash
# Pastikan target benar DULU
php artisan migrate:status --database=mysql \
  | grep oriondb_migration_sandbox

DB_DATABASE=oriondb_migration_sandbox php artisan migrate \
  --path=database/migrations/2026_10_08_233154_create_landing_page_builder_tables.php \
  --force
```

| Aspek | Nilai |
|-------|-------|
| Database | `oriondb_migration_sandbox` — diverifikasi 3x sebelum jalan |
| Migration | hanya 1 file yang disetujui |
| Dilarang | `migrate:fresh`, `migrate:refresh`, `migrate:rollback` |
| Risiko | **RENDAH** terhadap produksi — sandbox terisolasi |
| Dampak ke `oriondb` | **NOL** |

### Operasi D — Verifikasi pasca-migration (read-only)

Query `information_schema` untuk memastikan 4 tabel, FK, dan index benar.
`SELECT` baris ERP untuk memastikan data utuh. **Tidak ada write.**

---

## 3. YANG TIDAK AKAN DIJALANKAN

| Larangan | Status |
|----------|--------|
| `CREATE DATABASE oriondb` | ❌ tidak |
| `ALTER` / `DROP` di `oriondb` | ❌ tidak |
| Restore ke `oriondb` | ❌ tidak |
| `migrate:fresh` / `migrate:refresh` | ❌ tidak |
| `migrate:rollback` ke DB operasional | ❌ tidak |
| `DROP DATABASE oriondb_migration_sandbox` otomatis | ❌ **tidak akan dihapus otomatis** |
| Test destruktif (DROP TABLE) di sandbox | ❌ **tidak** — paket ini berisi data produksi, wajib diperlakukan read-only setelah restore |
| Menjalankan PHPUnit terhadap sandbox | ❌ **tidak** — `RefreshDatabase` akan `migrate:fresh` dan **menghancurkan data produksi yang dipulihkan**. Regression tetap jalan di `percetakan_erp_testing`. |
| Dependency baru | ❌ tidak |

---

## 4. PEMISAHAN ISOLASI

| Database | Peran | Dapat ditulis? |
|----------|-------|----------------|
| `oriondb` | **Operasional** | ❌ TIDAK PERNAH |
| `percetakan_erp_testing` | Regression PHPUnit | ✅ ya (RefreshDatabase) |
| `oriondb_migration_sandbox` | Uji migrasi | ✅ ya (fase 6–7 saja), setelah itu **read-only** |

Ketiganya terpisah. Tidak ada skrip, konfigurasi, atau `.env` yang diarahkan ke
sandbox secara permanen — `DB_DATABASE` hanya di-set per-perintah.

---

## 5. KRITERIA KEBERHASILAN

Phase 6 & 7 dianggap **BERHASIL** hanya bila SEMUA terpenuhi:

### Restore (Phase 6)
- [ ] Sandbox berisi **106 tabel**
- [ ] Struktur `users`, `transaksi`, `audit_logs` identik dengan `oriondb`
- [ ] Jumlah baris ERP representatif sama dengan `oriondb` (.users, transaksi, produk)
- [ ] Karakter set & kolasi seragam `utf8mb4` / `utf8mb4_unicode_ci`
- [ ] Tidak ada error

### Migration (Phase 7)
- [ ] 4 tabel landing page terbentuk
- [ ] **9 foreign key** terbentuk, semua `users(id_user)` cocok
- [ ] Unique constraint: `slug`, `landing_page_id` (draft), `(landing_page_id, revision_number)`, `path`
- [ ] Index: `status`, `published_revision_id`, `version`, `revision_number`, `(landing_page_id, mime_type)`
- [ ] Jumlah tabel ERP tetap **106** (tidak ada yang hilang)
- [ ] Jumlah baris ERP **tidak berubah**
- [ ] Migration tercatat di tabel `migrations` (batch 85)
- [ ] `insert audit_logs` tetap bekerja (uji kompatibilitas)

### Jika salah satu gagal
- **STOP**, jangan lanjut ke produksi.
- Laporkan ke checkpoint berikutnya.
- Sandbox **tidak dihapus** (untuk investigasi).

---

## 6. PERMINTAAN PERSETUJUAN

Saya memerlukan persetujuan eksplisit untuk:

| # | Operasi | Risiko |
|---|---------|--------|
| A | `CREATE DATABASE oriondb_migration_sandbox` | Rendah |
| B | Restore backup `backup_daily_20261009_010059.sql` ke sandbox | Rendah |
| C | Jalankan 1 migration yang disetujui di sandbox | Rendah |
| D | Query verifikasi read-only | Sangat rendah |

**Belum ada yang dieksekusi.** Mohon konfirmasi:

- [ ] Setujui semua (A+B+C+D)
- [ ] Setujui sebagian (sebutkan)
- [ ] Jangan setujui

**Catatan:** Grant `CREATE` untuk user database mungkin perlu diaktifkan dulu.
Kalau `CREATE DATABASE` ditolak hak akses, operasi A gagal dan seluruh
paket 4E.2 berhenti di situ — dengan laporan jujur.

---

## 7. BIAYA & RISIKO

| Aspek | Nilai |
|-------|-------|
| Waktu total | ± 3–5 menit |
| Ukuran tambahan | ~2 MB |
| Risiko ke `oriondb` | **NOL** — tidak ada perintah yang menyebut `oriondb` sebagai target tulis |
| Risiko ke `percetakan_erp_testing` | **NOL** — regression tetap di sana |
| Yang bisa hilang | Tidak ada. Semua operasi bersifat additive pada database baru |