<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KasManual extends Model
{
    protected $table = 'kas_manual';
    protected $primaryKey = 'id_kas_manual';

    protected $fillable = [
        'id_kas',
        'waktu',
        'keterangan',
        'status',
        'nominal',
        'id_user',
    ];

    protected $casts = [
        'waktu'   => 'datetime',
        'nominal' => 'decimal:2',
    ];

    public function kas()
    {
        return $this->belongsTo(KasHarian::class, 'id_kas', 'id_kas');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
