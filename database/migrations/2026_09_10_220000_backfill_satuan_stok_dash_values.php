<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BahanBakuController::store() sebelumnya selalu menyimpan satuan_stok = '-'
 * untuk material yang dibuat lewat form (field itu tidak pernah dikirim,
 * hanya id_satuan_stok/FK yang dikirim) - lihat perbaikan di
 * BahanBakuController. Migrasi ini membackfill baris lama yang terkena
 * bug tersebut, mengambil nama satuan dari relasi id_satuan_stok yang
 * sudah benar.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE bahan_baku b
            JOIN satuan s ON b.id_satuan_stok = s.id_satuan
            SET b.satuan_stok = s.nama_satuan
            WHERE b.satuan_stok = '-'
        ");
    }

    public function down(): void
    {
        // Tidak reversible dengan aman (tidak tahu baris mana yang tadinya
        // '-' sebelum backfill ini) - sengaja no-op.
    }
};
