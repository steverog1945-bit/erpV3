<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda barang "Susulan" - dipakai tombol "+ Tambah Barang Susulan" di
 * halaman detail order (beda dari "Kelola Barang" yang hapus-lalu-buat-
 * ulang SEMUA barang) untuk kasus qty/jenis barang bertambah SETELAH order
 * awal berjalan (mis. panitia acara bertambah, butuh jersey tambahan).
 * Barang lama sama sekali tidak disentuh - cuma baris baru ini yang
 * ditandai, supaya jelas terlihat mana pesanan awal vs tambahan susulan di
 * halaman detail & cetak.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('jersey_order_lines', 'is_susulan')) {
            Schema::table('jersey_order_lines', function (Blueprint $table) {
                $table->boolean('is_susulan')->default(false)->after('order_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('jersey_order_lines', 'is_susulan')) {
            Schema::table('jersey_order_lines', function (Blueprint $table) {
                $table->dropColumn('is_susulan');
            });
        }
    }
};
