<?php
/**
 * Ganti rentang baris pada berkas dengan isi dari berkas lain.
 *
 * Dipakai untuk blok markup yang spasi lekukannya sulit dicocokkan sebagai
 * string. Isi replacement dibaca dari file supaya tidak perlu escaping.
 *
 * Pemakaian:
 *   php tools/ganti-rentang.php <berkas-tujuan> <baris-awal> <baris-akhir> <berkas-isi>
 */
if ($argc < 5) {
    fwrite(STDERR, "Pemakaian: php tools/ganti-rentang.php <target> <mulai> <akhir> <isi>\n");
    exit(1);
}

$target = $argv[1];
$mulai = (int) $argv[2];
$akhir = (int) $argv[3];
$berkasIsi = $argv[4];

if (!is_file($target) || !is_file($berkasIsi)) {
    fwrite(STDERR, "Berkas tidak ditemukan\n");
    exit(1);
}

$isiBaris = rtrim(file_get_contents($berkasIsi));
$barisBaru = explode("\n", str_replace("\r\n", "\n", $isiBaris));

$barisLama = file($target);

if ($mulai < 1 || $akhir > count($barisLama) || $mulai > $akhir) {
    fwrite(STDERR, sprintf("Rentang %d-%d di luar jangkauan (total %d baris)\n", $mulai, $akhir, count($barisLama)));
    exit(1);
}

// Normalisasi ujung baris lama ke \n supaya penggabungannya konsisten.
for ($i = 0; $i < count($barisLama); $i++) {
    $barisLama[$i] = str_replace("\r\n", "\n", $barisLama[$i]);
}

$ganti = array_slice($barisLama, $mulai - 1, $akhir - $mulai + 1);
$hasil = array_merge(
    array_slice($barisLama, 0, $mulai - 1),
    array_map(static fn($b) => rtrim($b, "\n") . "\n", $barisBaru),
    array_slice($barisLama, $akhir)
);

file_put_contents($target, implode('', $hasil));

printf("Diganti %d baris lama dengan %d baris baru (%s).\n", count($ganti), count($barisBaru), basename($target));
echo "\n--- isi lama yang diganti ---\n";
foreach ($ganti as $b) {
    echo '  ' . rtrim($b) . "\n";
}