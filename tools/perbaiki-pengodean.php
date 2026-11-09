<?php
/**
 * Perbaiki berkas yang pengodeannya rusak.
 *
 * PowerShell 5.1 membaca berkas UTF-8 sebagai Windows-1252, lalu menulisnya
 * kembali sebagai UTF-8. Setiap karakter non-ASCII jadi terurai dua kali:
 * "≈" (E2 89 88) menjadi "â‰ˆ" (C3 A2 E2 80 B0 CB 86).
 *
 * Membatalkannya cukup satu langkah iconv: encode UTF-8 ke Windows-1252 akan
 * menghasilkan kembali byte asli, yaitu E2 89 88. Tidak ada langkah kedua -
 * langkah kedua justru mengembalikan kerusakannya.
 *
 * Pemakaian:
 *   php tools/perbaiki-pengodean.php            laporan saja
 *   php tools/perbaiki-pengodean.php --repair   menulis perbaikan
 */
$dryRun = !in_array('--repair', $argv, true);

$direktori = ['resources/views', 'resources/css', 'resources/js', 'app', 'routes',
              'config', 'tests', 'database', 'tools'];
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

/* Simbol yang harus kembali utuh. Dipakai sebagai bukti, bukan penentu. */
$spek = [
    'penyamaan' => "\xE2\x89\x88",  // =
    'bullet'    => "\xE2\x80\xA2",  // •
    'segitiga'  => "\xE2\x96\xB2",  // ▲
    'segitiga bawah' => "\xE2\x96\xBC",  // ▼
    'panah'     => "\xE2\x86\x92",  // →
    'pangkat2'  => "\xC2\xB2",      // ²
    'miring'    => "\xC2\xB1",      // ±
    'derajat'   => "\xC2\xB0",      // °
    'tengah'    => "\xC2\xB7",      // ·
    'ndash'     => "\xE2\x80\x93",  // –
    'mdash'     => "\xE2\x80\x94",  // —
    'kutip'     => "\xE2\x80\x9C",  // “
    'rupiah-ish' => "\xE2\x82\xAC", // € (sering ikut rusak)
];

function hitungSimbol($teks)
{
    global $spek;
    $n = 0;
    foreach ($spek as $bytes) {
        $n += substr_count($teks, $bytes);
    }
    return $n;
}

$rusak = [];
$diperbaiki = 0;
$lewati = 0;

foreach ($file as $path) {
    $isi = file_get_contents($path);

    // Berkas tanpa karakter non-ASCII tidak mungkin rusak.
    if (!preg_match("/[\x80-\xFF]/", $isi)) {
        continue;
    }
    if (!mb_check_encoding($isi, 'UTF-8')) {
        continue;
    }

    // Mojibake selalu memuat "â€" atau padanan cp1252 lain yang tak lazim.
    // Catatan: pola memakai tanda kutip GANDA, karena PHP hanya mengurai \x..
    // di dalam string berkutip ganda.
    if (!preg_match("/\xC3\xA2\xE2|\xC3\xA2\xE2\x80|\xC3\x83/", $isi)) {
        continue;
    }

    $balik = @iconv('UTF-8', 'Windows-1252', $isi);
    if ($balik === false || $balik === '') {
        $lewati++;
        echo '  LEWATI: ' . $path . PHP_EOL;
        continue;
    }

    $sebelum = hitungSimbol($isi);
    $sesudah = hitungSimbol($balik);

    if ($sesudah <= $sebelum) {
        $lewati++;
        continue;
    }

    $rusak[] = [$path, $sebelum, $sesudah];

    if ($dryRun) {
        continue;
    }
    file_put_contents($path, $balik);
    $diperbaiki++;
}

printf("Berkas diperiksa : %d%s", count($file), PHP_EOL);
printf("Berkas rusak     : %d%s", count($rusak), PHP_EOL);
printf("Mode             : %s%s", $dryRun ? 'LAPORAN (tulis --repair untuk memperbaiki)' : 'PERBAIKAN', PHP_EOL);
if (!$dryRun) {
    printf("Diperbaiki       : %d%s", $diperbaiki, PHP_EOL);
    printf("Dilewati         : %d%s", $lewati, PHP_EOL);
}
foreach ($rusak as $r) {
    printf("  %-60s simbol %d -> %d%s", $r[0], $r[1], $r[2], PHP_EOL);
}

exit(0);