/* SAMAKAN ID MODAL DI PEMICU

   ID modal berubah saat konversi ke <x-modal> (modalAkun -> akun-tambah).
   Pemicu yang masih menunjuk ID lama tidak akan menemukan apa-apa, jadi
   tombolnya jadi mati. Yang diganti HANYA nama ID di dalam argumen
   toggleModal/openModal/closeModal - tidak ada baris lain yang disentuh.
*/

const fs = require('fs');
const path = require('path');

const PETA = {
  'resources/views/master/akun/index.blade.php': {
    modalAkun: 'akun-tambah',
    modalEditAkun: 'akun-edit',
  },
  'resources/views/master/merk/index.blade.php': {
    modalMerk: 'merk-tambah',
    modalEditMerk: 'merk-edit',
  },
  'resources/views/master/bank/index.blade.php': {
    modalBank: 'bank-tambah',
    modalEditBank: 'bank-edit',
  },
  'resources/views/master/user/index.blade.php': {
    modalUser: 'user-tambah',
    modalEditUser: 'user-edit',
  },
  'resources/views/master/gudang/index.blade.php': {
    modalGudang: 'gudang-tambah',
    modalEditGudang: 'gudang-edit',
  },
  'resources/views/master/jasa/index.blade.php': {
    modalJasa: 'jasa-tambah',
    modalEditJasa: 'jasa-edit',
  },
  'resources/views/konsumen-deposit/index.blade.php': {
    modalDeposit: 'deposit-tambah',
  },
};

let total = 0;
for (const [file, peta] of Object.entries(PETA)) {
  const p = path.join(__dirname, '..', file);
  if (!fs.existsSync(p)) { console.log('LEWAT: ' + file); continue; }

  let c = fs.readFileSync(p, 'utf8');
  let n = 0;

  for (const [lama, baru] of Object.entries(peta)) {
    // Hanya argumen fungsi modal, supaya id yang sama di konteks lain
    // (mis. document.getElementById) tidak ikut tertimpa.
    const re = new RegExp("((?:open|close|toggle)Modal\\(')" + lama + "('\\))", 'g');
    const sebelum = (c.match(re) || []).length;
    c = c.replace(re, '$1' + baru + '$2');
    n += sebelum;
  }

  if (n > 0) { fs.writeFileSync(p, c, 'utf8'); total += n; console.log('  ' + file.replace('resources/views/', '') + ' : ' + n); }
}
console.log('Pemicu diperbarui: ' + total);
