<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Merk extends Model
{
    protected $table = 'merk';
    protected $primaryKey = 'id_merk';

    protected $fillable = ['nama_merk', 'keterangan'];

    public function bahanBaku()
    {
        return $this->hasMany(BahanBaku::class, 'id_merk', 'id_merk');
    }
}
