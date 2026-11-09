<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Karyawan extends Model
{
    use Auditable;
    use HasFactory;

    protected $table = 'karyawan';
    
    // INI KUNCINYA: Laravel harus tahu primary key-nya bukan 'id'
    protected $primaryKey = 'id_karyawan'; 

    // Jika id_karyawan bukan angka auto_increment (tapi di SQL Anda iya, jadi ini opsional)
    public $incrementing = true;

    protected $fillable = [
        'nama_karyawan', 
        'bagian', 
        'telepon', 
        'status_aktif'
    ];

    // Relasi ke User (untuk proteksi saat hapus)
    public function user()
    {
        return $this->hasOne(User::class, 'id_karyawan');
    }

    // Mesin/divisi yang jadi tanggung jawab karyawan ini - dipakai untuk
    // membatasi tampilan SPK ke divisi yang relevan saat operator login.
    public function mesin()
    {
        return $this->belongsToMany(Mesin::class, 'karyawan_mesin', 'id_karyawan', 'id_mesin');
    }
}