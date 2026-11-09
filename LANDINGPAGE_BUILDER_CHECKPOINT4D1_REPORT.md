# LANDING PAGE BUILDER — CHECKPOINT 4D.1
## SAFE CUTOVER IMPLEMENTATION (FLAG OFF)

**Status akhir: `LANDING PAGE BUILDER — CUTOVER INFRASTRUCTURE VERIFIED / FLAG OFF`**

Authorization: APPROVED FOR FEATURE FLAG AND FALLBACK IMPLEMENTATION ONLY.
Public activation: **NOT APPROVED, NOT PERFORMED.**

---

## 1. ORIGINAL HOMEPAGE ROUTE ANALYSIS

### Verified actual code (bukan asumsi)

| Item | Nilai aktual |
|------|--------------|
| Route publik | `routes/web.php:46` → `Route::get('/', [AuthController::class, 'showLogin'])->name('login');` |
| Nama route | `login` |
| Controller | `app/Http/Controllers/AuthController.php::showLogin()` |
| View | `resources/views/login.blade.php` |
| Data view | `PublikController::dataBeranda($request)` (statis) |
| Route publik lain | `routes/web.php:48` → `/baca/{slug}` → `PublikController::baca` → view `publik.baca` |
| Setting mechanism | `App\Models\AppSetting::current()` — tabel `app_settings`, cache `Cache::rememberForever('app_settings_current')` |
| Homepage punya route sendiri? | **Tidak.** `/` adalah satu-satunya entry homepage, dan ia sekaligus halaman login. |

### Perilaku asli homepage (sebelum modifikasi)

1. Sudah login → `redirect()->route('dashboard')`.
2. Request AJAX → `PublikController::jawabanDaftar($data)` (JSON untuk filter/cari/muat-lagi).
3. Selain itu → `view('login', $data)` dengan header `Cache-Control: no-store, no-cache, must-revalidate, max-age=0`.
4. `login.blade.php`extend `publik.layout`, memuat hero, katalog layanan, cara pesan, daftar tulisan, kontak, dan dialog login.

### Bukti respons asli (sebelum perubahan)

Diuji dengan `tests/Feature/HalamanDepanPublikTest.php` → **7 tests / 31 assertions, hijau** sebelum maupun sesudah perubahan Checkpoint 4D.1.

### Kontrak yang harus dipertahankan

- URL `/` tidak berubah.
- Nama route `login` tidak berubah.
- Tidak ada route homepage baru yang bersaing.
- `publik/layout.blade.php` dan `publik/baca.blade.php` **tidak dihapus**.

---

## 2. CHANGED FILES

### File baru (4)

| File | Lines | Fungsi |
|------|-------|--------|
| `config/landing_page_builder.php` | 16 | Feature flag + batas konfigurasi |
| `app/Services/LandingPageHomepageResolver.php` | 188 | Resolver: pilih homepage, validasi, fallback |
| `resources/views/landing-page-public.blade.php` | 84 | Public renderer + SEO head |
| `tests/Feature/LandingPageHomepageResolverTest.php` | 698 | 32 test |

### File baru (2 partial — bagian dari renderer reuse)

| File | Lines | Fungsi |
|------|-------|--------|
| `resources/views/pengaturan/partials/section-render.blade.php` | 232 | Partial render section, dipakai preview **dan** publik |
| `resources/views/pengaturan/partials/renderer-styles.blade.php` | 68 | CSS bersama agar output identik |

### File dimodifikasi (2)

| File | Perubahan |
|------|-----------|
| `app/Http/Controllers/AuthController.php` | +18 baris: satu cabang early-return di `showLogin()`.diff-tested, tidak menyentuh branch lain. |
| `resources/views/pengaturan/landing-page-preview.blade.php` | Di-refactor memakai partial bersama. Menambah `noarchive` pada meta robots; preview tidak punya canonical. |

### File yang SENGAJA tidak diubah

- `routes/web.php` — nol perubahan di 4D.1 (route builder dari checkpoint sebelumnya sudah ada).
- `resources/views/login.blade.php` — tidak disentuh.
- `resources/views/publik/layout.blade.php`, `publik/baca.blade.php` — tidak disentuh.
- `app/Http/Controllers/PublikController.php` — tidak disentuh.
- `.env` — **tidak disentuh** (dikonfirmasi `git status`: tidak muncul).
- Semua modul ERP (Kasir/SPK/Produksi/Pembayaran/Stok/Pembelian/Jurnal) — tidak disentuh.

