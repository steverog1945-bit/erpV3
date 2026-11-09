# AUDIT TOTAL ERP PERCETAKAN

Tanggal: 2026-09-30. Dasar: kode di `main`, database `oriondb` (nyata), `percetakan_erp_testing` (uji), server Apache/MySQL lokal.
Pelengkap: `docs/AUDIT_FASE0.md` (baseline), `anti-slop/audit-001-2026-09-30.md` (12 temuan gaya tulisan/UI).
Legenda: **SELESAI** = diperbaiki + dites. **TERBUKA** = butuh keputusan/tindakan pemilik. **KEEP** = benar, jangan diubah. **UNKNOWN** = tidak bisa dibuktikan dari sini.

## 1. Ringkasan eksekutif
- 7 temuan kritis/tinggi ditutup (rahasia bocor, over-bayar Jersey, stok kembali dobel, nota dobel di POS, eskalasi hak akses, unggahan HTML, FK hilang).
- Alur keuangan yang bolong ditutup: uang nota batal (jadi saldo deposit), serah terima nota belum lunas (wajib alasan), maker-checker pada persetujuan.
- Tes: 433 lulus / 1.997 assertion.
- Yang masih berisiko tinggi **bukan kode**, tetapi operasional: backup otomatis tidak pernah berjalan, MySQL terbuka ke LAN, tidak ada salinan di luar server.

## 2. Matriks cakupan permintaan awal
| Permintaan | Status | Bukti |
|---|---|---|
| Menu Jasa tidak bisa edit/simpan | SELESAI | prefix `jasa-harga`, `finishing-harga`, `setting-harga-tier` belum di `menu_access` (403 fail-closed); dipetakan |
| Jasa Jahit Borongan tidak bisa edit/simpan | SELESAI | `jasa-jahit => [68]`; format qty diperbaiki |
| Landing login gaya opencode | SELESAI (tampilan main dipertahankan sesuai perintah) | `login.blade.php` |
| Sidebar seperti gambar acuan | SELESAI | `layouts/app.blade.php` |
| Logout tidak mengingat halaman terakhir; login ke Dashboard | SELESAI | halaman tujuan lama dibersihkan |
| Database, FK, index | SELESAI | 35 dari ~116 FK ada; 114 dipulihkan via migrasi korektif |
| Stok, reversal, kartu stok | SELESAI + KEEP | snapshot, consume-once; rantai kartu stok konsisten, tidak ada stok negatif |
| Akuntansi | SELESAI | semua jurnal lewat `AccountingService`; debet = kredit; kredit nota batal |
| POS | SELESAI | `sekali.jalan` (anti nota dobel), audit pembayaran |
| Order Jersey (60 pemain, ukuran kustom) | SELESAI | tes HTTP; ukuran dinamis dari master |
| Keamanan (auth, otorisasi, upload, header) | SELESAI | bagian 4 |
| Audit trail | SEBAGIAN | pembayaran, master, 15 model; celah di bagian 6 |
| Concurrency | KEEP | `lockForUpdate` + cek status di dalam transaksi |
| Performa | TERBUKA ringan | bagian 7 |
| Backup/recovery | TERBUKA | restore teruji; penjadwal belum terpasang |
| LAN produksi | TERBUKA | bagian 8 |
| Frontend/keyboard | SEBAGIAN | bagian 9 |
| Tes | SELESAI | bagian 10 |
| Bahasa/UI "tidak generik" | AUDIT SELESAI, eksekusi menunggu pilihan | `audit-001` |
| Pemakaian skill | dipakai | antislop audit, laravel-best-practices |

## 3. Peta alur bisnis
| Alur | Pemicu | Data dibaca | Data ditulis | Stok | Jurnal | Audit | Pelaku | Titik gagal |
|---|---|---|---|---|---|---|---|---|
| Penjualan POS | Kasir simpan nota | produk, harga, BOM | transaksi, detail, snapshot bahan | kurang saat produksi | Dr Piutang/Cr Pendapatan (+HPP) | ya | Kasir | kirim ulang (ditutup `sekali.jalan`) |
| Pembayaran | Kasir bayar | transaksi | pembayaran_riwayat, deposit (bila Deposit) | - | Dr Kas/Bank, Cr Piutang | ya | Kasir | butuh kas aktif milik sendiri |
| Produksi/SPK | Operator | detail | status_spk, kartu_stok | ya | HPP | ya | Operator | selesai lalu batal (dijaga) |
| Collecting | Serah barang | transaksi | pengambilan | - | - | ya | Kasir | belum lunas: wajib alasan |
| Batal nota | Ajukan + setujui | transaksi | status, stok, jurnal balik, deposit kredit | dikembalikan (snapshot) | balik + Dr Piutang/Cr Hutang Deposit | ya | Kasir ajukan, Admin setuju | uang menggantung (ditutup) |
| Edit nota | Permintaan + terapkan | nota_edit_request | nota lama batal | dikembalikan | balik + kredit | ya | Kasir/Admin | sama dengan batal |
| Pembelian/kedatangan | Gudang | bahan, supplier | pembelian_bahan, kartu_stok | naik | Dr Persediaan/Cr Kas/Hutang | ya | Gudang | `id_kedatangan` NOT NULL vs SET NULL (TERBUKA) |
| Order Jersey | Kasir jersey | master jersey | order, line, item, pembayaran | per bahan | JerseyAccountingService | ya | Kasir | over-bayar (ditutup) |
| Deposit | Setoran/pemakaian | konsumen_deposit | ledger + jurnal | - | Dr Kas/Cr Hutang Deposit | ya | Kasir | belum ada pengembalian tunai (TERBUKA) |
| Kas kecil | Pengajuan | - | kas_kecil | - | ya | ya | Kasir/Admin | maker-checker |
| Tutup kas/periode | Admin | jurnal | periode | - | - | ya | Admin | `PeriodeAkuntansiTertutupException` |

