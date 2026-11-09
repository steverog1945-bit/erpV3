<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = config('database.connections.mysql.database');
if (!str_contains((string) $db, 'testing')) { fwrite(STDERR, "BATAL\n"); exit(1); }

$idUser = DB::table('users')->where('username', 'auditadmin')->value('id_user');
DB::table('kas_harian')->insert([
    'tgl_kas' => now()->format('Y-m-d'),
    'id_user' => $idUser,
    'saldo_awal' => 100000, 'saldo_akhir' => 100000,
    'status_lock' => 'open',
    'created_at' => now(), 'updated_at' => now(),
]);
echo "kas dibuka untuk user {$idUser}\n";