<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Mesin pendukung (mis. cutter Roland) tercatat sebagai aset tetapi tidak menjadi tab divisi di SPK/Produksi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mesin', function (Blueprint $t) {
            $t->boolean('tampil_divisi')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('mesin', fn (Blueprint $t) => $t->dropColumn('tampil_divisi'));
    }
};
