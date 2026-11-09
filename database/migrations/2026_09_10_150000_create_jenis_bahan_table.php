<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master Jenis Bahan (material type, mis. Vinyl/Kertas/Kain) - genuinely
 * baru. Tabel kategori yang sudah ada TIDAK bisa dipakai untuk ini: isinya
 * campur aduk antara kategori cetak asli (INDOOR/OUTDOOR/A3) dan nama mesin
 * (CENTURY STAR, RHINO DTF) - bukan taksonomi jenis material yang bersih.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_bahan', function (Blueprint $table) {
            $table->id('id_jenis_bahan');
            $table->string('nama_jenis', 100);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jenis_bahan');
    }
};
