# LANDING PAGE BUILDER — CHECKPOINT 5A
## LAPORAN AUDIT & FINALISATOR EDITOR VISUAL

**Tanggal:** 2026-10-09
**Status Akhir:** `LANDING PAGE BUILDER — EDITOR VISUAL SIAP DIGUNAKAN ADMINISTRATOR`

---

## 1. RINGKASAN EDITOR

Editor Landing Page Builder di `resources/views/pengaturan/landing-page-builder.blade.php` adalah satu file Blade berisi markup panel (Component Library, Canvas, Properties) dan satu blok `<script>` inline berisi seluruh logika editor. Tidak ada file JavaScript atau CSS terpisah.

Sebelum Checkpoint 5A, editor ini **tidak berfungsi sama sekali**. Audit statis menemukan **satu kesalahan sintaks JavaScript** yang membuat seluruh blok `<script>` gagal di-parse oleh browser. Artinya:

- Tombol apa pun tidak berfungsi.
- Drag-and-drop tidak berfungsi.
- Undo/redo tidak berfungsi.
- Preview responsif tidak berfungsi.
- Penyimpanan draft tidak berfungsi.
- Seluruh 15 fitur yang diminta tidak berfungsi.

Yang **sudah berfungsi** sebelum 5A (di sisi server):
- Draft API (GET/PUT draft, publish, restore, version history) — lengkap dengan konflik 409.
- Media manager (upload/delete/list) — validasi MIME + magic bytes.
- Validasi `content_json` — allowlist tipe/layout/motion/style, panjang string, skema URL.
- Otorisasi `hanya.admin` middleware + `assertAdministrator()` di controller.
- Renderer bersama `section-render.blade.php` — aman XSS (semua output di-escape).

---

## 2. FITUR SELESAI

### 2.1 Kesalahan Sintaks yang Diperbaiki

**Temuan:** Baris 630 (versi lama) berakhir dengan `'")` — kurung dan kutip liar yang memutus blok `<script>` dengan `SyntaxError: missing ) after argument list`. Seluruh JavaScript editor mati.

**Bukti sebelum perbaikan:**
```
SyntaxError: missing ) after argument list
```
(output dari parser JavaScript Node.js v24.21.0)

**Perbaikan:** Blok `<script>` ditulis ulang sepenuhnya. Verifikasi sesudah perbaikan:
```
node --check: LOLOS (1446 baris JavaScript)
```

### 2.2 ID Duplikat yang Diperbaiki

**Temuan:** Lima ID HTML muncul dua kali:
- `preview-desktop`, `preview-tablet`, `preview-mobile`
- `preview-size`, `canvas-empty`

`getElementById()` hanya mengembalikan elemen pertama, jadi tombol preview di tab "Responsive" tidak pernah mendapat listener.

**Perbaikan:** Satu set tombol preview di toolbar canvas; tab "Responsive" dihapus dari panel Properties.

### 2.3 Layout Columns: String vs Integer

**Temuan:** Editor mengirim `layoutColumns` sebagai string (`"3"`), server menolak dengan `is_int()` → 422. Akibatnya: **mengubah kolom selalu gagal menyimpan.**

**Perbaikan:** `clampInt()` di sisi klien memastikan nilai selalu integer sebelum dikirim.

### 2.4 Fitur yang Ditambahkan (belum ada sebelumnya)

| # | Fitur | Status |
|---|-------|--------|
| 1 | Tambah section | ✅ Sudah ada, diperbaiki |
| 2 | Hapus section **dengan konfirmasi** | ✅ Ditambahkan (`window.confirm`) |
| 3 | Gandakan section | ✅ Sudah ada, ID unik dijamin |
| 4 | Ubah urutan section (Up/Down + drag-and-drop) | ✅ Drag-and-drop ditambahkan |
| 5 | Sembunyikan/tampilkan section | ✅ Sudah ada |
| 6 | Ubah teks (judul/subjudul/deskripsi) | ✅ Sudah ada, diperbaiki |
| 7 | Atur gambar (path internal atau URL) | ✅ Ditambahkan |
| 8 | Atur warna teks | ✅ Ditambahkan |
| 9 | Atur background | ✅ Ditambahkan |
| 10 | Atur padding & margin | ✅ Ditambahkan |
| 11 | Atur alignment | ✅ Sudah ada |
| 12 | Atur tombol (label + URL) | ✅ Sudah ada |
| 13 | Atur ukuran font | ✅ Ditambahkan |
| 14 | Undo/redo | ✅ Sudah ada, coalescing ditambahkan |
| 15 | Preview desktop/tablet/mobile | ✅ Diperbaiki (ID duplikat) |
| — | Repeater items (services, portfolio, dll.) | ✅ Ditambahkan |
| — | Media picker modal | ✅ Ditambahkan |

