<?php
/**
 * Bandingkan byte berkas di working tree dengan versi yang tersimpan di git,
 * khusus soal pengodean. Tujuannya menjawab satu pertanyaan: BOM dan
 * mojibake itu sudah ada sebelumnya, atau muncul karena penulisan ulang
 * berkas belakangan ini.
 */
$daftar = [
    'resources/views/layouts/app.blade.php',
    'resources/css/app.css',
    'resources/css/ui-refresh.css',
    'resources/views/penjualan/create.blade.php',
    'resources/views/master/bank/index.blade.php',
    'resources/views/master/akun/index.blade.php',
    'resources/views/landing-page-public.blade.php',
    'resources/views/partials/tooltip-script.blade.php',
];

function diagnosis($isi)
{
    $m = [];
    if (substr($isi, 0, 3) === "\xEF\xBB\xBF") {
        $m[] = 'BOM';
    }
    if (!mb_check_encoding($isi, 'UTF-8')) {
        $m[] = 'bukan-UTF8';
        return $m;
    }
    $tanda = [
        'kutip-kanan' => "\xC3\xA2\xE2\x82\xAC\xE2\x80\x9D",
        'kutip-kiri'  => "\xC3\xA2\xE2\x82\xAC\xE2\x80\x9C",
        'apostrof'    => "\xC3\xA2\xE2\x82\xAC\xE2\x80\x99",
        'bullet'      => "\xC3\xA2\xE2\x82\xA2",
        'nbsp-ganda'  => "\xC3\x82\xC2\xA0",
        'e-accent'    => "\xC3\x83\xC2\xA9",
    ];
    foreach ($tanda as $nama => $tandaBytes) {
        $c = substr_count($isi, $tandaBytes);
        if ($c > 0) {
            $m[] = $nama . 'x' . $c;
        }
    }
    return $m;
}

function ringkas($arr)
{
    return $arr ? implode(',', $arr) : 'bersih';
}

printf("%-34s %-22s %s\n", 'BERKAS', 'SEBELUM (git)', 'SEKARANG (disk)');
echo str_repeat('-', 92), "\n";

foreach ($daftar as $p) {
    $pUnix = str_replace('\\', '/', $p);
    $git = shell_exec('git cat-file -p HEAD:' . escapeshellarg($pUnix) . ' 2>/dev/null');
    $diGit = ($git === null || $git === '') ? 'tidak-ada-di-HEAD' : ringkas(diagnosis($git));
    $diDisk = is_file($p) ? ringkas(diagnosis(file_get_contents($p))) : 'hilang';

    printf("%-34s %-22s %s\n", substr($p, strrpos($p, '/') + 1), $diGit, $diDisk);
}