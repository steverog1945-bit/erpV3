# Panduan Penggunaan ORION GRAFIKA (ERP Percetakan)

Panduan ini menjelaskan alur kerja aplikasi per peran. Nama menu mengikuti sidebar. Menu di sidebar dikelompokkan dalam grup yang bisa dibuka/ditutup (Produksi & Logistik, Relasi Bisnis, Finance & HR, Laporan, Akuntansi, Order Jersey, System Control); grup berisi halaman yang sedang dibuka otomatis terbuka.

## 1. Peran dan akses

| Peran | Tugas utama | Menu yang tersedia |
|---|---|---|
| Administrator | Mengelola semua, menyetujui pengajuan, menutup periode, backup | Semua menu |
| Kasir | Input order, terima pembayaran, kas harian, serah barang | Input Order, Penjualan Barang, Kasir, Collecting, Deposit Konsumen, Data Konsumen, Order Jersey, Laporan Kas, Laporan Keuangan |
| Operator Desain | Input order dan mengerjakan desain | Input Order, Produksi, Collecting, Data Konsumen, Order Jersey (input), Laporan Produksi |
| Operator Produksi | Mengerjakan antrean cetak | SPK, Produksi, Finishing Produk |
| Gudang | Barang masuk, stok, permintaan pembelian | Bahan Baku, Kedatangan Bahan, Gudang, Stock Opname, Purchase Request, Laporan Stok Bahan Baku |

Hak akses tiap peran diatur Administrator di System Control > Setting Akses. Membuka halaman di luar hak akses menghasilkan pesan 403.

## 2. Aturan penting yang berlaku di seluruh aplikasi

1. **Tidak ada penghapusan langsung untuk data keuangan.** Membatalkan nota, faktur pembelian, pembayaran hutang, pembayaran/order Jersey, dan catatan Kas Kecil hanya bisa **diajukan**. Administrator memutuskan di menu **Approval Workflow**. Saat disetujui, jurnal akuntansi dibalik dengan entri kontra; jurnal asli tidak pernah dihapus.
2. **Jurnal akuntansi dibuat otomatis** dari transaksi (penjualan, pembayaran, pembelian, kas kecil, deposit). Anda tidak menginput jurnal manual.
3. **Periode yang sudah ditutup terkunci.** Setelah Administrator menutup suatu bulan di Akuntansi > Accounting Period, transaksi yang menghasilkan jurnal bertanggal bulan itu ditolak sampai periode dibuka lagi.
4. **Semua perubahan penting tercatat** di System Control > Log Aktivitas (siapa, kapan, sebelum dan sesudah).
5. **Nomor dokumen dibuat otomatis** oleh server (INV-, PR-, PO-, SO- diikuti tanggal dan urutan). Jangan mengetik nomor sendiri.
6. **Halaman daftar dan dropdown memuat data terbaru saat dibuka.** Jika ada data baru dari halaman lain, buka dropdown lagi; tidak perlu memuat ulang halaman.
7. **Dokumen yang sudah diproses tampil hanya-baca DAN selalu dijelaskan alasannya.** Setiap form dokumen (Purchase Request/Order, dan modul lain) yang tidak bisa diedit menampilkan banner di atas form: status dokumen + apa langkah berikutnya (mis. "menunggu persetujuan", "lanjutkan lewat tombol Buat PO"). Field isian memakai mode baca (readonly) — masih bisa diklik, difokus, dan disalin — bukan disabled yang tampak seperti halaman macet; dropdown tetap disabled tetapi nilainya tetap tampil dan ikut terkirim lewat field tersembunyi.

## 3. Persiapan awal (sekali, sebelum dipakai massal)

Lakukan berurutan oleh Administrator:

1. **Master data:** Kategori Bahan, Jenis Bahan, Merk, Satuan & Konversi, Aset Mesin, Gudang, Supplier, Bank Perusahaan, Golongan Konsumen, Data Karyawan.
2. **Bahan Baku:** isi stok awal, stok minimal, dan satuan hitung.
3. **Produk & BOM:** tentukan bahan baku yang dipakai tiap produk (dasar HPP dan pengurangan stok otomatis), lalu Finishing Produk, Jasa, Price List & Level, dan Harga Promo bila ada.
4. **Pengguna dan akses:** Data Karyawan, Manajemen User, lalu Setting Akses per peran.
5. **Pengaturan Aplikasi:** nama, alamat, format nota, prefix nomor nota, pembulatan, libur kas hari Minggu.
6. **Saldo awal akuntansi:** jalankan `php artisan akuntansi:saldo-awal` sekali (Kas, Bank, Piutang, Persediaan, Hutang). Perintah ini menolak jalan jika jurnal sudah berisi transaksi.
7. **Reset data uji** dilakukan sebelum go-live agar transaksi percobaan tidak ikut ke data nyata.

## 4. Alur Pembelian bahan baku

```
Purchase Request -> Approval -> Purchase Order -> Kedatangan Bahan -> Pembelian (faktur) -> Bayar Hutang
```

