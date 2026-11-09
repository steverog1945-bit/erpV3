<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Rework yang tidak jadi dicetak ulang (mis. pelanggan menerima hasil reject) bisa ditutup: status baru "Ditutup".
return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE rework MODIFY status ENUM('Dibuka','Selesai','Ditutup') NOT NULL DEFAULT 'Dibuka'");
    }

    public function down(): void
    {
        DB::table('rework')->where('status', 'Ditutup')->update(['status' => 'Selesai']);
        DB::statement("ALTER TABLE rework MODIFY status ENUM('Dibuka','Selesai') NOT NULL DEFAULT 'Dibuka'");
    }
};
