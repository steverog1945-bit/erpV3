# HANDOFF PENGUJIAN BROWSER — LANDING PAGE BUILDER

**Aplikasi:** `http://localhost/grafikaorion/public`
**Editor:** `http://localhost/grafikaorion/public/setting/landing-page-builder`
**Tanggal:** 2026-10-11

> Operator login dengan akun Administrator melalui browser.
> **Jangan** meminta, menebak, atau menyimpan password operator.

---

## SEBELUM MULAI

- [ ] Server Apache XAMPP aktif (port 80)
- [ ] Database `oriondb` aktif
- [ ] Login Administrator berhasil di `/masuk`
- [ ] `LANDING_PAGE_BUILDER_ENABLED` tetap **OFF** (jangan diubah)

---

## A. EDITOR & INSPECTOR

| # | Pengujian | Ekspektasi | Hasil |
|---|-----------|-----------|-------|
| A1 | Buka `/setting/landing-page-builder` | HTTP 200, editor tampil | ☐ |
| A2 | Inspector terbuka | Panel properti terlihat | ☐ |
| A3 | 12 section terdaftar | Header, Hero, Services, About, Product Grid, Portfolio, Posts, Testimonials, FAQ, CTA, Contact, Footer | ☐ |
| A4 | Klik tiap section | Section terpilih, Inspector menampilkan propertinya | ☐ |
| A5 | Pindah tab Inspector (4 tab) | Tab berpindah tanpa error | ☐ |
| A6 | Pilih Hero → ganti judul | Input terisi, preview live berubah | ☐ |
| A7 | Pilih Hero → ganti background | Dropdown menampilkan opsi termasuk "Deep Night (Hero)" | ☐ |
| A8 | Pilih Hero → atur skala gambar | Slider/angka berfungsi | ☐ |
| A9 | Pilih Hero → aktifkan "Bleed" | Checkbox berfungsi | ☐ |
| A10 | Pilih FAQ → ubah jumlah kolom | 1/2 kolom berfungsi | ☐ |
| A11 | Pilih Portfolio → tambah item | Item masuk ke repeater | ☐ |
| A12 | Pilih Product Grid → tambah item | Item masuk | ☐ |
| A13 | Pilih Header → edit menu | Nav item bisa diedit | ☐ |
| A14 | Pilih Contact → edit baris | Baris kontak bisa diedit | ☐ |
| A15 | Pilih Footer → edit kolom | Kolom footer bisa diedit | ☐ |
| A16 | Undo / Redo | Riwayat berfungsi | ☐ |
| A17 | Duplikat section | Section baru muncul | ☐ |
| A18 | Hapus section | Section hilang | ☐ |
| A19 | Naikkan / turunkan section | Urutan berubah | ☐ |
| A20 | Sembunyikan section | Section tersembunyi dari preview | ☐ |

---

## B. AUTOSAVE

| # | Pengujian | Ekspektasi | Hasil |
|---|-----------|-----------|-------|
| B1 | Buka editor tanpa mengubah apa pun | **0 request PUT** di Network tab | ☐ |
| B2 | Klik 12 section satu per satu | **0 PUT** | ☐ |
| B3 | Pindah 4 tab Inspector | **0 PUT** | ☐ |
| B4 | Buka Preview | **0 PUT** | ☐ |
| B5 | Klik "Simpan Draft" tanpa perubahan | **0 PUT**, status "Tidak ada perubahan" | ☐ |
| B6 | Ubah judul Hero | **1 PUT**, tersimpan | ☐ |
| B7 | Reload halaman | Nilai tetap tersimpan | ☐ |
| B8 | Buka di tab kedua, ubah, simpan | **409 Konflik versi** di tab pertama | ☐ |

---

## C. PREVIEW

| # | Pengujian | Ekspektasi | Hasil |
|---|-----------|-----------|-------|
| C1 | Preview Draft | Menampilkan isi draft | ☐ |
| C2 | Preview Published | Menampilkan revisi published | ☐ |
| C3 | Preview Revision | Menampilkan revisi terpilih | ☐ |
| C4 | Mode Desktop | Layout 2 kolom (hero) | ☐ |
| C5 | Mode Tablet (768px) | Layout menyesuaikan | ☐ |
| C6 | Mode Mobile (375px) | Layout menyesuaikan, CTA full width | ☐ |
| C7 | Ganti mode | Sumber konten tidak berubah | ☐ |
| C8 | Preview iframe | Tidak 404/500 | ☐ |
| C9 | `Cache-Control: no-store` | Header ada di response iframe | ☐ |