1. **Gudang membuat Purchase Request** (Produksi & Logistik > Purchase Request > baru): pilih bahan dan qty, simpan sebagai Draft, lalu **Ajukan untuk Persetujuan**. Draft masih bisa diedit atau dihapus.
2. **Administrator menyetujui** di Approval Workflow. PR yang ditolak berstatus Ditolak dan tidak diproses.
3. **Pembelian membuat Purchase Order:** di daftar PR, PR berstatus Disetujui punya tombol **Buat PO**. Form terisi otomatis: qty bahan dari PR, catatan per bahan ikut terbawa, **supplier terpilih otomatis** dari histori PO (yang paling sering memasok bahan-bahan itu), dan **harga satuan otomatis dari PO terakhir** per bahan yang pernah dibeli - semuanya masih bisa diubah (harga berubah, supplier berbeda, dsb). Tinggal cek, lengkapi diskon/pajak/ongkir/termin/estimasi kedatangan, lalu simpan Draft dan **Terbitkan ke Supplier**. Total dihitung server. PO juga bisa dibuat langsung tanpa PR (isi manual seperti biasa).
4. **Barang datang:** di halaman PO berstatus Dipesan, isi No. Surat Jalan dan tanggal, lalu **Catat Kedatangan**. Sistem membuat Kedatangan Bahan (Draft) berisi sisa qty yang belum diterima. Buka kedatangan itu, sesuaikan qty fisik bila berbeda, lalu **validasi**. Saat divalidasi, stok bertambah dan tercatat di Kartu Stok; status PO menjadi Diterima Sebagian atau Selesai otomatis. Kedatangan tanpa PO (alur lama) tetap bisa dibuat.
5. **Faktur:** menu Pembelian Bahan > buat faktur dari kedatangan yang sudah divalidasi. Pilih **Cash** (langsung lunas) atau **Tempo** (menjadi hutang dengan jatuh tempo). No. faktur tidak bisa diubah setelah disimpan.
6. **Bayar hutang:** di daftar Pembelian Bahan, tombol Bayar Hutang. Isi nominal (default sisa hutang; boleh dicicil) dan metode (Tunai memotong Kas, Transfer memotong Bank). Faktur Lunas saat sisa nol. Riwayat pembayaran ada di halaman detail faktur.
7. **Salah bayar atau faktur salah?** Di detail faktur pilih **Ajukan Void** untuk satu pembayaran, atau tombol hapus di daftar untuk membatalkan seluruh faktur. Faktur yang masih punya pembayaran aktif hanya bisa dibatalkan setelah pembayarannya di-void.
8. **Pantau hutang:** Laporan > Laporan Hutang Supplier menampilkan sisa hutang per supplier menurut umur keterlambatan.

Purchase Order berstatus Dipesan/Draft bisa dibatalkan selama belum ada barang yang diterima.

## 5. Alur Stok

- **Kartu Stok:** riwayat semua mutasi stok per bahan (masuk, keluar, penyesuaian).
- **Stock Opname** (Produksi & Logistik > Stock Opname), dilakukan berkala oleh Gudang:
  1. Buat Stock Opname baru; semua bahan aktif otomatis masuk dengan snapshot stok sistem.
  2. Isi **stok fisik** hasil hitung. Bahan yang selisih wajib diberi alasan.
  3. **Ajukan untuk Persetujuan**. Stok belum berubah.
  4. Administrator menyetujui di Approval Workflow; barulah stok disesuaikan ke hasil hitung fisik dan tercatat sebagai penyesuaian di Kartu Stok.
- **Waste Management** (Produksi & Logistik > Waste Management): catat susut bahan di luar pemakaian normal BOM. **Normal** (susut wajar, mis. sisa potongan) langsung mengurangi stok dan dijurnal sebagai beban. **Abnormal** (rusak, salah potong, hilang) hanya diajukan; stok baru berkurang setelah Administrator menyetujui. Pilih item order terkait bila ada, supaya biayanya masuk Job Costing order itu.
- **Penyesuaian stok manual satu bahan** (Kartu Stok): jalur cepat untuk mengeluarkan atau merevisi stok satu bahan. Sama seperti Stock Opname, ini hanya **mengajukan**; stok baru berubah setelah Administrator menyetujui, dan persetujuan gagal aman kalau stok sudah tidak cukup.
- Stok bahan baku berkurang otomatis saat **nota dibuat** (Input Order), sesuai BOM produk, dan kembali otomatis saat nota atau SPK dibatalkan. Bahan yang terpakai untuk rework dikeluarkan terpisah lewat QC & Rework (bagian 6).

## 6. Alur Penjualan dan Produksi

```
Input Order -> SPK -> Produksi -> Pembayaran di Kasir -> Collecting (serah barang)
```

