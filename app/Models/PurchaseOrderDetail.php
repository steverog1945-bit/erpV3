<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderDetail extends Model
{
    protected $table = 'purchase_order_details';

    protected $fillable = ['id_purchase_order', 'id_bahan', 'qty', 'harga', 'subtotal', 'catatan', 'qty_diterima'];

    public function bahan()
    {
        return $this->belongsTo(BahanBaku::class, 'id_bahan', 'id_bahan');
    }
}
