<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PembelianBahanDetail extends Model
{
    protected $table = 'pembelian_bahan_detail';
    protected $primaryKey = 'id_detail';

    public $timestamps = false;

    protected $fillable = [
        'id_pembelian',
        'id_bahan',
        'qty',
        'harga',
        'diskon',
        'potongan',
        'subtotal'
    ];

    public function bahan()
    {
        return $this->belongsTo(BahanBaku::class,'id_bahan');
    }
}