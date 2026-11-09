<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Produk yang dicetak SPK "lengkap" (gambar, konsumen, desainer, bahan, finishing) seperti SPK order jersey. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produk', function (Blueprint $t) {
            $t->boolean('spk_lengkap')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('produk', fn (Blueprint $t) => $t->dropColumn('spk_lengkap'));
    }
};
