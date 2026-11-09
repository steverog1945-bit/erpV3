<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabel `kas_harian` dibuat manual di luar migration - lihat catatan di
// 2026_09_10_045900_create_finishing_table.php. Ditaruh sebelum
// 2026_09_12_010000_create_kas_manual_table (yang FK ke tabel ini) dan
// sebelum 2026_09_11_250000 (nambah total_debit_masuk, sudah ada
// hasColumn guard jadi aman didobel) & 2026_09_12_020000 (nambah
// status_rolling, TIDAK ada guard - sengaja TIDAK disertakan di sini
// supaya migration itu yang menambahkannya, tidak dobel).
return new class extends Migration {
    public function up()
    {
        Schema::create('kas_harian', function (Blueprint $table) {
            $table->id('id_kas');
            $table->date('tgl_kas');
            $table->unsignedBigInteger('id_user');
            $table->decimal('saldo_awal', 15, 2);
            $table->decimal('total_tunai_masuk', 15, 2)->nullable()->default(0);
            $table->decimal('total_transfer_masuk', 15, 2)->nullable()->default(0);
            $table->decimal('total_debit_masuk', 15, 2)->default(0);
            $table->decimal('total_tunai_keluar', 15, 2)->nullable()->default(0);
            $table->decimal('saldo_akhir', 15, 2)->default(0);
            $table->decimal('saldo_fisik', 15, 2)->nullable();
            $table->decimal('selisih', 15, 2)->nullable();
            $table->decimal('total_transfer', 15, 2)->default(0);
            $table->decimal('total_diskon', 15, 2)->nullable()->default(0);
            $table->enum('status_lock', ['open', 'closed'])->default('open');
            $table->timestamp('waktu_tutup')->nullable();
            $table->text('keterangan_closing')->nullable();
            $table->timestamps();

            $table->index('id_user', 'kas_harian_id_user_foreign');
        });
    }

    public function down()
    {
        Schema::dropIfExists('kas_harian');
    }
};