## 4. Keamanan
| # | Temuan | Status |
|---|---|---|
| S1 | `.env`/rahasia terbaca via web | SELESAI |
| S2 | Eskalasi hak lewat Data User (`Izin::bolehKelolaRole/User/berikanRole`) | SELESAI |
| S3 | Unggahan HTML menyamar `.pdf` (XSS tersimpan) | SELESAI (`BerkasAman` + `.htaccess`) |
| S4 | Pesan galat teknis bocor ke pengguna | SELESAI (`PesanError`) |
| S5 | Rute tanpa pemetaan menu | KEEP (fail-closed) |
| S6 | Header keamanan/CSP | SELESAI |
| S7 | Login: rate limit, sesi | KEEP |
| S8 | Berkas unggahan dilayani tanpa login (~20 tempat) | TERBUKA: butuh disk privat + rute unduh berizin |
| S9 | MySQL mendengar `0.0.0.0:3306`, akun root tanpa sandi | TERBUKA: `bind-address=127.0.0.1`; akun aplikasi khusus |
| S10 | `APP_ENV=local`, `LOG_LEVEL=debug` | TERBUKA: ubah setelah data uji dibersihkan |
| S11 | Rotasi `APP_KEY` | TERBUKA (memutus sesi/data terenkripsi lama; jadwalkan) |

## 5. Alur yang tidak sesuai ERP profesional (sudah dibenahi)
1. Uang nota batal menggantung -> saldo deposit + jurnal reklasifikasi.
2. Barang belum lunas keluar tanpa jejak -> konfirmasi + alasan (server), tercatat di audit.
3. Pembuat dan penyetuju bisa orang sama -> maker-checker di `ApprovalService`.
4. Harga POS ditimpa tanpa jejak -> price list vs harga dipakai dicatat.
5. Master (bank, golongan) bisa dihapus meski dipakai -> diblokir.

Belum: pengembalian uang tunai dari deposit; penyeragaman metode bayar (`Debit` hanya kasir, `QRIS` hanya Jersey).

## 6. Audit trail: celah tersisa
Sudah: pembayaran POS, master utama, 15 model. Celah kecil (RENDAH): sebagian jalur status SPK/produksi lewat `DB::table` belum memanggil `AuditLogger`, dan `StockOpname` belum diperiksa tuntas. Perbaikan: panggil `AuditLogger::master`/`log` di jalur itu; tanpa perubahan skema.

## 7. Performa
- 49 `paginate()` dari 168 `->get()` di controller. Kandidat: `Jersey/JerseyOrderController:53` (semua konsumen ke dropdown), `KonsumenTimelineController` (semua nota satu konsumen). Risiko sedang saat data ribuan. Perbaikan: pencarian AJAX/pagination.
- OPcache mati, `date.timezone=Europe/Berlin` di php.ini (koneksi DB dikunci `+07:00`, data aman). Perbaikan konfigurasi, bukan kode.
- `EXPLAIN` pada data produksi tidak dilakukan: UNKNOWN untuk query lambat.

## 8. LAN/produksi
`php artisan produksi:cek` (hanya baca): 0 gagal, 7 waspada (penjadwal, backup tanpa salinan luar, bind MySQL, root tanpa sandi, APP_ENV, LOG_LEVEL, OPcache).
**Backup otomatis tidak pernah berjalan.** Pasang: PowerShell Administrator, lalu `powershell -ExecutionPolicy Bypass -File scripts\pasang-penjadwal.ps1`. Restore sudah teruji (105 tabel, 2.687 baris identik).

## 9. Frontend
Klik ganda pada form biasa (bukan POS) bergantung pada penjagaan server: aman, tetapi pengguna bisa melihat pesan "sudah diproses". Rekomendasi (RENDAH): nonaktifkan tombol submit setelah klik secara global. Navigasi keyboard: UNKNOWN (belum diuji manual).

## 10. Matriks tes
| Domain | Tes | Celah |
|---|---|---|
| Keamanan/akses | matriks rute, eskalasi hak, upload, login | - |
| POS/pembayaran | sekali jalan, audit, harga | pembayaran paralel sungguhan (butuh 2 proses) |
| Stok | snapshot, reversal ganda, konversi | multi-gudang tidak ada (desain) |
| Akuntansi | jurnal seimbang, kredit nota batal | tutup periode + koreksi |
| Jersey | HTTP 60 pemain, ukuran, nomor | - |
| Collecting | 4 kasus belum lunas | - |
| Backup | restore diuji manual | belum ada tes otomatis |

## 11. Matriks risiko akhir
| Kelas | Item |
|---|---|
| **MUST FIX** (operasional, pemilik) | pasang penjadwal backup; `bind-address`; salinan backup di luar server |
| **SHOULD FIX** | disk privat untuk unggahan (S8); akun DB khusus; APP_ENV/LOG_LEVEL produksi; pengembalian tunai deposit; keputusan stok per gudang; penyeragaman metode bayar; audit jalur SPK/opname |
| **DEFER** | rotasi APP_KEY; pagination dropdown konsumen; migrasi bertanggal 2026-10 yang terurut di masa depan; `count()+1` referensi pembayaran (dijaga lock nota) |
| **KEEP** | validasi ukuran Jersey dinamis, reversal snapshot, penomoran atomik, fail-closed menu, void via approval, migrasi "duplikat" yang dibungkus `hasTable` |

## 12. Roadmap
| Fase | Isi | Status |
|---|---|---|
| 0 | Baseline, backup, git tag | SELESAI |
| 1 | Rahasia, hak akses, upload, FK, jasa/jahit | SELESAI |
| 2 | Integritas alur: stok, Jersey, POS, batal nota, collecting, maker-checker | SELESAI |
| 3 | Audit trail master + pembayaran | SELESAI (celah kecil bagian 6) |
| 4 | Operasional: penjadwal, bind MySQL, salinan luar | MENUNGGU PEMILIK |
| 5 | Penyimpanan unggahan privat (`BerkasOrderController`, view yang memakai `asset('storage')`; regresi: tampilan gambar) | DIREKOMENDASIKAN |
| 6 | Deposit: pengembalian tunai; metode bayar seragam (migrasi enum + layar; tes akuntansi) | DIREKOMENDASIKAN |
| 7 | Antislop `audit-001` (nomor 1, 2, 10 dulu) | MENUNGGU PILIHAN |
| 8 | Performa data besar + stok per gudang | DITUNDA sampai ada data/keputusan |

