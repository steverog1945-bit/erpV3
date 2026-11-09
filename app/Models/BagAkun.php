<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BagAkun extends Model
{
    protected $table = 'bag_akun';
    protected $primaryKey = 'id_akun';

    protected $fillable = [
        'kode_akun',
        'nama_akun',
        'tipe_akun',
        'saldo_normal',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function jurnal()
    {
        return $this->hasMany(JurnalUmum::class, 'kode_akun', 'kode_akun');
    }
}
