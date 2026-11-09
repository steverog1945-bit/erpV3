<?php

namespace App\Support;

/**
 * Sel CSV yang diawali =, +, -, @, tab, atau CR dibaca Excel/Sheets sebagai rumus (CSV injection): teks yang diketik
 * pengguna lain (nama bahan, alasan pengajuan) bisa menjalankan rumus di komputer pemilik saat berkas dibuka.
 * Sel teks seperti itu diberi awalan tanda kutip; angka (termasuk negatif) dibiarkan apa adanya.
 */
class CsvAman
{
    public static function baris(array $sel): array
    {
        return array_map(static function ($v) {
            if (is_string($v) && $v !== '' && !is_numeric($v) && in_array($v[0], ['=', '+', '-', '@', "	", ""], true)) {
                return "'" . $v;
            }

            return $v;
        }, $sel);
    }
}
