/* Uji patch dari kondisi awal yang jujur.

   Sumber kondisi awal: versi file di git HEAD. Kalau patch ini bisa
   dipasang ke file HEAD dan menghasilkan penanda yang sama dengan file
   sekarang, berarti patch-nya benar untuk folder lokal Anda.

   Bukan pengganti uji di browser - hanya membuktikan tidak ada pola
   yang gagal贴 dan tidak ada perubahan lain yang ikut tersentuh. */

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const root = path.join(__dirname, '..');
const uji = path.join(root, 'uji-patch-scratch');

const FILES = [
  'resources/views/jersey/orders/create.blade.php',
  'resources/views/jersey/orders/edit-lines.blade.php',
  'resources/views/jersey/orders/show.blade.php',
  'app/Http/Controllers/Jersey/JerseyOrderController.php',
];

/* 1. Siapkan folder uji berisi versi HEAD */
fs.rmSync(uji, { recursive: true, force: true });
fs.mkdirSync(path.join(uji, 'scripts'), { recursive: true });
fs.copyFileSync(
  path.join(root, 'scripts', 'patch-simpan-order-jersey.cjs'),
  path.join(uji, 'scripts', 'patch-simpan-order-jersey.cjs')
);

console.log('=== 1. menyalin versi HEAD ke folder uji ===');
let gagalHead = 0;
for (const rel of FILES) {
  const dst = path.join(uji, rel);
  fs.mkdirSync(path.dirname(dst), { recursive: true });
  try {
    const isi = execFileSync('git', ['show', 'HEAD:' + rel], { cwd: root, maxBuffer: 20 * 1024 * 1024 });
    fs.writeFileSync(dst, isi);
    console.log('  OK    ' + rel + '  (' + isi.length + ' byte)');
  } catch (e) {
    console.log('  GAGAL ' + rel + '  (tidak ada di HEAD)');
    gagalHead++;
  }
}
if (gagalHead) process.exit(1);

/* 2. Pastikan versi HEAD memang BELUM punya patch */
console.log('');
console.log('=== 2. kondisi HEAD harus belum terpatch ===');
for (const rel of FILES) {
  const isi = fs.readFileSync(path.join(uji, rel), 'utf8');
  const sudah = isi.includes('required_unless:lines.*.order_type,player_list') ||
                isi.includes('f.required = tampilkanDefault;') ||
                isi.includes('sedangMenyimpan');
  console.log('  ' + (sudah ? 'GAGAL' : 'OK   ') + '  belum terpatch: ' + rel);
}

/* 3. Jalankan skrip patch di folder uji */
console.log('');
console.log('=== 3. menjalankan skrip patch ===');
let kode = 0;
try {
  const out = execFileSync('node', [path.join('scripts', 'patch-simpan-order-jersey.cjs')], {
    cwd: uji, encoding: 'utf8'
  });
  console.log(out.trimEnd());
} catch (e) {
  kode = e.status || 1;
  console.log((e.stdout || '') + (e.stderr || ''));
}

/* 4. Bandingkan dengan file sekarang */
console.log('');
console.log('=== 4. bandingkan hasil patch dengan file sekarang ===');
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
    const harusSama = ['// Di bawah ini wajib untuk mode', 'f.required', 'sedangMenyimpan', 'required_unless'];
    let bedaDetail = 0;
    const max = Math.max(la.length, lb.length);
    for (let i = 0; i < max; i++) {
      if (la[i] !== lb[i]) {
        bedaDetail++;
        if (bedaDetail <= 3) {
          console.log('      baris ' + (i + 1));
          console.log('        uji : ' + JSON.stringify((la[i] || '').slice(0, 100)));
          console.log('        asli: ' + JSON.stringify((lb[i] || '').slice(0, 100)));
        }
      }
    }
    console.log('      total baris beda: ' + bedaDetail);
  }
}

console.log('');
console.log('folder uji: ' + uji);
process.exit(kode || beda ? 1 : 0);