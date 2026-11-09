# AUDIT FASE 0 - BASELINE (read-only)

Tanggal: 2026-09-30. Sumber kebenaran: source code di worktree + pemeriksaan status HTTP ke server lokal.
Batas: database TIDAK diperiksa (worktree tanpa `.env`/`vendor`; `.env` utama hanya dibaca 9 kunci non-rahasia). Semua yang butuh DB/EXPLAIN/uji konkurensi = **UNKNOWN** sampai Fase 1+.

## 1. Peta arsitektur (terbukti dari kode)

```
Browser (LAN) -> Apache (docroot = folder proyek, BUKAN public/) -> /.htaccess -> /index.php
 -> routes/web.php (286 rute) -> middleware: auth + menu.access (CheckMenuAccess) [+ restrict.input-only pada sebagian rute Jersey/penjualan]
 -> Controller (67) -> [Form Request: TIDAK ADA, validasi inline $request->validate()] -> Service (34) -> Model (75) / DB::table (816 pemanggilan)
 -> MySQL/MariaDB. Event/Job/Listener/Observer/Policy: 0 (tidak dipakai)
```
- Otorisasi: satu gerbang `CheckMenuAccess` + `config/menu_access.php` (prefix URL -> id_menu) + `Izin` (view/add/edit/delete dari verb HTTP) + `AksesHarga` + `roles_baca_saja`. Tidak ada Policy. Fail-closed untuk rute tak terpetakan.
- Audit: `AuditLogger` (23 file). Karena 816 `DB::table`, audit **manual per-pemanggilan**, bukan otomatis.
- Nomor dokumen: `DocumentNumberService` (counter atomik `INSERT..ON DUPLICATE KEY`) + `JerseyOrder::generateOrderNumber` (lock + unique).
- Stok: `kartu_stok` + `bahan_baku.stok`; reversal lewat `StokReversalService`.
- Jurnal: `AccountingService`, `JerseyAccountingService`, `AccountingPeriodService` (tutup buku via `PeriodeAkuntansiTertutupException`).
- Backup: `BackupService` (mysqldump + salin lokasi kedua) dijadwalkan di `routes/console.php` (daily/weekly/monthly).
- Test: 78 file di `tests/Feature`.
- Migrasi: 174 (63 memakai `hasTable/hasColumn`).

## 2. KEEP / DO NOT CHANGE (terbukti benar)

| Area | Bukti |
|---|---|
| Validasi ukuran Jersey dinamis dari `jersey_sizes` | `JerseyOrderController::ukuranMasterAktif()` dipakai store/updateLines/storeLineSusulan, cache per-request (tidak N query). Bukan whitelist hard-code. |
| Reversal stok berbasis snapshot mutasi asli | `StokReversalService::kembalikan` - snapshot `transaksi_detail_bahan_pakai`, `lockForUpdate`, consume-once, guard anti-dobel, transaksi sendiri, log ke audit. Target arsitektur Anda sudah tercapai untuk era snapshot. |
| Penomoran dokumen atomik | `DocumentNumberService`; Jersey: lock + `unique(order_number)`. |
| Rute non-admin fail-closed | `CheckMenuAccess` menolak rute tak terpetakan (403). |
| Void pembayaran Jersey lewat Approval | `JerseyFinanceController::destroyPayment` + jurnal dibalik saat disetujui (histori tidak dihapus). |
| Rate-limit login, CSRF, upload bukti dibatasi `image|mimes|max` | `LoginRateLimitTest`, `JerseyFinanceController::uploadBukti`. |
| Migrasi "duplikat" `create` | Aman: yang kedua dibungkus `hasTable` / `dropIfExists` (harga_promo, jurnal_umum, supplier, setting_harga, pembelian_bahan, jersey_order_*, kartu_stoks di-drop 2026_09_10). Jangan diedit. |

## 3. TEMUAN

Format: SEVERITY | modul | file | fungsi | masalah | dampak | akar | perbaikan | DB? | risiko

### CRITICAL

