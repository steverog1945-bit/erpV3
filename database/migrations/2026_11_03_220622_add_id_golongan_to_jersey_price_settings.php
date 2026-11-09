<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jersey_price_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('jersey_price_settings', 'id_golongan')) {
                $table->unsignedBigInteger('id_golongan')->nullable()->after('kelompok');
                $table->index('id_golongan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('jersey_price_settings', function (Blueprint $table) {
            if (Schema::hasColumn('jersey_price_settings', 'id_golongan')) {
                $table->dropColumn('id_golongan');
            }
        });
    }
};