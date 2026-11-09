<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransaksiDetail extends Model
{
    protected $table = 'transaksi_detail';
    protected $primaryKey = 'id_detail';

    protected $fillable = [
        'id_transaksi',
        'id_produk',
        'id_jasa',
        'id_bahan',
        'id_finishing',
        'biaya_finishing',
        'biaya_cetak',
        'biaya_desain',
        'nama_file',
        'thumbnail',
        'p_ukuran',
        'l_ukuran',
        'qty',
        'status_urgent',
        'status_urgensi',
        'status_finishing',
        'qty_cetak',
        'catatan_produksi',
        'subtotal',
        'potongan',
        'hpp_snapshot',
        'harga_jual_snapshot',
        'status_spk',
    ];

    protected $casts = [
        'biaya_finishing'     => 'decimal:2',
        'biaya_cetak'         => 'decimal:2',
        'biaya_desain'        => 'decimal:2',
        'p_ukuran'            => 'decimal:2',
        'l_ukuran'            => 'decimal:2',
        'status_urgent'       => 'boolean',
        'subtotal'            => 'decimal:2',
        'potongan'            => 'decimal:2',
        'hpp_snapshot'        => 'decimal:2',
        'harga_jual_snapshot' => 'decimal:2',
    ];

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class, 'id_transaksi', 'id_transaksi');
    }

    public function produk()
    {
        return $this->belongsTo(Produk::class, 'id_produk', 'id_produk');
    }

    public function jasa()
    {
        return $this->belongsTo(Jasa::class, 'id_jasa', 'id_jasa');
    }

    public function bahan()
    {
        return $this->belongsTo(BahanBaku::class, 'id_bahan', 'id_bahan');
    }

    public function finishing()
    {
        return $this->belongsTo(Finishing::class, 'id_finishing', 'id_finishing');
    }
}
