<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    // Append-only - tidak pernah diedit setelah dibuat.
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'user_label',
        'event',
        'auditable_type',
        'auditable_id',
        'keterangan',
        'old_values',
        'new_values',
        'url',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id_user');
    }
}