## 13. Keputusan yang masih di tangan pemilik
1. Jalankan `pasang-penjadwal.ps1`. 2. `bind-address=127.0.0.1` di `my.ini`. 3. Lokasi salinan backup luar server. 4. Nomor `audit-001` yang dikerjakan. 5. Perlu pengembalian tunai deposit? 6. Stok per gudang atau tetap global? 7. `pembelian_bahan.id_kedatangan`: NOT NULL atau SET NULL? 8. Hapus worktree lama.

## 14. Pembaruan penutup (2026-09-30, setelah penjadwal dipasang)
| Item | Hasil |
|---|---|
| Penjadwal backup | Terpasang oleh pemilik. Backup terakhir tercatat 3 jam sebelum pemeriksaan (`produksi:cek` OK) |
| Salinan backup kedua | SELESAI: disk `salinan_backup` -> `D:/BackupERP` (`BACKUP_SECONDARY_DISK`, `BACKUP_SALINAN_PATH` di `.env`), retensi sama dengan lokal. Terbukti: backup manual menghasilkan `offsite_status=Sukses`. Catatan: D: adalah drive lain di komputer yang sama; untuk perlindungan penuh arahkan `BACKUP_SALINAN_PATH` ke komputer lain di LAN (`\NAMA-PC\BackupERP`) atau disk eksternal |
| `bind-address` MySQL | Skrip `scripts/kunci-mysql-lokal.ps1` siap (mencadangkan `my.ini`, mengubah satu baris). Menunggu dijalankan pemilik sebagai Administrator lalu restart MySQL; ini pengaturan sistem, sengaja tidak diubah otomatis |
| LOG_LEVEL | `warning` |
| APP_ENV | tetap `local` (masih ada data uji; `production` memblokir perintah data demo). Ubah bersama pembersihan data uji |
| Pengembalian tunai deposit | SELESAI: tipe `Pengembalian`, hanya Administrator, alasan wajib, tidak boleh melebihi saldo, jurnal Dr Hutang Deposit / Cr Kas atau Bank, audit; migrasi sudah dijalankan; 4 tes |
| Audit SPK/produksi | SELESAI: input jumlah cetak, selesai, dan batal kini tercatat di audit. Opname: status lewat model `Auditable`, perubahan stok tercatat di kartu stok dengan `created_by` (cukup) |
| Berkas privat (S8) | SELESAI: desain pelanggan, bukti bayar, bukti kas kecil, bukti serah terima, berkas order hanya untuk pengguna login (`BerkasPrivatController`, aturan di `public/.htaccess`); logo dan gambar postingan tetap publik. Terverifikasi di Apache: tamu dialihkan ke login, logo 200. Tes menemukan dan memperbaiki satu cacat: `HeaderKeamanan` menimpa CSP respons berkas |
| `pembelian_bahan.id_kedatangan` | Diputuskan NOT NULL (kode selalu mewajibkan). FK `RESTRICT` dipasang lewat migrasi korektif; FK kini 115 |
| Klik ganda | Penjaga global di layout: tombol submit dinonaktifkan sementara setelah kirim; server tetap penjaga utama |
| Antislop 1 dan 10 | SELESAI: tanda pisah panjang (16) diganti; judul "Opps" (34) diganti judul yang menyebut kejadiannya. Nomor 2 tidak berlaku pada login versi main. Nomor 3 sampai 9, 11, 12 tidak disentuh (menunggu pilihan) |
| Metode bayar berbeda antarmodul | Diputuskan tidak diubah: `Debit`/`Deposit` khas kasir, `QRIS`/`Lainnya` khas Jersey; non-tunai sama-sama masuk Bank. Menyeragamkan berarti mengubah laporan closing kas |
| Stok per gudang | TIDAK dikerjakan: perubahan desain besar (kartu stok, opname, semua mutasi), butuh keputusan bisnis |
| Dropdown semua konsumen | Ditunda: aman untuk ribuan baris |
| Tes | 441 lulus, 2.019 assertion |

## 15. Catatan akhir brief awal dan SweetAlert (sesi lanjutan)
| Item | Hasil |
|---|---|
| Ikon SweetAlert menumpuk dengan tulisan | Akar: `padding-left` judul dibatalkan oleh `padding: 0` di bawahnya, sementara ikon `position:absolute` di kiri. Popup kini grid dua kolom (ikon kolom 1, judul dan isi kolom 2); tanpa ikon atau tanpa judul tetap rapi; toast tidak berubah. Diukur di browser: ikon (kanan 438) tidak lagi menyentuh judul (kiri 448) |
| Semua alert | 13 `confirm()` bawaan peramban di 12 view diganti dialog SweetAlert seragam lewat atribut `data-konfirmasi` (penangan global di layout). Dialog hapus/batal memakai tombol merah dan fokus awal di Batal |
| Jasa dan Jasa Jahit bisa diedit/disimpan role non-admin | Terkunci dengan tes regresi (tambah, ubah, tier harga; dan role tanpa izin edit tetap 403) |
| Logout tidak mengikat halaman terakhir; login ke Dashboard | Terkunci dengan tes regresi (`url.intended` dibuang saat login dan logout) |
| Login profesional, sidebar seperti acuan, skill dipakai | Sudah (lihat bagian 2); login versi main dipertahankan sesuai perintah |

## 16. Penutup operasional
| Item | Hasil |
|---|---|
| `bind-address` MySQL (H-4 / S9) | SELESAI. Layanan Windows `ORION_MariaDB` di-restart pemilik; terverifikasi `127.0.0.1:3306` (bukan lagi `0.0.0.0`), aplikasi tetap 200. Catatan: MariaDB di server ini berjalan sebagai layanan Windows, bukan lewat XAMPP Control Panel |
| `produksi:cek` | 0 gagal, 4 waspada, 13 sesuai |
| `config:cache` | SENGAJA tidak dijalankan di komputer ini: konfigurasi ter-cache mengalahkan `phpunit.xml`, sehingga tes (RefreshDatabase) akan berjalan di database asli dan mengosongkannya. Jalankan hanya di server yang tidak dipakai menjalankan tes. Di server pakai `php artisan app:config-cache` (menulis atomik + menolak konfigurasi yang menunjuk database uji) - `config:cache` biasa menulis berkas tanpa atomik dan sempat membuat seluruh halaman error 500 ("No application encryption key has been specified") |
| Antislop 3 | SELESAI: 8 ikon sihir/petir diganti ikon yang menggambarkan isinya |
| Antislop 7 | Diperiksa: panah yang tersisa menunjukkan arah nyata (tautan lanjut, halaman berikut); dibiarkan |