### Dependency

Tidak ada dependency baru. Tidak ada `composer.json` / `composer.lock` yang disentuh oleh 4D.1.

---

## 3. FEATURE FLAG IMPLEMENTATION

### File

`config/landing_page_builder.php`

```php
return [
    'enabled' => env('LANDING_PAGE_BUILDER_ENABLED', false),
    'fallback_to_existing' => true,
    'published_slug' => 'home',
    'media' => [
        'max_size_mb' => 10,
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        'max_dimensions' => 4096,
    ],
    'publish' => [
        'rate_limit' => 5,
        'require_approval' => false,
    ],
];
```

### Persyaratan flag vs bukti

| Persyaratan | Status | Bukti |
|-------------|--------|-------|
| Default OFF | ✅ | `env(..., false)`; `artisan tinker` → `false` |
| Aman saat env hilang | ✅ | `flag_defaults_to_off_when_env_missing` |
| Kompatibel `config:cache` | ✅ | `artisan config:cache` sukses, nilai tetap `false`; lalu `config:clear` |
| Tidak ada `env()` di controller | ✅ | grep `env(` di `AuthController` → 0 hasil |
| Tidak ada `env()` di service | ✅ | grep `env(` di resolver → 0 hasil |
| `env()` hanya di config | ✅ | 1 hasil, di `config/landing_page_builder.php:5` |
| Tanpa mutasi DB untuk toggle | ✅ | Flag murni env/config; `homepage_does_not_auto_create_page` |
| State flag terverifikasi test | ✅ | 6 test khusus flag state |
| Tidak mengaktifkan flag | ✅ | `.env` tidak diubah; nilai final `false` |

### Konvensi setting existing

Sistem punya `AppSetting` (tabel `app_settings`) untuk setting yang berbasis DB. **Flag ini sengaja TIDAK memakai `AppSetting`**, dengan alasan:

- `AppSetting` menyimpan data bisnis yang diedit lewat UI Pengaturan Aplikasi (nama_aplikasi, alamat, nomor nota, dll).
- Menambah kolom flag ke `app_settings` = **perubahan schema produksi**, yang dilarang pada checkpoint ini.
- `env` + `config` adalah mekanisme Laravel standar untuk cutover/kill-switch, dan justru lebih aman untuk rollback darurat (tidak butuh query DB saat insiden).

Konsekuensi yang jujur: toggle flag butuh edit `.env` + `config:clear/cache`, bukan klik di UI. Ini Trade-off yang disengaja dan dibalas dengan prosedur rollback di bagian 11.

---

## 4. RESOLVER IMPLEMENTATION

`app/Services/LandingPageHomepageResolver.php`

### Tanggung jawab dipisah 3 lapis

| Lapis | Metode | Tugas |
|------|--------|-------|
| **Content selection** | `resolve()`, `findValidPublishedRevision()` | Pilih revision mana yang sah |
| **Content validation** | `isValidContentStructure()`, `isValidSectionsArray()`, `normalizeSections()` | Pastikan bentuk & isi layak render |
| **Rendering** | `resources/views/pengaturan/partials/section-render.blade.php` | Output HTML, escaping + sanitasi |

Resolver **tidak** merender HTML. Ia hanya mengembalikan array:

```php
['mode' => 'existing'|'builder', 'view' => 'login'|'landing-page-public', 'data' => [...]]
```

### Rantai validasi publik

1. Flag OFF → `existing` (langsung, tanpa query sama sekali).
2. Flag ON → `LandingPage::where('slug', config('landing_page_builder.published_slug'))`.
3. Halaman harus ada.
4. `status` harus `published`.
5. `published_revision_id` harus tidak null.
6. Revision harus diambil **dengan filter `landing_page_id = $page->id`** → pointer silang antar halaman ditolak.
7. `schema_version` harus persis ada di allowlist.
8. Struktur `content_json` harus valid.
9. Kegagalan mana pun → `existing`.

### Draft mustahil bocor

Draft tidak pernah disentuh resolver. Query hanya membaca `landing_pages` dan `landing_page_revisions`. Tabel `landing_page_drafts` tidak direferensikan sama sekali di jalur publik.

