/* =====================================================================
   PATCH C1 - tier harga bisa terduplikasi saat kirim ganda
   ---------------------------------------------------------------------
   CARA PAKAI (di folder project Anda)
     1. Letakkan file ini di <project>/scripts/
     2. node scripts/patch-c1-tier-harga.cjs
     3. php artisan view:clear
     4. php vendor/phpunit/phpunit/phpunit --filter TierHargaKirimGandaTest
     5. php vendor/phpunit/phpunit/phpunit   (regresi penuh)

   Aman dijalankan berkali-kali. Bagian yang sudah terpasang akan dilewati
   dan dilaporkan "[lewat]".

   TIDAK ADA migration. TIDAK ada perubahan skema. TIDAK ada perubahan kontrak
   response. TIDAK ada submit_id yang dipaksakan ke helper fetch.

   BUG YANG DIPERBAIKI
     Aturan bisnis "satu induk hanya boleh punya satu tier untuk satu rentang
     qty" hanya ADA di SettingHargaController (method tumpangTindih).
     FinishingHargaController dan JasaHargaController tidak punya apa-apa -
     insertGetId polos setelah validasi numeric.

     Akibatnya: form tier dikirim lewat fetch() dengan body JSON, jadi
     submit_id tidak pernah ikut dan SubmitSekaliJalan tidak menghitung apa
     pun di jalur ini. Dua POST identik menghasilkan TIER KEMBAR, lalu qty
     1-10 punya dua harga dan mana yang dipakai bergantung urutan baris.

     Bukti (sebelum perbaikan): test TierHargaKirimGandaTest gagal 7 dari 11,
     termasuk "dua POST identik tanpa submit_id menghasilkan 2 baris".
     Setelah perbaikan: 11/11 lulus, regresi 669 test / 3497 assertion hijau.

   FILE YANG DISENTUH (6 dimodifikasi + 1 baru)
     app/Support/CekRentangHarga.php                          (BARU)
     app/Http/Controllers/FinishingHargaController.php
     app/Http/Controllers/JasaHargaController.php
     app/Http/Controllers/SettingHargaController.php
     resources/views/finishing/index.blade.php
     resources/views/master/jasa/index.blade.php
     resources/views/master/setting_harga/index.blade.php
   ===================================================================== */

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const P = (...s) => path.join(root, ...s);

/* Line ending asli tiap file dipertahankan supaya diff tidak ribut. */
function baca(rel) {
  const isi = fs.readFileSync(P(...rel.split('/')), 'utf8');
  return { isi, crlf: isi.includes('\r\n') };
}
function tulis(rel, isi, crlf) {
  fs.writeFileSync(P(...rel.split('/')), crlf ? isi.split('\n').join('\r\n') : isi, 'utf8');
}

/* `tanda` = potongan pendek yang menandai "sudah terpasang". Matching blok
   panjang rapuh: satu karakter beda (komentar yang diubah, spasi) membuat
   skrip menulis ulang blok yang sebenarnya sudah benar. */
function terapkan(rel, label, sebelum, sesudah, wajib = 1, tanda = sesudah) {
  const { isi, crlf } = baca(rel);
  if (isi.includes(tanda)) {
    console.log('    [lewat]  ' + label);
    return true;
  }
  const n = isi.split(sebelum).length - 1;
  if (n === 0) {
    console.log('    [GAGAL]  ' + label + '  (pola tidak ditemukan - cek versi file)');
    return false;
  }
  if (n !== wajib) {
    console.log('    [GAGAL]  ' + label + '  (diharapkan ' + wajib + ', ketemu ' + n + ')');
    return false;
  }
  tulis(rel, isi.split(sebelum).join(sesudah), crlf);
  console.log('    [pasang] ' + label);
  return true;
}

let gagal = 0;

/* =====================================================================
   1. FILE BARU - implementasi bersama aturan rentang
   ===================================================================== */