## 17. Landing login dan antislop (seluruh nomor)
| Nomor | Hasil |
|---|---|
| Landing login | BARU di `resources/views/login.blade.php`: token visual opencode (kanvas kertas, garis 1px, huruf mono untuk judul/label, sudut 4px, satu aksen), form masuk di kanan, lalu bagian Modul, Alur satu nota, Akses, dan kaki berisi alamat dari Pengaturan Aplikasi. Isi hanya modul yang memang ada; tanpa angka atau testimoni karangan. Di HP form tampil paling atas. Diperiksa di browser lebar 1280 dan 375 (tanpa gulir mendatar) |
| 1, 3, 10 | Selesai sebelumnya |
| 2 | Tidak berlaku (tidak ada logo karangan; tanpa logo hanya nama aplikasi) |
| 4 bayangan berwarna | 31 pemakaian diganti bayangan netral kecil |
| 5 radius, 6 huruf kapital renggang, 9 teks mikro | Sudah ditangani terpusat di `resources/css/ui-refresh.css` milik main (radius 8px, `text-transform: none`, tracking 0, ukuran minimal 13px); tidak perlu diubah per berkas |
| 7 panah | Yang tersisa menunjukkan arah nyata |
| 8 badge pil | Huruf kapitalnya sudah hilang lewat aturan terpusat; bentuk `rounded-full` dipertahankan sesuai pengecualian yang ditulis di CSS main |
| 11 indigo di mana-mana | 16 ikon dan 46 lencana/wadah ikon statis dinetralkan ke abu. Tombol utama, tautan, menu aktif, fokus, dan semua keadaan yang diatur lewat kondisi Blade atau JS tidak disentuh |
| 12 blok fitur kembar di login | Diganti daftar bergaris di landing baru |

## 18. Halaman depan ala blog (menggantikan landing bagian 17)
| Item | Hasil |
|---|---|
| Halaman depan `/` | Daftar tulisan publik: satu tulisan utama, saring kategori, cari, dan "Muat tulisan lain" tanpa muat ulang (AJAX, tetap jalan tanpa JS). Isi mengikuti jumlah tulisan yang diterbitkan |
| Baca tanpa login | `/baca/{slug}` (`PublikController`). Hanya tulisan berstatus terbit DAN dicentang "Tampil di halaman depan" (kolom baru `posts.tampil_publik`, bawaan tidak). Draft, terjadwal, dan tulisan internal membalas 404 |
| Masuk | Dialog dari tombol Masuk; terbuka sendiri bila login gagal atau baru ditolak dari halaman dalam. Pengguna yang sudah login tetap diarahkan ke Dashboard |
| Gaya | Judul serif (Playfair Display, sudah ada di server), antarmuka Inter, tanpa huruf mono, gradien, kaca, atau bayangan berwarna |
| Gerak (pedoman emil-design-eng) | Hanya transform dan opacity, di bawah 300ms, ease-out `cubic-bezier(0.23,1,0.32,1)`; tombol `scale(0.97)` saat ditekan; dialog mulai dari `scale(0.96)`; kartu baru naik 10px berurutan 40ms; hover hanya di perangkat ber-pointer; `prefers-reduced-motion` dihormati |
| Tes | `HalamanDepanPublikTest` (7 kasus) + matriks rute diperbarui |

## 19. Audit performa terukur (menggantikan perkiraan di bagian 7)
Cara: 107 halaman GET (tanpa parameter) dijalankan sebagai Administrator terhadap `oriondb`, hanya membaca, sambil menghitung query.
| Temuan | Sebelum | Sesudah | Perbaikan |
|---|---|---|---|
| TINGGI: composer `view('*')` di `AppServiceProvider` menjalankan query kas aktif untuk setiap view dan komponen Blade | Price List 329 query (316 di antaranya query yang sama), Kartu Stok 290, Master Jersey 191, Karyawan 131, Produk 112 | halaman terberat 53 query | hasil disimpan di atribut request (sekali per permintaan, tetap segar di permintaan berikutnya); tes `PerformaQueryTest` |
| SEDANG: `/pembelian-bahan` menghitung sisa hutang dengan satu `SUM` per faktur berhutang (tumbuh tanpa batas) | N query | 1 query | satu `SUM ... GROUP BY` |
| RENDAH: `/accounting-period` 12 query (satu per bulan) | terbatas 12 | dibiarkan | - |
Belum diukur: halaman ber-parameter (detail nota, cetak, ekspor) dan waktu query pada data besar (data sekarang masih kecil, `EXPLAIN` belum bermakna).

## 20. Audit tutup buku dan tanggal dokumen
| Severity | Temuan | Dampak | Perbaikan |
|---|---|---|---|
| TINGGI | Periode berjalan atau bulan depan bisa ditutup | Semua jurnal hari itu ditolak: kasir tidak bisa menyimpan nota atau menerima pembayaran | Hanya bulan yang sudah berakhir yang bisa ditutup |
| SEDANG | Tutup periode tanpa pemeriksaan | Bulan terkunci padahal sesi kasnya belum di-closing atau jurnalnya timpang | Ditolak bila ada sesi kas terbuka dari periode itu atau debet tidak sama dengan kredit |
| SEDANG | Tanggal dokumen bebas (kas kecil, setoran deposit, pembayaran jersey, kedatangan bahan, pembelian dan pembayaran hutang, catatan jahit), padahal jurnal selalu bertanggal hari ini | Dokumen bisa dimundurkan ke bulan yang sudah ditutup (laporan operasional bulan itu berubah setelah tutup buku) atau diberi tanggal masa depan | Aturan `App\Rules\TanggalTransaksi`: tidak melewati hari ini dan tidak di periode tertutup, dipasang di 8 titik |
| KEEP | Semua jurnal lewat `postJournal` yang menolak periode tertutup; buka/tutup periode tercatat di audit (`Auditable`); `audit:integritas` lulus di `oriondb` | - | - |
Tes: `TutupPeriodeKontrolTest` (6 kasus). Tidak diubah: tanggal dokumen non-keuangan (penawaran, PR, PO, opname, promo).

