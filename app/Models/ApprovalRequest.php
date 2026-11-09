<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    use Auditable;

    protected $fillable = [
        'tipe_aksi',
        'referensi_type',
        'referensi_id',
        'payload',
        'ringkasan',
        'requested_by',
        'alasan_pengajuan',
        'status',
        'decided_by',
        'catatan_keputusan',
        'decided_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'decided_at' => 'datetime',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by', 'id_user');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by', 'id_user');
    }
}
