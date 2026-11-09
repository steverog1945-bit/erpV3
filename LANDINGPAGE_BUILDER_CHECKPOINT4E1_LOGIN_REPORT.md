# LANDING PAGE BUILDER — CHECKPOINT 4E.1
## CRITICAL LOGIN ACCESS RECOVERY

## STATUS AKHIR: `LANDING PAGE BUILDER — LOGIN ACCESS VERIFIED`

**Authorization:** APPROVED FOR MINIMAL LOGIN ACCESS FIX IN DEVELOPMENT/TESTING ONLY.
**Public cutover:** TIDAK diaktifkan. Flag tetap `false`.
**`oriondb`:** READ-ONLY, tidak dimodifikasi.

---

## RINGKASAN EKSEKUTIF

Blocker B2 dari Checkpoint 4D.2 (**staff lockout total saat flag ON**) sudah
**dipulihkan dan terbukti lewat 55 test**.

Perbaikan inti sangat kecil: **nama route `login` dipindah dari `/` ke `/masuk`**.
Karena seluruh middleware dan kode aplikasi sudah memakai `route('login')`,
satu perubahan nama route itu otomatis memperbaiki semua titik lockout tanpa
menyentuh logika autentikasi sama sekali.

SATPUN bug tambahan ditemukan saat pengerjaan dan diperbaiki: **login gagal
mengarah ke `back()`** yang bisa mendarat di homepage builder tanpa form —
error message tak terlihat dan staf tidak bisa mencoba lagi.

---

## 1. ROOT CAUSE

### Rantai lockout (sebelum 4E.1)

```
Route::get('/', [AuthController::class,'showLogin'])->name('login');   ← route name = '/'
Route::post('/login', [AuthController::class,'login']);                ← tanpa nama

Staf buka /kasir/monitoring
  → middleware 'auth' menolak
  → Laravel: redirect()->guest(route('login'))      [Handler::unauthenticated]
  → route('login') = '/'                            ← homepage, BUKAN halaman login
  → AuthController::showLogin() → resolver → flag ON → landing-page-public
  → TIDAK ADA form login
  → staf tidak bisa masuk dari mana pun
```

Yang memperburuk: **tidak ada `GET /login` sama sekali**. Form login hanya ada
sebagai `<dialog id="dialog-masuk">` di dalam `publik/layout.blade.php`, yang
dirender hanya oleh view `login.blade.php` (homepage). Jadi ketika homepage
diganti, satu-satunya tempat form login ikut hilang.

### Akar konfigurasi

Satu nama route dipakai untuk dua hal berbeda:

| Nama route |_processor_ | Masalah |
|------------|------------|---------|
| `route('login')` | `/` (homepage publik) | Dipakai sebagai tujuan auth redirect |

Selama homepage == halaman login, ini aman. Begitu homepage bisa diganti,
konvksi itu pecah.

---

## 2. PERILAKU ROUTE ASLI (sebelum perubahan)

Dokumentasi lengkap hasil inspeksi Phase 1:

| Item | Nilai aktual |
|------|--------------|
| Route homepage | `Route::get('/', [AuthController::class,'showLogin'])->name('login');` |
| Route POST login | `Route::post('/login', [AuthController::class,'login'])->middleware('throttle:login');` (**tanpa nama**) |
| Route logout | `Route::post('/logout', [AuthController::class,'logout'])->name('logout');` |
| `GET /login` | **TIDAK ADA** |
| View form login | `publik/layout.blade.php` baris 110–137 (bukan `login.blade.php`) |
| Tombol pemicu | `publik/layout.blade.php:95` — `<button data-buka-masuk>Masuk</button>` |
| Auto-open dialog | `publik/layout.blade.php:5` — `$bukaMasuk = $errors->any() \|\| !empty($bukaMasuk) \|\| request()->has('masuk');` |
| Auth redirect | Laravel default → `route('login')` (`Handler::unauthenticated`) |
| Guest middleware | **Tidak dipakai** di aplikasi ini |
| Session config | `SESSION_DRIVER=array` saat test; `Cache::rememberForever` untuk AppSetting |

### Semua pemakaian `route('login')` yang ditemukan

