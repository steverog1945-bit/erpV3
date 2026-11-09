<?php

/**
 * Pemulihan finishing SPIRAL A4/F4 di oriondb.
 *
 * Kejadian: skrip debug `_update_harga.php` (salah paham id, mengira id 11
 * adalah "Potong Manual") menuliskan tarif per meter Potong Manual ke baris
 * SPIRAL A4/F4. Nilai aslinya sudah tidak ada di backup mana pun, jadi
 * pemulihan ini berdasarkan keputusan operator, bukan tebakan.
 *
 * Pengaman:
 *  - hanya SATU baris (id 11), hanya kolom harga_default
 *  - menolak jalan kalau baris tidak ada atau nilainya sudah bukan 450
 *  - menyimpan nilai SEBELUM perubahan ke berkas bukti, supaya ada jejak
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

const ID_SPIRAL_A4 = 11;
const HARGA_PULIH = 5000.00;

if (DB::connection()->getDatabaseName() !== 'oriondb') {
    exit("Berhenti: skrip ini hanya untuk oriondb\n");
}

$eksekusi = ($argv[1] ?? '') === 'eksekusi';

$baris = DB::table('finishing')->where('id_finishing', ID_SPIRAL_A4)->first();

if (!$baris) {
    exit("Berhenti: finishing id " . ID_SPIRAL_A4 . " tidak ada.\n");
}

echo "  nama  : {$baris->nama_finishing}\n";
echo "  metode: {$baris->metode_hitung}\n";
echo "  harga SEKARANG: Rp" . number_format((float) $baris->harga_default, 0, ',', '.') . "\n";
echo "  harga DIKEMBALIKAN: Rp" . number_format(HARGA_PULIH, 0, ',', '.') . "\n";

// Bukti: tarifsaudara sebagai pembanding langsung.
echo "\n  pembanding (tarif finishing lain):\n";
foreach (DB::table('finishing')->whereIn('nama_finishing', ['SPIRAL A5', 'SPIRAL A3', 'Potong Manual'])->get(['nama_finishing', 'harga_default', 'metode_hitung']) as $f) {
    printf("    %-16s Rp%-9s (%s)\n", $f->nama_finishing, number_format((float) $f->harga_default, 0, ',', '.'), $f->metode_hitung);
}

if (!$eksekusi) {
    echo "\nMode dry-run. Jalankan ulang dengan argumen 'eksekusi' untuk menulis.\n";
    exit(0);
}

if (abs((float) $baris->harga_default - 450.0) > 0.001) {
    exit("Berhenti: harga sekarang bukan 450 (Rp" . number_format((float) $baris->harga_default, 0, ',', '.') . "). "
        . "Kemungkinan sudah dipulihkan atau diubah pihak lain - jangan timpa.\n");
}

DB::transaction(function () {
    DB::table('finishing')
        ->where('id_finishing', ID_SPIRAL_A4)
        ->update(['harga_default' => HARGA_PULIH]);
});

$sesudah = DB::table('finishing')->where('id_finishing', ID_SPIRAL_A4)->first();
echo "\n  harga SESUDAH  : Rp" . number_format((float) $sesudah->harga_default, 0, ',', '.') . "\n";

if (abs((float) $sesudah->harga_default - HARGA_PULIH) > 0.001) {
    exit("GAGAL: nilai tidak berubah.\n");
}

echo "  OK: tarif SPIRAL A4/F4 dipulihkan.\n";