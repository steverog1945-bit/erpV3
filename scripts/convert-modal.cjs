/* KONVERSI SELUBUNG MODAL -> <x-modal>

   Prinsipnya: JANGAN menulis ulang isi form. Field di dalam form
   dipertahankan apa adanya - name, id, type, value, placeholder,
   required, onchange, @csrf, @method, dan seluruh fungsi JS tidak
   boleh tersentuh. Yang diubah hanya:

     1. Selubung modal   -> <x-modal>
     2. Header berwarna  -> dihapus (diganti kepala ikon + subjudul)
     3. Panel bulat besar -> dihapus (kepadatan radius-lg dari komponen)
     4. Kelas STYLING pada field & tombol -> dibuang

   Poin 4 dikerjakan sebagai penyaringan daftar kelas, bukan penulisan
   ulang tag. Jadi atribut fungsional dijamin tetap ada, dan tidak ada
   yang bisa hilang di tengah jalan. Daftar kelas yang dibuang semuanya
   gaya lama: radius besar, isian terpusat, bayangan dalam, huruf besar.
*/

const fs = require('fs');
const path = require('path');

/* Kelas yang hanya gaya visual, bukan perilaku. */
const BUANG = new Set([
  // radius besar
  'rounded-2xl', 'rounded-3xl', 'rounded-[2rem]', 'rounded-[3rem]', 'rounded-xl',
  // isian
  'bg-slate-50', 'border-none', 'shadow-inner',
  'py-4', 'px-6', 'px-5', 'py-3', 'py-2.5',
  // perataan
  'text-center', 'text-left',
  // huruf
  'uppercase', 'tracking-widest', 'tracking-[0.2em]', 'tracking-tighter',
  'font-black', 'font-bold',
  // warna tombol lama
  'text-slate-500', 'text-slate-400', 'bg-amber-500', 'bg-amber-600',
  'hover:bg-amber-500', 'hover:bg-amber-600', 'hover:bg-amber-700',
  'shadow-amber-100', 'shadow-indigo-100', 'shadow-indigo-50',
  'bg-amber-50', 'border-amber-100', 'hover:bg-amber-50',
  'hover:border-amber-100', 'hover:bg-indigo-700',
  'text-indigo-500', 'text-indigo-600',
  // fokus lama
  'focus:ring-4', 'focus:ring-indigo-500/10', 'focus:ring-amber-500/10',
  'focus:ring-2', 'focus:ring-indigo-500/20',
  'transition-all',
  // jarak
  'mb-3', 'mb-4', 'ml-1', 'ml-2', 'pt-4', 'pb-8', 'px-10', 'py-8',
  'py-12', 'space-y-8', 'space-y-6',
]);

function bersihkanKelas(attr) {
  const kelas = attr.split(/\s+/).filter(k => k && !BUANG.has(k));
  return kelas.length ? ' ' + kelas.join(' ') : '';
}