| Lokasi | Konteks |
|--------|---------|
| `HanyaAdministrator.php:37` | `redirect()->route('login')` untuk tamu |
| `vendor/.../Handler.php` | `redirect()->guest(route('login'))` untuk auth middleware |
| `tests/Feature/LandingPageBuilderTest.php:38` | assertion redirect (simetris, aman) |

Hanya **2 tempat produksi** — keduanya otomatis ikut benar setelah nama route
dipindahkan. Tidak ada kode yang meng-hardcode `route('login')` dengan asumsi
URI `/`.

---

## 3. FIX MINIMAL YANG DIPILIH

### Keputusan desain

Checkpoint meminta: *"The login route name must resolve to an endpoint that
always provides access to the login form."*

Saya memenuhi itu secara harfiah: **nama route `login` sekarang menunjuk
endpoint yang form-nya selalu ada, tanpa syarat.**

```
Route::get('/',       [AuthController::class,'showLogin'])->name('beranda');
Route::get('/masuk',  [AuthController::class,'formLogin'])->name('login');   ← nama 'login' pindah ke sini
Route::post('/login', [AuthController::class,'login'])->middleware('throttle:login');
Route::post('/logout',[AuthController::class,'logout'])->name('logout');
```

### Kenapa `/masuk`, bukan `/login`

`POST /login` sudah dipakai. Daftarkan `GET /login` pun tetap aman di Laravel
(method berbeda), tapi memakai `/masuk` memberi dua keuntungan:

1. **Tidak mungkin bertabrakan** dengan `POST /login` di masa depan.
2. **Tidak menyesatkan** — GET ke `/login` yang dulu 404 kini muncul halaman
   login penuh; `/masuk` jelas endpoint masuk.

### Kenapa `formLogin()` melewati resolver

```php
public function formLogin(Request $request)
{
    return $this->halamanBeranda($request, null, true);   // resolver = null
}
```

Di dalam `halamanBeranda()`, cabang `$paksaDialog` **dilewati sebelum resolver
dipanggil sama sekali**. Konsekuensinya:

> `/masuk` secara struktural **tidak mungkin** menyajikan konten builder —
> berapa pun nilai feature flag, dan bahkan kalau database tidak terbaca.

Ini jaminan yang jauh lebih kuat daripada sekadar "fallback jika gagal".

### Yang TIDAK的做法 (tidak dilakukan)

- Tidak menulis ulang sistem autentikasi.
- Tidak menambah sistem autentikasi baru.
- Tidak mengganti view `login.blade.php`.
- Tidak membuat form login kedua.
- Tidak menyalin logika login.
- `POST /login` tetap **persis** seperti sebelumnya (kecuali 1 baris redirect
  gagal,lihat bagian 4).

### Penampilan `/masuk`

Memakai `view('login')` yang sama persis dengan homepage — jadi form, dialog,
CSRF, dan endpoint-nya **identik**, dijamin oleh构造 ulang, bukan duplikasi.
Yang berbeda hanya `bukaMasuk` dipaksa `true`.

---

## 4. BUG TAMBAHAN YANG DITEMUKAN & DIPERBAIKI

Saat menulis test, ditemukan bug yang **tidak ada di laporan 4D.2**:

```php
// SEBELUM (AuthController::login, gagal)
return back()->withErrors(['username' => 'User tidak ditemukan atau password salah.']);
```

`back()` mengarah ke URL sebelumnya. Kalau form dibuka dari homepage `/` dan
flag ON, halaman tujuannya adalah **builder page tanpa dialog**. Akibatnya:

- Pesan "password salah" **tidak terlihat** (tidak ada dialog).
- Dialog **tidak terbuka** — `$errors->any()` dihitung di layout yang tidak
  dirender.
- Staf tidak bisa mencoba lagi. **Lockout kedua**, berbeda pintu masuk.

Diperbaiki:

```php
return redirect()->route('login')->withErrors([
    'username' => 'User tidak ditemukan atau password salah.',
]);
```

Ditutup test `skenario_08b_kredensial_salah_mengarah_ke_endpoint_login_bukan_homepage`
yang memverifikasi pesan error muncul **di halaman yang punya form**, dengan flag ON.

---

