<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom aditif untuk aturan luas tagih (mode gabungan / per item) dan luas aktual/ditagih per item.
 * Tidak mengubah makna kolom lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produk', function (Blueprint $t) {
            if (!Schema::hasColumn('produk', 'min_luas_tagih')) {
                $t->decimal('min_luas_tagih', 15, 4)->nullable()->after('min_lebar');
            }
            if (!Schema::hasColumn('produk', 'mode_luas')) {
                $t->enum('mode_luas', ['per_item', 'gabungan'])->default('per_item')->after('min_luas_tagih');
            }
        });

        Schema::table('transaksi_detail', function (Blueprint $t) {
            if (!Schema::hasColumn('transaksi_detail', 'luas_aktual')) {
                $t->decimal('luas_aktual', 15, 4)->nullable()->after('l_ukuran');
            }
            if (!Schema::hasColumn('transaksi_detail', 'luas_ditagih')) {
                $t->decimal('luas_ditagih', 15, 4)->nullable()->after('luas_aktual');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_detail', function (Blueprint $t) {
            if (Schema::hasColumn('transaksi_detail', 'luas_ditagih')) $t->dropColumn('luas_ditagih');
            if (Schema::hasColumn('transaksi_detail', 'luas_aktual')) $t->dropColumn('luas_aktual');
        });
        Schema::table('produk', function (Blueprint $t) {
            if (Schema::hasColumn('produk', 'mode_luas')) $t->dropColumn('mode_luas');
            if (Schema::hasColumn('produk', 'min_luas_tagih')) $t->dropColumn('min_luas_tagih');
        });
    }
};