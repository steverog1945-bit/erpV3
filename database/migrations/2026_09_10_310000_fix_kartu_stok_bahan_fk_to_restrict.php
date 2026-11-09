<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\Concerns\SafelyDropsForeignKeys;

/**
 * fk_kartu_stok_bahan sebelumnya ON DELETE CASCADE - satu-satunya dari 4 FK
 * yang menunjuk ke bahan_baku yang begitu (bom_detail, kedatangan_bahan_detail,
 * dan pembelian_bahan_detail semuanya RESTRICT). Akibatnya menghapus satu
 * bahan_baku diam-diam ikut menghapus SELURUH riwayat kartu_stok-nya tanpa
 * error apapun - ditemukan saat live-test fitur bulk delete Bahan Baku,
 * bertentangan langsung dengan prinsip "semua pergerakan stok harus
 * punya track record" yang jadi standar sesi ini. Diubah ke RESTRICT
 * supaya konsisten dengan 3 FK lainnya: bahan yang masih punya riwayat
 * kartu_stok sekarang tidak bisa dihapus (baik single maupun bulk delete),
 * sama seperti bahan yang masih punya riwayat kedatangan/pembelian.
 */
return new class extends Migration
{
    use SafelyDropsForeignKeys;

    public function up(): void
    {
        $this->dropForeignIfExists('kartu_stok', 'fk_kartu_stok_bahan');

        DB::statement('ALTER TABLE kartu_stok ADD CONSTRAINT fk_kartu_stok_bahan FOREIGN KEY (id_bahan) REFERENCES bahan_baku (id_bahan) ON DELETE RESTRICT');
    }

    public function down(): void
    {
        Schema::table('kartu_stok', function ($table) {
            $table->dropForeign('fk_kartu_stok_bahan');
        });

        DB::statement('ALTER TABLE kartu_stok ADD CONSTRAINT fk_kartu_stok_bahan FOREIGN KEY (id_bahan) REFERENCES bahan_baku (id_bahan) ON DELETE CASCADE');
    }
};
