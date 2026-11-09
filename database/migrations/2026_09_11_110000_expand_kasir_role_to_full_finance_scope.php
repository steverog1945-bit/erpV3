<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kasir sebelumnya cuma punya 5 menu (Kasir, Transaksi Baru, Data Konsumen,
 * Golongan Konsumen, Bank Perusahaan) - cukup untuk transaksi harian tapi
 * belum mencakup sisi finance lain yang sudah ada di aplikasi ini
 * (Collecting/penagihan, Laporan Kas, Deposit Konsumen, Laporan Keuangan).
 * Sesuai arah role ini ke depan ("kasir hanya berfungsi di bagian
 * finance"), 4 menu itu ditambahkan.
 *
 * Chart of Accounts (id_menu=35) SENGAJA TIDAK ditambahkan - itu mengatur
 * STRUKTUR akun akuntansi (kode akun, saldo normal, dst), beda level
 * dengan operasional finance harian. Salah ubah struktur akun bisa
 * mengacaukan laporan keuangan ke belakang, jadi tetap Administrator-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $kasirRoleId = DB::table('roles')->where('nama_role', 'Kasir')->value('id_role');
        if (!$kasirRoleId) {
            return;
        }

        $menuUrls = ['collecting', 'laporan-kas', 'konsumen-deposit', 'akuntansi'];
        $menuIds = DB::table('menus')->whereIn('url_link', $menuUrls)->pluck('id_menu');

        foreach ($menuIds as $menuId) {
            DB::table('role_permissions')->insertOrIgnore([
                'id_role' => $kasirRoleId,
                'id_menu' => $menuId,
            ]);
        }
    }

    public function down(): void
    {
        $kasirRoleId = DB::table('roles')->where('nama_role', 'Kasir')->value('id_role');
        if (!$kasirRoleId) {
            return;
        }

        $menuUrls = ['collecting', 'laporan-kas', 'konsumen-deposit', 'akuntansi'];
        $menuIds = DB::table('menus')->whereIn('url_link', $menuUrls)->pluck('id_menu');

        DB::table('role_permissions')
            ->where('id_role', $kasirRoleId)
            ->whereIn('id_menu', $menuIds)
            ->delete();
    }
};
