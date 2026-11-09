<?php

/**
 * Pekerja konkurensi - SATU proses OS.
 *
 * Berjalan di proses terpisah (bukan sekadar PHPUnit serial), persis seperti
 * dua kasir menekan tombol yang sama pada detik yang sama.
 *
 * Cara pakai: php tools/conc_pekerja.php <slug> <jumlah_peserta> <berkas_payload_json>
 */
$slug = $argv[1] ?? 'unknown';
$jumlahPeserta = (int) ($argv[2] ?? 2);

// Payload dibaca dari BERKAS, bukan argumen baris perintah. Di Windows,
// proc_open meneruskan perintah lewat cmd.exe yang tidak mengenal tanda kutip
// tunggal seperti Unix - payload JSON berisi spasi dan kutip jadi rusak dan
// semua peserta gagal diam-diam dengan "payload null".
$payload = json_decode(file_get_contents($argv[3] ?? ''), true) ?: [];
$data = json_decode(file_get_contents(__DIR__ . '/conc_data.json'), true);

$pdo = new PDO('mysql:host=127.0.0.1;dbname=percetakan_erp_conc', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$token = getmypid() . '-' . substr(md5(uniqid('', true)), 0, 6);

// --- Pagar Rendezvous ---------------------------------------------------
// Slot dibuat oleh driver SEBELUM peserta berjalan. Setiap peserta mengklaim
// satu slot dengan UPDATE atomik, lalu menunggu sampai semua slot terisi.
//
// Versi pertama memakai "insert baris sendiri lalu hapus saat dilepas":
// peserta yang keluar duluan menghapus barisnya, slot ikut hilang, dan
// peserta yang menyusul tidak akan pernah bisa lewat.
$slot = $pdo->prepare('UPDATE _conc_pagar SET token = ? WHERE slug = ? AND token IS NULL ORDER BY id LIMIT 1');
$slot->execute([$token, $slug]);

$batas = microtime(true) + 60;
while (true) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM _conc_pagar WHERE slug = ? AND token IS NOT NULL');
    $stmt->execute([$slug]);
    if ((int) $stmt->fetchColumn() >= $jumlahPeserta) {
        break;
    }
    if (microtime(true) > $batas) {
        echo json_encode(['token' => $token, 'status' => 'error', 'msg' => 'pagar tidak terpenuhi dalam 60 detik']);
        exit(0);
    }
    usleep(500);
}

$hasil = ['token' => $token, 'slug' => $slug, 'status' => 'ok', 'http' => null, 'msg' => ''];

/**
 * Request HTTP sungguhan dengan cookie jar sendiri.
 *
 * Body dikembalikan PENUH, bukan dipotong. Token CSRF ada ratusan karakter di
 * dalam halaman login (header, style, script), jadi body yang dipotong 500
 * byte tidak pernah memuatnya.
 */
function http(string $url, string $jar, ?string $csrf = null, ?array $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => $body !== null,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => false,
    ]);if ($body !== null) {
        if ($csrf !== null) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-CSRF-TOKEN: ' . $csrf]);
            // Token dikirim dua kali: header dan field form. Header saja
            // menghasilkan HTTP 419 (token mismatch) pada sebagian konfigurasi
            // karena yang dibaca verifier adalah session token, bukan nilai
            // meta tag yang diambil ulang.
            if (!array_key_exists('_token', $body)) {
                $body['_token'] = $csrf;
            }
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
    }
    $out = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['http' => $http, 'body' => (string) $out];
}

/** Ambil token CSRF dari halaman yang memuat form. */
function ambilCsrf(string $url, string $jar): ?string
{
    $res = http($url, $jar);
    if (preg_match('/name="_token"\s+value="([^"]+)"/', $res['body'], $m)) {
        return $m[1];
    }
    if (preg_match('/"csrfToken"\s*:\s*"([^"]+)"/', $res['body'], $m)) {
        return $m[1];
    }

    return null;
}

$base = $payload['base'] ?? '';
$jar = sys_get_temp_dir() . '/conc_jar_' . $token . '.txt';