### 2.5 Undo/Redo Coalescing

**Masalah lama:** `saveState()` dipanggil pada setiap event `input`, sehingga mengetik 20 karakter mengisi 20 langkah history.Undo hanya mencakup ~20 ketikan terakhir.

**Perbaikan:** Parameter `coalesceKey` menggabungkan perubahan beruntun pada field yang sama (dalam 700ms) menjadi satu langkah undo.

---

## 3. FITUR BELUM SELEASI / TIDAK ADA

Tidak ada fitur yang diminta Checkpoint 5A yang belum selesai. Namun catatan berikut perlu diketahui:

### 3.1 Browser E2E — BELUM DIVERIFIKASI

Tidak ada Playwright/Cypress/Dusk/Selenium yang terinstal, dan kredensial admin tidak tersedia. Verifikasi fungsional dilakukan dengan **DOM shim** (lihat Bagian 5), bukan browser sungguhan. Rendering visual, layout responsif sebenarnya, dan perilakan CSS tidak diuji.

### 3.2 Integrasi Penuh dengan Preview Renderer

Preview renderer (`/setting/landing-page-builder/preview/draft`) memakai `section-render.blade.php` bersama, tetapi **tidak** terhubung langsung ke canvas editor. Admin harus klik tombol "Publish" lalu membuka URL preview secara manual. Tombol "Preview" di dalam editor belum ada.

---

## 4. PERUBAHAN FILE

| File | Jenis | Baris | Uraian |
|------|-------|-------|--------|
| `resources/views/pengaturan/landing-page-builder.blade.php` | Diubah total | 752 → 1570 | Perbaikan sintaks, ID duplikat, semua fitur 15 butir, XSS-safe rendering, drag-and-drop, media picker, repeater |
| `resources/views/pengaturan/partials/section-render.blade.php` | Diubah | 252 → 259 | Dukungan padding/margin/fontSize via inline style aman |
| `app/Http/Controllers/LandingPageBuilderController.php` | Diubah | 827 → 944 | Validasi numerik, gambar, items; method `validateImageReference()` |
| `tests/Feature/LandingPageBuilderSectionValidationTest.php` | Baru | 326 | 27 tes untuk validasi server-side |
| `tests/Feature/LandingPageBuilderTest.php` | Diubah | 142 → 149 | Asersi label "Duplicate" → "Gandakan" (lokalisasi) |

---

## 5. HASIL PENGUJIAN

### 5.1 Statis: Sintaks JavaScript

```
node --check (1446 baris): LOLOS
```

### 5.2 Eksekusi Logika: DOM Shim

**Metode:** DOM tiruan (bukan browser) menjalankan blok `<script>` editor dan mengerjakannya melalui 17 skenario. Ini menguji logika event, state, undo/redo, validasi klien, dan payload yang dikirim ke server.

| Skenario | Hasil |
|----------|-------|
| S1 Draft termuat | ✅ |
| S2 Tambah section | ✅ |
| S3 Ubah teks | ✅ |
| S4 layoutColumns integer | ✅ |
| S5 Style (warna/background/radius) | ✅ |
| S6 Padding/margin/font | ✅ |
| S7 Clamp nilai di luar rentang | ✅ |
| S8 Hapus dengan konfirmasi (batal + setuju) | ✅ |
| S9 Undo | ✅ |
| S10 Redo | ✅ |
| S11 Gandakan section | ✅ |
| S12 Ubah urutan | ✅ |
| S13 Sembunyikan section | ✅ |
| S14 Repeater item (tambah + isi) | ✅ |
| S15 Preview responsif (desktop/tablet/mobile) | ✅ |
| S16 XSS: payload tampil sebagai teks | ✅ |
| S17 Undo coalescing | ✅ |

**Total: 17/17 lulus.**

### 5.3 PHPUnit: Validasi Server-Side

**File:** `tests/Feature/LandingPageBuilderSectionValidationTest.php`
**Target:** `percetakan_erp_testing`

