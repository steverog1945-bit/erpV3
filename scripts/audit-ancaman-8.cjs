/* Threat model statis untuk alur Customer -> Journal.

   Yang dicari, per kategori:
     A. Mass assignment  : $request->all() / fill() / create($request->...)
     B. SQL mentah       : whereRaw / orderByRaw / selectRaw / DB::raw / statement
     C. Kandidat IDOR    : controller yang menerima {id} lalu find/findOrFail
                           tanpa cek kepemilikan
     D. Eksposur file    : download / readfile / file() / Storage::disk
     E. Upload           : ->file( / move( / store( tanpa validasi MIME
     F. Route tanpa auth : deklarasi route di luar grup auth

   READ-ONLY. Tidak mengubah file apa pun. */

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

const baris = (isi) => {
  const l = isi.split('\n');
  return (idx) => (l[idx] || '').trim();
};

const ctrl = rekursif(P('app', 'Http', 'Controllers'));
const views = rekursif(P('app', 'Resources')).concat(rekursif(P('resources', 'views')));
const routes = P('routes', 'web.php');
const web = fs.readFileSync(routes, 'utf8');

const kosongkan = (s) => s.replace(/\s+/g, ' ');

/* ---------------------------------------------------------------- A */
console.log('=== A. MASS ASSIGNMENT ===');
const massHits = [];
for (const f of ctrl) {
  const isi = fs.readFileSync(f, 'utf8');
  isi.split('\n').forEach((b, i) => {
    if (/\$request->all\(\)|->fill\(\s*\$request|\.create\(\s*\$request|\.update\(\s*\$request/.test(b)) {
      massHits.push({ f: path.relative(root, f), l: i + 1, code: kosongkan(b).slice(0, 120) });
    }
  });
}
console.log('  jumlah: ' + massHits.length);
for (const h of massHits.slice(0, 20)) {
  console.log('    ' + h.f + ':' + h.l);
  console.log('        ' + h.code);
}

/* ---------------------------------------------------------------- B */
console.log('\n=== B. SQL MENTAH ===');
const rawHits = [];
for (const f of ctrl) {
  const isi = fs.readFileSync(f, 'utf8');
  isi.split('\n').forEach((b, i) => {
    if (/(whereRaw|orderByRaw|selectRaw|havingRaw|groupByRaw|DB::raw|DB::statement|DB::unprepared)/.test(b)) {
      rawHits.push({ f: path.relative(root, f), l: i + 1, code: kosongkan(b).slice(0, 130) });
    }
  });
}
console.log('  jumlah: ' + rawHits.length);
// Yang mencurigakan: ada variabel PHP di dalam raw
const mencurigakan = rawHits.filter(h => /\$\w+/.test(h.code) && !/\$\w+\s*\?\?/.test(h.code));
console.log('  dengan variabel PHP di dalamnya: ' + mencurigakan.length);
for (const h of mencurigakan.slice(0, 24)) {
  console.log('    ' + h.f + ':' + h.l);
  console.log('        ' + h.code);
}

/* ---------------------------------------------------------------- C */
console.log('\n=== C. KANDIDAT IDOR ===');
// Route model binding / parameter {id} per prefix
const paramRoutes = [...web.matchAll(/Route::(get|post|put|patch|delete)\(\s*'([^']*\{[^}]+\}[^']*)'/g)];
console.log('  route dengan parameter: ' + paramRoutes.length);

// Controller yang memakai find($id) / findOrFail($id) / ::find( tanpa cek
// kepemilikan di method yang sama.
const idor = [];
for (const f of ctrl) {
  const isi = fs.readFileSync(f, 'utf8');
  const blok = isi.split(/\n    public function |\n    private function /);
  for (let b = 1; b < blok.length; b++) {
    const badan = blok[b];
    const nama = (badan.match(/function (\w+)/) || [])[1] || '?';
    const ambilId = /::find\(\s*\$|->find\(\s*\$|findOrFail\(\s*\$/.test(badan);
    if (!ambilId) continue;
    // Adakah cek kepemilikan/otorisasi di badan yang sama?
    const cekOtorisasi = /(authorize|Gate::|can\(|abort_unless|abort_if|policies\(\)|->authorize\()/.test(badan);
    if (!cekOtorisasi) {
      idor.push({
        f: path.relative(root, f),
        nama,
        cuplikan: kosongkan(badan.slice(0, 260)).slice(0, 200),
      });
    }
  }
}
console.log('  method dengan find($id) tanpa authorize() di badan yang sama: ' + idor.length);
for (const h of idor.slice(0, 22)) {
  console.log('    ' + h.f + '::' + h.nama);
}

/* ---------------------------------------------------------------- D */
console.log('\n=== D. EKSOSUR FILE ===');
const fileHits = [];
for (const f of ctrl) {
  const isi = fs.readFileSync(f, 'utf8');
  isi.split('\n').forEach((b, i) => {
    if (/(response\(\)->download|Storage::download|->download\(|readfile\(|file_get_contents\(|fopen\(|->response\(\)->file|streamDownload)/.test(b)) {
      fileHits.push({ f: path.relative(root, f), l: i + 1, code: kosongkan(b).slice(0, 120) });
    }
  });
}
console.log('  jumlah: ' + fileHits.length);
for (const h of fileHits) {
  console.log('    ' + h.f + ':' + h.l);
  console.log('        ' + h.code);
}

/* ---------------------------------------------------------------- E */
console.log('\n=== E. UPLOAD ===');
const upHits = [];
for (const f of ctrl) {
  const isi = fs.readFileSync(f, 'utf8');
  isi.split('\n').forEach((b, i) => {
    if (/->file\(|\.move\(|->store(As|Public)?\(|getClientOriginalExtension|getClientOriginalName/.test(b)) {
      upHits.push({ f: path.relative(root, f), l: i + 1, code: kosongkan(b).slice(0, 120) });
    }
  });
}
console.log('  jumlah: ' + upHits.length);
for (const h of upHits) {
  console.log('    ' + h.f + ':' + h.l);
  console.log('        ' + h.code);
}

/* ---------------------------------------------------------------- F */
console.log('\n=== F. ROUTE DI LUAR GRUP AUTH ===');
// Cari blok Route:: di luar middleware auth
const barisWeb = web.split('\n');
let dalamAuth = false;
const luarAuth = [];
for (let i = 0; i < barisWeb.length; i++) {
  const b = barisWeb[i];
  if (/Route::middleware\(\s*\[[^\]]*'auth'/.test(b) || /->middleware\('auth'\)/.test(b)) dalamAuth = true;
  if (/^\s*\}\);?\s*$/.test(b) && dalamAuth) dalamAuth = false;
  if (/Route::(get|post|put|patch|delete)\(/.test(b) && !dalamAuth) {
    if (/login|sinyal|\/|storage/.test(b) === false) {
      luarAuth.push({ l: i + 1, code: kosongkan(b).slice(0, 120) });
    }
  }
}
console.log('  route di luar grup auth (kecuali login/sinyal/storage/root): ' + luarAuth.length);
for (const h of luarAuth.slice(0, 20)) {
  console.log('    L' + h.l + ': ' + h.code);
}