<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KartuStok extends Model
{
    protected $table = 'kartu_stok'; // Sesuai nama di PHPMyAdmin Anda
    
    protected $fillable = [
        'id_bahan', 'tanggal', 'tipe', 'qty', 
        'stok_sebelum', 'stok_sesudah', 'referensi', 
        'keterangan', 'created_by'
    ];

    // Relasi ke Bahan Baku
    public function bahan() {
        return $this->belongsTo(BahanBaku::class, 'id_bahan');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id_user');
    }
}