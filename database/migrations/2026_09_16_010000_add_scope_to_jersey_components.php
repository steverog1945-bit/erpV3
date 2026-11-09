<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Komponen (Body/Lengan/Rib/Celana dst) sebelumnya berlaku UNIVERSAL untuk
 * semua barang - SPK jadi menampilkan estimasi komponen yang sebenarnya
 * tidak dipakai barang itu (mis. komponen "Celana" ikut muncul di SPK
 * Jersey kaos biasa). Kolom baru ini nullable, mengikuti pola yang sama
 * persis dengan jersey_price_settings: null = berlaku untuk SEMUA
 * tipe produk/kategori, diisi = component itu hanya relevan untuk
 * tipe produk/kategori tsb.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jersey_components', function (Blueprint $table) {
            if (!Schema::hasColumn('jersey_components', 'jersey_product_type_id')) {
                $table->foreignId('jersey_product_type_id')->nullable()->after('name')
                    ->constrained('jersey_product_types')->nullOnDelete();
            }
            if (!Schema::hasColumn('jersey_components', 'jersey_category_id')) {
                $table->foreignId('jersey_category_id')->nullable()->after('jersey_product_type_id')
                    ->constrained('jersey_categories')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('jersey_components', function (Blueprint $table) {
            if (Schema::hasColumn('jersey_components', 'jersey_category_id')) {
                $table->dropConstrainedForeignId('jersey_category_id');
            }
            if (Schema::hasColumn('jersey_components', 'jersey_product_type_id')) {
                $table->dropConstrainedForeignId('jersey_product_type_id');
            }
        });
    }
};
