<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger deposit konsumen - genuinely baru, tidak ada tabel/kolom apa pun
 * sebelumnya untuk ini. Saldo deposit per konsumen DIHITUNG dari SUM(nominal)
 * baris di tabel ini (bukan kolom running-balance terpisah), supaya tidak
 * ada dua sumber kebenaran yang bisa saling tidak sinkron. Belum ada fitur
 * "pakai deposit sebagai pembayaran" di kasir - ini murni pencatatan
 * setoran dulu (sesuai scope yang disetujui: master data baru).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('konsumen_deposit', function (Blueprint $table) {
            $table->id('id_deposit');
            $table->foreignId('id_konsumen')->constrained('konsumen', 'id_konsumen')->cascadeOnDelete();
            $table->decimal('nominal', 15, 2);
            $table->string('metode_bayar', 30)->default('Tunai');
            $table->text('keterangan')->nullable();
            $table->date('tgl_deposit');
            $table->foreignId('id_user')->nullable()
                ->constrained('users', 'id_user')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('konsumen_deposit');
    }
};
