<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        // Tabel Users
        Schema::create('users', function (Blueprint $table) {
            $table->id('id_user');
            $table->unsignedBigInteger('id_karyawan');
            $table->string('username')->unique();
            $table->string('password');
            $table->unsignedBigInteger('id_role');
            
            // Foreign Keys sesuai kesepakatan
            $table->foreign('id_karyawan')->references('id_karyawan')->on('karyawan');
            $table->foreign('id_role')->references('id_role')->on('roles');
            $table->rememberToken();
            $table->timestamps();
        });

        // Tabel Role Permissions (Inilah kunci ON/OFF Menu)
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('id_role');
            $table->unsignedBigInteger('id_menu');
            $table->primary(['id_role', 'id_menu']);
            $table->foreign('id_role')->references('id_role')->on('roles');
            $table->foreign('id_menu')->references('id_menu')->on('menus');
        });
    }

    public function down() {
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('users');
    }
};