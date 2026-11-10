# AGENTS.md — Orion Grafika ERP (V3)

## Workspace & Branch
- Repo: `steverog1945-bit/erpV3`
- Dev workspace: `C:\xampp\htdocs\grafikaorionV3`
- Cabang: `main` (saat ini); cabang fitur per tugas.
- **JANGAN** akses `C:\xampp\htdocs\grafikaorion` (produksi).

## ARSIP HISTORIS — mandat repo/PR lama (tidak aktif)
Hanya untuk rujukan. Jangan anggap masih tugas aktif.

- **PR #21 / PWA Mobile Phase 2** (`steverog1945-bit/percetakan-app`):
  matriks `PWA_MOBILE_COVERAGE_MATRIX.md` mencatat 194 Blade = 14 SOURCE MOBILE
  (UNTESTED), 108 GENERIC ONLY / UI TODO, 63 PARTIAL, 9 PRINT. Kontrak PWA v2 ini
  **dicabut** oleh kontrak V3 di bawah.
- **PR #20** (sudah merged ke main): `integration/pwa-mobile-all-modules`,
  `PWA_MOBILE_ONE_PASS_EXECUTION.md`, `PWA_MOBILE_COMPLETION_PHASE2.md`,
  `PWA_MOBILE_RELEASE_RUNBOOK.md`. **Dicabut.** Jangan merge ulang PR #20,
  jangan ciptakan PR per modul, jangan implementasi ulang Master/POS/SPK/
  Produksi/Collecting/Jersey.
- **PR #15 (Master Bahan), #18 (POS), #19 (SPK)**: kode SPK & Produksi terkait
  sudah tergabung di main melalui PR #20. PR #19 dipelihara sebagai catatan sejarah
  sampai admin resmi melepasnya.
- Ringkasan di atas memangkas dokumen lama; kontrak kerja saat ini diuraikan di
  bawah.

## Ruang Lingkup Pengembangan V3
- Tujuan: pengembangan ERP dan penambahan scope terbaru. Perubahan yang telah
  diverifikasi nantinya diterapkan ke produksi melalui repository.
- Cakupan bebas: frontend, backend, role/permission, integrasi, database,
  sesuai permintaan owner.
- Mobile: desktop >=768px tetap baseline.
- Tugas saat ini (perbarui aturan proyek & siapkan skill): tanpa mandat mengubah
  fitur aplikasi. Tidak mengubah kode aplikasi atau database.

## Alur perubahan aplikasi
1. Pemeriksaan: direktori, git, konfigurasi, skema aktual
2. Implementasi terbatas
3. Tes yang relevan
4. Pemeriksaan browser (jika visual)
5. Review diff
6. Laporan bukti

## Keselamatan
- JANGAN baca/mengubah `.env`, kredensial, database `oriondb`, file produksi,
  backup, sertifikat, Apache/SSL/scheduler, atau Windows services.
- JANGAN menjalankan migration, seeder, Artisan, aplikasi, worker, atau scheduler
  yang menyentuh DB sebelum lingkungan dev/testing terverifikasi dan database
  sintetis digunakan. Status workspace development bukan bukti isolasi database.
- JANGAN ubah business logic, transaksi, stok, perhitungan, jurnal, route, model,
  controller, RBAC, API, PWA SW, atau konfigurasi produksi untuk scope UI/CSS/Blade.
- JANGAN merge, deploy, force-push, atau push ke `main` otomatis. Push hanya ke
  feature branch yang secara eksplisit menjadi target tugas.
- JANGAN mengganti model atau provider otomatis. Laporkan model/provider aktif
  tanpa rahasia serta pilihan yang tersedia.

## Standar Visual Mobile (<768px)
- CSS khusus mobile WAJIB di dalam `@media screen and (max-width: 767.98px)`.
- Zero-diff desktop: layar >=768px TIDAK BOLEH mengalami perubahan visual.
- Modularitas: dispatcher `mobile-ui.css` → `mobile-dashboard.css`,
  `mobile-bahan.css`, `mobile-modal.css`, `mobile-nav.css`.
- Integritas fungsional: jaga `id`, `name`, `form action`, event handler JS yang
  ada.
- Toleran light/dark mode dan safe-area (`env(safe-area-inset-bottom)`).

## Definition of Done
1. Implementasi selesai dalam scope.
2. Validasi faktual: tinjau diff & whitespace; jalankan lint/tes yang aman.
   Bedakan PASS/FAIL/NOT TESTED/BLOCKED.
3. Laporan permanen dibuat/sebelum commit akhir; jangan duplikat.
4. Commit ke feature branch aktif; jangan empty commit.
5. Push terevidensi: push normal; cocokkan SHA HEAD lokal & remote.
6. PR: gunakan PR yang ada; jangan merge/deploy.
7. LAPORAN AKHIR di chat utama: satu pesan final lengkap tanpa diminta.

## Format Laporan Final
```
ORION GRAFIKA — LAPORAN FINAL
Status: SELESAI / PARSIAL / BLOCKED
Task:
Branch:
HEAD lokal:
HEAD remote:
Commit perubahan:
PR:
File berubah:
Hasil implementasi:
Validasi: PASS [...]; FAIL [...]; NOT TESTED [...]
Screenshot: ERP ASLI / STATIC PREVIEW / TIDAK ADA
Risiko atau hambatan:
Produksi: TIDAK DISENTUH
Merge/deploy: TIDAK DILAKUKAN
```

## Database & Testing
- Gunakan hanya lingkungan sintetis yang terisolasi. Jika tidak tersedia,
  tandai NOT TESTED.
- Tidak ada akses ke database produksi `oriondb`.

## Skill Hermes yang tersedia (sudah terpasang; gunakan sesuai kebutuhan,
tidak duplikat)
- `orion-erp-audit` — audit, debug, verifikasi Laravel Orion Grafika; otorisasi,
  alur transaksi, regresi desktop/PWA mobile.
- `local-erp-setup` — setup Laravel lokal, migrasi (MySQL), testing, debugging
  HTTP 500, push/git.
- `systematic-debugging` (software-development) — debugging berbasis akar masalah.
- `sdlc-review` (devops) — review SDLC, persiapan rilis, git review.
- `github` (software-development) — GitHub via gh CLI: PR/issu/review.
- `requesting-code-review` (software-development) — pre-commit review.
- `codebase-inspection`, `dogfood`, `spike`, `test-driven-development`
  (software-development) — inspeksi, QA, eksperimen, TDD.
- `git-commit-workflow` — commit workflow untuk repo baru.
- `hermes-agent` — use/configure/theme/extend/orchestrate Hermes Agent.