## 5. BERKAS YANG BERUBAH

### Produksi (3 berkas)

| Berkas | Perubahan | Ukuran |
|--------|-----------|--------|
| `routes/web.php` | Nama `login` dipindah `/` → `/masuk`; homepage diberi nama `beranda` | +14 baris |
| `app/Http/Controllers/AuthController.php` | Tambah `formLogin()`; `showLogin()` jadi wrapper; extract `halamanBeranda()`; fix redirect login gagal | +50 baris |
| `resources/views/landing-page-public.blade.php` | Header + footer dengan pintu masuk staf | +50 baris |

### Test (4 berkas)

| Berkas | Perubahan |
|--------|-----------|
| `tests/Feature/LandingPageLoginAccessTest.php` | **BARU** — 32 test, 18 skenario wajib |
| `tests/Feature/LandingPageLoginRouteAuditTest.php` | Diperbarui: 6 test yang mengunci perilaku LAMA kini mengunci perilaku BARU |
| `tests/Feature/AuthTest.php` | `assertRedirect('/')` → `assertRedirect(route('login'))` |
| `tests/Feature/HalamanDepanPublikTest.php` | Dialog "sekali" dipindah keji ke homepage; `/masuk` selalu buka |
| `tests/Feature/MatriksRuteKeamananTest.php` | Allowlist rute publik ditambah `GET /masuk` |

### Yang SENGAJA tidak disentuh

`login.blade.php` · `publik/layout.blade.php` · `publik/baca.blade.php` ·
`PublikController.php` · `HanyaAdministrator.php` · `POST /login` handler
(auth logic) · tabel `users`/`roles`/`role_permissions` · modul ERP ·
`.env` · dependency.

---

## 6. PERUBAHAN ROUTE

### Sebelum → sesudah

| Nama route | Sebelum | Sesudah |
|-----------|---------|---------|
| `login` | `GET /` | **`GET /masuk`** |
| `beranda` | — | **`GET /`** (baru) |
| `publik.baca` | `GET /baca/{slug}` | tidak berubah |
| (tanpa nama) | `POST /login` | tidak berubah |
| `logout` | `POST /logout` | tidak berubah |

Verifikasi langsung:

```
route(login)   = http://localhost/masuk
route(beranda) = http://localhost
```

### Dampak pada middleware

| Middleware | Sebelum | Sesudah | Impacts |
|-----------|---------|---------|---------|
| `auth` (Laravel) | `→ /` | **`→ /masuk`** |ingo Pinternal |
| `hanya.admin` | `→ /` | **`→ /masuk`** | HanyaAdministrator.php:37 |

Keduanya **otomatis** ikut benar. Tidak ada kode yang perlu diubah.

### Rute publik baru

`GET /masuk` adalah rute publik baru. Sudah didaftarkan di allowlist
`MatriksRuteKeamananTest` — test keamanan ERP itu sempat **menangkap** rute ini
dan menolak, sehingga kemunculannya tercatat dan disengaja.

---

## 7. KOMPATIBILITAS AUTHENTIKASI

| Aspek | Status | Bukti |
|-------|--------|-------|
| Kredensial existing | ✅ Tidak berubah | `Auth::attempt($credentials)` utuh |
| Verifikasi password | ✅ Tidak berubah | Hash tidak disentuh |
| Regenerasi session | ✅ Tidak berubah | `$request->session()->regenerate()` |
| Session flash | ✅ Bailey | `url.intended` tetap di-`forget` setelah login |
| Remember-me | ✅ Tidak ada di app ini | Tidak ada perubahan |
| Logout | ✅ Tidak berubah | `POST /logout` → `/` |
| Redirect berbasis role | ✅ Tidak berubah | 403 untuk non-admin dari halaman admin |
| Intended URL | ✅ Dihormati, lalu dibuang | `skenario_12`, `skenario_12b` |
| CSRF | ✅ Berlaku | Token dari `@csrf`, diverifikasi terhadap session token |
| Rate limiting | ✅ Berlaku | `throttle:login` tetap di `POST /login` |
| Izin ERP | ✅ Tidak berubah | `menu.access`, `role_permissions` utuh |

### Yang berubah perilakunya (satu, disengaja)

