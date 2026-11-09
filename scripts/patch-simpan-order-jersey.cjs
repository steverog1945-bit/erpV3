/* =====================================================================
   PATCH: "Simpan Order Jersey tidak berfungsi"
   ---------------------------------------------------------------------
   CARA PAKAI
     1. Letakkan file ini di <project>/scripts/
     2. Jalankan:  node scripts/patch-simpan-order-jersey.cjs
     3. Aman dijalankan berkali-kali (idempoten). Kalau sudah terpasang,
        skrip akan melompat dan melaporkan "SUDAH ADA".

   AKAR MASALAH
     <select required> di dalam wrapper yang disembunyikan (display:none).
     Browser menjalankan constraint validation SEBELUM event submit
     menyala. Saat <select required> kosong DAN tersembunyi, Chrome tidak
     bisa memfokuskannya lalu membatalkan submit tanpa pesan apa pun:

       An invalid form control with name='lines[0][jersey_sleeve_type_id]'
       is not focusable.

     Akibatnya klik "Simpan Order" diam-diam tidak terjadi apa-apa:
     tidak ada request ke server, tidak ada pesan error.

     Diperparah oleh lapis server: rules() mewajibkan
     lines.*.jersey_sleeve_type_id untuk SEMUA mode, padahal mode
     "Daftar Pemain" menyembunyikan dropdown itu dari UI.

   FILE YANG DISENTUH (4)
     resources/views/jersey/orders/create.blade.php
     resources/views/jersey/orders/edit-lines.blade.php
     resources/views/jersey/orders/show.blade.php
     app/Http/Controllers/Jersey/JerseyOrderController.php

   PENTING: tiga halaman Blade di atas sama-sama meng-include
   _line-card-fields.blade.php, jadi tiga-tiganya punya dropdown Lengan/Kerah
   `required` yang disembunyikan di mode Daftar Pemain. Kalau hanya create
   yang dipatch, dua halaman lain tetap gagal simpan tanpa pesan.
   ===================================================================== */

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');

/* ---------------------------------------------------------------------
   Utilitas: baca file dengan jejak line ending asli, lalu tulis kembali
   dengan line ending yang sama. Ini penting karena file-project bisa
   LF atau CRLF; mencampur keduanya bikin diff jadi berantakan.
   --------------------------------------------------------------------- */
function baca(f) {
  const isi = fs.readFileSync(f, 'utf8');
  return { isi, crlf: isi.includes('\r\n') };
}
function tulis(f, isi, crlf) {
  fs.writeFileSync(f, crlf ? isi.split('\n').join('\r\n') : isi, 'utf8');
}

/* Ganti satu kemunculan. Idempoten: kalau `setelah` sudah ada, skip. */
function terapkan(f, label, sebelum, sesudah, wajib = 1) {
  const { isi, crlf } = baca(f);
  if (isi.includes(sesudah)) {
    console.log('    [lewat]  ' + label + '  (sudah ada)');
    return true;
  }
  const n = isi.split(sebelum).length - 1;
  if (n === 0) {
    console.log('    [GAGAL]  ' + label + '  (pola tidak ditemukan)');
    return false;
  }
  if (n !== wajib) {
    console.log('    [GAGAL]  ' + label + '  (diharapkan ' + wajib + ' kemunculan, ketemu ' + n + ')');
    return false;
  }
  tulis(f, isi.split(sebelum).join(sesudah), crlf);
  console.log('    [pasang] ' + label);
  return true;
}

let gagal = 0;

/* =====================================================================
   1. create.blade.php
   ===================================================================== */
console.log('\n[1] resources/views/jersey/orders/create.blade.php');

const fCreate = path.join(root, 'resources', 'views', 'jersey', 'orders', 'create.blade.php');

/* 1a. Lepas `required` saat wrapper lengan/kerah disembunyikan.
        sumber dari _line-card-fields.blade.php baris 51-66. */
