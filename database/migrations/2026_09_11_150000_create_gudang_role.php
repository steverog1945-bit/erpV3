<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Melengkapi peta bagian->role: karyawan.bagian sudah lama punya 5
 * kategori (Admin, Desain, Produksi, Kasir, Gudang), tapi role login
 * cuma menutupi 4 (Administrator, Operator Desain, Operator Produksi,
 * Kasir) - 'Gudang' satu-satunya bagian tanpa role sama sekali. Belum
 * ada karyawan bagian Gudang saat ini, tapi begitu ada, mereka akan
 * terjebak sama seperti Fathur Rosi/Moh. Riski sebelumnya (tidak ada
 * role yang cocok untuk dipilih).
 *
 * Cakupan: Master Bahan (lihat stok), Kedatangan Bahan (terima barang
 * masuk - kerja inti gudang), Gudang (kelola lokasi/gudang). Sengaja
 * TIDAK termasuk Pembelian Bahan - itu keputusan procurement/pembelian,
 * beda tanggung jawab dari penerimaan fisik barang di gudang.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sudahAda = DB::table('roles')->where('nama_role', 'Gudang')->exists();
        if ($sudahAda) {
            return;
        }

        $roleId = DB::table('roles')->insertGetId(['nama_role' => 'Gudang']);

        $urls = ['bahan-baku', 'kedatangan-bahan', 'gudang'];
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
        $roleId = DB::table('roles')->where('nama_role', 'Gudang')->value('id_role');
        if (!$roleId) {
            return;
        }

        DB::table('role_permissions')->where('id_role', $roleId)->delete();
        DB::table('roles')->where('id_role', $roleId)->delete();
    }
};
