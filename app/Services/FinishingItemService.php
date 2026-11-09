<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Rincian finishing per item order (transaksi_detail_finishing): satu item boleh punya beberapa finishing. */
class FinishingItemService
{
    /** @return \Illuminate\Support\Collection id_detail => koleksi baris finishing (nama, status, qty_pakai, biaya, catatan) */
    public static function untukDetail(iterable $idDetail)
    {
        $ids = collect($idDetail)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::table('transaksi_detail_finishing as tf')
            ->join('finishing as f', 'f.id_finishing', '=', 'tf.id_finishing')
            ->whereIn('tf.id_detail', $ids)->orderBy('tf.id')
            ->get(['tf.id_detail', 'f.nama_finishing', 'f.metode_hitung', 'tf.status_finishing', 'tf.qty_pakai', 'tf.biaya', 'tf.catatan'])
            ->groupBy('id_detail');
    }
}
