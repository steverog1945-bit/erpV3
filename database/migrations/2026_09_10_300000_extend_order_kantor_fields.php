<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field tambahan untuk Kasir Utama (bukan modul baru - audit menunjukkan
 * field yang diminta di spec "Order Kantor" sebagian besar sudah ada di
 * transaksi/transaksi_detail dengan nama berbeda: metode_hitung = "is
 * calculated by size", tipe_konsumen = permanent/temporary, p_ukuran/
 * l_ukuran/qty/nama_file/catatan_produksi/status_urgent/status_spk semua
 * sudah ada). Ini cuma menambah yang BENAR-BENAR belum ada.
 *
 * status_urgent (tinyint, dipakai SpkController::toggleUrgent/bulkUrgent
 * untuk override manual di layar SPK) SENGAJA tidak diubah/dihapus -
 * status_urgensi di sini adalah urgensi AWAL yang dipilih kasir saat order
 * dibuat, keduanya independen (PenjualanController akan menurunkan
 * status_urgent dari status_urgensi saat insert supaya layar SPK yang
 * sudah ada tetap jalan tanpa perubahan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->enum('tipe_order', ['Reguler', 'PO'])->default('Reguler')->after('nomor_nota');
            $table->string('no_po', 100)->nullable()->after('tipe_order');
        });

        Schema::table('transaksi_detail', function (Blueprint $table) {
            $table->decimal('biaya_cetak', 15, 2)->default(0)->after('biaya_finishing');
            $table->enum('status_urgensi', ['Normal', 'Urgent', 'Very Urgent'])->default('Normal')->after('status_urgent');
            $table->enum('status_finishing', ['Standar', 'Custom'])->default('Standar')->after('status_urgensi');
            $table->string('thumbnail', 255)->nullable()->after('nama_file');
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_detail', function (Blueprint $table) {
            $table->dropColumn(['biaya_cetak', 'status_urgensi', 'status_finishing', 'thumbnail']);
        });

        Schema::table('transaksi', function (Blueprint $table) {
            $table->dropColumn(['tipe_order', 'no_po']);
        });
    }
};