**C-1. Berkas rahasia & dump database bisa diunduh siapa pun di LAN** (DIBUKTIKAN: HTTP 200)
- FILE: `.htaccess` (root) + struktur docroot = folder proyek.
- PROBLEM: `RewriteCond !-f` membiarkan file yang ada disajikan langsung. `GET /grafikaorion/.env`, `/oriondb.sql` (453 KB, dump penuh), `/backups/*.sql`, `/backups/my_rec.ini`, `/storage/logs/laravel.log`, `/composer.json` semuanya **200**. (Hanya status yang diperiksa, isi tidak dibaca.)
- IMPACT: APP_KEY + kredensial DB bocor, seluruh data (konsumen, harga, hash password user) dapat diunduh; APP_KEY bocor memungkinkan pemalsuan cookie/sesi terenkripsi.
- ROOT CAUSE: aplikasi dilayani dari root proyek, bukan `public/`; tidak ada aturan deny.
- FIX (tanpa migrasi): (a) tambah aturan deny di `.htaccess` root untuk dotfile, `*.sql`, `*.log`, `*.ini`, `*.md`, `composer.*`, `package*`, dan direktori `app|bootstrap|config|database|backups|storage|vendor|routes|resources|tests|docs|scripts|node_modules` KECUALI `storage/app/public` (lewat symlink `public/storage`); (b) jangka menengah: pindahkan docroot ke `public/` atau vhost; (c) pindahkan `oriondb.sql` dan `backups/` ke luar docroot; (d) setelah (a) rotasi APP_KEY (butuh rencana: sesi terenkripsi kadaluarsa) dan password DB, anggap sudah bocor.
- DB CHANGE: tidak. RISK fix: rendah-sedang (jangan memblokir `storage/app/public` & `build/`).

### HIGH

