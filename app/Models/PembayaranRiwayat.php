<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PembayaranRiwayat extends Model
{
    protected $table = 'pembayaran_riwayat';
    protected $primaryKey = 'id_pembayaran';

    // Tabel ini tidak punya kolom created_at/updated_at (cuma tgl_bayar).
    public $timestamps = false;

    protected $fillable = [
        'id_transaksi',
        'nominal',
        'metode',
        'id_bank',
        'tgl_bayar',
        'keterangan',
    ];

    protected $casts = [
        'nominal'   => 'decimal:2',
        'tgl_bayar' => 'datetime',
    ];

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class, 'id_transaksi', 'id_transaksi');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'id_bank', 'id_bank');
    }
}
