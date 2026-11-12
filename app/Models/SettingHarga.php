<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingHarga extends Model
{
    protected $table = 'setting_harga';
    protected $primaryKey = 'id_harga';

    protected $fillable = [
        'id_produk',
        'id_golongan',
        'range_min',
        'range_max',
        'harga_jual',
        'id_satuan',
        'qty_berlaku',
        'id_satuan_berlaku',
    ];

    protected $casts = [
        'range_min'  => 'decimal:2',
        'range_max'  => 'decimal:2',
        'harga_jual' => 'decimal:2',
    ];

    public function produk()
    {
        return $this->belongsTo(Produk::class, 'id_produk', 'id_produk');
    }

    public function golongan()
    {
        return $this->belongsTo(GolonganKonsumen::class, 'id_golongan', 'id_golongan');
    }

    public function satuan()
    {
        return $this->belongsTo(Satuan::class, 'id_satuan', 'id_satuan');
    }
}
