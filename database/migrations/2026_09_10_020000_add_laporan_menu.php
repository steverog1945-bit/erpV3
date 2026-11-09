<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Modul Laporan tidak butuh tabel/kolom baru - murni membaca transaksi &
 * transaksi_detail yang sudah ada. Migration ini hanya mendaftarkan menunya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $items = [
            ['url_link' => 'laporan-order', 'nama_menu' => 'Laporan Order'],
            ['url_link' => 'laporan-produksi', 'nama_menu' => 'Laporan Produksi'],
        ];

        $adminRole = Schema::hasTable('roles')
            ? DB::table('roles')->where('nama_role', 'Administrator')->first()
            : null;

        foreach ($items as $item) {
            if (DB::table('menus')->where('url_link', $item['url_link'])->exists()) {
                continue;
            }

            $newId = (int) DB::table('menus')->max('id_menu') + 1;

            DB::table('menus')->insert([
                'id_menu' => $newId,
                'nama_menu' => $item['nama_menu'],
                'url_link' => $item['url_link'],
                'parent_id' => 0,
            ]);

            if ($adminRole && Schema::hasTable('role_permissions')) {
                DB::table('role_permissions')->insert([
                    'id_role' => $adminRole->id_role,
                    'id_menu' => $newId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('menus')->whereIn('url_link', ['laporan-order', 'laporan-produksi'])->pluck('id_menu');
        DB::table('role_permissions')->whereIn('id_menu', $ids)->delete();
        DB::table('menus')->whereIn('id_menu', $ids)->delete();
    }
};
