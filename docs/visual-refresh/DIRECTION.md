# Orion Grafika — Gambaran Aplikasi Laravel yang Lebih Profesional

Status: **DRAFT ARAH VISUAL — menunggu keputusan owner (bukan implementasi).**
Tanggal: 2026-11-10 · Cabang: `visual/pro-direction` · Repo: `steverog1945-bit/erpV3`

Dokumen ini merangkum hasil audit penuh aplikasi (V2 berjalan = referensi logika & struktur; V3 = target visual), lalu menyajikan arah desain profesional yang diusulkan. **Tidak ada kode aplikasi, database, atau `.env` yang berubah.** Lihat `PREVIEW.html` di folder yang sama untuk mockup interaktif (pilih tema & layar).

---

## 1. Apa yang ada hari ini (temuan audit)

### 1a. Duplikasi & token berceceran (tidak ada satu kebenaran)
- Tiga lapis token hidup bersamaan: `BrandThemeService` (preset `orion-classic` / `copilot-light` / `coastal-slate` / **aktif: `deep-ocean`**), `resources/css/app.css` (`@theme` + ratusan aturan `html.dark … !important`), dan `resources/css/ui-refresh.css` (token OpenCode). Nilai light/dark ditulis 3× dengan fallback berbeda; `.card` ada di dua file dengan radius berbeda; aturan `.rounded-2xl→8px` vs `.rounded-3xl→3px !important` bertabrakan.
- Mekanisme `!important` massal: huruf kapital dimatikan paksa, `.text-xs/[8–12px]` dipaksa jadi 13px — markup berbohong, CSS yang menang. Sulit dipelihara.
- Preset `indigo-600` yang tampil ≠ token teal aplikasi (nama token "indigo" berisi ramp teal/slate — warisan rename).

### 1b. Halaman error & permukaan publik bergaya berbeda-beda
- Semua halaman `errors/*` memuat **`cdn.tailwindcss.com`** (sudah diganti mandiri di build terbaru — lihat commit `d2398278`), dan kini didesain ulang penuh di commit tersebut. **V3 hilang 4 berkas error page** (`layout`, `405`, `419`, `429`).
- Publik (landing & `/masuk`) memakai palet **ROYAL INDIGO** (`#15143F` deep night, `#2E318E` royal indigo, `#4D55F8` electric violet, CTA `#FF6B17`). Offline.html memakai teal Orion Classic (`#006C7B`). App ERP memakai deep-ocean (`#146A80`). Tiga identitas dalam satu produk.
- Sidebar berisi blok "Status Kas" besar, lekukan aktif bergaya "notch", puluhan SVG inline beragam stroke, campur ikon FA/emoji/SVG. Tidak ada footer aplikasi, tidak ada breadcrumb (dihaki subtitle "Jalur / Halaman" manual).

### 1c. Tampilan tidak konsisten antar halaman
- Padding vertikal py-5/6/10; lebar container 1600px/6xl/5xl/3xl/90vw; tombol primer 3 warna berbeda di source (`x-button primary` = amber dengan fallback `#4F46E5`, `bg-indigo-600` ad hoc, `bg-[#006C7B]` hardcode); input "shadow-inner + border-none + bg-slate-50" (pola lama) vs token input (border 1px putih/radius 6px) vs label mengambang `.ld` (radius 16px/58px, bahasa ketiga) — tiga dialek isian; Swal radius 28px; radius source `rounded-2xl` di mana-mana tapi efektif 8px karena `!important`.
- Teks tertiary `#5F7167` gagal kontras AA untuk teks kecil; ada 13px paksaan; print CSS duplikat di laporan; laporan/akuntansi satu file dua identitas (layar/cetak).

### 1d. V3 (target) — snapshot penuh V2, belum jalan
- Laravel 12, 229 migrasi, ~200 blade, 84 controller / 80 model / 43 service; routes identik V2; `public/build` ada (artefak lokal); CI tidak ada; README masih skeleton Laravel; **`.env` V3 rusak** (parse error — 500) dan **`.env.sqlite.bak` ada di disk tanpa gitignore**; `public/storage-old2` **masih tersimpan di history git erpV3** (data unggahan pelanggan — lihat §5); root penuh ±43 laporan markdown & folder agen (`anti-slop*`, `skills*`).

---

## 2. Prinsip desain (operasional, bukan gaya-gayaan)

