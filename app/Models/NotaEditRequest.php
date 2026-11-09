<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotaEditRequest extends Model
{
    protected $table = 'nota_edit_request';
    protected $primaryKey = 'id_request';

    protected $fillable = [
        'id_transaksi',
        'id_user_requester',
        'alasan',
        'status',
        'id_user_approver',
        'catatan_approver',
        'tgl_keputusan',
        'tgl_selesai',
        'id_transaksi_koreksi',
    ];

    protected $casts = [
        'tgl_keputusan' => 'datetime',
        'tgl_selesai'   => 'datetime',
    ];

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class, 'id_transaksi', 'id_transaksi');
    }

    public function transaksiKoreksi()
    {
        return $this->belongsTo(Transaksi::class, 'id_transaksi_koreksi', 'id_transaksi');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'id_user_requester', 'id_user');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'id_user_approver', 'id_user');
    }
}
