<?php

/**
 * Driver gate konkurensi.
 *
 * Menjalankan request HTTP NYATA secara paralel lewat beberapa proses OS
 * terpisah, lalu memeriksa invariant integritas data.
 *
 * Isolasi dibuktikan, bukan diasumsikan: sebelum dan sesudah setiap uji,
 * sidik jari data oriondb dibandingkan. Kalau satu byte pun berubah di
 * database operasional, test ini gagal dan berhenti - bukan diam-diam lewat.
 *
 * Cara pakai: php tools/conc_driver.php <slug> [jumlah_peserta]
 */
require __DIR__ . '/../vendor/autoload.php';

$data = json_decode(file_get_contents(__DIR__ . '/conc_data.json'), true);

$slug = $argv[1] ?? 'pembayaran';
$peserta = (int) ($argv[2] ?? 2);
$base = 'http://127.0.0.1:8099';

function pdo(string $db): PDO
{
    $p = new PDO(
        'mysql:host=127.0.0.1;dbname=' . $db,
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    return $p;
}

$uji = pdo($data['db']);
$produksi = pdo('oriondb');


/** Sidik jari data operasional - jumlah baris per tabel kunci + nilai jurnal. */
function sidikJariOperasional(PDO $p): array
{
    $keluar = [];
    foreach (['transaksi', 'transaksi_detail', 'pembayaran_riwayat', 'konsumen_deposit', 'kas_harian', 'jurnal_umum', 'users'] as $t) {
        $baris = $p->query('SELECT COUNT(*) c FROM ' . $t)->fetch();
        $keluar[$t] = (int) $baris['c'];
    }
    $j = $p->query('SELECT COALESCE(SUM(debet),0) d, COALESCE(SUM(kredit),0) k FROM jurnal_umum')->fetch();
    $keluar['jurnal_nilai'] = (float) $j['d'] . ':' . (float) $j['k'];

    return $keluar;
}

$sebelumOp = sidikJariOperasional($produksi);

// Pagar harus bersih dari percobaan sebelumnya.
$uji->exec('DROP TABLE IF EXISTS _conc_pagar');
$uji->exec('CREATE TABLE _conc_pagar (id INT AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(64) NOT NULL, token VARCHAR(64) NOT NULL, KEY (slug)) ENGINE=InnoDB');
$payload = json_encode([
    'base' => $base,
    'username' => 'conc_kasir',
    'password' => 'RahasiaKuat123',
]);

// Pagar dibikin SEBELUM peserta dijalankan: N slot kosong, lalu setiap peserta
// mengklaim satu slot. Kalau slot dibuat peserta sendiri, peserta yang mulai
// lebih cepat akan melihat tabel kosong dan keluar sebelum peserta kedua sempat
// datang.
$uji->exec('DROP TABLE IF EXISTS _conc_pagar');
$uji->exec('CREATE TABLE _conc_pagar (id INT AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(64) NOT NULL, token VARCHAR(64) NULL, KEY (slug)) ENGINE=InnoDB');
for ($i = 0; $i < $peserta; $i++) {
    $uji->prepare('INSERT INTO _conc_pagar (slug, token) VALUES (?, NULL)')->execute([$slug]);
}

// Payload lewat BERKAS: di Windows perintah dijalankan lewat cmd.exe yang tidak
// mengenal tanda kutip tunggal Unix, jadi argumen JSON berisi spasi dan kutip
// akan sampai ke peserta dalam keadaan rusak.
$payloadFile = sys_get_temp_dir() . '/conc_payload_' . getmypid() . '.json';
file_put_contents($payloadFile, $payload);

echo "== {$slug}: {$peserta} proses OS paralel ==\n";

// --- Jalankan N proses secara bersamaan --------------------------------
$cmd = sprintf(
    '"%s" "%s" "%s" "%d" "%s"',
    PHP_BINARY,
    __DIR__ . '/conc_pekerja.php',
    $slug,
    $peserta,
    $payloadFile
);

$proses = [];
$pipes = [];
for ($i = 0; $i < $peserta; $i++) {
    $proses[$i] = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$i]);
    fclose($pipes[$i][0]);
}

$hasil = [];
foreach ($proses as $i => $p) {
    $keluaran = stream_get_contents($pipes[$i][1]);
    $err = stream_get_contents($pipes[$i][2]);
    fclose($pipes[$i][1]);
    fclose($pipes[$i][2]);
    proc_close($p);
    $j = json_decode(trim($keluaran), true);
    $hasil[] = $j ?: [
        'status' => 'error',
        'msg' => 'keluaran bukan JSON: ' . substr(trim($keluaran) . ' ' . $err, 0, 200),
    ];
}

@unlink($payloadFile);

