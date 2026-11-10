# Orion Grafika, Design System (DESIGN.md)

Sumber kebenaran visual tunggal untuk ERP Orion Grafika (repo erpV3, app.blade.php shell).
Dibuat 2026-11-10 atas perintah owner: "rombak total, Command Center, topbar, anti slop".

## Produk
- B2B ERP percetakan: kasir/POS, pesanan, produksi SPK, bahan, keuangan, laporan, master data.
- Audiens: kasir, operator produksi, admin/owner. Desktop >=768px baseline; mobile <768px di `@media (max-width: 767.98px)`.
- Karakter: tenang, padat data, terpercaya, kerja berat (data sering, harga, stok).

## Suara & teks
- Bahasa Indonesia lugas; kata benda & kata kerja konkret ("Simpan", "Cetak nota", "Antre produksi"), bukan hype.
- Tidak ada em dash, tidak ada emoji dekoratif, tidak ada "AI Powered/Seamless".
- Angka: `tabular-nums`; uang `Rp 1.234.567`; tanggal `d M Y` atau relatif sederhana.
- Label kapitalisasi sentence case; teks tombol spesifik ("Simpan transaksi", bukan "Submit").

## Token warna (light mode)
- Latar halaman: `#E9EEFA` (ice). Permukaan kartu: `#FFFFFF`, border `1px #E3E9F6`.
- Teks: `#0F1E3E` (navy ink), sekunder `#3E4C74`, tersier `#5D6A85`.
- **Satu aksen:** `#2563EB` (hover `#1D4ED8`), soft `#E3EBFF`, ring `rgba(37,99,235,.22)`.
- Sukses `#0E9F6E`, peringatan `#D97706`, bahaya `#DC2626`; masing-masing soft `-bg`.
- Neutral ramp: `text-slate-*` tetap dipakai sebagai legacy; jangan perkenalkan warna baru.

## Dark mode (`html.dark`)
- `--pro-bg:#141A29`, kartu `--pro-surface:#1C2436`, border `#2A3450` (strong `#3A4767`).
- Teks `#E8ECF8` / `#B9C3DD` / `#8A96B8`; aksen `#5B8DEF` (hover `#7FA6F5`), soft `#22304F`.

## Skala & bentuk
- Radius: chip `6px`, input/tombol/ikon `8-10px`, blok dalam `12px`, card/panel/tabel/modal `16px` (acu ke referensi Command Center).
- Spacing: skala 4/8/12/16/24/32px. Padding kartu 16-20px; form row gap 12px.
- Shadow: `--pro-shadow-sm` (kartu biasa), `--pro-shadow-md` (hover/angkat), `--pro-shadow-lg/xl` (panel terangkat: palette, modal, mega menu). Flat by default.
- Tipografi: Inter (self-hosted, `public/vendor/fonts/inter*.woff2`); basis 13px untuk data; judul halaman 20-24px/600; angka 18-28px/600 tabular.
- Weight hanya 400/500/600. Tidak ada 700+ kecuali judul besar.

## Shell (baru, tidak berubah lagi tanpa persetujuan)
- **Topbar 2 baris** (bukan sidebar): baris 1 = logo + cari (Ctrl+K / `#cmdk-react`) + util (tema, lonceng, profil); baris 2 = tab modul: Dasbor, Kasir, Produksi, Bahan Baku, Laporan, Pengaturan + tombol "Semua Menu" (`#menuPanelBtn` -> panel dropdown dengan seluruh menu dari sidebar lama yang dipindahkan + RBAC asli).
- Menu tetap dirender di `#mainSidebar` (hidden by default, toggle `.terbuka`); sumber data command palette.
- Footer: "ORION GRAFIKA ERP . PUSAT OPERASI" + "TOPOLOGI . SLA KEAMANAN . ERP CORE v1.0".

## Komponen kanonik
- Tombol: 1 primary biru, outline, ghost; `x-button` = satu-satunya API (varian primary = `#2563EB`).
- Input/select: putih, border `#E3E9F6`, focus ring biru; tinggi 38px (form), match dengan Select2 global.
- Card: putih/dark-surface, border tipis tegas `#E3E9F6`, radius 16px, padding konsisten, shadow-sm.
- Tabel: header uppercase 11px, baris 44px, hover lembut, kolom uang rata kanan.
- Badge: dot + teks, warna status saja (hijau/kuning/merah/biru), bukan dekorasi.
- Toast/Dialog: SweetAlert2 global; `window.Swal` mixin di layout; jangan inline `confirm()`.

## Motion (MOTION dial: 1)
- Masuk: transisi singkat 150-220ms ease `cubic-bezier(.22,1,.36,1)`; tidak ada bounce, tidak ada infinite pulse kecuali `.live-dot` kecil.
- Panel menu/modal: spring halus via framer-motion di palette & CSS transition pada panel menu.
- Fade antarhalaman: `.content-wrapper` animasi `proContentIn` .34s; keluar `pv2-leaving` .18s ease-out (dihormati reduced-motion).
- `prefers-reduced-motion`: matikan semua.

## Anti-slop (diringkas, wajib)
- Tidak ada gradient pelangi, tidak ada glow berlebihan, tidak ada glass di navbar/card.
- Satu aksen: biru; digunakan terbatas untuk aksi/keadaan aktif.
- Angka & fakta tidak boleh dikarang; keadaan kosong/loading/error punya teks penyebab + aksi.
- Semua kontrol punya perilaku nyata atau dihapus.
- Ikon: satu set (Font Awesome/SVG inline); relevan, bukan dekorasi.

## Yang TIDAK BOLEH berubah
- `window.openModal/closeModal`, `#mainSidebar`, `#sidebarOverlay`, `#cmdk-react`, `#input_*`, semua `data-*`, `sr-only`, `.chip*`, Swal CSS hooks, jQuery/Select2 global, `@stack('scripts')` paling akhir.
- Route/RBAC/menu/permissions, semua logika bisnis, perhitungan, transaksi.
- Aturan mobile `<768px` terpisah; zero-diff desktop untuk modul yang belum ditembus.

## Status
- 2026-11-10 (V3): topbar 2 baris + mega menu grid (accordion hanya mobile), kartu radius 16px, background ice dengan radial wash lembut, font smoothing (antialiased + grayscale), feature-settings cv11/ss01, fade in/out antarhalaman. Ramp Tailwind `indigo` digeser ke biru Command Center sehingga seluruh halaman otomatis ikut tanpa re-theme manual.
