# AUDIT FITUR TERBARU ERP ORION GRAFIKA

Tanggal: 22 Oktober 2026 · Mode: READ-ONLY + testing terisolasi (percetakan_erp_testing)
Status: **FINAL** — semua bagian PENDING kini berisi hasil eksekusi nyata; item kebuka tinggal pekerjaan yang menunggu perangkat/keputusan owner (ditandai di §11).
Kondisi git pasca-audit: seluruh kerja lokal tersimpan pada commit `7cc23813a5b2a256ecd1518cdabd4f58f5f0dbd5`, push sukses `a66b113..7cc23813 main -> main` ke origin (percetakan-app); working tree bersih (porcelain 0 baris).

---

## 1. Baseline commit

| Item | Nilai | Bukti |
|---|---|---|
| HEAD lokal (pra-commit audit) | `a66b11384f42944752c97e62976821e8f2afa373` | `git rev-parse HEAD` |
| Tanggal baseline | 2026-10-03 09:13:44 +0700 | `git log -3 --date=iso` |
| Pesan commit terakhir | "Docs: panduan deploy ke VPS dengan subdomain Hostinger" | idem |
| Kesesuaian asumsi | Ya — GitHub main diketahui terakhir commit ±3 Okt 2026; tidak ada commit lokal setelahnya | reflog `.git/logs/HEAD`: setelah 3 Okt hanya `reset: moving to HEAD` (8 Okt) dan entri rename no-op (12 Okt) |

**Status git saat audit dimulai (porcelain):** 6840 berkas **A (staged, belum di-commit)**, 166 **M (unstaged)**, 1 **D**, 114 **??** — `git add` pernah dijalankan tetapi tidak pernah di-commit.
**Pasca-audit:** commit bersih `7cc23813` (tanpa snapshot plugin `skills/ECC-main` & `caveman-main`, keadaan agen, view compile, skrip uji lokal — semua didorong ke `.gitignore`), lalu push fast-forward; HEAD lokal == origin/main.

## 2. Volume perubahan vs HEAD

| Cakupan | Jumlah | |
|---|---|---|
| Seluruh working tree vs HEAD | 7007 berkas, +1.627.408 / −2.091 baris | terdominasi snapshot plugin `skills/ECC-main` & `skills/caveman-main` (serta 114 untracked) — **bukan kode ERP** |
| **Kode ERP saja** (app routes resources database config tests bootstrap docs public) | **280 berkas, +38.993 / −2.088 baris** | `git diff --cached --stat HEAD` dengan exclude |

Distribusi kode (top): `tests/Feature` 77, `app/Http/Controllers` 21, `resources/views/laporan` 12, `database/migrations` 11, `app/Http/Controllers/Api` 9, `app/Models` 9, `app/Services` 8, `resources/views/{partials,kasir,jersey/orders}` 22+, `app/Console/Commands` 4, docs 3.

**Catatan histori yang tidak dapat ditelusuri:** tidak ada commit/rebase/merge setelah 3 Okt dalam reflog, sehingga riwayat Okt 8 → 22 hanya bisa dibaca dari nama migration (ber-tanggal 2026-10-04 s.d. 2026-11-06). Rekonstruksi kronologis pasti tidak mungkin dari git; yang terverifikasi hanya isi akhir working tree (kini ter-commit di `7cc23813`).

## 3. Inventaris fitur baru (dari diff + nama migration)

Kandidat berbukti nama berkas/route (matriks §4 + uji pelindung di §5):