const KONVERSI = [
  { file: 'resources/views/master/akun/index.blade.php', mulai: /\{\{-- MODAL TAMBAH/, akhir: /^<script>/,
    daftar: [
      { lama: 'modalAkun', baru: 'akun-tambah', judul: 'Tambah Akun', sub: 'Kode akun untuk jurnal, dipakai di semua transaksi keuangan', ikon: 'fa-chart-pie' },
      { lama: 'modalEditAkun', baru: 'akun-edit', judul: 'Edit Akun', sub: 'Perubahan berlaku untuk jurnal berikutnya', ikon: 'fa-pen' },
    ] },
  { file: 'resources/views/master/merk/index.blade.php', mulai: /\{\{-- MODAL TAMBAH/, akhir: /^<script>/,
    daftar: [
      { lama: 'modalMerk', baru: 'merk-tambah', judul: 'Tambah Merek', sub: 'Merek yang dipakai pada registrasi bahan baku', ikon: 'fa-tag' },
      { lama: 'modalEditMerk', baru: 'merk-edit', judul: 'Edit Merek', sub: 'Perubahan berlaku untuk bahan yang memakai merek ini', ikon: 'fa-pen' },
    ] },
  { file: 'resources/views/master/bank/index.blade.php', mulai: /\{\{-- MODAL TAMBAH/, akhir: /^<script>/,
    daftar: [
      { lama: 'modalBank', baru: 'bank-tambah', judul: 'Tambah Bank', sub: 'Rekening bank untuk sinkronisasi pembayaran', ikon: 'fa-building-columns' },
      { lama: 'modalEditBank', baru: 'bank-edit', judul: 'Edit Bank', sub: 'Perubahan berlaku untuk transaksi berikutnya', ikon: 'fa-pen' },
    ] },
  { file: 'resources/views/master/user/index.blade.php', mulai: /\{\{-- MODAL TAMBAH/, akhir: /^<script>/,
    daftar: [
      { lama: 'modalUser', baru: 'user-tambah', judul: 'Tambah Pengguna', sub: 'Akun login beserta bagian dan akses menu', ikon: 'fa-user-gear' },
      { lama: 'modalEditUser', baru: 'user-edit', judul: 'Edit Pengguna', sub: 'Perubahan akses berlaku setelah pengguna logout', ikon: 'fa-pen' },
    ] },
  { file: 'resources/views/master/gudang/index.blade.php', mulai: /\{\{-- MODAL TAMBAH/, akhir: /^<script>/,
    daftar: [
      { lama: 'modalGudang', baru: 'gudang-tambah', judul: 'Tambah Gudang', sub: 'Lokasi penyimpanan bahan dan produk', ikon: 'fa-warehouse' },
      { lama: 'modalEditGudang', baru: 'gudang-edit', judul: 'Edit Gudang', sub: 'Perubahan berlaku untuk penerimaan bahan berikutnya', ikon: 'fa-pen' },
    ] },
  { file: 'resources/views/master/jasa/index.blade.php', mulai: /\{\{-- MODAL TAMBAH/, akhir: /^<script>/,
    daftar: [
      { lama: 'modalJasa', baru: 'jasa-tambah', judul: 'Tambah Jasa', sub: 'JasaWORKS tambahan pada nota penjualan', ikon: 'fa-screwdriver-wrench' },
      { lama: 'modalEditJasa', baru: 'jasa-edit', judul: 'Edit Jasa', sub: 'Perubahan berlaku untuk nota berikutnya', ikon: 'fa-pen' },
    ] },
  { file: 'resources/views/konsumen-deposit/index.blade.php', mulai: /\{\{-- MODAL/, akhir: /^<script>/,
    daftar: [
      { lama: 'modalDeposit', baru: 'deposit-tambah', judul: 'Tambah Deposit Konsumen', sub: 'Dana muka yang mengurangi tagihan nota', ikon: 'fa-money-bill-transfer' },
    ] },
];

let total = 0;
for (const t of KONVERSI) {
  const p = path.join(__dirname, '..', t.file);
  if (!fs.existsSync(p)) { console.log('LEWAT (tidak ada): ' + t.file); continue; }

  let c = fs.readFileSync(p, 'utf8');
  const asli = c;
  let berubah = 0;

  for (const d of t.daftar) {
    // 1. Buka blok modal lama dan ambil isinya apa adanya
    const re = new RegExp(
      '<div id="' + d.lama + '"[^>]*>([\\s\\S]*?)\\n</div>\\s*\\n\\s*\\n',
      ''
    );
    const m = c.match(re);
    if (!m) { console.log('   Lewati ' + d.lama + ' (blok tidak ketemu)'); continue; }

    let isi = m[1];

    // 2. Ambil <form> beserta isinya VERBATIM
    const formMatch = isi.match(/<form\b[\s\S]*?<\/form>/);
    if (!formMatch) { console.log('   Lewati ' + d.lama + ' (form tidak ketemu)'); continue; }
    let form = formMatch[0];

    // 3. Buang header berwarna
    form = form.replace(/<div class="[^"]*\bbg-(indigo|amber|blue)-\d{3}\b[^"]*">[\s\S]*?<\/div>\s*<\/div>\s*<\/div>/, '');

    // 4. Saring kelas styling pada field & tombol (atribut lain utuh)
    form = form.replace(/class="([^"]*)"/g, (mm, isiKelas) => 'class="' + bersihkanKelas(isiKelas).trim() + '"');

    // 5. Bungkus jadi <x-modal>
    const modal =
      '<x-modal id="' + d.baru + '" title="' + d.judul + '"\n' +
      '         subtitle="' + d.sub + '"\n' +
      '         icon="' + d.ikon + '" maxWidth="md">\n' +
      form + '\n</x-modal>\n';

    c = c.replace(re, modal);
    berubah++;
  }

  if (c !== asli) { fs.writeFileSync(p, c, 'utf8'); total += berubah; console.log('OK  ' + t.file + ' (' + berubah + ' modal)'); }
  else { console.log('TIDAK BERUBAH  ' + t.file); }
}
console.log('Total modal dikonversi: ' + total);
