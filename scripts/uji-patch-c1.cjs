/* Uji patch-c1-tier-harga.cjs dari kondisi SEBELUM patch.

   Sumber kondisi awal: `git stash`-free. Kita ambil versi HEAD dari git untuk
   6 file yang disentuh, taruh di folder uji, lalu jalankan skrip patch.
   Kalau hasilnya SAMA dengan file sekarang di grafikaorion, patch ini
   terbukti benar untuk folder lokal yang belum pernah di-patch. */

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const root = path.join(__dirname, '..');
const uji = path.join(root, 'uji-c1-scratch');

/* Hanya file yang disentuh patch. Helper sengaja TIDAK diambil dari HEAD
   (belum ada di HEAD) - patch harus membuatnya sendiri, itu yang diuji. */
const FILES = [
  'app/Http/Controllers/FinishingHargaController.php',
  'app/Http/Controllers/JasaHargaController.php',
  'app/Http/Controllers/SettingHargaController.php',
  'resources/views/finishing/index.blade.php',
  'resources/views/master/jasa/index.blade.php',
  'resources/views/master/setting_harga/index.blade.php',
];

fs.rmSync(uji, { recursive: true, force: true });
fs.mkdirSync(path.join(uji, 'scripts'), { recursive: true });
fs.copyFileSync(
  path.join(root, 'scripts', 'patch-c1-tier-harga.cjs'),
  path.join(uji, 'scripts', 'patch-c1-tier-harga.cjs')
);

console.log('=== 1. mengambil versi HEAD (kondisi sebelum patch) ===');
let gagalHead = 0;
for (const rel of FILES) {
  const dst = path.join(uji, rel);
  fs.mkdirSync(path.dirname(dst), { recursive: true });
  try {
    const isi = execFileSync('git', ['show', 'HEAD:' + rel], { cwd: root, maxBuffer: 30 * 1024 * 1024 });
    fs.writeFileSync(dst, isi);
    const sudah = isi.includes('CekRentangHarga');
    console.log('  OK    ' + rel + (sudah ? '   (PERINGATAN: sudah ada patch di HEAD)' : ''));
  } catch (e) {
    console.log('  GAGAL ' + rel);
    gagalHead++;
  }
}
if (gagalHead) process.exit(1);

console.log('\n=== 2. menjalankan patch ===');
let kode = 0;
try {
  console.log(execFileSync('node', [path.join('scripts', 'patch-c1-tier-harga.cjs')], {
    cwd: uji, encoding: 'utf8'
  }).trimEnd());
} catch (e) {
  kode = e.status || 1;
  console.log((e.stdout || '') + (e.stderr || ''));
}

console.log('\n=== 3. membandingkan dengan file sekarang ===');
// Helper tidak ada di HEAD, jadi tidak bisa dibandingkan. Yang dibandingkan 6 file.
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
        if (n <= 3) {
          console.log('      baris ' + (i + 1));
          console.log('        uji  : ' + JSON.stringify((la[i] || '').slice(0, 105)));
          console.log('        asli : ' + JSON.stringify((lb[i] || '').slice(0, 105)));
        }
      }
    }
    console.log('      total baris beda: ' + n);
  }
}

// Helper harus sama juga dengan yang sekarang (kecuali typo yang saya rapikan manual).
const hj = fs.readFileSync(path.join(uji, 'app/Support/CekRentangHarga.php'), 'utf8');
const ha = fs.readFileSync(path.join(root, 'app/Support/CekRentangHarga.php'), 'utf8');
if (hj === ha) {
  console.log('  SAMA PERSIS  app/Support/CekRentangHarga.php (file baru)');
} else {
  beda++;
  console.log('  BERBEDA      app/Support/CekRentangHarga.php (file baru)');
  const la = hj.split('\n'), lb = ha.split('\n');
  for (let i = 0; i < Math.max(la.length, lb.length); i++) {
    if (la[i] !== lb[i]) {
      console.log('      baris ' + (i + 1));
      console.log('        patch: ' + JSON.stringify((la[i] || '').slice(0, 105)));
      console.log('        asli : ' + JSON.stringify((lb[i] || '').slice(0, 105)));
      break;
    }
  }
}

console.log('\n=== 4. menguji idempotensi (jalankan dua kali) ===');
try {
  const out2 = execFileSync('node', [path.join('scripts', 'patch-c1-tier-harga.cjs')], {
    cwd: uji, encoding: 'utf8'
  });
  const lewat = (out2.match(/\[lewat\]/g) || []).length;
  const pasang = (out2.match(/\[pasang\]|\[buat\]/g) || []).length;
  console.log('  [lewat] : ' + lewat + '   [pasang/buat] : ' + pasang);
  console.log('  ' + (pasang === 0 ? 'IDEMPOTEN - tidak ada yang ditulis ulang' : 'MASIH ADA YANG DIULANG'));
  if (pasang !== 0) beda++;
} catch (e) {
  console.log('  GAGAL pada jalan kedua:\n' + (e.stdout || '') + (e.stderr || ''));
  beda++;
}

console.log('\nfolder uji: ' + uji);
process.exit(kode || beda ? 1 : 0);