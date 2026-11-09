<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - app_settings.url_akses: alamat dasar aplikasi yang dipakai QR (agar bisa dipindai dari HP).
 * - Menu hak akses yang sebelumnya belum punya pengaturan sendiri: Riwayat Nota, Platform Belanja Online, Jam Kerja, SPK Desain.
 *   Peran yang sudah punya menu induknya otomatis diberi menu baru (perilaku sekarang tidak berubah), Operator Desain diberi Riwayat Nota.
 * - Menu mati (BOM Produk, Design Proof - fiturnya sudah tidak ada) dibersihkan dari daftar.
 */
return new class extends Migration
{
    private const BARU = [
        61 => ['Riwayat Nota', 'penjualan/riwayat', 3],
        62 => ['Platform Belanja Online', 'platform-belanja', 9],
        63 => ['Jam Kerja', 'pengaturan-jam-kerja', 37],
        64 => ['SPK Desain (Cetak Lengkap)', 'spk-desain', 41],
    ];

    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $t) {
            $t->string('url_akses', 255)->nullable();
        });

        foreach (self::BARU as $id => [$nama, $url, $induk]) {
            DB::table('menus')->insertOrIgnore(['id_menu' => $id, 'nama_menu' => $nama, 'url_link' => $url, 'parent_id' => 0]);
            foreach (DB::table('role_permissions')->where('id_menu', $induk)->get() as $rp) {
                DB::table('role_permissions')->updateOrInsert(
                    ['id_role' => $rp->id_role, 'id_menu' => $id],
                    ['can_view' => $rp->can_view, 'can_add' => $rp->can_add, 'can_edit' => $rp->can_edit, 'can_delete' => $rp->can_delete]
                );
            }
        }

        // Operator Desain: riwayat nota hanya lihat (tidak bisa mengajukan edit/batal).
        $idDesain = DB::table('roles')->where('nama_role', 'Operator Desain')->value('id_role');
        if ($idDesain) {
            DB::table('role_permissions')->updateOrInsert(['id_role' => $idDesain, 'id_menu' => 61], ['can_view' => 1, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0]);
        }

        DB::table('role_permissions')->whereIn('id_menu', [2, 59])->delete();
        DB::table('menus')->whereIn('id_menu', [2, 59])->delete();
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('id_menu', array_keys(self::BARU))->delete();
        DB::table('menus')->whereIn('id_menu', array_keys(self::BARU))->delete();
        Schema::table('app_settings', fn (Blueprint $t) => $t->dropColumn('url_akses'));
    }
};
