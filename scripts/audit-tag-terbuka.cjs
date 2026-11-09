/* Audit TAG HTML YANG TIDAK DITUTUP

  Tag yang tidak ditutup bukan sekadar salah ketik. Parser HTML tidak
  melempar error: dia memperpanjang tag itu sampai字符 '>' berikutnya,
  dan '</div>' di baris berikutnya ikut(termasuk. Akibatnya satu
  elemen tidak pernah tertutup, dan seluruh isi setelahnya bergeser
  satu level - memunculkan全新的 gejala di tempat yang jauh dari
  penyebabnya.

  Kasus nyata sudah terjadi di penjualan/create.blade.php: satu <img>
  yang tidak menutup menyebabkan keranjang terjadi anak kolom kiri
  dan harus di-scroll 1600px untuk dilihat.

  Cara mendeteksinya: untuk tiap baris yang MEMBUKA tag void-ish yang
  seharusnya ditutup sendiri (img, input, br, hr), periksa apakah
  baris itu berakhir dengan '>' atau '/>'. Opening tag umum seperti
  <div>/<span>/<p> boleh menyeberang baris, jadi tidak diperiksa di
  sini - yang diperiksa hanya tag yang WAJIB ditutup di baris yang
  sama.
*/

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');

// Tag yang harus menutup di baris yang sama: void element.
const VOID = ['img', 'input', 'br', 'hr', 'source', 'track', 'wbr', 'area', 'col', 'embed'];

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
let totalTag = 0;

for (const f of files) {
  const L = fs.readFileSync(f, 'utf8').split('\n');
  L.forEach((b, i) => {
    const t = b.trim();
    if (!t) return;
    // Lewati baris Blade murni dan baris yang jelas bukan markup
    if (/^\{\{--/.test(t)) return;

    for (const tag of VOID) {
      const re = new RegExp('<' + tag + '\\b', 'g');
      let m;
      while ((m = re.exec(b)) !== null) {
        totalTag += 1;

        // Cari '>' atau '/>' setelah posisi tag, DI BARIS INI
        const setelah = b.slice(m.index);
        if (setelah.search(/\/?>/) !== -1) continue;

        // Tidak ditutup di baris ini. Itu BELUM tentu rusak: atribut
        // sering melanjutkan ke baris berikutnya. Yang pasti rusak
        // kalau baris berikutnya langsung diawali tag penutup -
        // karena itu berarti penutup '>' tag ini hilang, dan
        // penutup berikutnya ikut(termasuk ke dalam tag.
        const barisBerikut = (L[i + 1] || '').trim();
        if (/^<\//.test(barisBerikut)) {
          temuan.push({
            f: path.relative(root, f).replace(/\\/g, '/'),
            baris: i + 1,
            tag,
            teks: t.slice(0, 88),
            barisBerikut: barisBerikut.slice(0, 60),
            alasan: 'tag <' + tag + '> tidak ditutup; baris berikutnya adalah ' +
              barisBerikut.slice(0, 22) + ' yang jadi ikut termasuk ke dalam tag',
          });
        }
      }
    }
  });
}

console.log('=== TAG HTML YANG TIDAK DITUTUP ===\n');
console.log('  berkas diperiksa : ' + files.length);
console.log('  tag void diperiksa: ' + totalTag);
console.log('  bermasalah        : ' + temuan.length + '\n');

if (!temuan.length) {
  console.log('  (bersih)\n');
} else {
  const perFile = new Map();
  for (const t of temuan) {
    if (!perFile.has(t.f)) perFile.set(t.f, []);
    perFile.get(t.f).push(t);
  }
  for (const [f, list] of perFile) {
    console.log('  ' + f);
    for (const t of list) {
      console.log('      baris ' + String(t.baris).padStart(5) + '  ' + t.alasan);
      console.log('        ' + t.teks);
    }
  }
}
