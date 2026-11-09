<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks tanggal kartu stok untuk laporan lintas bahan per periode (Audit Bulanan, Pemakaian Bahan,
 * Eksekutif). Indeks yang ada (id_bahan, tanggal) hanya membantu bila satu bahan dipilih.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('kartu_stok')) {
            return;
        }
        $ada = collect(DB::select("show index from kartu_stok where Key_name = 'kartu_stok_tanggal_index'"))->isNotEmpty();
        if (!$ada) {
            Schema::table('kartu_stok', fn (Blueprint $t) => $t->index('tanggal', 'kartu_stok_tanggal_index'));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('kartu_stok') && collect(DB::select("show index from kartu_stok where Key_name = 'kartu_stok_tanggal_index'"))->isNotEmpty()) {
            Schema::table('kartu_stok', fn (Blueprint $t) => $t->dropIndex('kartu_stok_tanggal_index'));
        }
    }
};
