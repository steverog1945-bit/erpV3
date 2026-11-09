/* Inventaris untuk audit: peta route, controller, dan deteksi pola yang
  _buffer sering menandakan duplikasi business logic.

   Yang dihitung:
     A. Jumlah route per prefix
     B. Controller per modul
     C. Method controller yang namanya mirip (kandidat duplikasi)
     D. Blade yang memanggil fetch() tanpa error handling
     E. Tombol submit tanpa disabled/loading di form transaksi
   */

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const P = (...s) => path.join(root, ...s);

function rekursif(dir, out = []) {
  if (!fs.existsSync(dir)) return out;
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) rekursif(p, out);
    else if (/\.php$/.test(e.name)) out.push(p);
  }
  return out;
}

/* ---------- A. Route ---------- */
console.log('=== A. ROUTE ===');
const web = fs.readFileSync(P('routes', 'web.php'), 'utf8');
const routeDef = [...web.matchAll(/Route::(get|post|put|patch|delete)\(\s*'([^']*)'/g)];
console.log('  total route di web.php : ' + routeDef.length);

const byPrefix = {};
for (const [, verb, uri] of routeDef) {
  const seg = (uri.split('/').filter(Boolean)[0] || '(root)').replace(/\{[^}]*\}/g, ':');
  byPrefix[seg] = (byPrefix[seg] || 0) + 1;
}
const top = Object.entries(byPrefix).sort((a, b) => b[1] - a[1]);
console.log('  route per prefix (top 20):');
for (const [p, n] of top.slice(0, 20)) console.log('    ' + String(n).padStart(4) + '  /' + p);

/* Route tanpa nama = sulit dipanggil lewat route() dan mudah salah ketik. */
const unnamed = [...web.matchAll(/Route::(get|post|put|patch|delete)\(\s*'([^']*)'[\s\S]{0,220}?\)\s*;/g)]
  .filter(m => !m[0].includes('->name('));
console.log('  route TANPA ->name() : ' + unnamed.length);

/* ---------- B. Controller ---------- */
console.log('\n=== B. CONTROLLER ===');
const ctrl = rekursif(P('app', 'Http', 'Controllers'));
console.log('  jumlah file controller : ' + ctrl.length);

const methods = [];
for (const f of ctrl) {
  const isi = fs.readFileSync(f, 'utf8');
  for (const m of isi.matchAll(/public function (\w+)\s*\(/g)) {
    methods.push({ file: path.relative(root, f), name: m[1] });
  }
}
console.log('  total method public    : ' + methods.length);

const byModul = {};
for (const m of methods) {
  const parts = m.file.split(path.sep);
  const cname = parts[parts.length - 1].replace('.php', '');
  byModul[cname] = (byModul[cname] || 0) + 1;
}
const topC = Object.entries(byModul).sort((a, b) => b[1] - a[1]).slice(0, 12);
console.log('  method per controller (top 12):');
for (const [c, n] of topC) console.log('    ' + String(n).padStart(4) + '  ' + c);

/* ---------- C. Kandidat duplikasi ---------- */
console.log('\n=== C. NAMA METHOD BERPOTENSI TUMPANG TINDIH ===');
const byName = {};
for (const m of methods) (byName[m.name] = byName[m.name] || []).push(m.file);
const dupName = Object.entries(byName)
  .filter(([, files]) => files.length > 1)
  .sort((a, b) => b[1].length - a[1].length);
console.log('  nama yang dipakai >1 file : ' + dupName.length);
for (const [n, files] of dupName.slice(0, 18)) {
  console.log('    ' + String(files.length).padStart(3) + 'x  ' + n + '  ->  ' +
    files.map(f => path.basename(f, '.php')).slice(0, 4).join(', '));
}

/* ---------- D. Fetch tanpa penanganan error ---------- */
console.log('\n=== D. AJAX: fetch() tanpa catch nearby ===');
const views = rekursif(P('resources', 'views')).filter(f => f.endsWith('.blade.php'));
let fetchTotal = 0, fetchNoCatch = 0;
const noCatchList = [];
for (const f of views) {
  const isi = fs.readFileSync(f, 'utf8');
  const fetches = [...isi.matchAll(/await fetch\(/g)];
  if (!fetches.length) continue;
  fetchTotal += fetches.length;
  for (const fc of fetches) {
    const setelah = isi.slice(fc.index, fc.index + 1400);
    if (!/\bcatch\b/.test(setelah)) {
      fetchNoCatch++;
      if (noCatchList.length < 10) {
        const line = isi.slice(0, fc.index).split('\n').length;
        noCatchList.push(path.relative(root, f) + ' baris ' + line);
      }
    }
  }
}
console.log('  total await fetch() : ' + fetchTotal);
console.log('  tanpa catch dalam 1400 karakter berikutnya : ' + fetchNoCatch);
for (const l of noCatchList) console.log('    ' + l);

/* ---------- E. Form tanpa loading ---------- */
console.log('\n=== E. FORM POST tanpa loading state ----------');
const postForms = [];
for (const f of views) {
  const isi = fs.readFileSync(f, 'utf8');
  const forms = [...isi.matchAll(/<form[^>]*method="POST"[\s\S]{0,4000}?<\/form>/gi)];
  for (const fm of forms) {
    const blok = fm[0];
    const action = (blok.match(/action="([^"]*)"/) || [])[1] || '?';
    const punyaLoading = /disabled|sedangMenyimpan|loading|fa-spin|prosesSubmit/.test(blok);
    postForms.push({ file: path.relative(root, f), action, punyaLoading });
  }
}
const tanpaLoading = postForms.filter(p => !p.punyaLoading);
console.log('  form POST ditemukan : ' + postForms.length);
console.log('  tanpa jejak loading/disabled : ' + tanpaLoading.length);
const byFile = {};
for (const p of tanpaLoading) (byFile[p.file] = byFile[p.file] || []).push(p.action);
for (const [f, actions] of Object.entries(byFile).slice(0, 14)) {
  console.log('    ' + f);
  for (const a of actions) console.log('        ' + a);
}