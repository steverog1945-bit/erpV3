<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== jersey_sizes ===\n";
foreach (DB::table('jersey_sizes')->orderBy('id')->get() as $s) {
    $aktif = property_exists($s, 'is_active') ? $s->is_active : '?';
    echo '  id=' . str_pad((string) $s->id, 4) . ' name=' . str_pad((string) $s->name, 28) . ' aktif=' . $aktif . "\n";
}

echo "\n=== NAMA DUPLIKAT di jersey_sizes ===\n";
$dup = DB::table('jersey_sizes')
    ->selectRaw('name, COUNT(*) as n, GROUP_CONCAT(id ORDER BY id) as ids')
    ->groupBy('name')->havingRaw('COUNT(*) > 1')->get();
if ($dup->isEmpty()) {
    echo "  tidak ada duplikat\n";
} else {
    foreach ($dup as $x) {
        echo '  "' . $x->name . '" muncul ' . $x->n . 'x -> id: ' . $x->ids . "\n";
    }
}

echo "\n=== items order 28: ukuran TEKS yang dipakai pemain ===\n";
$teks = DB::table('jersey_order_items as i')
    ->join('jersey_order_lines as l', 'l.id', '=', 'i.jersey_order_line_id')
    ->where('l.jersey_order_id', 28)
    ->selectRaw('i.size as nama_teks, COUNT(*) as n')
    ->groupBy('i.size')->orderBy('i.size')->get();
foreach ($teks as $t) {
    echo '  ' . str_pad((string) $t->nama_teks, 28) . ' = ' . $t->n . "\n";
}

echo "\n=== size_quantities order 28: id ukuran yang dipakai ===\n";
$id = DB::table('jersey_order_size_quantities as sq')
    ->join('jersey_order_lines as l', 'l.id', '=', 'sq.jersey_order_line_id')
    ->where('l.jersey_order_id', 28)
    ->join('jersey_sizes as s', 's.id', '=', 'sq.jersey_size_id')
    ->selectRaw('sq.jersey_size_id, s.name, sq.quantity')
    ->orderBy('sq.jersey_size_id')->get();
foreach ($id as $t) {
    echo '  id=' . str_pad((string) $t->jersey_size_id, 4) . str_pad((string) $t->name, 28) . ' qty=' . $t->quantity . "\n";
}