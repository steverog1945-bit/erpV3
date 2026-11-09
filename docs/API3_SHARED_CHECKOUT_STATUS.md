# API-3 — Ekstraksi checkout bersama (langkah 1)

Branch refactor ini memindahkan implementasi store web, validasi item, penulisan detail-finishing, serta pemakaian BOM/stok/HPP ke \`App\Services\PenjualanCheckoutService\`. PenjualanController tetap menggunakan service yang sama lewat delegasi, termasuk jalur edit-selisih. Seluruh helper domain yang dipindahkan merupakan salinan verbatim, kecuali perubahan visibilitas dari private ke public agar delegasi controller dapat berfungsi. API controller belum terhubung, POST /api/transaksi tetap HTTP 503; API gate tetap default OFF.

## Verifikasi wajib sebelum merge

- Jalankan tes terarah untuk order, BOM, kartu_stok, accounting, deposit, nota susulan, edit selisih, dan konkurensi pada DB \`percetakan_erp_testing\`.
- Jalankan \`php -l\` untuk dua berkas PHP yang diubah, kemudian \`php artisan test\` serial penuh; baseline 1.378 test.
- Pastikan alur web tetap membuat satu nota, satu penarikan stok dengan konversi, jurnal balance, deposit otomatis ketika operator memiliki sesi kas; tidak ada perubahan UI.
- Lakukan review sisi request/session karena service masih menerima Request dan mencatat flash ke session web. Ini transisi, bukan arsitektur final.
- API adapter berikutnya harus memakai service ini dengan konteks aktor autentikasi API yang eksplisit, validasi produk aktif dan golongan, pembatasan input biaya, dan idempotensi X-Submit-Id yang persisten/atomik. Jangan buka API sampai tes paritas dan konkurensi lolos.

**Jangan menjalankan tes destruktif ke oriondb. Jangan merge sebelum hasil test lokal PASS.**
