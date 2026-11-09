const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');

const target = [
  ['resources/views/finishing/index.blade.php', 'submitTierFormAjax'],
  ['resources/views/master/jasa/index.blade.php', 'submitJasaTierFormAjax'],
  ['resources/views/master/setting_harga/index.blade.php', 'submitHargaTierFormAjax'],
];

function badanFungsi(isi, nama) {
  const m = isi.match(new RegExp('async\\s+function\\s+' + nama + '\\s*\\([^)]*\\)\\s*\\{'));
  if (!m) return null;
  const dari = isi.indexOf('{', m.index);
  let depth = 0, akhir = dari;
  for (let i = dari; i < isi.length; i++) {
    if (isi[i] === '{') depth++;
    else if (isi[i] === '}') { depth--; if (depth === 0) { akhir = i; break; } }
  }
  return isi.slice(dari, akhir + 1);
}

console.log('=== Dua pertiga helper tier AJAX: sama atau tidak? ===\n');
const hasil = [];
for (const [rel, nama] of target) {
  const isi = fs.readFileSync(path.join(root, rel), 'utf8');
  const badan = badanFungsi(isi, nama);
  if (!badan) { console.log('  TIDAK DITEMUKAN: ' + nama); continue; }
  const norm = badan.replace(/\s+/g, ' ').trim();
  const baris = norm.split(';').length;
  const pakaiSubmitId = /submit_id/.test(badan);
  const jsonBody = /Content-Type['"]?\s*:\s*['"]application\/json/.test(badan);
  const panggilSubmit = /\.submit\(\)/.test(badan);
  const adaPenaga = /(sedangKirim|sedangMenyimpan|__\w+|dataset\.|\.disabled\s*=\s*true)/.test(badan);
  console.log('  ' + nama + '  (' + rel.split('/').pop() + ')');
  console.log('    panjang badan   : ' + baris + ' pernyataan');
  console.log('    kirim submit_id : ' + (pakaiSubmitId ? 'YA' : 'TIDAK'));
  console.log('    body JSON       : ' + (jsonBody ? 'YA' : 'TIDAK'));
  console.log('    form.submit()   : ' + (panggilSubmit ? 'YA' : 'TIDAK'));
  console.log('    penaga klik ganda: ' + (adaPenaga ? 'ADA' : 'TIDAK'));
  hasil.push({ nama, norm, baris, jsonBody, adaPenaga });
  console.log('');
}

// Normalisasi nama fungsi supaya perbandingan focuses pada logic.
function normalisasi(s) {
  return s
    .replace(/submit\w*TierFormAjax/g, 'F')
    .replace(/\s+/g, ' ')
    .trim();
}
const n = hasil.map(h => normalisasi(h.norm));
const semuaSama = n.every(x => x === n[0]);

console.log('=== Perbandingan isi ===');
console.log('  jumlah helper : ' + n.length);
console.log('  semua identik setelah nama fungsi dinormalisasi : ' + (semuaSama ? 'YA' : 'TIDAK'));
if (!semuaSama) {
  for (let i = 1; i < n.length; i++) {
    const a = n[0], b = n[i];
    let pos = 0;
    while (pos < Math.min(a.length, b.length) && a[pos] === b[pos]) pos++;
    console.log('\n  ' + hasil[0].nama + ' vs ' + hasil[i].nama + ' - beda mulai posisi ' + pos);
    console.log('    A: ' + JSON.stringify(a.slice(Math.max(0, pos - 30), pos + 70)));
    console.log('    B: ' + JSON.stringify(b.slice(Math.max(0, pos - 30), pos + 70)));
  }
}

console.log('\n=== Kesimpulan proteksi kirim ganda ===');
for (const h of hasil) {
  const json = h.jsonBody;
  const panggilSubmit = false;
  // SubmitSekaliJalan hanya jalan bila submit_id ada di body.
  const terlindungi = !json && panggilSubmit;
  console.log('  ' + h.nama.padEnd(26) + ' terlindungi SubmitSekaliJalan: ' +
    (terlindungi ? 'YA' : 'TIDAK (body JSON tanpa submit_id)'));
}