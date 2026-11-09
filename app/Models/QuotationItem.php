<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    protected $table = 'quotation_items';

    protected $fillable = [
        'id_quotation', 'tipe', 'id_produk', 'id_jasa', 'id_finishing', 'nama_item', 'p_ukuran', 'l_ukuran',
        'qty', 'qty_hitung', 'harga_satuan', 'biaya_finishing', 'biaya_desain', 'subtotal', 'catatan',
    ];
}
