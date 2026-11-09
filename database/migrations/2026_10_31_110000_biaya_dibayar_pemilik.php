<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sebagian biaya dibayar langsung oleh pemilik, bukan dari uang Kas Kecil. Kategori bertanda dibayar_pemilik tetap
 * dicatat (Dr Beban / Cr 3200 Dana Pemilik untuk Biaya Operasional) tanpa mengurangi saldo Kas Kecil.
 * Pembagian awal menurut pemilik (2026-10-02): yang tetap dari Kas Kecil adalah administrasi & umum, ekspedisi,
 * komisi desainer, upah jahit, biaya lain-lain, dan perlengkapan toko; selebihnya dibayar pemilik.
 */
return new class extends Migration
{
    private const DIBAYAR_PEMILIK = ['5120', '5130', '5140', '5150', '5160', '5180', '5200'];

    public function up(): void
    {
        if (! Schema::hasColumn('kategori_kas_kecil', 'dibayar_pemilik')) {
            Schema::table('kategori_kas_kecil', function (Blueprint $table) {
                $table->boolean('dibayar_pemilik')->default(false)->after('kode_akun');
            });
        }

        DB::table('kategori_kas_kecil')->where('jenis', 'Keluar')->whereIn('kode_akun', self::DIBAYAR_PEMILIK)->update(['dibayar_pemilik' => true]);

        DB::table('bag_akun')->insertOrIgnore([
            'kode_akun' => '3200', 'nama_akun' => 'Dana Pemilik untuk Biaya Operasional', 'tipe_akun' => 'Modal', 'saldo_normal' => 'Kredit',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('kategori_kas_kecil', 'dibayar_pemilik')) {
            Schema::table('kategori_kas_kecil', function (Blueprint $table) {
                $table->dropColumn('dibayar_pemilik');
            });
        }
        if (! DB::table('jurnal_umum')->where('kode_akun', '3200')->exists()) {
            DB::table('bag_akun')->where('kode_akun', '3200')->delete();
        }
    }
};
