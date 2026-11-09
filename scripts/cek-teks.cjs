/* Cek satu file: karakter non-ASCII + daftar kata yang sering rusak saat
   penulisan. Dipakai karena `node -e` di PowerShell merusak kutip. */

const fs = require('fs');
const path = require('path');

const target = process.argv[2];
const s = fs.readFileSync(target, 'utf8');

const bad = [...new Set(s.split('').filter(c => c.charCodeAt(0) > 127))];
console.log('file   : ' + path.basename(target));
console.log('baris  : ' + s.split('\n').length);
console.log('non-ASCII : ' + (bad.length ? bad.join(' ') : 'tidak ada'));

// Kata yang pernah keluar rusak. Kalau muncul, kata itu masih di file.
const rusak = [
  'sourcedari', 'diffgitymesak', '实施办法', '赋值', '代入', '语句',
  'berbedafv', 'perubahanya', 'menyimpanan', 'terbayar', 'yangg', 'dengann',
  'adalahh',
];
let ketemu = 0;
for (const w of rusak) {
  const n = s.split(w).length - 1;
  if (n) { console.log('  KATA RUSAK "' + w + '" : ' + n); ketemu++; }
}
console.log(ketemu === 0 ? 'kata rusak : tidak ada' : '');