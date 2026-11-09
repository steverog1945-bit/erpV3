/* AUDIT TOTAL MODAL
   Jalankan: node scripts/audit-modal.cjs

   Memeriksa lima hal yang bisa membuat modal gagal, secara statis:
     1. Akar modal tanpa penanda data-modal-root / data-modal-backdrop
        -> Escape dan klik-luar tidak pernah bekerja
     2. Visibilitas modal yang dimanipulasi langsung (classList pada
        elemen modal, atau document.body.style.overflow) sehingga
        melewati API global
     3. Pemicu openModal/closeModal/toggleModal yang menunjuk modal
        yang tidak ada
     4. Definisi fungsi modal lokal yang menimpa API global
     5. Akar modal yang berid di luar pola "modal-*"

   Audit ini statis - dia membaca markup, tidak menjalankan halaman.
   Verifikasi perilaku (Escape benar-benar menutup, scroll lock
   benar-benar lepas) harus dilakukan di browser.
*/

const fs = require('fs');
const path = require('path');

// __dirname = <proyek>/scripts, jadi views satu tingkat di atasnya.
const viewsDir = path.join(__dirname, '..', 'resources', 'views');
const files = [];
(function walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) walk(p);
    else if (e.name.endsWith('.blade.php')) files.push(p);
  }
})(viewsDir);

const rel = (f) => path.relative(viewsDir, f).replace(/\\/g, '/');
const lineOf = (src, idx) => src.slice(0, idx).split(/\r?\n/).length;

const semuaId = new Set();
const masalah = [];

const add = (grup, ket, file, line, id) => masalah.push({ grup, ket, file, line, id });

/* Sumber bersih per file: komentar Blade {{-- --}} dan blok @php
   sering menjelaskan masalah yang sudah lewat dengan menyebut kode
   lamanya. Kalau tidak dibuang, audit melaporkan temuannya sendiri
   sebagai masalah - persis yang terjadi saat komentar Category
   mengutip body.style.overflow yang sudah dihapus. */
const bersih = new Map();
for (const f of files) {
  bersih.set(
    f,
    fs.readFileSync(f, 'utf8')
      .replace(/\{\{--[\s\S]*?--\}\}/g, '')
      .replace(/@php[\s\S]*?@endphp/g, '')
  );
}

/* ---------- 1: akar modal dan penandanya ---------- */
for (const f of files) {
  const src = bersih.get(f);

  for (const m of src.matchAll(/<x-modal\s+id="([^"]+)"/g)) {
    semuaId.add('modal-' + m[1]);
  }

  // Akar modal yang ditulis manual. Atribut bisa berurutan apa saja,
  // jadi id dicari di dalam tag, bukan harus jadi atribut pertama.
  // Penentu akar modal adalah `fixed inset-0`: itu lapisan yang menutup
  // seluruh layar. `fixed top-10 left-1/2` dipakai banner notifikasi,
  // yang memang bukan modal dan tidak boleh diberi penanda modal.
  for (const m of src.matchAll(/<div\s+([^>]*\bid="([A-Za-z][A-Za-z0-9_-]*)"[^>]*)>/g)) {
    const attrs = m[1];
    const id = m[2];
    if (id.endsWith('-title')) continue;
    if (!/\bfixed\b/.test(attrs) || !/\binset-0\b/.test(attrs)) continue;

    // Root modal boleh berawalan "modal-" atau tidak. Yang kedua dulu
    // ada di halaman Mesin (tambahMesin, edit5, ...) dan tetap
    // berfungsi karena openModal() mencari dua pola, tapi bentuknya
    // tidak seragam di seluruh aplikasi.
    if (!/^modal/.test(id)) {
      add(5, 'Akar modal berid non-standar (' + id + ') -> tidak seragam dengan modal lain', rel(f), lineOf(src, m.index), id);
    }
    semuaId.add(id);

    if (!attrs.includes('data-modal-root')) {
      add(1, 'Akar modal tanpa data-modal-root -> Escape & klik-luar tidak bekerja', rel(f), lineOf(src, m.index), id);
    }

    if (!attrs.includes('data-modal-backdrop')) {
      // Penanda boleh ada di anak pertama, bukan di tag akar: pada
      // <x-modal> area gelap adalah elemen terpisah di dalam akar.
      const anak = src.slice(m.index + m[0].length, m.index + 400);
      if (!/data-modal-backdrop/.test(anak)) {
        add(1, 'Akar modal tanpa data-modal-backdrop -> klik area gelap tidak menutup', rel(f), lineOf(src, m.index), id);
      }
    }
  }
}