1. **Satu produk, satu identitas.** Semua permukaan (ERP, publik, login, errors, offline, print) memakai **satu set token**. Tidak ada palet ketiga "untuk sekdar".
2. **Tool hilang ke dalam tugas.** Density jujur, tabel padat tapi bernapas, halaman tenang; aksen dipakai untuk aksi/identitas saja.
3. **Keakraban yang diraih, bukan kejutan.** Pola ERP standar done well: sidebar, topbar + breadcrumb + ⌘K, tabel, form, toast/dialog, print.
4. **Token tunggal, tanpa `!important`.** Radius 4/6/8, skala spacing 4/8/12/16/24/32, bobot 400/500, Inter self-hosted, kontras AA (min 4.5:1 teks).
5. **Desktop ≥768px baseline; mobile <768px di `@media (max-width: 767.98px)`** (aturan proyek). Zero-diff desktop per rilis.
6. **Aksesibilitas struktural:** focus-visible ring accent, prefers-reduced-motion, dark mode via token (bukan ratusan `html.dark`), icon sprite tunggal (SVG stroke 1.5) — tanpa emoji & tanpa FA penuh.

---

## 3. Arah visual yang diusulkan — pilih SATU (keputusan owner)

| | **TEMA A — "Deep Ocean Pro"** (evolusi, risiko rendah) | **TEMA B — "Royal Indigo Pro"** (satu identitas, rekomendasi) |
|---|---|---|
| Sidebar | `#102126` → `#1A3037` (deep teal-navy), aksen `#56B8CD` | `#15143F` (deep night), aksen `#4D55F8` (electric violet) |
| Aksi utama | `#146A80` (teal-deep), hover `#0E5563`, teks putih | `#4D55F8`, hover `#2E318E` (royal indigo), teks putih |
| Permukaan | halaman `#F1F7F8`, kartu putih, border `#E2EAE4` | halaman `#F7F7FD`, kartu putih, border `#E4E3F0` |
| Teks | `#16231E` / `#3F4F47` / `#5F7167` | `#15143F` / `#403E6B` / `#6E6C9B` |
| POS gelap (input) | border + putih/soft | border + putih/soft |
| Publik (landing/login/errors/offline) | menyesuaikan ke teal-navy | **sudah** ROYAL INDIGO (tinggal disatukan) |
| Karakter | kalem, utilitarian, konservatif | lebih modern & brandable (sesuai publik yang sudah ada) |
| Biaya migrasi | kecil (token saja) | kecil–sedang (token + publik sudah cocok) |
| Risiko | identitas publik tetap beda (dua dunia) | penyatuan mengubah nuansa app — perlu persetujuan owner |

Rekomendasi: **Tema B**, karena permukaan publik sudah memakai ROYAL INDIGO dan errors terbaru sudah didesain ulang dengan warna teal; mengunify ke satu keluarga menghapus "tiga aplikasi dalam satu". Kerangka (layout) identik untuk keduanya — yang berubah hanya token. Keduanya tersedia di PREVIEW.html (tombol di kanan atas).

Nilai yang dipakai bersama (kedua tema):
```
radius-sm 4px (chip/badge) · radius-md 6px (tombol/input) · radius-lg 8px (card/tabel/modal/dialog)
type: 12/13/15/20/24 · bobot 400/500 · tabular-nums untuk uang/angka
spacing: 4 · 8 · 12 · 16 · 24 · 32 · konten max 1600px · form max 720px · py-6
tabel: row 44px · header uppercase 11px · hover lembut · sticky header · kolom uang kanan rata
komponen: 1 tombol (primary/subtle/ghost) · 1 input · 1 kartu · 1 tabel · 1 badge (dot) · 1 empty state · 1 dialog (8px)
ikon: SVG stroke 1.5, satu set (sprite) · fokus: outline 2px accent + ring soft
dark: token penuh (--surface-*, --text-*, --border-*, --accent-*) — tanpa !important override
```

---

## 4. Struktur yang diusulkan (shell baru)

