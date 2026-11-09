<?php
/**
 * Terapkan perbaikan pengodean pada daftar berkas tertentu.
 *
 * PowerShell 5.1 membaca berkas UTF-8 sebagai Windows-1252 lalu menuliskannya
 * kembali sebagai UTF-8. Setiap karakter non-ASCII jadi terurai ganda.
 * Membatalkannya cukup satu langkah iconv:
 *
 *     iconv('UTF-8', 'Windows-1252', $isi)
 *
 * Hasilnya mengembalikan byte asli. Terbukti pada resources/views/master/
 * bahan/index.blade.php: jumlah "≈" naik dari 0 ke 1, "•" 0 ke 1,
 * "▲" 0 ke 1.
 *
 * Pemakaian:
 *   php tools/perbaiki-terpilih.php daftar.txt
 */
$daftar = $argv[1] ?? null;
if ($daftar === null || !is_file($daftar)) {
    fwrite(STDERR, "Pemakaian: php tools/perbaiki-terpilih.php daftar.txt\n");
    exit(1);
}

$daftarBerkas = array_filter(array_map('trim', file($daftar, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)));

$spek = [
    'penyamaan '   => "\xE2\x89\x88",
    'bullet   '    => "\xE2\x80\xA2",
    'segitiga ▲'   => "\xE2\x96\xB2",
    'segitiga ▼'   => "\xE2\x96\xBC",
    'panah    '    => "\xE2\x86\x92",
    'pangkat ²'    => "\xC2\xB2",
    'miring   ±'   => "\xC2\xB1",
    'derajat  °'   => "\xC2\xB0",
    'tengah   ·'   => "\xC2\xB7",
    'ndash    –'   => "\xE2\x80\x93",
    'mdash    —'   => "\xE2\x80\x94",
    'kutip    “'   => "\xE2\x80\x9C",
];

$diperbaiki = 0;
$lewati = [];
$totalSimbol = 0;

foreach ($daftarBerkas as $p) {
    if (!is_file($p)) {
        $lewati[] = $p . ' (tidak ada)';
        continue;
    }
    $isi = file_get_contents($p);
    if (!preg_match("/[\x80-\xFF]/", $isi)) {
        continue;
    }
    if (!mb_check_encoding($isi, 'UTF-8')) {
        $lewati[] = $p . ' (bukan UTF-8)';
        continue;
    }

    $balik = @iconv('UTF-8', 'Windows-1252', $isi);
    if ($balik === false || $balik === '') {
        $lewati[] = $p . ' (iconv gagal)';
        continue;
    }
    if (!mb_check_encoding($balik, 'UTF-8')) {
        $lewati[] = $p . ' (hasil bukan UTF-8)';
        continue;
    }
    if ($balik === $isi) {
        continue;
    }

    file_put_contents($p, $balik);
    $diperbaiki++;

    $n = 0;
    foreach ($spek as $bytes) {
        $n += substr_count($balik, $bytes);
    }
    $totalSimbol += $n;
    printf("  diperbaiki: %-58s simbol pulih: %d%s", $p, $n, PHP_EOL);
}

printf("Berkas diproses : %d%s", count($daftarBerkas), PHP_EOL);
printf("Diperbaiki     : %d%s", $diperbaiki, PHP_EOL);
printf("Simbol pulih   : %d%s", $totalSimbol, PHP_EOL);
if ($lewati) {
    echo 'Dilewati       : ' . count($lewati) . PHP_EOL;
    foreach ($lewati as $l) {
        echo '  ' . $l . PHP_EOL;
    }
}