<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Bagian kedua dari hotfix insiden yang sama - lihat catatan lengkap di
 * 2026_09_12_030000_restore_missing_timestamps_on_transaksi.php.
 * transaksi_detail.created_at/updated_at ikut hilang total dari skema
 * (kasus sama, tabel berbeda), ditemukan saat audit lanjutan setelah
 * menambal transaksi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('transaksi_detail', 'created_at')) {
            DB::statement("ALTER TABLE `transaksi_detail` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL");
        }
        if (!Schema::hasColumn('transaksi_detail', 'updated_at')) {
            DB::statement("ALTER TABLE `transaksi_detail` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL");
        }
    }

    public function down(): void
    {
        // Sengaja tidak di-drop balik - lihat alasan yang sama di migrasi
        // pasangannya untuk transaksi.
    }
};