0. **Quotation (opsional, Finance & HR > Quotation):** untuk pelanggan yang bertanya harga dulu. Buat penawaran (pelanggan terdaftar atau prospek), pilih produk/jasa, ukuran, qty; harga dihitung otomatis dari Price List golongan pelanggan (sama dengan Input Order) dan bisa ditimpa per baris, ditambah finishing, desain, dan diskon. Cetak/simpan PDF atau kirim lewat WhatsApp, lalu **Tandai Terkirim**. Catat tanggapan pelanggan (**Setuju/Menolak**). Penawaran lewat masa berlaku otomatis tampil **Kedaluwarsa** dan tidak bisa disetujui atau dijadikan order. Untuk penawaran yang Terkirim/Disetujui, tombol **Buat Order dari Penawaran** membuka Input Order dengan rincian penawaran sebagai acuan di bagian atas layar; **item tetap diinput operator** sesuai penawaran. Begitu order tersimpan, penawaran berstatus **Menjadi Order** dan tertaut ke nota.
1. **Input Order** (Kasir atau Operator Desain): pilih atau tambah konsumen, pilih produk/jasa/finishing, isi ukuran dan qty, tentukan urgensi. Nomor nota dibuat server. Nota dapat berstatus Belum Bayar, DP, atau Lunas.
2. **SPK** (Operator Produksi): antrean kerja per item, status Antre, Desain, Cetak, Selesai (atau Pending/Batal).
3. **Produksi:** operator mencatat qty hasil, menyelesaikan, atau membatalkan item.
2a. **Design Proof** (Produksi & Logistik > Design Proof): untuk item yang desainnya perlu persetujuan pelanggan. Buka item, **unggah proof** (JPG/PNG/WEBP/PDF, maks 20 MB); tiap unggahan menjadi **Revisi #1, #2, ...** dengan waktu, pengunggah, dan catatan desainer. Setelah pelanggan memutuskan (lewat WhatsApp, langsung, atau email), staf mencatat **Setuju** atau **Minta revisi** (wajib catatan) beserta kanalnya. Setelah **Disetujui, desain terkunci**: unggahan baru ditolak. Bila pelanggan berubah pikiran, klik **Ajukan Buka Revisi** dengan alasan; Administrator memutuskan di Approval Workflow, baru revisi berikutnya bisa diunggah. **Penjaga produksi bersifat opsional per item:** item yang sudah punya proof tidak bisa naik ke Cetak atau Selesai di Produksi sebelum proof terakhirnya disetujui; item yang tidak memakai proof tidak terpengaruh.
3a. **QC & Rework** (menu Produksi & Logistik > QC & Rework): periksa hasil cetak per item (status Cetak/Selesai). Isi qty diperiksa, qty reject, dan centang poin bermasalah (ukuran, warna, posisi desain, jumlah, finishing, cacat cetak). Jika ada reject, **Rework otomatis dibuka**. Klik **Proses Rework** saat pengerjaan ulang dimulai: material tambahan sesuai BOM dikeluarkan dari stok, tercatat di Kartu Stok, dan dijurnal sebagai Beban Rework & Susut Bahan (akun 5200). Unit hasil rework dapat di-QC ulang setelah rework diproses. Stok yang tidak cukup untuk rework menggagalkan proses tanpa efek samping.
4. **Kasir:** pembayaran diterima lewat menu Kasir (Monitoring Kasir): tunai, transfer bank, atau deposit konsumen; DP dan cicilan didukung. Pembayaran tidak boleh melebihi sisa tagihan nota (sistem menolak dengan pesan sisa tagihan). Piutang dapat dilihat di Laporan Piutang.
5. **Collecting (serah terima):** daftar pesanan siap diambil. Saat barang diserahkan, klik tanda centang dan isi **jenis serah terima** (diambil sendiri, diambil perwakilan, atau dikirim), **nama penerima** (wajib untuk perwakilan/dikirim), catatan, dan **foto bukti** (opsional). Catatan ini menjadi Delivery Proof di Berkas Order dan muncul di Timeline Pelanggan.
5a. **Berkas Order** (tombol di halaman detail nota): semua berkas satu nota dalam satu tempat, dikelompokkan: Design (thumbnail), Proof, Approved Design (dari Design Proof), Production File, QC Photo, Lainnya (diunggah di halaman ini: JPG/PNG/WEBP/PDF/ZIP/AI/PSD/CDR/EPS, maks 50 MB), dan Delivery Proof. Berkas berfungsi sebagai bukti sehingga tidak bisa dihapus dari sini.
6. **Penjualan Barang:** menjual bahan baku langsung (khusus Kasir), otomatis masuk nota dan jurnal.
7. **Notifikasi antrean:** halaman SPK, Produksi, dan Collecting menampilkan banner saat ada data baru dari divisi lain; klik banner untuk memuat ulang.

**Nota salah:**
- **Batalkan nota:** dari Riwayat Penjualan, ajukan pembatalan dengan alasan. Setelah Administrator menyetujui, nota berstatus Dibatalkan, stok dikembalikan (jika dipilih), dan jurnal pendapatan/HPP nota dibalik otomatis. Uang yang sudah benar-benar diterima (tunai/transfer/deposit) TETAP tercatat di kas/bank — nota batal berarti pendapatannya hangus, bukan uangnya hilang; pengembalian uang ke konsumen dicatat manual (mis. lewat Kas Tak Terduga / pengurangan deposit) dan terlihat di Laporan Kas.
- **Pengembalian stok selalu sesuai mutasi asli:** qty yang kembali saat pembatalan diambil dari catatan pemakaian yang disimpan saat order dibuat — bukan dihitung ulang dari BOM saat ini. Artinya mengubah BOM atau harga bahan SETELAH order dibuat tidak mengubah jumlah pengembalian nota lama. Satu item hanya bisa direversal satu kali (batal → antrekan ulang → batal lagi tidak mengembalikan stok dobel). Setiap reversal tercatat di Log Aktivitas (event "stok_reversal").
- Nota yang sudah Dibatalkan tidak bisa menerima pembayaran baru dan tidak bisa dikoreksi lewat alur Edit Nota.
- **Edit nota:** ajukan permintaan edit dari Riwayat Penjualan; setelah disetujui Administrator, edit diterapkan dan jurnal dikoreksi.