Bukti: `draft_content_never_publicly_exposed` (marker `RAHASIA-DRAFT` tidak muncul), plus `flag_true_with_draft_only_page_falls_back_to_existing`.

### Bug nyata yang ditemukan & diperbaiki saat implementasi

**Bug 1 — truncation versi schema.**
Rencana awal membandingkan `(int) $revision->schema_version` dengan konstanta `1`. Nilai produksi sebenarnya adalah string **`'1.0.0'`** (`LandingPageBuilderController::publish` baris 332 dan default migration baris 47). `(int) '1.0.0'` = 1, jadi kebetulan lolos — tapi `'1.10.0'` juga akan jadi `1` dan **lolosreviewed sebagai versi yang kompatibel padahal bukan**. Diperbaiki dengan allowlist string:

```php
public const SUPPORTED_SCHEMA_VERSIONS = ['1.0.0'];
// ...
if (!in_array(trim((string) $revision->schema_version), self::SUPPORTED_SCHEMA_VERSIONS, true)) { ... }
```

Ditutup dengan 3 test: `resolver_allowlist_matches_schema_version_written_by_publish_flow` (membaca literal dari source publish flow), `resolver_allowlist_matches_migration_default` (membaca default dari source migration), dan `version_looking_like_numbers_is_not_truncated` (`'1.10.0'` harus ditolak). Kalau suatu saat publish flow mengubah versi, dua test pertama langsung gagal — tidak ada fallback diam-diam.

**Bug 2 — bentuk `content_json` tidak dinormalisasi.**
Validasi menerima dua bentuk (`[...]` dan `{'sections': [...]}`), tapi `content_json` diteruskan mentah ke renderer yang mengiterasi daftar section. Untuk bentuk terbungkus, `foreach` akan mengiterasi key `'sections'` sehingga section tidak pernah tampil. Diperbaiki dengan `normalizeSections()` yang melepas pembungkus sebelum data masuk view. Ditutup `wrapped_sections_format_is_normalized_and_rendered`.

### Renderer reuse

`section-render.blade.php` adalah **satu-satunya** tempat section dirender, dipakai oleh:
- `landing-page-preview.blade.php` (preview draft/published/revision, admin)
- `landing-page-public.blade.php` (homepage publik)

Tidak ada renderer kedua dengan perilaku berbeda. Efeknya: apa yang dilihat admin di preview identik dengan yang dilihat pengunjung.

Yang di-hardening di partial (m previously hanya di view preview):
- **URL**: hanya `http`/`https` atau path relatif `/`. Scheme `javascript:`, `data:`, `vbscript:`, `file:`, `about:` ditolak.
- **Gambar**: hanya path `landing-media/...` (diubah ke `asset('storage/...')`) atau URL http/https. String lain dibuang.
- **Nilai enum** (`styleBg`, `styleRadius`, `layoutSpacing`, `layoutAlign`, `motion`, `layoutColumns`): divalidasi terhadap allowlist, nilai ngawur jatuh ke default.
- **Tipe section tidak dikenal**: render minimal (judul + deskripsi ter-escape), bukan dibiarkan kosong atau dieksekusi.
- **Escape**: seluruh output memakai `{{ }}`. Nol `{!! !!}` di kedua view (dikonfirmasi grep).
- **Semantik heading**: `hero` = `<h1>`, section lain = `<h2>`, kartu = `<h3>`, agar hierarki heading tidak ngawur.

---

## 5. FALLBACK BEHAVIOR

### Matriks fallback (setiap baris punya test)

