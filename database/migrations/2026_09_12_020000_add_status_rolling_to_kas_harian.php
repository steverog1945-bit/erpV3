<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Status Rolling Saldo Akhir" - persis dari referensi "Closing Alur Kas
 * Kasir" yang sebelumnya SENGAJA dilewati (butuh klarifikasi pemilik
 * aplikasi dulu, sudah dijelaskan): apakah saldo akhir hari ini dibawa
 * lanjut jadi saldo awal BESOK (uang tetap di laci), atau TIDAK di-roll
 * (uang diambil/disetor, besok mulai dari saldo baru yang diketik manual).
 *
 * doctrine/dbal TIDAK terpasang - raw DB::statement() untuk ALTER TABLE,
 * sama seperti migrasi enum lain di project ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `kas_harian` ADD COLUMN `status_rolling` ENUM('Rolling','Tidak Rolling') NULL DEFAULT NULL AFTER `keterangan_closing`");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `kas_harian` DROP COLUMN `status_rolling`");
    }
};