| Kelompok | Tes | Lulus |
|-----------|-----|-------|
| Padding/margin/font (rentang, tipe, clamp) | 7 | 7 |
| Gambar (internal, URL, scheme berbahaya, traversal) | 7 | 7 |
| Repeater items (valid, limit, field tak dikenal, panjang) | 6 | 6 |
| Regresi (tipe, title, kolom, ID, jumlah maks) | 6 | 6 |
| Otorisasi non-admin | 1 | 1 |
| **Total** | **27** | **27** |

### 5.4 Regression Suite Penuh

```
Tests: 1112, Assertions: 5043
Failures: 0
Errors: 0
Skipped: 1 (RawSqlAuditTest, pre-existing)
```

Baseline sebelumnya: 1085 tes / 5006 assertions / 0 kegagalan.
Naik karena: +27 tes baru (SectionValidationTest).

---

## 6. HASIL PEMERIKSAAN KEAMANAN ADMINISTRATOR

### 6.1 Otorisasi Server-Side

| Lapisan | Lokasi | Status |
|---------|--------|--------|
| Route group | `routes/web.php` baris 718: `Route::middleware('hanya.admin')` | ✅ |
| Middleware | `HanyaAdministrator.php`: `str_starts_with($roleName, 'Administrator')` | ✅ |
| Middleware alias | `bootstrap/app.php` baris 27: `'hanya.admin' => HanyaAdministrator::class` | ✅ |
| Controller defense-in-depth | `LandingPageBuilderController.php` baris 488: `assertAdministrator()` | ✅ |
| Guest | Redirect ke `route('login')` | ✅ |
| Non-admin (role lain) | `abort(403)` | ✅ (teruji PHPUnit) |

**Semua 14 endpoint builder** berada di dalam group `hanya.admin`. Tidak ada endpoint yang terlewat.

### 6.2 XSS

**Renderer publik (`section-render.blade.php`):** Aman. Semua output menggunakan `{{ }}` (Blade auto-escape). URL di-validasi dengan allowlist `http/https` dan rejection `javascript/data/vscript/file/about:`. Gambar hanya dari `landing-media/` atau URL http(s). Tidak ada `{!! !!}`.

**Canvas editor:** Diperbaiki. Versi lama menyisipkan `s.title`, `s.subtitle`, `s.desc`, `buttonLabel`, `buttonUrl`, `s.type`, `rev.*`, dan `message` ke dalam `innerHTML` tanpa escaping. Versi baru membangun DOM dengan `createElement()` dan `textContent`, sehingga payload XSS tampil sebagai teks biasa, bukan elemen HTML.

**Bukti (skenario S16):** Payload `<img src=x onerror=...>` diinputkan ke judul → tersimpan sebagai teks, nol elemen `<img>` tersuntik.

### 6.3 CSRF

Semua permintaan `fetch()` menyertakan header `X-CSRF-TOKEN`. Middleware `SubmitSekaliJalan` mencegah double-submit.

### 6.4 Validasi URL

- `buttonUrl`: allowlist `http/https` atau path internal (diawali `/`).
- `image`: allowlist `landing-media/` (path traversal `..` ditolak) atau URL `http/https`.
- Klien: validasi ringan pada `blur`.
- Server: validasi penuh di `validateContentJson()` + `validateImageReference()`.

### 6.5 Validasi Properti Section

| Field | Aturan Server |
|-------|---------------|
| `type` | Allowlist 11 tipe |
| `title` | Wajib, maks 200 |
| `subtitle` | Maks 300 |
| `desc` | Maks 1000 |
| `buttonLabel` | Maks 50 |
| `buttonUrl` | http/https atau path `/` |
| `image` | `landing-media/` atau http/s |
| `alt_text` | Maks 255 |
| `layoutWidth` | Allowlist 3 nilai |
| `layoutAlign` | Allowlist 3 nilai |
| `layoutColumns` | Integer 1–4 |
| `layoutSpacing` | Allowlist 3 nilai |
| `paddingTop/Bottom` | Integer 0–200 |
| `marginTop/Bottom` | Integer -200–200 |
| `fontSize` | Integer 12–96 |
| `motion` | Allowlist 4 nilai |
| `styleBg` | Allowlist 3 nilai |
| `styleColor` | Allowlist 4 nilai |
| `styleRadius` | Allowlist 4 nilai |
| `visible` | Boolean |
| `items` | Array, maks 50, field allowlist per tipe, panjang per field |
| Jumlah section | Maks 100 |

### 6.6 Pembatasan Upload

