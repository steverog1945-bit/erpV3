<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drift tabel supplier: create_complex_system_tables (2026_02_25_111057) membuat
 * kolom `telepon` NOT NULL, sedangkan add_missing_erp_tables (2026_02_25_112610)
 * yang punya `kontak` dilewati oleh guard hasTable. Akibatnya migrate dari nol
 * menghasilkan skema berbeda dari DB dev/production (memakai `kontak`), padahal
 * SupplierController & model Supplier memakai `kontak`.
 *
 * Idempotent: DB yang sudah punya `kontak` tanpa `telepon` tidak diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('supplier')) {
            return;
        }

        $adaKontak  = Schema::hasColumn('supplier', 'kontak');
        $adaTelepon = Schema::hasColumn('supplier', 'telepon');

        if (!$adaKontak && $adaTelepon) {
            // Ganti nama + pertahankan data; sama dengan skema dev.
            DB::statement('ALTER TABLE supplier CHANGE telepon kontak VARCHAR(255) NOT NULL');
        } elseif (!$adaKontak) {
            DB::statement('ALTER TABLE supplier ADD kontak VARCHAR(255) NOT NULL DEFAULT \'\' AFTER alamat');
            DB::statement('ALTER TABLE supplier ALTER kontak DROP DEFAULT');
        } elseif ($adaTelepon) {
            // Keduanya ada: `telepon` tidak dipakai kode, jangan sampai memblokir insert.
            DB::statement('ALTER TABLE supplier MODIFY telepon VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        // Tidak dibalik: skema lama (telepon) adalah drift yang tidak diinginkan.
    }
};