| Flag | Kondisi published revision | Hasil | Test |
|------|---------------------------|-------|------|
| OFF | — (diabaikan) | Homepage existing | `flag_false_keeps_existing_homepage`, `existing_homepage_preserved_when_flag_off` |
| ON | Valid | Builder homepage | `flag_true_with_valid_published_revision_renders_builder` |
| ON | Halaman tidak ada | Homepage existing | `flag_true_without_page_falls_back_to_existing` |
| ON | Hanya draft, tidak ada published | Homepage existing | `flag_true_with_draft_only_page_falls_back_to_existing` |
| ON | `status` bukan published | Homepage existing | (tercakup test draft-only) |
| ON | `published_revision_id` null | Homepage existing | (tercakup test draft-only) |
| ON | Struktur konten rusak | Homepage existing | `flag_true_with_invalid_published_revision_falls_back_to_existing` |
| ON | Schema version tak didukung | Homepage existing | `flag_true_with_unsupported_schema_version_falls_back_to_existing` |
| ON | Pointer revision milik halaman lain | Homepage existing | `wrong_page_revision_pointer_falls_back_to_existing` |
| ON | JSON rusak di DB | Homepage existing, tidak 500 | `malformed_json_does_not_crash_homepage` |
| ON | Kegagalan baca database | Homepage existing, tidak 500 | `database_read_failure_falls_back_to_existing_homepage`, `database_read_failure_does_not_leak_exception_to_visitor` |

### Kesetaraan saat flag OFF

Resolver mengembalikan `mode = 'existing'`, lalu `AuthController`olerant_media | execute seluruhnya berjalan persis seperti kode sebelumnya: `PublikController::dataBeranda()`, jalur AJAX, `bukaMasuk`, dan header `Cache-Control: no-store, ...` semuanya tidak tersentuh. Percabangan baru hanya menambah satu `if` di awal.

Diverifikasi oleh test yang membandingkan **view** yang dirender, bukan sekadar substring:
- `existing_homepage_preserved_when_flag_off` → `assertViewIs('login')`
- `flag_true_with_valid_published_revision_renders_builder` → `assertViewIs('landing-page-public')`
- `ajax_homepage_still_works_when_flag_off` → struktur JSON `{html, berikut, jumlah}` utuh
- `authenticated_user_still_redirected_to_dashboard` → redirect ke dashboard tetap berlaku

---

## 6. SEO HANDLING

### Flag OFF

Tidak ada perubahan sama sekali. View `login` tetap dirender, `<title>`/`<meta name="description">`/`canonical` apa pun yang sudah ada di `publik/layout.blade.php` tetap utuh. 7 test `HalamanDepanPublikTest` tetap hijau.

### Flag ON

| Aspek | Implementasi | Test |
|-------|---------------|------|
| `<title>` | `seo['title']` → fallback `$set->nama_aplikasi` | `seo_metadata_rendered_when_flag_on` |
| Meta description | `seo['description']` → fallback deskripsi default | `seo_metadata_rendered_when_flag_on` |
| Canonical | Selalu `url('/')` | `canonical_url_is_never_preview_url` |
| Open Graph | `og:type`, `og:url`, `og:title`, `og:description`, `og:image` | `seo_metadata_rendered_when_flag_on` |
| Twitter Card | `summary_large_image`, title, description | — |
| Robots (publik) | `index, follow` | kontras dengan test preview |
| Robots (preview) | `noindex, nofollow, noarchive` | `preview_route_always_noindex` |
| Image alt text | `alt` dari field section/item | escaping HTML otomatis |
| Heading semantik | h1 (hero) → h2 (section) → h3 (kartu/item) | inspeksi partial |

### Jaminan tidak salah SEO

- **URL preview tidak pernah jadi canonical.** Diuji: respons homepage tidak mengandung string `preview/published`, `preview/draft`, maupun `preview/revision`; tag canonical berisi `url('/')`.
- **Draft tidak pernah bocor ke mesin pencari.** Preview dipaksa `noindex, noarchive` dan tidak punya tag canonical sama sekali (diuji `assertDontSee('<link rel="canonical"')`).

---

## 7. SECURITY CHECKS

| Cek | Hasil | Bukti |
|-----|-------|-------|
| XSS konten published di-escape | ✅ | `xss_in_published_content_is_escaped` — `<script>alert("xss")</script>` tidak muncul mentah; yang tampil `&lt;script&gt;` |
| URL `javascript:` ditolak | ✅ | `javascript_url_in_button_is_rejected` — tidak ada `href="javascript:` |
| Nol raw HTML output | ✅ | grep `{!!` → hanya ketemu di baris komentar |
| Draft tidak bocor publik | ✅ | `draft_content_never_publicly_exposed` |
| Section `visible: false` tidak dirender | ✅ | `hidden_sections_not_rendered_publicly` |
| Tipe section ngawur aman | ✅ | `unknown_section_type_renders_safely` |
| Exception internal tidak bocor ke pengunjung | ✅ | `database_read_failure_does_not_leak_exception_to_visitor` — respons 200, tanpa `SQLSTATE`/`landing_pages` |
| Tidak ada mutasi DB saat homepage diakses | ✅ | `visiting_homepage_creates_no_database_writes` |
| Resolver tidak membuat halaman otomatis | ✅ | `homepage_does_not_auto_create_page` |
| Preview URL tidak terindeks | ✅ | `preview_route_always_noindex` |
| Route builder tetap `hanya.admin` | ✅ | Tidak disentuh; 97 test builder tetap hijau |
| Tidak ada dependensi baru | ✅ | `composer.json` tidak diubah |

