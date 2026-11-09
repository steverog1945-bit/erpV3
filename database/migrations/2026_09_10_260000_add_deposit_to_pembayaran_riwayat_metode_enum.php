<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * pembayaran_riwayat.metode adalah enum('Tunai','Transfer') - ditemukan
 * lewat pengujian langsung saat menghubungkan pembayaran metode Deposit:
 * insert gagal dengan "Data truncated for column 'metode'" karena 'Deposit'
 * bukan salah satu nilai enum yang diizinkan. Menambahkan 'Deposit' ke
 * enum yang sudah ada, tidak mengubah nilai yang sudah tersimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE pembayaran_riwayat MODIFY metode ENUM('Tunai','Transfer','Deposit') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE pembayaran_riwayat MODIFY metode ENUM('Tunai','Transfer') NOT NULL");
    }
};
