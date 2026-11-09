<?php

/**
 * Dump database MySQL/MariaDB ke oriondb.sql lalu commit ke git.
 *
 * Pemakaian (dari project root):
 *   php scripts/db_dump_commit.php                 # dump + commit (kalau ada perubahan)
 *   php scripts/db_dump_commit.php --push          # dump + commit + push origin main
 *   php scripts/db_dump_commit.php "pesan commit"  # dump + commit dengan pesan sendiri
 *
 * Sumber koneksi dibaca dari .env (DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/
 * DB_PASSWORD) supaya tidak ada kredensial hard-code di repo. Binary
 * mysqldump dicari di PATH; kalau tidak ada (XAMPP Windows biasanya tidak
 * masuk PATH), dicoba path standar C:\xampp\mysql\bin\mysqldump.exe, atau
 * paksa lewat env MYSQLDUMP_BIN.
 *
 * Dump deterministik: --skip-dump-date menghapus timestamp "Dump completed"
 * sehingga database yang TIDAK berubah menghasilkan file identik - skrip
 * ini mendeteksinya dan TIDAK membuat commit kosong.
 */

$root = dirname(__DIR__);
chdir($root);

if (!is_file('.env')) {
    fwrite(STDERR, "ERROR: .env tidak ditemukan di {$root}\n");
    exit(1);
}

// ---- 1. Baca kredensial dari .env (tanpa menampilkan nilainya) ----
$env = [];
foreach (file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }
    [$key, $val] = array_pad(explode('=', $line, 2), 2, '');
    $env[trim($key)] = trim($val, " \t\"'");
}

$koneksi = $env['DB_CONNECTION'] ?? 'mysql';
if ($koneksi !== 'mysql' && $koneksi !== 'mariadb') {
    fwrite(STDERR, "ERROR: Skrip ini hanya untuk DB_CONNECTION=mysql/mariadb, .env Anda: {$koneksi}\n");
    exit(1);
}

$dbHost = $env['DB_HOST'] ?? '127.0.0.1';
$dbPort = $env['DB_PORT'] ?? '3306';
$dbName = $env['DB_DATABASE'] ?? 'oriondb';
$dbUser = $env['DB_USERNAME'] ?? 'root';
$dbPass = $env['DB_PASSWORD'] ?? '';

echo "DB  : {$dbName} @ {$dbHost}:{$dbPort}\n";

// ---- 2. Cari mysqldump ----
// Pencarian binary DIBEDA per OS: 'where ... 2>nul' hanya sah di cmd.exe
// Windows; di Linux '2>nul' malah membuat file sampah bernama "nul".
$cariCmd = PHP_OS_FAMILY === 'Windows' ? 'where mysqldump 2>nul' : 'command -v mysqldump 2>/dev/null';
$mysqldump = trim((string) (shell_exec($cariCmd) ?? ''));
if (!str_contains($mysqldump, 'mysqldump')) {
    $mysqldump = '';
}
// Paksa lewat env MYSQLDUMP_BIN (path lengkap) kalau tidak ada di PATH & bukan lokasi standar.
if ($mysqldump === '') {
    $viaEnv = (string) getenv('MYSQLDUMP_BIN');
    if ($viaEnv !== '' && is_file($viaEnv)) {
        $mysqldump = $viaEnv;
    }
}
if ($mysqldump === '') {
    foreach (['C:\\xampp\\mysql\\bin\\mysqldump.exe', '/xampp/mysql/bin/mysqldump', '/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/opt/lampp/bin/mysqldump'] as $kandidat) {
        if (is_executable($kandidat) || (PHP_OS_FAMILY === 'Windows' && is_file($kandidat))) {
            $mysqldump = $kandidat;
            break;
        }
    }
}
if ($mysqldump === '') {
    fwrite(STDERR, "ERROR: mysqldump tidak ditemukan. Set env MYSQLDUMP_BIN ke path lengkap, atau masukkan ke PATH.\n");
    exit(1);
}
echo "Tool: {$mysqldump}\n";

// ---- 3. Dump ke file sementara dulu (atomik: gagal = oriondb.sql lama utuh) ----
$target = $root . DIRECTORY_SEPARATOR . 'oriondb.sql';
$tmp = $target . '.tmp';

// Password lewat env MYSQL_PWD (bukan argv) supaya tidak bocor di process list
// dan bebas masalah escaping karakter spesial.
putenv('MYSQL_PWD=' . $dbPass);

