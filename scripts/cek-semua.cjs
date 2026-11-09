/* Jalankan cek-teks.cjs untuk semua file yang diubah patch. Kalau ada kata
   gagal ketik, ini yang Causes-nya. */

const { execFileSync } = require('child_process');
const path = require('path');

const targets = process.argv.slice(2);
for (const t of targets) {
  try {
    const out = execFileSync('node', [path.join(__dirname, 'cek-teks.cjs'), t], { encoding: 'utf8' });
    console.log(out.trimEnd());
    console.log('');
  } catch (e) {
    console.log((e.stdout || '') + (e.stderr || ''));
  }
}