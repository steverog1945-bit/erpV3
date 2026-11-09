<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JasaHarga extends Model
{
    protected $table = 'jasa_harga';
    protected $primaryKey = 'id_jasa_harga';

    protected $fillable = [
        'id_jasa',
        'range_min',
        'range_max',
        'harga',
    ];

    protected $casts = [
        'range_min' => 'decimal:2',
        'range_max' => 'decimal:2',
        'harga'     => 'decimal:2',
    ];

    public function jasa()
    {
        return $this->belongsTo(Jasa::class, 'id_jasa', 'id_jasa');
    }
}