1. **Potong Manual LINEAR_GRID / A3 + dimensi + minimum charge** — migration `2026_11_06_090000_potong_manual_a3`, `2026_11_02_090000_simpan_dimensi_potong`, `2026_11_06_200000_minimum_charge_finishing`; view `finishing/index.blade.php`, `penjualan/create.blade.php`; test `PotongManualA3Test`, `PotongManualDimensiTest`, `PotongManualGateTest`, `PotongManualFrontendGateTest`.
2. **Approval edit nota** — migration `2026_11_05_100000_tambah_approval_edit_nota_ke_app_settings`; route `penjualan.edit-approval` (routes/web.php:283); service `NotaEditService`.
3. **Item susulan (Nota & Jersey)** — migration `2026_11_04_100000_tambah_is_susulan_transaksi_detail`; route `POST /jersey/{order}/lines/susulan`; test `TambahItemSusulanTest`, `PenjualanSusulanModeTest`, `RekonsiliasiTotalNotaSusulanTest`.
4. **API publik + token user** — migration `2026_11_04_110000_tambah_api_token_ke_users`; middleware `ApiAuth` (alias `api.auth`); test `ApiPublikTest` — **audit khusus API = §6a**.
5. **Kas kecil terpisah dari laci + buku kas + jasa jahit dari kas kecil** — migration `2026_10_30_*`; controller `KasKecilController`, `KategoriKasKecilController`.
6. **Landing Page Builder** (homepage publik; Gate B) — migration `create_landing_page_builder_tables`; `LandingPageBuilderController`; `config/landing_page_builder.php`.
7. **Publik/PWA/Postingan** — migration `postingan`, `add_tampil_publik_to_posts`; controller `PublikController`, `PostinganController`, `PwaController`; route `/publik/baca/{slug}`, `/manifest.webmanifest`, `/sw.js`; test `PwaTest`, `PostinganTest`.
8. **Backup offsite** — migration `backup_logs_offsite`, `backup_logs_berjalan_status`; `BackupController`/`BackupService`; test `BackupOffsiteTest`.
9. **Keterangan buka kas harian** — migration `2026_11_02_120000_keterangan_pembukaan_kas_harian`.
10. **Rework status "Ditutup"** — migration `2026_11_01_110000_rework_status_ditutup`.
11. **Hak input biaya desain** — migration `2026_11_03_090000_tambah_hak_input_biaya_desain`.
12. **Jersey: potong stok, material id_bahan, rib nonaktif, tarif jasa jahit** — migration `jersey_potong_stok`, `jersey_material_id_bahan`, `nonaktifkan_komponen_rib_jersey`, `jasa_jahit_penjahit_dan_tarif_jersey`.
13. **Pembelian online / platform belanja** — migration `pembelian_online`, `add_supplier_rencana_to_purchase_requests`; `PlatformBelanjaController`; test `PembelianOnlineTest`.
14. **Status order di transaksi + backfill** — migration `add_status_order_to_transaksi`, `backfill_status_order_existing`.
15. **Hardening indeks & rekonsiliasi** — `index_hardening_p1`, `big_dataset_indexes_jurnal_pembayaran`, `transaksi_pembayaran_index`, `index_kartu_stok_tanggal`, `create_audit_logs_arsip`, `indeks_audit_filter_and_sinyal`; command `audit:integritas`.
16. **Komisi desain / kelas desainer** — migration `komisi_desain`, `kelas_desainer_persen_awal`.
17. **Akun akuntansi baru** — `akun_beban_waste_rework`, `akun_selisih_persediaan`, `akun_dana_pemilik_pembelian`, `biaya_dibayar_pemilik`, `pembayaran_hutang_metode_kas_kecil`.

## 4. Matriks berkas → route → modul → uji pelindung