| Situasi | Sebelum | Sesudah | Alasan |
|---------|---------|---------|--------|
| Login **gagal** | `back()` → bisa ke `/` | `→ /masuk` | Bug lockout kedua (bagian 4) |

---

## 8. HASIL TEST

### Suite baru — `LandingPageLoginAccessTest`

**32 tests / 137 assertions, 0 failure**

18 skenario wajib + pengaman tambahan:

| # | Skenario | Hasil | Test |
|---|----------|-------|------|
| 1 | Guest GET /login | ✅ | `skenario_01_...` |
| 1b | Dialog terbuka otomatis | ✅ | `skenario_01b_...` |
| 2 | Guest GET / flag OFF | ✅ | `skenario_02_...` |
| 3 | Guest GET / flag ON + pintu masuk | ✅ | `skenario_03_...` |
| 4 | Authenticated GET /login → dashboard | ✅ | `skenario_04_...` |
| 5 | Guest /dashboard → login | ✅ | `skenario_05_...` |
| 5b | **/masuk tidak pernah render builder** | ✅ | `skenario_05b_...` |
| 6 | Form berfungsi (action/POST/CSRF/label) | ✅ | `skenario_06_...` |
| 7 | Kredensial benar | ✅ | `skenario_07_...` |
| 8 | Kredensial salah ditolak | ✅ | `skenario_08_...` |
| 8b | **Error ke halaman berform** | ✅ | `skenario_08b_...` |
| 9 | CSRF ditegakkan | ✅ | `skenario_09_...`, `09b` |
| 10 | Logout | ✅ | `skenario_10_...` |
| 11 | Session expiry | ✅ | `skenario_11_...` |
| 12 | Intended destination | ✅ | `skenario_12_...`, `12b` |
| 13 | Role-based access | ✅ | `skenario_13_...`, `13b` |
| 14 | Pintu masuk di builder | ✅ | `skenario_14_...` |
| 15 | Pintu masuk flag OFF utuh | ✅ | `skenario_15_...` |
| 16 | Pintu masuk通往 endpoint berform | ✅ | `skenario_16_...` |
| 16b | **Builder tidak bocorkan form** | ✅ | `skenario_16b_...` |
| 17 | Tidak ada redirect loop | ✅ | `skenario_17_...`, `17b`, `17c` |
| 18 | Homepage existing utuh | ✅ | `skenario_18_...`, `18b` |

Plus: endpoint login tidak bocorkan konten ERP · tautan `nofollow` ·
`route('login')` ≠ `route('beranda')` · endpoint tetap merespons saat DB gagal.

### Suite audit 4D.2 — diperbarui

**23 tests / 86 assertions, 0 failure**

6 test yang sebelumnya **mengkunci perilaku rusak** kini mengunci perilaku benar:

| Test | Sebelum | Sesudah |
|------|---------|---------|
| `skenario_05_get_login_route_availability` | `route('login')` = `/` | `= /masuk` + form ada |
| `skenario_07_post_login_failure...` | redirect `/` | redirect `route('login')` |
| `skenario_09_guest_bounced...flag_off` | redirect `/` | redirect `/masuk` |
| `skenario_09b_...flag_on` | **assert TIDAK ada jalan masuk** | **assert ADA jalan masuk** |
| `skenario_11_route_name_login_compatibility` | URI `/` | URI `masuk` |
| `temuan_..._tidak_punya_jalan_masuk` | lockout | **`temuan_..._memiliki_pintu_masuk`** |

Test `temuan_builder_homepage_tidak_punya_jalan_masuk` sengaja saya ganti nama
dan dibalik assertions-nya — karena test lama akan **gagal** setelah perbaikan,
dan membiarkannya akan menyesatkan.

### Seluruh suite Landing Page Builder

```
190 tests / 596 assertions / 0 failure
```

---

## 9. FULL ERP REGRESSION

Dijalankan berurutan, tanpa proses PHP paralel.