**Timeline Pelanggan:** di Data Konsumen, tombol ikon garis waktu pada baris pelanggan membuka ringkasan hubungan bisnis (total omzet, sudah dibayar, piutang, saldo deposit, order terakhir, nota belum diambil) dan riwayat berurut waktu: penawaran, nota, pembayaran, deposit, dan order Jersey. Halaman ini hanya untuk dibaca; nota dan penawaran di dalamnya bisa diklik. Nota yang dibatalkan tampil merah dan tidak dihitung ke omzet/piutang.

## 7. Kas dan keuangan harian

- **Buka/Tutup Kas:** kas harian dibuka satu orang saja per hari (eksklusif). Saldo awal, transaksi tunai, dan kas manual dicatat di sesi itu. Hari Minggu libur kas jika diaktifkan di Pengaturan Aplikasi. Di akhir hari lakukan **Closing** dan cetak laporannya.
- **Kas Kecil:** catat pengeluaran kecil per kategori (Kategori Kas Kecil menentukan akun beban di jurnal). Membatalkan catatan hanya berupa pengajuan ke Approval Workflow; jurnalnya dibalik setelah disetujui.
- **Deposit Konsumen:** setoran deposit dicatat sebagai kewajiban dan dapat dipakai membayar nota.
- **Laporan:** Order, Produksi, Kas Utama, Belanja Bahan, Hutang Supplier, Piutang, Stok Bahan Baku, HPP & Margin, **Job Costing** (biaya aktual per nota = HPP material + rework + waste terkait, dibanding omzet; tenaga kerja, mesin, dan overhead belum tercatat), Order Jersey, dan Laporan Keuangan (Akuntansi).

**Dashboard Eksekutif (khusus Administrator, sidebar di bawah Dashboard):** ringkasan periode (default bulan berjalan): omzet, biaya aktual, laba kotor dan margin, omzet Jersey; saldo Kas, Bank, Piutang, dan Hutang dari buku besar; grafik omzet 6 bulan; top 5 pelanggan dan produk; peringatan (pengajuan menunggu, bahan di bawah stok minimal, PO lewat estimasi, faktur hutang jatuh tempo, nota belum lunas > 7 hari, rework menunggu); dan **Estimasi Bulan Depan** (rata-rata 3 bulan penuh, acuan kasar, bukan prediksi). **Cara membaca:** laba kotor = omzet - (HPP material + rework + waste); tenaga kerja, mesin, overhead, dan beban operasional belum tercatat sehingga bukan laba bersih, dan akurasinya bergantung pada BOM serta harga rata-rata bahan yang terisi benar.

**Laporan Profitabilitas:** laba kotor dan margin per produk/jasa, pelanggan, atau mesin/divisi untuk rentang tanggal. **Analisis Pemakaian Bahan:** stok keluar per bahan dipisah pemakaian order, rework, dan waste, dengan persentase dan nilai susut.

## 8. Order Jersey

Order Jersey (Order Jersey > Daftar Order, Master Data Jersey, Keuangan Jersey) memiliki modul sendiri untuk order, komponen, ukuran, dan pembayaran, dan **terhubung ke akuntansi**:

