<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu baru untuk Laporan HPP & Margin (id_menu 47) - membaca
 * transaksi_detail.hpp_snapshot yang sudah lama dihitung otomatis tapi
 * belum pernah ditampilkan di mana pun. Mengikuti pola menu laporan lain:
 * hanya Administrator (id_role 1) yang diberi akses secara default (data
 * margin/keuntungan sensitif).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('menus')->where('id_menu', 47)->exists()) {
            DB::table('menus')->insert([
                'id_menu' => 47,
                'nama_menu' => 'Laporan HPP & Margin',
                'url_link' => 'laporan/hpp',
                'parent_id' => 0,
            ]);
        }

        if (!DB::table('role_permissions')->where('id_role', 1)->where('id_menu', 47)->exists()) {
            DB::table('role_permissions')->insert([
                'id_role' => 1,
                'id_menu' => 47,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('id_menu', 47)->delete();
        DB::table('menus')->where('id_menu', 47)->delete();
    }
};
