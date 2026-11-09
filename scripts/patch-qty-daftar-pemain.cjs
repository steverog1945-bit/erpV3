/* =====================================================================
   PATCH: qty order jersey jadi 2x lipat saat Kelola Barang (edit)
   ---------------------------------------------------------------------
   CARA PAKAI
     1. Letakkan file ini di <project>/scripts/
     2. Jalankan:  node scripts/patch-qty-daftar-pemain.cjs
     3. Aman dijalankan berkali-kali (idempoten).

   BUGINDIK
   Modal "Tambah Baris Susulan" / form "Kelola Barang" (edit-lines) membuka
   ulang order yang mode-nya "Daftar Pemain". Halaman itu me-render ulang
   input size_quantities dengan nilai lama dari database, karena
   _line-card-fields.blade.php baris 15 selalu mengisi $sizeQtyMap tanpa
   melihat mode:

     $sizeQtyMap = $line ? $line->sizeQuantities->pluck('quantity', ...) : ...

   Wrapper-nya disembunyikan di mode Daftar Pemain, TAPI "tersembunyi" bukan
   berarti "tidak terkirim". Jadi form mengirim 35 pemain DAN size_quantities
   yang isinya 35.

   Server menjumlahkan keduanya:

     $qty = count($items) + array_sum($size_quantities)
          = 35 + 35 = 70

   Client sebenarnya sudah mengosongkan qty per ukuran saat mode diganti ke
   Daftar Pemain (toggleLineType), tapi saat halaman dibuka ulang dalam mode
   Daftar Pemain tidak ada pergantian mode - tidak ada yang mengosongkan
   apa pun. Server tidak boleh bergantung pada sesuatu yang hanya terjadi
   saat ada klik.

   Bukti di database (order 28, line 119): 35 baris pemain + size_quantities
   total 35 -> rumus lama menghasilkan 70.

   PERBAIKAN
   Satu helper baru, sumberQtyBaris(), jadi sumber kebenaran tunggal untuk
   "sumber mana yang dihitung" sesuai mode:

     quantity_only : qty per ukuran saja
     player_list   : daftar pemain saja
     mixed         : dua-duanya (memang dua sumber berbeda)

   Dipakai di tiga tempat yang tadinya punya salinan rumus yang sama:
   cekKelengkapan(), validasiQtyTerisi(), hitungHargaBaris().

   FILE YANG DISENTUH (2)
     app/Http/Controllers/Jersey/JerseyOrderController.php
     resources/views/jersey/orders/_line-card-fields.blade.php
   ===================================================================== */

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');

function baca(f) {
  const isi = fs.readFileSync(f, 'utf8');
  return { isi, crlf: isi.includes('\r\n') };
}
function tulis(f, isi, crlf) {
  fs.writeFileSync(f, crlf ? isi.split('\n').join('\r\n') : isi, 'utf8');
}

/* Ganti satu kemunculan.
   `tanda` = potongan pendek yang menandai "sudah terpasang". Dipakai untuk
   deteksi idempotensi. Matching blok panjang rapuh: satu karakter beda
   (komentar yang diubah, spasi) membuat skrip thinks belum terpasang lalu
   menulis ulang blok yang sebenarnya sudah benar. */
function terapkan(f, label, sebelum, sesudah, wajib = 1, tanda = sesudah) {
  const { isi, crlf } = baca(f);
  if (isi.includes(tanda)) {
    console.log('    [lewat]  ' + label + '  (sudah ada)');
    return true;
  }
  const n = isi.split(sebelum).length - 1;
  if (n === 0) {
    console.log('    [GAGAL]  ' + label + '  (pola tidak ditemukan)');
    return false;
  }
  if (n !== wajib) {
    console.log('    [GAGAL]  ' + label + '  (diharapkan ' + wajib + ' kemunculan, ketemu ' + n + ')');
    return false;
  }
  tulis(f, isi.split(sebelum).join(sesudah), crlf);
  console.log('    [pasang] ' + label);
  return true;
}

let gagal = 0;

/* =====================================================================
   1. JerseyOrderController.php
   ===================================================================== */
console.log('\n[1] app/Http/Controllers/Jersey/JerseyOrderController.php');
const fCtrl = path.join(root, 'app', 'Http', 'Controllers', 'Jersey', 'JerseyOrderController.php');

