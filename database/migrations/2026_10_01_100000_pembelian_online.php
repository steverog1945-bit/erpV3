<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pembelian bahan lewat toko online. Daftar platform (Tokopedia, Shopee, ...) adalah data master yang bisa
 * ditambah/diubah/dinonaktifkan sendiri (bukan hard-code). Info pesanan online menempel di Kedatangan Bahan;
 * ongkir/biaya layanan/voucher menempel di Faktur Pembelian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_belanja', function (Blueprint $t) {
            $t->id();
            $t->string('nama', 100)->unique();
            $t->string('catatan', 255)->nullable();
            $t->boolean('aktif')->default(true);
            $t->integer('urutan')->default(0);
            $t->timestamps();
        });
        DB::table('platform_belanja')->insert([
            ['nama' => 'Tokopedia', 'catatan' => null, 'aktif' => true, 'urutan' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['nama' => 'Shopee', 'catatan' => null, 'aktif' => true, 'urutan' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('kedatangan_bahan', function (Blueprint $t) {
            $t->unsignedBigInteger('id_platform')->nullable();
            $t->string('nama_toko_online', 150)->nullable();
            $t->string('no_pesanan_online', 100)->nullable();
            $t->string('no_resi', 100)->nullable();
        });

        Schema::table('pembelian_bahan', function (Blueprint $t) {
            $t->decimal('ongkir', 15, 2)->default(0);
            $t->decimal('biaya_layanan', 15, 2)->default(0);
            $t->decimal('voucher', 15, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('pembelian_bahan', fn (Blueprint $t) => $t->dropColumn(['ongkir', 'biaya_layanan', 'voucher']));
        Schema::table('kedatangan_bahan', fn (Blueprint $t) => $t->dropColumn(['id_platform', 'nama_toko_online', 'no_pesanan_online', 'no_resi']));
        Schema::dropIfExists('platform_belanja');
    }
};
