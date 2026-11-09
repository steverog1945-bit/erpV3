<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu Penjualan Barang (id_menu 48) - jual bahan baku langsung. Diberikan
 * ke Administrator (default semua menu baru) & Kasir (role id 3, yang
 * sungguhan menjalankan penjualan sehari-hari).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('menus')->where('id_menu', 48)->exists()) {
            DB::table('menus')->insert([
                'id_menu' => 48,
                'nama_menu' => 'Penjualan Barang',
                'url_link' => 'penjualan-barang',
                'parent_id' => 0,
            ]);
        }

        $roleKasir = DB::table('roles')->where('nama_role', 'Kasir')->value('id_role');
        foreach (array_filter([1, $roleKasir]) as $idRole) {
            if (!DB::table('role_permissions')->where('id_role', $idRole)->where('id_menu', 48)->exists()) {
                DB::table('role_permissions')->insert([
                    'id_role' => $idRole,
                    'id_menu' => 48,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('id_menu', 48)->delete();
        DB::table('menus')->where('id_menu', 48)->delete();
    }
};
