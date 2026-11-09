<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JerseySizeSleeveUsage extends Model
{
    protected $table = 'jersey_size_sleeve_usage';

    protected $fillable = ['jersey_size_id', 'jersey_sleeve_type_id', 'usage_meter', 'usage_weight_gram'];

    protected $casts = ['usage_meter' => 'decimal:3', 'usage_weight_gram' => 'decimal:2'];

    public function size()
    {
        return $this->belongsTo(JerseySize::class, 'jersey_size_id');
    }

    public function sleeveType()
    {
        return $this->belongsTo(JerseySleeveType::class, 'jersey_sleeve_type_id');
    }
}
