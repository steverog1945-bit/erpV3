<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class KategoriKasKecil extends Model
{
    use Auditable;
    protected $table = 'kategori_kas_kecil';
    protected $primaryKey = 'id_kategori_kas_kecil';

    protected $fillable = ['nama_kategori', 'jenis', 'kode_akun', 'dibayar_pemilik'];

    protected $casts = ['dibayar_pemilik' => 'boolean'];

    public function kasKecil()
    {
        return $this->hasMany(KasKecil::class, 'id_kategori', 'id_kategori_kas_kecil');
    }
}