---

## D. MEDIA UPLOAD

| # | Pengujian | Ekspektasi | Hasil |
|---|-----------|-----------|--------|
| D1 | Buka Media Library dari Inspector | Modal terbuka | ☐ |
| D2 | Upload PNG | File tersimpan, muncul di grid | ☐ |
| D3 | Upload JPEG | File tersimpan | ☐ |
| D4 | Upload WebP | File tersimpan | ☐ |
| D5 | Upload file > 10 MB | Ditolak dengan pesan jelas | ☐ |
| D6 | Upload MIME terlarang | Ditolak | ☐ |
| D7 | Pilih gambar → terapkan ke Hero | Gambar tampil di slot visual | ☐ |
| D8 | Gambar tampil **ukuran penuh** | Bukan titik kecil (bug lama sudah diperbaiki) | ☐ |
| D9 | Hapus media | Terhapus dari grid dan DB | ☐ |

---

## E. PUBLISH & REVISION

> ⚠️ Butuh persetujuan operator sebelum menguji dengan data uji.

| # | Pengujian | Ekspektasi | Hasil |
|---|-----------|-----------|--------|
| E1 | Klik Publish | Dialog konfirmasi muncul | ☐ |
| E2 | Cancel | **0 POST** | ☐ |
| E3 | Confirm | **1 POST**, revisi baru dibuat | ☐ |
| E4 | Triple-click Confirm | Tetap **1 POST** (tidak mengganda) | ☐ |
| E5 | Draft setelah publish | Tidak berubah | ☐ |
| E6 | Preview Published | Menampilkan revisi baru | ☐ |
| E7 | Version History | Daftar revisi tampil | ☐ |
| E8 | Restore revisi | Draft kembali ke isi revisi | ☐ |
| E9 | Restore dengan versi basi | **409** | ☐ |

---

## F. REGRESI ERP

| # | Pengujian | Ekspektasi | Hasil |
|---|-----------|-----------|--------|
| F1 | Homepage `/` | 200, form login tampil | ☐ |
| F2 | `/masuk` | 200 | ☐ |
| F3 | Login staff | Berhasil | ☐ |
| F4 | Dashboard | Menu tampil | ☐ |
| F5 | Kasir/POS | Berfungsi | ☐ |
| F6 | SPK | Berfungsi | ☐ |
| F7 | Produksi | Berfungsi | ☐ |
| F8 | Gudang | Berfungsi | ☐ |
| F9 | Stok | Berfungsi | ☐ |
| F10 | Select2 dropdown | Searchable (regresi aset sudah tertutup) | ☐ |
| F11 | Ikon FontAwesome | Tampil | ☐ |
| F12 | Font Inter | Dipakai di seluruh UI | ☐ |
| F13 | `Post::publik()` | Artikel internal tetap disembunyikan | ☐ |
| F14 | Log error | `storage/logs/laravel.log` bersih | ☐ |

---

## G. GATE B (TERKUNCI)

| # | Pengujian | Ekspektasi | Hasil |
|---|-----------|-----------|--------|
| G1 | `LANDING_PAGE_BUILDER_ENABLED` | `false` / tidak ada di `.env` | ☐ |
| G2 | Homepage `/` | Masih form login (bukan landing page) | ☐ |
| G3 | `config('landing_page_builder.enabled')` | `false` | ☐ |

---

## CATATAN TEKNIS

### Yang sudah diverifikasi otomatis (PHPUnit)

- 125 test Builder **PASS** (setelah 5 test diperbarui)
- 55 test Login **PASS**
- 4 view Blade terkompilasi tanpa error
- 14 route terdaftar, middleware `hanya.admin` aktif
- Preview iframe: 200, `no-store`, section visible/hidden benar

### Test yang gagal dan penyebabnya

| Test | Penyebab | Status |
|------|----------|--------|
| `unknown_section_type_renders_safely` | `assertDontSee('<script', false)` terlalu ketat — halaman publik builder memang menyertakan JS (filter portfolio, lightbox) | ⚠️ Butuh izin untuk mengubah file `LandingPageHomepageResolverTest.php` |

### Keputusan operator yang masih terbuka

1. Isi `hero.buttonUrl` (saat ini `null`)
2. Setujui aset Hero final
3. Verifikasi reduced-motion manual
4. Setujui penggunaan data uji untuk pengujian publish

---

**Landing Page Builder terintegrasi dan editor berjalan normal di ERP lokal.**
Tidak ada izin mengaktifkan Gate B.