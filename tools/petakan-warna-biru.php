<?php
/**
 * @OBSOLETE (2026-11) — JANGAN JALANKAN.
 * Alat warisan era palet teal (indigo -> teal). Ramp sky/blue/teal di
 * resources/css/app.css kini sudah dipetakan ke Command Center, dan hex
 * literal lama sudah dibersihkan. Menjalankan ulang berkas ini akan
 * mengembalikan biru-2563EB menjadi #006C7B dan merusak palet.
 * Pertahankan hanya sebagai arsip sejarah codemod.
 *
 * Petakan warna biru-ungu yang ditulis langsung ke blade dan CSS.
 *
 * Kenapa perlu: Tailwind memancarkan utility lewat var(--color-indigo-*),
 * dan ramp itu sudah diganti ke teal. Tapi ada warna indigo yang ditulis
 * sebagai hex literal di dalam blade dan CSS - misalnya
 * `confirmButtonColor: '#4f46e5'` untuk tombol dialog, `background: #4f46e5`
 * untuk badge sidebar, dan cincin fokus select. Semua itu tidak ikut ramp,
 * jadi masih biru-ungu di layar.
 *
 * Berkas ditulis lewat PHP, bukan PowerShell: PowerShell 5.1 merusak
 * karakter non-ASCII saat menulis ulang berkas UTF-8.
 *
 * Pemakaian:
 *   php tools/petakan-warna-biru.php            laporan saja
 *   php tools/petakan-warna-biru.php --apply    menulis
 */
$apply = in_array('--apply', $argv, true);

$peta = [
    // indigo-600/700/800/900 -> teal
    '#4f46e5' => '#006C7B',
    '#4338ca' => '#005663',
    '#3730a3' => '#01434D',
    '#312e81' => '#02363E',
    '#1e1b4b' => '#012228',
    // indigo-500 -> teal-500
    '#6366f1' => '#04727E',
    // indigo-400/300 -> teal terang, untuk latar gelap
    '#818cf8' => '#5FC4D4',
    '#a5b4fc' => '#7FD0DE',
    '#c7d2fe' => '#B6E4EC',
    // indigo-50/100 -> teal sangat muda
    '#eef2ff' => '#EAF6F7',
    '#dfe2fb' => '#D2EBEE',
    // biru sky/blue
    '#3b82f6' => '#04727E',
    '#2563eb' => '#006C7B',
    '#1d4ed8' => '#005663',
    '#1e40af' => '#01434D',
    // latar biru-ungu untuk mode gelap
    '#17223b' => '#01434D',
    '#33415f' => '#0A4C57',
];

$target = [];
foreach (['resources/views', 'resources/css'] as $d) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d));
    foreach ($it as $f) {
        if ($f->isFile() && in_array(strtolower($f->getExtension()), ['php', 'css'], true)) {
            $target[] = $f->getPathname();
        }
    }
}

$total = array_fill_keys(array_keys($peta), 0);
$sentuh = 0;

foreach ($target as $path) {
    $asal = file_get_contents($path);
    $baru = $asal;
    foreach ($peta as $lama => $baruWarna) {
        $n = 0;
        $baru = str_replace($lama, $baruWarna, $baru, $n);
        if ($n > 0) {
            $total[$lama] += $n;
        }
    }
    if ($baru === $asal) {
        continue;
    }
    if (!mb_check_encoding($baru, 'UTF-8')) {
        echo '  LEWATI (bukan UTF-8): ' . $path . PHP_EOL;
        continue;
    }
    $sentuh++;
    if ($apply) {
        file_put_contents($path, $baru);
    }
}

printf("Berkas diperiksa : %d%s", count($target), PHP_EOL);
printf("Mode             : %s%s", $apply ? 'TULIS' : 'LAPORAN (--apply untuk menulis)', PHP_EOL);
printf("Berkas tersentuh : %d%s", $sentuh, PHP_EOL);
foreach ($total as $lama => $n) {
    if ($n > 0) {
        printf("  %-10s -> %-10s %4d%s", $lama, $peta[$lama], $n, PHP_EOL);
    }
}