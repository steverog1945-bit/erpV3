<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alur "Edit Nota": nota yang sudah tersimpan sengaja tidak punya edit
 * langsung (lihat catatan di PenjualanController::show) karena stok &
 * jurnal akuntansi sudah diposting saat dibuat - koreksi butuh pembalikan
 * dulu, bukan update baris biasa. Tabel ini murni mencatat ALUR
 * PERSETUJUANNYA (siapa minta, kenapa, siapa setuju/tolak, kapan) - proses
 * pembalikan & pembuatan nota koreksinya sendiri dieksekusi lewat
 * PenjualanController::applyApprovedEdit() begitu status jadi 'Disetujui',
 * menggunakan ulang logic batalkanNota() (reversal stok) + halaman Kasir
 * Utama (pembuatan nota baru) yang sudah teruji, bukan menulis ulang logic
 * pembalikan/perhitungan harga dari nol.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nota_edit_request', function (Blueprint $table) {
            $table->id('id_request');
            $table->unsignedBigInteger('id_transaksi');
            $table->unsignedBigInteger('id_user_requester');
            $table->text('alasan');
            $table->enum('status', ['Menunggu', 'Disetujui', 'Ditolak', 'Selesai'])->default('Menunggu');
            $table->unsignedBigInteger('id_user_approver')->nullable();
            $table->text('catatan_approver')->nullable();
            $table->timestamp('tgl_keputusan')->nullable();
            $table->timestamp('tgl_selesai')->nullable();
            $table->unsignedBigInteger('id_transaksi_koreksi')->nullable();
            $table->timestamps();

            $table->foreign('id_transaksi')->references('id_transaksi')->on('transaksi');
            $table->foreign('id_user_requester')->references('id_user')->on('users');
            $table->foreign('id_user_approver')->references('id_user')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_edit_request');
    }
};
