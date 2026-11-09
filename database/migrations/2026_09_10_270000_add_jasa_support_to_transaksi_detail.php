<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Jasa (services master, sudah ada CRUD-nya) sebelumnya tidak punya
 * konsumen sama sekali - tidak bisa dijual di order manapun. Ini
 * memberinya jalur nyata: transaksi_detail.id_produk dilonggarkan jadi
 * nullable, dan id_jasa nullable ditambahkan - tepat SATU dari keduanya
 * yang wajib terisi per baris (divalidasi di PenjualanController, bukan
 * lewat CHECK constraint DB supaya pesan errornya jelas untuk kasir).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE transaksi_detail MODIFY id_produk BIGINT(20) UNSIGNED NULL');

        Schema::table('transaksi_detail', function (Blueprint $table) {
            $table->foreignId('id_jasa')->nullable()->after('id_produk')
                ->constrained('jasa', 'id_jasa')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_detail', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_jasa');
        });

        DB::statement('ALTER TABLE transaksi_detail MODIFY id_produk BIGINT(20) UNSIGNED NOT NULL');
    }
};
