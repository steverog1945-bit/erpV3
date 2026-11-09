<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menghubungkan pembayaran "Transfer" ke rekening bank perusahaan yang
     * dituju - sebelumnya bank_perusahaan hanya CRUD berdiri sendiri, tidak
     * pernah dipakai transaksi manapun (0 referensi kode di luar halaman
     * /bank itu sendiri). Nullable karena pembayaran Tunai tidak relevan
     * dengan kolom ini, dan data pembayaran_riwayat existing (sebelum
     * migrasi ini) memang tidak punya info tersebut.
     */
    public function up(): void
    {
        Schema::table('pembayaran_riwayat', function (Blueprint $table) {
            $table->foreignId('id_bank')->nullable()->after('metode')
                ->constrained('bank_perusahaan', 'id_bank')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pembayaran_riwayat', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_bank');
        });
    }
};
