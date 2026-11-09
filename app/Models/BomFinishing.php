<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class BomFinishing extends Model
{
    protected $table = 'bom_finishing';
    protected $primaryKey = 'id_bom_finishing';

    protected $fillable = [
        'id_produk',
        'id_finishing',
        'qty'
    ];

    public function finishing()
    {
        return $this->belongsTo(Finishing::class,'id_finishing');
    }

    public function produk()
    {
        return $this->belongsTo(Produk::class, 'id_produk', 'id_produk');
    }
}