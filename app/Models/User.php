<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;
    use Auditable;

    // password TIDAK pernah masuk audit_logs (hash pun tidak perlu
    // tersimpan di sana - riwayat "kapan password diganti" cukup dari
    // event 'updated'-nya sendiri, isinya tidak relevan/berisiko disimpan).
    // API-5: api_token (legacy) & api_token_hash ikut disembunyikan -
    // kredensial tidak boleh tumpah ke audit_logs, bentuk apa pun.
    protected array $hiddenFromAudit = ['password', 'remember_token', 'api_token', 'api_token_hash'];

    protected $table = 'users';
    protected $primaryKey = 'id_user';

    // 'password' dan 'id_role' SENGAJA tidak masuk $fillable (kolom
    // sensitif/privilege) - diisi lewat assignment eksplisit di
    // UserController, bukan mass-assignment, supaya endpoint baru yang
    // ceroboh (mis. pakai $request->all()) tidak bisa diam-diam mengubah
    // role/password lewat form field yang tidak seharusnya. API-5:
    // api_token juga dikeluarkan dari $fillable - token hanya ditulis
    // lewat App\Services\ApiTokenService (hash + kedaluwarsa).
    protected $fillable = [
        'id_karyawan', 'username', 'foto_profil',
    ];

    // Kredensial (web & API) tidak boleh tumpah dari serialisasi JSON
    // mana pun: password (bcrypt) dan remember_token (recall sesi) kini
    // ikut disembunyikan bersama kredensial API - SEC-USER-001. Pembacaan
    // atribut langsung (Hash::check, setRememberToken, dsb.) TIDAK
    // terpengaruh - $hidden hanya lapis serialisasi, bukan akses atribut.
    protected $hidden = ['password', 'remember_token', 'api_token', 'api_token_hash'];

    public function karyawan()
    {
    return $this->belongsTo(Karyawan::class, 'id_karyawan');
    }
    // Relasi ke tabel Role
    public function role()
    {
        return $this->belongsTo(Role::class, 'id_role');
    }
}