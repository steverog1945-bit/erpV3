<?php
/**
 * Penjaga karakter asing.
 *
 * Berkas sumber aplikasi (blade, css, js, php) harus bebas dari huruf
 * NON-LATIN. Jalankan setelah mengubah tampilan atau teks apa pun. Kalau ada
 * temuan, skrip ini berhenti dengan kode keluar 1 supaya tidak lolos diam-diam.
 *
 * Cakupannya DIBESARKAN pada 2026-10-17. Versi pertama hanya mencari
 * CJK/kana/hangul, sehingga huruf Cyrillic yang terselip di tengah kalimat
 * Indonesia di resources/views/penjualan/create.blade.php lolos tanpa
 * terdeteksi. Padahal alat ini dipakai menjaga laporan juga, dan laporan yang
 * memuat satu karakter aneh tidak terbaca sama sekali.
 */
$pola = '/['
    . '\x{0400}-\x{04ff}'    // Cyrillic
    . '\x{0370}-\x{03ff}'    // Greek
    . '\x{0590}-\x{05ff}'    // Hebrew
    . '\x{0600}-\x{06ff}'    // Arabic
    . '\x{0750}-\x{077f}'    // Arabic Supplement
    . '\x{0900}-\x{097f}'    // Devanagari
    . '\x{0e00}-\x{0e7f}'    // Thai
    . '\x{4e00}-\x{9fff}'    // CJK Unified Ideographs
    . '\x{3040}-\x{30ff}'    // Hiragana dan Katakana
    . '\x{31f0}-\x{31ff}'    // Katakana Extension
    . '\x{ac00}-\x{d7af}'    // Hangul Syllables
    . '\x{ff01}-\x{ff60}'    // Fullwidth ASCII
    . '\x{20000}-\x{2a6df}'  // CJK Extension B
    . ']/u';

/**
 * Izin khusus, sengaja dan sempit: simbol matematika yang sah lazim dipakai
 * dan tetap terbaca.
 *
 * Sigma dipakai pada kalimat uji stok "stok awal + Sigma pergerakan = stok
 * akhir" untuk menyatakan penjumlahan (tests/Feature/StokIntegritasAlurTest).
 * Menghapus simbol itu hanya agar pemeriksaan ini hijau akan membuat
 * kalimatnya salah secara matematika, bukan membuatnya lebih bersih. Karena
 * itu pengecualian ini ditulis eksplisit, bukan disembunyikan di dalam pola
 * yang longgar.
 *
 * Sigma sendiri berada di blok Greek yang juga dipindai, jadi satu karakter
 * bisa masuk lewat satu pintu dan ditolak lewat pintu lain. Daftar ini
 * menolak pemakaiannya sebagai TEKS, bukan pemakaiannya sebagai simbol.
 */
$simbolMatematika = [
    "\u{03a3}", // Sigma, penjumlahan
    "\u{221a}", // akar
    "\u{00b1}", // plus minus
    "\u{00d7}", // kali
    "\u{2264}", // kurang sama dengan
    "\u{2265}", // lebih sama dengan
    "\u{221e}", // tak hingga
];

/**
 * Buang simbol yang memang diizinkan, lalu periksa sisa baris.
 *
 * Nilai balik berupa nama blok huruf yang terdeteksi, atau string kosong
 * kalau bersih. Mengembalikan nama blok (bukan hanya true/false) supaya
 * pesan kegagalan bisa menyebutkan huruf apa yang tidak sengaja ikut masuk,
 * bukan hanya menyatakan ada karakter asing.
 */
function periksaBaris(string $baris, string $pola, array $simbol): string
{
    $sisa = $baris;
    foreach ($simbol as $s) {
        $sisa = str_replace($s, '', $sisa);
    }

    if (! preg_match($pola, $sisa)) {
        return '';
    }

    // Sebutkan blok hurufnya supaya penyebabnya jelas saat dibaca.
    $blok = [
        '0400-04ff' => 'Cyrillic',
        '0370-03ff' => 'Greek',
        '0590-05ff' => 'Hebrew',
        '0600-06ff' => 'Arabic',
        '0900-097f' => 'Devanagari',
        '0e00-0e7f' => 'Thai',
        '4e00-9fff' => 'CJK',
        '3040-30ff' => 'kana',
        'ac00-d7af' => 'Hangul',
        'ff01-ff60' => 'fullwidth',
    ];

    foreach ($blok as $rentang => $nama) {
        if (preg_match('/[\x{' . $rentang . '}]/u', $sisa)) {
            return $nama;
        }
    }

    return 'non-Latin';
}

// Berkas .md punya pemeriksa sendiri (tools/cek-md-karakter-asing.php)
// karena daftar izin karakternya berbeda: tanda baca Indonesia dan m2 ada
// di laporan tapi tidak relevan untuk kode.
//
// database/migrations dan tests ikut dipindai sejak 2026-11-02: keduanya
// otomatis lepas dari daftar awal, padahal hasil audit menulis komentar
// berbahasa Indonesia di kedua tempat itu.
$direktori = [
    'resources/views',
    'resources/css',
    'resources/js',
    'app',
    'routes',
    'config',
    'database/migrations',
    'tests',
];
$suffix = ['php', 'js', 'css'];

$file = [];
foreach ($direktori as $d) {
    if (! is_dir($d)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d));
    foreach ($it as $f) {
        if ($f->isFile() && in_array(strtolower($f->getExtension()), $suffix, true)) {
            $file[] = $f->getPathname();
        }
    }
}

$temuan = [];
foreach ($file as $path) {
    $isi = file_get_contents($path);
    if ($isi === false || ! preg_match($pola, $isi)) {
        continue;
    }
    foreach (preg_split('/\R/', $isi) as $no => $baris) {
        $blok = periksaBaris($baris, $pola, $simbolMatematika);
        if ($blok === '') {
            continue;
        }
        $potongan = trim($baris);
        if (strlen($potongan) > 90) {
            $potongan = substr($potongan, 0, 90);
        }
        $temuan[] = $path . ' baris ' . ($no + 1) . ' [' . $blok . ']: ' . $potongan;
    }
}

echo 'Berkas diperiksa: ' . count($file) . PHP_EOL;

if ($temuan) {
    echo 'GAGAL - ditemukan ' . count($temuan) . ' baris:' . PHP_EOL;
    foreach (array_slice($temuan, 0, 15) as $t) {
        echo '  ' . $t . PHP_EOL;
    }
    exit(1);
}

echo 'BERSIH - tidak ada karakter non-Latin.' . PHP_EOL;
exit(0);