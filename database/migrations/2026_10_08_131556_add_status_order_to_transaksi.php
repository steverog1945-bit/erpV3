<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->enum('status_order', ['Menunggu Kasir', 'Diterima Kasir', 'Dibatalkan'])
                ->default('Menunggu Kasir')
                ->after('status_nota');

            $table->index(['status_order', 'tgl_order'], 'transaksi_status_order_tgl_order_index');
        });
    }

    public function down(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->dropIndex('transaksi_status_order_tgl_order_index');
            $table->dropColumn('status_order');
        });
    }
};