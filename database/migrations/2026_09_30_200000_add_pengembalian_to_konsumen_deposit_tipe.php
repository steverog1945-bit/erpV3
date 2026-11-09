<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deposit pelanggan bisa dikembalikan sebagai uang (mis. nota dibatalkan dan pelanggan tidak
 * membuat nota pengganti). Saldo tetap = Setoran - (Pemakaian + Pengembalian).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('konsumen_deposit') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE konsumen_deposit MODIFY tipe ENUM('Setoran','Pemakaian','Pengembalian') NOT NULL DEFAULT 'Setoran'");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('konsumen_deposit') && DB::getDriverName() === 'mysql') {
            DB::table('konsumen_deposit')->where('tipe', 'Pengembalian')->delete();
            DB::statement("ALTER TABLE konsumen_deposit MODIFY tipe ENUM('Setoran','Pemakaian') NOT NULL DEFAULT 'Setoran'");
        }
    }
};
