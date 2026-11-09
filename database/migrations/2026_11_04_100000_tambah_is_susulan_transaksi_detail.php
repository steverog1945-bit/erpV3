<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah penanda "susulan" ke transaksi_detail.
     *
     * Dipakai oleh fitur "Tambah Item Susulan" (PenjualanController::
     * tambahItemSusulan) untuk membedakan item yang ditambahkan SETELAH
     * nota awal disimpan dari item awal. Pola yang sama dengan
     * jersey_order_lines.is_susulan, supaya SPK/cetak/nota bisa menampilkan
     * keduanya dengan jelas (item awal vs tambahan).
     *
     * Kolom ini ADDITIVE (default false) dan tidak mengubah data existing,
     * sehingga aman untuk nota yang sudah tersimpan sebelum migrasi ini.
     */
    public function up(): void
    {
        Schema::table('transaksi_detail', function (Blueprint $table) {
            if (! Schema::hasColumn('transaksi_detail', 'is_susulan')) {
                $table->boolean('is_susulan')->default(false)->after('status_spk');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_detail', function (Blueprint $table) {
            if (Schema::hasColumn('transaksi_detail', 'is_susulan')) {
                $table->dropColumn('is_susulan');
            }
        });
    }
};