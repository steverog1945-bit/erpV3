<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = config('database.connections.mysql.database');
if (!str_contains((string) $db, 'testing')) { fwrite(STDERR, "BATAL\n"); exit(1); }

DB::table('golongan_konsumen')->insert(['nama_golongan' => 'Umum Audit']);
echo 'golongan: ' . DB::table('golongan_konsumen')->count() . "\n";