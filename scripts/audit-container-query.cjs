/* Audit container query (@md:, @lg:, @xl:) yang dipakai DI LUAR @container.

  Tailwind v4 punya dua breakpoint:
    md:grid-cols-3   -> breakpoint LAYAR, selalu berlaku
    @md:grid-cols-3  -> CONTAINER query, hanya berlaku kalau ada leluhur
                        ber-atribut @container

  Yang kedua diam-diam tidak berlaku kalau @container-nya tidak ada.
  Tidak ada error, tidak ada peringatan di console - kartu просто turun
  ke satu kolom dan halaman terlihat renggang. Sudah terjadi di
  resources/views/jasa-jahit/index.blade.php (tiga kartu ringkasan).

  Cara mendeteksinya: untuk setiap berkas yang memakai @md:/@lg:/@xl:,
  cek apakah ada @container DI DALAM berkas yang sama DAN apakah
  elemen container itu muncul SEBELUM pemakaiannya di baris markup.

  Batasnya jelas dan saya nyatakan: ini hanya mendeteksi kelas yang
  tidak punya leluhur @container di berkas yang sama. Kasuscontainer
  yang datang dari layout bukan dicek - tapi memang exceedingly jarang
  dan tidak bisa dipastikan tanpa render.
*/

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');

function walk(d, out = []) {
  if (!fs.existsSync(d)) return out;
  for (const e of fs.readdirSync(d, { withFileTypes: true })) {
    if (e.name === 'node_modules' || e.name === 'vendor') continue;
    const p = path.join(d, e.name);
    if (e.isDirectory()) walk(p, out);
    else if (e.name.endsWith('.blade.php')) out.push(p);
  }
  return out;
}

const files = walk(path.join(root, 'resources', 'views'));

const temuan = [];
let totalPemakaian = 0;

const POLA = /@(md|lg|xl|sm):[a-z0-9:\/\[\]_.%-]+/g;

for (const f of files) {
  const L = fs.readFileSync(f, 'utf8').split('\n');

  // Baris mana saja yang punya @container
  const barisContainer = [];
  L.forEach((b, i) => {
    if (/@container\b/.test(b)) barisContainer.push(i + 1);
  });

  L.forEach((b, i) => {
    const m = b.match(POLA);
    if (!m) return;
    totalPemakaian += m.length;

    // @container AFTER the usage in the same file means the wrapper
    // does not exist above this usage in that file.
    const containerDiAtas = barisContainer.some(function (bc) { return bc < i + 1; });

    if (!containerDiAtas) {
      temuan.push({
        f: path.relative(root, f).replace(/\\/g, '/'),
        baris: i + 1,
        kelas: [...new Set(m)].join(' '),
        containerDiAtas: false,
        teks: b.trim().slice(0, 88),
      });
    }
  });
}

console.log('=== CONTAINER QUERY TANPA @container ===\n');
console.log('  berkas diperiksa     : ' + files.length);
console.log('  pemakaian @breakpoint: ' + totalPemakaian);
console.log('  bermasalah            : ' + temuan.length + '\n');

if (!temuan.length) {
  console.log('  (bersih)\n');
} else {
  const perFile = new Map();
  for (const t of temuan) {
    if (!perFile.has(t.f)) perFile.set(t.f, []);
    perFile.get(t.f).push(t);
  }
  for (const [f, list] of perFile) {
    console.log('  ' + f + '  (' + list.length + ')');
    for (const t of list.slice(0, 4)) {
      console.log('      baris ' + String(t.baris).padStart(5) + '  ' + t.kelas.slice(0, 52));
      console.log('             ' + t.teks);
    }
    if (list.length > 4) console.log('      ... ' + (list.length - 4) + ' lagi');
    console.log('');
  }
  console.log('  total file: ' + perFile.size);
}