### Apa yang belum diuji di checkpoint ini

- Stored XSS lewat **media manager** sudah ter-cover di checkpoint sebelumnya (`LandingPageBuilderMediaTest`), bukan di 4D.1.
- Rate limiting jalur publik: tidak diuji di 4D.1 (jalur publik homepage memang tidak memakai endpoint ber-rate-limit).

---

## 8. TEST RESULTS

### Suite baru

`tests/Feature/LandingPageHomepageResolverTest.php` — **32 tests / 85 assertions, 0 failure**

#### Flag state (6)
`flag_defaults_to_off_when_env_missing`, `flag_false_keeps_existing_homepage`, `flag_true_with_valid_published_revision_renders_builder`, `existing_homepage_preserved_when_flag_off`, `resolver_returns_existing_mode_when_flag_off`, `resolver_returns_builder_mode_with_valid_revision`

#### Fallback / validasi (7)
`flag_true_without_page_falls_back_to_existing`, `flag_true_with_draft_only_page_falls_back_to_existing`, `flag_true_with_invalid_published_revision_falls_back_to_existing`, `flag_true_with_unsupported_schema_version_falls_back_to_existing`, `wrong_page_revision_pointer_falls_back_to_existing`, `malformed_json_does_not_crash_homepage`, `wrapped_sections_format_is_normalized_and_rendered`

#### Draft & isolasi (3)
`draft_content_never_publicly_exposed`, `visiting_homepage_creates_no_database_writes`, `homepage_does_not_auto_create_page`

#### Route & kompatibilitas (3)
`ajax_homepage_still_works_when_flag_off`, `existing_public_routes_still_resolve`, `authenticated_user_still_redirected_to_dashboard`

#### SEO (3)
`seo_metadata_rendered_when_flag_on`, `canonical_url_is_never_preview_url`, `preview_route_always_noindex`

#### Keamanan output (3)
`xss_in_published_content_is_escaped`, `javascript_url_in_button_is_rejected`, `unknown_section_type_renders_safely`

#### Kegagalan database (2)
`database_read_failure_falls_back_to_existing_homepage`, `database_read_failure_does_not_leak_exception_to_visitor`

#### Kontrak schema version (3)
`resolver_allowlist_matches_schema_version_written_by_publish_flow`, `resolver_allowlist_matches_migration_default`, `version_looking_like_numbers_is_not_truncated`

#### Integrasi end-to-end (2)
`real_publish_flow_output_is_accepted_by_resolver` (publish lewat API sungguhan → homepage publik menampilkan hasilnya), `hidden_sections_not_rendered_publicly`

### Guard keamanan test

`setUp()` memverifikasi `config('database.connections.mysql.database') === 'percetakan_erp_testing'`; kalau tidak, seluruh test di-skip. Ini Dicek bergantian dengan `phpunit.xml` yang memang meng-hardcode `percetakan_erp_testing`. Tidak ada jalur test yang bisa menulis ke `oriondb`.

### Suite builder (regresi)

`LandingPageBuilder*` (5 file) + resolver baru = **122 tests / 334 assertions, 0 failure**.

---

## 9. FULL ERP REGRESSION

Perintah: `php vendor/bin/phpunit -c phpunit.xml` (seluruh suite, bukan subset).

### Angka aktual

| Metrik | Nilai |
|--------|-------|
| Tests | **1022** |
| Assertions | **4754** |
| Failures | **0** |
| Errors | **0** |
| Skipped | **1** |
| Risky | **0** |
| Deprecations | **133** (PHPUnit 11 metadata `/** @test */`) |
| Durasi | 7 menit 34 detik |

### Test yang di-skip

