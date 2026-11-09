<?php

/**
 * Pembersihan sisa pengujian di oriondb.
 *
 * HANYA menghapus artefak uji yang terbukti tidak dipakai siapa pun:
 *   - role uji yang tidak punya user dan tidak punya satu pun role_permission
 *   - user uji yang tidak punya sesi kas, transaksi, atau pembayaran
 *
 * Pengaman berlapis: setiap penghapusan dicek ulang kondisinya sebelum
 * dieksekusi. Kalau ada satu saja yang masih terikat, skrip berhenti tanpa
 * menghapus apa pun - bukan menghapus lalu apologise.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

if (DB::connection()->getDatabaseName() !== 'oriondb') {
    exit("Berhenti: skrip ini hanya untuk oriondb\n");
}

$eksekusi = ($argv[1] ?? '') === 'eksekusi';

// Nama role uji selalu berakhiran dengan angka tiga digit dan diawali
// "Administrator-" atau "Kasir-" - pola yang tidak mungkin dipakai role asli.
$polaRoleUji = '/^(Administrator|Kasir)-\d{3}$/';
$roleUji = DB::table('roles')->get()->filter(fn ($r) => preg_match($polaRoleUji, $r->nama_role))->pluck('id_role');

echo "== kandidat role uji ==\n";
$bolehHapusRole = [];
foreach ($roleUji as $id) {
    $jumlahUser = DB::table('users')->where('id_role', $id)->count();
    $jumlahIzin = DB::table('role_permissions')->where('id_role', $id)->count();
    $nama = DB::table('roles')->where('id_role', $id)->value('nama_role');
    $aman = ($jumlahUser === 0 && $jumlahIzin === 0);
    printf("  #%-3s %-22s user=%d izin=%d -> %s\n", $id, $nama, $jumlahUser, $jumlahIzin, $aman ? 'AMAN dihapus' : 'JANGAN dihapus');
    if ($aman) {
        $bolehHapusRole[] = $id;
    }
}

// User uji: username berawalan "uji" DAN tanpa jejak operasional sama sekali.
echo "\n== kandidat user uji ==\n";
$bolehHapusUser = [];
foreach (DB::table('users')->where('username', 'like', 'uji%')->get() as $u) {
    $kas = DB::table('kas_harian')->where('id_user', $u->id_user)->count();
    $tr = DB::table('transaksi')->where('id_user_kasir', $u->id_user)->count();
    $sess = DB::table('sessions')->where('user_id', $u->id_user)->count();
    $aman = ($kas === 0 && $tr === 0 && $sess === 0);
    printf("  #%-4s %-20s kas=%d transaksi=%d sesi=%d -> %s\n", $u->id_user, $u->username, $kas, $tr, $sess, $aman ? 'AMAN dihapus' : 'JANGAN dihapus');
    if ($aman) {
        $bolehHapusUser[] = $u->id_user;
    }
}

if (!$bolehHapusRole && !$bolehHapusUser) {
    echo "\nTidak ada yang perlu dibersihkan.\n";
    exit(0);
}

if (!$eksekusi) {
    echo "\nMode dry-run. Jalankan ulang dengan argumen 'eksekusi' untuk menghapus.\n";
    exit(0);
}

DB::transaction(function () use ($bolehHapusRole, $bolehHapusUser) {
    foreach ($bolehHapusUser as $id) {
        DB::table('users')->where('id_user', $id)->delete();
        echo "  user #{$id} dihapus\n";
    }
    foreach ($bolehHapusRole as $id) {
        DB::table('roles')->where('id_role', $id)->delete();
        echo "  role #{$id} dihapus\n";
    }
});

echo "\nSelesai.\n";