## 21. Audit konkurensi dengan proses sungguhan, SPK jersey, kembalian
| Severity | Temuan | Bukti | Perbaikan |
|---|---|---|---|
| KRITIS | Pelunasan ganda: beberapa pembayaran bersamaan untuk nota yang sama semuanya tercatat | `PembayaranParalelTest`: 6 proses PHP membayar lunas nota Rp 50.000 pada saat yang sama, 6 pembayaran tercatat (Rp 300.000) | Akar: MySQL REPEATABLE READ memotret data pada bacaan pertama transaksi; kunci nota diambil setelah bacaan lain, sehingga "total sudah dibayar" dihitung dari potret lama. Kunci nota kini bacaan pertama, dan koneksi memakai READ COMMITTED (`config/database.php`) |
| KRITIS | Deposit terpakai dua kali lewat dua nota berbeda milik pelanggan yang sama | Uji yang sama: saldo Rp 50.000 terpakai Rp 100.000 | Tertutup oleh READ COMMITTED (kunci pelanggan kini efektif) |
| Catatan | Temuan di bagian sebelumnya bahwa konkurensi "KEEP" hanya benar untuk klik ganda berurutan; tes lama berjalan dalam satu proses sehingga tidak bisa menangkap ini | - | Tes proses paralel masuk suite (3 kasus) |
| Permintaan | SPK jersey: rib dan kerah tercampur | - | Rib dipisah dari kain jersey di tabel bahan (tidak ikut total) dan komponen Rib dinonaktifkan di master; order lama tidak diubah |
| Permintaan | Cetak SPK di bawah 13px | diukur di browser: tidak ada teks di bawah 13px, huruf Inter | `partials/spk-print-style` (berlaku juga untuk SPK cetak reguler) |
| Permintaan | Nota jersey (thermal dan A4) menampilkan estimasi bahan | - | Dihapus dari nota; estimasi hanya ada di SPK |
| Permintaan | Kembalian | Kasir utama sudah menampilkan kembalian, tetapi server MENOLAK uang tunai yang lebih besar dari tagihan | Tunai boleh lebih: yang dicatat sebesar sisa tagihan, kembalian ditampilkan langsung dan di pesan sukses. Non-tunai yang melebihi tetap ditolak. Berlaku di kasir utama dan Catat Pembayaran jersey |

## 22. Audit konkurensi lanjutan dan halaman depan
| Item | Hasil |
|---|---|
| Pelunasan order jersey, 6 proses bersamaan | 1 pembayaran tercatat, jurnal seimbang, piutang nol (LULUS) |
| 6 nota POS bersamaan | 6 nomor nota berbeda, jurnal seimbang (LULUS) |
| Satu `submit_id` dikirim 6 kali bersamaan | 1 nota (LULUS; penjaga memakai cache database yang dipakai produksi) |
| Pengembalian deposit 6 kali bersamaan (saldo 100.000, masing-masing 60.000) | 1 pengembalian, saldo 40.000 (LULUS) |
| Belum diuji paralel | pengurangan stok saat produksi, pembayaran hutang supplier, persetujuan approval |
| Halaman depan | Dialog masuk kini di tengah layar (margin bawaan `<dialog>` dihapus oleh reset CSS). Ditambah: pembuka dengan ajakan WhatsApp, Yang kami kerjakan (8 kelompok dari katalog produk), Cara pesan (4 langkah), Kabar dan catatan, Hubungi kami. Hanya Inter (dan Nunito di isi artikel); tanpa serif dan mono |

## 23. Audit konkurensi: stok, hutang supplier, persetujuan (6 proses bersamaan)
| Skenario | Dengan READ COMMITTED (sekarang) | Dengan pengaturan lama (REPEATABLE READ) |
|---|---|---|
| 6 pelunasan hutang supplier Rp 100.000 untuk satu faktur | Rp 100.000 tercatat, Hutang Usaha nol | **Rp 600.000 tercatat** (temuan KRITIS ketiga dari akar yang sama; tertutup oleh perbaikan bagian 21) |
| 6 penjualan bahan (qty 3) dari stok 100 | stok akhir 82, rantai kartu stok 100, 97, 94, 91, 88, 85 utuh, 6 nomor nota berbeda | lulus (memakai nilai dari baris yang dikunci) |
| Pembatalan nota berbayar disetujui 6 kali bersamaan | dieksekusi sekali: satu kredit deposit Rp 40.000, piutang nol, jurnal seimbang | lulus |
Kesimpulan: semua alur uang yang menghitung total dari tabel lain setelah mengunci (pembayaran nota, deposit, hutang supplier) bergantung pada `isolation_level` READ COMMITTED di `config/database.php`. Jangan diubah; `AlurParalelTest` dan `PembayaranParalelTest` akan gagal bila dikembalikan.
Belum diuji paralel: penyelesaian SPK produksi (pemakaian bahan BOM) dan stok opname.

## 24. Konkurensi stok (BOM dan opname) dan performa halaman detail
| Skenario (6 proses bersamaan) | Hasil |
|---|---|
| 6 nota POS dengan BOM (masing-masing memakai 5 dari stok 100) | stok akhir 70, 6 baris snapshot pemakaian, kartu stok satu rantai tak terputus, jurnal seimbang (LULUS) |
| 3 penjualan bahan + 3 kali persetujuan opname yang sama, serentak | opname dieksekusi sekali, 3 mutasi keluar, kartu stok tetap satu rantai dan ujungnya sama dengan stok bahan (LULUS) |
Pemeriksa baru `assertRantaiKartuStok`: tidak boleh ada dua mutasi yang berangkat dari stok yang sama, dan rantai dari stok awal harus berakhir di stok sekarang.

