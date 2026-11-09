<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keping = jumlah barang jahit dalam satu baris nota (metode
        // finishing KEPING_ROLL). Meter jahit = keping x keliling ukuran
        // barang (2 x (P_b + L_b) / 100) x qty. Barang 55x500 lima keping
        // pada media 110 (atau 120) tetap jahat per keping.
        if (!Schema::hasColumn('transaksi_detail_finishing', 'keping')) {
            Schema::table('transaksi_detail_finishing', function (Blueprint $t) {
                $t->unsignedInteger('keping')->nullable()->after('qty_pakai');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transaksi_detail_finishing', 'keping')) {
            Schema::table('transaksi_detail_finishing', function (Blueprint $t) {
                $t->dropColumn('keping');
            });
        }
    }
};
