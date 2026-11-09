<?php

namespace App\Support;

/**
 * Omzet per item setelah diskon nota. Diskon disimpan di transaksi (bukan per item), sedangkan jurnal mengakui
 * pendapatan sebesar total nota setelah diskon. Laporan per item membagi diskon itu sebanding subtotal item
 * supaya jumlah omzetnya sama dengan pendapatan di jurnal.
 */
class OmzetBersih
{
    /** Ekspresi SQL omzet bersih satu baris transaksi_detail; $d dan $t adalah alias tabel detail dan transaksi. */
    public static function sql(string $d = 'd', string $t = 't'): string
    {
        return "({$d}.subtotal - COALESCE({$t}.diskon, 0) * {$d}.subtotal / COALESCE(NULLIF("
            . "(SELECT SUM(dx.subtotal) FROM transaksi_detail dx WHERE dx.id_transaksi = {$t}.id_transaksi), 0), 1))";
    }
}
