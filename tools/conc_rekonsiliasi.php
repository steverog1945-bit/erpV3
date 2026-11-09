<?php

/**
 * Rekonsiliasi keuangan oriondb - READ ONLY.
 *
 * Tidak mengubah apa pun. Hanya SELECT, supaya hasilnya bisa dipercaya
 * sebagai catatan_condition sebelum/sesudah perbaikan.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

if (DB::connection()->getDatabaseName() !== 'oriondb') {
    exit("Berhenti: rekonsiliasi hanya untuk oriondb\n");
}

$bulan = '2026-10';
$ok = 0;
$gagal = [];
$cek = function (string $label, bool $benar, string $bukti) use (&$ok, &$gagal) {
    echo sprintf("  [%s] %-46s %s\n", $benar ? 'LULUS' : 'GAGAL', $label, $bukti);
    $benar ? $ok++ : $gagal[] = $label . ' -> ' . $bukti;
};
$rp = fn ($n) => 'Rp' . number_format((float) $n, 0, ',', '.');

echo "=== 1. JURNAL ===\n";
$j = DB::table('jurnal_umum')->selectRaw('COALESCE(SUM(debet),0) d, COALESCE(SUM(kredit),0) k, COUNT(*) c')->first();
$cek('jurnalumum debit = kredit', abs($j->d - $j->k) < 0.01, "{$j->c} baris, D {$rp($j->d)} / K {$rp($j->k)}");

$abu = DB::table('jurnal_umum')->where(function ($q) {
    $q->where(function ($a) { $a->where('debet', '>', 0)->where('kredit', '>', 0); })
        ->orWhere(function ($a) { $a->where('debet', 0)->where('kredit', 0); });
})->count();
$cek('tidak ada baris jurnal yang debit DAN kredit', $abu === 0, "{$abu} baris");

$negatif = DB::table('jurnal_umum')->where('debet', '<', 0)->orWhere('kredit', '<', 0)->count();
$cek('tidak ada nilai jurnal negatif', $negatif === 0, "{$negatif} baris");

$tanpaAkun = DB::table('jurnal_umum')->whereNull('kode_akun')->orWhere('kode_akun', '')->count();
$cek('semua baris punya kode akun', $tanpaAkun === 0, "{$tanpaAkun} baris tanpa kode");



$tabelAkun = null;
foreach (['bag_akun', 'akun', 'daftar_akun', 'akuntansi_akun', 'coa'] as $kandidat) {
    if (DB::getSchemaBuilder()->hasTable($kandidat)) {
        $tabelAkun = $kandidat;
        break;
    }
}
if ($tabelAkun === null) {
    echo "  tabel daftar akun TIDAK DITEMUKAN - cek kode akun dilewati\n";
} else {
    $akun = DB::table($tabelAkun)->pluck('kode_akun')->flip();
    $orphan = DB::table('jurnal_umum')->get()->filter(fn ($r) => !isset($akun[$r->kode_akun]))->pluck('kode_akun')->unique();
    $cek('semua kode akun jurnal ada di daftar akun', $orphan->isEmpty(), $orphan->isEmpty() ? 'semua cocok' : $orphan->implode(', '));
}
$orphan = DB::table('jurnal_umum')->get()->filter(fn ($r) => !isset($akun[$r->kode_akun]))->pluck('kode_akun')->unique();
$cek('semua kode akun jurnal ada di daftar akun', $orphan->isEmpty(), $orphan->isEmpty() ? 'semua cocok' : $orphan->implode(', '));

echo "\n=== 2. TRANSAKSI <-> PEMBAYARAN ===\n";
$tr = DB::table('transaksi')->where('status_nota', '!=', 'Dibatalkan')->get();
$drift = [];
foreach ($tr as $t) {
    $terbayar = (float) DB::table('pembayaran_riwayat')->where('id_transaksi', $t->id_transaksi)->sum('nominal');
    $harapan = \App\Services\StatusPembayaran::dari((float) $t->total_bayar, $terbayar);
    if (\App\Services\StatusPembayaran::normalisasi($t->status_pembayaran) !== \App\Services\StatusPembayaran::normalisasi($harapan)) {
        $drift[] = "#{$t->id_transaksi} {$t->nomor_nota}: tersimpan {$t->status_pembayaran} / nyata {$harapan}";
    }
}
$cek('status pembayaran semua nota cocok dengan pembayaran', $drift === [], $drift === [] ? $tr->count() . ' nota' : implode(' | ', $drift));

$lebih = $tr->filter(function ($t) {
    return (float) DB::table('pembayaran_riwayat')->where('id_transaksi', $t->id_transaksi)->sum('nominal') > (float) $t->total_bayar + 0.01;
});
$cek('tidak ada pembayaran melebihi tagihan', $lebih->isEmpty(), $lebih->isEmpty() ? 'aman' : $lebih->pluck('nomor_nota')->implode(', '));

echo "\n=== 3. DEPOSIT ===\n";
$kons = DB::table('konsumen')->pluck('id_konsumen');
$negatifDeposit = 0;
$list = [];
foreach ($kons as $id) {
    $s = (float) DB::table('konsumen_deposit')->where('id_konsumen', $id)->where('tipe', 'Setoran')->sum('nominal');
    $p = (float) DB::table('konsumen_deposit')->where('id_konsumen', $id)->where('tipe', 'Pemakaian')->sum('nominal');
    if ($p > $s + 0.01) {
        $negatifDeposit++;
        $list[] = "#{$id} pakai {$rp($p)} dari setoran {$rp($s)}";
    }
}
$cek('tidak ada saldo deposit negatif', $negatifDeposit === 0, $negatifDeposit === 0 ? $kons->count() . ' konsumen diperiksa' : implode(' | ', $list));

echo "\n=== 4. SESI KAS ===\n";
$open = DB::table('kas_harian')->where('status_lock', 'open')->get(['id_kas', 'tgl_kas', 'id_user', 'created_at']);
foreach ($open as $k) {
    $umur = now()->diff(\Carbon\Carbon::parse($k->created_at))->h;
    echo "    sesi #{$k->id_kas} tgl {$k->tgl_kas} milik #{$k->id_user}, dibuka {$k->created_at} (~$umur jam)\n";
}
$cek('hanya satu sesi kas boleh terbuka', $open->count() <= 1, "{$open->count()} sesi terbuka");

$ganda = DB::table('kas_harian')->where('status_lock', 'open')->selectRaw('tgl_kas, COUNT(*) c')->groupBy('tgl_kas')->havingRaw('COUNT(*) > 1')->count();
$cek('tidak ada dua sesi terbuka di tanggal yang sama', $ganda === 0, "{$ganda} tanggalbertohta");

$negatifKas = DB::table('kas_harian')->where('saldo_akhir', '<', 0)->count();
$cek('tidak ada saldo akhir kas negatif', $negatifKas === 0, "{$negatifKas} baris");

echo "\n=== 5. NERACA (Akun vs Saldo) ===\n";

// Neraca dihitung langsung dari jurnal_umum, tanpa bergantung pada tabel
// daftar akun: yang benar-benar menentukan keseimbangan aset/liabilitas/
// ekuitas adalah SALDO AKUN di jurnal, bukan saldo kolom tersimpan.
$saldoPerKode = DB::table('jurnal_umum')
    ->groupBy('kode_akun')
    ->select('kode_akun')
    ->selectRaw('SUM(debet) d, SUM(kredit) k')
    ->get();


// CATATAN: TIDAK ada pemeriksaan "setiap kode akun harus seimbang". Itu
// keliru理解 untuk pembukuanDouble-entry - setiap akun memang SALDO SISI
// SAJA (aset & biaya di debit, liabilitas & ekuitas di kredit). Yang wajib
// seimbang adalah TOTAL seluruh jurnal, dan itu sudah diperiksa di atas.
// Menghapus cek yang salah ini supaya laporan tidak memunculkan "GAGAL" palsu.

echo "    saldo 20 kode akun terbesar:\n";
foreach (collect($saldoPerKode)->sortByDesc(fn ($r) => abs((float) $r->k - (float) $r->d))->take(20) as $r) {
    printf("      %-10s D %-16s K %-16s saldo %s\n", $r->kode_akun, $rp($r->d), $rp($r->k), $rp((float) $r->k - (float) $r->d));
}

echo "\n=== 6. STOK ===\n";

$negatifStok = DB::table('bahan_baku')->where('stok', '<', 0)->get(['id_bahan', 'nama_bahan', 'stok', 'satuan_stok']);
echo '    bahan dengan stok negatif: ' . $negatifStok->count() . "\n";
foreach ($negatifStok->take(12) as $b) {
    echo sprintf("      %-28s %s %s\n", $b->nama_bahan, rtrim(rtrim(number_format((float) $b->stok, 2, ',', ''), '0'), ','), $b->satuan);
}

echo "\n=== RINGKASAN ===\n";
echo "  LULUS: {$ok} | GAGAL: " . count($gagal) . "\n";
foreach ($gagal as $g) {
    echo "  - {$g}\n";
}