**H-1. Pembayaran Jersey: tidak ada validasi kelebihan bayar & tidak ada lock sebelum insert**
- FILE: `app/Http/Controllers/Jersey/JerseyFinanceController.php` `storePayment()` (baris 51-81).
- PROBLEM: `jumlah` hanya `min:1`; tidak dibandingkan dengan sisa tagihan. Lock hanya diambil di `recalculatePembayaran()` SETELAH baris pembayaran & jurnal dibuat. Dua kasir menekan simpan bersamaan (atau klik ganda) -> dua pembayaran + dua jurnal untuk tagihan yang sama.
- IMPACT: uang & jurnal ganda / deposit > total. Status jadi 'Lunas' walau kelebihan.
- FIX: dalam `DB::transaction`: `lockForUpdate` baris `jersey_orders` LEBIH DULU, hitung sisa dari ledger, tolak jika `jumlah > sisa` (atau minta konfirmasi eksplisit), baru insert. Tambah token anti-klik-ganda (idempotency key). DB: tidak wajib.
- `metode_bayar` `in:Tunai,Transfer,QRIS,Lainnya` hard-code, tidak terhubung master Bank/kas (aturan #12) -> UNKNOWN apakah `JerseyAccountingService` memetakan Transfer vs Tunai ke akun yang benar; verifikasi di Fase 3 (akuntansi).
- `bukti_path` diterima sebagai string bebas dari klien (bukan diverifikasi berasal dari `uploadBukti`) -> path arbitrer tersimpan; jadikan validasi `starts_with:jersey_bukti_bayar/` + `exists` di disk.

**H-2. Cutoff "era snapshot" hard-code ke tanggal masa depan**
- FILE: `app/Services/StokReversalService.php:138` (`gte('2026-10-14')`).
- PROBLEM: hari ini 2026-09-30. Nota yang dibuat sebelum 2026-10-14 dianggap "era lama": bila snapshot kosong (mis. produk tanpa komponen BOM saat dibuat) dan BOM produk ditambah belakangan, pembatalan memakai `kembalikanRecomputeBomLama` (BOM LIVE) -> stok fiktif. Ini persis pola B yang Anda tandai HIGH/CRITICAL.
- FIX: ganti dengan penanda data (mis. keberadaan baris OUT kartu_stok berreferensi nota / kolom penanda), atau tanggal deploy sebenarnya. UNKNOWN: kapan snapshot mulai aktif di produksi - perlu query `MIN(created_at)` `transaksi_detail_bahan_pakai`.
- Catatan MEDIUM: snapshot dihapus (consume-once) -> bukti mutasi asli hanya tersisa di `audit_logs`/`kartu_stok`. Pertimbangkan kolom `direversal_at` alih-alih `DELETE` (migrasi korektif baru, jangan ubah lama).

**H-3. Stok bisa berubah tanpa gembok pada beberapa jalur** (perlu verifikasi baris-per-baris)
- `BahanBakuController::store` (aman: bahan baru), tetapi `PembelianBahanController` (1 tx, 1 lock, hitung `stokSebelumBatch` dari objek yang dibaca - baris 215) dan `Jersey/JerseyOrderController` (5 tx, **0** lock) perlu dicek: pemotongan stok/estimasi bahan Jersey. Belum dibuktikan bug -> **UNKNOWN**, dijadwalkan Fase 2 (stok).

**H-4. MySQL kemungkinan terbuka ke LAN**
- `my.ini` tidak punya `bind-address`/`skip-networking` -> default XAMPP mendengarkan semua interface, port 3306; akun `root` XAMPP biasanya tanpa password. UNKNOWN sampai dicek `SHOW GRANTS` & firewall. Target Anda: klien tidak perlu akses langsung ke MySQL -> `bind-address=127.0.0.1`.

### MEDIUM

- **M-1. Pesan exception mentah ke pengguna**: 31 tempat `with('error', $e->getMessage())`/json (mis. `BahanBakuController::store` baris ~199) -> bocor detail SQL/internal. Ganti pesan generik + `report($e)`; pertahankan pesan bisnis (`RuntimeException` sengaja).
- **M-2. Kolom/nama DB tidak konsisten**: `.env` utama `DB_DATABASE=oriondb`, sedangkan instruksi menyebut `percetakan_erp` -> UNKNOWN mana kanonik. `.env.example` default `sqlite`, `APP_ENV=local`, `LOG_LEVEL=debug`; `.env` produksi `APP_ENV=local` (seharusnya `production`), `APP_URL=http://localhost` (tidak cocok untuk akses LAN).
- **M-3. Tidak ada Form Request/Policy**: validasi inline, otorisasi hanya prefix URL. Aksi "kepemilikan data" (divisi A vs B) hanya ada di `DivisiService` - cakupannya belum diuji (Fase 4).
- **M-4. `if nama_role === 'Administrator'` di ~18 tempat** (Controller/Service/Support). Konsisten, terpusat sebagian di `Izin::adalahAdmin`, tetapi masih tersebar (`PenjualanController` 757/1024, `SpkController` 67, `SinyalController` 42, `KartuStokController` 206, dst). Sarankan semua memanggil `Izin::adalahAdmin`; bukan mendesak.
- **M-5. Statistik besar di memori**: `JerseyFinanceController::index` memuat SEMUA order belum lunas (`->get()`) lalu `sum/filter` di PHP -> ganti aggregate SQL saat data membesar. `KonsumenTimeline`, laporan lain: belum diaudit.
- **M-6. Berkas sampah di docroot**: `public/_prev_kasir.html` (tracked; memuat token CSRF & base href lama), `daftar_isi.txt`, `list_file.txt`, `oriondb.sql`, `backups/` (isinya termasuk `mysql_data_raw_backup_*`).

### LOW
- `index.php` root memakai `__DIR__.'/../storage/framework/maintenance.php'` (path salah untuk lokasi root; mode maintenance tidak akan terdeteksi).
- 0 Job/Queue walau `QUEUE_CONNECTION=database`; cache & sesi di DB menambah beban query per request.

## 4. Sudah diperbaiki pada sesi ini (sebelum Fase 0)
- 403 pada `jasa-harga`, `finishing-harga`, `setting-harga-tier` (rute tier harga tidak terpetakan di `menu_access.php`).
- Login selalu ke Dashboard; landing page login; pita judul grup sidebar.

## 5. Celah cakupan Fase 0 yang BELUM bisa dijawab (butuh DB)
Orphan record, FK/index/precision uang, drift skema vs migrasi, EXPLAIN, jurnal debit=kredit, kartu_stok vs `bahan_baku.stok` (rekonsiliasi), negative stock, duplikat referensi. -> jalankan `php artisan audit:integritas` (`AuditIntegritasCommand` sudah ada) di server utama dan kirim keluarannya; **jangan** bangun ulang.

## 6. Usulan Fase 1 (menunggu persetujuan)
1. C-1 `.htaccess` deny + pindahkan dump/backups (file: `.htaccess`; tambah tes `tests/Feature/BerkasRahasiaTest`).
2. H-1 pembayaran Jersey (file: `JerseyFinanceController.php`; tes konkurensi + overpay).
3. H-2 cutoff era (file: `StokReversalService.php`; butuh jawaban tanggal deploy).
4. H-4 `bind-address` (konfigurasi server, bukan kode).
Dependensi: 1 sebelum lainnya. Risiko regresi: 1 rendah, 2 sedang (alur kasir Jersey), 3 sedang (stok).
