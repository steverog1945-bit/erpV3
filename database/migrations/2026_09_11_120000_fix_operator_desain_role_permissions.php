<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Role "Operator Desain" (id_role=2) - satu-satunya role untuk staf
 * produksi/desain di aplikasi ini (dipakai 2 user real: Rehan, Rahman) -
 * ternyata cuma punya 4 menu: Bank Perusahaan, Data Konsumen, Golongan
 * Konsumen, Transaksi Baru. TIDAK ADA akses ke SPK maupun Produksi sama
 * sekali - persis 4 dari 5 menu awal Kasir (minus menu Kasir sendiri),
 * mengindikasikan permission role ini kepencet ke-copy dari Kasir saat
 * setup awal dan tidak pernah direvisi untuk kebutuhan produksi/desain
 * yang sesungguhnya.
 *
 * Akibatnya: fitur filter SPK per-divisi (karyawan_mesin, migrasi
 * 100000) yang baru dibangun untuk role ini TIDAK BISA DIPAKAI SAMA
 * SEKALI oleh siapa pun berrole Operator Desain - mereka akan kena 403
 * "tidak punya akses" begitu coba buka /spk.
 *
 * Perbaikan:
 * - Tambah: SPK, Produksi, Finishing Produk, Laporan Produksi - inti
 *   pekerjaan operator/desain sehari-hari.
 * - Hapus: Bank Perusahaan - CRUD penuh (create/edit/delete rekening
 *   bank perusahaan) untuk role produksi/desain tidak ada hubungannya
 *   dengan pekerjaan mereka, kemungkinan besar ikut ke-copy dari Kasir.
 * - Transaksi Baru/Data Konsumen/Golongan Konsumen SENGAJA DIPERTAHANKAN
 *   apa adanya - tidak ada bukti itu salah (mungkin memang dipakai untuk
 *   input order jalan/walk-in), menghapusnya berisiko mematahkan alur
 *   kerja nyata 2 user aktif tanpa kepastian.
 */
return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('nama_role', 'Operator Desain')->value('id_role');
        if (!$roleId) {
            return;
        }

        $tambahUrls = ['spk', 'produksi', 'finishing', 'laporan-produksi'];
        $menuIdsTambah = DB::table('menus')->whereIn('url_link', $tambahUrls)->pluck('id_menu');
        foreach ($menuIdsTambah as $menuId) {
            DB::table('role_permissions')->insertOrIgnore([
                'id_role' => $roleId,
                'id_menu' => $menuId,
            ]);
        }

        $bankMenuId = DB::table('menus')->where('url_link', 'bank')->value('id_menu');
        if ($bankMenuId) {
            DB::table('role_permissions')
                ->where('id_role', $roleId)
                ->where('id_menu', $bankMenuId)
                ->delete();
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('nama_role', 'Operator Desain')->value('id_role');
        if (!$roleId) {
            return;
        }

        $tambahUrls = ['spk', 'produksi', 'finishing', 'laporan-produksi'];
        $menuIdsTambah = DB::table('menus')->whereIn('url_link', $tambahUrls)->pluck('id_menu');
        DB::table('role_permissions')
            ->where('id_role', $roleId)
            ->whereIn('id_menu', $menuIdsTambah)
            ->delete();

        $bankMenuId = DB::table('menus')->where('url_link', 'bank')->value('id_menu');
        if ($bankMenuId) {
            DB::table('role_permissions')->insertOrIgnore([
                'id_role' => $roleId,
                'id_menu' => $bankMenuId,
            ]);
        }
    }
};
