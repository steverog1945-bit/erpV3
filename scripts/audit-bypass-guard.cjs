/* Audit: form yang MELALUI global submit guard di layouts/app.blade.php.

   layouts/app.blade.php baris 2178 memasang guard ini untuk SEMUA form:
     - menolak klik ganda (dataset.sedangKirim)
     - menonaktifkan tombol submit
     - menghidupkan lagi setelah 8 detik bila halaman tidak berpindah

   Tapi baris 2180:
     if (e.defaultPrevented || ...) return;

   Form yang punya listener submit sendiri dan memanggil e.preventDefault()
   AKAN keluar dari guard global. Itu tidak salah - memang/me dengan
   automatic. Tapi kalau handler itu tidak memasang pagarnya sendiri, form itu
   kehilangan: pagaran klik ganda + tombol mati + pemulihannya.

   Script ini mencari tepat pola itu. Ini temuan yang bisa dibuktikan, bukan
   "kelihatan tidak ada spinner".
   */

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');

function rekursif(dir, out = []) {
  if (!fs.existsSync(dir)) return out;
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) rekursif(p, out);
    else if (e.name.endsWith('.blade.php')) out.push(p);
  }
  return out;
}

/* Pola listener submit milik halaman. */
const reListener = /addEventListener\(\s*['"]submit['"]\s*,\s*async?\s*function\s*\((\w+)\)\s*\{/g;

const temuan = [];

for (const f of rekursif(path.join(root, 'resources', 'views'))) {
  const isi = fs.readFileSync(f, 'utf8');

  for (const m of isi.matchAll(reListener)) {
    const mulai = m.index;
    // Ambil badan fungsi: dari posisi setelah { sampai 6000 karakter,
    // dihitung dengan keseimbangan kurung kurawal.
    const dari = isi.indexOf('{', mulai);
    let depth = 0, akhir = dari;
    for (let i = dari; i < Math.min(isi.length, dari + 8000); i++) {
      if (isi[i] === '{') depth++;
      else if (isi[i] === '}') { depth--; if (depth === 0) { akhir = i; break; } }
    }
    const badan = isi.slice(dari, akhir + 1);

    const preventDefault = /e\.preventDefault\(\)|preventDefault\(\)/.test(badan);
    if (!preventDefault) continue;

    const baris = isi.slice(0, mulai).split('\n').length;

    // Pagar milik halaman: apakah ada?
    const pagar = {
      'flag sedang dikirim': /sedangMenyimpan|sedangKirim|isSubmitting|submitting|busy\b/.test(badan),
      'tombol dinonaktifkan': /\.disabled\s*=\s*true/.test(badan),
      'tombol dihidupkan lagi': /\.disabled\s*=\s*false/.test(badan),
      'teks/ikon loading': /fa-spin|Menyimpan|menyimpan|loading|proses\(/i.test(badan),
      'native form.submit()': /\.submit\(\)/.test(badan),
    };

    // Yang: preventDefault tapi TIDAK pasang pagar minimal (tak ada disabled sama sekali).
    const tanpaPagar = !pagar['tombol dinonaktifkan'] && !pagar['flag sedang dikirim'];

    temuan.push({
      file: path.relative(root, f),
      baris,
      pagar,
      tanpaPagar,
    });
  }
}

console.log('=== Form dengan listener submit sendiri + preventDefault ===');
console.log('  jumlah: ' + temuan.length + '\n');

console.log('--- KELOMPOK A: sudah memasang pagar sendiri (aman) ---');
const aman = temuan.filter(t => !t.tanpaPagar);
console.log('  jumlah: ' + aman.length);
for (const t of aman) {
  const p = Object.entries(t.pagar).filter(([, v]) => v).map(([k]) => k).join(', ');
  console.log('    ' + t.file + '  baris ' + t.baris);
  console.log('        pagar: ' + p);
}

console.log('\n--- KELOMPOK B: preventDefault tapi TIDAK ada pagar klik ganda ---');
const bahaya = temuan.filter(t => t.tanpaPagar);
console.log('  jumlah: ' + bahaya.length + '   <== kehilangan guard global');
for (const t of bahaya) {
  const p = Object.entries(t.pagar).filter(([, v]) => v).map(([k]) => k).join(', ');
  console.log('    ' + t.file + '  baris ' + t.baris + (p ? '  (pagar parsial: ' + p + ')' : '  (tanpa pagar sama sekali)'));
}