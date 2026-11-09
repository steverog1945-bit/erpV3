<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== User berdasarkan role ===\n";
foreach (DB::table('users')
    ->join('roles', 'roles.id_role', '=', 'users.id_role')
    ->select('users.id_user', 'users.username', 'users.id_karyawan', 'roles.nama_role')
    ->orderBy('roles.nama_role')->orderBy('users.id_user')->get() as $u) {
    printf("  #%-3d %-16s karyawan=%-6s role=%s\n",
        $u->id_user, $u->username, (string) $u->id_karyawan, $u->nama_role);
}

echo "\n=== Administrator (yang bisa menyetujui) ===\n";
$admin = DB::table('users')
    ->join('roles', 'roles.id_role', '=', 'users.id_role')
    ->where('roles.nama_role', 'Administrator')
    ->select('users.id_user', 'users.username')
    ->get();
echo '  jumlah Administrator : ' . $admin->count() . "\n";
foreach ($admin as $a) {
    printf("    #%d  %s\n", $a->id_user, $a->username);
}

echo "\n  PENTING: tabel users tidak punya kolom is_active, jadi query di\n";
echo "  ApprovalService::setujui() menghitung Administrator tanpa peduli\n";
echo "  apakah akunnya masih dipakai. Akun leftovers tetap memblokir.\n";

echo "\n=== Pengajuan approval ===\n";
$reqs = DB::table('approval_requests')->orderBy('id')->get();
if ($reqs->isEmpty()) {
    echo "  tidak ada pengajuan\n";
} else {
    $un = DB::table('users')->pluck('username', 'id_user');
    foreach ($reqs as $r) {
        printf("  #%-3d %-24s pengaju=#%-3d %-14s %-10s kep=%-14s %s\n",
            $r->id, $r->tipe_aksi, $r->requested_by,
            (string) ($un[$r->requested_by] ?? '-'), $r->status,
            $r->decided_by ? (string) ($un[$r->decided_by] ?? '-') : '-',
            $r->status === 'Menunggu' ? '<-- MENUNGGU' : '');
    }
}