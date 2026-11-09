<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        // Tabel Karyawan
        Schema::create('karyawan', function (Blueprint $table) {
            $table->id('id_karyawan');
            $table->string('nama_karyawan', 100);
            $table->enum('bagian', ['Admin', 'Desain', 'Produksi', 'Kasir', 'Gudang']);
            $table->string('telepon', 20);
            $table->boolean('status_aktif')->default(1);
            $table->timestamps();
        });

        // Tabel Roles
        Schema::create('roles', function (Blueprint $table) {
            $table->id('id_role');
            $table->string('nama_role', 50);
        });

        // Tabel Menus (Untuk ON/OFF Checkbox)
        Schema::create('menus', function (Blueprint $table) {
            $table->id('id_menu');
            $table->string('nama_menu', 100);
            $table->string('url_link', 100);
            $table->integer('parent_id')->default(0);
        });
    }

    public function down() {
        Schema::dropIfExists('menus');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('karyawan');
    }
};