/* 1a. Helper baru + validasiQtyTerisi ikut memakainya. */
terapkan(
  fCtrl,
  'helper sumberQtyBaris() + validasiQtyTerisi',
  `    /**
     * Audit kelengkapan: WAJIB minimal satu sumber qty terisi per baris
     * berisi (pemain berukuran atau qty per ukuran > 0). Dipakai ketiga
     * jalur simpan (store/updateLines/susulan) sebelum transaksi dibuka.
     */
    private function validasiQtyTerisi(array $lines): void
    {
        foreach ($lines as $i => $lineData) {
            $items = array_values(array_filter($lineData['items'] ?? [], fn ($it) => trim($it['player_name'] ?? '') !== ''));
            $sizeQuantitiesMentah = array_filter($lineData['size_quantities'] ?? [], fn ($q) => (int) $q > 0);
            $qty = count($items) + array_sum(array_map('intval', $sizeQuantitiesMentah));`,
  `    /**
     * Sumber qty satu baris barang, DITENTUKAN oleh mode-nya.
     *
     * Bug yang diperbaiki di sini: dulu qty dihitung
     * \`count($items) + array_sum($size_quantities)\` untuk SEMUA mode.
     *
     * Di mode "Daftar Pemain", form Kelola Barang (edit-lines.blade.php)
     * merender input size_quantities dengan nilai lama dari database - karena
     * _line-card-fields.blade.php selalu mengisi $sizeQtyMap tanpa melihat
     * mode. Wrapper-nya memang disembunyikan, tapi "tersembunyi" bukan berarti
     * "tidak terkirim", jadi 35 pemain + size_quantities 35 = 70.
     *
     * Client sebenarnya sudah mengosongkan qty per ukuran saat mode diganti
     * ke Daftar Pemain (toggleLineType), tapi saat halaman dibuka ulang dalam
     * mode Daftar Pemain tidak ada pergantian mode - tidak ada yang mengosongkan
     * apa pun. Server tidak boleh bergantung pada sesuatu yang hanya terjadi
     * saat ada klik.
     *
     * Aturan per mode:
     *   quantity_only : qty per ukuran saja
     *   player_list   : daftar pemain saja
     *   mixed         : dua-duanya, memang dua sumber yang berbeda
     *
     * Dipakai cekKelengkapan(), validasiQtyTerisi(), dan hitungHargaBaris()
     * supaya ketiganya tidak bisa berbeda kesimpulan lagi.
     */
    private function sumberQtyBaris(array $lineData): array
    {
        $mode = (string) ($lineData['order_type'] ?? 'quantity_only');

        $items = array_values(array_filter(
            $lineData['items'] ?? [],
            fn ($it) => trim((string) ($it['player_name'] ?? '')) !== ''
        ));
        $sizeQty = array_filter($lineData['size_quantities'] ?? [], fn ($q) => (int) $q > 0);

        $pakaiItems = in_array($mode, ['player_list', 'mixed'], true);
        $pakaiSizeQty = in_array($mode, ['quantity_only', 'mixed'], true);

        $itemsDipakai = $pakaiItems ? $items : [];
        $sizeQtyDipakai = $pakaiSizeQty ? $sizeQty : [];

        return [
            'mode' => $mode,
            'items' => $itemsDipakai,
            'sizeQuantities' => $sizeQtyDipakai,
            'qty' => count($itemsDipakai) + array_sum(array_map('intval', $sizeQtyDipakai)),
        ];
    }

    /**
     * Audit kelengkapan: WAJIB minimal satu sumber qty terisi per baris
     * berisi (pemain berukuran atau qty per ukuran > 0). Dipakai ketiga
     * jalur simpan (store/updateLines/susulan) sebelum transaksi dibuka.
     */
    private function validasiQtyTerisi(array $lines): void
    {
        foreach ($lines as $i => $lineData) {
            $sumber = $this->sumberQtyBaris($lineData);
            $items = $sumber['items'];
            $sizeQuantitiesMentah = $sumber['sizeQuantities'];
            $qty = $sumber['qty'];`,
  1,
  `private function sumberQtyBaris(array $lineData): array`
);

