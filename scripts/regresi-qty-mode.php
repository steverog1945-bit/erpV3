<?php
/* Regresi mode: pastikan sumberQtyBaris() tidak merusak mode lain.

   Skenario nyata yang harus tetap benar:
     quantity_only : qty per ukuran saja. Kalau form pernah mengirim sisa
                     baris pemain, sisa itu HARUS diabaikan.
     player_list   : daftar pemain saja. size_quantities sisa HARUS diabaikan
                     (ini bug 35 -> 70 yang diperbaiki).
     mixed         : dua-duanya memang dua sumber berbeda.
     player_list tapi TIDAK ada pemain : qty 0, baris dilewati (bukan error). */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Jersey\JerseyOrderController;

$ref = new ReflectionClass(JerseyOrderController::class);
$controller = $ref->newInstanceWithoutConstructor();
$m = $ref->getMethod('sumberQtyBaris');
$m->setAccessible(true);

$pemain = fn ($n, $size) => array_map(
    fn ($i) => ['player_name' => 'Pemain ' . $i, 'size' => $size],
    range(1, $n)
);

$kasus = [
    [
        'nama' => 'player_list, 35 pemain + size_quantities sisa 35',
        'payload' => [
            'order_type' => 'player_list',
            'items' => $pemain(35, 'M'),
            'size_quantities' => [1 => 6, 2 => 6, 3 => 2, 4 => 5, 15 => 1, 17 => 1, 18 => 8, 19 => 6],
        ],
        'harusQty' => 35,
        'harusItem' => 35,
        'harusSizeQty' => 0,
    ],
    [
        'nama' => 'player_list, 12 pemain saja',
        'payload' => ['order_type' => 'player_list', 'items' => $pemain(12, 'L'), 'size_quantities' => []],
        'harusQty' => 12, 'harusItem' => 12, 'harusSizeQty' => 0,
    ],
    [
        'nama' => 'player_list, TIDAK ada pemain (hanya sisa size_quantities)',
        'payload' => ['order_type' => 'player_list', 'items' => [], 'size_quantities' => [1 => 6, 2 => 6]],
        'harusQty' => 0, 'harusItem' => 0, 'harusSizeQty' => 0,
    ],
    [
        'nama' => 'quantity_only, qty per ukuran saja',
        'payload' => ['order_type' => 'quantity_only', 'items' => [], 'size_quantities' => [1 => 2, 2 => 8, 3 => 10]],
        'harusQty' => 20, 'harusItem' => 0, 'harusSizeQty' => 3,
    ],
    [
        'nama' => 'quantity_only, sisa baris pemain harus DIABAIKAN',
        'payload' => ['order_type' => 'quantity_only', 'items' => $pemain(9, 'M'), 'size_quantities' => [1 => 2, 2 => 8]],
        'harusQty' => 10, 'harusItem' => 0, 'harusSizeQty' => 2,
    ],
    [
        'nama' => 'mixed, dua sumber memang dijumlahkan',
        'payload' => ['order_type' => 'mixed', 'items' => $pemain(7, 'XL'), 'size_quantities' => [1 => 4, 2 => 6]],
        'harusQty' => 17, 'harusItem' => 7, 'harusSizeQty' => 2,
    ],
    [
        'nama' => 'order_type tidak ada -> default quantity_only',
        'payload' => ['items' => $pemain(3, 'S'), 'size_quantities' => [1 => 5]],
        'harusQty' => 5, 'harusItem' => 0, 'harusSizeQty' => 1,
    ],
    [
        'nama' => 'baris kosong -> qty 0 (dilewati, bukan error)',
        'payload' => ['order_type' => 'quantity_only', 'items' => [], 'size_quantities' => [1 => 0, 2 => 0]],
        'harusQty' => 0, 'harusItem' => 0, 'harusSizeQty' => 0,
    ],
    [
        'nama' => 'nama pemain kosong diabaikan, sisanya dihitung',
        'payload' => [
            'order_type' => 'player_list',
            'items' => [
                ['player_name' => 'Andi', 'size' => 'M'],
                ['player_name' => '   ', 'size' => 'L'],
                ['player_name' => 'Budi', 'size' => 'S'],
            ],
            'size_quantities' => [],
        ],
        'harusQty' => 2, 'harusItem' => 2, 'harusSizeQty' => 0,
    ],
];

echo "=== Regresi sumberQtyBaris() per mode ===\n\n";
$gagal = 0;
foreach ($kasus as $k) {
    $s = $m->invoke($controller, $k['payload']);
    $ok = ($s['qty'] === $k['harusQty'])
        && (count($s['items']) === $k['harusItem'])
        && (count($s['sizeQuantities']) === $k['harusSizeQty']);
    if (!$ok) {
        $gagal++;
    }
    printf("  %-6s %-52s qty=%-5d item=%-4d sizeQty=%-4d\n",
        $ok ? 'LULUS' : 'GAGAL',
        $k['nama'],
        $s['qty'], count($s['items']), count($s['sizeQuantities'])
    );
    if (!$ok) {
        printf("         diharapkan: qty=%d item=%d sizeQty=%d\n",
            $k['harusQty'], $k['harusItem'], $k['harusSizeQty']);
    }
}

echo "\n" . ($gagal === 0
    ? "Semua " . count($kasus) . " kasus LULUS.\n"
    : $gagal . " kasus GAGAL.\n");

exit($gagal ? 1 : 0);