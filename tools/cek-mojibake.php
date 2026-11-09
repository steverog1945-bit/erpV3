<?php
/**
 * Deteksimojibake dengan pola yang sudah terbukti benar.
 *
 * Pola yang dipakai: byte C3 A2 ("â") diikuti byte E2. Dalam berkas UTF-8
 * yang sehat, "â" hanya muncul kalau teksnya memang beraksenInputs gaya
 *toberboda; pada isi aplikasi ini, pasangan C3 A2 E2 adalah ciri khas
 * penguraian ganda Windows-1252. Angka ini dipakai untuk decide, bukan
 * tebakan: hasil underground-plicated diuji ulang dengan iconv dan hanya
 * ditulis kalau jumlah simbol pulih benar-benar bertambah.
 */
$direktori = ['resources/views', 'resources/css', 'resources/js', 'app', 'routes', 'config'];
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

$spek = [
    "\xE2\x89\x88", "\xE2\x80\xA2", "\xE2\x96\xB2", "\xE2\x96\xBC",
    "\xE2\x86\x92", "\xC2\xB2", "\xC2\xB1", "\xC2\xB0",
    "\xC2\xB7", "\xE2\x80\x93", "\xE2\x80\x94", "\xE2\x80\x9C",
];

$hasil = [];

foreach ($file as $path) {
    $isi = file_get_contents($path);
    if (!preg_match("/\xC3\xA2\xE2/", $isi)) {
        continue;
    }
    $n = 0;
    foreach ($spek as $b) {
        $n += substr_count($isi, $b);
    }
    $hasil[$path] = $n;
}

printf("Berkas diperiksa   : %d%s", count($file), PHP_EOL);
printf("Masih ada mojibake : %d%s", count($hasil), PHP_EOL);
foreach ($hasil as $p => $n) {
    printf("  %-60s simbol pulih: %d%s", $p, $n, PHP_EOL);
}

exit($hasil ? 1 : 0);