terapkan(
  fCreate,
  'required ikut visibilitas wrapper lengan/kerah',
  `        const orderType = card.querySelector('input[name$="[order_type]"]:checked')?.value || 'quantity_only';
        const tampilkanDefault = orderType !== 'player_list';
        card.querySelectorAll('.wrapper-lengan-kerah-default').forEach(el => el.classList.toggle('hidden', !tampilkanDefault));
    }`,
  `        const orderType = card.querySelector('input[name$="[order_type]"]:checked')?.value || 'quantity_only';
        const tampilkanDefault = orderType !== 'player_list';
        card.querySelectorAll('.wrapper-lengan-kerah-default').forEach(el => {
            el.classList.toggle('hidden', !tampilkanDefault);
            // Atribut \`required\` WAJIB ikut dilepas saat wrapper disembunyikan.
            //
            // Bug yang diperbaiki di sini: dropdown Lengan/Kerah punya
            // \`required\` di HTML, tapi wrapper-nya disembunyikan (display:none)
            // di mode Daftar Pemain. Browser menjalankan constraint validation
            // SEBELUM event submit menyala; saat menemukan <select required>
            // yang kosong DAN tersembunyi, Chrome tidak bisa memfokuskannya
            // lalu membatalkan submit tanpa pesan apa pun:
            //
            //   An invalid form control with name='lines[0][jersey_sleeve_type_id]'
            //   is not focusable.
            //
            // Akibatnya klik "Simpan Order" diam-diam tidak terjadi apa-apa:
            // tidak ada request ke server, tidak ada pesan error, tombol
            // tetap aktif. Terverifikasi di browser - submit event tidak
            // pernah terpicu sama sekali.
            //
            // Field-nya tetap ENABLED (tidak \`disabled\`) supaya nilainya
            // tetap terkirim ke server, dan validasi server tetap menolak
            // order tanpa lengan/kerah pada mode yang memintanya.
            el.querySelectorAll('select, input').forEach(f => {
                f.required = tampilkanDefault;
            });
        });
    }`
);

/* 1b. Penanda submit sedang berjalan (pagar double-klik). */
terapkan(
  fCreate,
  'guard sedangMenyimpan',
  `    document.getElementById('jersey-order-form').addEventListener('submit', async function (e) {
        e.preventDefault();`,
  `    // Penanda submit sedang berjalan. Tanpa ini, klik "Simpan Order" dua
    // kali saat request pertama masih diproses menghasilkan 2 POST ke
    // jersey.orders.store - jadi 2 order + 2 detail order.
    let sedangMenyimpan = false;

    document.getElementById('jersey-order-form').addEventListener('submit', async function (e) {
        e.preventDefault();

        if (sedangMenyimpan) return;`
);

/* 1c. Loading state tepat sebelum this.submit(). */
terapkan(
  fCreate,
  'loading state tombol Simpan',
  `        this.querySelectorAll('input.format-ribuan').forEach(input => {
            input.value = cleanRupiah(input.value);
        });

        this.submit();
    });`,
  `        this.querySelectorAll('input.format-ribuan').forEach(input => {
            input.value = cleanRupiah(input.value);
        });

        // ---- Loading state ----
        // Disetel tepat sebelum this.submit() (navigasi penuh), supaya tidak
        // pernah tertahan kalau ada validasi di atas yang menolak. Tombol
        // dinonaktifkan sebagai pagar kedua; sedangMenyimpan sudah menolak
        // klik kedua sebelum spinner sempat tampil.
        sedangMenyimpan = true;
        const btnSimpan = this.querySelector('button[type="submit"]');
        if (btnSimpan) {
            btnSimpan.disabled = true;
            btnSimpan.classList.add('opacity-70', 'cursor-not-allowed');
            btnSimpan.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Menyimpan...';
        }

        // Pagar pengaman: kalau navigasi entah bagaimana tidak terjadi, form
        // jangan sampai terkunci selamanya dengan tombol mati.
        setTimeout(() => {
            if (!btnSimpan) return;
            sedangMenyimpan = false;
            btnSimpan.disabled = false;
            btnSimpan.classList.remove('opacity-70', 'cursor-not-allowed');
            btnSimpan.innerHTML = 'Simpan Order';
        }, 20000);

        this.submit();
    });`
);

