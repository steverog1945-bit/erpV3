<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Modul Harga Promo memakai tabel `harga_promo` yang sudah ada sejak awal
 * tapi tidak pernah dipakai kode apa pun - tidak ada tabel/kolom baru di sini.
 * Migration ini hanya mendaftarkan menunya + memetakannya di menu_access.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        if (DB::table('menus')->where('url_link', 'harga-promo')->exists()) {
            return;
        }

        $newId = (int) DB::table('menus')->max('id_menu') + 1;

        DB::table('menus')->insert([
            'id_menu' => $newId,
            'nama_menu' => 'Harga Promo',
            'url_link' => 'harga-promo',
            'parent_id' => 0,
        ]);

        $adminRole = Schema::hasTable('roles')
            ? DB::table('roles')->where('nama_role', 'Administrator')->first()
            : null;

        if ($adminRole && Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->insert([
                'id_role' => $adminRole->id_role,
                'id_menu' => $newId,
            ]);
        }
    }

    public function down(): void
    {
        $menu = DB::table('menus')->where('url_link', 'harga-promo')->first();
        if ($menu) {
            DB::table('role_permissions')->where('id_menu', $menu->id_menu)->delete();
            DB::table('menus')->where('id_menu', $menu->id_menu)->delete();
        }
    }
};