$esc = function (string $s): string {
    return PHP_OS_FAMILY === 'Windows' ? '"' . addslashes($s) . '"' : escapeshellarg($s);
};

// Redirect STDOUT saja - STDERR TIDAK di-merge (2>&1) supaya warning
// mysqldump tidak pernah masuk ke dalam file SQL; kalau gagal, pesan error
// mysqldump langsung tampil di console dan exit code menangkapnya.
$cmd = sprintf(
    '%s --host=%s --port=%s --user=%s %s %s > %s',
    $esc($mysqldump),
    $esc($dbHost),
    $esc($dbPort),
    $esc($dbUser),
    // --single-transaction: konsisten tanpa mengunci tabel (InnoDB/MariaDB)
    // --skip-dump-date: file identik kalau data tidak berubah (tidak ada commit kosong)
    '--single-transaction --quick --routines --triggers --events --default-character-set=utf8mb4 --skip-dump-date',
    $esc($dbName),
    $esc($tmp)
);

exec($cmd, $out, $kode);
unset($out);

if ($kode !== 0 || !is_file($tmp) || filesize($tmp) === 0) {
    $alasan = is_file($tmp) ? substr((string) file_get_contents($tmp), 0, 300) : '(tidak ada output)';
    @unlink($tmp);
    fwrite(STDERR, "ERROR: mysqldump gagal (exit {$kode}).\n{$alasan}\n");
    exit(1);
}
if (filesize($tmp) < 500 || stripos((string) file_get_contents($tmp), 'CREATE TABLE') === false) {
    @unlink($tmp);
    fwrite(STDERR, "ERROR: hasil dump tidak wajar (terlalu kecil / tanpa CREATE TABLE). Cek nama database.\n");
    exit(1);
}

$mb = round(filesize($tmp) / 1048576, 2);
rename($tmp, $target);
echo "Dump: oriondb.sql ({$mb} MB) - OK\n";

// ---- 4. Commit hanya kalau isinya benar-benar berubah ----
// Redirect stderr DIBEDA per OS: di Windows Composer menjalankan skrip PHP
// lewat cmd.exe (bukan bash MINGW64), jadi '2>/dev/null' gagal dengan
// "The system cannot find the path specified" dan exit code jadi 1 -
// padahal repo valid. Sintaks cmd.exe adalah '2>nul'.
$redirStderr = PHP_OS_FAMILY === 'Windows' ? '2>nul' : '2>/dev/null';
exec('git rev-parse --is-inside-work-tree ' . $redirStderr, $g, $gKode);
if ($gKode !== 0) {
    fwrite(STDERR, "ERROR: bukan repository git (jalankan dari project root).\n");
    exit(1);
}

exec('git add oriondb.sql && git diff --cached --quiet oriondb.sql', $o, $beda);
if ($beda === 0) {
    echo "Tidak ada perubahan database sejak dump terakhir - tidak perlu commit.\n";
    exit(0);
}

$pesan = $argv[1] ?? null;
if (!$pesan || str_starts_with($pesan, '--')) {
    $pesan = 'chore(db): update dump oriondb.sql ' . date('Y-m-d H:i');
}

// Pesan commit dikirim lewat file (-F) supaya aman dari tanda kutip/karakter
// khusus di shell (beda aturan escaping antara cmd.exe Windows dan bash).
$filePesan = $root . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR . 'DB_COMMIT_MSG.tmp';
file_put_contents($filePesan, $pesan);

$push = in_array('--push', $argv, true) ? ' && git push origin main' : '';
// Path pesan di-quote manual di Windows - escapeshellarg() menghasilkan
// kutip tunggal yang tidak dikenali cmd.exe. Path ini polos (di dalam .git)
// jadi kutip ganda aman.
$pathPesan = PHP_OS_FAMILY === 'Windows' ? '"' . $filePesan . '"' : escapeshellarg($filePesan);
passthru('git commit -F ' . $pathPesan . $push, $kodeGit);
@unlink($filePesan);
if ($kodeGit !== 0) {
    fwrite(STDERR, "ERROR: git commit/push gagal (exit {$kodeGit}). Dump SUDAH tersimpan di oriondb.sql - commit manual bila perlu.\n");
    exit(1);
}

echo "Selesai: dump + commit" . ($push ? " + push" : "") . ".\n";
