/* PINDAI SELURUH KODE UNTUK TEKS ASING

  Script sebelumnya hanya mencari daftar kata curiga. Yang lebih
 、全面 dan tidak bisa lolos dari daftar: karakter dari sistem
  tulisan lain. Kalau ada CJK, Cyrillic, atau Arab di dalam komentar
  kode PHP/JS/CSS, itu hampir pasti bukan disengaja - bahasa ini
  tidak memakainya.

 出力 yang diabaikan: file vendor (memang来自 luar), dan string
  yang memang data contoh.
*/

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');

function telusuri(d, out = []) {
  if (!fs.existsSync(d)) return out;
  for (const e of fs.readdirSync(d, { withFileTypes: true })) {
    if (['node_modules', '.git', 'build', 'storage', 'backups'].includes(e.name)) continue;
    const p = path.join(d, e.name);
    if (e.isDirectory()) telusuri(p, out);
    else if (/\.(php|js|css|blade\.php|env|example)$/.test(e.name)) out.push(p);
  }
  return out;
}

// CJK, Hiragana, Katakana, Hangul, Cyrillic, Greek, Arabic, Hebrew, Devanagari, Thai
const ASING = /[Ѐ-ӿ؀-ۿ֐-׿ऀ-ॿ฀-๿぀-ヿ㐀-鿿가-힯]/;

const target = ['app', 'resources', 'bootstrap', 'config', 'routes', 'database', 'scripts']
  .map((d) => path.join(root, d))
  .flatMap((d) => telusuri(d))
  // .env.example hidup di akar
  .concat([path.join(root, '.env.example')].filter((f) => fs.existsSync(f)));

let jumlah = 0;
for (const f of target) {
  const c = fs.readFileSync(f, 'utf8');
  const relatif = path.relative(root, f);
  c.split('\n').forEach((baris, i) => {
    if (!ASING.test(baris)) return;
    jumlah++;
    console.log('  ' + relatif + ':' + (i + 1));
    console.log('       ' + baris.trim().substring(0, 110));
  });
}

console.log(
  '\n' + (jumlah === 0
    ? 'Tidak ada karakter dari sistem tulisan lain di seluruh kode.'
    : jumlah + ' baris memakai karakter dari sistem tulisan lain.')
);
