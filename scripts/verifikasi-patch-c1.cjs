/* Verifikasi patch C1 terpasang di folder lokal. READ-ONLY.
   Tidak menulis file apa pun. */

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const P = (...s) => path.join(root, ...s);
const baca = (rel) => fs.readFileSync(P(...rel.split('/')), 'utf8');
const ada = (rel) => fs.existsSync(P(...rel.split('/')));

let gagal = 0;
const cek = (ok, label, bukti = '') => {
  console.log('  ' + (ok ? 'OK   ' : 'GAGAL') + '  ' + label + (bukti ? '  ' + bukti : ''));
  if (!ok) gagal++;
};

console.log('=== A. FILE BARU ===');
cek(ada('app/Support/CekRentangHarga.php'), 'app/Support/CekRentangHarga.php ada');
if (ada('app/Support/CekRentangHarga.php')) {
  const s = baca('app/Support/CekRentangHarga.php');
  cek(s.includes('class CekRentangHarga'), 'kelas CekRentangHarga');
  cek(s.includes('public static function tumpangTindih'), 'method tumpangTindih()');
  cek(s.includes("->where('range_min', '<=', \$maks)"), 'syarat range_min <= max baru');
  cek(s.includes("orWhere('range_max', '>=', \$min)"), 'syarat range_max >= min baru');
  cek(s.includes('whereNull(\'range_max\')'), 'range_max NULL dihitung terbuka');
}

console.log('\n=== B. CONTROLLER ===');
// B1 Finishing
const fin = baca('app/Http/Controllers/FinishingHargaController.php');
cek(fin.includes('CekRentangHarga::tumpangTindih'), 'Finishing memakai CekRentangHarga');
cek(fin.includes("'finishing_harga', 'id_finishing'"), 'Finishing -> tabel finishing_harga');
// urutan: validasi -> cek overlap -> insert
{
  const v = fin.indexOf('$request->validate(');
  const o = fin.indexOf('CekRentangHarga::tumpangTindih');
  const i = fin.indexOf("DB::table('finishing_harga')->insertGetId(");
  cek(v > -1 && o > v && i > o, 'Finishing urutan validasi -> overlap -> insert',
    `(validate=${v}, overlap=${o}, insert=${i})`);
  cek(fin.includes("'success' => false") && fin.includes('], 422)'), 'Finishing balas success=false + 422');
}

// B2 Jasa
const jas = baca('app/Http/Controllers/JasaHargaController.php');
cek(jas.includes('CekRentangHarga::tumpangTindih'), 'Jasa memakai CekRentangHarga');
cek(jas.includes("'jasa_harga', 'id_jasa'"), 'Jasa -> tabel jasa_harga');
{
  const v = jas.indexOf('$request->validate(');
  const o = jas.indexOf('CekRentangHarga::tumpangTindih');
  const i = jas.indexOf("DB::table('jasa_harga')->insertGetId(");
  cek(v > -1 && o > v && i > o, 'Jasa urutan validasi -> overlap -> insert',
    `(validate=${v}, overlap=${o}, insert=${i})`);
  cek(jas.includes("'success' => false") && jas.includes('], 422)'), 'Jasa balas success=false + 422');
}

// B3 Setting Harga - harus delegasi, signature publik tidak berubah
const set = baca('app/Http/Controllers/SettingHargaController.php');
cek(set.includes('private function tumpangTindih($idProduk, $idGolongan, $min, $max, $excludeId = null): bool'),
  'SettingHarga: signature tumpangTindih tidak berubah');
cek(set.includes('CekRentangHarga::tumpangTindih'), 'SettingHarga mendelegasikan ke helper bersama');
cek(set.includes("'id_harga'"), 'SettingHarga meneruskan kolom PK id_harga');
cek(set.includes("['id_golongan' => \$idGolongan]"), 'SettingHarga meneruskan id_golongan');

console.log('\n=== C. BLADE: penaga klik ganda lokal ===');
for (const [rel, label] of [
  ['resources/views/finishing/index.blade.php', 'finishing'],
  ['resources/views/master/jasa/index.blade.php', 'master/jasa'],
  ['resources/views/master/setting_harga/index.blade.php', 'master/setting_harga'],
]) {
  const s = baca(rel);
  const nonaktif = /tombol\.disabled = true;/.test(s);
  const hidup = /tombol\.disabled = false;/.test(s);
  cek(nonaktif && hidup, label + ' punya disabled true lalu false',
    `(true=${nonaktif}, false=${hidup})`);
}

console.log('\n=== D. YANG TIDAK BOLEH BERUBAH ===');
// D1 tidak ada submit_id dipaksakan ke payload fetch
for (const [rel, helper] of [
  ['resources/views/finishing/index.blade.php', 'submitTierFormAjax'],
  ['resources/views/master/jasa/index.blade.php', 'submitJasaTierFormAjax'],
  ['resources/views/master/setting_harga/index.blade.php', 'submitHargaTierFormAjax'],
]) {
  const s = baca(rel);
  // badan helper
  const m = s.match(new RegExp('(?:async\\s+)?function\\s+' + helper + '\\s*\\([^)]*\\)\\s*\\{'));
  let badan = null;
  if (m) {
    const dari = s.indexOf('{', s.indexOf(helper));
    let depth = 0;
    for (let i = dari; i < s.length; i++) {
      if (s[i] === '{') depth++;
      else if (s[i] === '}') { depth--; if (depth === 0) { badan = s.slice(dari, i + 1); break; } }
    }
  }
  cek(badan !== null, helper + ' ditemukan');
  if (badan) {
    cek(!/submit_id/.test(badan), helper + ' TIDAK mengirim submit_id (sesuaiInstruksi Â§3)');
    cek(/application\/json/.test(badan), helper + ' tetap body JSON (kontrak tidak berubah)');
    cek(!/form\.submit\(\)/.test(badan), helper + ' tidak bergantung form.submit() native');
  }
}

// D2 tidak ada perubahan skema / migration
const migrasi = fs.readdirSync(P('database', 'migrations')).filter(f => f.includes('cek_rentang') || f.includes('cekrentang'));
cek(migrasi.length === 0, 'tidak ada migration baru untuk C1', `(ditemukan: ${migrasi.length})`);

console.log('\n' + (gagal === 0
  ? 'PATCH C1 LENGKAP TERPASANG.'
  : gagal + ' pemeriksaan GAGAL.'));
process.exit(gagal ? 1 : 0);