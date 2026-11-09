<?php
// Proses anak serbaguna untuk uji paralel: satu permintaan POST lewat kernel HTTP, di proses PHP tersendiri.
// Dipanggil: php tests/paralel/permintaan.php <id_user> <mulai_pada (microtime)> <base64(json {uri, data})>
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (!str_contains((string) config('database.connections.mysql.database'), 'testing')) {
    fwrite(STDERR, "BUKAN database uji\n");
    exit(2);
}

[, $idUser, $mulai, $muatan] = $argv;
$muatan = json_decode(base64_decode($muatan), true);
$app['auth']->loginUsingId((int) $idUser);
Illuminate\Support\Facades\DB::select('select 1'); // koneksi sudah terbuka sebelum aba-aba

while (microtime(true) < (float) $mulai) {
    usleep(200);
}

$request = Illuminate\Http\Request::create($muatan['uri'], $muatan['metode'] ?? 'POST', $muatan['data']);
if (!empty($muatan['json'])) {
    $request->headers->set('Accept', 'application/json');
}
$respons = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);
$sesi = $request->hasSession() ? $request->session() : null;
echo json_encode([
    'status' => $respons->getStatusCode(),
    'ok' => $respons->getStatusCode() < 400 && (!$sesi || !$sesi->get('error')) && (!$sesi || !$sesi->get('errors')),
    'error' => $sesi ? $sesi->get('error') : null,
]);
