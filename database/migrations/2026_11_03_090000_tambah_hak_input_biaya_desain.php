<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hak "boleh MENGINPUT Biaya Desain" per role - dipisah dari
 * roles.boleh_lihat_harga yang sudah ada.
 *
 * Latar belakang: dulu tombol "+ Biaya Desain" dan input nominalnya di
 * Input Order di-gate oleh @bolehHarga (lihat app/Providers/AppServiceProvider.php).
 * Akibatnya Operator Desain - yang PEKERJAANNYA justru desain, tapi tidak
 * boleh melihat harga - tidak pernah bisa mengisi biaya yang ia kerjakan.
 * Menyalakan boleh_lihat_harga untuk role itu berarti ia ikut melihat SEMUA
 * harga (produk, cetak, finishing, halaman Finishing/Quotation, timeline nilai),
 * yaitu izin yang jauh lebih besar dari satu field. Jadi dua hak itu dipisah.
 *
 * Bedanya dengan boleh_lihat_harga:
 *   - boleh_lihat_harga        : boleh LIHAT & MENGUBAH angka harga di mana saja.
 *   - boleh_input_biaya_desain : boleh MENGISI nilai Biaya Desain saja pada satu
 *                                 baris Input Order. Tidak membuka halaman harga,
 *                                 tidak membuat harga produk/cetak/finishing terlihat.
 *
 * DEFAULT 0 (gagal-terutup): tidak ada role pun yang dinyalakan di migration ini,
 * karena menyalakannya adalah keputusan bisnis pemilik, bukan perubahan teknis.
 * Role menyalakannya dari halaman Pengaturan Hak Akses.
 *
 * Catatan: nilai ini SENGAJA TIDAK ikut sidik jari versi role
 * (AksesController::versiRole) - mengubah centang desain tidak mengubah
 * tampilan halaman pengaturan, jadi tidak boleh menggagalkan optimistic locking.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempoten: aman dijalankan ulang / di database yang kolomnya sudah ada.
        if (Schema::hasColumn('roles', 'boleh_input_biaya_desain')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table) {
            // tinyint(1) NOT NULL DEFAULT 0 - "belum ada role yang boleh"
            $table->boolean('boleh_input_biaya_desain')
                ->default(false)
                ->after('boleh_lihat_harga');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('roles', 'boleh_input_biaya_desain')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('boleh_input_biaya_desain');
            });
        }
    }
};