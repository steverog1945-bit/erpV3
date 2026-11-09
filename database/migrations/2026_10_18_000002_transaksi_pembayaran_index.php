<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2 (audit): Monitoring Kasir & waiting-list selalu memfilter
 * status_pembayaran (+status_nota) - kombinasi ini tidak punya index.
 * pembayaran_riwayat.id_transaksi (dipakai SUM per nota di hampir semua
 * layar kasir) juga. Sekalian menambah kolom created_at yang murni
 * hilang di transaksi (PenjualanController::store memakai insertGetId()
 * yang tidak mengisi timestamp otomatis) - walau penulisannya sudah
 * diperbaiki di controller, data lama tetap NULL; index ini untuk
 * sorting COALESCE(updated_at, tgl_order) tetap aman tanpa created_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            if (!$this->adaIndex('transaksi', 'transaksi_status_monitoring_index')) {
                $table->index(['status_pembayaran', 'status_nota'], 'transaksi_status_monitoring_index');
            }
        });

        Schema::table('pembayaran_riwayat', function (Blueprint $table) {
            if (!$this->adaIndex('pembayaran_riwayat', 'pembayaran_riwayat_id_transaksi_index')) {
                $table->index('id_transaksi', 'pembayaran_riwayat_id_transaksi_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            if ($this->adaIndex('transaksi', 'transaksi_status_monitoring_index')) {
                $table->dropIndex('transaksi_status_monitoring_index');
            }
        });

        Schema::table('pembayaran_riwayat', function (Blueprint $table) {
            if ($this->adaIndex('pembayaran_riwayat', 'pembayaran_riwayat_id_transaksi_index')) {
                $table->dropIndex('pembayaran_riwayat_id_transaksi_index');
            }
        });
    }

    private function adaIndex(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM {$table}"))->pluck('Key_name')->contains($index);
    }
};