try {
    // Setiap pekerja punya sesi login sendiri: ini dua kasir di dua terminal
    // berbeda, bukan satu sesi dipakai bersamaan. Session bersama justru
    // menambah kebocoran (satu file session ditulis dua proses) yang tidak ada
    // di dunia nyata.
    $csrf = ambilCsrf($base . '/masuk', $jar);
    if ($csrf === null) {
        $hasil['status'] = 'error';
        $hasil['msg'] = 'token CSRF halaman masuk tidak ditemukan';
        echo json_encode($hasil);
        exit(0);
    }

    // POST login ada di /login (nama rutenya), BUKAN /masuk (yang cuma
    // halaman GET-nya). Menembak /masuk dengan POST mengembalikan 405.
    $login = http($base . '/login', $jar, $csrf, [
        'username' => $payload['username'],
        'password' => $payload['password'],
        '_token' => $csrf,
    ]);
    if (!in_array($login['http'], [200, 302], true)) {
        $hasil['status'] = 'error';
        $hasil['msg'] = 'login gagal (HTTP ' . $login['http'] . ')';
        echo json_encode($hasil);
        exit(0);
    }

    // Setelah login, token CSRF yang berlaku ada di halaman berikutnya.
    
    // Token diambil dari dashboard, bukan dari halaman kasir: kalau role uji
    // kebetulan tidak punya izin menu tertentu, halaman itu membalas 403 dan
    // tidak memuat form CSRF sama sekali.
    $csrf2 = ambilCsrf($base . '/dashboard', $jar) ?? $csrf;

    switch ($slug) {
        case 'pembayaran':
            // Dua kasir membayar nota yang sama pada saat yang sama.
            // Invariant 2: satu pembayaran tidak boleh tercatat dua kali.
            $hasil = array_merge($hasil, http(
                $base . '/kasir/proses-bayar',
                $jar,
                $csrf2,
                [
                    'id_transaksi' => $data['nota_pembayaran'],
                    'tipe_bayar' => 'lunas',
                    'metode_pembayaran' => 'Tunai',
                    'jumlah_bayar' => '100000',
                    'diskon' => '0',
                    'total_akhir' => '100000',
                    'items' => [],
                ]
            ));
            break;

        case 'deposit':
            // Dua kasir memakai saldo deposit yang sama persis.
            // Invariant 3: deposit tidak boleh terpakai melebihi saldo.
            $hasil = array_merge($hasil, http(
                $base . '/kasir/proses-bayar',
                $jar,
                $csrf2,
                [
                    'id_transaksi' => $data['nota_deposit'],
                    'tipe_bayar' => 'dp',
                    'metode_pembayaran' => 'Deposit',
                    'jumlah_bayar' => '100000',
                    'diskon' => '0',
                    'total_akhir' => '100000',
                    'items' => [],
                ]
            ));
            break;

        case 'closing':
            // Dua kasir menutup sesi kas yang sama.
            // Invariant 5: satu sesi kas tidak boleh closing dua kali.
            $hasil = array_merge($hasil, http(
                $base . '/kasir/closing-simpan',
                $jar,
                $csrf2,
                
                [
                    'id_kas' => $data['kas_id'],
                    'saldo_fisik' => '500000',
                    'keterangan' => 'uji konkurensi - saldo pas',
                    // Nilai harus persis 'Rolling' atau 'Tidak Rolling'.
                    // Kirim yang lain membuat validasi gagal dan request
                    // sekadar balik ke halaman sebelumnya dengan HTTP 302 -
                    // yang terbaca "sukses" padahal tidak ada yang terjadi.
                    'status_rolling' => 'Rolling',
                ]
            ));
            break;

        case 'terima_transaksi':
            // Dua operator menerima nota yang sama dari antrean.
            $hasil = array_merge($hasil, http(
                $base . '/kasir/terima/' . $data['nota_pembayaran'],
                $jar,
                $csrf2,
                []
            ));
            break;

        case 'noop':
            $hasil = array_merge($hasil, ['http' => 200]);
            break;
    }
} catch (Throwable $e) {
    $hasil['status'] = 'error';
    $hasil['msg'] = get_class($e) . ': ' . $e->getMessage();
}

echo json_encode($hasil);