Performa halaman detail dan cetak (19 halaman, `oriondb`, hanya baca): terberat 35 query (detail nota) dan 31 (detail order jersey); tidak ada query yang berulang lebih dari 3 kali. Tidak ada temuan.

Status audit konkurensi: semua alur uang dan stok yang teridentifikasi sudah diuji dengan proses sungguhan (12 skenario). Yang tersisa di luar konkurensi: keadaan kosong/galat per halaman, navigasi keyboard, dan kecepatan pada data besar.

## 25. Tombol cari cepat hilang pada baris yang ditambahkan
Akar: `partials/quick-picker` memasang tombol hanya saat halaman dimuat (dan 0,3 s / 1,2 s sesudahnya), sehingga `<select>` yang ditambahkan lewat "Tambah Item" tidak pernah mendapat tombol. Ini berlaku di semua form berbaris dinamis, bukan hanya Permintaan Pembelian.
Perbaikan di satu tempat (berlaku untuk semua halaman): pengamat DOM memasang tombol setiap kali ada `<select>` atau `<option>` baru; baris hasil `cloneNode` (yang membawa tombol tanpa penangan klik) dipasang ulang.
Diperiksa di browser: Permintaan Pembelian 1 baris -> 4 baris, 4 tombol, tombol baris baru memanggil pencari; Pesanan Pembelian 1 -> 3, 3 tombol. Penawaran tidak punya dropdown panjang di barisnya.

## 26. Daftar Permintaan Pembelian: isi dan supplier
Kolom baru di daftar: **Isi permintaan** (bahan, qty, satuan; tiga pertama tampil, sisanya dilipat) dan **Supplier**. PR sebelumnya tidak punya data supplier sama sekali, jadi ditambah kolom opsional `purchase_requests.id_supplier_rencana` (migrasi korektif, FK ke supplier, SET NULL) dan isian "Rencana supplier" di form. Bila PR sudah menjadi Pesanan Pembelian, daftar menampilkan supplier PO itu beserta nomornya. Supplier rencana menjadi isian awal saat membuat PO dari PR (bila kosong, tetap memakai tebakan lama dari riwayat pembelian). Tes: `PermintaanPembelianDaftarTest` (5 kasus, termasuk tanpa query per baris).

## 27. Audit modal (semua halaman), penawaran
Cara: 90 halaman dirender sebagai Administrator lalu diurai di browser (193 modal, 323 tombol submit, 482 pemanggilan buka/tutup modal).
| Severity | Temuan | Cakupan | Perbaikan |
|---|---|---|---|
| TINGGI | Tombol "Simpan" di kaki modal berada di luar `<form>`: slot footer komponen `x-modal` dirender sebagai saudara isi, jadi tombol `type="submit"` tidak terhubung ke form mana pun. Diklik tidak terjadi apa-apa; Enter juga tidak mengirim | 38 tombol di 13 halaman: Jasa, Jasa Jahit, Golongan, Satuan, Kategori, Kategori Kas Kecil, Mesin, Merek, Jenis Bahan, Gudang, Karyawan, Akun, Deposit | Satu perbaikan di `components/modal.blade.php`: tombol submit di footer diberi atribut `form=` ke form di isi modal (form tanpa id diberi id). Berlaku otomatis untuk modal baru |
| TINGGI | Master Jasa: dua `<div>` tidak ditutup di modal Tambah dan Edit, sehingga modal Edit dan modal Harga Bertingkat bersarang DI DALAM modal Tambah. Edit tidak pernah tampil; saat Tambah dibuka, modal lain ikut muncul bertumpuk di bawahnya | `/jasa` | 4 `<div>` liar dibuang |
| TINGGI | Master Jasa, modal Edit: biaya operasional dan biaya tinta diisi dengan `formatRupiah("1500.00")` yang membuang titik desimal menjadi 150.000. Setiap kali jasa diedit lalu disimpan, kedua biaya naik 100 kali | `/jasa` | memakai `formatRupiahDariAngka`; pola yang sama di Input Order ikut diganti |
| - | Pemanggilan modal yang targetnya tidak ada | 0 dari 482 | - |
Ini akar keluhan awal "Jasa dan Jasa Jahit tidak bisa edit/simpan": pemetaan izin (403) yang diperbaiki di Fase 1 hanya separuh masalah.
Pemeriksa statis: `scripts/cek-modal.py` (keseimbangan tag di dalam `x-modal`); tes `ModalDanPenawaranTest`.

Penawaran: kolom P dan L hanya tampil untuk produk yang dihitung per ukuran; produk per qty hanya qty, dan ukuran yang ikut terkirim dibuang di server. Cetak penawaran kini berbentuk surat: nomor dan perihal, pembuka, terbilang, ketentuan (disusun dari isi dokumen: masa berlaku, ada tidaknya ukuran dan biaya desain), penutup, dan dua kolom tanda tangan; huruf Inter 13px.

