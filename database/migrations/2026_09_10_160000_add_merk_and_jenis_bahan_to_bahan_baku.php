<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom nullable murni - tidak wajib diisi, tidak mengubah data/logic yang
 * sudah ada di bahan_baku. Memberi Merk & Jenis Bahan sebuah consumer nyata
 * (bukan master data yang berdiri sendiri tanpa dipakai di mana pun).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bahan_baku', function (Blueprint $table) {
            $table->foreignId('id_merk')->nullable()->after('id_kategori')
                ->constrained('merk', 'id_merk')->nullOnDelete();
            $table->foreignId('id_jenis_bahan')->nullable()->after('id_merk')
                ->constrained('jenis_bahan', 'id_jenis_bahan')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bahan_baku', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_merk');
            $table->dropConstrainedForeignId('id_jenis_bahan');
        });
    }
};
