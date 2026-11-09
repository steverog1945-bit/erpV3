<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class JerseySize extends Model
{
    use Auditable;
    protected $table = 'jersey_sizes';

    protected $fillable = ['name', 'sort_order', 'is_active', 'price_surcharge', 'kelompok', 'price_percent'];

    protected $casts = [
        'is_active' => 'boolean',
        'price_surcharge' => 'decimal:2',
        'price_percent' => 'decimal:2',
    ];
}
