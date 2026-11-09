/* AUDIT XSS & SANITASI INPUT
   Menggabungkan tiga pertanyaan berbeda yang sering tercampur:
     1. Escape output  - apakah nilai dari database aman ditampilkan?
     2. Placeholder   - apakah nilai itu masuk ke atribut HTML/JS?
     3. DOM write     - apakah ada yang menulis HTML mentah lewat JS?
*/

const fs = require('fs');
const path = require('path');

const root = __dirname + '/..';
const views = [];
(function walk(d) {
  for (const e of fs.readdirSync(d, { withFileTypes: true })) {
    const p = path.join(d, e.name);
    if (e.isDirectory()) walk(p);
    else if (e.name.endsWith('.blade.php')) views.push(p);
  }
})(path.join(root, 'resources', 'views'));

const rel = (v) => path.relative(root, v).replace(/\\/g, '/');

/* -- 1. {!! !!} : output tanpa escape ------------------------------- */
const mentah = [];
for (const v of views) {
  const c = fs.readFileSync(v, 'utf8');
  for (const m of c.matchAll(/\{!!([\s\S]{0,200}?)!!\}/g)) {
    mentah.push({ file: rel(v), isi: m[1].trim().slice(0, 90) });
  }
}

/* -- 2. Nilai mentah di dalam atribut HTML ------------------------ */
// mis. onclick="f({!! $x !!})" atau title="{{ $x }}"
const diAtribut = [];
for (const v of views) {
  const c = fs.readFileSync(v, 'utf8');
  // nilai mentah di dalam tanda kutip atribut
  for (const m of c.matchAll(/=\s*"([^"]*\{!![^"]*)"/g)) {
    diAtribut.push({ file: rel(v), isi: m[1].trim().slice(0, 90) });
  }
}

/* -- 3. JS: penulisan HTML mentah ---------------------------------- */
const innerHtml = [];
for (const v of views) {
  const c = fs.readFileSync(v, 'utf8');
  for (const m of c.matchAll(/\.innerHTML\s*=\s*([^;\n]{1,110})/g)) {
    const rhs = m[1];
    // Beda: menyisipkan variabel ke HTML berarti XSS kalau variabelnya
    // tidak di-escape. Menyisipkan string literal statis aman.
    const sisipVariabel = /\$\{|\+ *[a-zA-Z_$][\w.$]*|\b(var|let|const)\b/.test(rhs) && !/^['"]/.test(rhs.trim());
    if (sisipVariabel) innerHtml.push({ file: rel(v), isi: rhs.trim().slice(0, 100) });
  }
}

const jml = (a) => a.length;

console.log('='.repeat(72));
console.log('AUDIT XSS / SANITASI OUTPUT');
console.log('='.repeat(72));
console.log('');
console.log('  Output tanpa escape {!! !!}   : ' + jml(mentah));
console.log('  Nilai mentah di dalam atribut : ' + jml(diAtribut));
console.log('  Penulisan HTML lewat JS       : ' + jml(innerHtml));
console.log('');

if (mentah.length) {
  console.log('## {!! !!} - output tanpa escape');
  const group = {};
  for (const x of mentah) group[x.file] = (group[x.file] || 0) + 1;
  Object.entries(group).sort((a, b) => b[1] - a[1]).forEach(([f, n]) => {
    console.log('   ' + String(n).padStart(3) + 'x  ' + f);
  });
  console.log('');
  console.log('   Contoh:');
  mentah.slice(0, 12).forEach(x => console.log('     ' + x.file + '\n        ' + x.isi));
  console.log('');
}

if (diAtribut.length) {
  console.log('## Nilai mentah di dalam atribut HTML');
  diAtribut.slice(0, 15).forEach(x => console.log('   ' + x.file + '\n      ' + x.isi));
  console.log('');
}

if (innerHtml.length) {
  console.log('## Penulisan HTML lewat JS (perlu diperiksa apakah nilainya di-escape)');
  const group = {};
  for (const x of innerHtml) group[x.file] = (group[x.file] || 0) + 1;
  Object.entries(group).sort((a, b) => b[1] - a[1]).slice(0, 15).forEach(([f, n]) => {
    console.log('   ' + String(n).padStart(3) + 'x  ' + f);
  });
  console.log('');
}
