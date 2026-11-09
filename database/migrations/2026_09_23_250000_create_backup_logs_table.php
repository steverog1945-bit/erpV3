<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Backup & Recovery (roadmap LEVEL 1 - WAJIB, "wajib sebelum production").
// Satu baris per eksekusi backup (bukan cuma status terakhir) supaya
// riwayat lengkap bisa ditelusuri di halaman /backup.
return new class extends Migration {
    public function up()
    {
        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('tipe', ['daily', 'weekly', 'monthly', 'manual']);
            $table->string('file_path', 500)->nullable();
            $table->unsignedBigInteger('ukuran_bytes')->nullable();
            $table->enum('status', ['Sukses', 'Gagal'])->default('Sukses');
            $table->text('pesan_error')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['tipe', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('backup_logs');
    }
};
