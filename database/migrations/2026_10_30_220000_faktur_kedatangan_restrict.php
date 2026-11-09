<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Samakan aturan faktur -> kedatangan dengan database produksi (keputusan pemilik): pembelian_bahan.id_kedatangan
 * WAJIB terisi dan FK RESTRICT. Rantai migrasi lama menghasilkan kolom nullable + SET NULL, sehingga database yang
 * dibangun dari migrasi (termasuk database tes) lebih longgar dari produksi: menghapus kedatangan yang sudah
 * difakturkan "berhasil" di tes tetapi galat di produksi. Di produksi migrasi ini tidak mengubah apa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $fk = DB::selectOne("SELECT constraint_name AS nama, delete_rule AS aturan FROM information_schema.referential_constraints
            WHERE constraint_schema = DATABASE() AND table_name = 'pembelian_bahan' AND referenced_table_name = 'kedatangan_bahan'");
        $nullable = DB::selectOne("SELECT is_nullable AS n FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'pembelian_bahan' AND column_name = 'id_kedatangan'");

        if ($fk && $fk->aturan === 'RESTRICT' && $nullable && $nullable->n === 'NO') {
            return;
        }
        if (DB::table('pembelian_bahan')->whereNull('id_kedatangan')->exists()) {
            throw new RuntimeException('Ada faktur pembelian tanpa kedatangan; lengkapi dulu sebelum migrasi ini.');
        }

        if ($fk) {
            DB::statement("ALTER TABLE pembelian_bahan DROP FOREIGN KEY `{$fk->nama}`");
        }
        DB::statement('ALTER TABLE pembelian_bahan MODIFY id_kedatangan BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE pembelian_bahan ADD CONSTRAINT pembelian_bahan_id_kedatangan_foreign FOREIGN KEY (id_kedatangan) REFERENCES kedatangan_bahan (id_kedatangan) ON UPDATE RESTRICT ON DELETE RESTRICT');
    }

    public function down(): void
    {
        // Tidak dikembalikan: aturan longgar adalah keadaan yang salah.
    }
};
