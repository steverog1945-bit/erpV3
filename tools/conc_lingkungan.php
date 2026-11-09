<?php

/**
 * Pensiapan lingkungan concurrency terisolasi.
 *
 * Menggandakan SKEMA dari database testing ke database baru yang khusus
 * concurrency, lalu mengisi data sintetis. Database operasional oriondb tidak
 * pernah disentuh, dan percetakan_erp_testing juga tidak - test suite PHPUnit
 * memakai yang itu, jadi kalau keduanya berebut satu sama lain hasilnya tidak
 * bisa dipercaya.
 *
 * Nama database/tabel di bawah berasal dari konstanta yang ditulis di berkas
 * ini, bukan input pengguna, jadi tidak perlu dibungkus tanda kutip
 * identifier. PDO::quote() juga tidak bisa dipakai: ia menghasilkan tanda kutip
 * string yang tidak valid untuk nama database.
 *
 * Cara pakai:
 *   set DB_DATABASE=percetakan_erp_testing
 *   php tools/conc_lingkungan.php siapkan
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

const SUMBER = 'percetakan_erp_testing';
const SASAR = 'percetakan_erp_conc';

$aksi = $argv[1] ?? 'siapkan';

if ($aksi === 'hapus') {
    DB::statement('DROP DATABASE IF EXISTS ' . SASAR);
    echo 'database ' . SASAR . " dihapus\n";
    exit(0);
}

// Pengaman: jangan sampai salah target.
$sumber = DB::connection()->getDatabaseName();
if ($sumber !== SUMBER) {
    exit("Berhenti: skrip ini harus dijalankan dengan DB_DATABASE=" . SUMBER . ", sekarang {$sumber}\n");
}

$root = DB::connection()->getPdo();

// 1. Salin skema persis dari database testing.
$root->exec('DROP DATABASE IF EXISTS ' . SASAR);
$root->exec('CREATE DATABASE ' . SASAR . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

$tabel = DB::select('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = ?', [SUMBER]);
$jumlah = 0;
foreach ($tabel as $row) {
    $nama = $row->t ?: $row->TABLE_NAME;
    $root->exec('CREATE TABLE ' . SASAR . '.' . $nama . ' LIKE ' . SUMBER . '.' . $nama);
    $root->exec('INSERT INTO ' . SASAR . '.' . $nama . ' SELECT * FROM ' . SUMBER . '.' . $nama);
    $jumlah++;
}
echo "skema tersalin: {$jumlah} tabel\n";

config(['database.connections.' . DB::getDefaultConnection() . '.database' => SASAR]);
DB::purge();
DB::reconnect();

if (DB::connection()->getDatabaseName() !== SASAR) {
    exit('Berhenti: koneksi tidak pindah ke ' . SASAR);
}
echo 'sekarang membaca: ' . DB::connection()->getDatabaseName() . "\n";

// 2. Bersihkan data yang tidak boleh ikut serta.
foreach (['jurnal_umum', 'audit_log', 'sessions', '_conc_pagar'] as $t) {
    if (DB::getSchemaBuilder()->hasTable($t)) {
        DB::table($t)->delete();
    }
}

DB::statement('SET FOREIGN_KEY_CHECKS = 0');
foreach (['transaksi', 'transaksi_detail', 'pembayaran_riwayat', 'konsumen_deposit', 'kas_harian', 'kas_manual'] as $t) {
    if (DB::getSchemaBuilder()->hasTable($t)) {
        DB::table($t)->delete();
    }
}
DB::statement('SET FOREIGN_KEY_CHECKS = 1');


// Role uji harus punya izin menu yang memang dipanggil pekerja konkurensi.
// Tanpa ini CheckMenuAccess membalas 403, halaman yang diambil tidak punya
// form CSRF, dan seluruh uji gagal dengan 419 yang menyesatkan - gejalanya
// terlihat seperti masalah CSRF, padahal sebenarnya masalah izin.

// Nama role PERSIS 'Administrator'. CheckMenuAccess meloloskan role dengan nama
// itu di baris pertama sebelum cek permission apa pun, dan role ini ikut
// tertutup AksesHarga. Role 'ConcKasir' akan mendapat 403 di setiap request -
// yang diuji gate ini adalah integritas data saat rebutan, bukan RBAC.
$roleId = DB::table('roles')->where('nama_role', 'Administrator')->value('id_role')
    ?: DB::table('roles')->insertGetId(['nama_role' => 'Administrator']);

$izinKasir = DB::table('menus')
    ->where('url_link', 'like', '%kasir%')
    ->pluck('id_menu')
    ->all();

// users.id_karyawan NOT NULL tanpa default, jadi karyawan dibuat lebih dulu
// (user di aplikasi selalu milik seorang karyawan).
$karyawanId = DB::table('karyawan')->insertGetId([
    'nama_karyawan' => 'Kasir Conc',
    'bagian' => 'Kasir',
    'telepon' => '0800000001',
    'status_aktif' => 1,
]);

$userId = DB::table('users')->insertGetId([
    'username' => 'conc_kasir',
    'password' => bcrypt('RahasiaKuat123'),
    'id_role' => $roleId,
    'id_karyawan' => $karyawanId,
]);

$golId = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Conc', 'keterangan' => '-']);
$konsId = DB::table('konsumen')->insertGetId([
    'nama_konsumen' => 'Konsumen Conc',
    'telepon' => '0800',
    'alamat' => '-',
    'id_golongan' => $golId,
]);

// Setoran deposit 100.000 - sengaja KECIL supaya dua kasir yang membayar
// bersamaan akan berebut saldo yang persis sama.
DB::table('konsumen_deposit')->insert([
    'id_konsumen' => $konsId,
    'tipe' => 'Setoran',
    'nominal' => 100000,
    'metode_bayar' => 'Tunai',
    'keterangan' => 'Saldo awal uji',
    'tgl_deposit' => now()->toDateString(),
    'id_user' => $userId,
    'created_at' => now(),
    'updated_at' => now(),
]);

$produkId = DB::table('produk')->insertGetId([
    'nama_produk' => 'Produk Conc',
    'metode_hitung' => 'quantity',
    'status' => 'Aktif',
]);

// Tiga nota: (1) double payment, (2) double spending deposit, (3) closing ganda.
$nota = [];
foreach ([1, 2, 3] as $i) {
    $nota[$i] = DB::table('transaksi')->insertGetId([
        'nomor_nota' => 'CONC-2026-' . $i,
        'tipe_order' => 'Reguler',
        'id_konsumen' => $konsId,
        'id_user_kasir' => $userId,
        'tgl_order' => now(),
        'total_bayar' => 100000,
        'status_pembayaran' => 'Belum Bayar',
        'status_nota' => 'Aktif',
        'status_order' => 'Diterima Kasir',
    ]);
    DB::table('transaksi_detail')->insert([
        'id_transaksi' => $nota[$i],
        'id_produk' => $produkId,
        'nama_file' => 'conc.pdf',
        'p_ukuran' => 0,
        'l_ukuran' => 0,
        'qty' => 1,
        'biaya_cetak' => 100000,
        'subtotal' => 100000,
        'status_spk' => 'Antre',
    ]);
}

$kasId = DB::table('kas_harian')->insertGetId([
    'tgl_kas' => now()->toDateString(),
    'id_user' => $userId,
    'saldo_awal' => 500000,
    'status_lock' => 'open',
    'created_at' => now(),
    'updated_at' => now(),
]);

// Pagar Rendezvous: dipakai driver untuk menyamakan waktu mulai semua proses.
DB::statement('DROP TABLE IF EXISTS _conc_pagar');
DB::statement('CREATE TABLE _conc_pagar (id INT AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(64) NOT NULL, token VARCHAR(64) NOT NULL, KEY (slug)) ENGINE=InnoDB');

file_put_contents(__DIR__ . '/conc_data.json', json_encode([
    'db' => SASAR,
    'user_id' => $userId,
    'role_id' => $roleId,
    'konsumen_id' => $konsId,
    'produk_id' => $produkId,
    'nota_pembayaran' => $nota[1],
    'nota_deposit' => $nota[2],
    'nota_closing' => $nota[3],
    'kas_id' => $kasId,
    'saldo_deposit' => 100000,
], JSON_PRETTY_PRINT));

echo "data sintetis siap\n";
echo "  user_id         = {$userId}\n";
echo "  nota pembayaran = {$nota[1]}\n";
echo "  nota deposit    = {$nota[2]}\n";
echo "  nota closing    = {$nota[3]}\n";
echo "  kas_id          = {$kasId}\n";