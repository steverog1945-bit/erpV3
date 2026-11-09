<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    use Auditable;

    protected $table = 'quotations';

    protected $fillable = [
        'nomor', 'tanggal', 'berlaku_sampai', 'id_konsumen', 'nama_pelanggan', 'telepon', 'id_golongan',
        'status', 'subtotal', 'diskon', 'total', 'catatan', 'id_transaksi', 'id_user_pembuat',
    ];

    protected $casts = ['tanggal' => 'date', 'berlaku_sampai' => 'date'];

    public function items()
    {
        return $this->hasMany(QuotationItem::class, 'id_quotation');
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'id_user_pembuat', 'id_user');
    }

    /** Draft/Terkirim yang lewat masa berlaku ditampilkan sebagai Kedaluwarsa (status di DB tidak diubah). */
    public function getStatusEfektifAttribute(): string
    {
        return in_array($this->status, ['Draft', 'Terkirim'], true) && $this->berlaku_sampai->lt(now()->startOfDay())
            ? 'Kedaluwarsa'
            : $this->status;
    }
}
