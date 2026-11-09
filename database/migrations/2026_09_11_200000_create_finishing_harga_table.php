<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Harga Finishing sebelumnya cuma satu angka flat (finishing.harga_default)
 * yang bahkan bebas ditimpa manual di Kasir tanpa acuan sama sekali. Tabel
 * ini menambahkan opsi harga BERTINGKAT per rentang qty (mis. 1-10 = Rp5rb,
 * 11-20 = Rp4.5rb) - sama seperti Price List produk, tapi TANPA dimensi
 * golongan konsumen (finishing dianggap ongkos kerja, bukan harga jual
 * utama yang lazim dibedakan per tipe pelanggan). finishing.harga_default
 * tetap dipertahankan sebagai fallback kalau tidak ada tier yang cocok/
 * belum diatur sama sekali - fitur ini murni ADITIF, tidak menghapus cara
 * lama yang sudah berjalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finishing_harga', function (Blueprint $table) {
            $table->id('id_finishing_harga');
            $table->unsignedBigInteger('id_finishing');
            $table->integer('range_min');
            $table->integer('range_max')->nullable();
            $table->decimal('harga', 15, 2);
            $table->timestamps();

            $table->foreign('id_finishing')->references('id_finishing')->on('finishing')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finishing_harga');
    }
};
