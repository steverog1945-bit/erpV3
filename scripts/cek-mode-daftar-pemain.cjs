/* Cek bahwa `tipe` benar-benar ada di scope fungsi toggleLineType di ketiga
   halaman, dan input size_quantities punya `disabled` di blade. */

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const files = [
  'resources/views/jersey/orders/create.blade.php',
  'resources/views/jersey/orders/edit-lines.blade.php',
  'resources/views/jersey/orders/show.blade.php',
];

let gagal = 0;

for (const rel of files) {
  const isi = fs.readFileSync(path.join(root, rel), 'utf8');
  console.log('\n=== ' + rel + ' ===');

  // 1. Cari blok toggleLineType
  const m = isi.match(/function toggleLineType\([^)]*\)\s*\{[\s\S]*?\n    \}/);
  if (!m) {
    console.log('  GAGAL  function toggleLineType tidak ditemukan');
    gagal++;
    continue;
  }
  const blok = m[0];
  const baris = isi.slice(0, m.index).split('\n').length;

  // 2. Apakah `tipe` dideklarasikan sebelum pemakaian pertama?
  const deklarasi = blok.match(/const\s+tipe\s*=/);
  const pertamaPakai = blok.indexOf("tipe === 'player_list'");
  console.log('  baris fungsi      : ' + baris);
  console.log('  `const tipe =`    : ' + (deklarasi ? 'ada' : 'TIDAK ADA'));
  console.log('  pemakaian pertama: baris relatif ' + blok.slice(0, pertamaPakai).split('\n').length);
  if (!deklarasi) { console.log('  GAGAL  tipe tidak dideklarasikan'); gagal++; }

  // 3. Apakah toggle disabled ikut?
  const adaDisabled = blok.includes("el.disabled = bukanModePemain;");
  console.log('  toggle disabled   : ' + (adaDisabled ? 'ada' : 'TIDAK ADA'));
  if (!adaDisabled) { console.log('  GAGAL  tidak ada toggle disabled'); gagal++; }

  // 4. Apakah baris lama sudah diganti?
  const masihLama = isi.includes("wrapper-qty-ukuran')?.classList.toggle('hidden', tipe === 'player_list');");
  console.log('  baris lama masih ada : ' + (masihLama ? 'YA - GAGAL' : 'tidak ada'));
  if (masihLama) { console.log('  GAGAL  baris lama belum diganti'); gagal++; }
}

console.log('\n=== blade: input size_quantities ===');
const blade = fs.readFileSync(path.join(root, 'resources/views/jersey/orders/_line-card-fields.blade.php'), 'utf8');
const cek = [
  ['ada atribut disabled kondisional', blade.includes("{{ $otIsi === 'player_list' ? 'disabled' : '' }}")],
  ['ada gaya disabled:', blade.includes('disabled:opacity-50')],
  ['komentar percentage masih ada', blade.includes('Persentase harga dihapus dari sini')],
  ['komentar disable ada', / sekadar\s+disembunyikan/.test(blade)],
  ['lebar kolom Ukuran w-40', blade.includes('w-40">Ukuran')],
  ['lebar kolom No. w-12', blade.includes('w-12">No.')],
  ['lebar kolom Gender w-14', blade.includes('w-14">Gender')],
];
for (const [label, ok] of cek) {
  console.log('  ' + (ok ? 'OK   ' : 'GAGAL') + '  ' + label);
  if (!ok) gagal++;
}

console.log('\n' + (gagal === 0 ? 'Semua OK.' : gagal + ' masalah.'));
process.exit(gagal ? 1 : 0);