/* 1b. cekKelengkapan(). */
terapkan(
  fCtrl,
  'cekKelengkapan() pakai sumberQtyBaris',
  `            $items = array_values(array_filter($lineData['items'] ?? [], fn ($it) => trim($it['player_name'] ?? '') !== ''));
            $sizeQuantitiesMentah = array_filter($lineData['size_quantities'] ?? [], fn ($q) => (int) $q > 0);
            $qty = count($items) + array_sum(array_map('intval', $sizeQuantitiesMentah));
            if ($qty <= 0) {
                continue; // baris kosong - sama seperti store(), dilewati saja
            }`,
  `            $sumber = $this->sumberQtyBaris($lineData);
            $qty = $sumber['qty'];
            if ($qty <= 0) {
                continue; // baris kosong - sama seperti store(), dilewati saja
            }`,
  1,
  `            $sumber = $this->sumberQtyBaris($lineData);
            $qty = $sumber['qty'];`
);

/* 1c. hitungHargaBaris() - termasuk koreksi komentarnya. */
terapkan(
  fCtrl,
  'hitungHargaBaris() pakai sumberQtyBaris',
  `        // Kunci 'size' dipaksa ada di sini, bukan hanya dijaga di tempat
        // dipakai. Alasannya bukan kerapian: filter di bawah hanya
        // memeriksa player_name, jadi baris pemain yang namanya sudah
        // terisi tapi ukurannya masih kosong tetap lolos. Dua loop di
        // bawah membaca $item['size'] secara langsung, dan tanpa
        // penjaga di sini satu baris yang belum lengkap membuat SELURUH
        // estimasi harga 500 (Undefined array key "size") - bukan cuma
        // baris itu yang gagal, tapi seluruh preview harga untuk order
        // ini. Gejalanya mudah terjadi karena operator mengetik nama dulu
        // dan ukuran menyusul.
        $items = array_values(array_filter(array_map(function ($i) {
            $i['size'] = trim((string) ($i['size'] ?? ''));

            return $i;
        }, $lineData['items'] ?? []), fn ($i) => trim($i['player_name'] ?? '') !== ''));
        $sizeQuantitiesMentah = array_filter($lineData['size_quantities'] ?? [], fn ($q) => (int) $q > 0);

        $qty = count($items) + array_sum(array_map('intval', $sizeQuantitiesMentah));`,
  `        // Kunci 'size' dipaksa ada di sini, bukan hanya dijaga di tempat
        // dipakai. Alasannya bukan kerapian: sumberQtyBaris() hanya
        // memeriksa player_name, jadi baris pemain yang namanya sudah
        // terisi tapi ukurannya masih kosong tetap lolos. Dua loop di
        // bawah membaca $item['size'] secara langsung, dan tanpa
        // penjaga di sini satu baris yang belum lengkap membuat SELURUH
        // estimasi harga 500 (Undefined array key "size") - bukan cuma
        // baris itu yang gagal, tapi seluruh preview harga untuk order
        // ini. Gejalanya mudah terjadi karena operator mengetik nama dulu
        // dan ukuran menyusul.
        //
        // Catatan: sumberQtyBaris() juga ikut membuang sumber qty yang tidak
        // relevan untuk modenya, jadi $items/$sizeQuantitiesMentah di bawah
        // sudah sesuai mode - mode Daftar Pemain tidak lagi ikut menghitung
        // size_quantities sisa (bug qty 35 -> 70).
        $sumber = $this->sumberQtyBaris($lineData);
        $items = array_map(function ($i) {
            $i['size'] = trim((string) ($i['size'] ?? ''));

            return $i;
        }, $sumber['items']);
        $sizeQuantitiesMentah = $sumber['sizeQuantities'];

        $qty = $sumber['qty'];`,
  1,
  `// size_quantities sisa (bug qty 35 -> 70).`
);

/* =====================================================================
   2. _line-card-fields.blade.php - lebar kolom Daftar Pemain

   complaints yang ini:
     - kolom Ukuran terlalu sempit, nama ukuran seperti "Anak XL (12-13 th)"
       terpotong
     - kolom No. dan Gender terlalu lebar; Gender cuma berisi satu huruf
       (L atau P)

   Ruang yang dipinjam dari No. (64px -> 48px) dan Gender (80px -> 56px)
   dipakai untuk Ukuran (80px -> 160px).
   ===================================================================== */
