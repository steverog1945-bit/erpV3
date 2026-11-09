<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

/* Tabel tier harga: apa pun yang namanya harga/tier di database. */
echo "=== Tabel yang mungkin menyimpan tier harga ===\n";
$tables = DB::select("SELECT TABLE_NAME FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE='BASE TABLE'
      AND (TABLE_NAME LIKE '%harga%' OR TABLE_NAME LIKE '%tier%')
    ORDER BY TABLE_NAME");
foreach ($tables as $t) {
    echo '  ' . $t->TABLE_NAME . "\n";
}

/* Untuk tiap kandidat, tampilkan INDEX-nya - di situlah unique constraint. */
foreach ($tables as $t) {
    $nama = $t->TABLE_NAME;
    echo "\n=== $nama ===\n";

    $idx = DB::select("SELECT INDEX_NAME, NON_UNIQUE,
            GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
        GROUP BY INDEX_NAME, NON_UNIQUE", [$nama]);

    $adaUnique = false;
    foreach ($idx as $i) {
        $tipe = ((int) $i->NON_UNIQUE) === 0 ? 'UNIQUE' : 'index ';
        if ($tipe === 'UNIQUE') {
            $adaUnique = true;
        }
        printf("  %-8s %-30s %s\n", $tipe, $i->INDEX_NAME, $i->cols);
    }
    if (!$adaUnique) {
        echo "  >>> TIDAK ADA UNIQUE CONSTRAINT di tabel ini\n";
    }
}

/* Cek foreign key: apakah ada ON DELETE / ON UPDATE yang relevan. */
echo "\n=== Isi tabel saat ini (cek duplikat yang sudah terlanjur ada) ===\n";
foreach (['finishing_harga', 'jasa_harga', 'harga'] as $tabel) {
    if (!DB::getSchemaBuilder()->hasTable($tabel)) {
        echo "  $tabel: tabel tidak ada\n";
        continue;
    }
    $total = DB::table($tabel)->count();
    echo "  $tabel: $total baris\n";

    // Deteksi duplikat berdasarkan kombinasi kolom yang masuk akal.
    $kolom = DB::getSchemaBuilder()->getColumnListing($tabel);
    $cari = ['id_finishing', 'id_jasa', 'id_produk', 'id_golongan', 'range_min', 'range_max', 'harga'];
    $ada = array_values(array_intersect($cari, $kolom));
    if ($ada) {
        $dup = DB::table($tabel)
            ->selectRaw(implode(', ', $ada) . ', COUNT(*) as n')
            ->groupBy(...$ada)->havingRaw('COUNT(*) > 1')->get();
        if ($dup->isEmpty()) {
            echo '    duplikat pada (' . implode(', ', $ada) . ') : tidak ada\n';
        } else {
            foreach ($dup as $d) {
                echo '    DUPLIKAT (' . implode(', ', $ada) . ') = ';
                $parts = [];
                foreach ($ada as $c) {
                    $parts[] = $c . '=' . var_export($d->$c, true);
                }
                echo implode(' ', $parts) . ' -> ' . $d->n . " baris\n";
            }
        }
    }
}