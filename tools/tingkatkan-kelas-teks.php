<?php
/**
 * Ganti kelas warna teks yang tidak terbaca, tanpa menyentuh kelas border.
 *
 * Alasannya: text-slate-300 di atas putih hanya 1.45:1 - praktis tak terlihat.
 * Files itself memakai text-slate-300 124 kali untuk teks yang memang harus
 * dibaca, sedangkan border-slate-300 hanya 18 kali untuk garis. Karena itu
 * hanya kelas TEKS yang dinaikkan, kelas border dibiarkan.
 *
 * Berkas ditulis dengan fwrite/file_put_contents, bukan lewat PowerShell.
 * PowerShell 5.1 merusak karakter non-ASCII saat menulis ulang berkas.
 *
 * Pemakaian:
 *   php tools/tingkatkan-kelas-teks.php            laporan saja
 *   php tools/tingkatkan-kelas-teks.php --apply    menulis
 */
$apply = in_array('--apply', $argv, true);

$peta = [
    // kelas lama            => kelas baru        alasan
    'text-slate-300'        => 'text-slate-500',  // 1.45:1 di putih, jauh di bawah AA
    'placeholder-slate-300' => 'placeholder-slate-500',
    'text-indigo-400'       => 'text-indigo-600', // 2.95:1 di atas indigo-50
    'hover:text-indigo-400' => 'hover:text-indigo-600',
];

$file = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('resources/views'));
foreach ($it as $f) {
    if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
        $file[] = $f->getPathname();
    }
}

$totalGanti = array_fill_keys(array_keys($peta), 0);
$berkasSentuh = 0;
$rusak = [];

foreach ($file as $path) {
    $asal = file_get_contents($path);
    $baru = $asal;

    foreach ($peta as $lama => $baruKelas) {
        $n = 0;
        // Batasi agar kelas border tidak ikut tertimpa: "text-slate-300"
        // tidak pernah menjadi bagian dari "border-slate-300", jadi
        // penggantian string biasa sudah aman.
        $baru = str_replace($lama, $baruKelas, $baru, $n);
        $totalGanti[$lama] += $n;
    }

    if ($baru === $asal) {
        continue;
    }

    if (!mb_check_encoding($baru, 'UTF-8')) {
        $rusak[] = $path;
        continue;
    }

    $berkasSentuh++;
    if ($apply) {
        file_put_contents($path, $baru);
    }
}

printf("Berkas blade      : %d%s", count($file), PHP_EOL);
printf("Mode              : %s%s", $apply ? 'TULIS' : 'LAPORAN (tulis --apply untuk menerapkan)', PHP_EOL);
printf("Berkas tersentuh : %d%s", $berkasSentuh, PHP_EOL);
foreach ($totalGanti as $kelas => $n) {
    printf("  %-24s -> %-24s %4d%s", $kelas, $peta[$kelas], $n, PHP_EOL);
}
if ($rusak) {
    printf("Dilewati (bukan UTF-8) : %d%s", count($rusak), PHP_EOL);
    foreach ($rusak as $p) {
        echo '  ' . $p . PHP_EOL;
    }
}