<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Daftarkan menu "Backup & Recovery" - khusus Administrator, mengikuti
// pola aman yang sama dengan 2026_09_23_220000_add_approval_menu.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menus') || DB::table('menus')->where('url_link', 'backup')->exists()) {
            return;
        }

        $newId = (int) DB::table('menus')->max('id_menu') + 1;

        DB::table('menus')->insert([
            'id_menu' => $newId,
            'nama_menu' => 'Backup & Recovery',
            'url_link' => 'backup',
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
        $id = DB::table('menus')->where('url_link', 'backup')->value('id_menu');
        if ($id) {
            DB::table('role_permissions')->where('id_menu', $id)->delete();
            DB::table('menus')->where('id_menu', $id)->delete();
        }
    }
};