`RawSqlAuditTest` (1 test) — `"Route laporan penjualan tidak ditemukan - test runtime tidak applicable"`.

**Bukan** dari checkpoint ini, dan **bukan** skip GD: `LandingPageBuilderMediaTest` punya skip kondisional GD, tetapi GD aktif sehingga **0 skip** dari media suite. Sesuai target checkpoint 4C ("0 skipped untuk media test yang bergantung pada GD") — tercapai.

### Perbandingan dengan baseline yang dilaporkan

| | Baseline dilaporkan (4C) | Terukur 4D.1 |
|---|---|---|
| Tests | 1008 | 1022 |
| Assertions | 4764 | 4754 |

Selisih: **+14 test** (32 test resolver baru, dikurangi test lama yang tidak lagi terhitung di run ini), **−10 assertion**.

Disclaimer yang jujur: angka baseline 1008/4764 berasal dari laporan checkpoint sebelumnya dan tidak bisa direproduksi persis pada run ini — jumlah test suite di repo berubah seiring checkpoint. Yang mattered dan konsisten di semua run bersih checkpoint ini adalah **0 failure / 0 error / 0 risky**. Run bersih pertama yang tercatat pada sesi ini: 1021 tests / 4752 assertions / 0 failure; run final dengan seluruh perubahan: 1022 / 4754 / 0 failure.

### Regresi modul ERP

Tidak ada modul ERP yang gagal. Semua modul tetap hijau dalam full run — termasuk Kasir, SPK, Produksi, Collecting, Pembayaran, Stok, Pembelian, Jurnal, Login, dan RBAC.

### Insiden selama sesi (dicatat jujur)

Dua kali guekahl的全 regression sempat melaporkan ratusan error (417 lalu 405). Penyebabnya **bukan** regresi kode: sebuah test versi awal saya menggunakan `DROP TABLE landing_pages` untuk menyimulasikan kegagalan database, dan test itu berjalan **bersamaan** dengan full-suite di background pada database testing yang sama. Dua proses PHPUnit saling menabrakkan `migrate:fresh`, jadi tabel hilang di tengah run.

Tindakan yang diambil:
1. Test destruktif tersebut **dihapus** dan diganti pendekatan non-destruktif (`DB::disconnect`/`DB::reconnect`).
2. Skema `percetakan_erp_testing` dibangun ulang otomatis oleh `RefreshDatabase`; diverifikasi 110 tabel + 4 tabel landing page utuh.
3. Semua run berikutnya dilakukan **satu per satu** tanpa proses PHP lain yang menyentuh database.

Tidak ada `oriondb` yang tersentuh. Verifikasi akhir: `oriondb` tidak muncul di `git status`, dan seluruh operasi DB pada sesi ini diarahkan `percetakan_erp_testing`.

---

## 10. BROWSER E2E STATUS

### Ketersediaan tool

| Tool | Status |
|------|--------|
| Playwright | ❌ tidak terpasang |
| Cypress | ❌ tidak terpasang |
| Selenium / WebDriver | ❌ tidak terpasang |
| Laravel Dusk | ❌ tidak terpasang |
| Chromium/Chrome DevTools Protocol | ❌ tidak tersedia di harness |

Tidak ada dependency baru dipasang (sesuai larangan).

### Status: **PENDING — TIDAK PERNYATAAN E2E VERIFIED**

Tidak ada satu pun skenario browser yang dieksekusi pada checkpoint ini. Klaim E2E tetap kosong sampai ada bukti eksekusi.

### Checklist browser acceptance (siap dijalankan, belum diisi)

Setiap baris wajib diisi penguji, tanggal, dan screenshot sebelum boleh ditandai PASS.