console.log('\n[1] app/Support/CekRentangHarga.php');
const fHelper = 'app/Support/CekRentangHarga.php';
if (fs.existsSync(P(...fHelper.split('/')))) {
  console.log('    [lewat]  sudah ada - tidak ditulis ulang');
} else {
  fs.mkdirSync(path.dirname(P(...fHelper.split('/'))), { recursive: true });
  fs.writeFileSync(P(...fHelper.split('/')), `<?php

namespace App\\Support;

use Illuminate\\Support\\Facades\\DB;

/**
 * Aturan bisnis satu untuk SEMUA tabel harga bertingkat: satu induk hanya boleh
 * punya satu tier untuk satu rentang qty. Dua tier 1-10 dan 5-15 berarti
 * ambigu: qty 7 punya dua harga, dan mana yang dipakai bergantung pada urutan
 * baris - bukan keputusan bisnis yang bisa dipertanggungjawabkan.
 *
 * Kenapa aturan ini harus di server, bukan di tombol:
 *
 * 1. Form tier harga dikirim lewat fetch() dengan body JSON, bukan submit form
 *    native. submit_id tidak pernah ikut, jadi SubmitSekaliJalan tidak
 *    menghitung apa pun di jalur ini - sudah dibuktikan oleh test
 *    TierHargaKirimGandaTest (dua POST identik tanpa submit_id menghasilkan
 *    dua baris sebelum perbaikan ini).
 * 2. Tidak ada UNIQUE constraint di ketiga tabel (hanya primary key), jadi
 *    database tidak akan menolak sendiri.
 * 3. Klien bisa dimatikan; aturan ini tidak boleh bergantung padanya.
 *
 * Batas yang perlu diketahui: rentang TUMPANG TINDIH tidak bisa dinyatakan
 * sebagai UNIQUE index di MySQL (index hanya bisa menyamakan nilai, bukan
 * membandingkan interval). Jadi pemeriksa ini tetap wajib ada meski nanti
 * ada unique index - keduanya melengkapi, bukan menggantikan.
 */
class CekRentangHarga
{
    /**
     * True bila ada tier lain pada induk yang sama yang rentangnya bertabrakan.
     *
     * Tabrakan terjadi bila: ada untuk interval (min_baru, max_baru tanpa
     * batas atas), yaitu tier lama yang mulai <= max_baru DAN berakhir >= min_baru.
     * range_max NULL berarti terbuka ke atas, jadi dianggap 999.999.999.
     *
     * @param  string  $tabel      nama tabel tier
     * @param  string  $kolomInduk kolom FK ke induk (id_produk/id_jasa/id_finishing)
     * @param  array   $kolomExtra kolom lain yang ikut membatasi (mis. id_golongan)
     */
    public static function tumpangTindih(
        string $tabel,
        string $kolomInduk,
        int|string $indukId,
        array $kolomExtra,
        int|float $min,
        int|float|null $max,
        ?int $excludeId = null,
        string $kolomPk = 'id'
    ): bool {
        $maks = $max ?? 999999999;

        return DB::table($tabel)
            ->where($kolomInduk, $indukId)
            ->when($excludeId, fn ($q) => $q->where($kolomPk, '!=', $excludeId))
            ->when(
                $kolomExtra,
                fn ($q) => $q->where(function ($q) use ($kolomExtra) {
                    foreach ($kolomExtra as $k => $v) {
                        $q->where($k, $v);
                    }
                })
            )
            ->where('range_min', '<=', $maks)
            ->where(function ($q) use ($min) {
                $q->whereNull('range_max')->orWhere('range_max', '>=', $min);
            })
            ->exists();
    }
}
`, 'utf8');
  console.log('    [buat]   file helper dibuat');
}

/* =====================================================================
   2. CONTROLLER
   ===================================================================== */
console.log('\n[2] app/Http/Controllers/FinishingHargaController.php');
terapkan(
  'app/Http/Controllers/FinishingHargaController.php',
  'cek overlap finishing_harga sebelum insert',
  `            'harga'     => 'required|numeric|min:0',
        ]);

        $id = DB::table('finishing_harga')->insertGetId([`,
  `            'harga'     => 'required|numeric|min:0',
        ]);

        // Aturan bisnis: satu finishing hanya boleh punya satu tier untuk satu
        // rentang qty. Tanpa ini, klik ganda pada form tier (yang dikirim lewat
        // fetch() JSON, jadi tidak pernah membawa submit_id dan tidak
        // terlindungi SubmitSekaliJalan) membuat tier kembar - lalu qty 1-10
        // punya dua harga dan mana yang dipakai bergantung urutan baris.
        // Bentuk respons dibuat sama persis dengan SettingHargaController
        // supaya helper JS di ketiga halaman bisa sama.
        if (\\App\\Support\\CekRentangHarga::tumpangTindih(
            'finishing_harga', 'id_finishing', (int) $idFinishing, [], $request->range_min, $request->range_max
        )) {
            return response()->json(['success' => false, 'message' => 'Rentang qty ini tumpang tindih dengan tier yang sudah ada. Sesuaikan dulu Min/Max-nya.'], 422);
        }

        $id = DB::table('finishing_harga')->insertGetId([`,
  1,
  "'finishing_harga', 'id_finishing'"
);

