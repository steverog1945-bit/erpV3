<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * jurnal_umum sudah ada tapi 0 baris data (dikonfirmasi lewat audit),
 * jadi aman menyempitkan kode_akun ke varchar(20) (sebelumnya varchar(255)
 * tanpa constraint apa pun) supaya cocok dengan bag_akun.kode_akun, lalu
 * memasang FK RESTRICT - jurnal tidak boleh menunjuk ke kode akun yang
 * tidak terdaftar di Chart of Accounts. Pakai raw SQL untuk MODIFY COLUMN
 * karena doctrine/dbal (dipakai Schema::table()->change()) tidak terpasang
 * di project ini, dan menambah dependency baru di luar scope migrasi ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE jurnal_umum MODIFY kode_akun VARCHAR(20) NOT NULL');

        Schema::table('jurnal_umum', function (Blueprint $table) {
            $table->foreign('kode_akun')
                ->references('kode_akun')->on('bag_akun')
                ->onDelete('restrict')
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('jurnal_umum', function (Blueprint $table) {
            $table->dropForeign(['kode_akun']);
        });

        DB::statement('ALTER TABLE jurnal_umum MODIFY kode_akun VARCHAR(255) NOT NULL');
    }
};
