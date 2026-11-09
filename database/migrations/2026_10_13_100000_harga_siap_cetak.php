<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File Siap Cetak: order yang filenya sudah jadi dari pelanggan (tanpa desain tim). Harganya tersendiri per produk
 * (BUKAN persentase dari harga biasa), memakai struktur tier & golongan yang sama dengan setting_harga.
 * Produk tanpa harga siap cetak tidak menawarkan opsi ini di Input Order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harga_siap_cetak', function (Blueprint $t) {
            $t->id('id_harga_siap_cetak');
            $t->unsignedBigInteger('id_produk')->index();
            $t->unsignedBigInteger('id_golongan');
            $t->decimal('range_min', 12, 2)->default(0);
            $t->decimal('range_max', 12, 2)->nullable();
            $t->decimal('harga_jual', 14, 2);
            $t->timestamps();
            $t->index(['id_produk', 'id_golongan']);
        });

        Schema::table('transaksi_detail', function (Blueprint $t) {
            $t->boolean('file_siap_cetak')->default(false)->after('biaya_desain');
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_detail', fn (Blueprint $t) => $t->dropColumn('file_siap_cetak'));
        Schema::dropIfExists('harga_siap_cetak');
    }
};
