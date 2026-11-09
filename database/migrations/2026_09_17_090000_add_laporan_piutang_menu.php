<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu baru untuk Laporan Daftar Piutang (id_menu 44) - mengikuti pola
 * persis laporan lain (25/26/29/40/43): hanya Administrator (id_role 1)
 * yang diberi akses secara default, sama seperti laporan lain saat
 * pertama dibuat.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('menus')->where('id_menu', 44)->exists()) {
            DB::table('menus')->insert([
                'id_menu' => 44,
                'nama_menu' => 'Laporan Piutang',
                'url_link' => 'laporan/piutang',
                'parent_id' => 0,
            ]);
        }

        if (!DB::table('role_permissions')->where('id_role', 1)->where('id_menu', 44)->exists()) {
            DB::table('role_permissions')->insert([
                'id_role' => 1,
                'id_menu' => 44,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('id_menu', 44)->delete();
        DB::table('menus')->where('id_menu', 44)->delete();
    }
};