| Metrik | Baseline 4D.2 | **4E.1** | Delta |
|--------|---------------|---------|-------|
| Tests | 1049 | **1085** | **+36** |
| Assertions | 4841 | **5006** | **+165** |
| **Failures** | 0 | **0** | — |
| **Errors** | 0 | **0** | — |
| Skipped | 1 | **1** | — |
| Risky | 0 | **0** | — |
| Deprecations | 160 | **194** | +34 |
| Durasi | 8:07 | 8:33 | — |

### Delta test +36 (ter Breakdown)

| Sumber | Tests |
|--------|-------|
| `LandingPageLoginAccessTest` (baru) | +32 |
| `HalamanDepanPublikTest` | +2 (`endpoint_login_membuka_dialog_masuk_selalu`, `homepage_buka_dialog_masuk_hanya_sekali_berikutnya`) |
| `LandingPageLoginRouteAuditTest` | +1 (`skenario_11c_homepage_memiliki_nama_beranda_terpisah`) |
| `LandingPageLoginRouteAuditTest` (rename 1 test) | ±0 |
| **Total** | **+35** |

Selisih 1 test karena `temuan_builder_homepage_tidak_punya_jalan_masuk`
diganti oleh **dua** test (`memiliki_pintu_masuk` + `tidak_membocorkan_form`).

### 3 test existing yang harus diperbarui

Semuanya mengunci kontrak route **lama**. Saya perbarui — dan satu di antaranya
justru **menangkap安全问题 baru**:

1. **`MatriksRuteKeamananTest`** — security allowlist ERP menangkap `GET /masuk`
   sebagai rute publik tak terduga. Saya daftarkan secara eksplisit. Ini bukti
   test keamanan itu bekerja dan rute baru memang disengaja.
2. **`AuthTest::test_halaman_terproteksi_redirect_ke_login_kalau_belum_masuk`** —
   `assertRedirect('/')` → `assertRedirect(route('login'))` + `url('/masuk')`.
3. **`HalamanDepanPublikTest::test_ditolak_dari_halaman_dalam_...`** —
   dialog "sekali" sekarang milik homepage; `/masuk` selalu buka.

Tidak ada assertion yang saya longgarkan — semuanya diperketat atau dipindah ke
perilaku yang benar.

### Test yang di-skip

`RawSqlAuditTest` (1) — sudah ada sebelum checkpoint ini, tidak terkait.

### Modul ERP

Kasir, SPK, Produksi, Collecting, Pembayaran, Stok, Pembelian, Jurnal, Login,
RBAC, Menu — **semua hijau**. Nol regresi.

---

## 10. BROWSER E2E — STATUS: PENDING

**Tidak ada skenario dieksekusi.** Klaim E2E verified **tidak diklaim**.

Tidak ada Playwright/Cypress/Dusk/Selenium. Tidak ada dependency baru dipasang.

### Yang sudah diverifikasi lewat HTTP nyata

Bukan browser, tapi bukti bahwa perubahan bekerja di server sungguhan:

```
http://localhost/grafikaorion/       → 200, 32233 bytes, form=True, dialog=True
http://localhost/grafikaorion/masuk → 200, 32242 bytes, form=True, dialog=True

$ php scripts/check_public_homepage.php http://localhost/grafikaorion/ existing
  HTTP status: 200
  Endpoint login: http://localhost/grafikaorion/masuk -> HTTP 200
  HASIL: SEHAT
```

### Prosedur manual (untuk dieksekusi penguji)

URL aplikasi: **`http://localhost/grafikaorion/`** (bukan `http://localhost` —
itu halaman default XAMPP).

#### A. Tamu & Login (flag OFF)

| ID | Langkah | Expected | Actual | PASS/FAIL | Bukti | Penguji | Tanggal |
|----|---------|----------|--------|-----------|-------|---------|---------|
| M1 | Buka `/` | Homepage + tombol "Masuk" | | | | | |
| M2 | Klik "Masuk" | Dialog terbuka | | | | | |
| M3 | Buka `/masuk` langsung | Dialog terbuka seketika | | | | | |
| M4 | Masuk dengan password salah | Pesan error terlihat di halaman yang ada formnya | | | | | |
| M5 | Masuk dengan kredensial benar | Masuk ke dashboard | | | | | |
| M6 | Klik "Keluar" | Kembali ke homepage | | | | | |
| M7 | Buka `/kasir/monitoring` setelah logout | Redirect ke `/masuk`, dialog terbuka | | | | | |
| M8 | Login lagi dari `/masuk` | Masuk ke dashboard | | | | | |