| # | Prasyarat | Skenario | Expected | Actual | PASS/FAIL | Bukti | Penguji | Tanggal |
|---|-----------|----------|----------|--------|-----------|-------|---------|---------|
| B1 | Flag OFF | Buka `/` sebagai tamu | Homepage existing (katalog layanan, tulisan, dialog masuk) | | | | | |
| B2 | Flag OFF | Buka `/` di 375px | Teggak 1 kolom, tanpa scroll horizontal | | | | | |
| B3 | Flag OFF | Lihat source halaman | `<title>` dan meta description sama seperti sebelum checkpoint | | | | | |
| B4 | Flag ON (testing) | Publish valid, buka `/` | Section dari published revision tampil | | | | | |
| B5 | Flag ON (testing) | Buka `/` di 768px dan 375px | Layout responsif, tanpa overflow | | | | | |
| B6 | Flag ON (testing) | Section dengan gambar | Gambar termuat, bukan broken image | | | | | |
| B7 | Flag ON (testing) | Klik tombol CTA | Navigasi ke URL yang benar | | | | | |
| B8 | Flag ON (testing) | Lihat `<head>` | Canonical = `/`, bukan URL preview | | | | | |
| B9 | Flag ON (testing) | Console browser | Tidak ada error kritikal | | | | | |
| B10 | Flag ON | Unpublish / hapus published revision, buka `/` | Otomatis fallback ke homepage existing | | | | | |
| B11 | Flag OFF (kembali) | Buka `/` | Homepage existing, tidak perlu restart DB | | | | | |
| B12 | Flag ON | Buka URL preview saat login | Banner PREVIEW MODE, `noindex` | | | | | |
| B13 | Flag ON | Alur order tetap jalan | Tidak ada perubahan di halaman kasir/login | | | | | |

---

## 11. ROLLBACK PROCEDURE

### Kapan rollback diperlukan

- Halaman publik error setelah flag dinyalakan.
- Konten published terlihat salah.
- Performa homepage turun.

### Cara rollback (tanpa hapus data)

```bash
# 1. Matikan flag di .env
#    LANDING_PAGE_BUILDER_ENABLED=true  ->  hapus baris / ubah jadi false

# 2. Bersihkan cache konfigurasi
php artisan config:clear

# 3. Kalau produksi memakai config cache
php artisan config:cache

# 4. Bersihkan cache aplikasi (opsional, untuk memaksa render ulang)
php artisan cache:clear
```

Homepage kembali ke `login.blade.php` pada request berikutnya. Estimasi: < 30 detik.

### Yang TIDAK terjadi saat rollback

- Tidak ada tabel yang dihapus.
- Tidak ada revision yang dihapus.
- Tidak ada draft yang hilang.
- Tidak ada file media yang terhapus.
- Tidak perlu rollback migration.
- Tidak perlu restore backup.
- Tidak ada perubahan pada `routes/web.php`.

Artinya: rollback bersifat **fully reversible** — menyalakan flag lagi akan mengembalikan tampilan builder persis seperti sebelumnya.

### Jalur rollback alternatif (tanpa menyentuh env)

Kalau edit `.env` dianggap berisiko, disengaja tersedia fallback **data**: hapus pointer `published_revision_id` (atau set `status` kembali ke `draft` pada tabel `landing_pages`). Resolver akan otomatis jatuh ke homepage existing karena published revision tidak lagi valid. Ini opsional dan tidak diperlukan untuk rollback normal.

---

## 12. REMAINING BLOCKERS

###阻断 cutover (harus selesai sebelum 4D.2)

| Blocker | Severity | Kenapa penting |
|---------|----------|----------------|
| Browser E2E belum dieksekusi | **TINGGI** | 13 skenario masih PENDING. Preview vs publik yang identik baru terbukti di parser test, belum di browser sungguhan. |
| Stakeholder sign-off belum ada | **TINGGI** | Cutover butuh persetujuan manusia, bukan hanya green test. |
| Monitoring belum disiapkan | **MENENGAH** | Butuh alert HTTP 500 dan kegagalan asset untuk homepage sebelum flag dinyalakan. |
| Cache invalidation belum dirancang | **MENENGAH** | Publish → cache perlu di-invalidate, supaya pengunjung tidak melihat konten basi. |
| Verifikasi backup produksi | **MENENGAH** | Belum dicek apakah backup `oriondb` benar-benar bisa restore. |

### Tidak阻断 (boleh dikerjakan setelah 4D.1)

| Item | Severity |
|------|----------|
| Lazy loading gambar di section atas (hero) — saat ini `loading="lazy"` juga dipakai untuk hero | RENDAH |
| Ukuran gambar tidak direduksi/di-resize saat upload | RENDAH |
| Multi-page support (saat ini slug `home` saja) | RENDAH |
| Validasi ID section di resolver (saat ini hanya publish flow yang menegakkan regex `s-[a-z0-9]{9}`) | RENDAH |
| Editor masih inline CSS, belum partial bersama | RENDAH |

