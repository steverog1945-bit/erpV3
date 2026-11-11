<?php

namespace App\Support;

/**
 * Kode unik konsumen (mis. CST-000123) yang ditampilkan ke pengguna.
 * Angka dasar tetap id_konsumen (auto-increment), jadi dijamin unik tanpa
 * kolom baru; hanya format tampilan & ekstraksi ID dari input yang dibagikan.
 */
class KonsumenKode
{
    /** "#CST-000123" / "cst-123" / "123" -> 123 ; selain itu -> null */
    public static function keId(?string $q): ?int
    {
        $q = trim((string) $q);
        if ($q === '') {
            return null;
        }
        if (preg_match('/^#?CST-?0*(\d{1,10})$/i', $q, $m)) {
            return (int) $m[1];
        }
        if (ctype_digit($q)) {
            return (int) $q;
        }
        return null;
    }

    /** 123 -> "CST-000123" */
    public static function dariId(int $id): string
    {
        return 'CST-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}
