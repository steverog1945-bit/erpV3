<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Bahan (kain) order jersey disinkronkan dengan Master Bahan Baku: stok & harga per kg satu sumber. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jersey_materials', function (Blueprint $t) {
            $t->unsignedBigInteger('id_bahan')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('jersey_materials', fn (Blueprint $t) => $t->dropColumn('id_bahan'));
    }
};
