<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    protected $table = 'role_permissions';

    // Primary key komposit (id_role, id_menu) - Eloquent tidak mendukung PK
    // komposit secara native, jadi model ini tidak dipakai lewat find()/
    // save() per baris seperti model biasa. Tetap dibuat supaya relasi
    // Role::menus() (belongsToMany) di Role.php punya representasi tabel
    // pivot yang eksplisit, dan supaya query manual bisa pakai Eloquent
    // query builder (where()->get() dst) bukan cuma DB::table().
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_role',
        'id_menu',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class, 'id_role', 'id_role');
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class, 'id_menu', 'id_menu');
    }
}
