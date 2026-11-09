<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Perbarui berat estimasi (usage_weight_gram) untuk komponen Body & Lengan
 * per ukuran, berdasarkan riset bahan jersey sublimasi 180 GSM (sumber:
 * collectivestudioltd.com fabric consumption calculator, 2026).
 *   Body (komponen 1): S=192, M=207, L=223, XL=239, 2XL=255, 3XL=270,
 *                      4XL=285, 5XL=300 g; anak XS=120..2XL=170 g.
 *   Lengan (komponen 2): S=60, M=65, L=70, XL=75, 2XL=80, 3XL=85,
 *                        4XL=90, 5XL=95 g; anak 40..60 g.
 * Meter (usage_meter) tidak diubah (sudah sesuai). Idempoten: UPDATE by
 * (jersey_size_id, jersey_component_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        $body = [1 => 192, 2 => 207, 3 => 223, 4 => 239, 5 => 255, 6 => 270, 7 => 285, 8 => 300,
                 15 => 120, 16 => 130, 17 => 140, 18 => 150, 19 => 160, 20 => 170];
        $lengan = [1 => 60, 2 => 65, 3 => 70, 4 => 75, 5 => 80, 6 => 85, 7 => 90, 8 => 95,
                    15 => 40, 16 => 44, 17 => 48, 18 => 52, 19 => 56, 20 => 60];

        foreach ($body as $sizeId => $gram) {
            DB::table('jersey_size_component_usage')
                ->where('jersey_size_id', $sizeId)
                ->where('jersey_component_id', 1)
                ->update(['usage_weight_gram' => $gram, 'updated_at' => now()]);
        }
        foreach ($lengan as $sizeId => $gram) {
            DB::table('jersey_size_component_usage')
                ->where('jersey_size_id', $sizeId)
                ->where('jersey_component_id', 2)
                ->update(['usage_weight_gram' => $gram, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Tidak mengembalikan (data estimasi bisa diedit manual).
    }
};
