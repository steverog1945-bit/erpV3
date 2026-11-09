<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Laporan Kas tidak butuh tabel/kolom baru - murni membaca kas_harian yang
 * sudah ada. Migration ini hanya mendaftarkan menunya, mengikuti pola yang
 * sama dengan add_laporan_menu.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menus') || DB::table('menus')->where('url_link', 'laporan-kas')->exists()) {
            return;
        }

        $newId = (int) DB::table('menus')->max('id_menu') + 1;

        DB::table('menus')->insert([
            'id_menu' => $newId,
            'nama_menu' => 'Laporan Kas',
            'url_link' => 'laporan-kas',
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
        $id = DB::table('menus')->where('url_link', 'laporan-kas')->value('id_menu');
        if ($id) {
            DB::table('role_permissions')->where('id_menu', $id)->delete();
            DB::table('menus')->where('id_menu', $id)->delete();
        }
    }
};
