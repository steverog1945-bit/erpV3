<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tambah "Debit" ke opsi metode bayar - sebelumnya cuma Tunai/Transfer/
 * Deposit, padahal kasir di lapangan juga sering terima kartu debit
 * (uang masuk ke rekening bank sama seperti Transfer, bukan uang fisik
 * di laci kasir, tapi tetap perlu dibedakan pencatatannya dari Transfer
 * bank-ke-bank biasa untuk kebutuhan rekonsiliasi).
 *
 * doctrine/dbal TIDAK terpasang di project ini jadi Schema::table()->
 * change() tidak bisa dipakai untuk modifikasi kolom enum - pakai raw
 * DB::statement() seperti migrasi 2026_09_11_240000.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `pembayaran_riwayat` MODIFY `metode` ENUM('Tunai','Transfer','Debit','Deposit') NOT NULL");

        if (!\Illuminate\Support\Facades\Schema::hasColumn('kas_harian', 'total_debit_masuk')) {
            DB::statement("ALTER TABLE `kas_harian` ADD COLUMN `total_debit_masuk` DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER `total_transfer_masuk`");
        }
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `pembayaran_riwayat` MODIFY `metode` ENUM('Tunai','Transfer','Deposit') NOT NULL");
        DB::statement("ALTER TABLE `kas_harian` DROP COLUMN `total_debit_masuk`");
    }
};
