<?php

namespace App\Services;

use App\Models\JerseySizeCollarUsage;
use App\Models\JerseySizeComponentUsage;
use App\Models\JerseySizeSleeveUsage;

/**
 * Estimasi pemakaian bahan (meter & berat) per baris order jersey, dihitung
 * otomatis dari qty per ukuran x matriks estimasi yang diisi admin di
 * Master Data - TIGA matriks terpisah: Komponen (Body/Rib/Celana/Manset,
 * dst - bebas ditambah admin), Tipe Lengan (Pendek/Panjang/dst), dan Tipe
 * Kerah (O/V/dst), supaya estimasi lebih mendekati riil (jersey lengan
 * panjang & kerah tertentu makan bahan lebih banyak, sebelumnya semua
 * disamaratakan). Lengan & Kerah dihitung per KOMBINASI ukuran x tipe
 * (bukan cuma per tipe) - jersey ukuran L lengan panjang belum tentu makan
 * bahan sama dengan ukuran S lengan panjang. Murni ESTIMASI (bukan
 * pemotongan stok riil) - kalau ukuran/tipe tertentu belum ada angka
 * estimasinya, baris itu dilewati begitu saja, tidak pernah menggagalkan
 * pembuatan order.
 */
class JerseyMaterialEstimationService
{
    /**
     * @param  array<int,int>  $qtyPerSize  [jersey_size_id => qty]
     * @param  int|null  $productTypeId  Tipe Produk barang - dipakai untuk menyaring Komponen yang di-scope (lihat catatan di bawah)
     * @param  int|null  $categoryId  Kategori barang - dipakai untuk menyaring Komponen yang di-scope
     * @return array<int,array{component: \Illuminate\Database\Eloquent\Model, total_meter: float, total_weight_gram: float}>
     */
    public static function hitungEstimasi(array $qtyPerSize, ?int $productTypeId = null, ?int $categoryId = null): array
    {
        $qtyPerSize = array_filter($qtyPerSize, fn ($q) => $q > 0);
        if (empty($qtyPerSize)) {
            return [];
        }

        $punyaLenganKerah = self::tipeMemilikiLenganKerah($productTypeId);

        $usages = JerseySizeComponentUsage::with('component')
            ->whereIn('jersey_size_id', array_keys($qtyPerSize))
            ->where(function ($q) {
                $q->whereNotNull('usage_meter')->orWhereNotNull('usage_weight_gram');
            })
            ->get();

        $totals = [];
        foreach ($usages as $usage) {
            $component = $usage->component;
            if (!$component || !$component->is_active) {
                continue;
            }
            // Komponen yang di-scope ke Tipe Produk/Kategori tertentu di
            // Master Data (mis. "Celana" cuma untuk tipe produk Celana)
            // dilewati kalau barang ini bukan tipe/kategori itu - null di
            // Master Data berarti komponen itu berlaku untuk SEMUA barang,
            // sama seperti pola null di jersey_price_settings.
            if ($component->jersey_product_type_id && $component->jersey_product_type_id != $productTypeId) {
                continue;
            }
            if ($component->jersey_category_id && $component->jersey_category_id != $categoryId) {
                continue;
            }
            // Komponen tanpa cakupan (Body, Rib, dst) tidak relevan untuk tipe
            // produk tanpa lengan/kerah (mis. Celana) - jangan ikut tampil.
            if (!$component->jersey_product_type_id && !$punyaLenganKerah) {
                continue;
            }
            $qty = $qtyPerSize[$usage->jersey_size_id] ?? 0;
            if ($qty <= 0) {
                continue;
            }
            self::tambahTotal($totals, $usage->jersey_component_id, $component, $usage, $qty);
        }

        return self::urutkanTotal($totals);
    }