/* ---------- 3: pemicu yang menunjuk modal tidak ada ---------- */
for (const f of files) {
  const src = bersih.get(f);
  for (const m of src.matchAll(/\b(openModal|closeModal|toggleModal)\(\s*'([^']*)'\s*\)/g)) {
    const fn = m[1], id = m[2];
    if (id.includes('{{') || id.includes('$')) continue;
    const kandidat = id.startsWith('modal') ? [id, id.slice(6)] : ['modal-' + id, id];
    if (!kandidat.some((k) => semuaId.has(k))) {
      add(3, 'Pemicu ' + fn + "('" + id + "') menunjuk modal yang tidak ada", rel(f), lineOf(src, m.index), id);
    }
  }
}

/* ---------- 4: fungsi modal lokal yang menimpa API global ---------- */
for (const f of files) {
  const src = bersih.get(f);
  for (const m of src.matchAll(/function\s+(toggleModal|openModal|closeModal|closeModal\w*|openModal\w*)\s*\(/g)) {
    add(4, 'Fungsi lokal ' + m[1] + '() bisa menimpa API global', rel(f), lineOf(src, m.index), m[1]);
  }
}

/* ---------- 2: manipulasi visibilitas yang melewati API ---------- */
for (const f of files) {
  const src = bersih.get(f);

  src.split(/\r?\n/).forEach((baris, i) => {
    if (/body\.style\.overflow\s*=/.test(baris)) {
      add(2, 'body.style.overflow di-set inline -> scroll lock tidak bisa dilepas API global', rel(f), i + 1, '-');
    }
  });

  const deklarasi = new Set();
  for (const m of src.matchAll(/(?:const|let|var)\s+(\w*[mM]odal\w*)\s*=/g)) deklarasi.add(m[1]);

  for (const v of deklarasi) {
    const esc = v.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const re = new RegExp(esc + "\\.classList\\.(remove|add|toggle)\\('hidden'\\)", 'g');
    for (const m of src.matchAll(re)) {
      add(2, 'Visibilitas modal diubah langsung (' + v + ".classList." + m[1] + "('hidden')) -> melewati API global, tanpa animasi dan tanpa data-state", rel(f), lineOf(src, m.index), v);
    }
  }
}

/* ---------- laporkan ---------- */
const judul = {
  1: 'AKAR MODAL',
  2: 'VISIBILITAS MELALUI API',
  3: 'PEMICU RUSAK',
  4: 'FUNGSI MODAL BAYANGAN',
  5: 'ID MODAL NON-STANDAR'
};

console.log('Total id modal terdaftar : ' + semuaId.size);
console.log('Total temuan             : ' + masalah.length);
console.log('');

for (const k of [1, 2, 3, 4, 5]) {
  const list = masalah.filter((m) => m.grup === k);
  if (!list.length) { console.log('=== ' + judul[k] + ' (0) === bersih'); console.log(''); continue; }
  console.log('=== ' + judul[k] + ' (' + list.length + ') ===');
  for (const m of list) {
    console.log('  ' + m.file + ':' + m.line + '  [' + m.id + ']');
    console.log('      ' + m.ket);
  }
  console.log('');
}
