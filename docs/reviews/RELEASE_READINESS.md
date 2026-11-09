# Release readiness — Master Bahan mobile polish

## Status

Checklist persiapan rilis untuk perubahan presentasi pada Draft PR #15. Ini bukan persetujuan deploy. Tidak ada perubahan atau pemeriksaan pada sistem produksi.

- Branch: `fix/mobile-visual-final-polish`
- Base: `origin/main` (`aee1d5d01b0de3c3c6fd6a0fe33545e6f6b082f6`)
- Implementasi CSS terbaru: `9a0c56da463e4c004e87c0635541158891331f03`
- PR: [#15 — Draft](https://github.com/steverog1945-bit/percetakan-app/pull/15)
- Kategori perubahan: CSS responsif khusus daftar Master Bahan; tanpa migration atau perubahan backend.

## Perbedaan terhadap main

Pada branch PR, cakupan terkait terdiri dari `resources/css/mobile-bahan.css`, `resources/views/master/bahan/index.blade.php`, laporan review ini, dan dokumen kesiapan rilis. Commit implementasi terbaru hanya menyentuh CSS. Perubahan sebelumnya pada Blade mempertahankan satu row dan sembilan sel sumber, serta membungkus aksi existing untuk tampilan mobile. Tidak ada perubahan bisnis, route, RBAC, data, atau perhitungan.

## Build dan pemeriksaan yang tersedia

- Vite frontend production build: **PASS**.
- `php -l` pada Blade Master Bahan dan komponen tabel: **PASS** (syntax PHP; bukan render Laravel).
- `git diff --check`: **PASS**.
- Static source-derived viewport checks: **PASS terbatas** pada 320, 360, 390, 430, 767, 768, dan 1024px; hasil dan batasan dicatat di `MOBILE_VISUAL_FIDELITY_FINAL.md`.
- Laravel/browser ERP, database, authorization HTTP, modal/Select2, perangkat iOS, dan data operasional: **NOT TESTED**.

Build menghasilkan asset CSS bernama hashed beserta manifest Vite. Rilis yang mengikutkan perubahan ini perlu menerbitkan manifest dan asset hasil build yang sepadan menggunakan pipeline normal proyek; jangan hanya menyalin source CSS.

## PWA compatibility

- Tidak ada perubahan pada `manifest.json`, `theme-color`, service worker, caching strategy, atau scope.
- Perubahan CSS tidak mengubah instalasi PWA atau routing. Nama asset hasil Vite berubah sesuai hash; verifikasi deployment menyajikan manifest dan asset CSS pasangannya.
- Perilaku cache service worker belum diuji. Jangan mengubah cache/scope untuk mengatasi tampilan stale tanpa validasi terpisah.

## Checklist sebelum deploy (operator rilis)

1. Tinjau dan setujui PR; jangan deploy sebelum approval dan CI wajib proyek lulus.
2. Simpan backup artefak rilis aktif, minimal manifest Vite dan asset build yang akan diganti; gunakan mekanisme backup aplikasi standar proyek.
3. Pastikan tersedia paket build sebelumnya untuk rollback. Tidak ada kebutuhan perubahan skema atau migrasi untuk perubahan ini.
4. Deploy hanya melalui proses rilis resmi yang berwenang; pekerjaan ini sendiri tidak menjalankan deployment.

## Smoke test setelah deploy

Di environment rilis yang diotorisasi, periksa Master Bahan pada 320/360/390/430px dan desktop >=768px: layout kartu/kolom, teks stok dan harga, search/filter, pagination, checkbox/bulk action, menu edit/status/delete, modal CRUD dan Select2. Buka/tutup drawer dan modal, pastikan floating nav tidak menutupi backdrop/kontrol, konfirmasi safe-area pada iPhone, serta verifikasi light/dark dan asset CSS baru dimuat. Jangan mengubah atau menguji data transaksi operasional untuk validasi visual.

## Rollback

Jika tampilan regresi, gunakan prosedur rollback rilis standar untuk mengembalikan source ke revisi sebelum perubahan Master Bahan dan pulihkan pasangan manifest/asset frontend sebelumnya secara konsisten. Jangan mengubah database karena perubahan ini tidak memiliki migration. Setelah rollback, lakukan smoke test halaman Master Bahan dan pastikan asset lama tersedia serta cache client dapat mengambil versi yang benar. Tidak ada rollback produksi yang dilakukan oleh pekerjaan ini.

## Risiko yang belum diverifikasi

- Belum ada screenshot atau render dari runtime Laravel; preview yang tersedia statis dan memakai placeholder.
- Data nyata yang panjang, status/empty state, modal/Select2, drawer/backdrop, sentuhan perangkat, safe-area iOS, dark mode di runtime, dan service-worker cache belum diverifikasi.
- Rilis tetap memerlukan persetujuan owner/reviewer, CI proyek, serta verifikasi operasional melalui jalur berwenang.

## Pola reusable dan urutan modul

Kandidat pola mobile reusable setelah owner menyetujui Master Bahan: filter kompak/kolaps, presentasi kartu dari data row yang sama tanpa duplikasi, label responsif per sel, disclosure aksi dengan target sentuh memadai, dan navigasi tema-aware yang menghormati safe-area. Prioritas lanjutan: **POS → SPK → Produksi → Collecting → Jersey → Keuangan → Laporan → Master lainnya**. Tidak ada modul tersebut yang diimplementasikan pada PR #15.

**Dampak produksi: ZERO.** Tidak ada akses produksi, `.env`, database, migration, server, merge, atau deployment.
