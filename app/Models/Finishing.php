<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Finishing extends Model
{
    use Auditable;
    protected $table = 'finishing';
    protected $primaryKey = 'id_finishing';

    protected $fillable = [
        'nama_finishing',
        'harga_default',
        // Lantai biaya finishing per item. Dipakai metode LINEAR_GRID
        // (Potong Manual). 0 = tidak ada minimum charge.
        'minimum_charge',
        'id_satuan',
        'id_kategori',
        'id_mesin',
        'metode_hitung',
        'keterangan',
    ];

    public function mesin()
    {
        return $this->belongsTo(Mesin::class, 'id_mesin');
    }

    public function kategori()
    {
        return $this->belongsTo(Kategori::class, 'id_kategori');
    }
}