```
<aside 264px>  logo default SVG (bukan mojibake) · grup MENU (DASBOR · PENJUALAN ·
                PRODUKSI · GUDANG · KEUANGAN · MASTER · PENGATURAN) · label grup 11px ·
                ikon stroke 1.5 · aktif = soft-accent fill + bar kiri 3px · ⌘K hint
                · footer: avatar kasir + "Mode Sidebar" collapse → rail 72px ikon
<main>
  <topbar sticky>  hamburger · breadcrumb ("Penjualan / Kasir") · ⌘K command palette
                   · lonceng (badge jumlah) · chip kas · toggle tema (admin) · profil
  <content>        halaman maksimum lebar token; footer: versi · env chip · tahun
```

- **Status Kas** keluar dari sidebar → chip topbar (kondisional).
- **Breadcrumb** komponen resmi; **footer** resmi; **notifikasi** polling as-is (tidak diubah logikanya).
- Mobile (<768px) tetap drawer + bottom-nav pola eksisting; hanya token yang berubah.
- Print: stylesheet cetak terpisah (layar ≠ cetak), header cetak resmi satu partial.

---

## 5. Keputusan yang HANYA bisa diambil owner (blokir implementasi)

1. **Tema A atau Tema B** untuk penyatuan identitas.
2. **`public/storage-old2` di repo `erpV3`**: file penghapusan ratusan aset unggahan pelanggan (order thumbnails, logo, .cdr) **masih ada di history commit awal erpV3** (GitHub). Pilihan: (a) biarkan history, catat risiko; (b) purge history + force-push (koordinat ion GitHub, koordinasi semua clone); (c) pindahkan erpV3 ke repo privat atau hapus repo & buat ulang. **Belum ada yang dikerjakan — menunggu keputusan.**
3. **`.env.sqlite.bak`** (berisi kemungkinan kredensial) di disk V3 — belum di-commit, tapi rawan. Perlu keputusan hapus/arsip (tidak disentuh agen).
4. **Kelayakan menjalankan V3**: `.env` rusak (parse error) — perbaikan `.env` hanya oleh owner (agen dilarang membaca/mengubah `.env`). Untuk melihat hasil refresh, minimal V3 butuh `.env` uji (sqlite sintetis + `APP_KEY`) dari owner, atau review lewat PREVIEW.html/screenshot statis.
5. **Pemulihan 4 error page yang hilang di V3** (`layout`, `405`, `419`, `429`) — usul: disalin dari V2 saat tahap refresh (file hanya tampilan, bukan logika).
6. **CI di V3** (build Vite) belum ada; usul tambah workflow build seperti V2.
7. **README V3** masih skeleton Laravel — usul tulis README ERP + panduan DB sintetis.

---

## 6. Rencana implementasi bertahap (setelah keputusan)

| Fase | Lingkup | Risiko |
|---|---|---|
| 0 | Kebersihan: keputusan §5; pulihkan 4 errors; gitignore `storage-old2`; konsistensi baris `.gitignore` | rendah |
| 1 | Token tunggal `resources/css/brand.css` (+ light/dark); hapus dialek `!important` bertahap; font tetap Inter | menengah (kaskade) |
| 2 | Shell baru: layout, sidebar, topbar, breadcrumb, footer, command palette (⌘K) | sedang (200 halaman konsumsi layout yang sama) |
| 3 | Komponen konsolidasi: card/button/input/select/table/badge/modal/toast; hapus `layouts/` duplikat; `x-button` primary = 1 warna | sedang |
| 4 | Migrasi modul bertahap + screenshot regression (desktop & mobile) per modul: Kasir → Pesanan/Jersey → Pembelian → Master → Keuangan → Laporan → Pengaturan | tinggi, dilakukan bertahap |
| 5 | Publik: login/landing/offline/errors satu palet; BRAND.md (logo SVG, palet, kontras AA, lisensi font OFL) | rendah |

Setiap fase: commit per gugus, push ke feature branch, tanpa sentuh `.env`/DB/logika bisnis, desktop ≥768px zero-diff per fase, laporan bukti (build + screenshot nyata).

---

## 7. Batasan & jujur
- V3 **tidak dijalankan**; semua klaim kelayakan berbasis inspeksi file. Build Vite belum dijalankan di sesi ini.
- Audit V2/V3 oleh agen read-only; ukuran byte/dimensi PNG tidak diukur langsung (dikecualikan); sebagian klaim visual efektif (radius/kaskade `!important`) adalah analisis statis CSS, bukan inspeksi runtime.
- `PREVIEW.html` adalah **konsep statis**, bukan halaman ERP yang berjalan; data di dalamnya contoh sintetis.
