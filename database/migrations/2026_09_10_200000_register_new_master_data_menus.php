<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Mendaftarkan 5 menu baru (Merk, Jenis Bahan, Jasa, Gudang, Deposit
 * Konsumen) mengikuti pola yang sama persis dengan add_laporan_kas_menu.php
 * - hanya diberikan ke Administrator, role lain bisa ditambahkan manual
 * lewat halaman Setting Akses kalau diperlukan.
 */
return new class extends Migration
{
    private array $menus = [
        ['nama_menu' => 'Merk',             'url_link' => 'merk'],
        ['nama_menu' => 'Jenis Bahan',       'url_link' => 'jenis-bahan'],
        ['nama_menu' => 'Jasa',              'url_link' => 'jasa'],
        ['nama_menu' => 'Gudang',            'url_link' => 'gudang'],
        ['nama_menu' => 'Deposit Konsumen',  'url_link' => 'konsumen-deposit'],
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
