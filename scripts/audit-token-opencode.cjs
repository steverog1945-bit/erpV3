/* BANDINGKAN TOKEN DESAIN OPENCODE DENGAN YANG DIPAKAI SEKARANG

  Token OpenCode diambil langsung dari
  packages/ui/src/styles/theme.css di repo sst/opencode, bukan dari
  perkiraan. Di sini yang diukur: seberapa jauh aplikasi ini menyimpang.

  Yang paling penting: OpenCode hanya punya DUA bobot teks, 400 dan
  500. Aplikasi ini memakai 600, 700, 800, dan 900 - itu yang membuat
  tampilannya terasa lebih tebal dari OpenCode.
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

const hitung = {};
const tambah = (k, n) => (hitung[k] = (hitung[k] || 0) + n);

for (const f of files) {
  const c = fs.readFileSync(f, 'utf8');

  // Bobot
  for (const m of c.match(/font-(thin|extralight|light|normal|medium|semibold|bold|extrabold|black)\b/g) || []) {
    tambah(m, 1);
  }
  // Huruf besar + spasi lebar
  tambah('uppercase', (c.match(/\buppercase\b/g) || []).length);
  tambah('tracking-widest', (c.match(/tracking-widest/g) || []).length);
  tambah('tracking-wide', (c.match(/tracking-wide/g) || []).length);
  // Radius
  for (const m of c.match(/rounded-(none|sm|md|lg|xl|2xl|3xl|full)\b/g) || []) {
    tambah(m, 1);
  }
  // Warna indigo / aksen
  tambah('indigo-*', (c.match(/indigo-\d{2,3}/g) || []).length);
  tambah('slate-*', (c.match(/slate-\d{2,3}/g) || []).length);
}

console.log('=== BOBOT TEKS (OpenCode hanya 400 dan 500) ===\n');
const BOBOT = ['font-thin', 'font-extralight', 'font-light', 'font-normal', 'font-medium', 'font-semibold', 'font-bold', 'font-extrabold', 'font-black'];
let totalBobot = 0;
for (const b of BOBOT) {
  const n = hitung[b] || 0;
  totalBobot += n;
  const tanda = ['font-normal', 'font-medium'].includes(b) ? '  <- sah di OpenCode' : '  <- TIDAK ADA di OpenCode';
  console.log('  ' + b.padEnd(16) + String(n).padStart(5) + tanda);
}
const berlebihan = totalBobot - (hitung['font-normal'] || 0) - (hitung['font-medium'] || 0);
console.log('\n  total pemakaian bobot : ' + totalBobot);
console.log('  yang melebihi OpenCode : ' + berlebihan + ' (' + Math.round((berlebihan / totalBobot) * 100) + '%)');

console.log('\n=== HURUF BESAR ===\n');
console.log('  uppercase        : ' + (hitung['uppercase'] || 0));
console.log('  tracking-widest  : ' + (hitung['tracking-widest'] || 0));
console.log('  tracking-wide    : ' + (hitung['tracking-wide'] || 0));

console.log('\n=== RADIUS ===\n');
for (const r of ['rounded-none', 'rounded-sm', 'rounded-md', 'rounded-lg', 'rounded-xl', 'rounded-2xl', 'rounded-3xl', 'rounded-full']) {
  console.log('  ' + r.padEnd(16) + (hitung[r] || 0));
}
console.log('  Catatan: OpenCode punya 2/4/6/8/10 px. Tailwind rounded-lg=8px, rounded-2xl=16px (melebih).');

console.log('\n=== WARNA ===\n');
console.log('  indigo-* (aksen)  : ' + (hitung['indigo-*'] || 0));
console.log('  slate-* (netral)  : ' + (hitung['slate-*'] || 0));
console.log('  Catatan: OpenCode tidak memakai indigo. Aksennya #034cff (biru), tombolnya #171717 (hampir hitam).');
