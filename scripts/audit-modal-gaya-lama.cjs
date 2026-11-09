/* DETEKSI MODAL BERGAYA LAMA
   Mencari modal yang masih memakai tata letak "Tambah Golongan":
     - header berwarna penuh (bg-indigo-600 / bg-amber-500 / bg-blue-*)
     - panel membulat berlebihan (rounded-[2rem] / rounded-[3rem])
     - isian terpusat (text-center)
     - tombol huruf besar (uppercase tracking-widest)
   Semua itu menyimpang dari <x-modal> yang sekarang dipakai 110 modal.
*/

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const viewsDir = path.join(root, 'resources', 'views');
const files = [];
(function walk(d) {
  for (const e of fs.readdirSync(d, { withFileTypes: true })) {
    const p = path.join(d, e.name);
    if (e.isDirectory()) walk(p);
    else if (e.name.endsWith('.blade.php')) files.push(p);
  }
})(viewsDir);

const rel = (f) => path.relative(root, f).replace(/\\/g, '/');
const lineOf = (src, i) => src.slice(0, i).split(/\r?\n/).length;

const hasil = [];

for (const f of files) {
  const src = fs.readFileSync(f, 'utf8');

  // Hanya modal yang masih ditulis manual (memakai penanda data-modal-root)
  for (const m of src.matchAll(/<div\s+([^>]*\bid="(modal[A-Za-z0-9_-]*)"[^>]*\bdata-modal-root\b[^>]*)>/g)) {
    const id = m[2];
    // Ambil isi modal: dari tag akar sampai tag </div> yang menutupnya.
    // Pendekatan praktis: ambil 260 baris berikutnya, cukup untuk modal.
    const mulai = m.index;
    const Potongan = src.slice(mulai, mulai + 9000);

    const tandakan = [];
    const headerWarna = Potongan.match(/class="[^"]*\bbg-(indigo|amber|blue|emerald|rose|violet)-\d{3}\b[^"]*"/);
    if (headerWarna) tandakan.push('header berwarna (' + headerWarna[1] + ')');

    const radiusBesar = Potongan.match(/rounded-\[(2|3)rem\]|rounded-2xl|rounded-3xl/);
    if (radiusBesar) tandakan.push('radius ' + radiusBesar[0]);

    const terpusat = (Potongan.match(/text-center/g) || []).length;
    if (terpusat > 0) tandakan.push('isian terpusat (' + terpusat + 'x text-center)');

    const hurufBesar = (Potongan.match(/uppercase/g) || []).length;
    if (hurufBesar >= 2) tandakan.push('tombol huruf besar (' + hurufBesar + 'x uppercase)');

    const paddingBesar = Potongan.match(/py-12 space-y-8|py-10 px-10|px-10 py-8/);
    if (paddingBesar) tandakan.push('jarak lega berlebihan (' + paddingBesar[0] + ')');

    if (tandakan.length) {
      hasil.push({
        file: rel(f),
        line: lineOf(src, m.index),
        id: id,
        jumlah: tandakan.length,
        tandakan: tandakan
      });
    }
  }
}

hasil.sort((a, b) => b.jumlah - a.jumlah);

console.log('Modal bergaya lama: ' + hasil.length);
console.log('');
const perFile = {};
for (const h of hasil) {
  (perFile[h.file] = perFile[h.file] || []).push(h);
}
for (const [f, list] of Object.entries(perFile)) {
  console.log('--- ' + f + '  (' + list.length + ' modal)');
  for (const h of list) {
    console.log('    ' + h.id);
    h.tandakan.forEach(t => console.log('       - ' + t));
  }
}
