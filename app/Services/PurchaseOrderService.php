<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

/**
 * Sinkronisasi penerimaan barang ke Purchase Order. qty_diterima SELALU
 * dihitung ulang dari semua Kedatangan Bahan berstatus Selesai yang tertaut
 * ke PO (bukan di-increment/decrement) - jadi tetap benar lewat semua jalur
 * yang mengubah stok kedatangan: validasi, edit, hapus, hapus massal.
 */
class PurchaseOrderService
{
    public static function sinkronPenerimaan(?int $idPurchaseOrder): void
    {
        if (!$idPurchaseOrder) {
            return;
        }

        DB::transaction(function () use ($idPurchaseOrder) {
            $po = PurchaseOrder::whereKey($idPurchaseOrder)->lockForUpdate()->first();

            if (!$po) {
                return;
            }

            $diterima = DB::table('kedatangan_bahan_detail as d')
                ->join('kedatangan_bahan as k', 'k.id_kedatangan', '=', 'd.id_kedatangan')
                ->where('k.id_purchase_order', $po->id)
                ->where('k.status', 'Selesai')
                ->groupBy('d.id_bahan')
                ->selectRaw('d.id_bahan, SUM(d.qty) as total')
                ->pluck('total', 'id_bahan');

            $lengkap = true;
            $adaTerima = false;

            foreach ($po->details as $detail) {
                $qty = (float) ($diterima[$detail->id_bahan] ?? 0);
                $detail->update(['qty_diterima' => $qty]);

                $adaTerima = $adaTerima || $qty > 0;
                $lengkap = $lengkap && $qty >= (float) $detail->qty;
            }

            // Draft & Dibatalkan tidak pernah diubah statusnya oleh penerimaan.
            if (in_array($po->status, ['Dipesan', 'Diterima Sebagian', 'Selesai'], true)) {
                $po->update(['status' => $lengkap ? 'Selesai' : ($adaTerima ? 'Diterima Sebagian' : 'Dipesan')]);
            }
        });
    }
}
