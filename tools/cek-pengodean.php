<?php
/**
 * Pemeriksa integritas pengodean.
 *
 * Hanya_two yang dicek:
 *   1. BOM di awal berkas (efek samping Set-Content -Encoding UTF8 di
 *      PowerShell 5.1).
 *   2. Mojibake, memakai tanda khas UTF-8 yang sudah terurai dua kali.
 *      Tanda dipilih spesifik, bukan rentang karakter, supaya huruf beraksen
 *      yang sah dan tanda baca seperti em-dash tidak salahreported.
 *
 * Pemeriksa ini hanya melaporkan; memperbaiki berkas ada di perbaiki-pengodean.
 */
$tandaMojibake = [
    'a-kutip-kanan-double' => "\xC3\xA2\xE2\x82\xAC\xE2\x80\x9D", // â€�
    'a-kutip-kiri-double'  => "\xC3\xA2\xE2\x82\xAC\xE2\x80\x9C", // â€œ
    'a-apostrof-kanan'      => "\xC3\xA2\xE2\x82\xAC\xE2\x80\x99", // â€™
    'a-bullet'             => "\xC3\xA2\xE2\x82\xA2",           // â€¢
    'a-nonbreaking-space'  => "\xC3\x82\xC2\xA0",           // Â +nbsp
    'a-e-accent'           => "\xC3\x83\xC2\xA9",           // Ã©
    'a-o-accent'           => "\xC3\x83\xC2\xB3",           // Ã³
];

$direktori = ['resources/views', 'resources/css', 'app', 'routes', 'config', 'tests', 'tools'];
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

$laporan = [];

foreach ($file as $path) {
    $isi = file_get_contents($path);

    $masalah = [];

    if (substr($isi, 0, 3) === "\xEF\xBB\xBF") {
        $masalah[] = 'BOM';
    }

    if (!mb_check_encoding($isi, 'UTF-8')) {
        $masalah[] = 'bukan-UTF8-valid';
    } else {
        foreach ($tandaMojibake as $nama => $tanda) {
            $n = substr_count($isi, $tanda);
            if ($n > 0) {
                $masalah[] = 'mojibake:' . $nama . '(' . $n . ')';
            }
        }
    }

    if ($masalah) {
        $laporan[$path] = $masalah;
    }
}

echo 'Berkas diperiksa : ' . count($file) . PHP_EOL;
echo 'Bermasalah       : ' . count($laporan) . PHP_EOL;

if ($laporan) {
    echo PHP_EOL;
    foreach ($laporan as $p => $m) {
        echo '  ' . $p . PHP_EOL;
        echo '      ' . implode(', ', $m) . PHP_EOL;
    }
}

exit($laporan ? 1 : 0);