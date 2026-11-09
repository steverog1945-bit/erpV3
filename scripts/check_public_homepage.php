<?php
/**
 * CHECKPOINT 4D.2 — Health check homepage publik.
 *
 * Dipakai untuk monitoring manual / cron / uptime check SEBELUM flag dinyalakan
 * dan selama rollback. Tidak mengubah apa pun: hanya GET.
 *
 * Exit code:
 *   0 = sehat
 *   1 = tidak sehat (5xx, timeout, atau konten tak terduga)
 *
 * Pemakaian:
 *   php scripts/check_public_homepage.php [URL] [EXPECT]
 *     URL    : default http://localhost/grafikaorion
 *              CATATAN: di XAMPP lokal, DocumentRoot adalah C:/xampp/htdocs,
 *              jadi aplikasi berada di subfolder /grafikaorion - BUKAN root.
 *              Root http://localhost menyajikan halaman default XAMPP.
 *     EXPECT : 'existing' (default) atau 'builder'
 *
 * Contoh:
 *   php scripts/check_public_homepage.php http://localhost/grafikaorion existing
 *   php scripts/check_public_homepage.php http://localhost/grafikaorion builder
 */

$url = $argv[1] ?? 'http://localhost/grafikaorion';
$expect = $argv[2] ?? 'existing';

$timeout = 10;
$context = stream_context_create([
    'http' => [
        'method'        => 'GET',
        'timeout'       => $timeout,
        'ignore_errors' => true,
        'header'        => "User-Agent: orion-healthcheck/1.0\r\n",
    ],
    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
]);

$startedAt = microtime(true);
$body = @file_get_contents($url, false, $context);
$durationMs = (int) round((microtime(true) - $startedAt) * 1000);

// Status HTTP dari $http_response_header (PHP hanya mengisinya di scope atas).
$status = 0;
if (isset($http_response_header) && is_array($http_response_header)) {
    foreach ($http_response_header as $h) {
        if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m)) {
            $status = (int) $m[1];
        }
    }
}

$problems = [];

if ($body === false) {
    $problems[] = "Gagal menghubungi {$url} (timeout {$timeout}s atau host mati)";
    $status = 0;
} else {
    if ($status >= 500) {
        $problems[] = "Server error HTTP {$status}";
    } elseif ($status === 0) {
        $problems[] = 'Status HTTP tidak terbaca';
    } elseif ($status >= 400) {
        $problems[] = "HTTP {$status} (halaman tidak ditemukan / forbidden)";
    }

    // Deteksi mode halaman yang benar-benar dirender.
    $isBuilder = str_contains($body, 'landing-page-public')
        || str_contains($body, 'data-section-type');

    // Builder homepage MEWAKILIPublished content, punya marker preview bila bocor.
    if (str_contains($body, 'PREVIEW MODE')) {
        $problems[] = 'BOCOR: banner PREVIEW MODE muncul di halaman publik';
    }

    // Halaman login existing harus punya dialog/form masuk.
    $hasLoginDialog = str_contains($body, 'id="dialog-masuk"')
        || str_contains($body, 'data-buka-masuk');

    // Builder homepage (flag ON) tidak punya dialog, tapi WAJIB punya pintu
    // masuk staf - kalau hilang, staf terkunci. Ini regresi 4D.2 yang sudah
    // diperbaiki di 4E.1, jadi harus dijaga.
    $loginUrl = rtrim($url, '/') . '/masuk';
    $hasStaffEntry = str_contains($body, 'Masuk Karyawan')
        || str_contains($body, $loginUrl);

    if ($expect === 'existing' && $isBuilder) {
        $problems[] = 'FLAG OFF kok halaman builder? pastikan flag & cache config';
    }
    if ($expect === 'existing' && !$hasLoginDialog) {
        $problems[] = 'Homepage existing tanpa dialog/form masuk - staf tidak bisa login';
    }
    if ($expect === 'builder' && !$isBuilder) {
        $problems[] = 'FLAG ON tapi homepage builder tidak terender (kemungkinan fallback)';
    }
    if ($expect === 'builder' && !$hasStaffEntry) {
        $problems[] = 'Halaman builder tanpa pintu masuk staf ("Masuk Karyawan") - risiko lockout';
    }
}

// --- Cek endpoint login juga (Checkpoint 4E.1) ---
// route('login') = /masuk. Halaman INI harus selalu punya form, apa pun flag.
$loginUrl = rtrim($url, '/') . '/masuk';
$loginCtx = stream_context_create([
    'http' => [
        'method'        => 'GET',
        'timeout'       => $timeout,
        'ignore_errors' => true,
        'header'        => "User-Agent: orion-healthcheck/1.0\r\n",
    ],
    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
]);
$loginBody = @file_get_contents($loginUrl, false, $loginCtx);
$loginStatus = 0;
if (isset($http_response_header) && is_array($http_response_header)) {
    foreach ($http_response_header as $h) {
        if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m2)) {
            $loginStatus = (int) $m2[1];
        }
    }
}

if ($loginBody === false || $loginStatus !== 200) {
    $problems[] = "Endpoint login /masuk tidak bisa diakses (status {$loginStatus})";
} elseif (!str_contains($loginBody, 'name="password"')) {
    $problems[] = 'Endpoint /masuk tidak memuat form password - staf tidak bisa login';
} elseif (!str_contains($loginBody, 'name="_token"')) {
    $problems[] = 'Endpoint /masuk tidak memuat token CSRF';
}

echo "=========================================================\n";
echo " PUBLIC HOMEPAGE HEALTH CHECK\n";
echo "=========================================================\n";
echo "URL        : {$url}\n";
echo "Waktu      : " . date('Y-m-d H:i:s') . "\n";
echo "HTTP status: {$status}\n";
echo "Durasi     : {$durationMs} ms\n";
echo "Harapan    : {$expect}\n";
echo "Ukuran body: " . ($body === false ? 0 : strlen($body)) . " byte\n";
echo "Endpoint login: {$loginUrl} -> HTTP {$loginStatus}\n";
echo "---------------------------------------------------------\n";

if ($problems) {
    echo " HASIL: TIDAK SEHAT\n";
    foreach ($problems as $p) {
        echo "  - {$p}\n";
    }
    echo "---------------------------------------------------------\n";
    echo " TINDAKAN:\n";
    echo "   1. Cek log aplikasi untuk pesan 'Landing page resolver'.\n";
    echo "   2. Bila halaman rusak, ROLLBACK: set flag OFF + config:clear.\n";
    echo "=========================================================\n";
    exit(1);
}

echo " HASIL: SEHAT\n";
echo "=========================================================\n";
exit(0);