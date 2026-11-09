// Ganti string pesan validasi Inggris -> Indonesia setelah lang:add id.
const fs = require('fs');
const ucfirst = (s) => s.charAt(0).toUpperCase() + s.slice(1);

const files = [
  'tests/Feature/UmpanBalikFormNativeTest.php',
  'tests/Feature/UmpanBalikVisibilityTest.php',
];
for (const p of files) {
  let s = fs.readFileSync(p, 'utf8');
  s = s.replace(/'The ([^']+?) field is required\.'/g, (m, a) => `'${ucfirst(a)} wajib diisi.'`);
  fs.writeFileSync(p, s);
  console.log(p, 'ok');
}

let a = fs.readFileSync('tests/Feature/UmpanBalikAjaxTest.php', 'utf8');
a = a.replace(`'nama supplier'`, `'Nama supplier wajib diisi.'`);
a = a.replace(`'nama konsumen'`, `'Nama konsumen wajib diisi.'`);
fs.writeFileSync('tests/Feature/UmpanBalikAjaxTest.php', a);
console.log('tests/Feature/UmpanBalikAjaxTest.php ok');
