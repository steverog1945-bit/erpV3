<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu baru untuk kelola Kategori Kas Kecil (id_menu 45) - sama polanya
 * dengan menu-menu lain yang baru dibuat sesi ini: hanya Administrator
 * (id_role 1) yang diberi akses secara default.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('menus')->where('id_menu', 45)->exists()) {
            DB::table('menus')->insert([
                'id_menu' => 45,
                'nama_menu' => 'Kategori Kas Kecil',
                'url_link' => 'kategori-kas-kecil',
                'parent_id' => 0,
            ]);
        }

        if (!DB::table('role_permissions')->where('id_role', 1)->where('id_menu', 45)->exists()) {
            DB::table('role_permissions')->insert([
                'id_role' => 1,
                'id_menu' => 45,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('id_menu', 45)->delete();
        DB::table('menus')->where('id_menu', 45)->delete();
    }
};