#### B. Flag ON (testing terisolasi)

| ID | Langkah | Expected | Actual | PASS/FAIL | Bukti | Penguji | Tanggal |
|----|---------|----------|--------|-----------|-------|---------|---------|
| M9 | Set flag ON lokal, publish valid | Homepage builder tampil | | | | | |
| M10 | Cari "Masuk Karyawan" | Ada di header DAN footer | | | | | |
| M11 | Klik "Masuk Karyawan" | Ke `/masuk`, dialog terbuka | | | | | |
| M12 | Buka `/kasir/monitoring` tanpa login | Redirect ke `/masuk` (BUKAN `/`) | | | | | |
| M13 | Salah password di `/masuk` | Pesan error terlihat | | | | | |
| M14 | Login benar dari `/masuk` | Masuk ke dashboard | | | | | |
| M15 | Buka `/` saat sudah login | Redirect dashboard | | | | | |
| M16 | Logout, buka `/kasir/monitoring` | Kembali ke `/masuk` | | | | | |

#### C. Mobile

| ID | Langkah | Expected | Actual | PASS/FAIL | Bukti | Penguji | Tanggal |
|----|---------|----------|--------|-----------|-------|---------|---------|
| M17 | `/` di 375px | Tombol "Masuk Karyawan" terlihat & bisa ditekan | | | | | |
| M18 | `/masuk` di 375px | Dialog muat di layar, input bisa diketik | | | | | |
| M19 | `/masuk` di 768px | Dialog tampil benar | | | | | |

#### D. ERP tidak terganggu

| ID | Langkah | Expected | Actual | PASS/FAIL | Bukti | Penguji | Tanggal |
|----|---------|----------|--------|-----------|-------|---------|---------|
| M20 | Login sebagai Kasir, buka `/kasir/monitoring` | Normal | | | | | |
| M21 | Login sebagai non-admin, buka `/setting/landing-page-builder` | 403 | | | | | |
| M22 | Alur order normal (order→kasir→SPK→produksi→bayar) | Normal | | | | | |

---

## 11. SISA RISIKO

### Sudah tertutup

| Risiko | Status |
|--------|--------|
| Staff lockout saat flag ON | ✅ **Tertutup** — `route('login')` → `/masuk` |
| Login gagal mengarahkan ke halaman tanpa form | ✅ **Tutup** — redirect ke `route('login')` |
| `/masuk` bisa render builder | ✅ Mustahil secara struktural |
| Pintu masuk hilang dari builder | ✅ Header + footer, `nofollow` |
| Form/kredensial bocor ke halaman publik | ✅ Terverifikasi, ditolak |
| Redirect loop | ✅ 3 test |
| Rute publik tak terdaftar | ✅ Tercakup security matrix |
| CSRF hilang di halaman baru | ✅ Token diverifikasi |

### Masih terbuka

| Risiko | Severity | Catatan |
|--------|----------|---------|
| **`oriondb` belum punya tabel landing page** | **TINGGI** | Blocker B1 dari 4D.2 — **belum tersentuh di 4E.1** |
| Browser E2E belum dieksekusi | SEDANG | 22 skenario M1–M22 PENDING |
| `/masuk` memakai view homepage penuh | RENDAH | Query `posts` dijalankan wasted. Bisa dioptimalkan nanti tanpa mengubah kontrak |
| Dialog `<dialog>` butuh browser modern | RENDAH | Sudah dipakai homepage existing |
| Staff yang bookmark `/` lalu buka manual | RENDAH | Sekarang `/` punya tombol Masuk — mitigation cukup |
| Monitoring otomatis belum ada | SEDANG | Hanya skrip manual (dari 4D.2) |
| Backup restore UNVERIFIED | SEDANG | Dari 4D.2, belum berubah |
| Tidak ada sign-off stakeholder | TINGGI | Dari 4D.2 |

### Yang perlu diwaspadai

