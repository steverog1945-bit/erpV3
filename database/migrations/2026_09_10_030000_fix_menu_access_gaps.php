<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Menambal 2 celah data yang ditemukan saat menegakkan hak akses di level
 * route (lihat app/Http/Middleware/CheckMenuAccess.php):
 *
 * 1. "Aset Mesin" tidak pernah punya baris menu sendiri - sidebar-nya selama
 *    ini mengecek id_menu 3 ("Transaksi Baru") secara asal karena memang
 *    tidak ada id yang benar untuk dicek. Sekarang didaftarkan dengan benar.
 *
 * 2. Role "Kasir" hanya pernah diberi akses ke menu 3 (Transaksi Baru) dan
 *    TIDAK PERNAH diberi akses ke menu 16 (Kasir) - modul yang namanya
 *    sama dengan role itu sendiri. Ini jelas kelalaian konfigurasi (baru
 *    ketahuan sekarang karena role_permissions sebelumnya cuma menentukan
 *    tampil/tidaknya link sidebar, tidak pernah benar-benar menghalangi
 *    akses). Ditambal supaya kasir tetap bisa memproses pembayaran begitu
 *    penegakan akses di level route dinyalakan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        // 1. Menu Aset Mesin
        $mesinMenu = DB::table('menus')->where('url_link', 'mesin')->first();

        if (!$mesinMenu) {
            $newId = (int) DB::table('menus')->max('id_menu') + 1;

            DB::table('menus')->insert([
                'id_menu' => $newId,
                'nama_menu' => 'Aset Mesin',
                'url_link' => 'mesin',
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

        // 2. Role Kasir wajib punya akses ke menu Kasir (id_menu 16)
        if (Schema::hasTable('roles') && Schema::hasTable('role_permissions')) {
            $kasirRole = DB::table('roles')->where('nama_role', 'Kasir')->first();
            $kasirMenu = DB::table('menus')->where('url_link', 'kasir')->first();

            if ($kasirRole && $kasirMenu) {
                $already = DB::table('role_permissions')
                    ->where('id_role', $kasirRole->id_role)
                    ->where('id_menu', $kasirMenu->id_menu)
                    ->exists();

                if (!$already) {
                    DB::table('role_permissions')->insert([
                        'id_role' => $kasirRole->id_role,
                        'id_menu' => $kasirMenu->id_menu,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $mesinMenu = DB::table('menus')->where('url_link', 'mesin')->first();
        if ($mesinMenu) {
            DB::table('role_permissions')->where('id_menu', $mesinMenu->id_menu)->delete();
            DB::table('menus')->where('id_menu', $mesinMenu->id_menu)->delete();
        }

        $kasirRole = DB::table('roles')->where('nama_role', 'Kasir')->first();
        $kasirMenu = DB::table('menus')->where('url_link', 'kasir')->first();
        if ($kasirRole && $kasirMenu) {
            DB::table('role_permissions')
                ->where('id_role', $kasirRole->id_role)
                ->where('id_menu', $kasirMenu->id_menu)
                ->delete();
        }
    }
};
