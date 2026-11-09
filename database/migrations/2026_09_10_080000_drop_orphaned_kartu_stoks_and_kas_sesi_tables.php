<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * kartu_stoks (plural) dan kas_sesi terbukti orphaned lewat audit: 0 baris
 * data dan 0 referensi kode aktif di seluruh aplikasi (kartu_stoks bahkan
 * cuma skeleton id+timestamps, bukan tabel ledger stok yang sesungguhnya -
 * itu adalah kartu_stok singular). kas_sesi ditinggalkan sejak lama demi
 * kas_harian (lihat komentar di KonsumenController.php baris ~48).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('kartu_stoks');
        Schema::dropIfExists('kas_sesi');
    }

    public function down(): void
    {
        Schema::create('kartu_stoks', function ($table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('kas_sesi', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->decimal('saldo_awal', 15, 2);
            $table->decimal('saldo_akhir', 15, 2)->nullable();
            $table->decimal('total_tunai', 15, 2)->default(0);
            $table->timestamp('waktu_buka')->useCurrent();
            $table->timestamp('waktu_tutup')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();
        });
    }
};
