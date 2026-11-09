<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * LuasTagihService - sumber tunggal untuk menghitung luas aktual vs luas ditagih
 * pada transaksi (input order, edit nota, susulan, API checkout, SPK).
 *
 * Mode:
 *   - per_item (default): tiap item ditagih pada luas aktualnya (perilaku lama).
 *   - gabungan: item dengan produk+golongan sama digabung; luas tagih = max(total aktual, min_luas_tagih);
 *     tier dipilih dari luas tagih; total grup = round(luas_tagih x harga/m2); alokasi ke item sebanding luas aktual,
 *     sisa pembulatan ke item terbesar.
 *
 * Tidak ada rumus di JS; UI memakai endpoint pratinjau.
 */
class LuasTagihService
{
    /**
     * Hitung untuk satu grup item (produk sama + golongan sama).
     *
     * @param array $items array of ['p' (cm), 'l' (cm), 'qty' (int)]
     * @param object $produk produk (min_luas_tagih, mode_luas, metode_hitung)
     * @param int $idGolongan
     * @return array{items: array, a_total: float, a_tagih: float, tier: ?object, harga_per_m2: float, total_grup: float, catatan: ?string}
     */
    public static function hitungGrup(array $items, $produk, int $idGolongan): array
    {
        $mode = $produk->mode_luas ?? 'per_item';
        $minLuas = $produk->min_luas_tagih !== null ? (float) $produk->min_luas_tagih : null;

        // luas aktual per item (m2)
        $luasItems = [];
        $aTotal = 0.0;
        foreach ($items as $it) {
            $p = (float) ($it['p'] ?? 0);
            $l = (float) ($it['l'] ?? 0);
            $qty = (float) ($it['qty'] ?? 0);
            $luas = ($p * $l * $qty) / 10000.0;
            $luasItems[] = $luas;
            $aTotal += $luas;
        }

        // mode per_item atau min kosong: perilaku lama (tiap item ditagih pada luas aktualnya)
        if ($mode !== 'gabungan' || $minLuas === null || $minLuas <= 0) {
            $totalGrup = 0.0;
            $itemResults = [];
            foreach ($items as $idx => $it) {
                $luas = $luasItems[$idx];
                $tier = self::tierUntuk($produk->id_produk, $idGolongan, $luas);
                $harga = $tier ? (float) $tier->harga_jual : 0.0;
                $sub = round($luas * $harga, 2);
                $totalGrup += $sub;
                $itemResults[] = [
                    'luas_aktual' => $luas,
                    'luas_ditagih' => $luas,
                    'subtotal' => $sub,
                    'harga_per_m2' => $harga,
                ];
            }
            return [
                'items' => $itemResults,
                'a_total' => $aTotal,
                'a_tagih' => $aTotal,
                'tier' => null,
                'harga_per_m2' => 0.0,
                'total_grup' => $totalGrup,
                'catatan' => null,
            ];
        }

        // mode gabungan
        $aTagih = max($aTotal, $minLuas);
        $tier = self::tierUntuk($produk->id_produk, $idGolongan, $aTagih);
        $harga = $tier ? (float) $tier->harga_jual : 0.0;
        $totalGrup = round($aTagih * $harga, 2);

        // alokasi sebanding luas aktual, sisa pembulatan ke item terbesar
        $itemResults = [];
        $alokasi = [];
        $sumLuas = $aTotal > 0 ? $aTotal : 1.0;
        $sisa = $totalGrup;
        $terbesarIdx = 0;
        $maxLuas = -1.0;
        foreach ($items as $idx => $it) {
            if ($luasItems[$idx] > $maxLuas) { $maxLuas = $luasItems[$idx]; $terbesarIdx = $idx; }
        }
        foreach ($items as $idx => $it) {
            if ($idx === $terbesarIdx) continue;
            $share = round($totalGrup * ($luasItems[$idx] / $sumLuas), 2);
            $alokasi[$idx] = $share;
            $sisa -= $share;
        }
        $alokasi[$terbesarIdx] = round($sisa, 2);

        foreach ($items as $idx => $it) {
            $itemResults[] = [
                'luas_aktual' => $luasItems[$idx],
                'luas_ditagih' => ($aTotal > 0) ? ($luasItems[$idx] / $sumLuas) * $aTagih : 0.0,
                'subtotal' => $alokasi[$idx],
                'harga_per_m2' => $harga,
            ];
        }

        $catatan = ($aTagih > $aTotal + 0.0000001) ? ('Minimum cetak ' . $minLuas . ' m2 diterapkan') : null;

        return [
            'items' => $itemResults,
            'a_total' => $aTotal,
            'a_tagih' => $aTagih,
            'tier' => $tier,
            'harga_per_m2' => $harga,
            'total_grup' => $totalGrup,
            'catatan' => $catatan,
        ];
    }

    /**
     * Tier yang min-nya terbesar <= qty (celah antar tier tidak boleh menghasilkan harga kosong).
     */
    public static function tierUntuk(int $idProduk, int $idGolongan, float $qty): ?object
    {
        return DB::table('setting_harga')
            ->where('id_produk', $idProduk)
            ->where('id_golongan', $idGolongan)
            ->where('range_min', '<=', $qty)
            ->where(function ($q) use ($qty) {
                $q->whereNull('range_max')->orWhere('range_max', '>=', $qty);
            })
            ->orderBy('range_min', 'desc')
            ->first();
    }
}