    /**
     * Apakah tipe produk ini punya lengan/kerah. Tipe kosong/tidak dikenal
     * dianggap punya (perilaku lama). Dipakai supaya estimasi Lengan, Kerah,
     * dan komponen umum tidak muncul untuk barang seperti Celana.
     */
    public static function tipeMemilikiLenganKerah(?int $productTypeId): bool
    {
        if (!$productTypeId) {
            return true;
        }

        $nilai = \Illuminate\Support\Facades\DB::table('jersey_product_types')->where('id', $productTypeId)->value('has_sleeve_collar');

        return $nilai === null ? true : (bool) $nilai;
    }

    /**
     * @param  array<int,array<int,int>>  $qtyPerSizeSleeveType  [jersey_size_id => [jersey_sleeve_type_id => qty]]
     */
    public static function hitungEstimasiLengan(array $qtyPerSizeSleeveType): array
    {
        return self::hitungEstimasiDuaDimensi(
            JerseySizeSleeveUsage::class, 'sleeveType', 'jersey_sleeve_type_id', $qtyPerSizeSleeveType
        );
    }

    /**
     * @param  array<int,array<int,int>>  $qtyPerSizeCollarType  [jersey_size_id => [jersey_collar_type_id => qty]]
     */
    public static function hitungEstimasiKerah(array $qtyPerSizeCollarType): array
    {
        return self::hitungEstimasiDuaDimensi(
            JerseySizeCollarUsage::class, 'collarType', 'jersey_collar_type_id', $qtyPerSizeCollarType
        );
    }

    /**
     * Inti hitungan Lengan & Kerah - qty dipecah per KOMBINASI ukuran x
     * tipe (bukan cuma per tipe), karena tabel usage-nya sendiri memang
     * matriks ukuran x tipe (satu ukuran+tipe bisa punya angka estimasi
     * beda dari ukuran+tipe lain).
     */
    private static function hitungEstimasiDuaDimensi(string $modelClass, string $relationName, string $fkColumn, array $qtyPerSizeType): array
    {
        $sizeIds = array_keys($qtyPerSizeType);
        if (empty($sizeIds)) {
            return [];
        }

        $usages = $modelClass::with($relationName)
            ->whereIn('jersey_size_id', $sizeIds)
            ->where(function ($q) {
                $q->whereNotNull('usage_meter')->orWhereNotNull('usage_weight_gram');
            })
            ->get();

        $totals = [];
        foreach ($usages as $usage) {
            $related = $usage->{$relationName};
            if (!$related || !$related->is_active) {
                continue;
            }
            $qty = $qtyPerSizeType[$usage->jersey_size_id][$usage->{$fkColumn}] ?? 0;
            if ($qty <= 0) {
                continue;
            }
            self::tambahTotal($totals, $related->id, $related, $usage, $qty);
        }

        return self::urutkanTotal($totals);
    }

    private static function tambahTotal(array &$totals, int $groupId, $entitas, $usage, int $qty): void
    {
        if (!isset($totals[$groupId])) {
            $totals[$groupId] = ['component' => $entitas, 'total_meter' => 0.0, 'total_weight_gram' => 0.0];
        }
        $totals[$groupId]['total_meter'] += (float) $usage->usage_meter * $qty;
        $totals[$groupId]['total_weight_gram'] += (float) $usage->usage_weight_gram * $qty;
    }

    private static function urutkanTotal(array $totals): array
    {
        uasort($totals, fn ($a, $b) => ($a['component']->sort_order ?? 0) <=> ($b['component']->sort_order ?? 0));
        return array_values($totals);
    }

    /**
     * Format gram jadi teks siap tampil - otomatis pakai kg kalau totalnya
     * >= 1000 gram, supaya angka besar tidak ditampilkan mentah dalam gram
     * (mis. "2500 g" jadi "2,50 kg"). Satu fungsi ini dipakai di mana pun
     * berat perlu ditampilkan (Master Data & cetak ringkasan) supaya
     * aturannya konsisten di satu tempat.
     */
    public static function formatBeratGram(float $gram): string
    {
        if ($gram <= 0) {
            return '-';
        }
        if ($gram >= 1000) {
            return number_format($gram / 1000, 2, ',', '.') . ' kg';
        }
        return number_format($gram, 0, ',', '.') . ' g';
    }
}
