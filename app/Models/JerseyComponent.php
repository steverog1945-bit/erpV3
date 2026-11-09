<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class JerseyComponent extends Model
{
    use Auditable;
    protected $table = 'jersey_components';

    protected $fillable = ['name', 'sort_order', 'is_active', 'jersey_product_type_id', 'jersey_category_id'];

    protected $casts = ['is_active' => 'boolean'];

    public function productType()
    {
        return $this->belongsTo(JerseyProductType::class, 'jersey_product_type_id');
    }

    public function category()
    {
        return $this->belongsTo(JerseyCategory::class, 'jersey_category_id');
    }
}
