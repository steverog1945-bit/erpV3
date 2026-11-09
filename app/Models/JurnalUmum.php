<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JurnalUmum extends Model
{
    use SoftDeletes;

    protected $table = 'jurnal_umum';
    protected $primaryKey = 'id_jurnal';

    protected $fillable = [
        'tgl_jurnal',
        'no_referensi',
        'keterangan',
        'kode_akun',
        'debet',
        'kredit',
    ];

    protected $casts = [
        'tgl_jurnal' => 'date',
        'debet'      => 'decimal:2',
        'kredit'     => 'decimal:2',
    ];

    public function akun()
    {
        return $this->belongsTo(BagAkun::class, 'kode_akun', 'kode_akun');
    }
}
