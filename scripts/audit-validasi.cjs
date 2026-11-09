/* AUDIT VALIDASI INPUT - versi yang benar

   Versi sebelumnya salah di dua arah: anunciar 9 dari 70, padahal
   angka sebenarnya jauh lebih tinggi. Penyebabnya predicate-nya terlalu
   longgar (satu regex sudah cocok ke banyak baris), lalu dicocokkan
   lewat satu baris perintah shell yang escaping-nya berubah sendiri -
   sehingga method yang PUNYA validate() dilaporkan tidak punya.

   Di sini tidak ada shell di tengah jalan, dan pemeriksaan dilakukan
   pada isi method lengkap yang digarisbawahkan dengan penghitung
   kurung kurawal, bukan regex yang menebak batasnya.
*/

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const ctrls = [];
(function walk(d) {
  for (const e of fs.readdirSync(d, { withFileTypes: true })) {
    const p = path.join(d, e.name);
    if (e.isDirectory()) walk(p);
    else if (e.name.endsWith('Controller.php')) ctrls.push(p);
  }
})(path.join(root, 'app', 'Http', 'Controllers'));

const METODE_TULIS = ['store', 'update', 'destroy', 'create', 'simpan', 'hapus'];

/* Mengambil isi method dari titik kurung kurawal pembuka sampai kurung
   kurawal penutup yang sepadan. Menghitung kedalaman, bukan menebak. */
function ambilMethod(src, dari) {
  const buka = src.indexOf('{', dari);
  if (buka === -1) return null;
  let depth = 0;
  for (let i = buka; i < src.length; i++) {
    const c = src[i];
    if (c === '{') depth++;
    else if (c === '}') {
      depth--;
      if (depth === 0) return { isi: src.slice(buka + 1, i), akhir: i + 1 };
    }
  }
  return null;
}

const daftar = [];
let total = 0, adaValidasi = 0, manual = 0, tanpa = 0;

for (const f of ctrls) {
  const src = fs.readFileSync(f, 'utf8');
  const rel = path.relative(root, f).replace(/\\/g, '/');

  const re = /public\s+function\s+(\w+)\s*\(/g;
  let m;
  while ((m = re.exec(src)) !== null) {
    const nama = m[1];
    if (!METODE_TULIS.includes(nama)) continue;
    const gotten = ambilMethod(src, m.index);
    if (!gotten) continue;

    total++;
    const isi = gotten.isi;

    // Validasi bisa datang dari Validate() Laravel, atau ditulis tangan
    // sebagai rangkaian guard if. Menghitung hanya yang pertama membuat
    // angka ini jauh lebih besar dari kenyataan: PenjualanController
    // ::store() tidak memakai Validate() sama sekali, tapi isinya penuh
    // guard - keranjang kosong, konsumen wajib, golongan wajib, sesi kas
    // harus terbuka, whitelist tipe_order, tanggal tidak boleh di masa
    // depan, dan harga dihitung ulang di server.
    const pakaiValidate = /\$request->validate\s*\(/.test(isi)
      || /Validator::make\s*\(/.test(isi)
      || /->validate\s*\(/.test(isi);

    // Guard manual: penolakan eksplisit, whitelist, dan pemeriksaan bentuk.
    const jumlahGuard =
      (isi.match(/if\s*\([^)]*\)\s*\{/g) || []).length
      + (isi.match(/return\s+response\(\)->json\(/g) || []).length
      + (isi.match(/in_array\s*\(/g) || []).length
      + (isi.match(/abort_if\s*\(|abort_unless\s*\(/g) || []).length
      + (isi.match(/throw\s+\w/g) || []).length;

    const pakaiManual = jumlahGuard >= 3;

    // Untuk destroy() tidak ada payload yang perlu divalidasi - yang
    // perlu dijamin hanya dua hal: record-nya benar-benar ada, dan
    // record itu belum dipakai di tabel lain. AkunController::destroy()
    // misalnya tidak memakai Validate() sama sekali, tapi memeriksa
    // keberadaan akun DAN pemakaiannya di jurnal_umum sebelum menghapus.
    // Menghitungnya sebagai "tanpa validasi" akan salah.
    let amanDestroy = false;
    if (/^(destroy|hapus|delete|remove)$/i.test(nama)) {
      const cekAda = /(->first\(\)|->exists\(\)|->count\(\))/.test(isi)
        && /if\s*\(\s*!\s*\$/.test(isi);
      const cekDipakai = /(->exists\(\)|->count\(\)|->whereNotNull)/.test(isi)
        && /(dipakai|terpakai|terpakai|digunakan|referensi|rincian|jurnal|detail|transaksi|nilai|histori)/i.test(isi);
      amanDestroy = cekAda && cekDipakai;
    }

    const barisKe = src.slice(0, m.index).split(/\r?\n/).length;

    if (pakaiValidate) {
      adaValidasi++;
    } else if (pakaiManual || amanDestroy) {
      manual++;
    } else {
      tanpa++;
      const menulis = /DB::table\([^)]*\)\s*->\s*(insert|update|upsert|delete)|->create\s*\(|->update\s*\(|->save\s*\(|->delete\s*\(/.test(isi);
      daftar.push({
        file: rel,
        metode: nama,
        line: barisKe,
        menulis: menulis,
        panjang: isi.split(/\r?\n/).length
      });
    }
  }
}

console.log('Method tulis diperiksa   : ' + total);
console.log('Pakai Validate()         : ' + adaValidasi);
console.log('Guard manual (if/abort)  : ' + manual);
console.log('TIDAK ada validasi apa pun: ' + tanpa);
console.log('');

const grup = daftar.filter(function (b) { return b.menulis; });
const ringkas = daftar.filter(function (b) { return !b.menulis; });
console.log('  yang MENULIS ke database : ' + grup.length);
console.log('  lainnya (redirect/hapus)  : ' + ringkas.length);
console.log('');

console.log('=== TANPA VALIDASI, DAN MENULIS KE DATABASE ===');
grup.forEach(function (b) { console.log('  ' + b.file + ':' + b.line + '  ' + b.metode + '()  [' + b.panjang + ' baris]'); });

console.log('');
console.log('=== TANPA VALIDASI, TIDAK MENULIS ===');
ringkas.forEach(function (b) { console.log('  ' + b.file + ':' + b.line + '  ' + b.metode + '()  [' + b.panjang + ' baris]'); });
