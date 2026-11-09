<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Nama tabel ASLI hasil pencarian pola, bukan tebakan.
$inti = ['jurnal_umum', 'bag_akun', 'kas_harian', 'kas_kecil', 'kas_manual',
    'pembayaran_hutang', 'pembayaran_riwayat', 'kartu_stok', 'bahan_baku'];

foreach ($inti as $t) {
    if (!DB::getSchemaBuilder()->hasTable($t)) {
        echo "\n--- $t : TIDAK ADA ---\n";
        continue;
    }
    echo "\n--- $t ---\n";
    foreach (DB::select("SHOW COLUMNS FROM `$t`") as $c) {
        $wajib = ($c->Null === 'NO' && $c->Default === null && !str_contains((string) $c->Extra, 'auto_increment'));
        echo '  ' . str_pad($c->Field, 28) . str_pad($c->Type, 22)
            . ($c->Null === 'YES' ? ' NULL' : ' NOTNULL')
            . ($c->Default === null ? '' : ' def=' . $c->Default)
            . ($wajib ? '  <== wajib' : '') . "\n";
    }

    $fk = DB::select("SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL", [$t]);
    if ($fk) {
        echo '    FK: ';
        echo implode(', ', array_map(fn ($f) => $f->COLUMN_NAME . '->' . $f->REFERENCED_TABLE_NAME, $fk));
        echo "\n";
    }

    $u = DB::select("SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) cols
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0
        GROUP BY INDEX_NAME", [$t]);
    $ada = array_map(fn ($i) => $i->INDEX_NAME . '(' . $i->cols . ')', $u);
    echo '    UNIQUE: ' . implode(', ', $ada) . "\n";
}

echo "\n=== Trigger (bisa bikin efek ganda dengan Laravel) ===\n";
$trg = DB::select("SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()");
if (!$trg) {
    echo "  tidak ada trigger\n";
} else {
    foreach ($trg as $t) {
        echo '  ' . $t->TRIGGER_NAME . ' ' . $t->EVENT_MANIPULATION . ' pada ' . $t->EVENT_OBJECT_TABLE . "\n";
    }
}