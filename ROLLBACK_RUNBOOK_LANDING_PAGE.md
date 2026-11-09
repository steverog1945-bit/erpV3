# ROLLBACK RUNBOOK — LANDING PAGE BUILDER (homepage publik)

Dokumen ini adalah prosedur rollback yang sudah diverifikasi langkah-langkahnya
selama Checkpoint 4D.1/4D.2. Tidak ada satu pun langkah yang menghapus data.

---

## 0. Kapan perlu rollback

Segera rollback bila salah satu kondisi ini terjadi:

| Trigger | Ambang |
|---------|--------|
| HTTP 5xx pada homepage | ≥ 1 kejadian |
| Halaman homepage kosong / error |_langsung_ |
| Konten published terlihat salah | Langsung |
| Dialog/form login tidak muncul di homepage | Langsung |
| Banner `PREVIEW MODE` bocor ke halaman publik | Langsung ( kebocoran data internal) |
| Timeout homepage naik tajam | > 3 detik rata-rata |
| Gambar hero rusak (broken) | ≥ 1 |

**Kapan TIDAK perlu rollback:**
-css/ layout kurang indah → perbaiki lalu publish ulang.
- One-off request lambat → observe dulu.

---

## 1. Rollback (kembali ke homepage existing)

Estimasi: **< 30 detik**.

### Langkah

```bash
# 1. Pastikan tidak ada proses PHP lain yang sedang jalan
#    (mencegah dua proses artisan bentrok pada cache)
#    Windows:
tasklist | findstr php

# 2. Matikan flag
#    Buka .env, ubah / hapus baris:
#      LANDING_PAGE_BUILDER_ENABLED=true   ->  hapus baris tsb
#
#    (kalau memang tidak pernah diaktifkan, tidak ada yang perlu diubah)

# 3. Bersihkan cache konfigurasi
php artisan config:clear

# 4. Kalau produksi memakai config cache
php artisan config:cache

# 5. (Opsional) bersihkan cache aplikasi agar render ulang memaksa
php artisan cache:clear
```

### Verifikasi rollback

```bash
php scripts/check_public_homepage.php <URL_HOMEPAGE> existing
```

Harus keluar `HASIL: SEHAT`, HTTP 200, dan memuat dialog masuk.

Verifikasi manual di browser: buka homepage → harus muncul katalog layanan +
tulisan + tombol "Masuk".

---

## 2. Yang TIDAK terjadi saat rollback

- Tidak ada tabel yang dihapus.
- Tidak ada draft / revision yang hilang.
- Tidak ada file media yang terhapus.
- Tidak perlu rollback migration.
- Tidak perlu restore backup.
- `routes/web.php` tidak berubah.
- `login.blade.php` tidak berubah.

Rollback bersifat **fully reversible** — menyalakan flag lagi mengembalikan
tampilan builder persis seperti sebelumnya.

---

## 3. Rollback alternatif (tanpa menyentuh .env)

Bila edit `.env` ingin dihindari, tersedia jalur **data** yang sengaja
dirancang sebagai fallback:

```sql
-- JALANKAN HANYA KALAU FLAG SUDAH ON dan homepage rusak.
-- Set pointer published revision jadi NULL: resolver otomatis fallback
-- ke homepage existing tanpa perlu restart.
UPDATE landing_pages
   SET published_revision_id = NULL,
       updated_at = NOW()
 WHERE slug = 'home';

-- ATAU kembalikan status halaman jadi draft:
UPDATE landing_pages
   SET status = 'draft',
       updated_at = NOW()
 WHERE slug = 'home';
```

Setelah itu: `php artisan cache:clear`.

**Catatan:** jalur ini menyentuh database produksi sehingga harus memakai kredensial
yang benar dan dicatat di audit log. Jalur `.env` (bagian 1) lebih aman dan lebih
disarankan.

---

## 4.、明 Verifikasi pascab-rollback

- [ ] `php artisan config:clear` selesai tanpa error
- [ ] Health check `HASIL: SEHAT`
- [ ] Homepage menampilkan tombol "Masuk"
- [ ] Staff bisa login seperti biasa
- [ ] Halaman kasir / SPK / produksi normal
- [ ] `audit_logs` mencatat publish yang memicu masalah (bila ada)
- [ ] Inciden dicatat: waktu, gejala, penyebab, tindakan

---

## 5. Storytelling rollback (untuk insert)

Setelah rollback berhasil, lacak penyebabnya:

1. Cek log aplikasi untuk baris berisi `Landing page resolver`:
   - `unsupported schema version` → versi schema publish tidak cocok allowlist.
   - `invalid content structure` → struktur `content_json` rusak.
   - `read failure, fallback to existing` → database tidak terbaca.
2. Cek `audit_logs` untuk perubahan landing page terakhir.
3. Cek revision terkait di `landing_page_revisions`.
4. Perbaiki di builder, publish ulang, lalu nyalakan flag dengan approval.

---

## 6. PIC / Penanggung jawab

| Peran | Tanggung jawab |
|-------|-----------------|
| Administrator sistem | Eksekusi rollback, verifikasi, komunikasi |
| Stakeholder pemilik sistem | Approval penyalaan ulang flag |
| On-call (bila ada) | Respons insiden di luar jam kerja |

**Nama PIC belum ditetapkan** → ini salah satu gate yang belum terpenuhi.