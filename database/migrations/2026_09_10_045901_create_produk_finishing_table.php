<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabel `produk_finishing` dibuat manual di luar migration - lihat catatan
// di 2026_09_10_045900_create_finishing_table.php. Ditaruh setelah
// create_finishing_table (butuh tabel finishing & produk sudah ada).
return new class extends Migration {
    public function up()
    {
        Schema::create('produk_finishing', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_produk')->nullable();
            $table->unsignedBigInteger('id_finishing')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('produk_finishing');
    }
};
