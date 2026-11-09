<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JerseySizeComponentUsage extends Model
{
    protected $table = 'jersey_size_component_usage';

    protected $fillable = ['jersey_size_id', 'jersey_component_id', 'usage_meter', 'usage_weight_gram'];

    protected $casts = ['usage_meter' => 'decimal:3', 'usage_weight_gram' => 'decimal:2'];

    public function size()
    {
        return $this->belongsTo(JerseySize::class, 'jersey_size_id');
    }

    public function component()
    {
        return $this->belongsTo(JerseyComponent::class, 'jersey_component_id');
    }
}
