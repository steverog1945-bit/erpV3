<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class JerseyPriceSetting extends Model
{
    use Auditable;
    protected $table = 'jersey_price_settings';

    protected $fillable = [
        'jersey_product_type_id',
        'jersey_category_id',
        'jersey_material_id',
        'qty_min',
        'qty_max',
        'price',
        'is_active',
        'kelompok',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
    ];

    public function productType()
    {
        return $this->belongsTo(JerseyProductType::class, 'jersey_product_type_id');
    }

    public function category()
    {
        return $this->belongsTo(JerseyCategory::class, 'jersey_category_id');
    }

    public function material()
    {
        return $this->belongsTo(JerseyMaterial::class, 'jersey_material_id');
    }
}
