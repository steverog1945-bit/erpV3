<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jasa sebelumnya TIDAK PUNYA harga tersimpan sama sekali - 100% input
 * manual kasir tiap kali dijual (lihat komentar di PenjualanController:
 * "tidak punya BOM/stok, harga ditentukan manual oleh kasir"). Ditambahkan
 * harga_default (starting point, sama seperti pola Finishing) DAN opsi
 * harga bertingkat per rentang qty di jasa_harga - kasir tetap bisa
 * override manual di POS, field ini murni acuan/starting point yang
 * sebelumnya tidak ada sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jasa', function (Blueprint $table) {
            $table->decimal('harga_default', 15, 2)->nullable()->after('id_satuan');
        });

        Schema::create('jasa_harga', function (Blueprint $table) {
            $table->id('id_jasa_harga');
            $table->unsignedBigInteger('id_jasa');
            $table->integer('range_min');
            $table->integer('range_max')->nullable();
            $table->decimal('harga', 15, 2);
            $table->timestamps();

            $table->foreign('id_jasa')->references('id_jasa')->on('jasa')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jasa_harga');
        Schema::table('jasa', function (Blueprint $table) {
            $table->dropColumn('harga_default');
        });
    }
};
