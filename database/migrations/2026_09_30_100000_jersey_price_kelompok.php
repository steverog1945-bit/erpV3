<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tier harga jersey per kelompok ukuran: 'dewasa' (juga dipakai sebagai acuan umum) atau 'anak'. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jersey_price_settings', function (Blueprint $t) {
            $t->string('kelompok', 10)->default('dewasa');
        });
    }

    public function down(): void
    {
        Schema::table('jersey_price_settings', fn (Blueprint $t) => $t->dropColumn('kelompok'));
    }
};
