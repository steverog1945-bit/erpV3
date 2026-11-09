# ORION GRAFIKA — Kontrak Visual Master Bahan Mobile

**STATUS: ACUAN WAJIB UNTUK PR #15; BELUM LULUS VISUAL.**
**Bahasa pelaporan: Indonesia.**
Jangan menggantinya dengan proposal, mockup alternatif, atau dashboard kreatif. Screenshot referensi owner (852 × 1846 px; proporsi sekitar 390 × 845 CSS px) adalah **sumber kebenaran visual**, sedangkan screenshot terakhir dari Copilot pada 398 × 907 adalah **contoh yang DITOLAK**.

## Penyebab penolakan hasil terakhir

1. Kartu material setinggi sekitar 220 CSS px, sedangkan referensi menggunakan **baris horizontal ringkas sekitar 70–85 CSS px per material** pada 390 px (dapat bertambah untuk teks panjang). Satu layar seharusnya memperlihatkan beberapa material, bukan satu kartu diikuti ruang kosong.
2. Isi kartu masih membentuk blok terpisah bertingkat (Spesifikasi, Stok, Harga Rata-rata, Harga Beli Terakhir, Selisih). Referensi memakai **satu baris utama yang horizontal**, dengan detail sekunder tersedia saat diminta.
3. Komposisi utama wajib: **avatar/foto 44–50 px kiri | nama/SKU/tag tengah | status + stok + harga kanan | tombol ⋮ di ujung**. Jangan mengembalikan header tabel desktop di mobile.
4. Header tabel `# / Identitas / Spesifikasi / Volume Stok / Harga Rata-rata / Harga Beli` TIDAK BOLEH muncul sebagai judul kolom bertumpuk/terpotong di bawah 768 px. Checkbox 'Pilih semua' boleh berada di baris kontrol ringkas yang terpisah.
5. Kotak Search dan Filter **satu baris berdampingan**, masing-masing tinggi sekitar 44–48 px; jangan membungkusnya dalam panel tinggi berlapis. Filter lanjut default tertutup.
6. Status chips (Semua/Aktif/Nonaktif) meniru desain HANYA bila nilai dan jumlah bisa diperoleh dari data yang sah, tanpa kueri produksi baru atau mengarang jumlah. Jika tidak tersedia, tampilkan filter status sebenarnya tanpa angka palsu.
7. Floating bottom navigation tetap kapsul **solid, tanpa glass/blur**, lebar layar dikurangi kira-kira 24 px, tinggi total sekitar 58–68 px dengan safe-area; Master harus aktif mengikuti route.
8. Referensi menampilkan foto bahan. **Model `app/Models/BahanBaku.php` yang diperiksa tidak memiliki field gambar/foto bahan.** Tanpa data/gambar asli, gunakan MONOGRAM di area avatar dengan bentuk dan ukuran yang sama; DILARANG menyisipkan foto stok, gambar palsu, atau foto dari internet hanya agar menyerupai referensi.
9. Tidak boleh mengarang data contoh seperti `286` material, `125 lembar`, harga `Rp 18.500`, kategori, dan sebagainya sebagai data aplikasi. Dalam contoh uji terisolasi, beri label fixture.

## Geometri sasaran pada viewport 390 CSS px

| Elemen | Acuan |
|---|---|
| Konten horizontal | Margin 14–18 px |
| Header MASTER + judul + deskripsi | Kompak, judul ~23–25 px, tombol tambah sejajar jika muat |
| Search/Filter | Satu baris, tinggi ~44–48 px, jeda ~8–10 px |
| Status chips (jika data tersedia) | Baris ringkas tinggi ~30–36 px |
| Baris material | Tinggi ideal ~70–85 px bila nama satu baris; 90–110 px saat konten panjang |
| Jarak antarbaris | 4–8 px |
| Avatar/monogram | 44–50 px, radius ~10–12 px |
| Nama material | ~14–15 px, weight 600–700, max 2 baris |
| Metadata SKU/kategori | ~11–12 px |
| Status badge | Pil ringan dot+label, ~11–12 px |
| Stok/harga | ~12–14 px, angka tabular, kanan |
| Titik tiga | Target sentuh min. 44 × 44 px, penampilan ikon kecil |
| Floating nav | Radius pill, solid; tidak menutup konten atau modal |

