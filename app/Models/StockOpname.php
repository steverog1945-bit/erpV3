<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{
    use Auditable;

    protected $table = 'stock_opnames';

    protected $fillable = ['nomor', 'id_gudang', 'tanggal', 'status', 'catatan', 'id_user_pembuat'];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function details()
    {
        return $this->hasMany(StockOpnameDetail::class, 'id_stock_opname');
    }

    public function gudang()
    {
        return $this->belongsTo(Gudang::class, 'id_gudang', 'id_gudang');
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'id_user_pembuat', 'id_user');
    }
}
