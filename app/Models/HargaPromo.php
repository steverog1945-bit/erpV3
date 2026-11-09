<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HargaPromo extends Model
{
    protected $table = 'harga_promo';
    protected $primaryKey = 'id_promo';

    // Tabel ini tidak punya kolom created_at/updated_at.
    public $timestamps = false;

    protected $fillable = [
        'id_produk',
        'harga_promo',
        'tgl_mulai',
        'tgl_selesai',
        'status_promo',
    ];

    protected $casts = [
        'harga_promo' => 'decimal:2',
        'tgl_mulai'   => 'date',
        'tgl_selesai' => 'date',
    ];

    public function produk()
    {
        return $this->belongsTo(Produk::class, 'id_produk', 'id_produk');
    }
}
