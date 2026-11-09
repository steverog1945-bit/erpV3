<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua kolom baru untuk menutup celah di alur "Custom Harga" saat pembayaran
 * (KasirController::prosesBayar):
 *
 * - biaya_desain: SEBELUMNYA dihitung ke subtotal saat order dibuat
 *   (PenjualanController::store) TAPI TIDAK PERNAH disimpan sebagai kolom
 *   sendiri - beda dengan biaya_cetak & biaya_finishing yang memang py
 *   kolom. Akibatnya nilai biaya desain "hilang" begitu order tersimpan,
 *   tidak bisa ditinjau/dikoreksi lagi setelahnya.
 * - potongan: belum pernah ada sama sekali - dipakai untuk kasus operator
 *   lupa memberi diskon/potongan per-item saat nota dibuat, dikoreksi
 *   belakangan saat pembayaran tanpa harus mengedit nota dari awal.
 *
 * Keduanya default 0 supaya baris lama (dibuat sebelum migrasi ini) tetap
 * konsisten - subtotal yang sudah tersimpan tidak berubah maknanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_detail', function (Blueprint $table) {
            $table->decimal('biaya_desain', 15, 2)->default(0)->after('biaya_cetak');
            $table->decimal('potongan', 15, 2)->default(0)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_detail', function (Blueprint $table) {
            $table->dropColumn(['biaya_desain', 'potongan']);
        });
    }
};
