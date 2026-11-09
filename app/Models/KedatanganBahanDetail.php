<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KedatanganBahanDetail extends Model
{
    protected $table = 'kedatangan_bahan_detail';
    protected $primaryKey = 'id_detail';
    public $timestamps = false;

    protected $fillable = [
        'id_kedatangan',
        'id_bahan',
        'qty',
        'satuan',
        'status'
    ];

    public function bahan()
    {
        return $this->belongsTo(BahanBaku::class, 'id_bahan', 'id_bahan');
    }
}