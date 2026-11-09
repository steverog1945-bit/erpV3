<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * API-3: idempotensi checkout API - TAHAN-CRASH, bukan cache sementara.
     *
     * Satu (id_user, submit_id) = SATU klaim lewat UNIQUE constraint database
     * (bukan Cache::add yang bisa hilang restart/kedaluwarsa). Row menyimpan
     * sidik payload + hasil checkout:
     *  - 'diproses': runner sedang mengeksekusi checkout (row ditanam SEBELUM
     *    transaksi DB checkout dimulai, jadi klaim eksklusif sudah terpegang).
     *  - 'sah'     : checkout sukses; id_transaksi diikat dan row di-finalisasi
     *    DALAM transaksi DB yang sama dengan nota/detail/stok/jurnal, jadi
     *    keempatnya ter-commit atomik - tidak ada jendela "nota hidup tapi
     *    klaim belum selesai" yang bisa melahirkan nota kedua.
     *  - 'gagal'   : dipakai hanya sekejap selama rollback memorandum retry;
     *    runner yang gagal MENGHAPUS row supaya ulang-kirim yang sah tetap
     *    mungkin (tidak ada catatan sukses palsu).
     *
     * Tabel ini HANYA untuk jalur API; web tetap memakai SubmitSekaliJalan.
     */
    public function up(): void
    {
        Schema::create('api_checkout_idempotensi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user');
            $table->string('submit_id', 100);
            $table->char('payload_hash', 64);
            $table->unsignedBigInteger('id_transaksi')->nullable();
            $table->string('status', 12)->default('diproses');
            $table->timestamps();

            $table->unique(['id_user', 'submit_id']);
            $table->foreign('id_user')->references('id_user')->on('users');
            $table->foreign('id_transaksi')->references('id_transaksi')->on('transaksi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_checkout_idempotensi');
    }
};
