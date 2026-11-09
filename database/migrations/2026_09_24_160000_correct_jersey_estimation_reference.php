<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Koreksi & pelengkapan estimasi kain Jersey (meter per pcs) setelah
 * pemulihan dari backup. Berat (gram) TIDAK disentuh - itu angka input
 * pemilik. Yang dilakukan:
 * - Mengisi meter yang kosong (4XL/5XL) mengikuti tren ukuran sebelumnya.
 * - Merapikan angka janggal: Celana yang datar 1,00 m lalu melonjak 1,30 m
 *   di 3XL, dan Lengan panjang 3XL yang turun ke 0,48 m.
 * Nilai ini ACUAN AWAL - harus diverifikasi dengan pengukuran riil di
 * lapangan (lihat docs/ACUAN_ESTIMASI_KAIN_JERSEY.md) lalu diubah di
 * Master Data bila berbeda. Hanya mengisi/mengoreksi nilai yang masih
 * kosong atau persis sama dengan nilai janggal lama, jadi tidak menimpa
 * angka yang sudah Anda ubah sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        // komponen => [ukuran => [meter baru, meter lama yang boleh ditimpa (null = hanya jika kosong)]]
        $koreksi = [
            'Body' => ['4XL' => [1.00, null], '5XL' => [1.04, null]],
            'Rib' => ['4XL' => [0.04, null], '5XL' => [0.04, null]],
            'Celana' => ['XL' => [1.05, 1.000], '2XL' => [1.10, 1.000], '3XL' => [1.15, 1.300], '4XL' => [1.20, null], '5XL' => [1.25, null]],
            'Lengan' => ['4XL' => [0.335, null], '5XL' => [0.350, null]],
            'Lengan panjang' => ['3XL' => [0.700, 0.480], '4XL' => [0.750, null], '5XL' => [0.800, null]],
            'Rib Wangki' => ['4XL' => [0.12, null], '5XL' => [0.12, null]],
        ];

        foreach ($koreksi as $namaKomponen => $perUkuran) {
            $idKomponen = DB::table('jersey_components')->where('name', $namaKomponen)->value('id');
            if (!$idKomponen) {
                continue;
            }
            foreach ($perUkuran as $namaUkuran => [$baru, $lama]) {
                $idUkuran = DB::table('jersey_sizes')->where('name', $namaUkuran)->value('id');
                if (!$idUkuran) {
                    continue;
                }
                $q = DB::table('jersey_size_component_usage')->where('jersey_component_id', $idKomponen)->where('jersey_size_id', $idUkuran);
                $lama === null ? $q->whereNull('usage_meter') : $q->where('usage_meter', $lama);
                $q->update(['usage_meter' => $baru, 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Data master - tidak dikembalikan otomatis.
    }
};
