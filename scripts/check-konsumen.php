<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo 'konsumen count: ' . DB::table('konsumen')->count() . "\n";
DB::table('konsumen')->orderBy('id_konsumen','desc')->limit(3)->get()->each(function($r){ 
    echo $r->id_konsumen . ' - ' . $r->nama_konsumen . "\n"; 
});