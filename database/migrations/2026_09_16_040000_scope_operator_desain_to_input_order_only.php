<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permintaan langsung user: role Desainer (Operator Desain, id_role=2)
 * dibatasi HANYA untuk: Data Konsumen, Kasir Utama (cuma input order baru,
 * bukan kelola kas/riwayat/batalkan nota), Order Jersey (cuma input order
 * baru, bukan master data/keuangan/edit/hapus order lama).
 *
 * Sebelumnya role ini punya id_menu 3 (Transaksi Baru - penuh, termasuk
 * riwayat & batalkan-nota lewat prefix yang sama) dan id_menu 16 (Kasir -
 * kelola kas/closing/monitoring, modul TERPISAH dari input order). Diganti
 * dengan 2 menu baru yang granularitasnya lebih sempit (41, 42) - dipetakan
 * di config/menu_access.php ke prefix route yang benar-benar cuma dipakai
 * alur input order, dan route mutasi yang tidak bisa dipisah lewat prefix
 * (mis. /jersey/{order} update/destroy) digembok terpisah lewat middleware
 * restrict.input-only langsung di route-nya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menus') || !Schema::hasTable('role_permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $idOperatorDesain = DB::table('roles')->where('nama_role', 'Operator Desain')->value('id_role');
        if (!$idOperatorDesain) {
            return;
        }

        $idMenuTransaksiInput = DB::table('menus')->where('url_link', 'penjualan-input-order')->value('id_menu');
        if (!$idMenuTransaksiInput) {
            $idMenuTransaksiInput = DB::table('menus')->insertGetId([
                'nama_menu' => 'Kasir Utama (Input Order)',
                'url_link' => 'penjualan-input-order',
                'parent_id' => 0,
            ], 'id_menu');
        }

        $idMenuJerseyInput = DB::table('menus')->where('url_link', 'jersey-input-order')->value('id_menu');
        if (!$idMenuJerseyInput) {
            $idMenuJerseyInput = DB::table('menus')->insertGetId([
                'nama_menu' => 'Order Jersey (Input Order)',
                'url_link' => 'jersey-input-order',
                'parent_id' => 0,
            ], 'id_menu');
        }

        $idMenuLaporanJersey = DB::table('menus')->where('url_link', 'laporan/order-jersey')->value('id_menu');
        if (!$idMenuLaporanJersey) {
            $idMenuLaporanJersey = DB::table('menus')->insertGetId([
                'nama_menu' => 'Laporan Order Jersey',
                'url_link' => 'laporan/order-jersey',
                'parent_id' => 0,
            ], 'id_menu');
        }

        // Cabut akses PENUH lama (id_menu 3 "Transaksi Baru" & 16 "Kasir")
        // dari Operator Desain, ganti dengan 2 menu input-only yang baru.
        DB::table('role_permissions')
            ->where('id_role', $idOperatorDesain)
            ->whereIn('id_menu', [3, 16])
            ->delete();

        foreach ([$idMenuTransaksiInput, $idMenuJerseyInput] as $idMenuBaru) {
            $sudahAda = DB::table('role_permissions')
                ->where('id_role', $idOperatorDesain)
                ->where('id_menu', $idMenuBaru)
                ->exists();
            if (!$sudahAda) {
                DB::table('role_permissions')->insert(['id_role' => $idOperatorDesain, 'id_menu' => $idMenuBaru]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('menus') || !Schema::hasTable('role_permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $idOperatorDesain = DB::table('roles')->where('nama_role', 'Operator Desain')->value('id_role');
        $idsMenuBaru = DB::table('menus')
            ->whereIn('url_link', ['penjualan-input-order', 'jersey-input-order', 'laporan/order-jersey'])
            ->pluck('id_menu');

        if ($idOperatorDesain && $idsMenuBaru->isNotEmpty()) {
            DB::table('role_permissions')
                ->where('id_role', $idOperatorDesain)
                ->whereIn('id_menu', $idsMenuBaru)
                ->delete();

            // Kembalikan akses penuh lama supaya rollback benar-benar
            // mengembalikan kondisi semula.
            foreach ([3, 16] as $idMenuLama) {
                $sudahAda = DB::table('role_permissions')
                    ->where('id_role', $idOperatorDesain)
                    ->where('id_menu', $idMenuLama)
                    ->exists();
                if (!$sudahAda) {
                    DB::table('role_permissions')->insert(['id_role' => $idOperatorDesain, 'id_menu' => $idMenuLama]);
                }
            }
        }

        if ($idsMenuBaru->isNotEmpty()) {
            DB::table('menus')->whereIn('id_menu', $idsMenuBaru)->delete();
        }
    }
};
