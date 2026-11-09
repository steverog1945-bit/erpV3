/* AUDIT KONSISTENSI VISUAL BERBASIS CSS HASIL BUILD

  Audit sebelumnya membaca KELAS UTILITY di blade (font-bold, rounded-2xl).
  Itu hanya separuh: sebagian halamanlooked" OpenCode" padahal
  kebetulan, dan sebagian kelas hilang dari build karena tidak pernah
  dipakai di view manapun - jadi audit kelas melaporkan bersih padahal
  tampilannya tidak.

  Yang di sini mengukur yang benar-benar terjadi: apa yang dirender.
  Kalau aturan CSS tidak ada di build sama sekali, itu dilaporkan -
  karena itulah tanda halaman itu terlihat seperti punya gayanya sendiri.

  Rujukan nilai diambil dari theme.css repo sst/opencode (lihat
  scripts/audit-token-opencode.cjs).
*/

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');

// Nilai acuan OpenCode
const ACUAN = {
  radius: { '2px': true, '4px': true, '6px': true, '8px': true, '10px': true },
  warna: {
    '23, 23, 23': 'teks utama',
    '111, 111, 111': 'teks sekunder',
    '143, 143, 143': 'teks tersier',
    '244, 244, 244': 'latar',
    '229, 229, 229': 'garis',
    '3, 76, 255': 'aksen',
  },
};

// Warna yang TIDAK ada di palet OpenCode tapi sering dipakai di
// aplikasi ini. Indigo punya 1003 kemunculan.
const ASING = [
  { nama: 'indigo', re: /(99, 102, 241|79, 70, 229|238, 242, 255|224, 231, 255|224, 242, 254|199, 210, 254|165, 180, 252|129, 140, 248)/ },
  { nama: 'sky-cyan', re: /(56, 189, 248|125, 211, 252|224, 242, 254)/ },
];

function hexKeRgb(hex) {
  const h = hex.replace('#', '');
  return [
    parseInt(h.slice(0, 2), 16),
    parseInt(h.slice(2, 4), 16),
    parseInt(h.slice(4, 6), 16),
  ].join(', ');
}

const ACUAN_RGB = new Set(Object.keys(ACUAN.warna).map((k) => k));

const cssFiles = [];
function walk(d) {
  if (!fs.existsSync(d)) return;
  for (const e of fs.readdirSync(d, { withFileTypes: true })) {
    const p = path.join(d, e.name);
    if (e.isDirectory()) walk(p);
    else if (e.name.endsWith('.css')) cssFiles.push(p);
  }
}
walk(path.join(root, 'public', 'build', 'assets'));
walk(path.join(root, 'resources', 'css'));
walk(path.join(root, 'resources', 'views', 'partials'));
walk(path.join(root, 'resources', 'views', 'layouts'));

console.log('=== FILE CSS YANG DIPERIKSA ===\n');
for (const f of cssFiles) {
  const relatif = path.relative(root, f).replace(/\\/g, '/');
  const c = fs.readFileSync(f, 'utf8');

  const radius = [...c.matchAll(/border-radius:\s*([^;}]+)/g)].map((m) => m[1].trim());
  const radiusBesar = radius.filter((r) => {
    const px = parseFloat(r);
    return !Number.isNaN(px) && px > 10;
  });

  const fontWeight = [...c.matchAll(/font-weight:\s*(\d+|bold|bolder)/g)].map((m) => m[1]);
  const beratTinggi = fontWeight.filter((w) => /^[6-9]00$|bold|bolder/.test(w));

  const fontFamily = [...c.matchAll(/font-family:\s*([^;}]+)/g)].map((m) => m[1].trim());
  const bukanSystem = fontFamily.filter((f) => !/ui-sans-serif|system-ui|inherit|ui-monospace|Inter/.test(f));

  const warna = [...c.matchAll(/#[0-9a-fA-F]{6}\b/g)].map((m) => m[0].toLowerCase());
  const unik = [...new Set(warna)];
  const indigoUnik = unik.filter((w) => ASING[0].re.test(hexKeRgb(w)));
  const diPalet = unik.filter((w) => ACUAN_RGB.has(hexKeRgb(w)));

  const masalah = [];
  if (radiusBesar.length) masalah.push(radiusBesar.length + ' radius > 10px');
  if (beratTinggi.length) masalah.push(beratTinggi.length + ' font-weight >= 600');
  if (indigoUnik.length) masalah.push(indigoUnik.length + ' warna indigo');
  if (bukanSystem.length) masalah.push(bukanSystem.length + ' font-family non-system');

  console.log('  ' + relatif);
  console.log('      warna unik   : ' + unik.length + '  (dari palet OpenCode: ' + diPalet.length + ')');
  if (masalah.length) {
    console.log('      MASALAH      : ' + masalah.join(', '));
    if (indigoUnik.length) console.log('        indigo : ' + indigoUnik.slice(0, 6).join(' '));
    if (radiusBesar.length) console.log('        radius : ' + [...new Set(radiusBesar)].slice(0, 6).join(' | '));
    if (beratTinggi.length) console.log('        weight : ' + [...new Set(beratTinggi)].slice(0, 6).join(' '));
  } else {
    console.log('      (bersih)');
  }
  console.log('');
}
