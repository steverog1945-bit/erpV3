<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Estimasi kain Kerah per ukuran untuk tipe kerah yang ada di Master Data
 * (Oneck, Vneck, Wangki, Shanghai Zip). Acuan awal: "Wangki" memakai angka
 * komponen Rib Wangki yang dulu Anda input (0,12 m; 10-15 g), tipe lain
 * diturunkan darinya (Oneck lebih kecil, Vneck di antaranya, Shanghai Zip
 * dua kali lipat karena ada plaket/ritsleting). Wajib diverifikasi dengan
 * ukur riil (lihat docs/ACUAN_ESTIMASI_KAIN_JERSEY.md). Hanya mengisi
 * pasangan ukuran x tipe yang belum ada, jadi tidak menimpa edit Anda.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        // nama tipe (huruf kecil) => [meter per ukuran, gram per ukuran] urut S,M,L,XL,2XL,3XL,4XL,5XL
        $acuan = [
            'oneck' => [array_fill(0, 8, 0.08), [7, 7, 7, 8, 8, 10, 10, 10]],
            'vneck' => [array_fill(0, 8, 0.10), [8, 8, 8, 10, 10, 12, 12, 12]],
            'wangki' => [array_fill(0, 8, 0.12), [10, 10, 10, 12, 12, 15, 15, 15]],
            'shanghai zip' => [array_fill(0, 8, 0.24), [20, 20, 20, 24, 24, 30, 30, 30]],
        ];

        $ukuran = DB::table('jersey_sizes')->orderBy('sort_order')->orderBy('id')->pluck('id')->values();

        foreach (DB::table('jersey_collar_types')->get() as $tipe) {
            $data = $acuan[strtolower(trim($tipe->name))] ?? null;
            if (!$data) {
                continue;
            }
            foreach ($ukuran as $i => $idUkuran) {
                if (!isset($data[1][$i])) {
                    continue;
                }
                DB::table('jersey_size_collar_usage')->insertOrIgnore([
                    'jersey_size_id' => $idUkuran, 'jersey_collar_type_id' => $tipe->id,
                    'usage_meter' => $data[0][$i], 'usage_weight_gram' => $data[1][$i],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Data master - tidak dihapus saat rollback.
    }
};
