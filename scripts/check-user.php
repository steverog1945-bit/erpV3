<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$u = App\Models\User::where('username', 'auditadmin')->first();
if ($u) {
    echo "found: {$u->id_user}\n";
    echo "password hash: {$u->password}\n";
} else {
    echo "not found\n";
}