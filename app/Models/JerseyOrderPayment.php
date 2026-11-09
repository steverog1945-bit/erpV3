<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JerseyOrderPayment extends Model
{
    use SoftDeletes;
    use Auditable;

    protected $table = 'jersey_order_payments';

    protected $fillable = [
        'jersey_order_id',
        'tanggal_bayar',
        'jumlah',
        'metode_bayar',
        'bukti_path',
        'keterangan',
        'uang_diterima',
        'kembalian',
        'created_by',
    ];

    protected $casts = [
        'tanggal_bayar' => 'date',
        'jumlah' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(JerseyOrder::class, 'jersey_order_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id_user');
    }
}
