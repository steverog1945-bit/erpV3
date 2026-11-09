<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JerseyOrderItem extends Model
{
    protected $table = 'jersey_order_items';

    protected $fillable = [
        'jersey_order_line_id',
        'player_name',
        'player_number',
        'size',
        'jersey_size_id',
        'gender',
        'sleeve',
        'jersey_sleeve_type_id',
        'collar',
        'jersey_collar_type_id',
        'notes',
    ];

    public function line()
    {
        return $this->belongsTo(JerseyOrderLine::class, 'jersey_order_line_id');
    }

    // Master ukuran (mendampingi snapshot teks size) - dipakai untuk
    // mengurutkan tabel pemain sesuai sort_order master (S, M, L, XL, ...)
    // alih-alih urutan input, supaya mudah difilter/diperiksa per ukuran.
    public function jerseySize()
    {
        return $this->belongsTo(JerseySize::class, 'jersey_size_id');
    }

    public function sleeveType()
    {
        return $this->belongsTo(JerseySleeveType::class, 'jersey_sleeve_type_id');
    }

    public function collarType()
    {
        return $this->belongsTo(JerseyCollarType::class, 'jersey_collar_type_id');
    }
}
