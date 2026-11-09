<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        // Tabel Kategori (Outdoor, Indoor, dll)
        Schema::create('kategori', function (Blueprint $table) {
            $table->id('id_kategori');
            $table->string('nama_kategori', 50);
        });

        // Tabel Konversi Satuan
        Schema::create('satuan', function (Blueprint $table) {
            $table->id('id_satuan');
            $table->string('nama_satuan', 20); // cm, m, roll, pcs
            $table->decimal('nilai_konversi', 10, 2);
        });
    }

    public function down() {
        Schema::dropIfExists('satuan');
        Schema::dropIfExists('kategori');
    }
};