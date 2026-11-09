<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class JerseyMaterial extends Model
{
    use Auditable;
    protected $table = 'jersey_materials';

    protected $fillable = ['name', 'is_active', 'id_bahan'];

    protected $casts = ['is_active' => 'boolean'];

    /** Bahan baku (gudang) yang disinkronkan dengan bahan jersey ini. */
    public function bahan()
    {
        return $this->belongsTo(BahanBaku::class, 'id_bahan', 'id_bahan');
    }
}
