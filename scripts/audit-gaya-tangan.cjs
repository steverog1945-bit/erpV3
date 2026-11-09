/* CARI POLA GAYA TANGAN DI SELURUH APLIKASI

  Akar masalah complaint "halaman ini tidak seperti OpenCode" ternyata
  bukan di komponen bersama, tapi di HALAMAN yang menulis style-nya
  sendiri. Komponen bersama diperbaiki dengan benar, tapi halaman
  yang menentukan <table>, padding, dan radius sendiri tidak pernah
  ikut diperbaiki - dan tidak ada yang memberi tahu.

  Yang dicari di sini adalah tanda tangan halaman-buatan-dir sendiri:
    - radius di luar rentang OpenCode (2/4/6/8/10px)
    - padding tabel jauh lebih besar dari 10px/16px
    - tracking lebar + uppercase (pola "label tebal")
    - <table> tanpa kelas bersama
    - utility radius arbitrary (rounded-[...])
*/

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const dirs = [
  path.join(root, 'resources', 'views'),
  path.join(root, 'resources', 'css'),
];

function walk(d, out = []) {
  if (!fs.existsSync(d)) return out;
  for (const e of fs.readdirSync(d, { withFileTypes: true })) {
    const p = path.join(d, e.name);
    if (e.isDirectory()) walk(p, out);
    else if (/\.(blade\.php|css)$/.test(e.name)) out.push(p);
  }
  return out;
}

const POLA = [
  {
    nama: 'radius di luar rentang OpenCode',
    re: /rounded-\[(1[1-9]|[2-9]\d)px\]|rounded-\[(1[1-9]|[2-9]\d)rem\]/g,
    contoh: (m) => m,
  },
  {
    nama: 'utility radius 2xl/3xl (16/24px)',
    re: /rounded-(2xl|3xl)\b/g,
    contoh: (m) => m,
  },
  { nama: 'tabel tanpa kelas bersama', re: /<table(?![-\w])(?![^>]*class="[^"]*(modern-table|rtable))/g, contoh: () => '<table>' },
  { nama: 'padding tabel besar (px-6 ke atas)', re: /<(th|td)[^>]*\bpx-(6|7|8)\b/g, contoh: (m) => m.slice(0, 40) },
  { nama: 'label uppercase + tracking lebar', re: /\buppercase\b[^"']*tracking-(wide|widest|2xl|3xl)\b|\btracking-(wide|widest|2xl|3xl)\b[^"']*uppercase\b/g, contoh: (m) => m.slice(0, 46) },
  { nama: 'border-radius manual > 10px', re: /border-radius:\s*(1[1-9]|[2-9]\d)px/g, contoh: (m) => m },
];

const total = {};
POLA.forEach((p) => (total[p.nama] = 0));

const perFile = [];

for (const d of dirs) {
  for (const f of walk(d)) {
    const c = fs.readFileSync(f, 'utf8');
    // Buang komentar blade dan JS supaya dokumentasi tidak dihitung
    const bersih = c.replace(/\{\{--[\s\S]*?--\}\}/g, '').replace(/^\s*(\/\/|\*|\/\*).*$/gm, '');
    const relatif = path.relative(root, f).replace(/\\/g, '/');
    const ketemu = [];

    for (const p of POLA) {
      const m = bersih.match(p.re) || [];
      if (m.length) {
        ketemu.push({ nama: p.nama, n: m.length, contoh: [...new Set(m)].slice(0, 3) });
        total[p.nama] += m.length;
      }
    }

    if (ketemu.length) perFile.push({ f: relatif, ketemu });
  }
}

console.log('=== TOTAL PER POLA ===\n');
for (const [nama, n] of Object.entries(total)) {
  console.log('  ' + String(n).padStart(4) + '  ' + nama);
}

console.log('\n=== FILE, DIURUTKAN YANG PALING BERMASALAH ===\n');
perFile
  .sort((a, b) => b.ketemu.reduce((s, x) => s + x.n, 0) - a.ketemu.reduce((s, x) => s + x.n, 0))
  .slice(0, 30)
  .forEach((r) => {
    const tot = r.ketemu.reduce((s, x) => s + x.n, 0);
    console.log('  ' + String(tot).padStart(4) + '  ' + r.f);
    r.ketemu.forEach((k) => {
      console.log('         ' + k.nama + ' x' + k.n + '  ' + k.contoh.join(' | ').slice(0, 70));
    });
  });

console.log('\n  total file: ' + perFile.length);
