<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequest extends Model
{
    use Auditable;

    protected $table = 'purchase_requests';

    protected $fillable = ['nomor', 'tanggal', 'status', 'catatan', 'id_supplier_rencana', 'id_user_pembuat'];

    protected $casts = ['tanggal' => 'date'];

    public function details()
    {
        return $this->hasMany(PurchaseRequestDetail::class, 'id_purchase_request');
    }

    /** Supplier yang direncanakan saat PR dibuat (opsional). */
    public function supplierRencana()
    {
        return $this->belongsTo(Supplier::class, 'id_supplier_rencana', 'id_supplier');
    }

    /** Pesanan Pembelian yang dibuat dari PR ini (supplier sebenarnya ada di sini). */
    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'id_purchase_request');
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'id_user_pembuat', 'id_user');
    }
}
