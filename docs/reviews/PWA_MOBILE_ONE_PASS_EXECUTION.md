# ORION GRAFIKA — SINGLE-RUN MOBILE PWA COMPLETION & RELEASE CANDIDATE

## PERUBAHAN BASIS GIT — PRIORITAS WAJIB 26 Oktober 2026

**GitHub terbaru:**
- `main` = `1e73415a83c5f54618c763e317c226017a144fa5`.
- PR #15 (Master Bahan) **SUDAH MERGED** ke `main` sebagai `1b690ff`.
- PR #18 (POS) **SUDAH MERGED** ke `main` sebagai `1e73415`.
- PR #19 (SPK) **DRAFT, GitHub mergeable=false**; konflik disebabkan perubahan bersama relatif ke `main` terkini. **JANGAN paksa merge PR #19 ke `main`**, karena source SPK sudah berada di integrasi #20.
- PR #20 **DRAFT, mergeable=false** karena `main` lebih baru dari basis branch; head sebelum instruksi ini `ce96d2e`. Konflik harus dituntaskan terlebih dahulu di branch integrasi, **bukan** `main`.

**Tindakan tunggal OpenCode paling awal:**
1. Verifikasi status worktree; `git fetch origin`, lalu checkout branch integrasi #20 tanpa membuang perubahan lokal. Jika konflik checkout/worktree, gunakan worktree aman di workspace development yang sama.
2. `git merge --no-ff origin/main` **ke `integration/pwa-mobile-all-modules`**, bukan sebaliknya. Jangan rebase riwayat yang sudah dipush, `reset --hard`, force-push, atau rewrite merge commit existing.
3. Resolusi konflik **semantik** per file: `mobile-ui.css` mempertahankan **SEMUA** import `mobile-dashboard`, `mobile-bahan`, `mobile-modal`, `mobile-nav`, `mobile-erp`, `mobile-pos`, `mobile-spk`, `mobile-produksi`; jaga satu import tiap modul. Source Blade dan CSS Master/Pos yang sudah ada di `main` jangan digandakan; SPK/Produksi dari branch integrasi harus tetap ada. Untuk `AGENTS.md`, pertahankan mandat one-pass + aturan keselamatan. Untuk laporan, pelihara bukti dan sejarah tanpa duplikasi klaim kelulusan.
4. Jalankan `git diff --check`, `npm ci` hanya bila aman di clone kerja tanpa scripts yang mengakses secrets, `npm run build`, dan validasi sumber/DOM aman; verifikasi GitHub `mergeable` PR #20 setelah push. Bila branch konflik tidak terselesaikan secara aman, tulis nama file + potongan konflik persis dan lanjut ke pekerjaan independen yang aman; jangan menebak atau mengambil `--ours/--theirs` seluruh tree.
5. **PR #19 boleh tetap Draft** sebagai rekam perubahan, namun dianggap *superseded by PR #20* hanya bila isi CSS dan Blade SPK di PR #20 terbukti sama atau lebih baru, dan semua fitur tetap. Jangan merge #19 ke main atau membuat PR SPK lain.
6. **Kembali ke pengerjaan SEMUA modul tanpa prompt lain** sesudah #20 bebas konflik; status mergeable bukan bukti uji Laravel atau izin deployment.


**Dibuat: 26 Oktober 2026.** Status: **MANDAT PELAKSANAAN**, bukan hasil pengujian dan **bukan izin deploy**.

## Status integrasi terverifikasi — 26 Oktober 2026

**Ini checkpoint implementasi, bukan status selesai.**