/* =====================================================================
   2. edit-lines.blade.php  (pola required tersembunyi yang sama)
   ===================================================================== */
console.log('\n[2] resources/views/jersey/orders/edit-lines.blade.php');

terapkan(
  path.join(root, 'resources', 'views', 'jersey', 'orders', 'edit-lines.blade.php'),
  'required ikut visibilitas wrapper lengan/kerah',
  `        const orderType = card.querySelector('input[name$="[order_type]"]:checked')?.value || 'quantity_only';
        const tampilkanDefault = orderType !== 'player_list';
        card.querySelectorAll('.wrapper-lengan-kerah-default').forEach(el => el.classList.toggle('hidden', !tampilkanDefault));
    }`,
  `        const orderType = card.querySelector('input[name$="[order_type]"]:checked')?.value || 'quantity_only';
        const tampilkanDefault = orderType !== 'player_list';
        card.querySelectorAll('.wrapper-lengan-kerah-default').forEach(el => {
            el.classList.toggle('hidden', !tampilkanDefault);
            // Sama seperti create.blade.php: \`required\` harus dilepas saat
            // wrapper disembunyikan. <select required> yang tersembunyi dan
            // kosong membuat Chrome membatalkan submit tanpa pesan
            // ("... is not focusable") sehingga klik "Simpan" tidak
            // melakukan apa-apa tanpa indikasi apa pun.
            el.querySelectorAll('select, input').forEach(f => {
                f.required = tampilkanDefault;
            });
        });
    }`
);

/* =====================================================================
   3. show.blade.php  (modal "Tambah Baris Susulan")

      File ini juga meng-include _line-card-fields.blade.php (baris 259),
      jadi punya dropdown Lengan/Kerah `required` yang sama. Fungsi
      perbaruiVisibilitasLenganKerah-nya ada di baris 610-616 dengan logika
      sembunyikan yang sama persis - dan TIDAK ikut tertangkap kalau hanya
      create + edit-lines yang dipatch.
   ===================================================================== */
console.log('\n[3] resources/views/jersey/orders/show.blade.php');

terapkan(
  path.join(root, 'resources', 'views', 'jersey', 'orders', 'show.blade.php'),
  'required ikut visibilitas wrapper lengan/kerah',
  `        const orderType = card.querySelector('input[name$="[order_type]"]:checked')?.value || 'quantity_only';
        const tampilkanDefault = orderType !== 'player_list';
        card.querySelectorAll('.wrapper-lengan-kerah-default').forEach(el => el.classList.toggle('hidden', !tampilkanDefault));
    }`,
  `        const orderType = card.querySelector('input[name$="[order_type]"]:checked')?.value || 'quantity_only';
        const tampilkanDefault = orderType !== 'player_list';
        card.querySelectorAll('.wrapper-lengan-kerah-default').forEach(el => {
            el.classList.toggle('hidden', !tampilkanDefault);
            // Sama seperti create & edit-lines: \`required\` harus dilepas saat
            // wrapper disembunyikan. Tanpa ini, modal "Tambah Baris Susulan"
            // di mode Daftar Pemain diam-diam tidak tersimpan: Chrome
            // membatalkan submit karena <select required> tersembunyi tidak
            // bisa difokus ("... is not focusable"), tanpa pesan apa pun.
            el.querySelectorAll('select, input').forEach(f => {
                f.required = tampilkanDefault;
            });
        });
    }`
);

/* =====================================================================
   4. JerseyOrderController.php
      3 blok rules() - store(), update(), storeLineSusulan()
   ===================================================================== */
console.log('\n[4] app/Http/Controllers/Jersey/JerseyOrderController.php');

