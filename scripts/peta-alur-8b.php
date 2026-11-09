<?php
/* Peta skema AKTUAL untuk alur Produksi -> Collecting -> Payment -> Journal.
   READ-ONLY. Tidak mengarang nama tabel/kolom. */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== 1. Tabel yang ada (cari pola Produksi/Collecting/Payment/Journal) ===\n";
$tabs = DB::select("SELECT TABLE_NAME FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME");
$nama = array_map(fn ($t) => $t->TABLE_NAME ?? $t->name, $tabs);

$pola = ['spk', 'produksi', 'collect', 'bayar', 'payment', 'jurnal', 'journal', 'kas', 'transaksi', 'piutang', 'hutang', 'invoice', 'rekening', 'akun', 'akuntansi'];
foreach ($pola as $p) {
    $cocok = array_values(array_filter($nama, fn ($x) => stripos($x, $p) !== false));
    if ($cocok) {
        echo '  [' . $p . "]\n";
        foreach ($cocok as $c) {
            echo '      ' . $c . "\n";
        }
    }
}

echo "\n=== 2. Kolom kunci per tabel inti ===\n";
$inti = ['transaksi', 'transaksi_detail', 'spk', 'produksi', 'collecting',
    'pembayaran', 'jurnal', 'jurnal_detail', 'kas', 'rekening', 'akun', 'piutang'];
foreach ($inti as $t) {
    if (!DB::getSchemaBuilder()->hasTable($t)) {
        echo "\n  --- $t : TIDAK ADA ---\n";
        continue;
    }
    echo "\n  --- $t ---\n";
    foreach (DB::select("SHOW COLUMNS FROM `$t`") as $c) {
        $tipe = $c->Type;
        $extra = trim(($c->Extra ?? '') . ' ' . (str_contains((string) $c->Type, 'enum') ? '' : ''));
        $wajib = ($c->Null === 'NO' && $c->Default === null && !str_contains((string) $c->Extra, 'auto_increment'));
        echo '    ' . str_pad($c->Field, 26) . str_pad($tipe, 20)
            . ($c->Null === 'YES' ? ' NULL' : ' NOTNULL')
            . ($c->Default === null ? '' : ' def=' . $c->Default)
            . ($wajib ? '  <== wajib' : '') . "\n";
    }
}

echo "\n=== 3. Foreign key antar tabel inti ===\n";
foreach ($inti as $t) {
    if (!DB::getSchemaBuilder()->hasTable($t)) {
        continue;
    }
    $fk = DB::select("SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL", [$t]);
    if ($fk) {
        echo '  ' . $t . ":\n";
        foreach ($fk as $f) {
            echo '      ' . $f->COLUMN_NAME . ' -> ' . $f->REFERENCED_TABLE_NAME . '.' . $f->REFERENCED_COLUMN_NAME . "\n";
        }
    }
}

echo "\n=== 4. Unique constraint (anti-duplikat di level DB) ===\n";
foreach ($inti as $t) {
    if (!DB::getSchemaBuilder()->hasTable($t)) {
        continue;
    }
    $u = DB::select("SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) cols
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0
        GROUP BY INDEX_NAME", [$t]);
    $ada = [];
    foreach ($u as $i) {
        $ada[] = $i->INDEX_NAME . '(' . $i->cols . ')';
    }
    echo '  ' . str_pad($t, 20) . ' ' . ($ada ? implode(', ', $ada) : 'TIDAK ADA unique selain PK') . "\n";
}