<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Audit skema (Phase 1) menemukan beberapa kolom bergaya ID yang tipenya
// tidak konsisten dengan konvensi aplikasi (semua PK = bigint(20) unsigned
// auto_increment): sebagian masih int(11), sebagian bigint SIGNED. Semua
// perubahan di sini murni WIDENING (int->bigint, signed->unsigned) pada
// kolom yang sudah dipastikan tidak punya nilai negatif - tidak ada risiko
// kehilangan/mengubah data, murni menyamakan tipe.
return new class extends Migration {
    public function up()
    {
        DB::statement('ALTER TABLE `finishing` MODIFY `id_kategori` BIGINT(20) UNSIGNED NULL');
        DB::statement('ALTER TABLE `kategori_finishing` MODIFY `id_kategori` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE `worksheet` MODIFY `id_worksheet` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE `menus` MODIFY `parent_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE `pembayaran_riwayat` MODIFY `id_pembayaran` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE `pembayaran_riwayat` MODIFY `id_transaksi` BIGINT(20) UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE `produk` MODIFY `id_mesin` BIGINT(20) UNSIGNED NULL');
        DB::statement('ALTER TABLE `produk_finishing` MODIFY `id_produk` BIGINT(20) UNSIGNED NULL');
        DB::statement('ALTER TABLE `produk_finishing` MODIFY `id_finishing` BIGINT(20) UNSIGNED NULL');
    }

    public function down()
    {
        DB::statement('ALTER TABLE `finishing` MODIFY `id_kategori` BIGINT(20) NULL');
        DB::statement('ALTER TABLE `kategori_finishing` MODIFY `id_kategori` BIGINT(20) NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE `worksheet` MODIFY `id_worksheet` BIGINT(20) NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE `menus` MODIFY `parent_id` INT(11) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE `pembayaran_riwayat` MODIFY `id_pembayaran` INT(11) NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE `pembayaran_riwayat` MODIFY `id_transaksi` INT(11) NOT NULL');
        DB::statement('ALTER TABLE `produk` MODIFY `id_mesin` INT(11) NULL');
        DB::statement('ALTER TABLE `produk_finishing` MODIFY `id_produk` INT(11) NULL');
        DB::statement('ALTER TABLE `produk_finishing` MODIFY `id_finishing` INT(11) NULL');
    }
};
