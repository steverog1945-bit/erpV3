<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Collecting (halaman /collecting) sebelumnya murni dashboard status -
 * menghitung kelengkapan item per nota, tapi TIDAK ADA aksi apa pun untuk
 * benar-benar menandai "sudah diambil customer". Kolom 'Status Collecting'
 * yang ada sebenarnya mengukur KELENGKAPAN PENGERJAAN (Lengkap/Belum
 * Lengkap dari transaksi_detail.status_spk), bukan status pengambilan
 * barang - dua konsep berbeda yang kebetulan dinamai sama.
 *
 * Kolom baru ini murni status PENGAMBILAN FISIK oleh customer - terpisah
 * dari status pengerjaan yang sudah ada. Ditaruh di tabel transaksi
 * (bukan tabel baru) karena ini atribut satu nota, bukan entitas
 * tersendiri dengan relasi kompleks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->enum('status_collecting', ['Belum Diambil', 'Sudah Diambil'])
                ->default('Belum Diambil')
                ->after('status_pembayaran');
            $table->timestamp('tgl_collecting')->nullable()->after('status_collecting');
            $table->foreignId('id_user_collecting')->nullable()
                ->after('tgl_collecting')
                ->constrained('users', 'id_user')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->dropForeign(['id_user_collecting']);
            $table->dropColumn(['status_collecting', 'tgl_collecting', 'id_user_collecting']);
        });
    }
};
