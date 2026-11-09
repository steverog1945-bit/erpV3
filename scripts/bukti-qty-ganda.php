<?php
/* BUKTI: penggandaan qty di mode player_list.

   Order 28 punya satu baris (line 119):
     - order_type     = player_list
     - items (pemain) = 35 baris
     - size_quantities= total 35 (dari 8 ukuran)

   Form Kelola Barang merender KEDUA sumber itu pada saat yang sama
   (_line-card-fields.blade.php baris 15 + 149: nilai size_quantities
   selalu diisi dari $sizeQtyMap, tanpa peduli mode-nya). Wrapper-nya
   disembunyikan, tapi "tersembunyi" bukan berarti "tidak terkirim".

   Lalu JerseyOrderController::simpanBarisOrder() baris 445 menjumlahkan
   keduanya:
     $qty = count($items) + array_sum($sizeQuantities)
          = 35 + 35 = 70

   Script ini menjalankan rumus yang PERSIS sama dengan baris 443-463
   memakai data nyata dari database. */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$lineId = (int) ($argv[1] ?? 119);

$line = DB::table('jersey_order_lines')->where('id', $lineId)->first();
if (!$line) {
    echo "line $lineId tidak ada\n";
    exit(1);
}

echo "=== Line $lineId (order {$line->jersey_order_id}) ===\n";
echo '  order_type : ' . $line->order_type . "\n";
echo '  qty tersimpan sekarang : ' . $line->qty . "\n\n";

/* Sumber 1: items (daftar pemain) - apa yang dikirim form untuk baris ini */
$items = DB::table('jersey_order_items')
    ->where('jersey_order_line_id', $lineId)
    ->get(['player_name', 'size'])
    ->map(fn ($r) => ['player_name' => $r->player_name, 'size' => $r->size])
    ->filter(fn ($i) => trim((string) $i['player_name']) !== '')
    ->values()
    ->all();

/* Sumber 2: size_quantities - apa yang dirender form (nilai TIDAK pernah
   dikosongkan hanya karena wrapper-nya disembunyikan) */
$sizeQuantities = [];
foreach (DB::table('jersey_order_size_quantities')
    ->where('jersey_order_line_id', $lineId)->get() as $r) {
    $sizeQuantities[$r->jersey_size_id] = (int) $r->quantity;
}
$sizeQuantitiesMentah = array_filter($sizeQuantities, fn ($q) => (int) $q > 0);

/* Rumus dari baris 443-445, tidak diubah. */
$qty = count($items) + array_sum(array_map('intval', $sizeQuantitiesMentah));

echo "=== Yang akan dikirim form Kelola Barang ===\n";
echo '  items terkirim        : ' . count($items) . " pemain\n";
echo '  size_quantities kirim : ' . count($sizeQuantitiesMentah) . " ukuran, total " . array_sum(array_map('intval', $sizeQuantitiesMentah)) . "\n\n";

echo "=== Rumus baris 445 ===\n";
echo '  $qty = count($items) + array_sum($size_quantities)' . "\n";
echo '      = ' . count($items) . ' + ' . array_sum(array_map('intval', $sizeQuantitiesMentah)) . "\n";
echo '      = ' . $qty . "\n\n";

echo '  qty benar seharusnya : ' . $line->qty . "\n";
echo '  qty hasil rumus     : ' . $qty . "\n";
echo '  selisih             : ' . ($qty - $line->qty) . "\n\n";

echo ($qty === $line->qty * 2 && $line->qty > 0)
    ? '=> TERBUKTI:qganda 2x (' . $line->qty . ' -> ' . $qty . ")\n"
    : "=> tidak cocok 2x; perlu pemeriksaan lanjut\n";

/* Rekap per ukuran supaya terlihat bahwa size_quantities =Players */
echo "\n=== Rekap per ukuran ===\n";
$perUkuran = [];
foreach ($items as $i) {
    $perUkuran[$i['size']] = ($perUkuran[$i['size']] ?? 0) + 1;
}
$semuaId = array_unique(array_merge(array_keys($perUkuran), array_keys($sizeQuantitiesMentah)));
sort($semuaId);
$sizeNama = DB::table('jersey_sizes')->pluck('name', 'id');
printf("  %-14s %8s %8s %8s\n", 'ukuran', 'pemain', 'qty_ukuran', 'jumlah');
foreach ($semuaId as $sid) {
    $p = $perUkuran[$sid] ?? 0;
    $q = $sizeQuantitiesMentah[$sid] ?? 0;
    printf("  %-14s %8d %8d %8d\n", (string) ($sizeNama[$sid] ?? $sid), $p, $q, $p + $q);
}
$tp = array_sum($perUkuran);
$tq = array_sum(array_map('intval', $sizeQuantitiesMentah));
printf("  %-14s %8d %8d %8d\n", 'TOTAL', $tp, $tq, $tp + $tq);