console.log('\n[2] resources/views/jersey/orders/_line-card-fields.blade.php');
terapkan(
  path.join(root, 'resources', 'views', 'jersey', 'orders', '_line-card-fields.blade.php'),
  'lebar kolom: No. w-12, Ukuran w-40, Gender w-14',
  `<th class="p-1 text-left">Nama</th><th class="p-1 text-left w-16">No.</th><th class="p-1 text-left w-20">Ukuran</th>
                <th class="p-1 text-left w-20">Gender</th>`,
  `<th class="p-1 text-left">Nama</th><th class="p-1 text-left w-12">No.</th><th class="p-1 text-left w-40">Ukuran</th>
                <th class="p-1 text-left w-14">Gender</th>`,
  1,
  'w-40">Ukuran'
);

/* 2b. Input qty per ukuran DISABLE di mode Daftar Pemain.

   Wrapper `hidden` hanya menyembunyikan tampilan - input di dalamnya tetap
   terkirim ke server. Itu sebabnya qty pernah terhitung 2x. Disable =
   tidak terkirim sama sekali, jadi data basi tidak mungkin ikut terhitung.

   Nilai TETAP diisi dari database supaya pindah ke mode Campuran tidak
   menghapus isian, tapi tidak dikirim selama mode-nya Daftar Pemain. */
terapkan(
  path.join(root, 'resources', 'views', 'jersey', 'orders', '_line-card-fields.blade.php'),
  'input size_quantities disable di mode Daftar Pemain',
  `value="{{ $sizeQtyMap[$s->id] ?? 0 }}" onfocus="this.select()" class="input-qty-ukuran w-full bg-white border border-slate-100 rounded-lg p-2 text-xs font-medium text-center focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-300">`,
  `value="{{ $sizeQtyMap[$s->id] ?? 0 }}" onfocus="this.select()" {{ $otIsi === 'player_list' ? 'disabled' : '' }} class="input-qty-ukuran w-full bg-white border border-slate-100 rounded-lg p-2 text-xs font-medium text-center focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-300 disabled:opacity-50 disabled:cursor-not-allowed">`,
  1,
  `{{ $otIsi === 'player_list' ? 'disabled' : '' }}`
);

/* 2c. Komentar penanda, supaya tidak ada yang nanti bertanya kenapa inputnya
   disable tapi nilainya masih ada. */
terapkan(
  path.join(root, 'resources', 'views', 'jersey', 'orders', '_line-card-fields.blade.php'),
  'komentar: nilai tetap diisi, input didisable',
  `                    --}}
                    <label class="block text-[10px] font-medium text-slate-400 uppercase text-center mb-1 leading-tight">
                        {{ $namaUkuran }}`,
  `                    --}}
                    {{--
                        Nilai size_quantities SELALU diisi dari database,
                        termasuk saat mode-nya Daftar Pemain - sengaja, supaya
                        berpindah ke mode Campuran tidak menghapus data yang
                        sudah ada.

                        Tapi di mode Daftar Pemain inputnya DISABLE, bukan
                        sekadar disembunyikan. Wrapper \`hidden\` hanya
                        menyembunyikan tampilan; input di dalamnya tetap
                        terkirim ke server. Itulah sumber bug qty 35 -> 70:
                        35 pemain + sisa size_quantities 35 dijumlahkan.
                        Disable = tidak terkirim sama sekali, jadi tidak ada
                        data basi yang bisa ikut terhitung.
                    --}}
                    <label class="block text-[10px] font-medium text-slate-400 uppercase text-center mb-1 leading-tight">
                        {{ $namaUkuran }}`,
  1,
  'data basi yang bisa ikut terhitung'
);

/* 2d. Toggle `disabled` saat mode diganti, di tiga halaman yang memakai
   line-card: create, edit-lines (Kelola Barang), show (modal susulan). */
