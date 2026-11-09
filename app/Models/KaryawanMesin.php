<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KaryawanMesin extends Model
{
    protected $table = 'karyawan_mesin';

    protected $fillable = [
        'id_karyawan',
        'id_mesin',
    ];

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class, 'id_karyawan', 'id_karyawan');
    }

    public function mesin()
    {
        return $this->belongsTo(Mesin::class, 'id_mesin', 'id_mesin');
    }
}
