<?php
/**
 * Berikan izin menu yang hilang, dan perbaiki visibilitas grup sidebar.
 *
 * Latar belakang: menu 68, 37, 63, 66 tidak punya satu pun baris
 * role_permissions, dan grup sidebar yang memuatnya tidak memasukkan
 * id-nya ke syarat visibilitas. Akibatnya menunya tidak pernah muncul
 * dan route-nya selalu 403 - bukan karena fiturnya rusak, tapi karena
 * tidak ada yang mengaktifkannya.
 *
 * Semua perubahan di oriondb dijalankan lewat satu transaksi dan hanya
 * MENAMBAH baris role_permissions - tidak ada yang dihapus atau diubah.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

if (DB::connection()->getDatabaseName() !== 'oriondb') {
    exit("Berhenti: skrip ini hanya untuk oriondb\n");
}

$eksekusi = ($argv[1] ?? '') === 'eksekusi';

// Siapa yang rightful punya tiap menu.
//
// 68 Jasa Jahit Borongan  -> sejajar dengan menu 32 "Jasa" (Administrator).
//                           Ini menu Relasi Bisnis yang mengelola tarif jasa,
//                           bukan menu operasional harian.
// 37 Pengaturan Aplikasi  -> sejajar dengan /setting-akses dan /menu-editor,
//                           keduanya hanya Administrator.
// 63 Jam Kerja            -> memengaruhi perhitungan lembur Produksi; ditulis
//                           oleh Administrator.
// 66 Bagi Hasil Desainer  -> sudah dikunci hanya.admin pada audit ini, jadi
//                           izinnya harus naik ke Administrator juga.
$rencana = [
    68 => [1],
    37 => [1],
    63 => [1],
    66 => [1],
];

echo "== rencana izin ==\n";
foreach ($rencana as $idMenu => $roles) {
    $m = DB::table('menus')->where('id_menu', $idMenu)->first();
    $sudah = DB::table('role_permissions')->where('id_menu', $idMenu)->pluck('id_role')->all();
    printf(
        "  id %-3s %-30s url=%-24s tambah ke role %-10s (sudah ada: %s)\n",
        $idMenu,
        $m->nama_menu ?? '(TIDAK ADA)',
        $m->url_link ?? '-',
        implode(',', $roles),
        $sudah ? implode(',', $sudah) : 'tidak ada'
    );
}

if (!$eksekusi) {
    echo "\nMode dry-run. Jalankan ulang dengan argumen 'eksekusi' untuk menulis.\n";
    exit(0);
}

DB::transaction(function () use ($rencana) {
    foreach ($rencana as $idMenu => $roles) {
        foreach ($roles as $idRole) {
            $ada = DB::table('role_permissions')
                ->where('id_menu', $idMenu)
                ->where('id_role', $idRole)
                ->exists();

            if ($ada) {
                continue;
            }

            DB::table('role_permissions')->insert([
                'id_role' => $idRole,
                'id_menu' => $idMenu,
            ]);
            echo "  + role_permissions({$idRole}, {$idMenu})\n";
        }
    }
});

echo "\n== verifikasi ==\n";
foreach (array_keys($rencana) as $idMenu) {
    $m = DB::table('menus')->where('id_menu', $idMenu)->first();
    $p = DB::table('role_permissions')->where('id_menu', $idMenu)->pluck('id_role')->all();
    printf("  id %-3s %-30s role: %s\n", $idMenu, $m->nama_menu ?? '-', $p ? implode(',', $p) : 'TIDAK ADA');
}