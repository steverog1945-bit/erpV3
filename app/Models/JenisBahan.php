<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JenisBahan extends Model
{
    protected $table = 'jenis_bahan';
    protected $primaryKey = 'id_jenis_bahan';

    protected $fillable = ['nama_jenis', 'keterangan'];

    public function bahanBaku()
    {
        return $this->hasMany(BahanBaku::class, 'id_jenis_bahan', 'id_jenis_bahan');
    }
}
