<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uang yang diserahkan pelanggan dan kembaliannya disimpan per pembayaran. Nominal pembayaran (yang
 * masuk kas dan jurnal) tetap sebesar tagihan; dua kolom ini hanya catatan serah uang supaya nota bisa
 * mencetak "Tunai" dan "Kembali" yang sebenarnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['pembayaran_riwayat', 'jersey_order_payments'] as $t) {
            if (Schema::hasTable($t) && !Schema::hasColumn($t, 'kembalian')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->decimal('uang_diterima', 15, 2)->nullable();
                    $table->decimal('kembalian', 15, 2)->default(0);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['pembayaran_riwayat', 'jersey_order_payments'] as $t) {
            if (Schema::hasTable($t) && Schema::hasColumn($t, 'kembalian')) {
                Schema::table($t, fn (Blueprint $table) => $table->dropColumn(['uang_diterima', 'kembalian']));
            }
        }
    }
};
