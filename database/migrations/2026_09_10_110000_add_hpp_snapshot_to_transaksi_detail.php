<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mengunci nilai HPP (biaya bahan baku dari BOM, dihitung pakai
 * bahan_baku.harga_rata_rata saat order dibuat) dan harga jual (subtotal
 * baris, sudah termasuk biaya_finishing & biaya_desain) per baris
 * transaksi_detail. Tanpa ini, margin per-order tidak bisa dilaporkan
 * secara akurat kalau harga_rata_rata bahan berubah di kemudian hari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_detail', function (Blueprint $table) {
            $table->decimal('hpp_snapshot', 15, 2)->default(0)->after('subtotal');
            $table->decimal('harga_jual_snapshot', 15, 2)->default(0)->after('hpp_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_detail', function (Blueprint $table) {
            $table->dropColumn(['hpp_snapshot', 'harga_jual_snapshot']);
        });
    }
};
