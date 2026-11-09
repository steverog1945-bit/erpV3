<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Melengkapi master Jersey untuk kebutuhan cetak jersey - hanya MENAMBAH
 * yang belum ada (berdasarkan nama), tidak mengubah/menimpa data Anda:
 * - Tipe Produk: Celana Panjang, Kaos Kiper, Polo
 * - Kategori (metode cetak): Jersey Sablon DTF, Jersey Bordir
 * - Bahan: Benzema, Dryfit, Jacquard, Lacoste Pique
 * - Komponen "Celana Panjang" (khusus tipe Celana Panjang) + estimasinya
 * - Tier harga umum (semua bahan & kategori) per tipe produk dengan pita
 *   1-11, 12-23, 24-49, 50+ (pita lusin) sebagai fallback; harga tier yang
 *   spesifik bahan (mis. Brazil) tetap didahulukan. Harga = ACUAN dari
 *   harga Brazil Anda (Jersey Rp 80.000, Training 65.000, Celana 30.000),
 *   WAJIB disesuaikan di Master > Tier Harga > Input Cepat.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $now = now();

        $tambah = function (string $tabel, string $nama, array $extra = []) use ($now) {
            if (!DB::table($tabel)->where('name', $nama)->exists()) {
                DB::table($tabel)->insert(['name' => $nama, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now] + $extra);
            }

            return (int) DB::table($tabel)->where('name', $nama)->value('id');
        };

        $jersey = (int) DB::table('jersey_product_types')->where('name', 'Jersey')->value('id');
        $celanaPendek = (int) DB::table('jersey_product_types')->where('name', 'Celana Pendek')->value('id');
        $training = (int) DB::table('jersey_product_types')->where('name', 'Training')->value('id');
        $celanaPanjang = $tambah('jersey_product_types', 'Celana Panjang', ['has_sleeve_collar' => 0]);
        $kiper = $tambah('jersey_product_types', 'Kaos Kiper', ['has_sleeve_collar' => 1]);
        $polo = $tambah('jersey_product_types', 'Polo', ['has_sleeve_collar' => 1]);

        $tambah('jersey_categories', 'Jersey Sablon DTF');
        $tambah('jersey_categories', 'Jersey Bordir');
        foreach (['Benzema', 'Dryfit', 'Jacquard', 'Lacoste Pique'] as $bahan) {
            $tambah('jersey_materials', $bahan);
        }

        // Komponen Celana Panjang + estimasi (kira-kira 1,75x celana pendek; 225 g per meter).
        $idKomponen = $tambah('jersey_components', 'Celana Panjang', ['sort_order' => 5, 'jersey_product_type_id' => $celanaPanjang]);
        $meter = [0.96, 1.05, 1.14, 1.22, 1.33, 1.44, 1.54, 1.65];
        $gram = [215, 235, 255, 275, 300, 325, 345, 370];
        $ukuran = DB::table('jersey_sizes')->orderBy('sort_order')->orderBy('id')->pluck('id')->values();
        foreach ($ukuran as $i => $idUkuran) {
            if (isset($meter[$i])) {
                DB::table('jersey_size_component_usage')->insertOrIgnore([
                    'jersey_size_id' => $idUkuran, 'jersey_component_id' => $idKomponen,
                    'usage_meter' => $meter[$i], 'usage_weight_gram' => $gram[$i], 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // Tier harga umum: [tipe => [harga 1-11, 12-23, 24-49, 50+]]
        $harga = [
            $jersey => [90000, 85000, 80000, 75000],
            $training => [75000, 70000, 65000, 60000],
            $celanaPendek => [35000, 32000, 30000, 27000],
            $celanaPanjang => [50000, 47000, 45000, 42000],
            $kiper => [105000, 98000, 92000, 88000],
            $polo => [100000, 92000, 85000, 80000],
        ];
        $pita = [[1, 11], [12, 23], [24, 49], [50, null]];

        foreach ($harga as $idTipe => $daftar) {
            if (!$idTipe) {
                continue;
            }
            $sudahAda = DB::table('jersey_price_settings')->where('jersey_product_type_id', $idTipe)
                ->whereNull('jersey_material_id')->whereNull('jersey_category_id')->exists();
            if ($sudahAda) {
                continue;
            }
            foreach ($pita as $i => [$min, $max]) {
                DB::table('jersey_price_settings')->insert([
                    'jersey_product_type_id' => $idTipe, 'jersey_category_id' => null, 'jersey_material_id' => null,
                    'qty_min' => $min, 'qty_max' => $max, 'price' => $daftar[$i], 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Data master - tidak dihapus saat rollback.
    }
};
