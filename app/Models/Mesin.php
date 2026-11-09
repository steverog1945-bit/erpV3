<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Mesin extends Model
{
    use Auditable;
    protected $table = 'mesin';
    protected $primaryKey = 'id_mesin';
    public $timestamps = false;

    protected $fillable = ['nama_mesin', 'tipe_mesin', 'tampil_divisi'];

    protected $casts = ['tampil_divisi' => 'boolean'];

    public function bahanBaku()
    {
        return $this->hasMany(BahanBaku::class, 'id_mesin', 'id_mesin');
    }

    public function finishing()
    {
        return $this->hasMany(Finishing::class, 'id_mesin', 'id_mesin');
    }

    public function karyawan()
    {
        return $this->belongsToMany(Karyawan::class, 'karyawan_mesin', 'id_mesin', 'id_karyawan');
    }
}