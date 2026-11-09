<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * karyawan.bagian membedakan 'Produksi' dari 'Desain' sebagai kategori
 * kerja yang berbeda, tapi sistem role login cuma punya 'Operator Desain'
 * - dan 2 karyawan bagian Produksi yang sudah ditugaskan mesin (migrasi
 * 100000: Fathur Rosi, Moh. Riski) BELUM PUNYA AKUN LOGIN SAMA SEKALI
 * (dicek: users.id_karyawan mereka NULL).
 *
 * Role 'Operator Desain' bisa saja dipakai bersama, tapi masih membawa
 * Transaksi Baru/Data Konsumen/Golongan Konsumen - wajar untuk staf
 * desain yang mungkin ikut melayani walk-in, tapi tidak relevan untuk
 * operator mesin murni. Role baru ini sengaja lebih sempit: cuma SPK,
 * Produksi, Finishing Produk - persis kebutuhan operator lini produksi.
 *
 * Akun login untuk Fathur Rosi & Moh. Riski TIDAK dibuat di migrasi ini -
 * password akun harus ditentukan sendiri oleh mereka/admin, bukan
 * ditentukan lewat migrasi. Buat lewat Master User (menu Data User)
 * setelah migrasi ini jalan, role 'Operator Produksi' akan muncul di
 * dropdown pilihan role.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sudahAda = DB::table('roles')->where('nama_role', 'Operator Produksi')->exists();
        if ($sudahAda) {
            return;
        }

        $roleId = DB::table('roles')->insertGetId(['nama_role' => 'Operator Produksi']);

        $urls = ['spk', 'produksi', 'finishing'];
        $menuIds = DB::table('menus')->whereIn('url_link', $urls)->pluck('id_menu');

        foreach ($menuIds as $menuId) {
            DB::table('role_permissions')->insertOrIgnore([
                'id_role' => $roleId,
                'id_menu' => $menuId,
            ]);
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('nama_role', 'Operator Produksi')->value('id_role');
        if (!$roleId) {
            return;
        }

        DB::table('role_permissions')->where('id_role', $roleId)->delete();
        DB::table('roles')->where('id_role', $roleId)->delete();
    }
};
