<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estimasi kebutuhan bahan per ukuran x komponen sebelumnya cuma dalam
 * meter (usage_meter) - sekarang ditambah berat (gram) supaya bisa
 * memperkirakan kebutuhan bahan yang lebih pas diukur per berat (mis. rib,
 * benang) daripada per meter. Disimpan dalam gram (presisi dua desimal,
 * cukup untuk komponen kecil) - ditampilkan otomatis dalam kg kalau totalnya
 * >= 1000 gram (lihat JerseyMaterialEstimationService::formatBeratGram()),
 * jadi satu kolom ini sudah mencakup baik input gram maupun kg tanpa perlu
 * kolom/satuan terpisah.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('jersey_size_component_usage', 'usage_weight_gram')) {
            Schema::table('jersey_size_component_usage', function (Blueprint $table) {
                $table->decimal('usage_weight_gram', 10, 2)->nullable()->after('usage_meter');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('jersey_size_component_usage', 'usage_weight_gram')) {
            Schema::table('jersey_size_component_usage', function (Blueprint $table) {
                $table->dropColumn('usage_weight_gram');
            });
        }
    }
};
