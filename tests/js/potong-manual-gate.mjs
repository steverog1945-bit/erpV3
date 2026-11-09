/**
 * Gate Potong Manual - sisi peramban.
 *
 * Menjalankan JavaScript ASLI dari resources/views/penjualan/create.blade.php,
 * bukan salinan logikanya. Kalau kodenya diubah lalu diekstrak ulang di sini,
 * test ini ikut menguji perubahan itu - jadi test tidak bisa hijau sementara
 * aplikasinya masih salah.
 *
 * Yang diuji:
 *  1. Rumus grid sama persis dengan server (Rp5.000 / 9.090 / 13.635 / 22.725).
 *  2. Biaya di DAFTAR finishing ikut berubah saat qty dinaikkan. Ini akar bug
 *     yang dilaporkan: field isian sudah benar, tapi Total Finishing & Subtotal
 *     membaca finishingList[].biaya yang dibekukan.
 *  3. Dimensi potong entri dipakai sendiri-sendiri, bukan dimensi panel.
 *  4. Dimensi kosong TIDAK boleh wiping biaya jadi Rp0.
 *  5. Dimensi potong lebih besar dari media = 0 (kelegalan geometri).
 *
 * Dijalankan oleh tests/Feature/PotongManualFrontendGateTest.php.
 */
import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

// Berkas yang diuji boleh diarahkan lewat env var BLADE. Dipakai untuk
// negative control: menjalankan gate ini terhadap versi LAMA berkas untuk
// membuktikan gate benar-benar menangkap bug, bukan selalu hijau.
const BLADE = path.resolve(process.env.BLADE || 'resources/views/penjualan/create.blade.php');
const src = fs.readFileSync(BLADE, 'utf8');

/** Ekstrak satu fungsi utuh dari sumber (pencacah kurung kurawal). */
function ambilFungsi(nama) {
  const m = src.match(new RegExp(`(?:async\\s+)?function\\s+${nama}\\s*\\(`));
  if (!m) {
    throw new Error(`Fungsi ${nama} tidak ditemukan di ${BLADE}. Apakah nama fungsi berubah?`);
  }
  const mulai = src.indexOf('{', m.index + m[0].length - 1);
  let depth = 0;
  let i = mulai;
  for (; i < src.length; i++) {
    const c = src[i];
    if (c === '{') depth++;
    else if (c === '}') {
      depth--;
      if (depth === 0) return src.slice(m.index, i + 1);
    }
  }
  throw new Error(`Fungsi ${nama} tidak tertutup kurung kurawal.`);
}

// DOM semu - hanya yang benar-benar dibaca fungsi di bawah.
const elemen = {
  input_p: { value: '150' },
  input_l: { value: '40' },
  input_qty_ukuran: { value: '1' },
  input_qty_biasa: { value: '1' },
  input_lebar_potong: { value: '10' },
  input_tinggi_potong: { value: '10' },
  // Ukuran media per lembar (produk yang TIDAK punya Panjang/Lebar di form,
  // mis. kertas A3). currentProduk di bawah ber-metode_hitung 'quantity',
  // jadi media diambil dari dua field INI - sama dengan 150 x 40.
  input_lebar_media_potong: { value: '150' },
  input_tinggi_media_potong: { value: '40' },
  input_finishing_biaya: { value: '0' },
};

const OPSI = {
  value: '9',
  dataset: { metode: 'LINEAR_GRID', harga: '450', minimum: '5000' },
};

const OPTION_MAP = {
  9: OPSI,
};

globalThis.document = {
  getElementById: (id) => (id in elemen ? elemen[id] : null),
  querySelector: (sel) => {
    const m = sel.match(/option\[value="(\d+)"\]/);
    if (!m) return null;
    const o = OPTION_MAP[m[1]];
    return o ? { dataset: o.dataset } : null;
  },
};

globalThis.currentProduk = { metode_hitung: 'quantity', id_produk: 1 };
globalThis.formatRupiahDariAngka = (n) => String(Math.round(n));
globalThis.renderFinishingList = () => {};

const kode = [
  ambilFungsi('kalkulasiGridLinearGrid'),
  ambilFungsi('bacaTarifLinearGrid'),
  ambilFungsi('qtyCetakBerjalan'),
  ambilFungsi('produkPakaiUkuran'),
  ambilFungsi('ukuranMediaPotong'),
  ambilFungsi('hitungBiayaLinearGrid'),
  ambilFungsi('hitungUlangPotongManualDiDaftar'),
].join('\n\n');

