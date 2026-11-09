<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('app_settings', 'libur_kas_minggu')) {
            Schema::table('app_settings', function (Blueprint $table) {
                // Kalau aktif, Open Saldo & Closing Kas diblokir setiap hari
                // Minggu - permintaan eksplisit user (toko tutup/tidak ada
                // yang jaga kas hari Minggu). Default TRUE karena inilah
                // perilaku yang diminta sekarang; admin tetap bisa
                // menonaktifkan dari Pengaturan Aplikasi kalau ternyata toko
                // buka hari Minggu.
                $table->boolean('libur_kas_minggu')->default(true)->after('website');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('app_settings', 'libur_kas_minggu')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->dropColumn('libur_kas_minggu');
            });
        }
    }
};
