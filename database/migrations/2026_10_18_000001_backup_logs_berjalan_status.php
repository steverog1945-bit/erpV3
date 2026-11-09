<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1 (audit): BackupLog::create(['status' => 'Berjalan']) - enum kolom
 * backup_logs.status diperluas dengan nilai 'Berjalan' supaya backup yang
 * masih proses / mati mendadak tidak tercatat sebagai "Sukses" palsu.
 * Penurunan mundur (down) akan gagal kalau sudah ada baris 'Berjalan' -
 * normalisasi dulu ke 'Gagal' sebelum enum dikembalikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE backup_logs MODIFY COLUMN status ENUM('Sukses','Gagal','Berjalan') NOT NULL DEFAULT 'Sukses'");
    }

    public function down(): void
    {
        DB::table('backup_logs')->where('status', 'Berjalan')->update(['status' => 'Gagal']);
        DB::statement("ALTER TABLE backup_logs MODIFY COLUMN status ENUM('Sukses','Gagal') NOT NULL DEFAULT 'Sukses'");
    }
};
