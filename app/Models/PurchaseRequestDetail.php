<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequestDetail extends Model
{
    protected $table = 'purchase_request_details';

    protected $fillable = ['id_purchase_request', 'id_bahan', 'qty', 'catatan'];

    public function bahan()
    {
        return $this->belongsTo(BahanBaku::class, 'id_bahan', 'id_bahan');
    }
}
