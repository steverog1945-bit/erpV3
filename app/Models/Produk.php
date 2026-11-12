<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produk extends Model
{
    protected $table = 'produk';
    protected $primaryKey = 'id_produk';
    protected $fillable = [
        'nama_produk',
        'id_mesin',
        'metode_hitung',
        'barcode',
        'min_order',
        'min_panjang',
        'min_lebar',
        'keterangan',
        'status',
    ];

    // Relasi Many-to-Many ke Bahan Baku
    public function mesin()
{
    return $this->belongsTo(Mesin::class, 'id_mesin', 'id_mesin');
}
}