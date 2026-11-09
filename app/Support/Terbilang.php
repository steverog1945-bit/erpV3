<?php

namespace App\Support;

/** Angka rupiah menjadi kata (untuk dokumen resmi: penawaran, kuitansi). Bilangan bulat, sampai triliun. */
class Terbilang
{
    private const SATUAN = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    public static function rupiah(float|int|string $nilai): string
    {
        $n = (int) round((float) $nilai);
        if ($n === 0) {
            return 'Nol rupiah';
        }

        $kata = trim(preg_replace('/\s+/', ' ', ($n < 0 ? 'minus ' : '') . self::kata(abs($n))));

        return ucfirst($kata) . ' rupiah';
    }

    private static function kata(int $n): string
    {
        return match (true) {
            $n < 12 => self::SATUAN[$n],
            $n < 20 => self::kata($n - 10) . ' belas',
            $n < 100 => self::kata(intdiv($n, 10)) . ' puluh ' . self::kata($n % 10),
            $n < 200 => 'seratus ' . self::kata($n - 100),
            $n < 1000 => self::kata(intdiv($n, 100)) . ' ratus ' . self::kata($n % 100),
            $n < 2000 => 'seribu ' . self::kata($n - 1000),
            $n < 1000000 => self::kata(intdiv($n, 1000)) . ' ribu ' . self::kata($n % 1000),
            $n < 1000000000 => self::kata(intdiv($n, 1000000)) . ' juta ' . self::kata($n % 1000000),
            $n < 1000000000000 => self::kata(intdiv($n, 1000000000)) . ' miliar ' . self::kata($n % 1000000000),
            default => self::kata(intdiv($n, 1000000000000)) . ' triliun ' . self::kata($n % 1000000000000),
        };
    }
}
