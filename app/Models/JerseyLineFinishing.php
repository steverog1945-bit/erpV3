<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Finishing per BARIS order jersey (analog transaksi_detail_finishing di
 * POS). v1 fokus jahit keliling per keping (metode KEPING_ROLL) beserta
 * metode keliling barang, tapi strukturnya generik supaya jenis finishing
 * lain bisa ditambah tanpa rombak struktur.
 */
class JerseyLineFinishing extends Model
{
    protected $table = 'jersey_line_finishing';

    protected $fillable = [
        'jersey_order_line_id',
        'id_finishing',
        'status_finishing',
        'qty_pakai',
        'biaya',
        'tarif_efektif',
        'keping',
        'lebar_potong',
        'tinggi_potong',
        'catatan',
    ];

    protected $casts = [
        'biaya' => 'decimal:2',
        'tarif_efektif' => 'decimal:4',
        'qty_pakai' => 'decimal:4',
        'keping' => 'integer',
        'lebar_potong' => 'decimal:2',
        'tinggi_potong' => 'decimal:2',
    ];

    public function line()
    {
        return $this->belongsTo(JerseyOrderLine::class, 'jersey_order_line_id');
    }

    public function finishing()
    {
        return $this->belongsTo(Finishing::class, 'id_finishing', 'id_finishing');
    }
}
