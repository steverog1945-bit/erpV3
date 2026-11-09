# Master Bahan — filter desktop dan padding monitor kecil

Tanggal: 28 Oktober 2026 (Asia/Jakarta).

Status: implementasi selesai; validasi visual/runtime belum tersedia. Owner telah mengizinkan push feature branch dan pembuatan Draft PR dalam percakapan.

Branch: `fix/bahan-filter-compact-layout`.
Base: `main`, `dbb18b668a1578e66e7a548aa2aafb118091d073`.
PR: belum tersedia untuk perubahan ini.

## Tujuan dan perubahan

- `resources/css/mobile-bahan.css`: netralisasi kotak native `::details-content` milik filter Master Bahan pada desktop >=768px. Wrapper `details` tertutup sebelumnya tetap menyembunyikan isi di Chromium meskipun wrapper sudah memakai `display: contents`. Kotak isi kini memakai `display: contents` dan `content-visibility: visible`, mempertahankan susunan flex asli. Browser yang tidak mendukung pseudo-element ini mengabaikan aturan tambahan; perilakunya belum diverifikasi.
- `resources/views/layouts/app.blade.php`: padding khusus desktop kecil (lebar 768–1440px atau lebar >=768px dengan tinggi <=800px). Area utama 32px 24px menjadi 20px 16px; topbar 18px 24px menjadi 10px 16px dan margin 8px; wrapper halaman langsung dengan `max-w-[1600px].py-10` menjadi padding vertikal 24px dari 40px. Ukuran teks dan kontrol tetap.
- Perubahan ini mengikuti permintaan owner untuk merapatkan desktop, sebagai pengecualian terhadap aturan zero-diff desktop tugas mobile terdahulu. Tidak melanjutkan mandat lintas modul PR #21 yang berada di luar permintaan sesi ini.

## Validasi

- PASS: `npm ci --no-audit --no-fund`.
- PASS: `npm run build` setelah kedua perubahan; Vite 7.3.1, 111 modul.
- PASS: `git diff --check`.
- PASS (source review): aturan filter ditambahkan hanya di media desktop; CSS mobile Master Bahan, markup/ID/name/action/handler form tidak berubah.
- PASS (source review): aturan padding dibatasi `screen` dan desktop >=768px; tidak mengubah print atau aturan HP.
- NOT TESTED: render Laravel, visual browser pada 1366×768/1024×768/1920×1080/390×844, interaksi filter dan transisi breakpoint, light/dark runtime.
- BLOCKED: PHP/Composer tidak tersedia; executable Chromium Playwright tidak ada, unduhan browser gagal. Build bukan bukti visual atau keberhasilan interaksi runtime.
- Screenshot: TIDAK ADA. Tidak menggunakan fixture statis sebagai bukti ERP.

## Agent, skill, dan keamanan

Agent khusus menangani investigasi serta patch filter; agent utama menangani layout dan review gabungan. Instruksi `AGENTS.md` dan `.github/copilot-instructions.md` diikuti sesuai scope terbaru. Skill PromptCraft dipakai untuk mempertahankan bahasa desain dan membatasi perubahan. Paket ECC, Caveman, dan antislop yang dirujuk repo tidak tersedia dalam clone (folder skill diabaikan Git).

Produksi `C:\xampp\htdocs\grafikaorion`, `.env`, database, backend bisnis, RBAC, route, dan service worker tidak disentuh. Tidak menjalankan Artisan/migration/seeder. Tidak merge/deploy. Push feature branch dan pembuatan Draft PR diizinkan owner; merge/deploy tidak diizinkan. Hasil publikasi dan SHA remote dicatat pada laporan chat setelah verifikasi.

## Pemeriksaan lanjutan pada staging

Pada desktop 1366×768 dan sidebar terbuka/tertutup, pastikan pencarian, kategori, status, harga, urutan, reset tampil serta mengirim query yang sama. Pada 390×844 pastikan panel Filter tetap dapat dibuka/tutup. Bandingkan desktop 1920×1080 dan print dengan baseline. Gunakan staging sintetis terisolasi.

## Koreksi berdasarkan screenshot owner setelah penerapan

Screenshot owner menunjukkan filter masih menumpuk vertikal di kanan dan panel terlalu tinggi; patch pertama belum menyelesaikan geometri Chromium. Filter desktop kini memakai kotak `display:flex` nyata pada `<details>`, bukan bergantung pada perataan wrapper tersebut. Search memiliki basis 220px; kelompok filter basis 640px dengan wrapping; setiap dropdown fleksibel berbasis 128px, reset 38px. Aturan scoped pada `#filterForm` dan desktop >=768px. CSS mobile dan Blade master tidak diubah.

Validasi: build frontend dan whitespace diperiksa kembali; hasil visual perbaikan kedua belum diverifikasi melalui browser. Screenshot owner adalah bukti kegagalan patch pertama, bukan bukti kelulusan patch kedua. PR: https://github.com/steverog1945-bit/percetakan-app/pull/25. Workspace XAMPP owner juga memiliki modifikasi lokal pada Blade master; jangan menimpanya.

## Solusi final sesuai arahan owner: hapus wrapper filter mobile

Dua pendekatan CSS terhadap `<details>` belum menghasilkan layout benar menurut screenshot owner. Owner mengizinkan penghapusan panel filter mobile. Wrapper `<details>`, `<summary>`, dan `.mobile-bahan-filter-fields` dihapus dari form Master Bahan; lima kelompok field dan reset kini menjadi anak langsung form. CSS khusus panel lama dan kedua workaround dihapus. Desktop menggunakan flex wrapping langsung pada field; HP menampilkan grid dua kolom dengan pencarian selebar form, tanpa disclosure. Perubahan label lokal owner tidak diubah.

PASS: Vite build, git diff whitespace, parser HTML stdlib memeriksa nesting seimbang dan tepat lima kelompok div + satu reset sebagai anak langsung form; nama field, method/action, serta seluruh option identik dengan sebelum perubahan. Percobaan parser BeautifulSoup tidak tersedia; pemeriksaan digantikan stdlib HTMLParser dan berhasil. NOT TESTED: visual/runtime Laravel setelah solusi ini. File Blade kini ikut berubah hanya pada penghapusan wrapper, jadi penerapan pada XAMPP harus berhenti jika Git melaporkan konflik, tanpa menimpa modifikasi lokal owner.
