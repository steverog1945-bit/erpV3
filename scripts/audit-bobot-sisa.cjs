/* AUDIT BOBOT TEKS AKHIR

  Peringatan: <text> di dalam SVG bukan teks UI. browser tetap
  menghitungnya sebagai elemen teks dan font-weight-nya terlihat
  700, padahal itu isi gambar - diagram, ikon, label di dalam
  vektor. Mengubahnya tidak akan mengubah tampilan sama sekali
  karena yang dirender adalah path SVG, bukan huruf.

  Jadi skrip ini MEMBEDAKAN keduanya. Yang penting diukur adalah teks
  HTML sungguhan: label, tabel, tombol, judul, nilai.
*/

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');

function walk(d, out = []) {
  if (!fs.existsSync(d)) return out;
  for (const e of fs.readdirSync(d, { withFileTypes: true })) {
    const p = path.join(d, e.name);
    if (e.isDirectory()) walk(p, out);
    else if (e.name.endsWith('.blade.php')) out.push(p);
  }
  return out;
}

const files = walk(path.join(root, 'resources', 'views'));

// Kelas bobot tinggi di luar rentang OpenCode.
const pola = /font-(semibold|bold|extrabold|black)\b/g;

// Elemen yang bobotnya datang dari CSS, bukan dari kelas utility.
const cssFiles = [
  path.join(root, 'resources', 'css', 'app.css'),
  path.join(root, 'resources', 'css', 'ui-refresh.css'),
  path.join(root, 'resources', 'views', 'layouts', 'app.blade.php'),
];

console.log('=== KELAS UTILITY BOBOT TINGGI ===\n');
let totalKelas = 0;
for (const f of files) {
  const c = fs.readFileSync(f, 'utf8');
  const n = (c.match(pola) || []).length;
  if (n > 0) {
    totalKelas += n;
    console.log('  ' + n + '  ' + path.relative(root, f).replace(/\\/g, '/'));
  }
}
console.log('\n  total: ' + totalKelas);

console.log('\n=== ATURAN CSS BOBOT TINGGI ===\n');
let totalAturan = 0;
for (const f of cssFiles) {
  if (!fs.existsSync(f)) continue;
  const c = fs.readFileSync(f, 'utf8');
  const re = /font-weight\s*:\s*(600|700|800|900|bolder|bold)\b/gi;
  const baris = c.split('\n');
  let shown = 0;
  baris.forEach((b, i) => {
    re.lastIndex = 0;
    if (!re.test(b)) return;
    totalAturan += 1;
    // Blockir dari konteks agar terlihat selector mana
    const mula = Math.max(0, i - 6);
    let sel = '';
    for (let j = i; j >= mula; j--) {
      const t = baris[j];
      const s = t.match(/^([^@}{][^{]*)\{/);
      if (s) { sel = s[1].trim().slice(0, 60); break; }
    }
    if (shown < 25) {
      console.log('  ' + path.basename(f) + ':' + (i + 1) + '  ' + (sel || '(selector di baris lain)'));
      shown += 1;
    }
  });
}
console.log('\n  total: ' + totalAturan);
console.log('  (yang di .prose-post / .ql-editor adalah isi artikel, dikecualikan)');
