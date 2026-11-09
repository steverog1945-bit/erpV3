<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Perbarui tier harga Retail (golongan 1) untuk tipe Jersey(1), Celana Pendek(2),
 * Celana Panjang(4) agar mengikuti spek owner 2026-11-09 (qty range + harga).
 * Hanya tier dengan kategori & bahan null (tier umum Retail) yang diperbarui;
 * tier spesifik (kategori/bahan terisi) dibiarkan.
 * Idempoten: hapus tier Retail umum lama untuk tipe-tipe tersebut, lalu insert
 * ulang sesuai spek.
 */
return new class extends Migration
{
    public function up(): void
    {
        $idRetail = (int) DB::table('golongan_konsumen')->where('nama_golongan', 'Retail')->value('id_golongan');
        if (!$idRetail) {
            return;
        }

        // Hapus tier Retail umum (kategori null & bahan null) untuk tipe 1,2,4
        DB::table('jersey_price_settings')
            ->where('id_golongan', $idRetail)
            ->whereIn('jersey_product_type_id', [1, 2, 4])
            ->whereNull('jersey_category_id')
            ->whereNull('jersey_material_id')
            ->delete();

        $rows = [];
        foreach ([[1, 20, 80000], [21, 50, 78000], [51, 100, 75000]] as [$a, $b, $h]) {
            $rows[] = ['tipe' => 1, 'kelompok' => 'dewasa', 'qty_min' => $a, 'qty_max' => $b, 'price' => $h];
        }
        foreach ([[1, 50, 30000], [51, 100, 28000]] as [$a, $b, $h]) {
            $rows[] = ['tipe' => 2, 'kelompok' => 'dewasa', 'qty_min' => $a, 'qty_max' => $b, 'price' => $h];
        }
        foreach ([[1, 50, 65000], [51, 100, 60000]] as [$a, $b, $h]) {
            $rows[] = ['tipe' => 4, 'kelompok' => 'dewasa', 'qty_min' => $a, 'qty_max' => $b, 'price' => $h];
        }
        foreach ([[1, 20, 60000], [21, 100, 58000]] as [$a, $b, $h]) {
            $rows[] = ['tipe' => 1, 'kelompok' => 'anak', 'qty_min' => $a, 'qty_max' => $b, 'price' => $h];
        }
        // Member tier untuk Retail tidak dibuat (Retail bukan Member).

        foreach ($rows as $r) {
            DB::table('jersey_price_settings')->insert([
                'jersey_product_type_id' => $r['tipe'],
                'jersey_category_id' => null,
                'jersey_material_id' => null,
                'qty_min' => $r['qty_min'],
                'qty_max' => $r['qty_max'],
                'price' => $r['price'],
                'is_active' => 1,
                'kelompok' => $r['kelompok'],
                'id_golongan' => $idRetail,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Tidak menghapus (tier bisa dipakai transaksi lama).
    }
};
