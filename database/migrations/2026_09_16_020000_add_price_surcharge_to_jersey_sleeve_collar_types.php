<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahan harga flat per Tipe Lengan/Tipe Kerah (mis. lengan panjang
 * +Rp5.000/pcs) - pola sama persis dengan jersey_sizes.price_surcharge
 * yang sudah berjalan untuk tambahan harga per ukuran.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('jersey_sleeve_types', 'price_surcharge')) {
            Schema::table('jersey_sleeve_types', function (Blueprint $table) {
                $table->decimal('price_surcharge', 12, 2)->default(0)->after('name');
            });
        }
        if (!Schema::hasColumn('jersey_collar_types', 'price_surcharge')) {
            Schema::table('jersey_collar_types', function (Blueprint $table) {
                $table->decimal('price_surcharge', 12, 2)->default(0)->after('name');
            });
        }
        if (!Schema::hasColumn('jersey_order_lines', 'sleeve_collar_surcharge_total')) {
            Schema::table('jersey_order_lines', function (Blueprint $table) {
                $table->decimal('sleeve_collar_surcharge_total', 12, 2)->default(0)->after('size_surcharge_total');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('jersey_order_lines', 'sleeve_collar_surcharge_total')) {
            Schema::table('jersey_order_lines', function (Blueprint $table) {
                $table->dropColumn('sleeve_collar_surcharge_total');
            });
        }
        if (Schema::hasColumn('jersey_collar_types', 'price_surcharge')) {
            Schema::table('jersey_collar_types', function (Blueprint $table) {
                $table->dropColumn('price_surcharge');
            });
        }
        if (Schema::hasColumn('jersey_sleeve_types', 'price_surcharge')) {
            Schema::table('jersey_sleeve_types', function (Blueprint $table) {
                $table->dropColumn('price_surcharge');
            });
        }
    }
};