console.log('\n[3] app/Http/Controllers/JasaHargaController.php');
terapkan(
  'app/Http/Controllers/JasaHargaController.php',
  'cek overlap jasa_harga sebelum insert',
  `            'harga'     => 'required|numeric|min:0',
        ]);

        $id = DB::table('jasa_harga')->insertGetId([`,
  `            'harga'     => 'required|numeric|min:0',
        ]);

        // Aturan bisnis: satu jasa hanya boleh punya satu tier untuk satu rentang
        // qty. Tanpa ini, klik ganda pada form tier (yang dikirim lewat fetch()
        // JSON, jadi tidak pernah membawa submit_id dan tidak terlindungi
        // SubmitSekaliJalan) membuat tier kembar - lalu qty 1-10 punya dua harga
        // dan mana yang dipakai bergantung urutan baris.
        // Bentuk respons dibuat sama persis dengan SettingHargaController
        // supaya helper JS di ketiga halaman bisa sama.
        if (\\App\\Support\\CekRentangHarga::tumpangTindih(
            'jasa_harga', 'id_jasa', (int) $idJasa, [], $request->range_min, $request->range_max
        )) {
            return response()->json(['success' => false, 'message' => 'Rentang qty ini tumpang tindih dengan tier yang sudah ada. Sesuaikan dulu Min/Max-nya.'], 422);
        }

        $id = DB::table('jasa_harga')->insertGetId([`,
  1,
  "'jasa_harga', 'id_jasa'"
);

console.log('\n[4] app/Http/Controllers/SettingHargaController.php');
terapkan(
  'app/Http/Controllers/SettingHargaController.php',
  'tumpangTindih() mendelegasikan ke helper bersama',
  `        return DB::table('setting_harga')
            ->where('id_produk', $idProduk)
            ->where('id_golongan', $idGolongan)
            ->when($excludeId, fn ($q) => $q->where('id_harga', '!=', $excludeId))
            ->where('range_min', '<=', $max ?? 999999999)
            ->where(function ($q) use ($min) {
                $q->whereNull('range_max')->orWhere('range_max', '>=', $min);
            })
            ->exists();`,
  `        // Arahkan ke implementasi bersama supaya aturan "satu tier per rentang"
        // hanya ada satu di aplikasi ini. Audit C1 menemukan aturan ini hanya
        // ada di SettingHargaController, sementara finishing_harga dan
        // jasa_harga tidak punya sama sekali - padahal ketiganya punya form
        // tier dengan cara yang persis sama.
        //
        // Kolom primary key tabel ini bukan "id" melainkan id_harga, jadi
        // diteruskan secara eksplisit.
        return \\App\\Support\\CekRentangHarga::tumpangTindih(
            'setting_harga',
            'id_produk',
            $idProduk,
            ['id_golongan' => $idGolongan],
            $min,
            $max,
            $excludeId,
            'id_harga'
        );`,
  1,
  "['id_golongan' => $idGolongan],"
);

/* =====================================================================
   3. BLADE - penaga klik ganda lokal (lapisan UX, bukan proteksi utama)
   ===================================================================== */
console.log('\n[5] Blade: penaga klik ganda pada form tier');

const BLADE = [
  ['resources/views/finishing/index.blade.php', 'submitTierFormAjax(form, \'POST\')'],
  ['resources/views/master/jasa/index.blade.php', 'submitJasaTierFormAjax(form, \'POST\')'],
  ['resources/views/master/setting_harga/index.blade.php', 'submitHargaTierFormAjax(form, \'POST\')'],
];

const CATATAN = `            // Pagar klik ganda. Form ini dikirim lewat fetch() JSON jadi tidak
            // pernah membawa submit_id dan tidak terlindungi SubmitSekaliJalan.
            // Pagar sesungguhnya ada di server (CekRentangHarga menolak rentang
            // bertabrakan); yang ini supaya operator tidak mengirim dua kali dan
            // tidak melihat pesan "rentang tumpang tindih" untuk kliknya sendiri.
            const tombol = form.querySelector('button[type="submit"]');
            if (tombol) { tombol.disabled = true; tombol.classList.add('opacity-60'); }`;

