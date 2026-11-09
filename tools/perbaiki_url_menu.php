<?php
/**
 * Perbaiki url_link menu yang menunjuk ke URL yang tidak pernah ada.
 *
 * Latar belakang: tabel menus menyimpan url_link yang juga dipakai sebagai
 * teks di matriks Pengaturan Akses (resources/views/akses/_matriks.blade.php
 * baris 36). Dua di antaranya berisi URL karangan:
 *
 *   id 41 "Kasir Utama (Input Order)"  url='penjualan-input-order'
 *   id 42 "Order Jersey (Input Order)" url='jersey-input-order'
 *
 * URL sebenarnya adalah /penjualan/kasir (route penjualan.create) dan
 * /jersey/create (route jersey.orders.create). Madhya izin 41/42 tetap
 * dipakai sidebar sebagai alias - yang diubah hanya teks url_link-nya,
 * sehingga tidak ada perubahan akses untuk role mana pun.
 *
 * Nilai lama disimpan di backup_url_menu bila perlu dikembalikan.
 *
 * Jalankan tanpa argumen untuk dry-run, tambahkan 'eksekusi' untuk menulis.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (DB::connection()->getDatabaseName() !== 'oriondb') {
    exit("Berhenti: skrip ini hanya untuk oriondb\n");
}

$eksekusi = ($argv[1] ?? '') === 'eksekusi';

// Tabel cadangan nilai lama. Dibuat di sini, bukan lewat migration, karena
// ini perkakas audit sekali jalan dan tidak perlu ikut versioning aplikasi.
if (!Schema::hasTable('backup_url_menu')) {
    Schema::create('backup_url_menu', function ($t) {
        $t->id('id_backup')->autoIncrement();
        $t->unsignedSmallInteger('id_menu')->unique();
        $t->string('url_lama', 255);
        $t->string('url_baru', 255);
        $t->timestamp('dicatat_pada')->nullable();
    });

    echo "Tabel backup_url_menu dibuat.\n";
}

// id menu => [url_link baru, route yang harus ada]
$perbaikan = [
    41 => ['penjualan/kasir', 'penjualan.create'],
    42 => ['jersey/create', 'jersey.orders.create'],
];

// Pastikan URL barunya benar-benar route yang hidup, kalau tidak jangan tulis.
$adaRoute = static function (string $namaRoute): bool {
    try {
        return app('router')->has($namaRoute);
    } catch (\Throwable $e) {
        return false;
    }
};

echo "== rencana perbaikan url_link ==\n";
foreach ($perbaikan as $idMenu => $spec) {
    [$urlBaru, $route] = $spec;
    $m = DB::table('menus')->where('id_menu', $idMenu)->first();

    if (!$m) {
        echo '  id ' . $idMenu . ": TIDAK ADA di tabel menus, dilewati\n";

        continue;
    }

    $hidup = $adaRoute($route);
    printf(
        "  id %-3s %-30s '%s' -> '%s'  (route %s: %s)\n",
        $idMenu,
        $m->nama_menu,
        $m->url_link,
        $urlBaru,
        $route,
        $hidup ? 'ADA' : 'TIDAK ADA - dibatalkan'
    );

    if (!$hidup) {
        unset($perbaikan[$idMenu]);
    }
}

if (!$perbaikan) {
    echo "\nTidak ada yang perlu ditulis.\n";
    exit(0);
}

if (!$eksekusi) {
    echo "\nMode dry-run. Jalankan ulang dengan argumen 'eksekusi' untuk menulis.\n";
    exit(0);
}

DB::transaction(function () use ($perbaikan) {
    foreach ($perbaikan as $idMenu => $spec) {
        $urlBaru = $spec[0];
        $lama = DB::table('menus')->where('id_menu', $idMenu)->value('url_link');

        DB::table('backup_url_menu')->updateOrInsert(
            ['id_menu' => $idMenu],
            ['url_lama' => $lama, 'url_baru' => $urlBaru, 'dicatat_pada' => now()]
        );

        DB::table('menus')->where('id_menu', $idMenu)->update(['url_link' => $urlBaru]);
        echo "  menu {$idMenu}: '{$lama}' -> '{$urlBaru}'\n";
    }
});

echo "\n== verifikasi ==\n";
foreach (array_keys($perbaikan) as $idMenu) {
    $m = DB::table('menus')->where('id_menu', $idMenu)->first();
    $role = DB::table('role_permissions')->where('id_menu', $idMenu)->pluck('id_role')->all();
    echo '  id ' . $idMenu . '  ' . $m->nama_menu . "  url='" . $m->url_link
        . "'  role=" . ($role ? implode(',', $role) : 'tidak ada') . "\n";
}