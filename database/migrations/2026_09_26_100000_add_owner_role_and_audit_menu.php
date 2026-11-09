<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Role Owner (baca-saja): permintaan bahan (PR/PO), keuangan, seluruh laporan, pemakaian bahan
 * (cetak & salah cetak), dan bahan audit bulanan. Menu 60 = "Audit Bulanan Owner".
 */
return new class extends Migration
{
    private const MENU_OWNER = [60, 20, 25, 26, 29, 36, 39, 40, 43, 44, 46, 47, 53, 54, 55, 56, 57];

    public function up(): void
    {
        DB::table('menus')->insertOrIgnore(['id_menu' => 60, 'nama_menu' => 'Audit Bulanan Owner', 'url_link' => 'audit-owner', 'parent_id' => 0]);

        $role = DB::table('roles')->where('nama_role', 'Owner')->first();
        $idRole = $role?->id_role ?? DB::table('roles')->insertGetId(['nama_role' => 'Owner']);
        DB::table('roles')->where('id_role', $idRole)->update(['boleh_lihat_harga' => 1]);

        foreach (self::MENU_OWNER as $idMenu) {
            if (DB::table('menus')->where('id_menu', $idMenu)->exists()) {
                DB::table('role_permissions')->updateOrInsert(['id_role' => $idRole, 'id_menu' => $idMenu], []);
            }
        }
    }

    public function down(): void
    {
        $idRole = DB::table('roles')->where('nama_role', 'Owner')->value('id_role');
        if ($idRole) {
            DB::table('role_permissions')->where('id_role', $idRole)->delete();
            DB::table('roles')->where('id_role', $idRole)->delete();
        }
        DB::table('menus')->where('id_menu', 60)->delete();
    }
};