## 28. Audit query berat pada data besar
Cara: salinan `oriondb` di database terpisah `percetakan_erp_beban` diisi data demo lalu digandakan sampai 87 ribu nota, 173 ribu item, 670 ribu baris jurnal, 690 ribu log audit, 650 ribu mutasi kartu stok, 16 ribu order jersey. Semua 159 rute GET (halaman dan endpoint AJAX yang dipanggil modal) dijalankan sebagai Administrator; query di atas 150 ms di-EXPLAIN. `oriondb` tidak disentuh.
| Halaman | Sebelum | Sesudah | Akar dan perbaikan |
|---|---|---|---|
| Audit Bulanan (`/audit-owner`) | 105 detik | 0,5 detik | `DATE(kolom) BETWEEN` di 19 query mematikan indeks. Makro baru `whereTanggalAntara()` (rentang `>=` awal, `<` besok) + indeks `kartu_stok.tanggal` |
| Ekspor CSV Audit Bulanan | 113 detik | 0,5 detik | sama |
| Laporan Pemakaian Bahan | 17,5 detik | < 0,1 detik | sama (DashboardEksekutifService) |
| Kartu Stok | 24,7 detik | 0,7 detik | seluruh kartu stok di-GROUP BY setiap buka halaman; diganti subquery berindeks per bahan |
| Collecting | 3,3 detik | 0,2 detik | JOIN + GROUP BY semua nota untuk paginasi 20 baris dan hitungan "siap diambil" di PHP; diganti subquery per nota dan hitungan di database |
| Dashboard | 1,3 detik | 0,35 detik | JOIN konsumen memaksa pengurutan seluruh nota; ringkasan collecting mengelompokkan semua item |
| Notifikasi (dipanggil tiap 30 detik oleh setiap layar) | hitungan per pengguna | angka dibagi bersama 20 detik; hitungan "siap diambil" di database | `NotifikasiService` |
Sisa di atas 1 detik (4 halaman), dibiarkan dengan alasan: SPK dan Produksi (1,2 sampai 1,9 detik) karena data uji berisi 60 ribu item yang masih berjalan, jauh di atas kenyataan; Neraca (1,2 detik) menjumlah 670 ribu baris jurnal memakai indeks penutup, wajar untuk laporan; `/sinyal` hanya pada permintaan pertama sebelum cache.
Database `percetakan_erp_beban` dibiarkan untuk pengukuran ulang: `python scripts/beban-gandakan.py 10` lalu `DB_DATABASE=percetakan_erp_beban php artisan tinker scripts/beban-ukur.php`. Boleh dihapus kapan saja.

## 29. Kembalian dan pembukuan jersey, nota thermal, audit indeks
**Cacat pembukuan (dilaporkan pemilik).** JO-20260925-001: tagihan Rp 128.640, pelanggan membayar Rp 200.000, yang tercatat di pembayaran, Kas Kecil, dan jurnal Rp 200.000 (dicatat 29-09, sebelum aturan kembalian di bagian 21). Ditemukan sekaligus: pendapatan order itu ditandai terjurnal tetapi jurnalnya tidak ada, sehingga Piutang minus Rp 200.000.
Perbaikan data dengan perintah baru `php artisan jersey:koreksi-pembukuan` (tanpa `--terapkan` hanya pratinjau): pembayaran menjadi Rp 128.640, Kas Kecil Rp 128.640, jurnal koreksi Dr Piutang / Cr Kas Kecil Rp 71.360, jurnal pendapatan Rp 128.640 diposting. Jurnal lama tidak dihapus; setiap koreksi tercatat di audit. Piutang order itu kini nol; `audit:integritas` lulus. `audit:integritas` kini juga memeriksa pembayaran yang melebihi tagihan dan pendapatan jersey yang tidak terjurnal.
**Semua pintu pembayaran dari pelanggan:** kasir utama, pelunasan jersey, dan DP awal jersey mencatat sebesar tagihan; uang yang diserahkan dan kembaliannya disimpan di kolom baru `uang_diterima` dan `kembalian` (tabel pembayaran_riwayat dan jersey_order_payments) dan disebut di pesan sukses. Setoran deposit tidak punya tagihan; pemakaian deposit otomatis sudah dibatasi sebesar tagihan.
**Nota thermal/A4** ditata ulang mengikuti contoh struk (label dan titik dua sejajar, item dua baris dengan harga satuan, ringkasan rata kanan); isi tetap. Baris penggunaan deposit dan saldo deposit selalu dicetak. Semua teks hitam pekat dan garis solid (abu-abu tercetak sebagai titik-titik di printer thermal); huruf tidak diubah. "Kembali" di nota kini dari kembalian yang tersimpan.
**Audit indeks:** 518 query unik dari semua rute GET (dengan variasi saringan status, tanggal, dan pencarian) di-EXPLAIN pada data besar. 42 temuan pemindaian penuh atau filesort; 19 indeks baru (status collecting, urgent + status SPK, approval status + tanggal, kas kecil tanggal + jenis, tanggal/status/jatuh tempo order jersey, tanggal bayar jersey, `updated_at` di 13 tabel yang dipantau `/sinyal`) dan satu query ditulis ulang (NOT IN menjadi IN). Sisa 19 temuan tidak bisa dibantu indeks: hitungan seluruh tabel untuk paginasi, pencarian `LIKE '%kata%'`, agregat seluruh jurnal di Neraca Saldo, dan saringan `!=`; terberat 0,56 detik pada 670 ribu baris jurnal.

## 30. Siklus hidup data
| Data | Sebelumnya | Sekarang |
|---|---|---|
| Log audit (`audit_logs`) | tumbuh tanpa batas (690 ribu baris di data uji; setiap halaman log menghitung seluruh tabel) | baris lebih tua dari 24 bulan (`AUDIT_ARSIP_BULAN`, minimal 6) DIPINDAH ke `audit_logs_arsip` tiap malam, bertahap 5.000 baris; tetap bisa dibuka di Log Aktivitas dengan centang "Tampilkan arsip". Tidak ada yang dihapus |
| Cache database | baris kedaluwarsa tidak pernah dibuang | dibuang tiap malam |
| Sesi database | hanya dibersihkan acak (2 dari 100 permintaan) | juga dibersihkan tiap malam |
| `laravel.log` | satu berkas, 11 MB dalam tiga hari | `LOG_STACK=daily`: satu berkas per hari, disimpan 14 hari (`LOG_DAILY_DAYS`). Berkas lama `laravel.log` dibiarkan; boleh dihapus |
| Riwayat backup (`backup_logs`) | tanpa batas | lebih dari 1 tahun dihapus (berkas backup punya retensi sendiri: 7 harian, 4 mingguan, 12 bulanan, 10 manual) |
| Transaksi, jurnal, stok, pembayaran | - | tidak pernah dihapus atau diarsipkan: data keuangan wajib disimpan (UU KUP: 10 tahun) |
Perintah: `php artisan data:rapikan` (dijadwalkan 03:00; `--pratinjau` untuk menghitung saja). Tes: `RapikanDataTest`.
Nota: tata letak nota kasir sudah aktif di server (diverifikasi dengan merender nota di `oriondb`); cetakan kini dikirim dengan `Cache-Control: no-store` supaya peramban tidak menampilkan salinan lama. Nota order jersey (thermal/A4) ditata dengan aturan yang sama (teks hitam, garis solid, titik dua sejajar) dan menampilkan Kembali bila ada.

