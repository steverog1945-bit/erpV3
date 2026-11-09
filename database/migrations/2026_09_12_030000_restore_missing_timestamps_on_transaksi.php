<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * HOTFIX INSIDEN - kolom transaksi.created_at/updated_at ditemukan HILANG
 * TOTAL dari skema (bukan NULL, betul-betul tidak ada di SHOW COLUMNS),
 * ditemukan saat mengaudit error nyata di log
 * (storage/logs/laravel.log, 2026-09-12 12:44:09):
 *
 *   SQLSTATE[42S22]: Column not found: 1054 Unknown column
 *   'transaksi.updated_at' in 'order clause'
 *
 * ...yang membuat KasirController::index() (Monitoring Kasir) SELALU
 * gagal (orderByRaw('COALESCE(transaksi.updated_at, transaksi.tgl_order)')).
 *
 * Log yang sama menunjukkan rentetan error migrasi lain (12:10-12:19) di
 * banyak tabel tak berkaitan (transaksi_detail, pembayaran_riwayat,
 * gudang, kedatangan_bahan, kartu_stok) dan kolom baru "nama_konsumen_
 * manual" yang TIDAK berasal dari migrasi mana pun di project ini (sudah
 * dicek: 0 file migrasi menyinggung nama itu, di folder ini MAUPUN
 * mirror staging) - dan pada saat yang sama 11 tabel milik aplikasi lain
 * yang berbagi database yang sama (jersey-order-system: orders,
 * customers, designers, sizes, dst) sudah TIDAK ADA lagi. Pola ini cocok
 * dengan `php artisan migrate:fresh` yang dijalankan dari SALINAN LAIN
 * project ini (bukan folder ini maupun mirror staging) yang menunjuk ke
 * database bersama yang sama - migrate:fresh men-drop SEMUA tabel di
 * database (bukan cuma yang dikenali migrasinya) lalu membangun ulang
 * hanya dari migrasi milik salinan itu sendiri.
 *
 * Migrasi ini HANYA menambal kolom yang hilang (aman, murni ADD COLUMN,
 * tidak menyentuh data) supaya Monitoring Kasir bisa jalan lagi sekarang
 * juga. TIDAK mencoba memulihkan 11 tabel aplikasi lain yang hilang -
 * itu di luar kewenangan & pengetahuan skema project ini, perlu
 * konfirmasi/tindakan pemilik aplikasi (lihat pesan chat).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('transaksi', 'created_at')) {
            DB::statement("ALTER TABLE `transaksi` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL");
        }
        if (!Schema::hasColumn('transaksi', 'updated_at')) {
            DB::statement("ALTER TABLE `transaksi` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL");
        }
    }

    public function down(): void
    {
        // SENGAJA TIDAK di-drop balik di sini - kolom ini semestinya SELALU
        // ada (hilangnya adalah insiden, bukan desain), rollback migrasi
        // hotfix ini tidak boleh menciptakan ulang bug yang sama.
    }
};
