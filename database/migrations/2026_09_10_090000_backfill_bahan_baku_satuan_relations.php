<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 6 dari 13 baris bahan_baku belum punya id_satuan_stok/id_satuan_hitung
 * terisi (kolom string satuan_stok legacy masih dipertahankan dan dipakai
 * di banyak tempat, TIDAK dihapus di migrasi ini - lihat audit UI session
 * ini). Semua 6 baris yang kosong punya satuan_stok = "roll"/"Roll".
 * Pola yang SUDAH ada di baris lain untuk material "roll" (TC Textile
 * Import, PET FILM DTF, CANVAS) selalu memetakan ke id_satuan_stok=17
 * (roll) dan id_satuan_hitung=21 (m2, sesuai formula BOM area (P*L)/10000).
 * Backfill ini murni mengisi kolom yang kosong, tidak mengubah data yang
 * sudah terisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('bahan_baku')
            ->whereNull('id_satuan_stok')
            ->whereRaw('LOWER(satuan_stok) = ?', ['roll'])
            ->update([
                'id_satuan_stok'   => 17, // roll
                'id_satuan_hitung' => 21, // m2
            ]);
    }

    public function down(): void
    {
        // Tidak reversible dengan aman (tidak tahu baris mana yang tadinya
        // NULL vs sudah terisi sebelum backfill ini) - sengaja no-op.
    }
};
