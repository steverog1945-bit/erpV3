<?php
// Proses anak untuk PembayaranParalelTest: satu pembayaran kasir lewat kernel HTTP, di proses PHP tersendiri.
// Dipanggil: php tests/paralel/bayar.php <id_transaksi> <id_user> <nominal> <submit_id> <mulai_pada (microtime)> [metode]
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (!str_contains((string) config('database.connections.mysql.database'), 'testing')) {
    fwrite(STDERR, "BUKAN database uji\n");
    exit(2);
}

[, $idTrx, $idUser, $nominal, $submitId, $mulai] = $argv;
$metode = $argv[6] ?? 'Tunai';
$app['auth']->loginUsingId((int) $idUser);
Illuminate\Support\Facades\DB::select('select 1'); // koneksi sudah terbuka sebelum aba-aba

// Semua proses menunggu aba-aba yang sama supaya benar-benar bertabrakan.
while (microtime(true) < (float) $mulai) {
    usleep(200);
}

$request = Illuminate\Http\Request::create('/kasir/proses-bayar', 'POST', [
    'id_transaksi' => (int) $idTrx, 'jumlah_bayar' => $nominal, 'metode_pembayaran' => $metode, 'submit_id' => $submitId,
]);
$respons = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);
$sesi = $request->hasSession() ? $request->session() : null;
echo json_encode([
    'status' => $respons->getStatusCode(),
    'ok' => $sesi ? (bool) $sesi->get('success') : false,
    'error' => $sesi ? $sesi->get('error') : null,
]);
