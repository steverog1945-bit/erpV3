<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Letterhead cetak laporan (referensi user) menampilkan Fax & Website di
 * samping Telepon/Email yang sudah ada - kolom app_settings sebelumnya
 * belum menampung keduanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('app_settings', 'fax')) {
                $table->string('fax', 50)->nullable()->after('telepon');
            }
            if (!Schema::hasColumn('app_settings', 'website')) {
                $table->string('website', 255)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            if (Schema::hasColumn('app_settings', 'fax')) {
                $table->dropColumn('fax');
            }
            if (Schema::hasColumn('app_settings', 'website')) {
                $table->dropColumn('website');
            }
        });
    }
};
