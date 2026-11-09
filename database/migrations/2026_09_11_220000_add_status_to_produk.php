<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status aktif/nonaktif untuk produk - pola & tipe kolom persis sama dengan
 * bahan_baku.status (varchar 20, default 'aktif'). Produk yang sudah pernah
 * dipakai di transaksi TIDAK BISA dihapus permanen (FK RESTRICT dari
 * transaksi_detail.id_produk, sengaja begitu supaya riwayat nota lama tidak
 * pernah rusak) - Nonaktifkan jadi jalan keluar untuk "pensiunkan" produk
 * lama tanpa kehilangan riwayat, sama seperti pola BahanBakuController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->string('status', 20)->default('aktif')->after('keterangan');
        });
    }

    public function down(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
