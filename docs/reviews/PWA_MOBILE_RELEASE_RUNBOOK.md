# ORION GRAFIKA — PWA Mobile Release Runbook

## Status

**DRAFT — Belum diuji runtime.** Runbook ini adalah panduan operator untuk release PWA Mobile. Semua langkah harus diuji di staging sebelum produksi.

## Prasyarat

1. Branch `feat/pwa-mobile-completion-phase2` sudah di-merge ke `main` (belum — masih Draft PR #21).
2. Vite build berhasil (`npm run build`).
3. PHPUnit suite lulus (1419+ tests).
4. Laravel runtime tersedia di staging.
5. Database staging sintetis (bukan produksi `oriondb`).

## Langkah Release

### 1. Backup
- Backup database staging (sintetis).
- Backup file produksi (jangan sentuh `C:\xampp\htdocs\grafikaorion`).
- Catat SHA commit saat ini.

### 2. Deploy Asset
- Upload `public/build/` (manifest.json + hashed assets) ke staging.
- Verifikasi `manifest.json` berisi hash yang benar.
- Clear cache browser (service worker).

### 3. Validasi Staging
- Buka staging di browser mobile (320/360/390/430/767px).
- Buka staging di desktop (768/1024/1366px).
- Verifikasi PWA install (Android/iOS).
- Verifikasi offline mode (service worker).
- Verifikasi safe-area (iPhone notch).
- Verifikasi dark mode.
- Verifikasi filter/search/pagination di setiap modul.
- Verifikasi scroll sampai item terakhir (tidak tertutup nav).

### 4. Rollback
- Jika ada masalah: revert ke SHA sebelumnya.
- Restore database staging dari backup.
- Clear cache browser.
- Verifikasi rollback berhasil.

### 5. GO/NO-GO
- Owner review visual (screenshot ERP asli).
- Owner approval eksplisit.
- Merge ke main (owner approval).
- Deploy ke produksi (operator + owner approval).

## Checklist Operator

- [ ] Backup database staging
- [ ] Backup file produksi
- [ ] Catat SHA commit
- [ ] Upload asset (public/build/)
- [ ] Verifikasi manifest.json
- [ ] Clear cache browser
- [ ] Buka staging mobile (320/360/390/430/767)
- [ ] Buka staging desktop (768/1024/1366)
- [ ] Verifikasi PWA install
- [ ] Verifikasi offline mode
- [ ] Verifikasi safe-area
- [ ] Verifikasi dark mode
- [ ] Verifikasi filter/search/pagination
- [ ] Verifikasi scroll sampai item terakhir
- [ ] Owner review visual
- [ ] Owner approval eksplisit
- [ ] Merge ke main (owner approval)
- [ ] Deploy ke produksi (operator + owner approval)

## Risiko

1. **Laravel runtime tidak tersedia:** Semua bukti visual adalah STATIC SOURCE-DERIVED.
2. **gh CLI tidak terautentikasi:** Draft PR perlu dibuat manual.
3. **Modul lanjutan belum selesai:** Collecting, Jersey, Stok, Pembelian, Master lainnya, Keuangan, Laporan, Dashboard, Pengaturan hanya memiliki fondasi CSS generik.
4. **Produksi tidak disentuh:** Semua perubahan di branch feature, belum merge ke main.

## Produksi

- TIDAK DISENTUH
- Merge/deploy: TIDAK DILAKUKAN
