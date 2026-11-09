<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Approval Workflow generik (roadmap LEVEL 1 - WAJIB). Sebelumnya
// approval cuma ada per-modul (mis. nota_edit_request khusus edit nota) -
// tabel ini dipakai bersama oleh SEMUA jenis aksi yang butuh persetujuan
// (batalkan nota, diskon besar, dst ke depannya), lihat
// App\Services\ApprovalService.
return new class extends Migration {
    public function up()
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('tipe_aksi', 50); // 'batalkan_nota', dst - dipetakan ke logic eksekusi di ApprovalController
            $table->string('referensi_type', 50)->nullable(); // nama tabel/model terkait, mis. 'transaksi'
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->json('payload')->nullable(); // parameter yang dibutuhkan untuk MENGEKSEKUSI aksi setelah disetujui
            $table->string('ringkasan', 255)->nullable(); // teks singkat untuk ditampilkan di daftar approval, mis. "Batalkan nota INV-001"
            $table->unsignedBigInteger('requested_by');
            $table->string('alasan_pengajuan', 500)->nullable();
            $table->enum('status', ['Menunggu', 'Disetujui', 'Ditolak'])->default('Menunggu');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->string('catatan_keputusan', 500)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['tipe_aksi', 'status']);
            $table->index(['referensi_type', 'referensi_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('approval_requests');
    }
};
