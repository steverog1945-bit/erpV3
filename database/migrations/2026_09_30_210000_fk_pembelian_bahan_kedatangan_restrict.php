<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * pembelian_bahan.id_kedatangan wajib terisi (PembelianBahanController memvalidasinya `required`, dan di database
 * berjalan kolomnya NOT NULL), sehingga aturan SET NULL dari migrasi pemulihan FK tidak pernah bisa terpasang
 * (MySQL menolak SET NULL pada kolom NOT NULL). Keputusan: pembelian selalu ditelusuri ke kedatangan, jadi
 * penghapusan kedatangan yang sudah punya pembelian harus ditolak (RESTRICT), bukan diam-diam memutus tautan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || !Schema::hasTable('pembelian_bahan') || !Schema::hasTable('kedatangan_bahan')) {
            return;
        }

        $sudahAda = DB::selectOne(
            "select count(*) c from information_schema.key_column_usage where table_schema = database() and table_name = 'pembelian_bahan' and column_name = 'id_kedatangan' and referenced_table_name is not null"
        )->c;
        if ($sudahAda > 0) {
            return;
        }

        $yatim = DB::selectOne('select count(*) c from pembelian_bahan p left join kedatangan_bahan k on k.id_kedatangan = p.id_kedatangan where p.id_kedatangan is not null and k.id_kedatangan is null')->c;
        if ($yatim > 0) {
            throw new RuntimeException("{$yatim} baris pembelian_bahan menunjuk kedatangan yang tidak ada; rapikan dulu sebelum memasang FK.");
        }

        DB::statement('ALTER TABLE pembelian_bahan ADD CONSTRAINT pembelian_bahan_id_kedatangan_foreign FOREIGN KEY (id_kedatangan) REFERENCES kedatangan_bahan (id_kedatangan) ON DELETE RESTRICT');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql' && Schema::hasTable('pembelian_bahan')) {
            try {
                DB::statement('ALTER TABLE pembelian_bahan DROP FOREIGN KEY pembelian_bahan_id_kedatangan_foreign');
            } catch (Throwable $e) {
                // tidak ada FK bernama itu: tidak ada yang perlu dicabut
            }
        }
    }
};
