<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Apakah ada model kepemilikan data (IDOR-relevant)? ===\n";
echo "Cari kolom pemilik di tabel utama alur Customer -> Journal.\n\n";

$tabel = ['transaksi', 'spk', 'produksi', 'kartu_stok', 'bahan_baku', 'konsumen'];
$polaPemilik = ['id_user', 'id_karyawan', 'id_divisi', 'divisi', 'id_pegawai', 'owner_id', 'created_by', 'id_pengguna'];

foreach ($tabel as $t) {
    if (!DB::getSchemaBuilder()->hasTable($t)) {
        echo "  $t: tabel tidak ada\n";
        continue;
    }
    $kolom = DB::getSchemaBuilder()->getColumnListing($t);
    $ditemukan = array_values(array_intersect($polaPemilik, $kolom));
    printf("  %-12s kolom pemilik: %s\n", $t, $ditemukan ? implode(', ', $ditemukan) : 'TIDAK ADA');
}

echo "\n=== Kesimpulan soal IDOR ===\n";
$ada = [];
foreach ($tabel as $t) {
    if (!DB::getSchemaBuilder()->hasTable($t)) {
        continue;
    }
    $kolom = DB::getSchemaBuilder()->getColumnListing($t);
    if (array_intersect($polaPemilik, $kolom)) {
        $ada[] = $t;
    }
}
if ($ada) {
    echo "  Tabel yang punya kolom pemilik: " . implode(', ', $ada) . "\n";
    echo "  -> IDOR antar-pengguna DIMUNGKINkan dan harus diuji.\n";
} else {
    echo "  TIDAK ADA satu pun tabel alur yang punya kolom pemilik.\n";
    echo "  -> Ini aplikasi SATU PERUSAHAAN: semua staf melihat semua order.\n";
    echo "  -> IDOR antar-pengguna TIDAK berlaku sebagai kategori finding di sini;\n";
    echo "     batas keamanan yang NYATA adalah PERAN (role), bukan kepemilikan baris.\n";
}

echo "\n=== Batas yang benar-benar ada: role & akses menu ===\n";
$roles = DB::table('roles')->get();
echo '  role terdaftar: ' . $roles->count() . "\n";
foreach ($roles as $r) {
    $jumlah = DB::table('users')->where('id_role', $r->id_role)->count();
    printf("    #%-3d %-22s user=%d\n", $r->id_role, $r->nama_role, $jumlah);
}

echo "\n=== Konfigurasi menu_access: apakah jadi pagar server? ===\n";

$cfg = config('menu_access.rules', []);
echo '  jumlah aturan di config/menu_access.rules : ' . count($cfg) . "\n";
echo '  middleware yang menerapkannya : ' . (middleware_exists_check() ? 'menu.access' : 'tidak ditemukan') . "\n";

function middleware_exists_check(): bool
{
    $r = app('router')->getMiddleware();
    foreach ($r as $k => $v) {
        foreach ((array) $v as $alias) {
            if ($alias === 'menu.access') {
                return true;
            }
        }
    }

    return false;
}

echo "\n=== Izin::boleh dipakai di berapa banyak tempat? ===\n";
$n = 0;
$file = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/app'));
foreach ($rii as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
        $s = file_get_contents($f->getPathname());
        if (str_contains($s, 'Izin::boleh')) {
            $n++;
            $file[] = str_replace(dirname(__DIR__) . DIRECTORY_SEPARATOR, '', $f->getPathname());
        }
    }
}
echo '  jumlah: ' . $n . "\n";
foreach ($file as $x) {
    echo '    ' . $x . "\n";
}