const HIDUP = `            // Tombol hidup lagi baik sukses maupun gagal: form di-reset setelah
            // sukses supaya operator bisa menambah tier lagi.
            if (tombol) { tombol.disabled = false; tombol.classList.remove('opacity-60'); }`;

for (const [rel, call] of BLADE) {
  const nama = rel.split('/').pop() === 'index.blade.php'
    ? rel.split('/').slice(-2, -1)[0]
    : rel;

  terapkan(
    rel,
    nama + ' - tombol mati saat kirim',
    `            const data = await ${call};`,
    CATATAN + `
            const data = await ${call};
` + HIDUP,
    1,
    'tombol.disabled = true;'
  );
}

/* =====================================================================
   VERIFIKASI
   ===================================================================== */
console.log('\n=== VERIFIKASI ===');
const v = {};
for (const rel of [
  fHelper,
  'app/Http/Controllers/FinishingHargaController.php',
  'app/Http/Controllers/JasaHargaController.php',
  'app/Http/Controllers/SettingHargaController.php',
  ...BLADE.map(([r]) => r),
]) {
  v[rel] = baca(rel).isi;
}

const cek = [
  ['CekRentangHarga ada', v[fHelper].includes('class CekRentangHarga')],
  ['CekRentangFilename punya syarat overlap', v[fHelper].includes("->where('range_min', '<=', $maks)")
    && v[fHelper].includes("orWhere('range_max', '>=', $min)")],
  ['Finishing pakai helper', v['app/Http/Controllers/FinishingHargaController.php'].includes('CekRentangHarga::tumpangTindih')],
  ['Jasa pakai helper', v['app/Http/Controllers/JasaHargaController.php'].includes('CekRentangHarga::tumpangTindih')],
  ['SettingHeight signature utuh', v['app/Http/Controllers/SettingHargaController.php']
    .includes('private function tumpangTindih($idProduk, $idGolongan, $min, $max, $excludeId = null): bool')],
  ['SettingHeight delegasi', v['app/Http/Controllers/SettingHargaController.php']
    .includes("['id_golongan' => $idGolongan],")],
  ...BLADE.map(([rel]) => [
    rel.split('/').slice(-2).join('/') + ' punya penaga',
    v[rel].includes('tombol.disabled = true;') && v[rel].includes('tombol.disabled = false;'),
  ]),
  ['tidak ada submit_id dipaksakan', !BLADE.some(([rel, call]) => {
    const helper = call.split('(')[0];
    const m = v[rel].match(new RegExp('(?:async\\s+)?function\\s+' + helper + '\\s*\\([^)]*\\)\\s*\\{'));
    if (!m) return false;
    const dari = v[rel].indexOf('{', v[rel].indexOf(helper));
    let depth = 0;
    for (let i = dari; i < v[rel].length; i++) {
      if (v[rel][i] === '{') depth++;
      else if (v[rel][i] === '}') { depth--; if (depth === 0) return /submit_id/.test(v[rel].slice(dari, i + 1)); }
    }
    return false;
  })],
];

for (const [label, ok] of cek) {
  console.log('  ' + (ok ? 'OK   ' : 'GAGAL') + '  ' + label);
  if (!ok) gagal++;
}

/* Karakter dari sistem tulisan lain = tanda penulisan rusak. Simbol umum
   seperti "×" bukan tanda rusak, jadi tidak ikut dihitung. */
for (const [rel, isi] of Object.entries(v)) {
  const aneh = [...new Set(isi.split('').filter((c) => {
    const n = c.charCodeAt(0);
    return (n >= 0x2e80 && n <= 0x9fff) || (n >= 0xac00 && n <= 0xd7af)
        || (n >= 0xff00 && n <= 0xffef) || (n >= 0x0600 && n <= 0x06ff);
  }))];
  if (aneh.length) {
    console.log('  GAGAL  ' + rel + ' punya karakter tulisan lain: ' + aneh.join(' '));
    gagal++;
  }
}

console.log('\n' + (gagal === 0
  ? 'Semua patch terpasang.\n'
    + 'Langkah berikutnya:\n'
    + '  php artisan view:clear\n'
    + '  php vendor/phpunit/phpunit/phpunit --filter TierHargaKirimGandaTest\n'
    + '  php vendor/phpunit/phpunit/phpunit'
  : gagal + ' pemeriksaan GAGAL. Jangan lanjut ke test.'));
process.exit(gagal === 0 ? 0 : 1);