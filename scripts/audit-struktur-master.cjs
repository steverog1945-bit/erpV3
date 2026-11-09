/* BANDINGKAN STRUKTUR MARKUP ANTAR HALAMAN MASTER

  Pengukuran computed style혀리 menunjukkan lima dari enam halaman
  yang disebut user identik. Jadi yang membuatnya terasa "beda"
  bukan gaya terhitung - tapi STRUKTUR: halaman mana yang memakai
  komponen bersama (<x-table>, <x-page-header>) dan mana yang
  menulis markupper sendiri.

  Yang menulis sendiri itu yang bisa menyimpang diam-diam, karena
  perbaikan di komponen tidak menjangkaunya.
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

// Halaman master yang seharusnya seragam
const TARGET = [
  'master/merk/index.blade.php',
  'master/satuan/index.blade.php',
  'master/mesin/index.blade.php',
  'master/gudang/index.blade.php',
  'master/jasa/index.blade.php',
  'konsumen-deposit/index.blade.php',
  'master/akun/index.blade.php',
  'master/karyawan/index.blade.php',
  'master/bahan/index.blade.php',
  'master/jenis_bahan/index.blade.php',
  'master/supplier/index.blade.php',
  'master/produk/index.blade.php',
];

console.log('=== KOMPONEN BERSAMA YANG DIPAKAI ===\n');
console.log('  ' + 'berkas'.padEnd(40) + 'x-table  x-page-hdr  modal  tabel-mentah');
console.log('  ' + '-'.repeat(78));

const tanpaKomponen = [];

for (const t of TARGET) {
  const p = path.join(dir, t);
  if (!fs.existsSync(p)) {
    console.log('  ' + t.padEnd(40) + '(tidak ada)');
    continue;
  }
  const c = fs.readFileSync(p, 'utf8');

  const xTable = (c.match(/<x-table[.>]/g) || []).length;
  const xPage = (c.match(/<x-page-header/g) || []).length;
  const modal = (c.match(/<x-modal/g) || []).length;

  // Tabel HTML mentah yang BUKAN di dalam komponen
  const mentah = (c.match(/<table(?![-\w])[^>]*>/g) || []).filter((m) =>
    !/class="[^"]*(rtable|modern-table)/.test(m)
  ).length;

  console.log(
    '  ' + t.padEnd(40) +
    String(xTable).padEnd(9) +
    String(xPage).padEnd(12) +
    String(modal).padEnd(7) +
    mentah
  );

  if (xTable === 0 || xPage === 0) {
    tanpaKomponen.push({ t, xTable, xPage, mentah });
  }
}

console.log('\n=== HALAMAN YANG TIDAK PAKAI KOMPONEN BERSAMA ===\n');
if (!tanpaKomponen.length) {
  console.log('  tidak ada\n');
} else {
  tanpaKomponen.forEach((x) => {
    console.log('  ' + x.t);
    if (!x.xTable) console.log('      tidak pakai <x-table> -> styling tabel tidak ikut dari komponen');
    if (!x.xPage) console.log('      tidak pakai <x-page-header> -> judul halaman bisa berbeda dari yang lain');
    if (x.mentah) console.log('      ' + x.mentah + ' <table> mentah tanpa kelas bersama');
  });
  console.log('\n  total: ' + tanpaKomponen.length);
}
