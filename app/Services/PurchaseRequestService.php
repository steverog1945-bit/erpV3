<?php

namespace App\Services;

use App\Models\PurchaseRequest;

/**
 * Hook eksekusi persetujuan/penolakan Purchase Request lewat generic
 * ApprovalService (tipe_aksi 'purchase_request'). Persetujuan cuma mengubah
 * status - PR tidak mengubah stok atau keuangan apa pun; baru saat dibuat
 * jadi PO (PurchaseOrderController) alurnya berlanjut.
 */
class PurchaseRequestService
{
    public static function eksekusiPersetujuan(int $id): void
    {
        $pr = PurchaseRequest::whereKey($id)->lockForUpdate()->firstOrFail();

        if ($pr->status !== 'Diajukan') {
            throw new \RuntimeException("Purchase Request {$pr->nomor} tidak dalam status Diajukan (status saat ini: {$pr->status}).");
        }

        $pr->update(['status' => 'Disetujui']);
    }

    public static function eksekusiPenolakan(int $id): void
    {
        $pr = PurchaseRequest::whereKey($id)->lockForUpdate()->firstOrFail();

        if ($pr->status !== 'Diajukan') {
            throw new \RuntimeException("Purchase Request {$pr->nomor} tidak dalam status Diajukan (status saat ini: {$pr->status}).");
        }

        $pr->update(['status' => 'Ditolak']);
    }
}
