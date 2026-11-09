<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            // Format cetak nota GLOBAL (bukan lagi pilihan per-transaksi lewat
            // dropdown "Nota Thermal (58mm)" / "Invoice (A4/A5)" - kasir tidak
            // perlu pilih format tiap kali cetak, satu format standar toko
            // yang diatur sekali oleh admin). 'continuous' = kertas
            // sambung/dot-matrix, biasanya lebih lebar dari A4/A5.
            if (!Schema::hasColumn('app_settings', 'format_nota')) {
                $table->string('format_nota', 20)->default('a4')->after('libur_kas_minggu');
            }
            // Prefix nomor nota (dulu hardcode "INV-" di PenjualanController).
            if (!Schema::hasColumn('app_settings', 'nomor_nota_prefix')) {
                $table->string('nomor_nota_prefix', 20)->default('INV')->after('format_nota');
            }
            // Footer nota (rekening, syarat & ketentuan, dst) - dulu hardcode
            // "Terima kasih atas kepercayaan Anda" tanpa bisa diubah. Teks
            // polos (bukan rich text/HTML) sengaja dipilih - tidak perlu
            // sanitasi HTML tambahan, tetap fleksibel untuk beberapa baris
            // info (satu baris = satu instruksi/keterangan).
            if (!Schema::hasColumn('app_settings', 'nota_footer_text')) {
                $table->text('nota_footer_text')->nullable()->after('nomor_nota_prefix');
            }
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            foreach (['format_nota', 'nomor_nota_prefix', 'nota_footer_text'] as $col) {
                if (Schema::hasColumn('app_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
