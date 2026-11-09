<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier harga jersey sekarang berpusat pada Bahan, bukan cuma Tipe Produk +
 * Kategori - harga belanja tiap jenis bahan selisihnya bisa sangat jauh,
 * jadi tier per qty pun wajib bisa dibedakan per bahan. jersey_material_id
 * NULLABLE (pola sama dengan jersey_product_type_id/jersey_category_id yang
 * sudah ada) supaya tier "berlaku untuk semua bahan" tetap bisa dibuat kalau
 * memang diperlukan, tapi pencarian tier (lihat JerseyOrderPricingService)
 * sekarang memprioritaskan kombinasi yang match bahan spesifik dulu sebelum
 * jatuh ke tier generik.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('jersey_price_settings', 'jersey_material_id')) {
            Schema::table('jersey_price_settings', function (Blueprint $table) {
                $table->foreignId('jersey_material_id')->nullable()
                    ->after('jersey_category_id')
                    ->constrained('jersey_materials')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('jersey_price_settings', 'jersey_material_id')) {
            Schema::table('jersey_price_settings', function (Blueprint $table) {
                $table->dropForeign(['jersey_material_id']);
                $table->dropColumn('jersey_material_id');
            });
        }
    }
};
