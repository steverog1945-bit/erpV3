<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\Concerns\SafelyDropsForeignKeys;

/**
 * kedatangan_bahan.id_gudang ternyata TIDAK PERNAH punya foreign key
 * constraint sama sekali sejak awal - beda dengan id_supplier di tabel
 * yang sama yang sudah benar ber-FK. GudangController::destroy() memang
 * sudah mengecek pemakaian di kedatangan_bahan sebelum menghapus, tapi
 * itu murni pengecekan level APLIKASI - integritas datanya sendiri
 * tidak dijamin di level database sama sekali (mis. kalau ada jalur
 * hapus lain di masa depan yang lupa pakai pengecekan yang sama,
 * lubangnya tetap terbuka; constraint DB adalah jaring pengaman
 * terakhir yang tidak bergantung pada disiplin programmer mengingat
 * semua tempat yang perlu dicek).
 *
 * Root cause kenapa FK-nya tidak pernah bisa dibuat: gudang.id_gudang
 * ternyata bigint SIGNED (beda dari konvensi id auto-increment di
 * seluruh tabel lain di skema ini yang UNSIGNED, termasuk
 * kedatangan_bahan.id_gudang sendiri) - MySQL menolak bikin FK kalau
 * tipe kolom persis tidak sama (termasuk signed/unsigned). Diperbaiki
 * dulu ke UNSIGNED (aman - tidak ada gudang dengan id negatif) baru
 * FK-nya bisa ditambahkan.
 *
 * Tidak ada data yatim (dicek dulu sebelum migrasi ini ditulis) -
 * seluruh kedatangan_bahan.id_gudang yang ada sekarang valid menunjuk
 * ke gudang yang benar-benar ada, aman ditambah RESTRICT.
 */
return new class extends Migration
{
    use SafelyDropsForeignKeys;

    public function up(): void
    {
        DB::statement('ALTER TABLE gudang MODIFY id_gudang BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

        // dropIfExists dulu - migrasi 2026_09_10_210000 (lebih awal) pada
        // instalasi fresh sudah lebih dulu berhasil membuat FK bernama
        // sama ini dengan ON DELETE SET NULL; di sini SENGAJA diganti ke
        // RESTRICT (semantik yang lebih benar, lihat catatan panjang di
        // atas) - tanpa drop dulu, ADD di bawah gagal "duplicate key"
        // karena nama constraint sudah dipakai.
        $this->dropForeignIfExists('kedatangan_bahan', 'kedatangan_bahan_id_gudang_foreign');

        Schema::table('kedatangan_bahan', function ($table) {
            $table->foreign('id_gudang', 'kedatangan_bahan_id_gudang_foreign')
                ->references('id_gudang')->on('gudang')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('kedatangan_bahan', function ($table) {
            $table->dropForeign('kedatangan_bahan_id_gudang_foreign');
        });

        DB::statement('ALTER TABLE gudang MODIFY id_gudang BIGINT NOT NULL AUTO_INCREMENT');
    }
};
