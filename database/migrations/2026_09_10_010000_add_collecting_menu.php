<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Modul Collecting tidak butuh tabel/kolom baru sama sekali - statusnya
 * dihitung langsung dari transaksi_detail.status_spk yang sudah ada.
 * Migration ini hanya mendaftarkan menunya supaya bisa diatur lewat
 * Setting Akses yang sudah ada, sama seperti SPK & Produksi sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $already = DB::table('menus')->where('url_link', 'collecting')->exists();
        if ($already) {
            return;
        }

        $maxId = (int) DB::table('menus')->max('id_menu');
        $newId = $maxId + 1;

        DB::table('menus')->insert([
            'id_menu' => $newId,
            'nama_menu' => 'Collecting',
            'url_link' => 'collecting',
            'parent_id' => 0,
        ]);

        if (Schema::hasTable('role_permissions') && Schema::hasTable('roles')) {
            $adminRole = DB::table('roles')->where('nama_role', 'Administrator')->first();
            if ($adminRole) {
                DB::table('role_permissions')->insert([
                    'id_role' => $adminRole->id_role,
                    'id_menu' => $newId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $menu = DB::table('menus')->where('url_link', 'collecting')->first();
        if ($menu) {
            DB::table('role_permissions')->where('id_menu', $menu->id_menu)->delete();
            DB::table('menus')->where('id_menu', $menu->id_menu)->delete();
        }
    }
};
