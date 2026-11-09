<?php
/**
 * Buang BOM dari berkas sumber.
 *
 * PowerShell 5.1 pada Windows menulis BOM saat memakai Set-Content -Encoding
 * UTF8. BOM itu bukan karakter yang dikehendaki sumber dan bisa mengganggu
 * parser PHP, blade, dan diff. Skrip ini menapis berkas yang benar-benar
 * diawali BOM lalu menuliskannya ulang tanpa BOM. Isi lain tidak diubah
 * sama sekali: hanya tiga byte pertama yang dibuang.
 */
$direktori = ['resources/views', 'resources/css', 'resources/js', 'app', 'routes',
              'config', 'tests', 'tools', 'database'];
$suffix = ['php', 'js', 'css'];

$file = [];
foreach ($direktori as $d) {
    if (!is_dir($d)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d));
    foreach ($it as $f) {
        if ($f->isFile() && in_array(strtolower($f->getExtension()), $suffix, true)) {
            $file[] = $f->getPathname();
        }
    }
}

$perbaiki = 0;
$total = 0;

foreach ($file as $path) {
    $isi = file_get_contents($path);
    $total++;
    if (substr($isi, 0, 3) !== "\xEF\xBB\xBF") {
        continue;
    }
    $tanpaBom = substr($isi, 3);
    if (!mb_check_encoding($tanpaBom, 'UTF-8')) {
        echo '  LEWATI (bukan UTF-8): ' . $path . PHP_EOL;
        continue;
    }
    file_put_contents($path, $tanpaBom);
    $perbaiki++;
}

echo 'Berkas diperiksa : ' . $total . PHP_EOL;
echo 'BOM dibuang       : ' . $perbaiki . PHP_EOL;