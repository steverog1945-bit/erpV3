<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master Jasa (layanan non-material, mis. laminasi luar, instalasi) -
 * genuinely baru. id_satuan dipakai ulang dari master satuan yang sudah
 * ada (bukan bikin tabel satuan baru).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jasa', function (Blueprint $table) {
            $table->id('id_jasa');
            $table->string('nama_jasa', 100);
            $table->foreignId('id_satuan')->nullable()
                ->constrained('satuan', 'id_satuan')->nullOnDelete();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jasa');
    }
};
