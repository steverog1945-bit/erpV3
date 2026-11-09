<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';
    protected $primaryKey = 'id_role';
    public $timestamps = false;

    protected $fillable = ['nama_role'];

    protected $casts = [
        'boleh_lihat_harga' => 'boolean',
        'boleh_input_biaya_desain' => 'boolean',
    ];

    public function menus()
    {
        return $this->belongsToMany(Menu::class, 'role_permissions', 'id_role', 'id_menu');
    }
}