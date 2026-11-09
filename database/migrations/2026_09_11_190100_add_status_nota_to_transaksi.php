<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sebelumnya tidak ada cara membatalkan NOTA (satu order penuh) sama
 * sekali - hanya per-item lewat status_spk di transaksi_detail. Untuk
 * kasus "nota salah input" (salah konsumen, duplikat, dsb) operator
 * butuh cara membatalkan seluruh nota, TAPI tetap sebagai soft-cancel
 * (nota TIDAK dihapus dari database) supaya nomor_nota, riwayat
 * pembayaran, dan kartu stok yang sudah tertaut tetap utuh untuk audit -
 * lihat PenjualanController::batalkanNota().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->enum('status_nota', ['Aktif', 'Dibatalkan'])->default('Aktif')->after('status_pembayaran');
            $table->text('alasan_batal_nota')->nullable()->after('status_nota');
            $table->timestamp('tgl_dibatalkan_nota')->nullable()->after('alasan_batal_nota');
            $table->unsignedBigInteger('id_user_pembatal_nota')->nullable()->after('tgl_dibatalkan_nota');

            $table->foreign('id_user_pembatal_nota', 'transaksi_id_user_pembatal_nota_foreign')
                ->references('id_user')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->dropForeign('transaksi_id_user_pembatal_nota_foreign');
            $table->dropColumn(['status_nota', 'alasan_batal_nota', 'tgl_dibatalkan_nota', 'id_user_pembatal_nota']);
        });
    }
};