- Ukuran jersey **sumber kebenarannya satu**: Master Data Jersey → Ukuran. Ukuran baru yang ditambahkan langsung bisa dipakai di form order (pemain & qty per ukuran) tanpa perubahan kode. Ukuran yang dinonaktifkan tetap tampil di order lama, tapi tidak lagi bisa dipilih untuk order baru.
- **Lengan dan Kerah wajib dipilih** (bukan opsional). Untuk barang tanpa lengan/kerah (mis. Celana) pilih master **"Tanpa Lengan" / "Tanpa Kerah"** dari Master Data Jersey → Tipe Lengan / Tipe Kerah — pilihannya tercatat eksplisit di database, bukan dibiarkan kosong. Dropdown tidak disembunyikan dan tidak ada nilai default diam-diam; form menolak simpan bila belum dipilih.
- **Tabel pemain diurutkan sesuai urutan master ukuran** (S, M, L, XL, ...) alih-alih urutan input, di halaman detail order maupun cetakan SPK, supaya mudah difilter dan diperiksa per ukuran.
- **Import CSV Daftar Pemain**: di kartu barang mode Daftar Pemain (form order baru, edit barang, dan form Barang Susulan) tersedia tombol **Import CSV** dan **Template CSV**. Template tetap (juga bisa diunduh lewat tombol): `nama;nomor;ukuran;gender;lengan;kerah` - pemisah titik koma, satu baris satu pemain, baris header opsional. `ukuran`, `lengan`, dan `kerah` HARUS sama dengan nama di Master Data Jersey (pencocokan tidak peduli huruf besar/kecil); barang tanpa lengan/kerah tetap wajib menuliskan master `Tanpa Lengan` / `Tanpa Kerah`; `gender` diisi `L` atau `P` (kosong dianggap `L`); `nomor` boleh kosong. Validasi dilakukan sebelum form diisi - kalau ada satu baris saja yang gagal, TIDAK ADA baris yang diubah dan daftar error per baris ditampilkan di bawah tabel pemain. Import mengganti seluruh Daftar Pemain pada kartu itu dengan isi CSV.
- **CSV sebagai pedoman macro .GMS CorelDRAW**: format kolom di atas sengaja dibuat tetap supaya file CSV yang sama bisa dipakai macro otomasi layouting jersey di CorelDRAW (satu baris = satu sisi cetak pemain: nama + nomor untuk desain, `ukuran` untuk dimensi artboard/template sesuai master ukuran). Jangan mengubah urutan kolom atau pemisahnya; macro membaca file dengan asumsi yang sama dengan form import ini.
- **Cetakan nota ringkasan A4** menampilkan nama pelanggan paling atas dengan detail order rapat di sebelahnya (label dan nilai berdampingan), bukan label kiri dan nilai terdorong ke tepi kanan, agar mudah dibaca.
- Nomor order jersey selalu naik (tidak pernah dobel walaupun dua operator menyimpan bersamaan, dan nomor bekas order yang dihapus tidak dipakai ulang).

- Total order diakui sebagai piutang dan pendapatan di akun **Pendapatan Order Jersey (4300)**. Jika line order berubah, sistem hanya memposting selisihnya.
- Pembayaran Tunai masuk Kas; Transfer/QRIS/Lainnya masuk Bank. Pembayaran mengurangi piutang.
- **Hapus pembayaran** dan **hapus order yang sudah bernilai atau sudah ada pembayaran** hanya berupa pengajuan ke Approval Workflow (jurnal dibalik saat disetujui). Order yang punya pembayaran hanya bisa dibatalkan setelah pembayarannya di-void. Order kosong (belum bernilai) masih bisa dihapus langsung.
- Data Jersey lama (sebelum integrasi ini) tidak di-backfill ke jurnal; data uji direset sebelum go-live.

## 9. Akuntansi dan tutup buku bulanan

1. Pastikan semua transaksi bulan berjalan sudah dicatat dan kas sudah closing.
2. Periksa Laporan Keuangan dan jalankan `php artisan audit:integritas` (jurnal balance, saldo, sinkron kartu stok).
3. Administrator membuka **Akuntansi > Accounting Period**, lalu **Tutup Periode** bulan tersebut.
4. Koreksi terhadap bulan yang sudah ditutup: Administrator membuka kembali periode itu, memposting koreksi, lalu menutup lagi. Semuanya tercatat di Log Aktivitas.

## 10. Administrasi sistem (Administrator)

- **Approval Workflow:** daftar pengajuan Menunggu. Setujui untuk mengeksekusi, atau Tolak dengan alasan. Jenis pengajuan saat ini: pembatalan nota, Stock Opname, Purchase Request, pembatalan faktur pembelian, void pembayaran hutang, void pembayaran Jersey, pembatalan order Jersey, pembatalan catatan Kas Kecil, Waste Abnormal, pembukaan revisi desain yang sudah terkunci, dan penyesuaian stok manual.
- **Log Aktivitas:** telusuri perubahan data, filter menurut jenis, kejadian, dan tanggal. Serah terima barang di modul Collecting juga tercatat di sini (event "pengambilan_barang").
- **Anti klik-ganda pembayaran:** tombol bayar terkunci sesaat setelah diklik, dan server menolak submit dengan ID yang sama datang dua kali. Pembayaran berkala (DP lalu pelunasan) tetap normal karena setiap klik punya ID berbeda.
- **Backup & Recovery:** backup otomatis harian, mingguan, dan bulanan (retensi 7/4/12) dan tombol backup manual. Otomatis hanya berjalan jika scheduler aktif (lihat "Sebelum Deploy ke Server/VPS" di bawah). File backup di `storage/app/backups/` berisi data konsumen - berada di disk yang sama dengan aplikasi, jadi atur `BACKUP_SECONDARY_DISK` di `.env` (lihat `config/backup.php`) supaya setiap backup otomatis tersalin ke lokasi kedua di luar server; kalau belum diatur, salin manual secara berkala. Kolom "Lokasi Kedua" di halaman Backup menunjukkan status salinannya.
- **Manajemen User dan Setting Akses:** buat akun per karyawan, atur menu per peran. Password minimal 8 karakter. Akun yang masih tercatat sebagai kasir/pembatal nota tidak bisa dihapus (riwayat harus tetap utuh) — nonaktifkan lewat Setting Akses atau ganti rolenya.
- **Status backup:** riwayat di menu Backup menampilkan **Berjalan** (proses masih berjalan / tidak selesai dengan bersih — perlu diperiksa), **Sukses**, atau **Gagal**.
- **Kesiapan data besar:** database sudah memiliki index khusus untuk tabel yang tumbuh terus (jurnal akuntansi: per akun+tanggal dan per nomor referensi; riwayat pembayaran: per tanggal), dan filter tanggal di seluruh modul memakai rentang yang memanfaatkan index. Aplikasi tetap responsif saat transaksi mencapai ratusan ribu baris — tidak ada tindakan khusus yang perlu dilakukan operator.

