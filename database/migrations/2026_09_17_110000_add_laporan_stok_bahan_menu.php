<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu baru untuk Laporan Stok Bahan Baku (id_menu 46) - evaluasi
 * pengadaan/pembelanjaan bahan (badge stok menipis/habis). Mengikuti pola
 * menu laporan lain: hanya Administrator (id_role 1) yang diberi akses
 * secara default.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('menus')->where('id_menu', 46)->exists()) {
            DB::table('menus')->insert([
                'id_menu' => 46,
                'nama_menu' => 'Laporan Stok Bahan Baku',
                'url_link' => 'laporan/stok-bahan',
                'parent_id' => 0,
            ]);
        }

        if (!DB::table('role_permissions')->where('id_role', 1)->where('id_menu', 46)->exists()) {
            DB::table('role_permissions')->insert([
                'id_role' => 1,
                'id_menu' => 46,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('id_menu', 46)->delete();
        DB::table('menus')->where('id_menu', 46)->delete();
    }
};
