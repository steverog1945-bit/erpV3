<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * konsumen_deposit sebelumnya cuma ledger setoran (Setoran-only, sesuai
 * scope yang disetujui saat itu). Menambahkan tipe supaya baris yang sama
 * bisa juga mencatat Pemakaian (deposit dipakai bayar nota di Kasir) -
 * saldo = SUM(Setoran) - SUM(Pemakaian), bukan kolom running-balance
 * terpisah, supaya tidak ada dua sumber kebenaran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('konsumen_deposit', function (Blueprint $table) {
            $table->enum('tipe', ['Setoran', 'Pemakaian'])->default('Setoran')->after('id_konsumen');
            $table->foreignId('id_transaksi')->nullable()->after('tipe')
                ->constrained('transaksi', 'id_transaksi')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('konsumen_deposit', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_transaksi');
            $table->dropColumn('tipe');
        });
    }
};
