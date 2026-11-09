<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menghubungkan modul Finishing (tabel finishing, sudah ada dan sudah
     * dipakai di master.finishing) ke alur order - sebelumnya order sama
     * sekali tidak bisa mencatat biaya/tipe finishing yang dipilih.
     * Kedua kolom nullable dan tidak mengubah/menghapus data existing.
     */
    public function up(): void
    {
        Schema::table('transaksi_detail', function (Blueprint $table) {
            $table->foreignId('id_finishing')->nullable()->after('id_produk')
                ->constrained('finishing', 'id_finishing')->nullOnDelete();
            $table->decimal('biaya_finishing', 15, 2)->nullable()->default(0)->after('id_finishing');
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_detail', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_finishing');
            $table->dropColumn('biaya_finishing');
        });
    }
};
