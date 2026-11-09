<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu item order boleh punya BEBERAPA finishing (mis. Laminasi + Kisscut), masing-masing dengan spek
 * Standar/Custom, jumlah pakai, catatan, dan biayanya sendiri. transaksi_detail.biaya_finishing tetap
 * menyimpan TOTAL semua finishing (laporan & kasir tidak berubah); id_finishing = finishing pertama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi_detail_finishing', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('id_detail')->index();
            $t->unsignedBigInteger('id_finishing');
            $t->string('status_finishing', 10)->default('Standar'); // Standar | Custom
            $t->decimal('qty_pakai', 14, 4)->nullable();
            $t->decimal('biaya', 15, 2)->default(0);
            $t->string('catatan', 255)->nullable();
            $t->timestamps();
        });

        // Data lama: finishing tunggal di transaksi_detail disalin sebagai satu baris agar tampilan seragam.
        $lama = DB::table('transaksi_detail')->whereNotNull('id_finishing')->get(['id_detail', 'id_finishing', 'biaya_finishing', 'status_finishing']);
        foreach ($lama as $d) {
            DB::table('transaksi_detail_finishing')->insert([
                'id_detail' => $d->id_detail, 'id_finishing' => $d->id_finishing, 'status_finishing' => $d->status_finishing ?: 'Standar',
                'biaya' => (float) $d->biaya_finishing, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_detail_finishing');
    }
};
