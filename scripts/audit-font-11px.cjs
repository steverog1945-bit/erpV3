/* Audit font-size 11px di seluruh aplikasi.

  Permintaan: hindari font-size 11px, minimal 13px agar terbaca jelas.

  Yang dicari:
    - Kelas Tailwind text-[11px] di view
    - font-size: 11px di CSS
    - font-size="11" di SVG (ilustrasi)

  Yang TIDAK diubah tanpa pengecekan:
    - SVG <text> di dalam diagram (viewBox 160x100) - angka di sana
      bukan ukuran layar, dan mengubahnya mengubah proporsi gambar.
    - Label yang memang harus kecil karena konteksnya (mis. badge status
      di dalam tabel yang padat).

  Laporan dikelompokkan supaya keputusan bisa diambil per kelompok.
*/

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');

function walk(d, out = []) {
  if (!fs.existsSync(d)) return out;
  for (const e of fs.readdirSync(d, { withFileTypes: true })) {
    if (e.name === 'node_modules' || e.name === 'vendor') continue;
    const p = path.join(d, e.name);
    if (e.isDirectory()) walk(p, out);
    else if (/\.(blade\.php|css)$/.test(e.name)) out.push(p);
  }
  return out;
}

const files = walk(path.join(root, 'resources', 'views'));
const cssFiles = walk(path.join(root, 'resources', 'css'));

const perFile = new Map();
let totalKelas = 0;
let totalCss = 0;
let totalSvg = 0;

for (const f of files) {
  const L = fs.readFileSync(f, 'utf8').split('\n');
  const list = [];
  L.forEach((b, i) => {
    const t = b.trim();
    if (/text-\[11px\]/.test(t)) {
      list.push({ baris: i + 1, jenis: 'kelas', teks: t.slice(0, 76) });
      totalKelas++;
    }
    if (/font-size=["']11px?["']/.test(t)) {
      list.push({ baris: i + 1, jenis: 'svg', teks: t.slice(0, 76) });
      totalSvg++;
    }
  });
  if (list.length) perFile.set(path.relative(root, f).replace(/\\/g, '/'), list);
}

for (const f of cssFiles) {
  const L = fs.readFileSync(f, 'utf8').split('\n');
  L.forEach((b, i) => {
    if (/font-size:\s*11px/.test(b)) {
      totalCss++;
      const rel = path.relative(root, f).replace(/\\/g, '/');
      if (!perFile.has(rel)) perFile.set(rel, []);
      perFile.get(rel).push({ baris: i + 1, jenis: 'css', teks: b.trim().slice(0, 76) });
    }
  });
}

console.log('=== FONT-SIZE 11px ===\n');
console.log('  kelas text-[11px] di view : ' + totalKelas);
console.log('  font-size: 11px di CSS     : ' + totalCss);
console.log('  font-size="11" di SVG       : ' + totalSvg);
console.log('  total                      : ' + (totalKelas + totalCss + totalSvg));
console.log('  file terlibat              : ' + perFile.size + '\n');

// Kelompokkan: view vs css vs svg
const viewFiles = [...perFile.entries()].filter(([f]) => f.endsWith('.blade.php'));
const cssOnly = [...perFile.entries()].filter(([f]) => f.endsWith('.css'));

console.log('=== DI VIEW (text-[11px]) ===\n');
let n = 0;
for (const [f, list] of viewFiles) {
  const kelas = list.filter((x) => x.jenis === 'kelas');
  const svg = list.filter((x) => x.jenis === 'svg');
  if (!kelas.length && !svg.length) continue;
  n++;
  console.log('  ' + f + '  (' + kelas.length + ' kelas' + (svg.length ? ', ' + svg.length + ' svg' : '') + ')');
  kelas.slice(0, 3).forEach((x) => console.log('      ' + String(x.baris).padStart(5) + '  ' + x.teks));
  if (kelas.length > 3) console.log('      ... ' + (kelas.length - 3) + ' lagi');
}
console.log('\n  total view: ' + n);

console.log('\n=== DI CSS (font-size: 11px) ===\n');
for (const [f, list] of cssOnly) {
  console.log('  ' + f + '  (' + list.length + ')');
  list.slice(0, 4).forEach((x) => console.log('      ' + String(x.baris).padStart(5) + '  ' + x.teks));
  if (list.length > 4) console.log('      ... ' + (list.length - 4) + ' lagi');
}
