<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabel `worksheet` dibuat manual di luar migration - lihat catatan di
// 2026_09_10_045900_create_finishing_table.php.
return new class extends Migration {
    public function up()
    {
        Schema::create('worksheet', function (Blueprint $table) {
            $table->id('id_worksheet');
            $table->string('nama_mesin', 100)->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('worksheet');
    }
};
