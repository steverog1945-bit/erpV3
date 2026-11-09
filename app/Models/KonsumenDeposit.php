<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KonsumenDeposit extends Model
{
    use SoftDeletes;

    protected $table = 'konsumen_deposit';
    protected $primaryKey = 'id_deposit';

    protected $fillable = [
        'id_konsumen',
        'nominal',
        'metode_bayar',
        'keterangan',
        'tgl_deposit',
        'id_user',
    ];

    protected $casts = [
        'tgl_deposit' => 'date',
        'nominal'     => 'decimal:2',
    ];

    public function konsumen()
    {
        return $this->belongsTo(Konsumen::class, 'id_konsumen', 'id_konsumen');
    }
}
