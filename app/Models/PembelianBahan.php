<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PembelianBahan extends Model
{
    use SoftDeletes;
    use Auditable;

    protected $table = 'pembelian_bahan';
    protected $primaryKey = 'id_pembelian';

    protected $fillable = [
        'id_kedatangan',
        'no_faktur',
        'tgl_transaksi',
        'tipe_pembelian',
        'jatuh_tempo_hari',
        'jatuh_tempo_tgl',
        'status_pajak',
        'include_pajak',
        'grand_total',
        'ongkir',
        'biaya_layanan',
        'voucher',
    ];

    public function detail()
    {
        return $this->hasMany(PembelianBahanDetail::class,'id_pembelian');
    }

    public function kedatangan()
    {
        return $this->belongsTo(KedatanganBahan::class,'id_kedatangan');
    }
}