<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Mendaftarkan 2 menu baru untuk modul akuntansi yang sebelumnya sama
 * sekali tidak punya UI (bag_akun cuma diisi via migration, jurnal_umum
 * tidak bisa dilihat sama sekali walau AccountingService sudah menulis ke
 * sana sejak awal). Mengikuti pola persis 200000_register_new_master_data_menus.
 *
 * 'Chart of Accounts' -> CRUD bag_akun (prefix url 'akun').
 * 'Laporan Keuangan'  -> Jurnal Umum, Buku Besar, Neraca Saldo, Laba Rugi,
 *                        Neraca - semua read-only, satu menu karena
 *                        sifatnya satu rumpun laporan (prefix url 'akuntansi').
 */
return new class extends Migration
{
    private array $menus = [
        ['nama_menu' => 'Chart of Accounts', 'url_link' => 'akun'],
        ['nama_menu' => 'Laporan Keuangan',   'url_link' => 'akuntansi'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $adminRole = Schema::hasTable('roles')
            ? DB::table('roles')->where('nama_role', 'Administrator')->first()
            : null;

        foreach ($this->menus as $menu) {
            if (DB::table('menus')->where('url_link', $menu['url_link'])->exists()) {
                continue;
            }

            $newId = (int) DB::table('menus')->max('id_menu') + 1;

            DB::table('menus')->insert([
                'id_menu'   => $newId,
                'nama_menu' => $menu['nama_menu'],
                'url_link'  => $menu['url_link'],
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
        $urls = array_column($this->menus, 'url_link');
        $ids = DB::table('menus')->whereIn('url_link', $urls)->pluck('id_menu');

        DB::table('role_permissions')->whereIn('id_menu', $ids)->delete();
        DB::table('menus')->whereIn('id_menu', $ids)->delete();
    }
};
