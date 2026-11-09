<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabel `bank_perusahaan` (master rekening bank milik toko, dipakai
// BankController & pembayaran_riwayat.id_bank) dibuat manual di luar
// migration - lihat catatan panjang di
// 2026_09_10_045900_create_finishing_table.php untuk kenapa ini penting
// diperbaiki (migrate:fresh dari kosong gagal tanpa ini).
return new class extends Migration {
    public function up()
    {
        Schema::create('bank_perusahaan', function (Blueprint $table) {
            $table->id('id_bank');
            $table->string('nama_bank', 50);
            $table->string('nomor_rekening', 50);
            $table->string('atas_nama', 100);
        });
    }

    public function down()
    {
        Schema::dropIfExists('bank_perusahaan');
    }
};
