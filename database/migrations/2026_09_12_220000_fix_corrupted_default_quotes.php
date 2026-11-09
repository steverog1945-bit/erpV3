<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Perbaikan bug data integrity: kolom produk.status, bahan_baku.status, dan
 * satuan.tipe_satuan punya DEFAULT yang korup di database - nilainya secara
 * harfiah tersimpan DENGAN tanda kutip di dalamnya (mis. string "'aktif'"
 * bukan "aktif"), terlihat dari `SHOW CREATE TABLE produk` yang menunjukkan
 * `DEFAULT '''aktif'''` (bukan `DEFAULT 'aktif'` yang seharusnya dihasilkan
 * migrasi aslinya - migrasi sumber sudah benar pakai ->default('aktif'),
 * jadi korupsi ini terjadi lewat ALTER TABLE manual di luar migrasi, kemungkinan
 * besar saat pemulihan darurat database sebelumnya, bukan dari kode aplikasi).
 *
 * Dampak nyata: produk baru yang dibuat lewat form (status ikut default,
 * tidak diisi eksplisit oleh ProdukController::store()) tersimpan dengan
 * status = "'aktif'" (7 karakter, ada tanda kutip) - lolos insert (makanya
 * alert "berhasil disimpan" tetap muncul) tapi filter daftar produk
 * (WHERE status = 'aktif', tanpa tanda kutip) tidak pernah cocok, jadi produk
 * itu "hilang" dari tabel meski sudah tersimpan di database. bahan_baku &
 * satuan punya cacat DEFAULT yang sama tapi belum ada baris yang benar-benar
 * kena (semua baris yang ada sejauh ini selalu diisi eksplisit oleh
 * controller masing-masing) - tetap diperbaiki sekarang supaya tidak jadi
 * bug laten yang muncul kapan saja nanti.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Perbaiki definisi DEFAULT di skema - guard: hanya ALTER kalau
        // masih dalam kondisi korup (idempotent, aman dijalankan ulang).
        $fixes = [
            ['produk', 'status', "MODIFY status VARCHAR(20) NOT NULL DEFAULT 'aktif'"],
            ['bahan_baku', 'status', "MODIFY status VARCHAR(20) DEFAULT 'aktif'"],
            ['satuan', 'tipe_satuan', "MODIFY tipe_satuan VARCHAR(255) NOT NULL DEFAULT 'Barang'"],
        ];

        foreach ($fixes as [$table, $column, $alterClause]) {
            $default = DB::selectOne(
                "SELECT COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                [$table, $column]
            )?->COLUMN_DEFAULT;

            // Nilai korup selalu diawali+diakhiri tanda kutip GANDA (3 kutip
            // beruntun di DDL asli) - default yang sudah benar cuma 1 lapis.
            if ($default !== null && str_starts_with($default, "'")) {
                DB::statement("ALTER TABLE {$table} {$alterClause}");
            }
        }

        // 2. Backfill baris yang sudah kena dampak - status/tipe tersimpan
        // literal dengan tanda kutip di dalamnya, dibersihkan supaya cocok
        // lagi dengan filter WHERE status = 'aktif' yang dipakai di mana pun.
        DB::table('produk')->where('status', "'aktif'")->update(['status' => 'aktif']);
        DB::table('bahan_baku')->where('status', "'aktif'")->update(['status' => 'aktif']);
        DB::table('satuan')->where('tipe_satuan', "'Barang'")->update(['tipe_satuan' => 'Barang']);
    }

    public function down(): void
    {
        // Sengaja tidak dikembalikan ke kondisi korup - down() di sini cuma
        // no-op supaya rollback migrasi lain tidak ikut menghidupkan lagi bug
        // data yang sudah terbukti merugikan.
    }
};
