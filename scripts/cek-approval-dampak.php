<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Akun Administrator ===\n";
foreach (DB::table('users')
    ->join('roles', 'roles.id_role', '=', 'users.id_role')
    ->where('roles.nama_role', 'Administrator')
    ->select('users.id_user', 'users.username', 'users.id_karyawan', 'users.created_at', 'users.updated_at')
    ->orderBy('users.id_user')->get() as $u) {
    echo '  #' . $u->id_user . ' ' . str_pad($u->username, 14)
        . ' karyawan=' . str_pad((string) $u->id_karyawan, 5)
        . ' dibuat=' . $u->created_at . "\n";
}

echo "\n=== Dampak: berapa pengajuan yang bisa/kurang bisa diputuskan ===\n";
$adminIds = DB::table('users')
    ->join('roles', 'roles.id_role', '=', 'users.id_role')
    ->where('roles.nama_role', 'Administrator')
    ->pluck('users.id_user')->all();

echo '  Administrator terdaftar : ' . implode(', ', $adminIds) . "\n";

// Simulasikan aturan setujui() untuk tiap Administrator
foreach ($adminIds as $a) {
    $adaLain = false;
    foreach ($adminIds as $b) {
        if ((int) $b !== (int) $a) { $adaLain = true; }
    }
    $menunggu = DB::table('approval_requests')
        ->where('requested_by', $a)->where('status', 'Menunggu')->count();
    echo '  #' . $a . ' adaPenyetujuLain=' . ($adaLain ? 'YA' : 'TIDAK')
        . '  -> boleh menyetujui pengajuannya sendiri: ' . ($adaLain ? 'TIDAK' : 'YA')
        . '  (menunggu miliknya: ' . $menunggu . ")\n";
}

echo "\n=== Pengajuan Menunggu ===\n";
$menunggu = DB::table('approval_requests')->where('status', 'Menunggu')->get();
if ($menunggu->isEmpty()) {
    echo "  tidak ada\n";
}
foreach ($menunggu as $m) {
    $un = DB::table('users')->pluck('username', 'id_user');
    echo '  #' . $m->id . ' ' . $m->tipe_aksi . ' oleh ' . ($un[$m->requested_by] ?? '?')
        . '  ringkasan: ' . substr((string) $m->ringkasan, 0, 60) . "\n";
}