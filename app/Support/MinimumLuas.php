<?php

namespace App\Support;

/**
 * Helper murni (tanpa side-effect) untuk hitungan minimum luas outdoor.
 *
 * Aturan (keputusan bisnis final A, penyajian HYBRID T4-A keputusan owner):
 *  - Minimum berlaku PER KELOMPOK (kelompok_minimum), bukan per item dan
 *    bukan per bahan.
 *  - Total aktual kelompok = jumlah luas aktual semua item di kelompok itu.
 *  - Jika total aktual < min_luas_total: selisih = min - total, digulung ke
 *    subtotal baris item TERBESAR kelompok itu (bukan lagi baris terpisah).
 *  - Jika total >= min: tidak ada selisih (0).
 *  - Pembulatan konsisten 4 desimal; jangan bandingkan float mentah.
 */
class MinimumLuas
{
    public static function selisih(float $totalAktual, float $minLuasTotal): float
    {
        $diff = round($minLuasTotal - $totalAktual, 4);
        return $diff > 0 ? $diff : 0.0;
    }

    public static function kelompokLabel(?string $kelompok, int $idProduk): string
    {
        $k = trim((string) $kelompok);
        return $k !== '' ? $k : (string) $idProduk;
    }
}
