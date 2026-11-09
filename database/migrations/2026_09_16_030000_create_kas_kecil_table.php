<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kas Kecil - pencatatan belanja kebutuhan mendasar di luar bahan baku
 * utama (mis. ATK, konsumsi, ongkos kirim kecil, dst) yang selama ini
 * tidak tercatat di mana pun (kas_manual tidak punya kategori, dan
 * belanja bahan utama sudah punya jalurnya sendiri lewat pembelian_bahan).
 * Dibuat sebagai tabel BERDIRI SENDIRI (bukan menumpuk kolom kategori ke
 * kas_manual) - pola sama persis dengan jersey_order_payments yang
 * sengaja dipisah dari kas_harian, supaya laporan pengeluaran kas kecil
 * bisa diaudit independen dari kas fisik laci kasir maupun pembelian
 * bahan baku.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('kas_kecil')) {
            Schema::create('kas_kecil', function (Blueprint $table) {
                $table->id();
                $table->date('tanggal');
                $table->string('kategori', 100);
                $table->string('keterangan', 500);
                $table->decimal('nominal', 12, 2);
                $table->string('bukti_path')->nullable();
                $table->foreignId('id_user')->nullable()->constrained('users', 'id_user')->nullOnDelete();
                $table->timestamps();
            });
        }

        // Daftarkan menu baru "Kas Kecil" & "Laporan Belanja Bahan" supaya
        // sidebar & middleware CheckMenuAccess mengenalinya, plus berikan
        // akses langsung ke role Administrator (role lain diatur manual
        // lewat halaman Kelola Akses kalau memang perlu).
        if (Schema::hasTable('menus') && !DB::table('menus')->where('url_link', 'kas-kecil')->exists()) {
            $idMenuKasKecil = DB::table('menus')->insertGetId([
                'nama_menu' => 'Kas Kecil',
                'url_link' => 'kas-kecil',
                'parent_id' => 0,
            ], 'id_menu');

            $idAdmin = DB::table('roles')->where('nama_role', 'Administrator')->value('id_role');
            if ($idAdmin) {
                DB::table('role_permissions')->insert(['id_role' => $idAdmin, 'id_menu' => $idMenuKasKecil]);
            }
        }

        if (Schema::hasTable('menus') && !DB::table('menus')->where('url_link', 'laporan/belanja-bahan')->exists()) {
            $idMenuLaporanBelanja = DB::table('menus')->insertGetId([
                'nama_menu' => 'Laporan Belanja Bahan',
                'url_link' => 'laporan/belanja-bahan',
                'parent_id' => 0,
            ], 'id_menu');

            $idAdmin = DB::table('roles')->where('nama_role', 'Administrator')->value('id_role');
            if ($idAdmin) {
                DB::table('role_permissions')->insert(['id_role' => $idAdmin, 'id_menu' => $idMenuLaporanBelanja]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('menus')) {
            $ids = DB::table('menus')->whereIn('url_link', ['kas-kecil', 'laporan/belanja-bahan'])->pluck('id_menu');
            if ($ids->isNotEmpty()) {
                DB::table('role_permissions')->whereIn('id_menu', $ids)->delete();
                DB::table('menus')->whereIn('id_menu', $ids)->delete();
            }
        }
        Schema::dropIfExists('kas_kecil');
    }
};
