<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ukuran jersey dikelompokkan (dewasa / anak) dan boleh punya selisih harga persen
 * (mis. ukuran anak -20% dari harga tier) selain tambahan nominal tetap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jersey_sizes', function (Blueprint $t) {
            $t->string('kelompok', 10)->default('dewasa');
            $t->decimal('price_percent', 6, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('jersey_sizes', fn (Blueprint $t) => $t->dropColumn(['kelompok', 'price_percent']));
    }
};
