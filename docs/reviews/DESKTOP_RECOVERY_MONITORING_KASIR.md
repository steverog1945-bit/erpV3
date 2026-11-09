# ORION GRAFIKA — DESKTOP RECOVERY & PWA MOBILE QA

**Status pekerjaan:** SELESAI (regresi desktop Monitoring Kasir dipulihkan & diverifikasi browser)
**Status produksi:** NO-GO (belum ada review operator/owner; tidak ada merge/deploy)
**Branch:** `fix/form-filter-month-typography` (PR #22, tetap Draft)
**HEAD sebelum:** `df45f052daed039ead55c2f797ab3952a40519bf`
**HEAD sesudah:** lihat commit `fix(kasir): restore horizontal desktop filter...` pada branch ini

---

## 1. Ringkasan eksekutif

Gejala pada screenshot owner (filter Monitoring Kasir desktop bertumpuk vertikal, panel
terlalu tinggi, tabel terdorong ke bawah) **berhasil direproduksi di browser nyata** dan
**root cause-nya terbukti**, bukan tebakan:

- Regresi diperkenalkan oleh commit **`1b0c1cf`** ("feat(pwa): visual QA pass ... compact kasir filter").
  Commit itu mengubah markup filter dari deretan `<div>` langsung di dalam `<form class="flex flex-wrap items-end gap-4">`
  menjadi `<details class="pwa-ops-filter">` + `summary` + `<div class="pwa-ops-filter-fields">`, lalu menambahkan
  aturan CSS `resources/css/mobile-operations.css`: `.pwa-ops-filter{display:contents}`.
- **`display:contents` pada elemen `<details>` tidak bekerja di Chromium.** Elemen `<details>` tetap
  membangun kotak `::details-content` sendiri, sehingga anak-anaknya (5 field + tombol aksi)
  **tidak pernah menjadi item flex dari `<form>`**. Akibatnya di desktop:
  - `getBoundingClientRect()` form = **0 px tinggi**, kotak kartu filter tingginya hanya **40 px (kosong)**,
    dan 5 field "menumpuk" di luar kartu (pada Chromium lama tampil menumpuk vertikal;
    pada Chromium 156 subtrees-nya di-skip sehingga panel filter tampak **kosong/blur**),
  - `<summary>` disembunyikan di desktop (`display:none`) sehingga filter **tidak bisa dibuka sama sekali**.

Pemulihan dilakukan **seminimal mungkin**: satu blok `@media screen and (min-width:768px)` di
`mobile-operations.css` yang mengubah `<details>` menjadi kotak flex nyata dan menetralkan
`::details-content`. Tidak ada perubahan markup, Blade, JS, route, model, controller, atau RBAC.

**Hasil: geometri desktop kembali PERSIS sama dengan baseline historis** (lihat §4).

---

## 2. Desktop baseline yang dipakai

| Item | Nilai |
|---|---|
| Baseline SHA | `1b0c1cf^` (commit sebelum regresi; marker `1b0c1cf` = awal mula `<details class="pwa-ops-filter">` + `display:contents`) |
| Sumber bukti | Diff `git show 1b0c1cf^:resources/views/kasir/monitoring_transaksi.blade.php` + pengukuran runtime di Laravel staging |
| Status baseline | **TERVERIFIKASI** — dirender langsung dari source baseline di lingkungan staging yang sama, lalu dibandingkan metriknya |

Baseline desktop: `<form class="flex flex-wrap items-end gap-4">` berisi langsung 5 field wrapper
(`w-full md:w-40/w-44/w-56`) + tombol Filter + link Reset → satu baris horizontal (wrap alami bila lebar kurang).

---

## 3. Bukti root cause (browser nyata, Chromium 156 / Playwright)

Metrik diambil dengan `getComputedStyle` + `getBoundingClientRect` pada halaman Blade asli
(`/kasir/monitoring`, login sungguhan, data fixture sintetis), build CSS Vite dari source.

| Properti (1366 px) | Baseline `1b0c1cf^` | Sebelum perbaikan (`df45f05`) | Sesudah perbaikan |
|---|---|---|---|
| `display` `<details class="pwa-ops-filter>` | – (belum ada `<details>`) | `contents` | `flex` |
| Tinggi `<form>` filter | 144 px | **0 px** | 144 px |
| Tinggi kartu filter | 184 px | **40 px (kosong)** | 184 px |
| Posisi atas tabel transaksi | y = 489 | y = 344 (tabel menempel kartu kosong) | y = 489 |
| Baris field | 4 + 1 (wrap) | 5 baris menumpuk di luar kartu | 4 + 1 (wrap) |
| `<summary>` (toggle) | – | `display:none`, tak bisa dibuka | `display:none`, tak dibutuhkan (field selalu terlihat) |
| Input font-size / weight | 14 px / 400 | 14 px / 400 | 14 px / 400 |
| Label font-size | 13 px | 13 px | 13 px |

---

## 4. Pemulihan desktop = baseline (angka identik, semua viewport)

Pengukuran runtime halaman yang sama, data sama, role sama.

| Viewport | Baseline `1b0c1cf^` (form / kartu / tabel-y) | Sesudah perbaikan | Kesamaan |
|---|---|---|---|
| 1920 | 64 / 104 / 408 | 64 / 104 / 408 | **IDENTIK** |
| 1440 | 116 / 156 / 460 | 116 / 156 / 460 | **IDENTIK** |
| 1366 | 144 / 184 / 489 | 144 / 184 / 489 | **IDENTIK** |
| 1280 | 144 / 184 / 510 | 144 / 184 / 510 | **IDENTIK** |
| 1024 | 144 / 184 / 510 | 144 / 184 / 510 | **IDENTIK** |
| 768  | 144 / 184 / 510 | 144 / 184 / 510 | **IDENTIK** |

Koordinat per-field di 1366 & 768 juga identik persis (x, y, lebar, tinggi), termasuk posisi
tombol Filter (557,407) di 1366 dan (453,428) di 768.

---

## 5. Mobile PWA: nol perubahan (zero-diff)

Perbaikan diisolasi di dalam `@media screen and (min-width:768px)`. Perbandingan sebelum vs sesudah
pada 320/360/375/390/430/767 px menunjukkan **angka identik semua**: `display:details=block`,
toggle `summary` terlihat, field tersembunyi saat tertutup, tinggi form 54 px, kartu 82–94 px,
input 16 px, label 13 px, tanpa overflow horizontal.

---

## 6. Bukti visual (file PNG nyata di repo)

Semua di `docs/reviews/assets/pwa-mobile-typography/`:

| File | Isi |
|---|---|
| `baseline-{768..1920}.png` | Desktop baseline `1b0c1cf^` (sebelum `<details>`) |
| `before-{768..1920}.png` | Kondisi rusak di HEAD `df45f05` (kartu filter kosong / field menumpuk) |
| `after-{768..1920}.png` | Kondisi sesudah perbaikan (baris filter horizontal) |
| `before-{320..767}.png` vs `after-{320..767}.png` | Bukti mobile zero-diff |
| `before-active-{1366,390}.png` | Filter aktif (query string) sebelum perbaikan |
| `qa-desktop-1366-filter-aktif.png`, `qa-desktop-1366-filter-reset.png` | Alur filter & reset (role `kasir`) |
| `qa-mobile-390-filter-tutup.png`, `qa-mobile-390-filter-buka.png` | Toggle filter mobile (role `kasir`) |
| `admin-smoke-1366-*.png` | Smoke lintas modul (role `admin`) |
| `*-metrics.json` | Metrik computed style & geometry mentah |

---

## 7. Verifikasi fungsional (staging terisolasi, data sintetis)

Lingkungan: PHP 8.3 + `php artisan serve` + SQLite **terisolasi** `orion_pwa_staging_pr21`
(dibuat baru dari nol di workspace testing, di-set lewat env `DB_CONNECTION`/`DB_DATABASE`,
**tidak** mewarisi `.env` produksi; seeder `StagingFixtureSeeder` punya penjegan *fail-closed*
yang menolak berjalan di luar database itu). Tidak ada migrasi/seeder yang dijalankan terhadap
database apa pun selain itu.

| Pemeriksaan | Hasil |
|---|---|
| Login `kasir` → `/kasir/monitoring` | PASS (200, render Blade asli) |
| Submit filter GET (tgl mulai/selesai + status) | PASS — URL berisi `status_filtered=1&tgl_mulai=...&status_bayar=Lunas`, `<details>` terbuka, kelas `pwa-ops-filter--active` aktif, 2 baris data |
| Tombol/konfirmasi Reset Filter | PASS — kembali ke `/kasir/monitoring` tanpa query string |
| Toggle filter mobile (390 px) | PASS — field tertutup → terbuka saat diklik, target sentuh 44 px |
| Tipografi | PASS — desktop input 14 px/400, mobile 16 px, label 13 px/500 |
| Smoke lintas modul (role admin): `/kasir/monitoring`, `/kartu-stok`, `/bahan-baku`, `/pembelian-bahan`, `/laporan/kas` | PASS — 200, tanpa error JS konsol |
| RBAC | PASS (perilaku benar) — akun `kasir` menerima **403** di `/pembelian-bahan` dan `/laporan/kas` |
| `npm run build` (Vite) | PASS |
| `git diff --check` (whitespace) | PASS |
| Scope diff | PASS — hanya `.pwa-ops-filter` di `mobile-operations.css`; selector itu hanya dipakai `kasir/monitoring_transaksi.blade.php` |

---

## 8. Temuan backend (DILAPORKAN, TIDAK DIUBAH)

Semua muncul hanya karena staging memakai **SQLite**, sementara produksi memakai **MySQL/MariaDB**:

1. `DashboardController` (baris 317) dan `QcController` (baris 28) memakai `orderByRaw("FIELD(...)")`
   → SQLite tidak punya fungsi `FIELD` → `/dashboard` dan `/sinyal` widget menghasilkan 500 **di staging saja**.
2. Migrasi MySQL-only (`SHOW COLUMNS`, `information_schema`, `ALTER ... MODIFY`, `ENUM`) gagal di SQLite;
   untuk kebutuhan staging dibuat patch **sekali jalan di database staging** (skrip lokal, tidak dikomit).
   Konsekuensinya tabel `jersey_order_lines` tidak terbentuk → `/jersey/keuangan` 500 di staging (NOT TESTED).
3. Akun `kasir` (role `Operator Produksi` pada fixture) ditolak akses `/pembelian-bahan` dan `/laporan/kas` (403).
   Perilaku fail-closed middleware `CheckMenuAccess`; tidak diubah.

---

## 9. Status gate

| Gate | Status |
|---|---|
| A — Preflight (branch, SHA, worktree, tools) | PASS |
| B — Forensic (root cause terbukti di browser) | PASS |
| C — Desktop restore (minimal, tanpa redesign) | PASS |
| D — Desktop visual 768–1920 vs baseline | PASS (identik) |
| E — Mobile QA (zero-diff ≤767 px) | PASS |
| F — Functional QA | PASS (monitoring); smoke modul lain PASS untuk role admin |
| G — Pelaporan & evidence | PASS (dokumen ini + PNG/JSON) |
| Matrix lintas modul penuh (semua role/route) | NOT TESTED (di luar scope perbaikan ini) |
| Dark mode | NOT TESTED (perbaikan tidak menyentuh warna/komponen berwarna sama sekali) |
| PHPUnit / test database | BLOCKED (staging SQLite; query MySQL-only) |

---

## 10. Perubahan kode

| File | Perubahan |
|---|---|
| `resources/css/mobile-operations.css` | Ganti `.pwa-ops-filter{display:contents}` (1 baris) dengan blok `@media screen and (min-width:768px)` berisi flex nyata untuk `<details>` + `::details-content{display:contents;content-visibility:visible}`; komentar disesuaikan. Net +12 baris, tanpa `!important`, tanpa selector baru di luar `.pwa-ops-filter`. |
| `docs/reviews/DESKTOP_RECOVERY_MONITORING_KASIR.md` | Dokumen ini. |
| `docs/reviews/assets/pwa-mobile-typography/*` | Bukti PNG + JSON hasil browser. |

---

## 11. Risiko & keputusan

- **Risiko tersisa:** aturan baru bergantung pada pseudo-element `::details-content` (Chromium ≥ 131;
  didukung sejak Chrome 131, Firefox 131, Safari 18). Di browser lama yang belum mengenal
  `::details-content` selektor diabaikan, dan perilaku jatuh ke kondisi sebelumnya (bukan lebih buruk).
  Bila owner memakai browser yang lebih lama, mohon konfirmasi tampilan desktop.
- **Rekomendasi keputusan:** **NO-GO untuk merge/deploy** sampai owner memverifikasi galeri bukti dan
  menjalankan QA di lingkungan staging MySQL sendiri (khususnya `/dashboard`, yang di staging SQLite
  tidak bisa diuji karena keterbatasan `FIELD()`).
- **Merge/deploy:** TIDAK DILAKUKAN. PR #22 tetap **Draft**.
- **Akses produksi:** TIDAK DILAKUKAN (folder produksi, `.env`, `oriondb`, backup, sertifikat tidak disentuh).
