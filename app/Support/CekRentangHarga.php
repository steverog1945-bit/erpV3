<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Aturan bisnis satu untuk SEMUA tabel harga bertingkat: satu induk hanya boleh
 * punya satu tier untuk satu rentang qty. Dua tier 1-10 dan 5-15 berarti
 * ambigu: qty 7 punya dua harga, dan mana yang dipakai bergantung pada urutan
 * baris - bukan keputusan bisnis yang bisa dipertanggungjawabkan.
 *
 * Kenapa aturan ini harus di server, bukan di tombol:
 *
 * 1. Form tier harga dikirim lewat fetch() dengan body JSON, bukan submit form
 *    native. submit_id tidak pernah ikut, jadi SubmitSekaliJalan tidak
 *   menghitung apa pun di jalur ini - sudah dibuktikan oleh test
 *    TierHargaKirimGandaTest (dua POST identik tanpa submit_id menghasilkan
 *    dua baris sebelum perbaikan ini).
 * 2. Tidak ada UNIQUE constraint di ketiga tabel (hanya primary key), jadi
 *    database tidak akan menolak sendiri.
 * 3. Klien bisa dimatikan; aturan ini tidak boleh bergantung padanya.
 *
 * Batas yang perlu diketahui: rentang TUMPANG TINDIH tidak bisa dinyatakan
 * sebagai UNIQUE index di MySQL (index hanya bisa menyamakan nilai, bukan
 * membandingkan interval). Jadi pemeriksa ini tetap wajib ada meski nanti
 * ada unique index - keduanya melengkapi, bukan menggantikan.
 */
class CekRentangHarga
{
    /**
     * True bila ada tier lain pada induk yang sama yang rentangnya bertabrakan.
     *
     * Tabrakan terjadi bila: ada untuk interval (min_baru, max_baru ∩ tak_terbatas),
     * yaitu tier lama yang mulai <= max_baru DAN berakhir >= min_baru.
     * range_max NULL berarti terbuka ke atas, jadi dianggap 999.999.999.
     *
     * @param  string  $tabel      nama tabel tier
     * @param  string  $kolomInduk kolom FK ke induk (id_produk/id_jasa/id_finishing)
     * @param  array   $kolomExtra kolom lain yang ikut membatasi (mis. id_golongan)
     */
    public static function tumpangTindih(
        string $tabel,
        string $kolomInduk,
        int|string $indukId,
        array $kolomExtra,
        int|float $min,
        int|float|null $max,
        ?int $excludeId = null,
        string $kolomPk = 'id'
    ): bool {
        $maks = $max ?? 999999999;

        return DB::table($tabel)
            ->where($kolomInduk, $indukId)
            ->when($excludeId, fn ($q) => $q->where($kolomPk, '!=', $excludeId))
            ->when(
                $kolomExtra,
                fn ($q) => $q->where(function ($q) use ($kolomExtra) {
                    foreach ($kolomExtra as $k => $v) {
                        $q->where($k, $v);
                    }
                })
            )
            ->where('range_min', '<=', $maks)
            ->where(function ($q) use ($min) {
                $q->whereNull('range_max')->orWhere('range_max', '>=', $min);
            })
            ->exists();
    }
}