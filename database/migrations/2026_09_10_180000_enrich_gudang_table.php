<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * gudang sudah ada (dipakai Kedatangan Bahan) tapi cuma 2 kolom
 * (id_gudang, nama_gudang) dan belum punya halaman CRUD sendiri. Kolom
 * baru nullable - tidak mengganggu baris yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gudang', function (Blueprint $table) {
            $table->text('alamat')->nullable()->after('nama_gudang');
            $table->string('penanggung_jawab', 100)->nullable()->after('alamat');
            $table->string('telepon', 30)->nullable()->after('penanggung_jawab');
        });
    }

    public function down(): void
    {
        Schema::table('gudang', function (Blueprint $table) {
            $table->dropColumn(['alamat', 'penanggung_jawab', 'telepon']);
        });
    }
};
