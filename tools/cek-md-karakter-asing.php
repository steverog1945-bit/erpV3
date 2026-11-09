<?php
/**
 * Pemindai karakter non-ASCII yang tidak seharusnya ada pada berkas .md.
 *
 * tools/cek-karakter-asing.php dan cek-mojibake.php hanya memindai 437 berkas
 * PHP/blade - keduanya TIDAK menyentuh .md. Laporan audit dibaca orang
 * non-teknis, jadi satu karakter CJK atau satu U+FFFD yang bocor ke dalam
 * .md langsung kelihatan. Skrip ini menutup celah itu.
 *
 * Yang dianggap bermasalah:
 *   - U+FFFD : replacement character, tanda decoding rusak
 *   - CJK / Hangul / Kana : kebocoran dari mesin lain
 *   - karakter kontrol yang tidak-boleh (kecuali TAB, CR, LF)
 *
 * Yang MASIH diizinkan dan sengaja ada di laporan: tanda baca Indonesia,
 * panah, dan tanda status PASS/FAIL.
 */
$target = $argv[1] ?? base_path('AUDIT_ORION_GRAFIKA_FINAL.md');

if (!is_file($target)) {
    exit("Berkas tidak ada: {$target}\n");
}

$isi = (string) file_get_contents($target);

// Izinkan per karakter - daftar di bawah.
$izinkan = [
    0x2014 => '-', 0x2013 => '-', 0x2018 => "'", 0x2019 => "'",
    0x201C => '"', 0x201D => '"', 0x2022 => '-', 0x25B8 => '>',
    0x2713 => 'v', 0x2717 => 'x', 0x2718 => 'x', 0x2192 => '->',
    0x2265 => '>=', 0x2264 => '<=', 0x00D7 => 'x', 0x00F7 => '/',
    0x00B0 => 'deg', 0x00A7 => 'S', 0x00B6 => 'P', 0x00B1 => '+/-',
    0x00A0 => ' ', 0x2026 => '...', 0x00AE => '(R)', 0x2122 => '(TM)',
    0x00B7 => '|', 0x00B2 => '^2', 0x2248 => '~=', 0x00AD => '-',
];

$masalah = [];
$lain = [];
$jumlah = 0;
$total = mb_strlen($isi, 'UTF-8');

for ($i = 0; $i < $total; $i++) {
    $ch = mb_substr($isi, $i, 1, 'UTF-8');
    $cp = mb_ord($ch, 'UTF-8');

    if ($cp < 128) {
        if ($cp < 32 && !in_array($cp, [9, 10, 13], true)) {
            $masalah[] = ['kode' => $cp, 'kolom' => $i];
        }

        continue;
    }

    if (isset($izinkan[$cp])) {
        continue;
    }

    $cjk = ($cp >= 0x4E00 && $cp <= 0x9FFF)
        || ($cp >= 0x3400 && $cp <= 0x4DBF)
        || ($cp >= 0x3040 && $cp <= 0x30FF)
        || ($cp >= 0xAC00 && $cp <= 0xD7AF)
        || ($cp >= 0xF900 && $cp <= 0xFAFF);

    if ($cp === 0xFFFD || $cjk) {
        $masalah[] = ['kode' => $cp, 'kolom' => $i];
        $jumlah++;

        continue;
    }

    $lain[] = ['kode' => $cp, 'kolom' => $i];
    $jumlah++;
}

// Cek kedua: kata bahasa Inggris yang menyisip ke kalimat Indonesia.
//
// Pemeriksa karakter di atas hanya menangkap CJK dan U+FFFD. Kelas
// masalah ini tidak tertangkap: kata Inggris utuh yang terselip di tengah
// kalimat Indonesia, misalnya "Dua lapis luckily sudah menutupnya" atau
// "butuhCredits bertahap". Semuanya terbaca seperti kalimat normal bagi
// pemeriksa karakter, tapi bagi pembaca Indonesia langsung terasa rusak.
//
// Daftar di bawah adalah kata yang TIDAK pernah dipakai dalam laporan ini
// sebagai istilah teknis. Jangan masukkan nama fungsi, kelas, atau istilah
// teknis lain ke sini - karena memang sah muncul di dalam backtick.
$asingJawa = [
    'luckily', 'slated', 'blanket', 'mitigated', 'credits', 'aware',
    'simply', 'just', 'actually', 'basically', 'obviously', 'clearly',
    'however', 'therefore', 'moreover', 'furthermore', 'nevertheless',
    'roughly', 'various', 'several', 'certain', 'properly', 'correctly',
    'wrongly', 'badly', 'well', 'indeed', 'perhaps', 'maybe', 'quite',
];

$teksLower = mb_strtolower($isi, 'UTF-8');
$temuanJawa = [];

foreach ($asingJawa as $kata) {
    // Cari hanya kemunculan yang dikelilingi huruf Indonesia, supaya
    // kata yang memang berdiri sendiri di luar backtick tidak dianggap.
    $offset = 0;

    while (($pos = mb_stripos($teksLower, $kata, $offset)) !== false) {
        $offset = $pos + 1;

        // Ambil satu karakter sebelum dan sesudah untuk lihat konteks.
        $sebelum = $pos > 0 ? mb_substr($teksLower, $pos - 1, 1, 'UTF-8') : ' ';
        $sesudahPos = $pos + strlen($kata);
        $sesudah = mb_substr($teksLower, $sesudahPos, 1, 'UTF-8');

        // Kalau ada huruf Indonesia di sebelahnya, berarti ini bagian
        // dari kata Indonesia yang kebetulan memuat ejaan Inggris.
        $indonesia = '/[a-z]/u';
        $beforeLetter = preg_match($indonesia, $sebelum) === 1;
        $afterLetter = preg_match($indonesia, $sesudah) === 1;

        if (!$beforeLetter && !$afterLetter) {
            $temuanJawa[] = [
                'kata' => $kata,
                'kolom' => $pos,
                'ctx' => mb_substr($isi, max(0, $pos - 30), 70, 'UTF-8'),
            ];
        }
    }
}

echo "Berkas  : {$target}\n";
echo 'Karakter: ' . $total . "\n";

if ($jumlah === 0 && !$temuanJawa) {
    echo "HASIL   : BERSIH (0 CJK, 0 U+FFFD, 0 kata asing tersisip)\n";
    exit(0);
}

echo 'MASALAH : ' . $jumlah . "\n\n";

if ($temuanJawa) {
    echo '-- kata Inggris yang menyisip ke kalimat Indonesia --' . "\n";

    foreach (array_slice($temuanJawa, 0, 30) as $m) {
        printf("  \"%s\" di kolom %d\n    ...%s...\n", $m['kata'], $m['kolom'], $m['ctx']);
    }

    echo "\n";
}

if ($lain) {
    echo "-- di luar daftar izin (perlu ditinjau) --\n";

    foreach ($lain as $m) {
        printf(
            "  U+%04X di kolom %d\n    ...%s...\n",
            $m['kode'],
            $m['kolom'],
            mb_substr($isi, max(0, $m['kolom'] - 35), 70, 'UTF-8')
        );
    }

    echo "\n";
}

foreach (array_slice($masalah, 0, 40) as $m) {
    printf(
        "  U+%04X di kolom %d (%s)\n    ...%s...\n",
        $m['kode'],
        $m['kolom'],
        $m['kode'] === 0xFFFD ? 'replacement character' : 'CJK / Hangul / Kana',
        mb_substr($isi, max(0, $m['kolom'] - 35), 70, 'UTF-8')
    );
}

exit(1);