## 11. Hal yang belum tercakup (saat ini)

- **Stock Transfer antar gudang:** ditunda karena stok masih satu angka global dan hanya ada satu gudang aktif.

## 12. Bila ada masalah

| Gejala | Yang dilakukan |
|---|---|
| Muncul 403 | Peran Anda belum diberi akses menu itu; minta Administrator. |
| "Periode akuntansi ... sudah ditutup" | Bulan itu sudah ditutup; minta Administrator membuka periodenya bila memang perlu koreksi. |
| Tidak bisa membatalkan faktur/PO | Cek pesan: ada pembayaran aktif (void dulu) atau barang sudah diterima. |
| Tombol Bayar Hutang menolak nominal | Nominal tidak boleh melebihi sisa hutang. |
| Kas tidak bisa dibuka | Kas hanya boleh aktif satu sesi; pastikan sesi lain sudah closing. |
| Stok tidak sesuai fisik | Lakukan Stock Opname, jangan mengubah lewat database. |

## Data Dummy untuk Uji Coba

Untuk menguji seluruh alur tanpa mengetik manual, jalankan di terminal proyek:

```
php artisan demo:isi
```

- Membuat: 8 pelanggan "DEMO ...", sesi kas hari ini, ~9 nota dengan status SPK bervariasi, pembayaran (lunas/DP/belum), serah terima, design proof, QC + rework, waste, 3 quotation, PR/PO/kedatangan/faktur tempo/cicilan hutang, kas kecil, deposit, dan 2 order jersey.
- Semua lewat jalur aplikasi sungguhan, sehingga stok dan jurnal tetap konsisten.
- Data riil (bahan baku, mesin, kategori, supplier, jasa, produk) hanya dibaca, tidak diubah.
- Otomatis membuat backup dulu; menolak jalan di production; menolak bila data DEMO sudah ada (kecuali `--tambah`).
- Data dummy ini dibersihkan saat persiapan go-live (reset data uji dilakukan terpisah dengan konfirmasi).

## Input Harga Jersey Cepat (per Tipe Produk)

Master Jersey > tab **Tier Harga** > kotak hijau **Input Cepat per Tipe Produk**:

1. Pilih Tipe Produk (bahan boleh dikosongkan = harga umum semua bahan).
2. Tier yang sudah ada langsung terisi. Ubah, tambah baris, atau klik **Pita standar (lusin)** untuk 1-11, 12-23, 24-49, 50+.
3. Klik **Simpan Semua Tier**. Berlaku untuk semua kategori, tanpa memilih kategori.

Harga tier khusus bahan (mis. Brazil) selalu didahulukan; bila tidak ada, dipakai tier umum tipe itu.
Tier yang dibatasi ke kategori tertentu tidak diubah oleh Input Cepat. Rentang qty tidak boleh tumpang tindih.

Master jersey sudah dilengkapi: tipe Celana Panjang, Kaos Kiper, Polo; kategori Jersey Sablon DTF & Bordir;
bahan Benzema, Dryfit, Jacquard, Lacoste Pique; serta harga umum acuan per tipe (sesuaikan dengan harga Anda).

## Menjalankan Server Tanpa XAMPP Control Panel (pemakaian bersama)

Sekali saja, di komputer server: klik kanan `C:\xampp\orion_install_service.bat` > **Run as administrator**.
Skrip memasang MariaDB dan Apache sebagai Windows Service (start otomatis saat boot, restart sendiri bila mati,
dimatikan rapi oleh Windows saat shutdown/restart, port tetap 80 dan 3306, firewall lokal terbuka).
Setelah itu XAMPP Control Panel tidak dipakai lagi.

- Alamat untuk komputer lain: `http://IP-SERVER/percetakan-app` (beri IP tetap/reservasi DHCP di router).
- Shutdown atau restart Windows biasa sudah aman.
- Kembali ke cara lama: `orion_uninstall_service.bat` (Run as administrator); data tidak disentuh.
- Tanpa service, gunakan `orion_start.bat` dan `orion_stop.bat` (selalu matikan lewat orion_stop.bat).

## Harga Awal Bahan Baku & Koreksi Harga

