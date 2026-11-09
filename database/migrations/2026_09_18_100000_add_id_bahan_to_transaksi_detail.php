<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('transaksi_detail', 'id_bahan')) {
            Schema::table('transaksi_detail', function (Blueprint $table) {
                // Baris "Penjualan Barang" (jual bahan baku langsung, bukan
                // produk cetak/jasa) - sejajar id_produk/id_jasa yang sudah
                // ada, sama-sama nullable (satu baris cuma isi SALAH SATU
                // dari ketiganya). Nota/pembayaran/laporan yang sudah ada
                // otomatis mendukung baris ini karena semuanya baca
                // transaksi_detail generik (subtotal, qty, nama_file),
                // tidak perlu jalur terpisah.
                $table->unsignedBigInteger('id_bahan')->nullable()->after('id_jasa');
                $table->foreign('id_bahan')->references('id_bahan')->on('bahan_baku')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transaksi_detail', 'id_bahan')) {
            Schema::table('transaksi_detail', function (Blueprint $table) {
                $table->dropForeign(['id_bahan']);
                $table->dropColumn('id_bahan');
            });
        }
    }
};
