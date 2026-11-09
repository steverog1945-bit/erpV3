<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\Concerns\SafelyDropsForeignKeys;

/**
 * konsumen_deposit_id_konsumen_foreign ternyata ON DELETE CASCADE - persis
 * kelas bug yang sama dengan fk_kartu_stok_bahan (migrasi 310000): menghapus
 * satu konsumen diam-diam ikut menghapus SELURUH riwayat deposit (Setoran
 * maupun Pemakaian) miliknya tanpa error apapun. KonsumenController::destroy()
 * sebelumnya juga tidak ada try/catch - baik delete konsumen ber-transaksi
 * (FK transaksi sudah RESTRICT, jadi crash exception mentah) maupun delete
 * konsumen ber-deposit (CASCADE, jadi "berhasil" tapi diam-diam menghapus
 * riwayat uang) sama-sama tidak ditangani dengan baik.
 *
 * Uang deposit adalah data finansial - kehilangan riwayatnya sama seriusnya
 * dengan kehilangan riwayat kartu_stok. Diubah ke RESTRICT: konsumen yang
 * masih punya riwayat deposit (pernah setor atau masih ada sisa saldo)
 * tidak bisa dihapus, konsisten dengan transaksi_konsumen yang sudah RESTRICT.
 */
return new class extends Migration
{
    use SafelyDropsForeignKeys;

    public function up(): void
    {
        $this->dropForeignIfExists('konsumen_deposit', 'konsumen_deposit_id_konsumen_foreign');

        DB::statement('ALTER TABLE konsumen_deposit ADD CONSTRAINT konsumen_deposit_id_konsumen_foreign FOREIGN KEY (id_konsumen) REFERENCES konsumen (id_konsumen) ON DELETE RESTRICT');
    }

    public function down(): void
    {
        Schema::table('konsumen_deposit', function ($table) {
            $table->dropForeign('konsumen_deposit_id_konsumen_foreign');
        });

        DB::statement('ALTER TABLE konsumen_deposit ADD CONSTRAINT konsumen_deposit_id_konsumen_foreign FOREIGN KEY (id_konsumen) REFERENCES konsumen (id_konsumen) ON DELETE CASCADE');
    }
};
