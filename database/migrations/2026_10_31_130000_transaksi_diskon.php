<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diskon tingkat nota yang diberikan kasir disimpan. Sebelumnya hanya dipakai saat itu: form pembayaran berikutnya
 * mulai dari 0 lagi, sehingga pelunasan mengembalikan total nota ke harga sebelum diskon (piutang muncul lagi).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('transaksi', 'diskon')) {
            Schema::table('transaksi', function (Blueprint $table) {
                $table->decimal('diskon', 15, 2)->default(0)->after('total_bayar');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transaksi', 'diskon')) {
            Schema::table('transaksi', function (Blueprint $table) {
                $table->dropColumn('diskon');
            });
        }
    }
};
