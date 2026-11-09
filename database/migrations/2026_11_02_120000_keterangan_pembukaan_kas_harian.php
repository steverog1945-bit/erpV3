<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simpan alasan ketika saldo awal sesi kas TIDAK sama dengan isi laci sesi
 * sebelumnya.
 *
 * Latar belakang: sesi kas dihitung terpisah-pisah, tidak beruntun
 * (tidak ada satu pun SUM antar sesi). Rantainya:
 *
 *     saldo_akhir = saldo_awal + tunai_masuk + kas_masuk - kas_keluar
 *     selisih     = saldo_fisik - saldo_akhir
 *
 * dan saldo_awal sesi berikutnya diambil dari saldo_fisik sesi sebelumnya
 * lewat saran "Rolling" (KasirController::kelolaKas). Saran itu hanya
 * methadone - kasir bebas mengetik angka lain, dan mengetik angka lain itu
 *artinya saat ini tidak ada yang bisa menjelaskan.
 *
 * Bukti nyata di oriondb saat migration ini dibuat:
 *
 *   Sesi #2 ditutup dengan isi laci Rp436.193 dan status_rolling='Rolling'.
 *   Sesi #3 dibuka dengan saldo_awal Rp100.000 - sistem sudah menawarkan
 *   Rp436.193 lewat saran, tapi tidak ada peringatan, tidak ada catatan,
 *   dan selisih Rp336.193 hilang tanpa jejak.
 *
 * Kolom ini nullable. Nullable, bukan default kosong, supaya "kasir tidak
 * menemukan selisih" (kolom NULL) berbeda dari "kasir menemukan selisih
 * dan menuliskan penjelasannya" (kolom terisi) saat ditelusuri/
 * direkonsiliasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kas_harian', function (Blueprint $table) {
            $table->text('keterangan_pembukaan')->nullable()->after('keterangan_closing');
        });
    }

    public function down(): void
    {
        Schema::table('kas_harian', function (Blueprint $table) {
            $table->dropColumn('keterangan_pembukaan');
        });
    }
};
