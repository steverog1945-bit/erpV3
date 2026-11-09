/* CEK KESEIMBANGAN DIRECTIVE BLADE DI SELURUH VIEW

  Temuan di master/karyawan/index.blade.php: saat modal lama diganti
  dengan <x-modal>, tag pembuka modal lama dihapus tapi ekor markupnya
  tertinggal, termasuk satu @endforeach berlebih. Akibatnya halaman
  500 dengan pesan "unexpected token endforeach" - dan karena halaman
  ini adalah salah satu acuan tampilan, itu berarti ada kemungkinan
  pola serupa di file lain.

  Dipakai untuk menyisir semua file, bukan hanya yang dilaporkan.
*/

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const dir = path.join(root, 'resources', 'views');

function walk(d, out = []) {
  for (const e of fs.readdirSync(d, { withFileTypes: true })) {
    const p = path.join(d, e.name);
    if (e.isDirectory()) walk(p, out);
    else if (e.name.endsWith('.blade.php')) out.push(p);
  }
  return out;
}

/* Pasangan yang harus seimbang. Urutan tidak penting untuk
   pemeriksaan ini - yang penting jumlah buka vs jumlah tutup per
   jenis, karena Blade tidak melacakIMUM berlaku lintas tipe. */
const PASANGAN = [
  ['if', /@if\s*[\s\S]*?[^@]/, /@if\s*\(|\@if\s*\(|\B@if\(/g, /@endif/g],
  ['foreach', null, /@foreach\s*\(/g, /@endforeach/g],
  ['forelse', null, /@forelse/g, /@endforelse/g],
  ['php', null, /@php\b/g, /@endphp/g],
  ['section', null, /@section\s*\(/g, /@endsection|@stop/g],
  ['isset', null, /@isset\s*\(/g, /@endisset/g],
  ['empty', null, /@empty\s*\(/g, /@endempty/g],
  ['unless', null, /@unless\s*\(/g, /@endunless/g],
  ['auth', null, /@auth\b/g, /@endauth/g],
  ['guest', null, /@guest\b/g, /@endguest/g],
  ['push', null, /@push\s*\(/g, /@endpush/g],
  ['once', null, /@once\b/g, /@endonce/g],
  ['verbatim', null, /@verbatim\b/g, /@endverbatim/g],
  ['error', null, /@error\s*\(/g, /@enderror/g],
];

const masalah = [];

for (const f of walk(dir)) {
  const c = fs.readFileSync(f, 'utf8');
  // Buang komentar blade supaya contoh di dalam {{-- --}} tidak dihitung
  const bersih = c.replace(/\{\{--[\s\S]*?--\}\}/g, '');

  const beda = [];
  for (const [nama, , buka, tutup] of PASANGAN) {
    const b = (bersih.match(buka) || []).length;
    const t = (bersih.match(tutup) || []).length;
    if (b !== t) beda.push(`${nama} ${b}/${t}`);
  }

  if (beda.length) {
    // Temukan nomor baris untuk beritahu di mana letaknya
    const baris = bersih.split('\n');
    let kedalaman = 0;
    const titik = [];
    baris.forEach((l, i) => {
      const b = (l.match(/@foreach\s*\(/g) || []).length + (l.match(/@forelse/g) || []).length;
      const t = (l.match(/@endforeach/g) || []).length + (l.match(/@endforelse/g) || []).length;
      kedalaman += b - t;
      if (kedalaman < 0) { titik.push(i + 1); kedalaman = 0; }
    });

    masalah.push({
      f: path.relative(dir, f).replace(/\\/g, '/'),
      beda,
      profondeurAkhir: kedalaman,
      titik,
    });
  }
}

console.log('=== FILE DENGAN DIRECTIVE TIDAK SEIMBANG ===\n');
if (!masalah.length) {
  console.log('  tidak ada - semua file blade seimbang');
} else {
  masalah.forEach((m) => {
    console.log('  ' + m.f);
    console.log('      ' + m.beda.join('   '));
    if (m.kedalamanAkhir !== 0) console.log('      sisa foreach terbuka: ' + m.kedalamanAkhir);
    if (m.titik.length) console.log('      @endforeach berlebih di baris: ' + m.titik.join(', '));
  });
  console.log('\n  total file bermasalah: ' + masalah.length);
}
