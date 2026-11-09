<?php
/* Regresi: qty tidak lagi 2x lipat di mode Daftar Pemain.

   Sebelum fix: $qty = count($items) + array_sum($size_quantities)
                = 35 + 35 = 70
   Sesudah fix: sumberQtyBaris() membuang sumber yang tidak relevan per mode,
                jadi $qty = 35.

   Test ini memanggil hitungHargaBaris() SEBENARNYA lewat reflection dengan
   data persis seperti yang akan dikirim form Kelola Barang untuk line 119
   (order 28): 35 pemain + size_quantities sisa dari 8 ukuran. */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Jersey\JerseyOrderController;

$lineId = (int) ($argv[1] ?? 119);
$line = DB::table('jersey_order_lines')->where('id', $lineId)->first();
if (!$line) {
    echo "line $lineId tidak ada\n";
    exit(1);
}

echo "=== Data yang dikirim form Kelola Barang (line $lineId) ===\n";
echo '  order_type : ' . $line->order_type . "\n";
echo '  qty di DB sebelum edit : ' . $line->qty . "\n\n";

/* Payload apa adanya: items dari DB, size_quantities dari DB.
   Inilah yang benar-benar terkirim - wrapper disembunyikan bukan berarti
   inputnya tidak ikut terkirim. */
$items = DB::table('jersey_order_items')->where('jersey_order_line_id', $lineId)
    ->get(['player_name', 'size', 'jersey_sleeve_type_id', 'jersey_collar_type_id', 'gender', 'player_number'])
    ->map(fn ($r) => (array) $r)->all();

$sizeQuantities = [];
foreach (DB::table('jersey_order_size_quantities')->where('jersey_order_line_id', $lineId)->get() as $r) {
    $sizeQuantities[$r->jersey_size_id] = (int) $r->quantity;
}

$payload = [
    'order_type' => $line->order_type,
    'nama_barang' => $line->nama_barang,
    'jersey_product_type_id' => $line->jersey_product_type_id,
    'jersey_category_id' => $line->jersey_category_id,
    'jersey_material_id' => $line->jersey_material_id,
    'jersey_sleeve_type_id' => $line->jersey_sleeve_type_id,
    'jersey_collar_type_id' => $line->jersey_collar_type_id,
    'items' => $items,
    'size_quantities' => $sizeQuantities,
];

echo '  items terkirim        : ' . count($items) . " pemain\n";
echo '  size_quantities kirim : total ' . array_sum($sizeQuantities) . ' dari ' . count($sizeQuantities) . " ukuran\n\n";

/* Panggil controller sungguhan. */
$ref = new ReflectionClass(JerseyOrderController::class);
$controller = $ref->newInstanceWithoutConstructor();

$mHitung = $ref->getMethod('hitungHargaBaris');
$mHitung->setAccessible(true);
$mSumber = $ref->getMethod('sumberQtyBaris');
$mSumber->setAccessible(true);

$sumber = $mSumber->invoke($controller, $payload);
$hasil = $mHitung->invoke($controller, $payload);

echo "=== sumberQtyBaris() ===\n";
echo '  mode dipakai    : ' . $sumber['mode'] . "\n";
echo '  items dihitung  : ' . count($sumber['items']) . "\n";
echo '  sizeQty dihitung: ' . count($sumber['sizeQuantities']) . " ukuran (total " . array_sum($sumber['sizeQuantities']) . ")\n";
echo '  QTY             : ' . $sumber['qty'] . "\n\n";

echo "=== hitungHargaBaris() ===\n";
$qty = $hasil['qty'] ?? null;
echo '  qty   : ' . $qty . "\n";
echo '  harga : ' . ($hasil['hargaSatuan'] ?? '-') . "\n";
echo '  subtotal : ' . ($hasil['subtotal'] ?? '-') . "\n\n";

/* Rekapan per ukuran - memastikan rekap ukuran juga tidak dobel. */
echo "=== Rekap per ukuran (hasil hitungHargaBaris) ===\n";
$per = $hasil['qtyPerSizeId'] ?? [];
ksort($per);
$total = 0;
foreach ($per as $sizeId => $n) {
    $nama = DB::table('jersey_sizes')->where('id', $sizeId)->value('name') ?? ('id' . $sizeId);
    printf("  %-24s %d\n", $nama, $n);
    $total += $n;
}
echo "  TOTAL rekap : $total\n\n";

/* --- PENILAIAN --- */
$benar = (int) $line->qty;
$gagal = [];

if ($qty !== $benar) {
    $gagal[] = "qty hasil = $qty, seharusnya $benar";
}
if ($sumber['qty'] !== $benar) {
    $gagal[] = "sumberQtyBaris = {$sumber['qty']}, seharusnya $benar";
}
if ($total !== $benar) {
    $gagal[] = "rekap per ukuran = $total, seharusnya $benar";
}
if ($qty === $benar * 2) {
    $gagal[] = 'qty masih 2x lipat';
}

echo $gagal
    ? "GAGAL:\n - " . implode("\n - ", $gagal) . "\n"
    : "LULUS: qty tetap $benar (bukan " . ($benar * 2) . "), rekap ukuran $total.\n";

exit($gagal ? 1 : 0);