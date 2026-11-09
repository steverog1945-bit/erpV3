<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan tampilan logo di sidebar: ukuran, gaya latar, cara logo
 * ditampilkan saat sidebar diringkas, dan logo ikon opsional (persegi)
 * untuk mode ringkas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('app_settings', 'logo_ukuran')) {
                $table->string('logo_ukuran', 10)->default('sedang');
            }
            if (!Schema::hasColumn('app_settings', 'logo_gaya')) {
                $table->string('logo_gaya', 10)->default('polos');
            }
            if (!Schema::hasColumn('app_settings', 'logo_ringkas')) {
                $table->string('logo_ringkas', 10)->default('potong');
            }
            if (!Schema::hasColumn('app_settings', 'logo_ikon_path')) {
                $table->string('logo_ikon_path')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            foreach (['logo_ukuran', 'logo_gaya', 'logo_ringkas', 'logo_ikon_path'] as $kolom) {
                if (Schema::hasColumn('app_settings', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};