// `finishingList` sengaja DIJADIKAN parameter factory, bukan variabel modul.
// new Function() berjalan di lingkup global, jadi variabel modul tidak akan
// terlihat oleh fungsi yang diekstrak - `typeof finishingList` selalu
// 'undefined' di dalamnya dan fungsi langsung keluar tanpa mengerjakan apa
// pun. Test pun hijau tanpa menguji apa pun.
const factory = new Function(
  'currentProduk',
  `
  ${kode}
  let finishingList = [];
  return {
    api: { kalkulasiGridLinearGrid, bacaTarifLinearGrid, hitungBiayaLinearGrid, hitungUlangPotongManualDiDaftar },
    isiDaftar: (isi) => { finishingList = isi; },
    ambilDaftar: () => finishingList,
  };
`
);

let api;
let isiDaftar;
let ambilDaftar;
try {
  const dibuat = factory(currentProduk);
  api = dibuat.api;
  isiDaftar = dibuat.isiDaftar;
  ambilDaftar = dibuat.ambilDaftar;
} catch (e) {
  console.error('GAGAL_MUAT: ' + e.message);
  process.exit(2);
}

const gagal = [];
function cek(label, syarat, detail = '') {
  if (syarat) {
    console.log('OK   ' + label);
  } else {
    console.log('GAGAL ' + label + (detail ? ' :: ' + detail : ''));
    gagal.push(label);
  }
}

function rupiah(n) {
  return 'Rp' + Math.round(n).toLocaleString('id-ID');
}

// --- 1. Rumus sesuai angka acuan pemilik -------------------------------
const ACUAN = [
  [1, 5000],
  [2, 9090],
  [3, 13635],
  [5, 22725],
];

for (const [qty, harapan] of ACUAN) {
  const h = api.kalkulasiGridLinearGrid(150, 40, 10, 10, qty, 450, 5000);
  cek(
    `rumus qty ${qty} = ${rupiah(harapan)}`,
    Math.abs(h.biaya - harapan) < 0.001,
    `dapat ${h.biaya} (meter ${h.meter})`
  );
}

// --- 2. DAFTAR finishing ikut berubah saat qty dinaikkan ---------------
// Ini inti bug yang dilaporkan.
const finishingList = [
  {
    id_finishing: 9,
    nama: 'Potong Manual',
    metode: 'LINEAR_GRID',
    status: 'Standar',
    qty_pakai: null,
    lebar_potong: 10,
    tinggi_potong: 10,
    biaya: 0,
    catatan: '',
  },
];
isiDaftar(finishingList);

for (const [qty, harapan] of ACUAN) {
  elemen.input_qty_biasa.value = String(qty);
  api.hitungUlangPotongManualDiDaftar();
  cek(
    `entri daftar ikut berubah saat qty ${qty} -> ${rupiah(harapan)}`,
    Math.abs(finishingList[0].biaya - harapan) < 0.001,
    `dapat ${rupiah(finishingList[0].biaya)}`
  );
}

// --- 3. Perubahan cepat 1 -> 3 -> 2 -> 5 (tanpa jeda) ----------------
const urutan = [
  [1, 5000],
  [3, 13635],
  [2, 9090],
  [5, 22725],
];
for (const [qty, harapan] of urutan) {
  elemen.input_qty_biasa.value = String(qty);
  api.hitungUlangPotongManualDiDaftar();
}
cek(
  'perubahan cepat 1->3->2->5 berakhir di angka qty terakhir',
  Math.abs(finishingList[0].biaya - 22725) < 0.001,
  `dapat ${rupiah(finishingList[0].biaya)}`
);

// --- 4. Dimensi milik entri sendiri, bukan dimensi panel --------------
finishingList[0].lebar_potong = 20;
finishingList[0].tinggi_potong = 20;
elemen.input_lebar_potong.value = '10'; // panel sengaja dibiarkan berbeda
elemen.input_tinggi_potong.value = '10';
elemen.input_qty_biasa.value = '1';
api.hitungUlangPotongManualDiDaftar();

// potong 20x20: kolom = floor(150/20) = 7, baris = floor(40/20) = 2
// vertikal 6 x 40 = 240, horizontal 1 x 150 = 150 -> 3,90 m -> 1.755 -> minimum 5.000
cek(
  'entri memakai dimensi potongnya sendiri',
  Math.abs(finishingList[0].biaya - 5000) < 0.001,
  `dapat ${rupiah(finishingList[0].biaya)}`
);

// --- 5. Dimensi kosong TIDAK boleh menghapus biaya ---------------------
finishingList[0].lebar_potong = null;
finishingList[0].tinggi_potong = null;
api.hitungUlangPotongManualDiDaftar();
cek(
  'dimensi entri kosong tidak mengosongkan biaya',
  Math.abs(finishingList[0].biaya - 5000) < 0.001,
  `dapat ${rupiah(finishingList[0].biaya)}`
);

// --- 6. Potong lebih besar dari media = 0 ------------------------------
const hGede = api.kalkulasiGridLinearGrid(150, 40, 200, 200, 3, 450, 5000);
cek(
  'potong lebih besar dari media bernilai 0, bukan minimum charge',
  hGede.biaya === 0 && hGede.terisi === true,
  `dapat ${hGede.biaya}`
);