## Implementasi struktural — bukan sekadar mengecilkan CSS

- Telaah `resources/views/master/bahan/index.blade.php`, `resources/views/components/table.blade.php`, `resources/css/mobile-bahan.css`, `resources/css/mobile-erp.css`, dan output build `app.css`.
- **Ubah presentasi mobile menjadi satu baris daftar dua/lebih zona yang sejajar**, bukan sembilan `td` yang menumpuk menjadi kartu tinggi.
- Identitas, status, stok, dan harga rata-rata harus tampak pada ringkasan utama. Spesifikasi, harga beli terakhir, selisih, dan detail lain tetap dapat diakses melalui disclosure/drawer **terlokalisasi ke material yang sama**; tombol koreksi harga serta edit/status/hapus tetap dapat dijalankan.
- Jangan menduplikasi input name/id/checkbox/JS handler, jangan mengganti form action/CSRF, role/route, stok, nilai uang, atau rumus. Jika perlu penyesuaian markup presentasional Blade, jaga kesetaraan satu record dan satu set kontrol interaktif, serta desktop >=768 px tetap seperti semula.
- Pilih `CSS Grid` atau penataan mobile yang benar-benar sesuai DOM asli. Jangan membuat screenshot simulasi yang memakai struktur DOM berbeda dari Blade dan komponen aktual.
- Perhatikan nama bahan panjang, satuan panjang, harga besar, stok minus/nol, bahan nonaktif, tanpa harga, satuan konversi, pengguna dengan izin lebih sedikit, dan empty state.

## Acceptance test — semua wajib sebelum boleh disebut selesai

1. Lampirkan gambar acuan owner dan gambar hasil di Copilot session yang sama. Agent HARUS menyebutkan bahwa ia dapat melihat/membaca gambar acuan sebelum mengeksekusi styling. Jika gambar tidak terlihat oleh agent, **BLOCKED visual**; jangan menebak.
2. Render **DOM yang benar-benar berasal dari Blade/komponen** dengan **CSS bundle Vite yang sama dengan aplikasi** di lingkungan terisolasi. Jika tidak mungkin, tandai NOT TESTED; jangan menyatakan visual PASS.
3. Screenshot hasil pada 320/360/390/430 px, dan bandingkan berdampingan dengan gambar acuan pada 390 px.
4. Pada 390 × 844 dengan data fixture 6+ bahan bernama pendek, filter tertutup, **minimal empat baris material terlihat di atas floating nav** bila header normal. Dilarang memperpendek dengan menyembunyikan konten penting tanpa jalur untuk menampilkannya.
5. Tidak ada overflow horizontal, teks terpotong parah, header tabel muncul di mobile, kartu >120 px tanpa alasan data, menu ⋮ tertimpa, atau nav menutupi konten.
6. Filter/search, select-all, checkbox, tiga aksi, koreksi harga, pagination, dan modal tidak boleh rusak. Desktop >=768 px harus tetap pada struktur tabel asli.
7. Wajib screenshot computed styles `display`, `grid-template-columns`, `min-width`, `overflow` untuk tabel, tbody, tr, td, wrapper; pastikan CSS yang di-build benar-benar dimuat.
8. Jika UI belum menyerupai referensi, status **FAIL**, bukan SELESAI; jangan mendorong owner melakukan merge/deploy.

## Scope dan rilis

- Target pekerjaan tetap branch `fix/mobile-visual-final-polish`, PR #15, tidak membuat branch/session baru.
- Perubahan hanya presentasi mobile, tanpa mengakses produksi `C:\xampp\htdocs\grafikaorion`, `.env`, database `oriondb`, migration, model bisnis, transaksi, atau permission.
- **Belum izin MERGE ataupun DEPLOY.** Owner harus meninjau screenshot dari DOM/CSS sebenarnya, lalu memberikan persetujuan eksplisit.
- Setelah benar, kirim commit/push pada PR #15 yang sama, perbarui laporan Markdown secara otomatis, dan tampilkan ringkasan final di chat Copilot utama.
