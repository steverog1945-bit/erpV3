# Template CSV Daftar Pemain Jersey (form + macro .GMS CorelDRAW)

Template ini TETAP: dipakai (1) tombol **Import CSV** pada form order jersey
untuk mengisi Daftar Pemain otomatis, dan (2) sebagai pedoman macro `.GMS`
CorelDRAW untuk otomasi layouting (satu baris CSV = satu sisi cetak pemain).

## Format

```
nama;nomor;ukuran;gender;lengan;kerah
Ahmad Faiz;10;XL;L;Pendek;O-Neck
Bima Saputra;7;L;L;Pendek;O-Neck
Citra Dewi;3;S;P;Tanpa Lengan;Tanpa Kerah
```

- **Pemisah kolom: titik koma `;`** (hasil simpanan default Excel Indonesia),
  BUKAN koma. Urutan kolom tidak boleh diubah.
- Baris pertama boleh berupa header (`nama;nomor;...`) - otomatis dilewati.
- Satu baris = satu pemain. Baris kosong diabaikan.
- `nama` wajib diisi.
- `nomor` boleh kosong, kalau diisi harus angka 1-3 digit.
- `gender`: `L` atau `P`; kosong dianggap `L`.
- `ukuran`, `lengan`, `kerah` HARUS sama dengan nama di Master Data Jersey
  (Ukuran / Tipe Lengan / Tipe Kerah). Pencocokan tidak peduli huruf
  besar/kecil dan spasi berlebih (`xl`, `XL`, ` x l ` sama-sama cocok ke XL).
- Barang tanpa lengan/kerah (mis. Celana) tetap wajib menuliskan master
  `Tanpa Lengan` / `Tanpa Kerah` - sama seperti aturan wajib pilih di form.
- Nilai yang mengandung titik koma dibungkus tanda kutip ganda: `"Nama; alias"`.

## Perilaku tombol Import CSV di aplikasi

- Ada di setiap kartu barang mode Daftar Pemain: form order baru, edit barang
  order, dan form Barang Susulan (modal).
- Tombol **Template CSV** mengunduh `template-daftar-pemain-jersey.csv`.
- Validasi dilakukan sebelum form disentuh - **atomik**: kalau ada satu baris
  saja yang gagal, tidak ada baris yang diubah; daftar error per baris
  (dengan nomor baris file) tampil di bawah tabel pemain.
- Import berhasil = seluruh Daftar Pemain pada kartu itu **diganti** dengan
  isi CSV. Baris pemain yang sudah diinput manual akan tergantikan.
- Setelah import, periksa kembali lalu simpan form seperti biasa - validasi
  server (ukuran/lengan/kerah wajib & terdaftar) tetap berlaku.

## Pedoman macro .GMS CorelDRAW (otomasi layouting)

Format kolom sengaja dikunci agar macro tidak perlu menebak:

- `nama` + `nomor` → teks yang diganti/diisi pada desain satu sisi cetak.
- `ukuran` → pemilih dimensi/template artboard sesuai master ukuran
  (urutan master: sort_order di tabel `jersey_sizes`).
- `gender` → pembeda pola/komposisi bila desainnya berbeda untuk P.
- `lengan` / `kerah` → pemilih objek/varian pada template (termasuk
  `Tanpa Lengan`/`Tanpa Kerah`).

Prinsip pembacaan di macro (samakan dengan parser web agar hasil identik):

1. Baca file sebagai UTF-8 (form web menangani BOM UTF-8 otomatis).
2. Baris pertama yang mengandung kata `nama` + (`ukuran` atau `nomor`)
   dianggap header dan dilewati.
3. Pecah per baris (terima CRLF maupun LF), lalu pecah kolom per `;`
   dengan dukungan tanda kutip ganda (`""` = kutip literal).
4. Trim tiap nilai, bandingkan nama master dengan huruf kecil + spasi
   dirapatkan (`\s+` → satu spasi).
5. Satu baris valid = satu sisi cetak; baris tidak valid dilaporkan
   lengkap dengan nomor barisnya, jangan diproses sebagian.

## Contoh file salah (ditolak, semua error ditampilkan)

```
nama;nomor;ukuran;gender;lengan;kerah
Ahmad Faiz;10;XL;L;Pendek;O-Neck
Bima; tujuh;M;L;Pendek;O-Neck
Citra Dewi;3;XXXL;P;Tanpa Lengan;Tanpa Kerah
```

Error yang muncul:
- `Baris 3: nomor punggung "tujuh" tidak valid (isi angka, mis. 10).`
- `Baris 4: ukuran "XXXL" tidak ada di Master Data Ukuran.`

## Catatan teknis (implementasi web)

- Parser + validasi: `resources/views/jersey/orders/_csv-pemain-tools.blade.php`.
  Master dibaca dari option dropdown template baris pemain
  (`template_baris_pemain_jersey`), jadi master ukuran/lengan/kerah yang
  nonaktif otomatis tidak bisa diimport - konsisten dengan dropdown form.
- Tombol & panel hasil per kartu: `_csv-pemain-card-buttons.blade.php` dan
  `_csv-pemain-hasil-panel.blade.php`, dipasang lewat
  `_line-card-fields.blade.php`.
- Payload yang dikirim ke server tidak berubah sama sekali (masih
  `lines[*][items][*][player_name|player_number|size|gender|
  jersey_sleeve_type_id|jersey_collar_type_id]`), jadi tidak ada perubahan
  validasi/penyimpanan di sisi backend.
