<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Konsumen extends Model
{
    protected $table = 'konsumen';
    protected $primaryKey = 'id_konsumen';
    public $timestamps = false;

    protected $fillable = [
        'nama_konsumen',
        'telepon',
        'alamat',
        'id_golongan',
        'tipe_konsumen',
    ];

    public function deposits()
    {
        return $this->hasMany(KonsumenDeposit::class, 'id_konsumen', 'id_konsumen');
    }

    public function golongan()
    {
        return $this->belongsTo(GolonganKonsumen::class, 'id_golongan', 'id_golongan');
    }
}
