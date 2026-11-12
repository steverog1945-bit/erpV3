<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KasHarian extends Model
{
    protected $table = 'kas_harian';
    protected $primaryKey = 'id_kas';

    protected $fillable = [
        'tgl_kas',
        'id_user',
        'saldo_awal',
        'total_tunai_masuk',
        'total_transfer_masuk',
        'total_debit_masuk',
        'total_tunai_keluar',
        'saldo_akhir',
        'saldo_fisik',
        'selisih',
        'total_transfer',
        'total_diskon',
        'status_lock',
        'waktu_tutup',
        'keterangan_closing',
        'status_rolling',
    ];

    protected $casts = [
        'tgl_kas'              => 'date',
        'saldo_awal'           => 'decimal:2',
        'total_tunai_masuk'    => 'decimal:2',
        'total_transfer_masuk' => 'decimal:2',
        'total_debit_masuk'    => 'decimal:2',
        'total_tunai_keluar'   => 'decimal:2',
        'saldo_akhir'          => 'decimal:2',
        'saldo_fisik'          => 'decimal:2',
        'selisih'              => 'decimal:2',
        'total_transfer'       => 'decimal:2',
        'total_diskon'         => 'decimal:2',
        'waktu_tutup'          => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
