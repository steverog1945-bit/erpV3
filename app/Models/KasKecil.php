<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KasKecil extends Model
{
    use SoftDeletes;
    use Auditable;

    protected $table = 'kas_kecil';

    protected $fillable = ['tanggal', 'jenis', 'sumber', 'id_referensi', 'metode', 'id_kategori', 'keterangan', 'nominal', 'bukti_path', 'id_user'];

    protected $casts = [
        'tanggal' => 'date',
        'nominal' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function kategori()
    {
        return $this->belongsTo(KategoriKasKecil::class, 'id_kategori', 'id_kategori_kas_kecil');
    }
}
