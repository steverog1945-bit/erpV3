<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinishingHarga extends Model
{
    protected $table = 'finishing_harga';
    protected $primaryKey = 'id_finishing_harga';

    protected $fillable = [
        'id_finishing',
        'range_min',
        'range_max',
        'harga',
    ];

    protected $casts = [
        'range_min' => 'decimal:2',
        'range_max' => 'decimal:2',
        'harga'     => 'decimal:2',
    ];

    public function finishing()
    {
        return $this->belongsTo(Finishing::class, 'id_finishing', 'id_finishing');
    }
}
