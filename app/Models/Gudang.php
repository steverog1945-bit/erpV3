<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gudang extends Model
{
    protected $table = 'gudang';
    protected $primaryKey = 'id_gudang';
    public $timestamps = false;

    protected $fillable = [
        'nama_gudang',
        'alamat',
        'penanggung_jawab',
        'telepon',
    ];

    public function kedatangans()
    {
        return $this->hasMany(KedatanganBahan::class, 'id_gudang', 'id_gudang');
    }
}