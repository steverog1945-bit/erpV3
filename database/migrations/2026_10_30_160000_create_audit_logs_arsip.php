<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Arsip log audit: struktur sama persis dengan audit_logs. Baris lama dipindahkan ke sini oleh
 * `php artisan data:rapikan` (tidak dihapus), supaya tabel yang dipakai sehari-hari tetap kecil sementara
 * jejak lama tetap bisa dibuka dari halaman Log Aktivitas (pilihan "Arsip").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs') && !Schema::hasTable('audit_logs_arsip')) {
            DB::statement('CREATE TABLE audit_logs_arsip LIKE audit_logs');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs_arsip');
    }
};
