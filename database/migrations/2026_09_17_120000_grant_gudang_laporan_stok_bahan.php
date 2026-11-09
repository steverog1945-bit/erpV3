<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard Gudang (role id 5) menautkan langsung ke Laporan Stok Bahan
 * Baku (id_menu 46, sebelumnya cuma Administrator) - wajar diberi akses
 * karena laporan itu memang tentang tanggung jawab mereka (evaluasi
 * pengadaan bahan).
 */
return new class extends Migration
{
    public function up(): void
    {
        // id_role=5/id_menu=46 SEBELUMNYA di-hardcode - kebetulan benar di
        // database production yang sudah berjalan (dibangun bertahap,
        // insert Gudang & menu ini kebetulan jatuh di nomor itu), tapi
        // tidak terjamin sama pada migrate:fresh dari kosong (nomor
        // auto_increment tergantung urutan insert role/menu lain sebelumnya).
        // Dicari dinamis by nama, sama seperti pola aman di migrasi lain
        // (mis. 2026_09_10_070000_add_laporan_kas_menu.php).
        $idRole = DB::table('roles')->where('nama_role', 'Gudang')->value('id_role');
        $idMenu = DB::table('menus')->where('url_link', 'laporan-stok-bahan')->value('id_menu');

        if (!$idRole || !$idMenu) {
            return;
        }

        if (!DB::table('role_permissions')->where('id_role', $idRole)->where('id_menu', $idMenu)->exists()) {
            DB::table('role_permissions')->insert([
                'id_role' => $idRole,
                'id_menu' => $idMenu,
            ]);
        }
    }

    public function down(): void
    {
        $idRole = DB::table('roles')->where('nama_role', 'Gudang')->value('id_role');
        $idMenu = DB::table('menus')->where('url_link', 'laporan-stok-bahan')->value('id_menu');
        if ($idRole && $idMenu) {
            DB::table('role_permissions')->where('id_role', $idRole)->where('id_menu', $idMenu)->delete();
        }
    }
};
