<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpnameDetail extends Model
{
    protected $table = 'stock_opname_details';

    protected $fillable = ['id_stock_opname', 'id_bahan', 'stok_sistem', 'stok_fisik', 'alasan'];

    protected $casts = [
        'stok_sistem' => 'float',
        'stok_fisik' => 'float',
    ];

    public function bahan()
    {
        return $this->belongsTo(BahanBaku::class, 'id_bahan', 'id_bahan');
    }

    public function selisih(): ?float
    {
        return $this->stok_fisik === null ? null : $this->stok_fisik - $this->stok_sistem;
    }
}
