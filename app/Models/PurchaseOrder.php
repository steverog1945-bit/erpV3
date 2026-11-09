<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use Auditable;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'nomor', 'id_supplier', 'id_purchase_request', 'tanggal', 'tgl_pengiriman_estimasi', 'termin_pembayaran',
        'status', 'subtotal', 'diskon', 'pajak_persen', 'ongkir', 'total', 'catatan', 'id_user_pembuat',
    ];

    protected $casts = ['tanggal' => 'date', 'tgl_pengiriman_estimasi' => 'date'];

    public function details()
    {
        return $this->hasMany(PurchaseOrderDetail::class, 'id_purchase_order');
    }

    public function kedatangan()
    {
        return $this->hasMany(KedatanganBahan::class, 'id_purchase_order');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'id_supplier', 'id_supplier');
    }

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class, 'id_purchase_request');
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'id_user_pembuat', 'id_user');
    }
}
