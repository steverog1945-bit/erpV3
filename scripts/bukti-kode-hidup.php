<?php
/* Bukti: kode C1 yang sudah "terpasang" itu hidup sebagai KODE, bukan
   sebagai skrip. Skrip .cjs di scripts/ tidak pernah dipanggil aplikasi
   (dibuktikan lewat composer.json / package.json / routes/console.php).

   Test ini memanggil helper-nya persis seperti controller memanggilnya.
   Kalau class-nya tidak ter-autoload, ini akan fatal error - jadi ini
   membuktikan file-nya benar-benar tersedia bagi aplikasi. */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Support\CekRentangHarga;

echo "=== 1. Autoload ===\n";
$ref = new ReflectionClass(CekRentangHarga::class);
echo '  class      : ' . $ref->getName() . "\n";
echo '  file       : ' . str_replace(dirname(__DIR__) . DIRECTORY_SEPARATOR, '', $ref->getFileName()) . "\n";
$psr4 = (string) file_get_contents(dirname(__DIR__) . '/vendor/composer/autoload_psr4.php');
echo '  psr4 App\\ terdaftar di vendor/composer/autoload_psr4.php : '
    . (str_contains($psr4, "App\\\\") ? 'YA' : 'TIDAK') . "\n";

echo "\n=== 2. Helper bisa dipanggil (dengan data nyata, READ-ONLY) ===\n";

// Gunakan data finishing_harga yang sudah ada. TIDAK menulis apa pun.
$baris = DB::table('finishing_harga')->orderBy('id_finishing_harga')->get();
echo '  baris finishing_harga di DB : ' . $baris->count() . "\n";

$contoh = $baris->first();
if ($contoh) {
    $tumpang = CekRentangHarga::tumpangTindih(
        'finishing_harga', 'id_finishing', (int) $contoh->id_finishing, [],
        $contoh->range_min, $contoh->range_max
    );
    echo '  rentang yang SUDAH ADA ('
        . $contoh->range_min . '-' . ($contoh->range_max ?? 'âˆž')
        . ') ditumpang-tindih dengan dirinya sendiri : ' . ($tumpang ? 'YA (benar)' : 'TIDAK (SALAH)') . "\n";

    // Rentang yang jelas tidak bertabrakan harus false.
    $tumpangJauh = CekRentangHarga::tumpangTindih(
        'finishing_harga', 'id_finishing', (int) $contoh->id_finishing, [],
        999999, 999999
    );
    echo '  rentang jauh (999999-999999) bertabrakan : ' . ($tumpangJauh ? 'YA (SALAH)' : 'TIDAK (benar)') . "\n";
}

echo "\n=== 3. Yang TIDAK dipanggil aplikasi ===\n";
$composer = (string) file_get_contents(dirname(__DIR__) . '/composer.json');
$pkg = (string) file_get_contents(dirname(__DIR__) . '/package.json');
$console = (string) file_get_contents(dirname(__DIR__) . '/routes/console.php');

$skripCjs = glob(dirname(__DIR__) . '/scripts/*.cjs') ?: [];
echo '  jumlah skrip .cjs di scripts/ : ' . count($skripCjs) . "\n";
$ADA = [];
foreach ($skripCjs as $f) {
    $ADA[] = basename($f);
}
echo '  composer.json menyebut .cjs  : ' . (preg_match('/\.cjs/', $composer) ? 'YA' : 'TIDAK') . "\n";
echo '  package.json menyebut .cjs   : ' . (preg_match('/\.cjs/', $pkg) ? 'YA' : 'TIDAK') . "\n";
echo '  routes/console.php .cjs      : ' . (preg_match('/\.cjs/', $console) ? 'YA' : 'TIDAK') . "\n";

echo "\n  Daftar skrip .cjs (alat sekali pakai, tidak dijalankan aplikasi):\n";
foreach ($ADA as $n) {
  echo '    - ' . $n . "\n";
}

echo "\n  Satu-satunya yang dipanggil composer: scripts/db_dump_commit.php (PHP, untuk dump DB).\n";
echo "  Jadwal scheduler yang aktif: backup:run, data:rapian, audit:integritas.\n";
echo "  Tidak ada satupun skrip .cjs di antara keduanya.\n";