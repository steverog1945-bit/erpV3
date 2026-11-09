<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabel `pembayaran_riwayat` dibuat manual di luar migration - lihat
// catatan di 2026_09_10_045900_create_finishing_table.php. Ditaruh sebelum
// 2026_09_10_060000_add_bank_to_pembayaran_riwayat, dan SENGAJA TIDAK
// menyertakan kolom id_bank di sini - migration 060000 itulah yang
// bertugas menambahkannya (kalau id_bank ikut dibuat di sini, 060000 akan
// gagal "Duplicate column" saat migrate:fresh dari kosong).
return new class extends Migration {
    public function up()
    {
        Schema::create('pembayaran_riwayat', function (Blueprint $table) {
            $table->id('id_pembayaran');
            $table->unsignedBigInteger('id_transaksi');
            $table->decimal('nominal', 15, 2);
            $table->enum('metode', ['Tunai', 'Transfer', 'Debit', 'Deposit']);
            $table->dateTime('tgl_bayar');
            $table->string('keterangan', 255)->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('pembayaran_riwayat');
    }
};
