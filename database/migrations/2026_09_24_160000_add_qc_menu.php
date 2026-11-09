<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Menu "QC & Rework" - di-grant ke role yang SAMA dengan yang punya akses
// menu 'produksi' (mengikuti pola add_purchase_menus).
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menus') || DB::table('menus')->where('url_link', 'qc')->exists()) {
            return;
        }

        $newId = (int) DB::table('menus')->max('id_menu') + 1;
        DB::table('menus')->insert(['id_menu' => $newId, 'nama_menu' => 'QC & Rework', 'url_link' => 'qc', 'parent_id' => 0]);

        $ref = DB::table('menus')->where('url_link', 'produksi')->value('id_menu');

        if ($ref && Schema::hasTable('role_permissions')) {
            foreach (DB::table('role_permissions')->where('id_menu', $ref)->pluck('id_role') as $idRole) {
                DB::table('role_permissions')->insert(['id_role' => $idRole, 'id_menu' => $newId]);
            }
        }
    }

    public function down(): void
    {
        $id = DB::table('menus')->where('url_link', 'qc')->value('id_menu');
        if ($id) {
            DB::table('role_permissions')->where('id_menu', $id)->delete();
            DB::table('menus')->where('id_menu', $id)->delete();
        }
    }
};