// --- 7. Tarif diambil dari master, bukan angka tetap -------------------
// Dulu tarif peramban di-hardcode 450 (window.LINEAR_GRID_TARIF tidak pernah
// ada di mana pun), jadi mengubah tarif master tidak mengubah apa pun di layar
// - angka di layar menyimpang dari yang disimpan server.
OPSI.dataset.harga = '600';
const h600 = api.kalkulasiGridLinearGrid(150, 40, 10, 10, 2, 600, 5000);
cek('tarif master 600 dipakai, bukan angka tetap 450', Math.abs(h600.biaya - 12120) < 0.001, `dapat ${h600.biaya}`);
OPSI.dataset.harga = '450';

// --- 8. Produk per lembar (A3) memakai ukuran media sendiri -------------
// currentProduk di gate ini ber-metode_hitung 'quantity' - produk seperti
// ini TIDAK punya Panjang/Lebar di form Input Pesanan, jadi media diambil
// dari field "Lebar/Tinggi Media" di panel Potong Manual.
//
// Media 300 x 300, potong 10 x 10, qty 1:
//   kolom 30, baris 30 -> garis 29 + 29
//   meter = (29*300 + 29*300)/100 = 174 m -> 174 * 450 = 78.300
elemen.input_lebar_media_potong.value = '300';
elemen.input_tinggi_media_potong.value = '300';
elemen.input_qty_biasa.value = '1';
finishingList[0].lebar_potong = 10;
finishingList[0].tinggi_potong = 10;
api.hitungUlangPotongManualDiDaftar();
cek(
  'media per lembar dipakai, bukan Panjang/Lebar produk',
  Math.abs(finishingList[0].biaya - 78300) < 0.001,
  `dapat ${rupiah(finishingList[0].biaya)}`
);

// Panjang/Lebar produk (yang di form = 150 x 40) TIDAK boleh ikut dipakai
// untuk produk per lembar - kalau iya, hasilnya 5.000 (minimum charge).
elemen.input_lebar_media_potong.value = '0';
elemen.input_tinggi_media_potong.value = '0';
api.hitungUlangPotongManualDiDaftar();
cek(
  'media kosong tidak diam-diam jatuh ke Panjang/Lebar produk',
  Math.abs(finishingList[0].biaya - 78300) < 0.001,
  `dapat ${rupiah(finishingList[0].biaya)}`
);
elemen.input_lebar_media_potong.value = '150';
elemen.input_tinggi_media_potong.value = '40';

// --- 9. Spek Custom = harga manual, TIDAK boleh ditimpa rumus ----------
finishingList[0].status = 'Custom';
finishingList[0].biaya = 7777;
elemen.input_lebar_media_potong.value = '300';
elemen.input_tinggi_media_potong.value = '300';
api.hitungUlangPotongManualDiDaftar();
cek(
  'entri Custom tidak ditimpa hitungan rumus',
  Math.abs(finishingList[0].biaya - 7777) < 0.001,
  `dapat ${rupiah(finishingList[0].biaya)}`
);
finishingList[0].status = 'Standar';
elemen.input_lebar_media_potong.value = '150';
elemen.input_tinggi_media_potong.value = '40';

// --- 10. Minimum charge dari master, bukan angka mati -----------------
// Dulu minimum charge ditulis 5000 di dalam kode peramban. Sekarang
// diambil dari baris master finishing yang sama dengan yang dibaca server.
const optsAwal = api.bacaTarifLinearGrid(OPSI);
cek(
  'minimum charge diambil dari master (bukan angka tetap)',
  optsAwal.minimumCharge === 5000 && optsAwal.tarifPerMeter === 450,
  `dapat minimum ${optsAwal.minimumCharge} tarif ${optsAwal.tarifPerMeter}`
);

OPSI.dataset.minimum = '8000';
const denganMinimum8000 = api.kalkulasiGridLinearGrid(150, 40, 10, 10, 1, 450, 8000);
cek(
  'minimum charge 8.000 dari master dipakai',
  Math.abs(denganMinimum8000.biaya - 8000) < 0.001,
  `dapat ${rupiah(denganMinimum8000.biaya)}`
);

OPSI.dataset.minimum = '0';
const tanpaMinimum = api.kalkulasiGridLinearGrid(150, 40, 10, 10, 1, 450, 0);
cek(
  'minimum charge 0 = tanpa lantai biaya',
  Math.abs(tanpaMinimum.biaya - 4545) < 0.001,
  `dapat ${rupiah(tanpaMinimum.biaya)}`
);
OPSI.dataset.minimum = '5000';

console.log('');
if (gagal.length) {
  console.log('HASIL: FAIL (' + gagal.length + ')');
  process.exit(1);
}
console.log('HASIL: PASS');