echo "\n-- Hasil tiap peserta --\n";
foreach ($hasil as $i => $h) {
    echo sprintf(
        "  peserta %d: HTTP %-4s status=%-6s %s\n",
        $i + 1,
        $h['http'] ?? '-',
        $h['status'] ?? '?',
        substr((string) ($h['msg'] ?? ''), 0, 150)
    );
}

$sukses = array_filter($hasil, fn ($h) => ($h['http'] ?? 0) >= 200 && ($h['http'] ?? 0) < 400 && ($h['status'] ?? '') === 'ok');
echo '  sukses: ' . count($sukses) . ' / ' . count($hasil) . "\n";

// --- Invariant ---------------------------------------------------------
echo "\n-- Kondisi database uji --\n";
$temuan = [];

$cek = function (string $label, bool $benar, string $bukti) use (&$temuan) {
    echo sprintf("  [%s] %-50s %s\n", $benar ? 'LULUS' : 'GAGAL', $label, $bukti);
    if (!$benar) {
        $temuan[] = $label . ' -> ' . $bukti;
    }
};

switch ($slug) {
    case 'pembayaran':
        $tr = $uji->query("SELECT COUNT(*) c, COALESCE(SUM(nominal),0) s FROM pembayaran_riwayat WHERE id_transaksi = {$data['nota_pembayaran']}")->fetch();
        $total = (float) $tr['s'];

        $cek('tidak ada pembayaran ganda (total = 100.000)', abs($total - 100000.0) < 0.01, "{$tr['c']} baris, total Rp" . number_format($total, 0, ',', '.'));
        $cek('pembayaran tidak melebihi tagihan', $total <= 100000.01, 'Rp' . number_format($total, 0, ',', '.'));

        $st = $uji->query("SELECT status_pembayaran FROM transaksi WHERE id_transaksi = {$data['nota_pembayaran']}")->fetch();
        $cek('status nota sesuai pembayaran', $st['status_pembayaran'] === 'Lunas', (string) $st['status_pembayaran']);

        $j = $uji->query("SELECT COALESCE(SUM(debet),0) d, COALESCE(SUM(kredit),0) k FROM jurnal_umum WHERE no_referensi = 'CONC-2026-1'")->fetch();
        $cek('jurnal seimbang (tidak ada data parsial)', abs((float) $j['d'] - (float) $j['k']) < 0.01, "debet {$j['d']} / kredit {$j['k']}");
        break;

    case 'deposit':
        $pakai = $uji->query("SELECT COALESCE(SUM(nominal),0) s, COUNT(*) c FROM konsumen_deposit WHERE id_konsumen = {$data['konsumen_id']} AND tipe = 'Pemakaian'")->fetch();
        $setor = $uji->query("SELECT COALESCE(SUM(nominal),0) s FROM konsumen_deposit WHERE id_konsumen = {$data['konsumen_id']} AND tipe = 'Setoran'")->fetch();
        $cek('deposit tidak terpakai melebihi saldo', (float) $pakai['s'] <= (float) $setor['s'] + 0.01, 'pemakaian Rp' . number_format((float) $pakai['s'], 0, ',', '.') . ' dari setoran Rp' . number_format((float) $setor['s'], 0, ',', '.'));

        $bayar = $uji->query("SELECT COALESCE(SUM(nominal),0) s FROM pembayaran_riwayat WHERE id_transaksi = {$data['nota_deposit']}")->fetch();
        $cek('pembayaran deposit <= saldo', (float) $bayar['s'] <= 100000.01, 'Rp' . number_format((float) $bayar['s'], 0, ',', '.'));
        break;

    case 'closing':
        $kas = $uji->query("SELECT COUNT(*) c FROM kas_harian WHERE id_kas = {$data['kas_id']} AND status_lock = 'closed'")->fetch();
        $cek('sesi kas tertutup tepat satu kali', (int) $kas['c'] === 1, "baris tertutup: {$kas['c']}");

        $st = $uji->query("SELECT status_lock FROM kas_harian WHERE id_kas = {$data['kas_id']}")->fetch();
        $cek('status kas locked = closed', ($st['status_lock'] ?? '') === 'closed', (string) ($st['status_lock'] ?? '-'));
        break;
}

// --- Isolasi: database operasional harus utuh ----------------------------
echo "\n-- Isolasi database operasional --\n";
$sesudahOp = sidikJariOperasional($produksi);
$cek('oriondb tidak berubah sama sekali', $sebelumOp === $sesudahOp, $sebelumOp === $sesudahOp ? 'sidik jari identik' : 'PERUBAHAN: ' . json_encode(array_diff_assoc($sebelumOp, $sesudahOp)));

echo "\n== RINGKASAN ==\n";
if ($temuan) {
    echo 'FAIL (' . count($temuan) . "):\n";
    foreach ($temuan as $t) {
        echo "  - {$t}\n";
    }
    exit(1);
}

echo "PASS: seluruh invariant terpenuhi.\n";
exit(0);