## 31. Keadaan kosong/galat dan navigasi keyboard
**Keadaan kosong dan galat** (89 halaman dirender pada database uji kosong):
| Temuan | Perbaikan |
|---|---|
| 8 alamat `/kategori/create`, `/supplier/create`, `/bank/create`, `/konsumen/create`, `/golongan/create`, `/satuan/create`, `/mesin/create`, `/produk/create` membalas galat 500 (rute resource tanpa method di controller; tambah dan ubah memang lewat modal) | rute yang tidak punya method dikeluarkan (`->except`), juga `kedatangan-bahan` show. Pemeriksa: setiap rute kini menunjuk method yang ada |
| Tabel kosong tanpa pesan: Golongan, Mesin, Kelola Kas (uang masuk dan kas tidak terduga) | pesan kosong ditambahkan |
| Stok opname: tombol Ajukan menyimpan hasil hitung lewat AJAX lalu mengajukan TANPA memeriksa hasil simpan; bila simpan gagal (mis. alasan selisih kosong), yang diajukan angka lama | pengajuan berhenti dan menampilkan pesan bila penyimpanan gagal atau koneksi putus |
| `kasir/index.blade.php` tidak dipakai di mana pun dan memanggil alamat yang tidak ada | dihapus |
**Keyboard:**
| Temuan | Perbaikan |
|---|---|
| Tab di dalam modal keluar ke halaman di belakang modal | Tab dan Shift+Tab berputar di dalam modal (`resources/js/app.js`); diperiksa di browser: 12 elemen, berputar dua arah, Esc menutup |
| Menu samping di layar kecil tidak bisa ditutup dengan Esc | Esc menutup |
| Pemilih surat jalan di Pembelian Bahan hanya bisa diklik mouse | Enter/Spasi membuka pemilih |
| 28 tombol yang hanya berisi ikon tanpa nama (pembaca layar membaca "tombol" saja) | diberi `aria-label` dan `title` sesuai ikon (Hapus, Ubah, Tutup, dst.) |
Sudah benar dan dipertahankan: cincin fokus global, Esc menutup modal, fokus pertama masuk ke isian modal, tidak ada elemen `onclick` lain yang tak terjangkau keyboard.

## 32. Pemeriksaan visual setelah login, responsif, dan GitHub
Masuk dengan akun uji di salinan database `percetakan_erp_beban` (server `php artisan serve` port 8766), lalu 74 halaman menu + halaman depan diperiksa pada lebar 375, 768, dan 1024 px: tabrakan antartombol/isian dan gulir mendatar.
| Temuan | Perbaikan |
|---|---|
| Info & Panduan: tombol Tulis Postingan ditempel absolut di pojok sehingga menabrak kolom cari saat layar menyempit | tombol masuk ke baris pencarian dan turun ke baris sendiri di layar kecil; diperiksa 375/768/1024: tidak bertabrakan |
| Paginasi melebar keluar layar di 375 px pada daftar dengan banyak halaman (SPK, Produksi, Collecting, Kasir, Jersey, Approval, dll.) | nomor halaman membungkus ke baris berikut |
| Audit Bulanan: tabel lebar menggeser seluruh halaman | tabel menggulir di dalam kartunya |
| Tanggal tampil berbahasa Inggris ("Thursday, 01 October", "Oct") | `APP_LOCALE=id` dan 66 format tanggal memakai `translatedFormat` |
| Panduan visual finishing di tiga dashboard | dihapus sesuai permintaan; informasi semacam itu kini lewat Postingan |
| Halaman baca tulisan: gambar sampul lebih lebar (1.120 px) dari kolom tulisan (720 px) | judul, sampul, isi, tag, dan "Tulisan lain" kini satu kolom 720 px; diukur di 375/768/1280: tepi kiri dan kanan sama persis |
**GitHub** (`steverog1945-bit/percetakan-app`): repositori **publik** (terbaca tanpa login). Cabang `main` di GitHub berisi `oriondb.sql` (dump lengkap: pengguna dan hash password, karyawan, konsumen) dan folder `backups/` (2.336 berkas, 543 MB: salinan mentah folder data MySQL termasuk tabel `mysql.user`, dump SQL, skrip pemulihan). `.env` dan `APP_KEY` tidak pernah masuk. Di komputer ini berkas-berkas itu sudah tidak dilacak git dan `.gitignore` kini menolak backup, dump SQL, dan berkas data MySQL. Riwayat di GitHub tetap memuatnya sampai dihapus dengan penulisan ulang riwayat (menunggu persetujuan pemilik).

## 33. Riwayat GitHub, dialog, kunci produk
- **Riwayat GitHub dibersihkan** (atas persetujuan pemilik, setelah repositori dijadikan privat): `backups/` dan `oriondb.sql` dibuang dari seluruh 425 commit `main` (`git filter-branch`), lalu force push. Tag `tampilan-awal` di GitHub diarahkan ke commit hasil tulis ulang yang setara. Isi kode di ujung `main` identik dengan sebelum penulisan ulang. Cadangan riwayat lama: `D:/BackupERP/git/percetakan-sebelum-tulis-ulang-20261001.bundle` (berisi data, simpan di tempat aman). Catatan: GitHub dapat menyimpan salinan commit lama yang tidak lagi dirujuk sampai pembersihan internal mereka; untuk penghapusan tuntas dapat diminta lewat GitHub Support.
- **Dialog dan notifikasi** mengikuti contoh pemilik: kartu bersudut besar, judul besar rata kiri, tombol tutup bulat di kanan atas, keterangan abu-abu, tanpa ikon, tombol Batal bergaris lalu aksi utama di kanan (merah untuk hapus/batal, hitam untuk lainnya). Berlaku untuk semua `Swal.fire()` lewat default di layout dan CSS di `ui-refresh.css` bagian 24, termasuk toast.
- **Input Order: produk dikunci sampai kategori dipilih** juga untuk tombol cari cepat (sebelumnya pencari tetap bisa dibuka dan menampilkan semua produk). Pencari kini ikut terkunci, menampilkan pesan bila dicoba, dan hanya menampilkan produk dari kategori yang dipilih.
