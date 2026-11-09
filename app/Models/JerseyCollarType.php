<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class JerseyCollarType extends Model
{
    use Auditable;
    protected $table = 'jersey_collar_types';

    protected $fillable = ['name', 'sort_order', 'is_active', 'price_surcharge'];

    protected $casts = ['is_active' => 'boolean', 'price_surcharge' => 'decimal:2'];
}
