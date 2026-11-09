<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hardening P1-1 (audit): indeks yang terbukti hilang lewat EXPLAIN pada
 * dataset sintetis 20rb+ baris (lihat laporan audit P1) - murni aditif,
 * tidak mengubah kolom/data apa pun, aman di-rollback.
 *
 * - transaksi(tgl_order, status_nota): dipakai LaporanController::order()
 *   (default filter "hari ini"), DashboardController (piutang), dan yang
 *   paling sering: KasirController::checkNewTransaction() yang di-poll
 *   SETIAP 10 DETIK oleh setiap Kasir/Administrator yang login (lihat
 *   layouts/app.blade.php & kasir/monitoring_transaksi.blade.php) -
 *   sebelumnya EXPLAIN menunjukkan full table scan (type=ALL) untuk query
 *   ini.
 * - kartu_stok(id_bahan, tanggal): riwayat Kartu Stok per bahan diurutkan
 *   tanggal - sebelumnya "Using filesort" walau sudah pakai indeks id_bahan.
 * - kartu_stok(id_bahan, referensi): dipakai StokReversalService (guard
 *   tinta untuk transaksi lama tanpa snapshot) & pencarian riwayat per
 *   nota - sebelumnya harus memindai semua baris id_bahan itu satu per satu.
 * - transaksi_detail(status_spk, id_detail): listing SPK/Produksi
 *   (WHERE status_spk = ... ORDER BY id_detail) - saat ini masih terlayani
 *   lewat PRIMARY key karena proporsi baris "Antre" masih besar di data
 *   uji, tapi begitu proporsinya mengecil (mayoritas sudah Selesai) pola
 *   ini berisiko mendekati full scan juga - ditambahkan sekarang sebagai
 *   pencegahan sebelum jadi masalah nyata.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->index(['tgl_order', 'status_nota'], 'transaksi_tgl_order_status_nota_index');
        });

        Schema::table('kartu_stok', function (Blueprint $table) {
            $table->index(['id_bahan', 'tanggal'], 'kartu_stok_id_bahan_tanggal_index');
            $table->index(['id_bahan', 'referensi'], 'kartu_stok_id_bahan_referensi_index');
        });

        Schema::table('transaksi_detail', function (Blueprint $table) {
            $table->index(['status_spk', 'id_detail'], 'transaksi_detail_status_spk_id_detail_index');
        });
    }

    public function down(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->dropIndex('transaksi_tgl_order_status_nota_index');
        });

        Schema::table('kartu_stok', function (Blueprint $table) {
            $table->dropIndex('kartu_stok_id_bahan_tanggal_index');
            $table->dropIndex('kartu_stok_id_bahan_referensi_index');
        });

        Schema::table('transaksi_detail', function (Blueprint $table) {
            $table->dropIndex('transaksi_detail_status_spk_id_detail_index');
        });
    }
};
