<?php

use App\Support\Angka;

if (!function_exists('angka')) {
    /** Angka tampilan Indonesia ("200.000", "12,5"), nol pecahan dipotong. */
    function angka(mixed $nilai, int $maks = 2): string
    {
        return Angka::format($nilai, $maks);
    }
}

if (!function_exists('rupiah')) {
    function rupiah(mixed $nilai, bool $denganAwalan = true): string
    {
        return Angka::rupiah($nilai, $denganAwalan);
    }
}

if (!function_exists('angka_polos')) {
    /** Angka polos untuk input type=number / atribut data ("12.5"). */
    function angka_polos(mixed $nilai, int $maks = 4): string
    {
        return Angka::polos($nilai, $maks);
    }
}

if (!function_exists('angka_input')) {
    /** Isi awal kolom uang yang diformat saat mengetik. */
    function angka_input(mixed $nilai, int $maks = 0): string
    {
        return Angka::input($nilai, $maks);
    }
}
