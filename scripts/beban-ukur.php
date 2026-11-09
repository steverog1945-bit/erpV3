<?php
// Audit query berat pada data besar (database BEBAN, hanya baca): setiap halaman GET dan endpoint AJAX
// dijalankan sebagai Administrator; dicatat waktu total, jumlah query, query terlama, dan EXPLAIN-nya.
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

if (DB::getDatabaseName() !== 'percetakan_erp_beban') { echo "BUKAN DB BEBAN\n"; return; }
$admin = App\Models\User::whereHas('role', fn ($q) => $q->where('nama_role', 'Administrator'))->first();

$isi = [
    'id' => DB::table('transaksi')->max('id_transaksi'), 'transaksi' => DB::table('transaksi')->max('id_transaksi'),
    'order' => DB::table('jersey_orders')->max('id'), 'konsumen' => DB::table('konsumen')->max('id_konsumen'),
    'id_konsumen' => DB::table('konsumen')->max('id_konsumen'), 'id_bahan' => DB::table('bahan_baku')->min('id_bahan'),
    'line' => DB::table('jersey_order_lines')->max('id'), 'qty' => 5, 'id_produk' => DB::table('produk')->min('id_produk'),
    'id_jasa' => DB::table('jasa')->min('id_jasa'), 'slug' => DB::table('posts')->value('slug') ?? 'x',
];
$hasil = [];
$lambat = [];
foreach (Route::getRoutes() as $r) {
    if (!in_array('GET', $r->methods()) || !in_array('auth', $r->gatherMiddleware())) continue;
    if (preg_match('#(backup|logout|storage|unduh|download)#', $r->uri())) continue;
    $uri = preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => $isi[$m[1]] ?? (DB::table('transaksi')->max('id_transaksi')), $r->uri());
    $uri = '/' . ltrim($uri, '/');
    DB::flushQueryLog(); DB::enableQueryLog();
    $t = microtime(true);
    try {
        auth()->setUser($admin);
        $req = Illuminate\Http\Request::create($uri, 'GET');
        $req->setLaravelSession(app('session.store'));
        $kode = app()->handle($req)->getStatusCode();
    } catch (Throwable $e) { $kode = 'ERR'; }
    $ms = round((microtime(true) - $t) * 1000);
    $log = DB::getQueryLog();
    usort($log, fn ($a, $b) => $b['time'] <=> $a['time']);
    $hasil[] = [$ms, $uri, $kode, count($log), round(array_sum(array_column($log, 'time'))), $log[0]['time'] ?? 0];
    foreach ($log as $q) {
        if ($q['time'] < 150) break;
        $kunci = preg_replace('/\s+/', ' ', $q['query']);
        if (!isset($lambat[$kunci]) || $lambat[$kunci]['ms'] < $q['time']) {
            $lambat[$kunci] = ['ms' => round($q['time']), 'uri' => $uri, 'b' => $q['bindings']];
        }
    }
}
usort($hasil, fn ($a, $b) => $b[0] <=> $a[0]);
echo "== 25 halaman terlambat (ms total | uri | status | jumlah query | ms di DB | query terlama ms)\n";
foreach (array_slice($hasil, 0, 25) as $h) echo implode(' | ', $h), "\n";
echo "== total halaman: ", count($hasil), ", di atas 1 detik: ", count(array_filter($hasil, fn ($h) => $h[0] > 1000)), "\n";
uasort($lambat, fn ($a, $b) => $b['ms'] <=> $a['ms']);
echo "== query >= 150ms: ", count($lambat), "\n";
foreach (array_slice($lambat, 0, 25, true) as $sql => $x) {
    echo "\n[", $x['ms'], "ms] ", $x['uri'], "\n  ", substr($sql, 0, 400), "\n";
    try {
        foreach (DB::select('EXPLAIN ' . $sql, $x['b']) as $e) {
            echo "   - ", $e->table, " type=", $e->type, " key=", $e->key ?? '-', " rows=", $e->rows, " extra=", $e->Extra ?? '', "\n";
        }
    } catch (Throwable $e) { echo "   (EXPLAIN gagal)\n"; }
}
