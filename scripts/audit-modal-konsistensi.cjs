/* AUDIT KONSISTENSI BADAN MODAL

  Empat modal yang disebut user (Tambah Merk, Tambah Gudang, Deposit
  Konsumen, Tambah Jasa) sudah memakai <x-modal> yang sama, tapi BADAN
  isinya tidak sama. Sebagian membungkus kontennya di max-w-[85%]
  mx-auto, sebagian menambah py-10 sendiri di atas padding bawaan
  komponen. Akibatnya tepi kiri konten tidak rata dengan modal lain,
  dan tinggi isinya berbeda-beda - persis yang dirasakan sebagai
  "tidak konsisten".

  Aturan yang di sini: modal TIDAK BOLEH menambah padding sendiri
  (p-5 sudah ada di komponen) dan TIDAK BOLEH mempersempit isi
  (max-w + mx-auto). Yang di dalam boleh bebas.
*/

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const dir = path.join(root, 'resources', 'views');

function walk(d, out = []) {
  if (!fs.existsSync(d)) return out;
  for (const e of fs.readdirSync(d, { withFileTypes: true })) {
    const p = path.join(d, e.name);
    if (e.isDirectory()) walk(p, out);
    else if (e.name.endsWith('.blade.php')) out.push(p);
  }
  return out;
}

/* Cari blok <x-modal ...> ... </x-modal> dan periksa isinya.

   CATATAN SOAL NESTING: pola regex tunggal tidak bisa menghitung
   <x-modal> di dalam <x-modal> dengan benar, karena pencocokan
   </x-modal> pertama dianggap penutup. Karena itu blok dihitung dengan
   menghitung tag secara berurutan, bukan regex. Tanpa itu, audit akan
   melaporkan false positive pada file yang modal edit-nya berisi
   modal harga bertingkat.
*/
const MASALAH = [
  { nama: 'padding sendiri (py-10 dll)', re: /<form[^>]*\bclass="[^"]*\bpy-\d/ },
  { nama: 'mempersempit isi (max-w + mx-auto)', re: /max-w-\[\d+%\][^"]*mx-auto|mx-auto[^"]*max-w-\[\d+%\]/ },
  { nama: 'wrapper max-w-fixed', re: /max-w-\[\d+px\]/ },
  { nama: 'indeks z yang menabrak modal lain', re: /z-\[(5[1-9]|6[1-9]|7[0-9])\]/ },
];

/* Ambil isi setiap <x-modal> dengan menghitung kedalaman tag, supaya
   modal di dalam modal (modal edit yang memuat modal harga
   bertingkat) tidak salah dipotong di tag penutup pertama. */
function isiModal(c) {
  const hasil = [];
  const buka = /<x-modal\b([^>]*)>/g;
  let m;
  while ((m = buka.exec(c)) !== null) {
    const mulai = buka.lastIndex;
    let kedalaman = 1;
    const tag = /<x-modal\b[^>]*>|<\/x-modal>/g;
    tag.lastIndex = mulai;
    let t;
    while ((t = tag.exec(c)) !== null) {
      if (t[0] === '</x-modal>') {
        kedalaman -= 1;
        if (kedalaman === 0) {
          const id = (m[1].match(/id="([^"]+)"/) || [, '?'])[1];
          hasil.push({ id: id, isi: c.slice(mulai, t.index), baris: c.slice(0, m.index).split('\n').length });
          break;
        }
      } else {
        kedalaman += 1;
      }
    }
  }
  return hasil;
}

let total = 0;
const perFile = [];

for (const f of walk(dir)) {
  const c = fs.readFileSync(f, 'utf8');
  const daftar = [];
  for (const blok of isiModal(c)) {
    const ketemu = [];
    for (const p of MASALAH) {
      if (p.re.test(blok.isi)) {
        ketemu.push(p.nama);
        total += 1;
      }
    }
    if (ketemu.length) {
      daftar.push({ id: blok.id, masalah: ketemu, baris: blok.baris });
    }
  }
  if (daftar.length) perFile.push({ f: path.relative(dir, f).replace(/\\/g, '/'), daftar });
}

console.log('=== MASALAH KONSISTENSI BADAN MODAL ===\n');
if (!total) {
  console.log('  tidak ada');
} else {
  perFile.forEach((r) => {
    console.log('  ' + r.f);
    r.daftar.forEach((x) => {
      console.log('      ' + x.id + '  (baris ' + x.baris + ')  ' + x.masalah.join(' + '));
    });
  });
  console.log('\n  total masalah: ' + total);
}
