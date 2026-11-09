<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Memulihkan master data estimasi bahan Jersey yang hilang saat database
 * rusak (24 Sep 2026), diambil dari backup terakhir yang masih memuatnya
 * (pre_lengan_kerah_20260913 - data yang diinput pemilik):
 * - Tipe Produk (Jersey, Celana Pendek, Training)
 * - Matriks estimasi Komponen x Ukuran (meter & gram)
 * - Perbaikan matriks Lengan yang menunjuk tipe lengan id=5 (tipe itu
 *   hilang; angkanya - lebih besar dari Pendek - dialihkan ke "Panjang").
 * Cakupan komponen dirapikan supaya SPK/nota hanya memuat bahan yang
 * dibutuhkan: Celana hanya untuk tipe Celana Pendek, komponen Lengan lama
 * tetap nonaktif (sudah digantikan matriks Tipe Lengan).
 * Hanya jalan bila matriks masih kosong, jadi tidak menimpa edit berikutnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $now = now();

        if (DB::table('jersey_product_types')->doesntExist()) {
            DB::table('jersey_product_types')->insert([
                ['id' => 1, 'name' => 'Jersey', 'has_sleeve_collar' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['id' => 2, 'name' => 'Celana Pendek', 'has_sleeve_collar' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['id' => 3, 'name' => 'Training', 'has_sleeve_collar' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        if (DB::table('jersey_size_component_usage')->exists() || DB::table('jersey_components')->doesntExist()) {
            return;
        }

        // komponen id => [meter per ukuran 1..8, gram per ukuran 1..8]
        $data = [
            1 => [[0.712, 0.730, 0.750, 0.860, 0.920, 0.960, null, null], [140, 160, 180, 200, 230, 260, 290, 320]],
            2 => [[0.200, 0.240, 0.260, 0.285, 0.310, 0.320, null, null], [55, 60, 65, 70, 80, 90, 100, 110]],
            3 => [[0.030, 0.030, 0.030, 0.030, 0.030, 0.040, null, null], [5, 5, 5, 5, 5, 5, 5, 5]],
            4 => [[0.840, 0.890, 1.000, 1.000, 1.000, 1.300, null, null], [110, 120, 135, 150, 170, 190, 210, 230]],
            5 => [[0.500, 0.500, 0.600, 0.600, 0.650, 0.480, null, null], [130, 145, 160, 175, 195, 215, 235, 255]],
            6 => [[0.120, 0.120, 0.120, 0.120, 0.120, 0.120, null, null], [10, 10, 10, 12, 12, 15, 15, 15]],
        ];

        $sizeIds = DB::table('jersey_sizes')->orderBy('sort_order')->orderBy('id')->pluck('id')->values();
        $komponenAda = DB::table('jersey_components')->pluck('id')->all();
        $rows = [];

        foreach ($data as $idKomponen => [$meter, $gram]) {
            if (!in_array($idKomponen, $komponenAda, true)) {
                continue;
            }
            foreach ($sizeIds as $i => $idSize) {
                if (!isset($gram[$i])) {
                    continue;
                }
                $rows[] = [
                    'jersey_size_id' => $idSize, 'jersey_component_id' => $idKomponen,
                    'usage_meter' => $meter[$i], 'usage_weight_gram' => $gram[$i],
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }
        DB::table('jersey_size_component_usage')->insert($rows);

        // Cakupan: Body/Rib berlaku untuk tipe yang punya lengan/kerah, Celana hanya Celana Pendek.
        DB::table('jersey_components')->whereIn('id', [1, 3])->update(['is_active' => 1, 'jersey_product_type_id' => null, 'jersey_category_id' => null]);
        DB::table('jersey_components')->where('id', 4)->update(['is_active' => 1, 'jersey_product_type_id' => 2, 'jersey_category_id' => null]);
        DB::table('jersey_components')->whereIn('id', [2, 5, 6])->update(['is_active' => 0]);

        // Matriks Lengan yang menunjuk tipe yang sudah tidak ada.
        $idPanjang = DB::table('jersey_sleeve_types')->where('name', 'Panjang')->value('id');
        $adaTipe5 = DB::table('jersey_sleeve_types')->where('id', 5)->exists();
        if ($idPanjang && !$adaTipe5 && DB::table('jersey_size_sleeve_usage')->where('jersey_sleeve_type_id', $idPanjang)->doesntExist()) {
            DB::table('jersey_size_sleeve_usage')->where('jersey_sleeve_type_id', 5)->update(['jersey_sleeve_type_id' => $idPanjang]);
        }
    }

    public function down(): void
    {
        // Data master - tidak dihapus saat rollback.
    }
};
