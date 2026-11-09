<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JerseyOrderLineMaterial extends Model
{
    protected $table = 'jersey_order_line_materials';

    protected $fillable = [
        'jersey_order_line_id',
        'nama_barang',
        'warna',
        'penggunaan_kg',
        'penggunaan_meter',
        'penggunaan_yard',
        'keterangan',
    ];

    public function line()
    {
        return $this->belongsTo(JerseyOrderLine::class, 'jersey_order_line_id');
    }
}
