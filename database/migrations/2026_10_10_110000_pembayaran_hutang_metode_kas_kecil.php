<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Sumber pembayaran hutang bertambah: "Kas Kecil" (simpanan darurat) selain Tunai & Transfer. */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE pembayaran_hutang MODIFY metode VARCHAR(20) NOT NULL DEFAULT 'Tunai'");
    }

    public function down(): void
    {
        DB::table('pembayaran_hutang')->where('metode', 'Kas Kecil')->update(['metode' => 'Tunai']);
        DB::statement("ALTER TABLE pembayaran_hutang MODIFY metode ENUM('Tunai','Transfer') NOT NULL DEFAULT 'Tunai'");
    }
};
