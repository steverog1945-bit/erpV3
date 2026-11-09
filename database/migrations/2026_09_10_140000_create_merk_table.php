<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master Merk (brand) bahan baku - genuinely baru, belum ada tabel apa pun
 * yang menampung ini sebelumnya (dikonfirmasi lewat audit sebelum migrasi
 * ini ditulis).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merk', function (Blueprint $table) {
            $table->id('id_merk');
            $table->string('nama_merk', 100);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merk');
    }
};