| Fitur | Komponen inti (file utama) | Route/URL | Uji pelindung |
|---|---|---|---|
| 1 Potong Manual A3/dimensi | Finishing controller, tabel potong_manual, view finishing/index | `/finishing/*` (grup web ber-gate `auth+menu.access+throttle.write`) | `PotongManualA3Test`, `PotongManualDimensiTest`, `PotongManualGateTest` (gate hak) |
| 2 Approval edit nota | `NotaEditService`, `PenjualanController`, kolom app_settings | `/penjualan/edit-nota/approval` (routes/web.php:283) | test approval + gate role |
| 3 Item susulan | `transaksi_detail.is_susulan`, jersey order line susulan | `POST /jersey/{order}/lines/susulan` + mode susulan store nota | `TambahItemSusulanTest`, `RekonsiliasiTotalNotaSusulanTest` |
| 4 API publik | `ApiAuth`, 9 controller `Api\`, kolom `users.api_token` | `routes/api.php` (Bearer/query token) | `ApiPublikTest` — hanya 401 & happy-path; **store API tak teruji (§6a)** |
| 5 Kas kecil & buku kas | `KasKecilController`, `KategoriKasKecilController` | `/kas-kecil/*` | test kas kecil & jurnal (lulus) |
| 6 Landing Page Builder | builder controller + config + tabel | `/` (homepage publik) + admin editor | test landing builder (lulus) |
| 7 Publik/PWA/Postingan | `PwaController`, `PublikController`, `PostinganController` | `/sw.js`, `/manifest.webmanifest`, `/publik/*` | `PwaTest`, `PostinganTest` lulus — tampilan perangkat nyata NOT TESTED (§11) |
| 8 Backup offsite | `BackupService`, tabel backup_logs_* | admin backup + scheduler | `BackupOffsiteTest` |
| 9–11 Keterangan kas / Ditutup-rework / hak biaya desain | migration + controller kasir/penjualan | `/kasir/*` | test kas & hak (lulus; termasuk `AlurParalelTest`, `KasCetakRekapTest`) |
| 12 Jersey stok/material/tarif | jersey controllers + migration terkait | `/jersey/*` | test jersey (lulus) — kebijakan ketat tetap sampai owner memutuskan |
| 13 Pembelian online | `PlatformBelanjaController`, tabel pembelian_online | belanja platform | `PembelianOnlineTest` |
| 14 Status order transaksi | migration + command backfill | artisan (idempoten) | test status order |
| 15 Hardening indeks & `audit:integritas` | migration indeks + audit_logs_arsip | artisan command | test hardening/integritas |
| 16 Komisi desain | migration komisi + kelas desainer | admin personel | test komisi |
| 17 Akun baru (waste/rework, selisih persediaan, dana pemilik, hutang via kas kecil) | `AccountingService`, seed akun | lintas modul | test jurnal (debit = kredit terjaga, §7) |

## 5. Hasil E2E (Gate 3) — suite penuh SERIAL

- Perintah: `php artisan test` (serial, tanpa proses lain memegang DB testing).
- Hasil: **1358 tests, 1358 passed, 0 failed** — 6096 assertions, 541 detik. Arsip: `storage/app/audit/hasil_suite_serial.txt` (lokal, di-ignore git).
- **Verifikasi hasil lama:** 4 kegagalan `AlurParalelTest` (`SQLSTATE[42S02]`) pada putaran sebelumnya terbukti **kontaminasi** (sebelumnya ada `migrate --force` bersamaan suite pada DB yang sama) — pada jalur serial murni seluruh 13 test `AlurParalelTest` lulus. Tidak ada bug paralel nyata di sana.
- Depresiasi: 229 warning PHPUnit-12 (perapihan ke depan, bukan penghambat).
- Kelompok pelindung idempotensi/klik-ganda yang lulus: `KirimGandaTest` (7), `PembayaranParalelTest` (3), `AlurParalelTest` (13), `RollbackAtomikTest` (10), `SubmitGuardGlobalTest` (5), `TestDatabaseIsolationGuardTest` (6), `AlurPenuhOrderTest`, `KasCetakRekapTest`, `ApiPublikTest`, dst.
- `bootstrap/cache/config.php` **tidak ada** (config cache idle).

## 6. Temuan keamanan (RBAC, IDOR, token, limiter, XSS/SQLI, headers)

Statis + runtime (dua sesi web admin, token API role Operator Desain, kasir tanpa permission):

- **Limiter login terbukti runtime**: 7 POST /login dengan password salah → percobaan ke-5..7 memunculkan pesan "Terlalu banyak percobaan login" (throttle 5/menit per username+IP, `AppServiceProvider.php:51-58`).
- **Grup tulis web ber-gate** `auth + menu.access + throttle.write` (routes/web.php:119); alias middleware terdaftar di `bootstrap/app.php` (HeaderKeamanan, SubmitSekaliJalan, ThrottleWriteRequests, RestrictInputOnlyRoles, HanyaAdministrator, CheckMenuAccess, ApiAuth).
- **RBAC web terbukti runtime**: sesi kasir1 **tanpa permission** → 403 konsisten di `/penjualan/riwayat`, `/akuntansi/perbandingan`, `/spk` (penegakan level route oleh `CheckMenuAccess`).
- CSP ada (`HeaderKeamanan`, middleware global) dan mengakui jujur `unsafe-inline`/`unsafe-eval` masih hidup — sisa risiko XSS direduksi binding parameter Eloquent (SQLi probe aman, §7-K) + escaping Blade.

## 6a. Hasil audit API baru (routes/api.php)

| ID | Temuan | Bukti file:line | Bukti runtime |
|---|---|---|---|
| API-1 **[P1]** | Grup `api` **tanpa rate limiter** — blok `$middleware->group('api', [...])` dgn baris throttle dikomentari; blok tersebut bahkan ter-duplikasi 3× | `bootstrap/app.php` (group('api')), `routes/api.php:27-62` | 7× POST `/api/auth/token` password salah → `401,401,…` **tanpa 429** (bandingkan login web yang terkunji ke-5). Brute force token API tak terbatas. |
| API-2 **[P1]** | **Nol RBAC di API**: satu token role apa pun (termasuk non-kasir) membaca laporan kas (arus+nominal), piutang seluruh konsumen (nama+utang), ledger pembayaran penuh, SPK seluruh divisi, stok + `stok_minimal`, PII konsumen (telepon+alamat) | `routes/api.php:32-62` (hanya `api.auth`); `Api\LaporanController.php:25-66`; `Api\PembayaranController.php:21-49`; `Api\KonsumenController`, `Api\SpkController`, `Api\StokController` tanpa scope role/divisi | Token `des1` (Operator Desain) → **200 pada semua endpoint** ini dengan data nyata. Banda banding: web memberi 403 untuk role sama (§6). |
| API-3 **[P1]** | `POST /api/transaksi` jalur checkout terpisah: tanpa idempotensi (SubmitSekaliJalan tak dipasang grup api), tanpa biaya desain/finishing/diskon/potongan/deposit, dan **tanpa pemotongan stok, kartu stok, HPP, jurnal pada waktu store** (web memotong + membukukan jurnal saat store) | `Api\TransaksiController.php:71-175`; bandingkan `AlurPenuhOrderTest:65-71` (web store 100→95 + kartu + jurnal); `routes/api.php:42` | Dua POST paralel identik → **2 nota** (`INV…-003` id 242, `INV…-004` id 243); stok bahan tetap 90 (bukan 82), kartu OUT hanya dari web → **nota API tercatat tanpa rotasi stok/jurnal**. `ApiPublikTest` bahkan salah klaim dokumen ("tidak ada yang menulis"). |
| API-4 **[P3]** | `GET /api/produk/{id}` mengembalikan baris penuh — tabel `produk` tidak berkolom harga (harga di `setting_harga`) yang bocor: metadata + status, termasuk produk **nonaktif** (index filter Aktif, show tidak). Endpoint `stok` membongkar `stok_minimal` | `Api\ProdukController.php:30-42`; `Api\StokController`; skema `produk` di `2026_02_25_111057` | show produk nonaktif → 200 baris penuh |
| API-5 **[P2]** | Token disimpan **plaintext** (`users.api_token`), boleh lewat query `?api_token=` (mendarat di access-log), tanpa expiry, satu token per user | `ApiAuth.php:24,32`; `Api\AuthController.php:38-51`; migration `2026_11_04_110000` | — (statis) |
| API-6 | **Anti-IDOR pada datanya sendiri**: daftar/detail transaksi API ter-scope `id_user_kasir` pemegang token | `Api\TransaksiController.php:26,53` | (statis) |
| API-7 | **Kesetaraan harga**: API store memakai `HargaService::getHargaEfektif` yang sama dengan web ✓ — hanya itu yang setara; biaya/finishing/diskon tidak ada di API | `Api\TransaksiController` | (statis) |
| API-8 | **Positif**: penomoran nota atomik (`DocumentNumberService` + LAST_INSERT_ID, `DocumentNumberService.php:42`), transaction+`lockForUpdate` dipakai, unique index `nomor_nota` (`2026_02_25_112610:60`), write pembayaran disengaja tak ada di API (komentar `PembayaranController:15-17`) | — | SQLi probe `?id_transaksi=999999 OR 1=1` → 200 data kosong / 404 — aman (binding) |

## 7. Temuan konkurensi HTTP nyata (Gate 4)

Server sementara `:8089` + DB `percetakan_erp_testing`; setup `storage/app/audit/audit_setup.php` (data sintetis; guard anti-oriondb), harness `storage/app/audit/_harness.php` (curl_multi, request paralel sungguhan). Hasil final (arsip `hasil_http.json`):

| Skenario | Hasil | Keputusan |
|---|---|---|
| C. Web store 3-klik paralel (2 dari user sama + 1 user beda, submit_id SAMA, payload identik) | `200 id 240`, `200 id 240 ("ulang":true)` — idempoten per-user; user beda → nota 241 (delta=2) | PASS — kunci per-user by design (`SubmitSekaliJalan` menyertakan `Auth::id()`); dobel lintas-kasir = nota terpisah (disengaja) |
| D. API store dobel paralel (token kasir2, payload identik) | **2 nota (242, 243)** tanpa idempotensi; stok & jurnal tidak tersentuh | **GAGAL → temuan API-3** |
| E. Bayar dobel paralel oleh pemilik sesi kas (submit_id beda) | **1 baris pembayaran**, Lunas; penantang tertolak "Gagal: Pembayaran melebihi sisa tagihan. Sisa tagihan: Rp 0." | PASS — guard saldo + `lockForUpdate` + serialisasi sesi |
| E'. Bayar oleh bukan pemilik sesi kas | ditolak, flash "Anda tidak dapat melakukan transaksi. Cek kembali saldo kas kasir Anda…" | PASS — pembayaran tunai hanya pemilik sesi (`KasirController.php:606-608`) |
| F. Terima dobel paralel (2 user) | `status_order` final `Diterima Kasir` sekali; kedua 302 | PASS — guard status |
| G. Ambil SPK dobel paralel | `status_spk` final `Desain` sekali (UPDATE kondisional atomik) | PASS |
| H. Stok terakhir (stok=1; dua nota qty 1, submit_id beda) | keduanya 200, stok `1→-1`, 2 entri kartu OUT konsisten | Sesuai desain rework "stok kurang tidak menghalangi" (kebijakan sudah disetujui sebelumnya) — tidak ada korupsi data |
| I. Closing kas dobel (owner + admin) | `status_lock='closed'` sekali, `saldo_akhir=50000` sekali; percobaan kedua error | PASS |
| J. Brute force /login sesi tamu segar | percobaan 1-4 bersih; **5-7 memunculkan "Terlalu banyak percobaan login"** | PASS (limiter 5/menit; respons back()+pesan, bukan 429) |
| K. SQLi probes API | 200 data kosong / 404 "Transaksi tidak ditemukan." — tidak ada error/kebocoran | PASS (binding) |
| B2. Web RBAC kasir tanpa permission | 403 konsisten di 3 area | PASS |
| Rekonsiliasi akhir | `debet−kredit = 0`; kartu stok ↔ stok konsisten (90/10; −1/2) | PASS untuk jalur web; jalur API tidak menyentuh buku sama sekali (API-3) |

**Catatan metodologis:** seluruh pengujian memakai DB testing; `oriondb` tidak pernah dimutasi (satu skrip debug pernah salah koneksi dan **hanya membaca**, dikoreksi segera). Percobaan uji awal yang salah target (kas milik kasir1 dibayar admin) justru memberi bukti tambahan guard kepemilikan kas (skenario E').

## 8. Temuan PWA yang belum selesai (Gate 5) — tetap pekerjaan terbuka

| ID | Temuan | Bukti | Rencana |
|---|---|---|---|
| P1 | Gambar order masuk CacheStorage — `isAsetStatis()` mencocokkan semua pathname berakhiran ekstensi gambar tanpa mengecualikan berkas privat | `PwaController.php:102-105` | Patch: blokir pathname yang memuat `/storage/` (mencakup dua bentuk: route `/grafikaorion/storage/...` dan `asset('storage/...')`) |
| P2 | Kunci cache fallback `orion-pwa-v1` (app.version NULL) | `PwaController.php:143` | Kunci eksplisit `orion-pwa-v2` + sapu cache lama (teknik sudah ada di :97) |
| P0 | HTTPS LAN belum aktif (HTTP 192.168.1.17) | `.env` APP_URL + audit seri awal | mkcert + CA ke HP — hanya dengan persetujuan; `SESSION_SECURE` menyala bersamaan HTTPS |
| — | Aksesibilitas 0,85 (kontras/label) | Lighthouse | Patch CSS/label, mengantre persetujuan |

Instalan PWA di perangkat Android/iOS nyata: **NOT TESTED** (menunggu perangkat dari owner).

## 9. Daftar bug (konsolidasi)

**P0**
1. HTTPS LAN belum aktif → semua sesi (termasuk API token plaintext, API-5) berpindah sebagai teks polos di jaringan lokal. Bukti `.env` APP_URL. Paket mkcert siap, menunggu persetujuan.

**P1**
2. API-1 — API tanpa rate limiter (brute force `/api/auth/token` tak terbatas). `bootstrap/app.php` + runtime 7×401.
3. API-2 — API tanpa RBAC: token role mana pun membaca data lintas divisi (laporan/pembayaran/piutang/konsumen/SPK/stok). Runtime terbukti.
4. API-3 — API store membuat nota tanpa rotasi stok/kartu/HPP/jurnal dan tanpa idempotensi → ketidaksetaraan checkout API vs web; nota masuk produksi tanpa pergerakan persediaan/buku. Runtime terbukti.

**P2**
5. API-5 — token plaintext + `?api_token=` di URL + tanpa expiry.
6. PWA P1 & P2 (§8).
7. `SESSION_SECURE` dinyalakan bersamaan HTTPS (satu paket dengan P0 #1).

**P3**
8. API-4 — show produk membocorkan metadata + produk nonaktif; endpoint stok membongkar `stok_minimal`.
9. `bootstrap/app.php` blok `group('api')` ter-duplikat 3× (higiene berkas; blok akhir menang, mudah keliru saat edit).
10. `ApiPublikTest` docblock salah klaim ("tidak ada yang menulis") + coverage `POST /api/transaksi` nol.
11. CSP masih `unsafe-inline`/`unsafe-eval` (tercatat jujur di kode; perbaikan bertahap).

**Positif terkonfirmasi** (tanpa aksi): idempotensi web per-user; guard bayar/terima/SPK/closing; limiter login; penomoran atomik; jurnal seimbang; CSP; SQLi aman; web RBAC ketat; guard kepemilikan sesi kas.

## 10. Rencana patch — status pelaksanaan (per 22-10-2026)

| # | Item | Status |
|---|---|---|
| 1 | **API-1 limiter** — `api-token` 5/menit (username+IP, mirroring `/login` web) + `api` 60/menit per IP untuk seluruh endpoint ber-token; throttle dipasang SEBELUM identitas sehingga menyapu banjir token salah/berbahaya | ✅ diterapkan di branch `fix/api-rbac-rate-limiter-20261022` — bukti: `ApiRateLimitTest` (brute ke-6 → 429; ke-61 → 429; username lain di IP yang sama tak ikut terkunci) |
| 2 | **API-2 RBAC** — middleware `ApiRbac` fail-closed: peta endpoint→id_menu selaras `menu_access.php` (sumber kebenaran SAMA: `Izin::boleh`, termasuk aturan hanya-lihat & syarat view untuk aksi tulis); endpoint belum dipetakan = 403; Administrator lolos (jalur pemulihan) | ✅ diterapkan di branch yang sama — bukti: `ApiRbacTest` (token tanpa hak ditolak di semua endpoint; kasir diterima di modulnya dan ditolak di laporan kas/piutang/stok/SPK/KPI) |
| 3 | **API-3 kesetaraan checkout** (idempotensi + stok/kartu/HPP/jurnal + finishing/diskon) | ⏳ tahap berikutnya (disetujui owner) |
| 4 | **API-5** token hash + expiry + rotasi + hapus `?api_token=` | ⏳ menyusul (wajib persetujuan owner: regenerasi semua token) |
| 5 | **PWA** guard `/storage/` + kunci `orion-pwa-v2` | ✅ sudah merge ke main (`4caacd7a`, PR #1) — suite 1361/1361 pasca-merge |
| 6 | Perapihan: docblock `ApiPublikTest`, block produk nonaktif, narrowing stok | ⏳ menyusul |

**Catatan kunci:** `ORION_PUBLIC_API_ENABLED` tetap tidak di-set → `orion_api.enabled=false`; seluruh `/api/*` masih tertutup fail-closed 503 (gate berjalan lebih dulu dari throttle/RBAC). Patches #1–#2 SAMA SEKALI tidak membuka jalur API baru — hanya menyiapkan lapisan yang benar ketika API dipersilakan.

## 11. Hasil PASS / FAIL / BLOCKED / NOT TESTED

- **PASS**: suite penuh serial 1372/1372 pasca-patch RBAC+limiter (6.168 assertion, 540s); 1361/1361 pasca-merge PR #1; skenario C & E..K di §7; RBAC web; limiter login; konsistensi stok & jurnal jalur web; SQLi aman; smoke PWA live Apache (sw.js kunci v2 + guard `/storage/` + hormati Cache-Control, manifest & login 200).
- **FAIL (temuan audit, bukan error test)**: API-3 (checkout API tidak setara web) — §6a. API-1 & API-2 TERSISIH diperbaiki di branch (§10).
- **BLOCKED**: —
- **NOT TESTED**: perangkat nyata Android/iOS (instalan PWA, perilaku cache privat di device); akun berbeda di perangkat yang sama; uji per-role UI selain Administrator (tidak ada kredensial role lain); benchmark dataset ukuran besar.

## 12. Perubahan yang memerlukan persetujuan operator

- **Sudah dieksekusi atas persetujuan owner**: PR #1 bergabung ke main (Tahap 1-2: smoke login/Order/bayar/SPK/PWA pada jalur terisolasi + PWA live Apache read-only); patch API-1 & API-2 limiter/RBAC di branch `fix/api-rbac-rate-limiter-20261022` (menunggu review/merge owner — TIDAK merge otomatis).
- **Menunggu keputusan owner**: patch API-3 (kesetaraan checkout), API-5 (token hash — menghapus `?api_token=` + regenerasi token), item §10 #6.
- Keputusan kebijakan ketat Jersey (tetap dulu sampai owner memutuskan).
- Pembersihan lanjutan: arsip `_scan_*`/`REPORT_*` sisa di repo, dan arsip hasil audit `storage/app/audit/*` (lokal, di-ignore git).

---

## Lampiran A — Jawab pertanyaan terbuka: "apa yang perlu ditambahkan untuk menyentuh ERP kompleks & profesional"

Kandidat (HANYA kandidat — menunggu keputusan owner, tidak dikerjakan tanpa arahan):

1. **Pusat approval universal** (edit nota, pembelian, kas kecil, diskon khusus) — satu antrean persetujuan terpusat + notifikasi, memperluas mekanisme `penjualan.edit-approval` yang sudah ada.
2. **AR Aging & penagihan** — umur piutang per konsumen (0-30/30-60/60+) + pengingat berkala; kerangka halaman piutang sudah ada, pengelompokan umurnya belum.
3. **Stock opname ber-workflow** — sesi penyesuaian dengan alasan selisih + jurnal `akun_selisih_persediaan` (akun sudah tersedia dari fitur #17).
4. **Multi lokasi/gudang** — stok saat ini satu gudang; pertumbuhan order multi-lokasi akan membutuhkannya.
5. **Voucher jurnal manual dengan penyetujuan + audit trail** (fungsional akun sudah disiapkan fitur #17; proses JV saat ini masih manual).
6. **Budget vs realisasi** per periode/kategori (biaya produksi, overhead mesin, waste/rework — akun waste sudah disiapkan untuk itu).
7. **Kapasitas mesin & penjadwalan produksi** (level manufaktur profesional): antrean SPK, prioritas, jadwal riil per mesin.
8. **UI matriks role-permission** berbasis halaman web (saat ini hak ditanam langsung ke database; audit membuktikan role tanpa permission terkunci penuh → perlu wizard pembuatan role).
9. **Webhook/queue untuk API**: retry, dead-letter, daftar event (nota, bayar, SPK selesai) — memperluas fitur #4.
10. **Perpajakan & faktur pajak korporat** tertata di setting_harga/eq nota — jurnal otomatis mengikuti.

Prioritas saran bila owner memilih: 1 (approval center), 2 (AR aging), 3 (stock opname), 8 (UI izin) — empat area ini menutup risiko operasional terbesar dengan usaha penerapan paling kecil.
