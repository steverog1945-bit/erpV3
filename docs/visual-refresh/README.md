# Visual Refresh — Konsep (2026-11)

STATUS: **DRAFT ARAH — menunggu keputusan owner. Bukan kode aplikasi.**

## Isi folder
- `DIRECTION.md` — hasil audit penuh aplikasi + arah visual yang diusulkan
  (dua tema, rencana fase, keputusan yang menunggu owner).
- `PREVIEW.html` — mockup interaktif: pilih tema (Tema A "Deep Ocean Pro" /
  Tema B "Royal Indigo Pro", default B) dan layar (Masuk, Dasbor, Kasir,
  Master Bahan). Data di dalamnya sintetis.
- `vendor/` — salinan lokal Inter (SIL OFL) supaya preview tampil sama di
  semua mesin tanpa dependensi ke folder lain.

## Cara membuka
- Buka `PREVIEW.html` lewat preview pane OpenCode, atau sajikan folder ini
  sebagai static root apa pun (mis. `php -S 127.0.0.1:8295 -t docs/visual-refresh`).
- Tanpa server pun file-nya bisa dibuka sebagai file lokal.

## Batasan
- Tidak mengubah `app/`, `resources/`, `routes/`, `database/`, `.env`, atau
  file aplikasi lain. Hanya folder `docs/visual-refresh/`.
- Implementasi nyata (bila disetujui) dilakukan bertahap di V3 sesuai
  §6 DIRECTION.md — tetap tidak menyentuh `.env` & database.