- Draft PR integrasi **#20 sudah dibuat**: https://github.com/steverog1945-bit/percetakan-app/pull/20. Tidak perlu `gh pr create` ulang.
- Master Bahan (#15), SPK (#19), dan Produksi Phase 4 telah ditambahkan oleh OpenCode sebelumnya ke branch integrasi. POS (#18) yang tertinggal telah **diintegrasikan ke branch integrasi** pada commit merge `3f6acc0`; `resources/css/mobile-ui.css` kini mengimpor **POS + SPK + Produksi**, tanpa menghapus lapisan Master dan theme.
- `AGENTS.md` diperbarui pada commit `c79ea17` agar mode #20 berjalan sekali sampai seluruh modul tercakup, tidak berhenti untuk mengirim laporan "final parsial" tiap modul.
- **Tidak dibutuhkan PR Produksi yang terpisah** untuk jalur one-pass karena source sudah berada dalam #20. Jangan membuat PR baru untuk Collecting/Jersey/dll.
- Modul berikutnya: **Collecting → Jersey → Kasir tambahan/Logistik/Pembelian/Stok → seluruh Master → Keuangan/Laporan → Dashboard/Setting dan seluruh layar aktif tersisa**. Penyelesaian visual source masing-masing tidak boleh disebut runtime PASS sampai benar-benar diuji.
- Kode integrasi terbaru belum menjalani build/tes runtime setelah commit penggabungan POS. **VALIDASI AKHIR: NOT TESTED**, rilis produksi tetap NO-GO. OpenCode harus `git fetch origin` lalu sync tanpa membuang pekerjaan lokal sebelum melanjutkan.

## Instruksi final: sekali jalan, tidak meminta prompt modul demi modul

OpenCode harus bekerja secara mandiri dalam **satu rangkaian pengerjaan yang berkesinambungan**, menyelesaikan modul sampai cakupan selesai, lalu memberi **satu laporan akhir**. Jangan berulang kali mengaudit modul yang sudah selesai, menulis proposal, meminta owner mengulang prompt, membangun clone lain yang tidak perlu, atau menghentikan proses hanya untuk mengatakan satu modul selesai. Progres internal boleh menggunakan commit per gugus modul, bukan percakapan baru. Jika ada blocker substantif, kerjakan modul independen yang masih aman sambil mendokumentasikan blocker; **jangan menciptakan bukti PASS**.

Workspace yang digunakan owner: `C:\repos\grafikaorion-copilot-mobile\percetakan-app`. Periksa `git status` dan perubahan lokal, `git fetch origin` secara non-destruktif. Gunakan worktree terpisah yang aman di bawah `C:\repos\grafikaorion-copilot-mobile` bila clone sedang dipakai, **jangan overwrite atau reset --hard**. Branch integrasi terpusat: `integration/pwa-mobile-all-modules` dari `origin/main` (PR integrasi Draft; jangan ubah `main`).

## Baseline dan integrasi pekerjaan yang telah dibuat

Pada audit terakhir `main=aee1d5d01b0de3c3c6fd6a0fe33545e6f6b082f6`. Tiga PR lama yang perlu **diintegrasikan pada branch integrasi, bukan di-merge ke main tanpa approval**:

| Area | PR dan branch | Head pada audit |
|---|---|---|
| Master Bahan | #15 `fix/mobile-visual-final-polish` | `27990df77` |
| POS/Pembayaran | #18 `feat/mobile-pos-phase2` | `fa948070b` |
| SPK | #19 `feat/mobile-spk-phase3` | `f7e346ebc` |

Selalu `git fetch origin` dan cek HEAD terbaru, bukan mengasumsikan SHA tabel masih yang terkini. **Gabungkan ketiganya ke branch integrasi** dengan Git merge normal (atau cherry-pick perubahan produk bila riwayat PR mengandung commit dokumen yang tidak relevan), resolusi konflik eksplisit, terutama `resources/css/mobile-ui.css`, komponen `x-table`, CSS specificity, serta filter SPK. Jangan cherry-pick buta; hindari menduplikasi handler, menu, DOM, atau CSS. Pertahankan jejak commit/source dan rapikan `AGENTS.md` satu kali agar berlaku lintas modul dan lintas branch. Hentikan pengulangan review Master/Pos/SPK: cukup remediasi masalah yang masih terbukti dan tes integrasi gabungan.

## Target akhir: 100% layar operasional mobile, bukan tiga modul contoh

Bangun **inventaris deterministik dari routes, menu/role RBAC, dan `resources/views/**/*.blade.php`**. Tandai setiap *route/screen* dengan status `COVERED`, `NOT APPLICABLE (print-only)`, `BLOCKED`, dan tautan bukti; jangan mengecualikan diam-diam. Modul wajib meliputi, bila ada route layar aktif:

1. Dashboard seluruh role, topbar/sidebar, login, profil, menu Master, ikon, tema/light-dark, safe-area, floating nav.
2. Input Order/POS dan kasir: order, keranjang, pembayaran, riwayat, waiting/open order, monitoring, kas/closing, penjualan barang, quotation/revisi yang tersedia.
3. SPK, divisi, pencarian/filter, antrian/urgent/pending, Produksi, QC bila ada, Collecting, status dan detail pekerjaan; pahami dependensi SPK → Produksi → Collecting tanpa mengubahnya.
4. Jersey master, daftar order, create, edit pemain/ukuran, nominal, status, keuangan, CSV/import UI; scrollbar hanya pada tabel teknis jika diperlukan.
5. Master data: bahan, konsumen, golongan, kategori, jenis bahan, merk, mesin, produk/jasa, satuan, konversi, karyawan, role/user, supplier, gudang, bank, rekening, harga, dan master lain yang muncul pada routes aktif.
6. Persediaan/logistik: kartu stok, bahan masuk/keluar, gudang, kedatangan, pembelian bahan, barang, supplier, BOM/worksheet/aset jika ada.
7. Keuangan/laporan: deposit konsumen, kas kecil/harian, hutang/piutang, penjualan, pembelian, HPP/job costing, laporan order, stok, produksi, jersey, rekap, setting/aplikasi.
8. Halaman form create/edit/detail, empty/loading/error, pagination, dropdown, textarea, Select2, modal, konfirmasi, validation errors, tabel lebar dan role-limited; **bukan hanya halaman index**.

**Kecualikan hanya view print/nota/SPK cetak dan ekspor dengan alasan "print-only"; mereka tetap harus bisa diprint dengan baik.** Jangan membangun ulang seluruh Blade untuk membuat gambar mirip, gunakan komponen yang ada.

## Desain & integritas ERP

- Acuan desain owner: `docs/reviews/assets/MOBILE_VISUAL_390_SIDE_BY_SIDE.png` **panel KIRI adalah referensi, KANAN hasil lama**; gunakan `docs/reviews/MOBILE_MASTER_REFERENCE_CONTRACT.md` dan theme tokens terkini. Kualitas iOS-inspired profesional, **solid surfaces, tanpa blur/glass, whitespace dan typography konsisten, minim dekorasi**. Jangan memaksakan daftar kartu untuk halaman transaksi rumit; berikan hierarki sesuai pekerjaan.
- Global breakpoint mobile `<768px`, desktop `>=768px` **tanpa perubahan regresif**. Satu floating nav, layering/modal benar, tema/role aktif benar, nama panjang tidak terpotong agresif, bottom clearance + safe-area, touch target >=44x44px untuk aksi penting, form input tetap nyaman dengan keyboard virtual.
- Gunakan shared tokens/styles/komponen; hindari aturan global yang membocorkan CSS spesifik ke modul lain, selector rapuh, `!important` yang tidak perlu, duplikasi data/ID/form, screenshot placeholder seolah data ERP.
- **Jangan mengubah** business logic, role/RBAC, perhitungan harga/HPP, jurnal, stok, status transaksi, ID/name input, form action, event handler, controller, route, API, PWA SW/manifest hanya untuk mengejar desain. Bila ada bug bisnis yang nyata, catat dan buat patch/PR terpisah **hanya jika owner mengizinkan**.
- Pertahankan autentikasi, confirmation setiap aksi, akses per role, transaksi kasir dan data pelanggan.

## Jalur eksekusi tanpa perulangan prompt

**0. Safety & snapshot:** baca `AGENTS.md` (dari feature PR bila tidak ada pada branch), cek kondisi Git, manifest Build, inventory route/menu view; jangan scan rahasia. Simpan baseline screenshot perwakilan desktop/mobile. **Satu kali saja.**

**1. Integrasi lama:** gabungkan #15/#18/#19 ke branch ini; selesaikan konflik dan jalankan build/lint, tes UI smoke seperlunya. Review manual hanya konflik/risk yang nyata. Jangan memperbaiki satu modul yang sudah baik berulang-ulang.

**2. Implementasi tersisa, urut:** Produksi + Collecting → Jersey → Kasir tambahan & Pembelian/Logistik/Stok → semua Master/Karyawan/User/Gudang → Keuangan/Laporan → Setting/Dashboard/edge states. Commit per gugus dengan catatan coverage. Terus lanjut otomatis ke gugus berikutnya tanpa owner harus mengirim prompt.

**3. QA terpadu:** gunakan DOM hasil Blade/runtime clone terisolasi bila tersedia; Vite CSS build sama dengan aplikasi. Screenshots di 320, 360, 390, 430, 767px serta 768, 1024, 1366px; cukup screenshot per pola UI/risiko + matriks semua layar, jangan timbun ratusan screenshot duplikat. Uji search/filter aktif setelah reload, halaman panjang sampai akhir, nav vs sticky submit, grid/card/tables, modaI/Select2, tombol 44px, dark mode, nama panjang, empty states, print view. Link bukti yang sebenarnya.

**4. Tes fungsional aman:** `npm ci`, `npm run build`, `git diff --check`, PHPUnit/Artisan hanya setelah secara eksplisit memverifikasi lingkungan **sepenuhnya sintetis dan tidak ada koneksi prod** (misalnya DB in-memory SQLite terisolasi, tidak ada kredensial/pewarisan .env). Jika tidak aman, tidak tersedia, atau DB engine tidak kompatibel, tulis `NOT TESTED`, bukan PASS. Lakukan negative RBAC/IDOR per role, Kasir A/B, SPK produksi, akses berkas privat; setiap mutasi hanya pada fixture DB sintetis. Uji PWA installability, manifest, SW cache behavior pada build yang dipakai, iOS safe-area, Android keyboard, asset Vite hash, offline page, cache busting. Jangan menyentuh PWA global kecuali masalah terbukti dan tercakup PR ini.

**5. Build release candidate & go/no-go:** siapkan `docs/reviews/PWA_MOBILE_COVERAGE_MATRIX.md`, `docs/reviews/PWA_MOBILE_INTEGRATION_FINAL.md`, `docs/reviews/RELEASE_READINESS.md` (atau pointer issue #17 jika berada pada PR lain), manifest SHA per merge, daftar file/risiko dan test ledger `PASS/FAIL/NOT TESTED/BLOCKED`. Siapkan paket build sesuai Vite manifest, step-by-step operator deployment & rollback. Semua perubahan masuk **satu Draft PR integrasi**. Verifikasi `git status`, local=remote; kirim **satu laporan akhir** dalam chat utama.

**6. Rilis utama hanya setelah owner menyatakan GO:** jangan otomatis merge ke `main`, menyalin ke produksi, menyentuh folder `C:\xampp\htdocs\grafikaorion`, membaca `.env`/kredensial/`oriondb`, SSL Apache, backup, scheduler atau layanan Windows. Staging terisolasi dan tes wajib. Operator terotorisasi memegang backup+restore teruji, asset manifest/hash, waktu rilis, smoke test dan rollback. Jika semua tes aman lulus, keluarkan status **READY FOR OWNER GO/NO-GO**. Jika tes runtime/perangkat belum dapat dilakukan, keluarkan **BLOCKED FOR PROD RELEASE** tetapi jangan berhenti mengerjakan UI modul lain.

## Kontrak selesai

- Semua menu/rute layar aktif tercatat dalam coverage matrix; tidak boleh berhenti pada beberapa modul.
- Tidak ada P1 UI/aksesibilitas/permission terkonfirmasi yang belum dikerjakan pada scope UI.
- Visual dinilai dari render *source asli*; preview statis diberi label, tidak menggantikan aplikasi hidup.
- Tidak ada regresi desktop >=768, form/action/RBAC/ekspor, dan alur transaksi.
- Build dan suite uji dijalankan sesuai batas keselamatan. `NOT TESTED` berarti release **NO-GO**, bukan siap deploy.
- Tidak ada audit/proposal/prompt per modul. Satu laporan akhir setelah pekerjaan selesai, dengan bukti commit/branch/PR, screenshot, matriks, pengujian dan operator release runbook.

### Referensi
- Source: `steverog1945-bit/percetakan-app`
- PR pekerjaan terdahulu: #15, #18, #19
- Issue roadmap: #16
- Issue release gate: #17

**Instruksi prioritas:** bila task memakan waktu lama, OpenCode tetap lanjut internal tanpa memerlukan prompt berikutnya. Berhenti meminta input owner hanya pada otorisasi rilis produksi, akses rahasia/produksi, perubahan bisnis di luar scope, atau blocker keamanan yang tak bisa diatasi aman.
