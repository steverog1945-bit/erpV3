<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GolonganKonsumen extends Model
{
    protected $table = 'golongan_konsumen';
    protected $primaryKey = 'id_golongan';
    public $timestamps = false;

    protected $fillable = ['nama_golongan', 'keterangan'];

    public function konsumen()
    {
        return $this->hasMany(Konsumen::class, 'id_golongan', 'id_golongan');
    }
}