- **Registrasi bahan baru:** isi "Harga Beli per Satuan Stok" (opsional). Menjadi harga rata-rata awal (dasar HPP). Bila Stok Awal juga diisi, nilainya dijurnal sebagai saldo awal persediaan (Dr Persediaan / Cr Modal); centang bisa dimatikan bila saldo awal akuntansi akan diisi sekaligus lewat `akuntansi:saldo-awal`.
- **Bahan yang sudah ada tanpa harga:** di Master Bahan Baku, klik tulisan "Belum ada harga" (atau filter Harga = Belum ada harga) lalu isi harga dan alasan. Hanya mengubah dasar HPP ke depan; tidak mengubah stok dan tidak membuat jurnal. Tercatat di Log Aktivitas.
- **Tinta/toner:** sudah didaftarkan per mesin (stok 0, harga kosong). Acuan pemakaian per m2 ada di `docs/ACUAN_PEMAKAIAN_TINTA.md`.

## Laporan Biaya Tinta

Menu Laporan > **Biaya Tinta** (akses sama dengan Laporan HPP). Memilih periode, lalu menampilkan:
- Kartu ringkas: total biaya tinta, total luas cetak, biaya tinta per m².
- Pemakaian per bahan tinta (volume cetak, jumlah terpakai, biaya).
- Biaya tinta per produk: luas, omzet, biaya, biaya per m², dan persen dari omzet.

Cara hitung: luas cetak dari pesanan (panjang × lebar × qty, nota dibatalkan dikecualikan) × pemakaian per m² (atau per lembar untuk toner) ×
harga rata-rata bahan tinta (sudah dikonversi dari satuan hitung ke satuan stok). Perkiraan; tidak mengubah stok maupun HPP order.
Pemakaian per mesin bisa diubah di bagian bawah halaman ("Pengaturan Pemakaian per Mesin"); angka awal adalah acuan
(`docs/ACUAN_PEMAKAIAN_TINTA.md`). Bahan tinta yang harganya masih kosong diberi peringatan dan dihitung Rp 0.

## Tampilan Sidebar

- **Mode lebar:** logo perusahaan besar di bagian atas (tanpa nama, karena sudah ada di top bar), menu berwarna kontras tinggi, halaman aktif ditandai gradasi ungu, dan grup yang berisi halaman aktif diberi titik penanda.
- **Mode ringkas (tombol ☰ di top bar):** berupa rel ikon besar per grup. Arahkan kursor (atau klik) ikon grup untuk melihat daftar menunya dalam panel melayang; ikon menu tunggal menampilkan tooltip nama. Pilihan mode diingat di browser.

**Catatan teknis untuk server mana pun:** tampilan memakai aset yang dibangun oleh Vite (folder `public/build`, tidak masuk git).
Setelah menarik kode terbaru atau mengubah tampilan, jalankan `npm install` (sekali) lalu `npm run build`; tanpa itu kelas tampilan baru tidak termuat dan halaman terlihat berantakan.

**Logo sidebar dapat diatur:** Pengaturan Aplikasi > Umum > "Tampilan Logo di Sidebar" - ukuran (kecil/sedang/besar), gaya latar
(polos dengan kilau lembut, kaca transparan, atau kotak putih), cara logo tampil saat sidebar diringkas (potong lambang kiri otomatis atau
logo penuh diperkecil), serta Logo Ikon persegi opsional. Ada pratinjau langsung di latar sidebar.

## Lembar Evaluasi Master (Cetak Harian/Bulanan)

Menu Laporan > **Lembar Evaluasi Master** (akses sama dengan Laporan HPP). Memuat dalam satu lembar:
- **A. Bahan Baku:** stok, stok minimal, harga rata-rata, nilai persediaan, penanda stok menipis dan harga kosong, serta kolom **Stok Fisik / Selisih** untuk cek harian (bisa dimatikan).
- **B. BOM per Produk:** pemakaian dan biaya tiap bahan untuk 1 satuan jual (1 m² atau 1 pcs) beserta HPP bahan.
- **C. Harga Produk:** semua tier per golongan dengan rentang masing-masing, beserta margin terhadap HPP bahan (merah bila di bawah 20%) dan penanda promo.

Pilih isi lembar (semua atau salah satu), untuk evaluasi Harian/Bulanan, mesin, dan kategori; klik **Cetak** (A4 lanskap, kop perusahaan, kolom tanda tangan) atau **Unduh CSV** untuk Excel. Angka HPP baru lengkap setelah harga rata-rata semua bahan dan BOM terisi.

## Sesi Kas: Tetap Open Sampai Closing

Kas kasir **tetap Open sampai di-closing**, bukan otomatis standby saat ganti tanggal:
- Sidebar dan semua transaksi (order, pembayaran, kas tidak terduga) memakai sesi yang masih Open, walau dibuka kemarin.
- Rekap closing dan pembayaran dihitung dari **waktu sesi dibuka sampai closing**, jadi pembayaran setelah tengah malam ikut terhitung.
- Sesi Open lebih dari **24 jam** ditandai amber ("Kasir Open >24 jam") di sidebar dan Kelola Kas sebagai pengingat closing.
- Sesi baru tidak bisa dibuka selama sesi lama belum di-closing. **Administrator dapat tutup paksa** sesi kasir lain (alasan wajib, tercatat di Log Aktivitas).
- Hari Minggu libur (bila diaktifkan) memblokir transaksi dan pembukaan sesi baru, tetapi **closing tetap boleh** supaya sesi hari Sabtu bisa ditutup.

