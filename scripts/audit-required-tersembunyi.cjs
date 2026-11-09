/* Audit: "required di dalam wrapper tersembunyi" - bug yang membuat klik
   Simpan diam-diam tidak melakukan apa-apa.

   Duascreen:
     A. Semua tempat JS menyembunyikan sebuah container.
     B. Untuk tiap container itu, apakah isinya punya field `required`.

   Ini yang membuat halaman Transaksi/Kasir/Jersey diam-diam gagal simpan:
   browser membatalkan submit karena <select required> tidak bisa difokus
   (display:none), tanpa pesan error sama sekali. */

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const viewDirs = [path.join(root, 'resources', 'views')];
const jsDirs = [path.join(root, 'resources', 'js'), path.join(root, 'public', 'js')];

function rekursif(dir, out = []) {
  if (!fs.existsSync(dir)) return out;
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) rekursif(p, out);
    else if (/\.(blade\.php|js)$/.test(e.name)) out.push(p);
  }
  return out;
}

const files = [...viewDirs.flatMap(d => rekursif(d)), ...jsDirs.flatMap(d => rekursif(d))];

/* --- A. Kumpulkanpernyataan yang menyembunyikan container --- */
const sembunyi = [];
const reToggle = /classList\.(?:toggle|add|remove)\(\s*['"]hidden['"]/g;
const reSelector = /(?:querySelector(?:All)?\(\s*|classList\.\w+\(\s*)['"]([^'"]+)['"]/g;

for (const f of files) {
  const isi = fs.readFileSync(f, 'utf8');
  if (!reToggle.test(isi)) continue;
  reToggle.lastIndex = 0;

  // Ambil selector yang Irakitan di baris yang sama dengan toggle('hidden')
  const baris = isi.split('\n');
  baris.forEach((b, i) => {
    reToggle.lastIndex = 0;
    if (!reToggle.test(b)) { reToggle.lastIndex = 0; return; }
    reToggle.lastIndex = 0;
    const sel = [...b.matchAll(reSelector)].map(m => m[1]);
    sembunyi.push({
      file: path.relative(root, f),
      baris: i + 1,
      kode: b.trim().slice(0, 130),
      sel: [...new Set(sel)],
    });
  });
}

console.log('=== A. Container yang disembunyikan oleh JS (' + sembunyi.length + ' tempat) ===');
const byFile = {};
for (const s of sembunyi) (byFile[s.file] = byFile[s.file] || []).push(s);
for (const [file, list] of Object.entries(byFile)) {
  console.log('\n  ' + file);
  for (const s of list) {
    console.log('    ' + String(s.baris).padStart(5) + '  sel: ' + (s.sel.join(' | ') || '(tidak ada selector di baris ini)'));
  }
}

/* --- B. Cek apakah container itu (atau induknya) punya field required --- */
console.log('\n');
console.log('=== B. Field `required` yang berada di dalam container tersembunyi ===');

/* Untuk tiap selector class, cari elemen blade yang punya class itu, lalu
   lihat apakah di dalam bloknya ada `required`.-Blok ditentukan oleh tag
   pembuka yang sama. Ini perkiraan; yang perlu dicek manual ditandai. */
function classAdaDanPunyaRequired(selectorClass, fileRel) {
  const f = path.join(root, fileRel);
  if (!fs.existsSync(f)) return null;
  const isi = fs.readFileSync(f, 'utf8');
  const re = new RegExp('class="[^"]*\\b' + selectorClass + '\\b[^"]*"', 'g');
  const ketemu = re.test(isi);
  re.lastIndex = 0;
  if (!ketemu) return null;

  // Cari semua baris yang punya `required` dan class yang sama ATAU
  // di dalam wrapper yang sama - pakai Approach konservatif: cari
  // `required` di file yang sama lalulihat jaraknya.
  const barisReq = [];
  isi.split('\n').forEach((b, i) => { if (b.includes('required')) barisReq.push(i + 1); });
  const barisCls = [];
  isi.split('\n').forEach((b, i) => { if (new RegExp('\\b' + selectorClass + '\\b').test(b)) barisCls.push(i + 1); });
  return { barisCls, barisReq };
}

let temuan = 0;
for (const [file, list] of Object.entries(byFile)) {
  for (const s of list) {
    const classNames = s.sel
      .flatMap(sel => (sel.match(/\.[A-Za-z0-9_-]+/g) || []))
      .map(c => c.slice(1));
    for (const cn of classNames) {
      const info = classAdaDanPunyaRequired(cn, file);
      if (!info || info.barisReq.length === 0) continue;
      // Dekat? (dalam 25 baris)
      const dekat = info.barisCls.some(l => info.barisReq.some(r => Math.abs(r - l) <= 25));
      if (dekat) {
        temuan++;
        console.log('  PERIKSA  ' + file + '  selector .' + cn + ' (baris ' + s.baris + ')');
        console.log('           container di baris ' + info.barisCls.join(',') +
                    ' | field required di baris ' + info.barisReq.join(','));
      }
    }
  }
}
if (!temuan) console.log('  tidak ada container tersembunyi yang memegang field required.');