terapkan(
  path.join(root, 'app', 'Http', 'Controllers', 'Jersey', 'JerseyOrderController.php'),
  'rules lengan/kerah jadi mode-aware (3 blok)',
  `            'lines.*.jersey_sleeve_type_id' => 'required|exists:jersey_sleeve_types,id',
            'lines.*.jersey_collar_type_id' => 'required|exists:jersey_collar_types,id',`,
  `            // Mode "Daftar Pemain" (order_type = player_list) menyembunyikan
            // dropdown lengan/kerah default di level baris - pemakaiannya jadi
            // per pemain (lihat rules items.* di bawah). Kalau baris ini tetap
            // \`required\` polos, order mode Daftar Pemain tidak akan pernah bisa
            // disimpan karena user tidak punya UI untuk mengisinya.
            // Kolom di database sudah nullable dan diisi di baris penyimpan
            // memakai \`!empty(...) ? (int) : null\`, jadi null aman.
            'lines.*.jersey_sleeve_type_id' => ['nullable', 'required_unless:lines.*.order_type,player_list', 'exists:jersey_sleeve_types,id'],
            'lines.*.jersey_collar_type_id' => ['nullable', 'required_unless:lines.*.order_type,player_list', 'exists:jersey_collar_types,id'],`,
  3
);

/* =====================================================================
   VERIFIKASI
   ===================================================================== */
console.log('\n=== VERIFIKASI ===');

const vCreate = baca(fCreate).isi;
const vEdit = baca(path.join(root, 'resources', 'views', 'jersey', 'orders', 'edit-lines.blade.php')).isi;
const vShow = baca(path.join(root, 'resources', 'views', 'jersey', 'orders', 'show.blade.php')).isi;
const vCtrl = baca(path.join(root, 'app', 'Http', 'Controllers', 'Jersey', 'JerseyOrderController.php')).isi;

const cek = [
  ['create: required ikut visibilitas', vCreate.includes('f.required = tampilkanDefault;')],
  ['create: guard sedangMenyimpan', vCreate.includes('if (sedangMenyimpan) return;')],
  ['create: loading state', vCreate.includes('Menyimpan...') && vCreate.includes('btnSimpan.disabled = true;')],
  ['edit: required ikut visibilitas', vEdit.includes('f.required = tampilkanDefault;')],
  ['show: required ikut visibilitas', vShow.includes('f.required = tampilkanDefault;')],
  ['ctrl: required_unless (6 aturan)', (vCtrl.match(/required_unless:lines\.\*\.order_type,player_list/g) || []).length === 6],
  ['ctrl: tidak ada required polos', !vCtrl.includes(`'lines.*.jersey_sleeve_type_id' => 'required|`)],
];

for (const [label, ok] of cek) {
  console.log('  ' + (ok ? 'OK   ' : 'GAGAL') + '  ' + label);
  if (!ok) gagal++;
}

/* Karakter dari sistem tulisan lain = tanda penulisan rusak (mis. karakter
   CJK yang nyasar ke dalam komentar). Simbol umum seperti "×" sahaja dan
   BUKAN tanda rusak, jadi jangan ikut dipakai sebagai kriteria. */
function hurufAsing(isi) {
  return [...new Set(isi.split('').filter(c => {
    const n = c.charCodeAt(0);
    return (n >= 0x2e80 && n <= 0x9fff) ||    // CJK
           (n >= 0xac00 && n <= 0xd7af) ||    // Hangul
           (n >= 0xff00 && n <= 0xffef) ||    // fullwidth
           (n >= 0x0600 && n <= 0x06ff);      // Arab
  }))];
}

for (const [nama, isi] of [['create', vCreate], ['edit', vEdit], ['show', vShow], ['ctrl', vCtrl]]) {
  const aneh = hurufAsing(isi);
  if (aneh.length) {
    console.log('  GAGAL  ' + nama + ' punya karakter tulisan lain: ' + aneh.join(' '));
    gagal++;
  }
}

console.log('\n' + (gagal === 0
  ? 'Semua patch terpasang. Lanjut: php artisan view:clear'
  : gagal + ' pemeriksaan gagal.'));
process.exit(gagal === 0 ? 0 : 1);