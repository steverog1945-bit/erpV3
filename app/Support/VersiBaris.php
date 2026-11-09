<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;

/**
 * Optimistic locking ringan: form edit membawa "versi" baris (= updated_at saat form dibuka) di kolom
 * tersembunyi _versi. Saat simpan, versi itu dibandingkan dengan baris terkini; bila berbeda, ada orang lain
 * yang sudah mengubah data itu -> simpan ditolak (bukan menimpa diam-diam). Tanpa _versi (form lama/jalur API)
 * pengecekan dilewati, jadi pola ini aman dipasang bertahap per halaman.
 */
class VersiBaris
{
    /** Versi baris: updated_at dinormalkan ke "Y-m-d H:i:s" (baris baru tanpa updated_at = kosong). */
    public static function token($row): string
    {
        $v = is_array($row) ? ($row['updated_at'] ?? null) : ($row->updated_at ?? null);
        if ($v === null || $v === '') {
            return '';
        }

        return \Illuminate\Support\Carbon::parse($v)->format('Y-m-d H:i:s');
    }

    /** Input tersembunyi untuk ditaruh di dalam <form>. */
    public static function field($row): HtmlString
    {
        return new HtmlString('<input type="hidden" name="_versi" value="' . e(self::token($row)) . '">');
    }

    /** true = data sudah diubah orang lain sejak form dibuka. */
    public static function bentrok(Request $request, $barisTerkini): bool
    {
        if (!$request->filled('_versi')) {
            return false;
        }

        return (string) $request->input('_versi') !== self::token($barisTerkini);
    }

    public static function pesan($barisTerkini = null): string
    {
        $kapan = $barisTerkini && self::token($barisTerkini) !== '' ? ' (terakhir diubah ' . \Illuminate\Support\Carbon::parse(self::token($barisTerkini))->format('d/m/Y H:i') . ')' : '';

        return 'Data ini sudah diubah oleh pengguna lain' . $kapan . ' sejak Anda membukanya, jadi perubahan Anda TIDAK disimpan agar tidak menimpa. Muat ulang halaman, lalu ulangi perubahan Anda.';
    }
}
