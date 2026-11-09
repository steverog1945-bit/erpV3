<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Insert Roles
        DB::table('roles')->insert([
            ['id_role' => 1, 'nama_role' => 'Administrator'],
            ['id_role' => 2, 'nama_role' => 'Operator Desain'],
            ['id_role' => 3, 'nama_role' => 'Kasir'],
        ]);

        // 2. Insert Menus Utama
        DB::table('menus')->insert([
            ['id_menu' => 1, 'nama_menu' => 'Master Bahan', 'url_link' => 'bahan-baku'],
            ['id_menu' => 2, 'nama_menu' => 'BOM Produk', 'url_link' => 'bom'],
            ['id_menu' => 3, 'nama_menu' => 'Transaksi Baru', 'url_link' => 'transaksi'],
            ['id_menu' => 4, 'nama_menu' => 'Pengaturan Akses', 'url_link' => 'setting-akses'],
        ]);

        // 3. Insert Master Karyawan Awal
        DB::table('karyawan')->insert([
            'id_karyawan' => 1,
            'nama_karyawan' => 'Admin Utama',
            'bagian' => 'Admin',
            'telepon' => '08123456789',
            'status_aktif' => 1
        ]);

        // 4. Insert User Admin
        DB::table('users')->insert([
            'id_user' => 1,
            'id_karyawan' => 1,
            'username' => 'admin',
            'password' => Hash::make('admin123'), // Password terenkripsi
            'id_role' => 1,
        ]);
        DB::table('kategori')->insert([
    ['id_kategori' => 1, 'nama_kategori' => 'Sticker'],
    ['id_kategori' => 2, 'nama_kategori' => 'Banner/Flexi'],
]);

DB::table('satuan')->insert([
    ['id_satuan' => 1, 'nama_satuan' => 'cm', 'nilai_konversi' => 1],
    ['id_satuan' => 2, 'nama_satuan' => 'm', 'nilai_konversi' => 100],
]);
    }
}