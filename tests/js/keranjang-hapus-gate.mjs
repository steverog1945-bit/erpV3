/**
 * Gate keranjang Input Pesanan - sisi peramban.
 *
 * Menjalankan JavaScript ASLI dari resources/views/penjualan/create.blade.php,
 * bukan salinan logikanya (pola sama dengan gate Potong Manual).
 *
 * Yang diuji:
 *  1. Menghapus item SEBELUM item yang sedang diedit harus menurunkan index
 *     edit-nya (semua index sesudahnya geser satu ke atas). Kalau tidak,
 *     "UPDATE ITEM" menimpa baris yang salah.
 *  2. Menghapus item yang SEDANG diedit harus membatalkan mode edit
 *     (index jadi null, tombol kembali "TAMBAH"). Kalau tidak, index-nya
 *     menunjuk ke luar jangkauan dan Tambah menambah baris dobel.
 *  3. Menghapus item SESUDAH item yang sedang diedit tidak boleh mengubah
 *     index edit.
 *  4. Membatalkan konfirmasi tidak boleh mengubah apa pun.
 *  5. Mengosongkan keranjang juga membatalkan mode edit.
 *
 * Dijalankan oleh tests/Feature/KeranjangHapusGateTest.php.
 */
import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const BLADE = path.resolve(process.env.BLADE || 'resources/views/penjualan/create.blade.php');
const src = fs.readFileSync(BLADE, 'utf8');

/** Ekstrak satu fungsi utuh dari sumber (pencacah kurung kurawal). */
function ambilFungsi(nama) {
  const m = src.match(new RegExp(`(?:async\\s+)?function\\s+${nama}\\s*\\(`));
  if (!m) {
    throw new Error(`Fungsi ${nama} tidak ditemukan di ${BLADE}. Apakah nama fungsi berubah?`);
  }
  const mulai = src.indexOf('{', m.index + m[0].length - 1);
  let depth = 0;
  let i = mulai;
  for (; i < src.length; i++) {
    const c = src[i];
    if (c === '{') depth++;
    else if (c === '}') {
      depth--;
      if (depth === 0) return src.slice(m.index, i + 1);
    }
  }
  throw new Error(`Fungsi ${nama} tidak tertutup kurung kurawal.`);
}

// Tombol "Tambah" palsu - outgoingModeEdit() cuma mengubah label & kelasnya.
const tombolTambah = {
  innerText: 'UPDATE ITEM',
  classList: { replace: (a, b) => { tombolTambah._kelas = b; } },
  _kelas: 'bg-amber-500',
};

globalThis.document = {
  querySelector: (sel) => (sel.indexOf('tambahKeKeranjang') >= 0 ? tombolTambah : null),
};

// Swal.confirm = true/false -> controla "Ya, Hapus".
let swalKonfirmasi = true;
globalThis.Swal = {
  fire: () => Promise.resolve({ isConfirmed: swalKonfirmasi }),
};

const kode = [
  ambilFungsi('keluarDariModeEdit'),
  ambilFungsi('hapusItem'),
].join('\n\n');

// cart & editIndex dibuat parameter factory (bukan variabel modul) supaya
// new Function() - yang berjalan di lingkup global - tetap bisa melihat dan
// mengubahnya.
const factory = new Function('cart', 'editIndex', 'renderCart', `
  ${kode}
  return {
    hapus: (i) => hapusItem(i),
    ambilEditIndex: () => editIndex,
    ambilCart: () => cart,
  };
`);

const gagal = [];
function cek(label, syarat, detail = '') {
  if (syarat) {
    console.log('OK   ' + label);
  } else {
    console.log('GAGAL ' + label + (detail ? ' :: ' + detail : ''));
    gagal.push(label);
  }
}

function buatApi(cart, editIndex) {
  return factory(cart, editIndex, () => {});
}

async function hapus(api, index) {
  await api.hapus(index);
  // .then() pada Swal sudah resolve; tunggu satu tick supaya promise
  // di dalam then() selesai.
  await new Promise((r) => setTimeout(r, 0));
}

// --- 1. Hapus item SEBELUM item yang sedang diedit ---------------------
{
  const cart = [{ nama_file: 'A' }, { nama_file: 'B' }, { nama_file: 'C' }];
  const api = buatApi(cart, 2); // sedang mengedit item index 2 (C)
  await hapus(api, 0); // hapus A -> B jadi index 0, C jadi index 1
  cek(
    'hapus item sebelum item yang diedit: index edit ikut turun satu',
    api.ambilEditIndex() === 1,
    `dapat index edit ${api.ambilEditIndex()}`
  );
  cek('hapus item sebelum item yang diedit: cart jadi 2 item', api.ambilCart().length === 2);
}

// --- 2. Hapus item yang SEDANG diedit ----------------------------------
{
  const cart = [{ nama_file: 'A' }, { nama_file: 'B' }];
  const api = buatApi(cart, 1); // sedang mengedit B
  await hapus(api, 1); // hapus B itu sendiri
  cek(
    'hapus item yang sedang diedit: index edit jadi null',
    api.ambilEditIndex() === null,
    `dapat index edit ${api.ambilEditIndex()}`
  );
  cek(
    'hapus item yang sedang diedit: tombol kembali TAMBAH',
    tombolTambah.innerText === 'TAMBAH',
    `dapat label "${tombolTambah.innerText}"`
  );
  cek('hapus item yang sedang diedit: cart jadi 1 item', api.ambilCart().length === 1);
}

// --- 3. Hapus item SESUDAH item yang sedang diedit ---------------------
{
  const cart = [{ nama_file: 'A' }, { nama_file: 'B' }, { nama_file: 'C' }];
  const api = buatApi(cart, 0); // sedang mengedit A
  await hapus(api, 2); // hapus C
  cek(
    'hapus item setelah item yang diedit: index edit tidak berubah',
    api.ambilEditIndex() === 0,
    `dapat index edit ${api.ambilEditIndex()}`
  );
}

// --- 4. Batalkan konfirmasi -------------------------------------------
{
  const cart = [{ nama_file: 'A' }, { nama_file: 'B' }];
  const api = buatApi(cart, 1);
  swalKonfirmasi = false;
  await hapus(api, 0);
  swalKonfirmasi = true;
  cek('batal konfirmasi: cart tidak berubah', api.ambilCart().length === 2);
  cek('batal konfirmasi: index edit tidak berubah', api.ambilEditIndex() === 1);
}

// --- 5. Tidak sedang edit: index edit tetap null ----------------------
{
  const cart = [{ nama_file: 'A' }, { nama_file: 'B' }];
  const api = buatApi(cart, null);
  await hapus(api, 0);
  cek('hapus tanpa mode edit: index edit tetap null', api.ambilEditIndex() === null);
  cek('hapus tanpa mode edit: cart jadi 1 item', api.ambilCart().length === 1);
}

console.log('');
if (gagal.length) {
  console.log('HASIL: FAIL (' + gagal.length + ')');
  process.exit(1);
}
console.log('HASIL: PASS');
