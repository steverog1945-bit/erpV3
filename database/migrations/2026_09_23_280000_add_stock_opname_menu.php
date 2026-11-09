<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Daftarkan menu "Stock Opname" - grant ke role yang SAMA dengan yang
// sudah punya akses bahan-baku/kartu-stok (bukan cuma Administrator),
// karena staff gudang yang menghitung fisik, bukan cuma admin.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menus') || DB::table('menus')->where('url_link', 'stock-opname')->exists()) {
            return;
        }

        $newId = (int) DB::table('menus')->max('id_menu') + 1;

        DB::table('menus')->insert([
            'id_menu' => $newId,
            'nama_menu' => 'Stock Opname',
            'url_link' => 'stock-opname',
            'parent_id' => 0,
        ]);

        $bahanBakuMenuId = DB::table('menus')->where('url_link', 'bahan-baku')->value('id_menu');

        if ($bahanBakuMenuId && Schema::hasTable('role_permissions')) {
            $roleIds = DB::table('role_permissions')->where('id_menu', $bahanBakuMenuId)->pluck('id_role');

            foreach ($roleIds as $idRole) {
                DB::table('role_permissions')->insert([
                    'id_role' => $idRole,
                    'id_menu' => $newId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $id = DB::table('menus')->where('url_link', 'stock-opname')->value('id_menu');
        if ($id) {
            DB::table('role_permissions')->where('id_menu', $id)->delete();
            DB::table('menus')->where('id_menu', $id)->delete();
        }
    }
};