Perubahan ini **mengubah kontrak route** `route('login')` dari `/` ke `/masuk`.
Kode di luar repo (webhook, integrasi pihak ketiga, bookmark, dokumentasi) yang
meng-hardcode `route('login')` → `/` akan ikut berubah. Di dalam repo semua
sudah aman karena semuanya memakai fungsi `route()`. Tetap, ini perubahan
kontrak yang harus dicatat dalam changelog saat deploy.

---

## 12. REKOMENDASI UNTUK 4E.2

**Rekomendasi: 4E.2 — VERIFY BROWSER + PRODUCTION PREREQUISITES. Tetap jangan
aktifkan flag.**

Urutan:

1. **Jalankan M1–M22** di browser nyata terhadap `percetakan_erp_testing` dengan
   flag ON di lokal. Isi semua kolom.
2. **Blokir B1** — approval + migrasi `oriondb`, lalu backup ulang. Tanpa ini
   fitur tidak bisa berfungsi di produksi.
3. **Restore test** ke database terisolasi.
4. **Pasang monitoring** + alerting, tetapkan rollback PIC.
5. **Soak test di staging** 24–48 jam dengan flag ON.
6. **Kumpulkan sign-off.**
7. Baru **4E.3 — ACTIVATION**.

### Apakah perlu-soak test tambahan untuk 4E.1?

Tidak. Perubahan 4E.1 kecil, terisolasi, dan **tidak mengubah perilaku saat flag
OFF** — homepage existing identik. Yang perlu di-soak adalah fitur builder-nya
sendiri, yang memang sudah masuk antrean sejak 4D.2.

### Satu saran perbaikan opsional (bukan sekarang)

`/masuk` saat ini mewarisi seluruh query homepage (`posts`, `kategoriList`,
`unggulan`). Bisa dibuat ramping dengan view pembungkus tipis. **Tidak
disarankan sekarang** — menambah view baru berarti menambah permukaan regresi,
dan query tambahan ini tidak impacting-operational._urban

---

## LAMPIRAN A — Reproduksi

```bash
# Flag harus OFF
php artisan config:clear
php artisan tinker --execute="echo var_export(config('landing_page_builder.enabled'), true);"
# → false

# Route
php artisan route:list --name=login
php artisan route:list --name=beranda
php artisan tinker --execute="echo route('login');"   # /masuk

# Suite login
php vendor/bin/phpunit tests/Feature/LandingPageLoginAccessTest.php
php vendor/bin/phpunit tests/Feature/LandingPageLoginRouteAuditTest.php

# ERP auth
php vendor/bin/phpunit tests/Feature/AuthTest.php
php vendor/bin/phpunit tests/Feature/HalamanDepanPublikTest.php
php vendor/bin/phpunit tests/Feature/MatriksRuteKeamananTest.php

# Full suite
php vendor/bin/phpunit -c phpunit.xml

# Live check
php scripts/check_public_homepage.php http://localhost/grafikaorion/ existing
```

---

## LAMPIRAN B — Yang tidak dilakukan (sesuai stop condition)

| Larangan | Status |
|----------|--------|
| Mengaktifkan flag produksi | ✅ Tidak — `false`, `.env` tidak diubah |
| Modifikasi `oriondb` | ✅ Tidak |
| Deploy | ✅ Tidak |
| Menjalankan migration | ✅ Tidak |
| Refactor autentikasi menyeluruh | ✅ Tidak — hanya 1 method baru + 1 extract |
| Mengubah modul ERP | ✅ Tidak |
| Menghapus view login existing | ✅ Tidak |
| Memasang dependency | ✅ Tidak |

---

## STATUS AKHIR

# `LANDING PAGE BUILDER — LOGIN ACCESS VERIFIED`

Staff lockout **teratasi**: `route('login')` kini selalu menunjuk `/masuk`,
endpoint yang form login-nya selalu ada tanpa syarat flag.

**55 test**专门 menunggu perbaikan ini (32 baru + 23 diperbarui).
**Full ERP regression: 1085 tests / 5006 assertions / 0 failures / 0 errors.**

Flag tetap **OFF**. `oriondb` **tidak dimodifikasi**. Public cutover **tidak
dilakukan** — blocker B1 (tabel landing page di `oriondb`) masih berdiri dan
menjadi gerbang 4E.2.