## Format Angka (Aturan Tunggal)

- **Tampilan** (tabel, laporan, kartu, pesan) selalu format Indonesia: titik = ribuan, koma = desimal (`200.000`, `12,5`, `Rp 1.300.000`). Nol hanya dipotong dari pecahan (`12,50` menjadi `12,5`), tidak pernah dari bilangan bulat. Di kode gunakan `angka()`, `rupiah()`, `angka_polos()`, `angka_input()` (lihat `app/Support/Angka.php`); jangan menulis `number_format` bergaya Amerika atau `rtrim` pemotong nol.
- **Input uang** memakai pemisah ribuan **hanya saat mengetik**: `format-ribuan` (rupiah utuh) atau `format-ribuan-desimal` (boleh koma desimal, mis. `281.057,64`). Yang terkirim ke server selalu angka polos tanpa pemisah (`281057.64`), termasuk saat form dikirim lewat `form.submit()` dan saat kolom dibiarkan kosong (tetap kosong).
- **Input jumlah/ukuran** (stok, qty, konversi, lebar, panjang) tetap `type=number` tanpa pemisah ribuan supaya tidak rancu dengan titik desimal; nol di belakang koma dibuang otomatis (`226.000` tampil `226`).
- Penjaga otomatis di `tests/Feature/FormatAngkaTest.php`: menolak `number_format` gaya Amerika, pemotong nol yang salah, dan input uang tanpa pemisah ribuan.

## Commit Dump Database (satu perintah)

Setiap kali data/struktur database lokal berubah dan ingin disimpan ke git bersama kode:

```
composer db-commit          # dump oriondb.sql + commit (kalau dump berubah)
composer db-commit-push    # dump + commit + push origin main
composer db-commit "pesan"  # dump + commit dengan pesan sendiri
```

Setara juga bisa langsung: `php scripts/db_dump_commit.php [--push] ["pesan"]`.

- Koneksi dibaca dari `.env` (tanpa kredensial di repo); `mysqldump` dicari di PATH, lalu lokasi standar XAMPP/LAMPP, atau dipaksa lewat env `MYSQLDUMP_BIN`.
- Dump deterministik (`--skip-dump-date`): database yang tidak berubah menghasilkan file identik - skrip **tidak membuat commit kosong**.
- Dump ditulis ke file sementara dulu (atomik): kalau mysqldump gagal, `oriondb.sql` lama tetap utuh.
- File sementara `oriondb.sql.tmp` tidak ikut ter-commit (sudah dikecualikan di `.gitignore`).

## Sebelum Deploy ke Server/VPS

Checklist teknis - hanya dijalankan setelah aplikasi dinyatakan sempurna dan siap online (lihat memori proyek):

1. **`.env` produksi:** `APP_ENV=production`, `APP_DEBUG=false` (sebelum ini, error apa pun tampil lengkap ke pengunjung - kebocoran informasi). `SESSION_SECURE_COOKIE=true` bila situs sudah HTTPS.
2. **Cache konfigurasi & rute** - dijalankan ulang setiap kali `.env`/route/config berubah setelahnya (`config:clear` dulu sebelum edit `.env`, baru bangun cache lagi):
   ```
   php artisan app:config-cache
   php artisan route:cache
   php artisan view:cache
   ```
   **Pakai `app:config-cache`, bukan `config:cache`.** Perintah bawaan menulis berkas cache konfigurasi tanpa atomik: ada saat berkasnya terisi separuh, dan permintaan web yang kebetulan membacanya mendapat error 500 "No application encryption key has been specified" di SELURUH halaman. Perintah `app:config-cache` menulis secara atomik (tulis berkas sementara lalu rename), membersihkan environment yang diwarisi dari proses lain, dan MENOLAK menulis cache kalau `app.key` kosong atau konfigurasinya menunjuk database uji.

   > **Di komputer development cache konfigurasi TIDAK boleh di-build.** Konfigurasi ter-cache mengalahkan `phpunit.xml`, sehingga test otomatis bisa berjalan di database asli dan mengosongkannya. Kalau `app:config-cache` dipakai di sini, jalankan `php artisan config:clear` SETELAH test selesai - jangan tinggalkan cache-nya.
3. **Scheduler benar-benar berjalan** - jadwal `Schedule::command(...)` di `routes/console.php` (backup harian/mingguan/bulanan) tidak aktif dengan sendirinya. Perlu cron (`* * * * * php artisan schedule:run`) atau Windows Task Scheduler tiap menit.
4. **`BACKUP_SECONDARY_DISK`** diatur ke disk di luar server (lihat bagian Backup & Recovery di atas) - backup yang hanya ada di server yang sama tidak melindungi dari kerusakan server itu sendiri.
5. **Build aset produksi:** `npm install && npm run build` di server (folder `public/build` tidak ikut git).
6. **Backup database dulu** sebelum migrasi pertama di server baru, dan reset data uji (bukan data riil) bila belum dilakukan.
7. **`akuntansi:saldo-awal`** dijalankan dengan angka saldo awal yang sudah disepakati, kalau belum dilakukan sebelumnya.
