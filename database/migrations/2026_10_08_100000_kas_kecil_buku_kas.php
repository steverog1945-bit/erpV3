<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kas Kecil menjadi buku kas kecil dua arah: selain pengeluaran, kini bisa mencatat
 * PEMASUKAN manual, dan pembayaran order jersey otomatis tercermin sebagai pemasukan
 * (baris cermin sumber 'jersey' - jurnalnya tetap milik pembayaran jersey, tidak dobel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kategori_kas_kecil', function ($t) {
            $t->string('jenis', 10)->default('Keluar')->after('nama_kategori');
        });

        Schema::table('kas_kecil', function ($t) {
            $t->string('jenis', 10)->default('Keluar')->after('tanggal');
            $t->string('sumber', 20)->default('manual')->after('jenis');
            $t->unsignedBigInteger('id_referensi')->nullable()->after('sumber');
            $t->string('metode', 20)->default('Tunai')->after('id_referensi');
        });
        DB::statement('ALTER TABLE kas_kecil MODIFY id_kategori BIGINT UNSIGNED NULL');

        foreach ([['Pendapatan Lain-lain', '4200'], ['Tambahan Modal / Setoran Pemilik', '3000']] as [$nama, $akun]) {
            DB::table('kategori_kas_kecil')->insertOrIgnore([
                'nama_kategori' => $nama, 'jenis' => 'Masuk', 'kode_akun' => $akun, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Pembayaran jersey yang sudah ada ikut tercermin.
        foreach (DB::table('jersey_order_payments as p')->join('jersey_orders as o', 'o.id', '=', 'p.jersey_order_id')
            ->whereNull('p.deleted_at')->get(['p.*', 'o.order_number']) as $p) {
            DB::table('kas_kecil')->insert([
                'tanggal' => $p->tanggal_bayar, 'jenis' => 'Masuk', 'sumber' => 'jersey', 'id_referensi' => $p->id, 'metode' => $p->metode_bayar,
                'id_kategori' => null, 'keterangan' => "Pembayaran order jersey {$p->order_number}", 'nominal' => $p->jumlah,
                'id_user' => $p->created_by, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('kas_kecil')->where('sumber', 'jersey')->delete();
        DB::table('kas_kecil')->where('jenis', 'Masuk')->delete();
        DB::table('kategori_kas_kecil')->where('jenis', 'Masuk')->delete();
        DB::statement('ALTER TABLE kas_kecil MODIFY id_kategori BIGINT UNSIGNED NOT NULL');
        Schema::table('kas_kecil', fn ($t) => $t->dropColumn(['jenis', 'sumber', 'id_referensi', 'metode']));
        Schema::table('kategori_kas_kecil', fn ($t) => $t->dropColumn('jenis'));
    }
};