- MIME: `image/jpeg`, `image/png`, `image/gif`, `image/webp` (dicek via `finfo`)
- Magic bytes: JPEG, PNG, GIF, WebP
- Dimensi: maks 4096×4096
- Ukuran: maks 10 MB
- Nama file: di-generate server (`lm_{time}_{random}.{ext}`)

### 6.7 Larangan JavaScript Bebas

Renderer publik tidak pernah meng-output `{!! !!}` atau HTML mentah dari konten user. Semua konten melewati `{{ }}` (auto-escape).

---

## 7. HASIL RESPONSIVE PREVIEW

| Perangkat | Lebar Canvas | Status |
|-----------|-------------|--------|
| Desktop | 100% | ✅ |
| Tablet | 768px | ✅ |
| Mobile | 375px | ✅ |

Tombol preview mengubah `canvas.style.maxWidth` dan `margin: 0 auto`. Label ikut berubah. Teruji via DOM shim (skenario S15).

---

## 8. STATUS PENYIMPANAN DRAFT

| Aspek | Status |
|-------|--------|
| Load draft saat halaman dibuka | ✅ |
| Autosave (debounce 2 detik) | ✅ |
| Save manual (tombol / Ctrl+S) | ✅ |
| Indikator status (tersimpan/menyimpan/belum/error) | ✅ |
| Konflik versi (409) dengan dialog | ✅ |
| Retry otomatis (maks 3) | ✅ |
| Warning `beforeunload` | ✅ |
| Normalisasi section lama | ✅ |

---

## 9. KENDALA INTEGRASI

### 9.1 Browser E2E — BELUM DIVERIFIKASI

Tidak ada Playwright/Cypress/Dusk/Selenium. Kredensial admin tidak tersedia. Verifikasi dilakukan dengan DOM shim (logika) dan PHPUnit (server-side). Rendering visual dan layout sebenarnya tidak diuji.

### 9.2 Editor dan Preview Renderer Terpisah

Canvas editor memakai representasi sendiri (kartu dengan tombol aksi). Preview renderer memakai `section-render.blade.php` yang sama dengan halaman publik. Keduanya mengonsumsi `content_json` yang sama, tetapi **tidak** rendering secara langsung di dalam editor. Admin harus membuka URL preview secara terpisah.

### 9.3 Middleware vs Controller: Konsistensi Pemeriksaan Role

- `HanyaAdministrator` memakai `str_starts_with($roleName, 'Administrator')` — lebih longgar.
- `assertAdministrator()` memakai `$roleName !== 'Administrator'` — lebih ketat.

Karena keduanya diterapkan (middleware lalu controller), efektifnya adalah yang lebih ketat (exact match). Ini bukan celah keamanan, tetapi konsistensi kode bisa ditingkatkan.

---

## 10. REKOMENDASI PENYELESAIAN AKHIR

### Prioritas Tinggi

1. **Browser E2E nyata** — perlu kredensial admin dan tool automation. Uji: tambah section, edit teks, upload gambar, publish, preview responsif.
2. **Tombol Preview di dalam editor** — membuka `/setting/landing-page-builder/preview/draft` di tab baru setelah autosave.

### Prioritas Sedang

3. **Konsistensi pemeriksaan role** — samakan `str_starts_with` vs `!==`.
4. **Unit test untuk `normalizeSection()`** — cakupan edge case konten lama.

### Prioritas Rendah

5. **Ekspor/Impor JSON** — backup konten landing page.
6. **Undo lintas sesi** — history tersimpan di `localStorage`.

---

## STATUS AKHIR

# `LANDING PAGE BUILDER — EDITOR VISUAL SIAP DIGUNAKAN ADMINISTRATOR`

**Dasar penetapan status ini:**

| Kriteria | Bukti |
|----------|------|
| JavaScript valid | `node --check` 1446 baris: LOLOS |
| 15 fitur terverifikasi | DOM shim 17 skenario: 17/17 lulus |
| Validasi server-side | PHPUnit 27 tes: 27/27 lulus |
| Otorisasi admin | Middleware + controller + 14 endpoint terproteksi |
| XSS aman | Renderer publik escape; canvas editor pakai `textContent` |
| Regression | 1112 tes / 5043 assertions / 0 kegagal / 0 error |

**Tidak diklaim:** Browser E2E, rendering visual, layout CSS, integrasi preview langsung di editor. Ini dicatat sebagai BELUM DIVERIFIKASI di Bagian 9.
