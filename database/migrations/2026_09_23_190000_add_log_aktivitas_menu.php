<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Daftarkan menu "Log Aktivitas" (Audit Trail) - khusus Administrator.
// Dicari dinamis by nama role (bukan hardcode id_role) - lihat catatan di
// 2026_09_17_120000_grant_gudang_laporan_stok_bahan.php kenapa hardcode
// berbahaya pada migrate:fresh.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menus') || DB::table('menus')->where('url_link', 'log-aktivitas')->exists()) {
            return;
        }

        $newId = (int) DB::table('menus')->max('id_menu') + 1;

        DB::table('menus')->insert([
            'id_menu' => $newId,
            'nama_menu' => 'Log Aktivitas',
            'url_link' => 'log-aktivitas',
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
        $id = DB::table('menus')->where('url_link', 'log-aktivitas')->value('id_menu');
        if ($id) {
            DB::table('role_permissions')->where('id_menu', $id)->delete();
            DB::table('menus')->where('id_menu', $id)->delete();
        }
    }
};
