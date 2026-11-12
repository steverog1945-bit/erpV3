<?php

namespace App\Services;

use App\Models\JerseyPriceSetting;

/**
 * Cari tier harga jersey_price_settings yang cocok untuk kombinasi bahan +
 * tipe produk + kategori + qty. Bahan adalah dimensi PALING PENTING (harga
 * belanja tiap jenis bahan bisa selisih sangat jauh), jadi kombinasi yang
 * match bahan spesifik SELALU dicoba lebih dulu sebelum jatuh ke tier
 * generik tanpa bahan (jersey_material_id NULL = "berlaku untuk semua
 * bahan"). Di dalam grup match-bahan maupun grup generik, urutan pencarian
 * tetap dari kombinasi tipe+kategori paling spesifik ke paling umum, sama
 * seperti sebelumnya. Tidak pernah melempar error kalau tidak ada tier
 * cocok - kasir/admin tetap bisa isi harga manual di form, sama seperti pola
 * HargaService di modul percetakan (harga tidak pernah memblokir pembuatan
 * order).
 */
class JerseyOrderPricingService
{
    public static function cariTierHarga(?int $productTypeId, ?int $categoryId, ?int $materialId, int $qty, string $kelompok = 'dewasa', ?int $idGolongan = null): ?JerseyPriceSetting
    {
        $kombinasiTipeKategori = [
            [$productTypeId, $categoryId],
            [null, $categoryId],
            [$productTypeId, null],
            [null, null],
        ];

        $kombinasiBahan = $materialId ? [$materialId, null] : [null];

        // Golongan: tier khusus (mis. Member Jersey) diprioritaskan bila
        // konsumen termasuk golongan itu; fallback ke tier umum (null).
        $kombinasiGolongan = $idGolongan ? [$idGolongan, null] : [null];

        foreach ($kombinasiGolongan as $gol) {
            foreach ($kombinasiBahan as $mat) {
                foreach ($kombinasiTipeKategori as [$pt, $cat]) {
                    $tier = JerseyPriceSetting::query()
                        ->where('is_active', true)
                        ->where('kelompok', $kelompok)
                        ->where('jersey_product_type_id', $pt)
                        ->where('jersey_category_id', $cat)
                        ->where('jersey_material_id', $mat)
                        ->where(function ($q) use ($gol) {
                            if ($gol === null) {
                                $q->whereNull('id_golongan');
                            } else {
                                $q->where('id_golongan', $gol);
                            }
                        })
                        ->where('qty_min', '<=', $qty)
                        ->where(function ($q) use ($qty) {
                            $q->whereNull('qty_max')->orWhere('qty_max', '>=', $qty);
                        })
                        ->orderByDesc('qty_min')
                        ->first();

                    if ($tier) {
                        return $tier;
                    }
                }
            }
        }

        return null;
    }
}
