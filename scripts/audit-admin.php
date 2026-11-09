<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = config('database.connections.mysql.database');
if (!str_contains((string) $db, 'testing')) { fwrite(STDERR, "BATAL\n"); exit(1); }

$idRole = DB::table('roles')->where('nama_role', 'Administrator')->value('id_role')
    ?? DB::table('roles')->insertGetId(['nama_role' => 'Administrator']);

$karyawan = App\Models\Karyawan::factory()->create();

$user = App\Models\User::factory()->create([
    'id_role' => $idRole,
    'id_karyawan' => $karyawan->id_karyawan,
    'username' => 'auditadmin',
    'password' => Illuminate\Support\Facades\Hash::make('audit1234'),
]);

echo "OK user={$user->username} id={$user->id_user} role={$idRole}\n";

if (DB::table('finishing')->count() === 0) {
    DB::table('finishing')->insert(['nama_finishing' => 'Finishing Audit', 'harga_default' => 1000, 'metode_hitung' => 'quantity', 'created_at' => now(), 'updated_at' => now()]);
    echo "finishing fixture dibuat\n";
}
if (DB::table('jasa')->count() === 0) {
    DB::table('jasa')->insert(['nama_jasa' => 'Jasa Audit', 'biaya_operasional' => 0, 'biaya_tinta' => 0, 'created_at' => now(), 'updated_at' => now()]);
    echo "jasa fixture dibuat\n";
}
echo "DB aktif: {$db}\n";