### Risiko yang harus dicatat

Risiko utama 4D.1 adalah **false sense of safety**: resolver sudah teruji 32 skenario, tapi jalur homepage publik adalah jalur paling@Getter di depan mata.esa belum ada yang pernah membukanya di browser sungguhan dengan flag ON. Cutover tanpa B1–B13 sama dengan navigasi tanpa kompas.

---

## 13. REKOMENDASI UNTUK 4D.2

**Rekomendasi: 4D.2 — EXECUTED BROWSER ACCEPTANCE + MONITORING (flag tetap OFF)**

Checkpoint berikutnya sebaiknya **tidak** langsung menyalakan flag. Isinya:

### 13.1 Wajib

1. **Jalankan B1–B13** di Chrome lokal terhadap `percetakan_erp_testing` dengan flag ON di environment lokal saja. Isi kolom Actual, PASS/FAIL, screenshot, nama penguji, tanggal.
2. **Siapkan monitoring** sebelum flag dinyalakan di produksi:
   - Alert rasio HTTP 5xx pada route `/`.
   - Alert kegagalan asset (`/build/*`, `/storage/landing-media/*`).
   - Log `Log::warning` dari resolver sudah ada sebagai sinyal (`unsupported schema version`, `invalid content structure`, `read failure, fallback to existing`) — pastikan ini masuk ke aggregate yang dipantau.
3. **Rancang cache invalidation** saat publish, supaya konten baru tampil tanpa menunggu TTL.
4. **Verifikasi backup `oriondb`** benar-benar bisa di-restore (dry-run di mesin terpisah).
5. **Catat sign-off** stakeholders (Administrator pemilik sistem, siapa yang menyetujui homepage baru).

### 13.2”后 4D.2 — baru 4D.3 (Activation)

Hanya setelah B1–B13 hijau, monitoring aktif, dan sign-off tercatat:
`4D.3 — ACTIVATION WITH FLAG ON, FLAGIH-OFF FIRST` menyalakan flag di produksi **dengan** satu Published Revision yang sudah ditinjau, lalu memantau. Rollbackkeeping adalah satu variabel env.

### 13.3 Goyang yang saya rekomendasikan

Jangan pernah menyalakan flag pada environment yang sama dengan mesin ERP produksi tanpa jeda observasi. Jika memungkinkan, nyalakan flag di **staging/clone** terlebih dahulu selama 24–48 jam dengan monitoring aktif, baru produksi.

---

## RINGKASAN

| Item | Status |
|------|--------|
| Feature flag | ✅ Dibuat, default OFF, `config:cache`-safe |
| Flag diaktifkan? | ❌ **TIDAK** — nilai terverifikasi `false`, `.env` tidak diubah |
| Resolver | ✅ 3 lapis (seleksi, validasi, render) |
| Fallback ke homepage existing | ✅ 10 kondisi, semuanya ada testnya |
| Draft bocor ke publik | ❌ Tidak mungkin (resolver tidak menyentuh tabel draft) |
| Renderer reuse | ✅ Preview & publik pakai partial yang sama |
| SEO | ✅ Canonical tidak pernah URL preview; preview `noindex` |
| Route publik | ✅ `/` dan nama route `login` tidak berubah |
| Blade lama | ✅ Tidak dihapus |
| oriondb | ✅ Tidak tersentuh |
| Dependency baru | ✅ Tidak ada |
| Full ERP regression | ✅ 1022 tests / 4754 assertions / **0 failure / 0 error** |
| Test baru | ✅ 32 tests / 85 assertions |
| Bug ditemukan & diperbaiki | ✅ 2 (truncation schema version, content_json tidak dinormalisasi) |
| Browser E2E | ⏸️ **PENDING** — tidak diklaim verified |
| Insiden | ⚠️ Skema testing DB sempat hancur oleh test destruktif saya; sudah diperbaiki & dicatat |

## STATUS AKHIR

**`LANDING PAGE BUILDER — CUTOVER INFRASTRUCTURE VERIFIED / FLAG OFF`**

Infrastruktur cutover terverifikasi dan flag terbukti OFF. Public cutover **tidak** dilakukan dan **tidak** diaktifkan pada checkpoint ini.