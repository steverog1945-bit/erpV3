<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Audit Trail (roadmap LEVEL 1 - WAJIB). Tabel append-only: tidak ada
// updated_at (baris di sini tidak pernah diedit), dan sengaja TIDAK ber-FK
// ke user_id (baris log harus tetap ada meski user penyebabnya sudah
// dihapus - itu justru bagian dari jejak yang harus dipertahankan).
return new class extends Migration {
    public function up()
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_label', 100)->nullable(); // salinan username saat kejadian - tetap terbaca walau user dihapus/berganti nama nanti
            $table->string('event', 20); // created|updated|deleted|restored
            $table->string('auditable_type', 100); // nama model Eloquent atau nama tabel (untuk jalur DB::table())
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('keterangan', 255)->nullable(); // konteks singkat, mis. "Pelunasan faktur PB-001"
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('url', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('audit_logs');
    }
};
