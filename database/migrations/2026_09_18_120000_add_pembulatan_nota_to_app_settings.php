<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembulatan nominal di NOTA CETAK saja (murni tampilan) - Grand Total
 * transaksi & seluruh pencatatan akuntansi (transaksi.total_bayar,
 * pembayaran_riwayat, jurnal_umum) TIDAK disentuh sama sekali. Nilai
 * disimpan sebagai string kelipatan ('tidak','100','500','1000') supaya
 * gampang ditambah pilihan baru nanti tanpa migrasi ulang tipe kolom.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('app_settings', 'pembulatan_nota')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('pembulatan_nota', 10)->default('500')->after('nota_footer_text');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('app_settings', 'pembulatan_nota')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->dropColumn('pembulatan_nota');
            });
        }
    }
};
