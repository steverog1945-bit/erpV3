<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Kolom yang WAJIB diisi (NOT NULL tanpa default, bukan auto-increment).
foreach (['bahan_baku','mesin','kategori','role_permissions','menus','accounting_periods'] as $tabel) {
    echo "=== $tabel: kolom wajib ===\n";
    foreach (DB::select("SHOW COLUMNS FROM `$tabel`") as $c) {
        $wajib = $c->Null === 'NO' && $c->Default === null && !str_contains((string) $c->Extra, 'auto_increment');
        echo '  ' . str_pad($c->Field, 24) . $c->Type . str_pad($c->Null, 6) . ($c->Default === null ? 'no-default' : 'default=' . $c->Default)
            . ($wajib ? '   <== WAJIB' : '') . "\n";
    }
}