console.log('\n[2b] toggle disabled qty per ukuran saat mode diganti');
const blokLama = `card.querySelector('.wrapper-qty-ukuran')?.classList.toggle('hidden', tipe === 'player_list');`;
const blokBaru = `// Mode Daftar Pemain: qty per ukuran TIDAK dipakai sama sekali, jadi
        // inputnya dinonaktifkan - bukan cuma disembunyikan. Wrapper \`hidden\`
        // hanya menyembunyikan tampilan; input di dalamnya tetap terkirim ke
        // server, dan itulah sumber bug qty 35 -> 70 (35 pemain + sisa
        // size_quantities 35 dijumlahkan). Disable = tidak terkirim sama sekali.
        // Mode Campuran tetap memakai keduanya, jadi tidak dinonaktifkan.
        const wrapperQty = card.querySelector('.wrapper-qty-ukuran');
        if (wrapperQty) {
            const bukanModePemain = tipe === 'player_list';
            wrapperQty.classList.toggle('hidden', bukanModePemain);
            wrapperQty.querySelectorAll('input, select').forEach(el => {
                el.disabled = bukanModePemain;
            });
        }`;

for (const nama of ['create', 'edit-lines', 'show']) {
  terapkan(
    path.join(root, 'resources', 'views', 'jersey', 'orders', nama + '.blade.php'),
    'toggle disabled di ' + nama,
    blokLama,
    blokBaru,
    1,
    'el.disabled = bukanModePemain;'
  );
}

/* =====================================================================
   VERIFIKASI
   ===================================================================== */
console.log('\n=== VERIFIKASI ===');
const vCtrl = baca(fCtrl).isi;
const vCard = baca(path.join(root, 'resources', 'views', 'jersey', 'orders', '_line-card-fields.blade.php')).isi;
const vCreate = baca(path.join(root, 'resources', 'views', 'jersey', 'orders', 'create.blade.php')).isi;
const vEdit = baca(path.join(root, 'resources', 'views', 'jersey', 'orders', 'edit-lines.blade.php')).isi;
const vShow = baca(path.join(root, 'resources', 'views', 'jersey', 'orders', 'show.blade.php')).isi;

const cek = [
  ['helper sumberQtyBaris ada', vCtrl.includes('private function sumberQtyBaris(array $lineData): array')],
  ['pakai helper 3x', (vCtrl.match(/\$this->sumberQtyBaris\(\$lineData\)/g) || []).length === 3],
  ['tidak ada rumus lama', !vCtrl.includes(`$qty = count($items) + array_sum(array_map('intval', $sizeQuantitiesMentah));`)],
  ['kolom No. w-12', vCard.includes('w-12">No.')],
  ['kolom Ukuran w-40', vCard.includes('w-40">Ukuran')],
  ['kolom Gender w-14', vCard.includes('w-14">Gender')],
  ['input qty disable di blade', vCard.includes(`{{ $otIsi === 'player_list' ? 'disabled' : '' }}`)],
  ['toggle disabled di create', vCreate.includes('el.disabled = bukanModePemain;')],
  ['toggle disabled di edit-lines', vEdit.includes('el.disabled = bukanModePemain;')],
  ['toggle disabled di show', vShow.includes('el.disabled = bukanModePemain;')],
  ['tidak ada toggle hidden tanpa disabled', !/wrapper-qty-ukuran'\)\?\.classList\.toggle/.test(
    vCreate + vEdit + vShow
  )],
];
for (const [label, ok] of cek) {
  console.log('  ' + (ok ? 'OK   ' : 'GAGAL') + '  ' + label);
  if (!ok) gagal++;
}

function hurufAsing(isi) {
  return [...new Set(isi.split('').filter(c => {
    const n = c.charCodeAt(0);
    return (n >= 0x2e80 && n <= 0x9fff) || (n >= 0xac00 && n <= 0xd7af) ||
           (n >= 0xff00 && n <= 0xffef) || (n >= 0x0600 && n <= 0x06ff);
  }))];
}
for (const [nama, isi] of [['ctrl', vCtrl], ['card', vCard], ['create', vCreate], ['edit', vEdit], ['show', vShow]]) {
  const aneh = hurufAsing(isi);
  if (aneh.length) {
    console.log('  GAGAL  ' + nama + ' punya karakter tulisan lain: ' + aneh.join(' '));
    gagal++;
  }
}

console.log('\n' + (gagal === 0
  ? 'Semua patch terpasang. Jalankan: php artisan view:clear'
  : gagal + ' pemeriksaan gagal.'));
process.exit(gagal === 0 ? 0 : 1);