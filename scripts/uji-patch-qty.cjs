/* Uji patch-qty-daftar-pemain.cjs dari kondisi awal yang jujur: versi HEAD.

   Kalau patch dipasang ke file HEAD dan hasilnya sama persis dengan file
   sekarang di grafikaorion, berarti patch-nya benar untuk folder lokal user
   yang belum pernah di-patch. */

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const root = path.join(__dirname, '..');
const uji = path.join(root, 'uji-qty-scratch');

const FILES = [
  'app/Http/Controllers/Jersey/JerseyOrderController.php',
  'resources/views/jersey/orders/_line-card-fields.blade.php',
  // Patch 1 (patch-simpan-order-jersey.cjs) menyentuh file-file ini juga.
  // Semuanya ikut disalin supaya patch 1 benar-benar terpasang utuh dan
  // perbandingan dengan grafikaorion Apples-to-apples.
  'resources/views/jersey/orders/create.blade.php',
  'resources/views/jersey/orders/edit-lines.blade.php',
  'resources/views/jersey/orders/show.blade.php',
];

/* Patch 1 (yang sudah lebih dulu ada) ikut dipasang supaya file HEAD
   berada di kondisi yang sama dengan kondisi sebelum patch qty. */
const PATCHES = ['patch-simpan-order-jersey.cjs', 'patch-qty-daftar-pemain.cjs'];

fs.rmSync(uji, { recursive: true, force: true });
fs.mkdirSync(path.join(uji, 'scripts'), { recursive: true });
for (const p of PATCHES) {
  fs.copyFileSync(path.join(root, 'scripts', p), path.join(uji, 'scripts', p));
}

console.log('=== 1. menyalin versi HEAD ===');
for (const rel of FILES) {
  const dst = path.join(uji, rel);
  fs.mkdirSync(path.dirname(dst), { recursive: true });
  try {
    const isi = execFileSync('git', ['show', 'HEAD:' + rel], { cwd: root, maxBuffer: 20 * 1024 * 1024 });
    fs.writeFileSync(dst, isi);
    console.log('  OK    ' + rel);
  } catch (e) {
    console.log('  GAGAL ' + rel + ' (tidak ada di HEAD)');
    process.exit(1);
  }
}

console.log('\n=== 2. kedua patch dijalankan ===');
let kode = 0;
for (const p of PATCHES) {
  console.log('\n--- ' + p + ' ---');
  try {
    console.log(execFileSync('node', [path.join('scripts', p)], { cwd: uji, encoding: 'utf8' }).trimEnd());
  } catch (e) {
    kode = e.status || 1;
    console.log((e.stdout || '') + (e.stderr || ''));
  }
}

console.log('\n=== 3. bandingkan dengan file sekarang ===');
let beda = 0;
for (const rel of FILES) {
  const a = fs.readFileSync(path.join(uji, rel), 'utf8');
  const b = fs.readFileSync(path.join(root, rel), 'utf8');
  if (a === b) {
    console.log('  SAMA PERSIS  ' + rel);
  } else {
    beda++;
    console.log('  BERBEDA      ' + rel);
    const la = a.split('\n'), lb = b.split('\n');
    let n = 0;
    for (let i = 0; i < Math.max(la.length, lb.length); i++) {
      if (la[i] !== lb[i]) {
        n++;
        if (n <= 4) {
          console.log('      baris ' + (i + 1));
          console.log('        uji  : ' + JSON.stringify((la[i] || '').slice(0, 110)));
          console.log('        asli : ' + JSON.stringify((lb[i] || '').slice(0, 110)));
        }
      }
    }
    console.log('      total baris beda: ' + n);
  }
}

console.log('\nfolder uji: ' + uji);
process.exit(kode || beda ? 1 : 0);