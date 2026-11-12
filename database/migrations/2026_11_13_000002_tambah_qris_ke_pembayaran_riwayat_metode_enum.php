<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * QRIS = metode pembayaran yang umum untuk pelanggan kecil (cashless Asia).
 * Ditambahkan sebagai metode tersendiri, sama seperti Deposit ditambahkan
 * lewat migrasi sebelumnya. Tidak mengubah nilai yang sudah tersimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE pembayaran_riwayat MODIFY metode ENUM('Tunai','Transfer','Debit','Deposit','QRIS') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE pembayaran_riwayat MODIFY metode ENUM('Tunai','Transfer','Debit','Deposit') NOT NULL");
    }
};
