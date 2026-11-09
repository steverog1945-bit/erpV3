<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hardening P0-1 (audit hardening): snapshot pemakaian bahan baku PER BARIS
 * order, dicatat persis saat qty_pakai dipotong dari stok (PenjualanController::
 * store(), sebaris dengan insert kartu_stok tipe OUT). Sebelum tabel ini ada,
 * StokReversalService menghitung ULANG qty yang harus dikembalikan dari
 * BomCalculationService::komponen() - yang membaca bom_detail/pemakaian_tinta
 * LIVE (tabel yang sama yang di-delete-lalu-insert-ulang tiap kali BOM produk
 * diedit, lihat ProdukController::update). Kalau BOM produk berubah di antara
 * order dibuat dan item itu dibatalkan/direversal, reversal akan memakai
 * formula BARU (bahan berbeda dan/atau qty berbeda) - bukan yang benar-benar
 * dipotong dulu - sehingga stok bahan_baku perlahan menyimpang dari fisik
 * tanpa ada tanda kesalahan apa pun.
 *
 * qty_pakai di sini SUDAH dalam satuan STOK (sudah dibagi nilai_konversi) -
 * identik dengan kartu_stok.qty pada baris OUT yang dicatat di saat yang
 * sama - supaya reversal tinggal menjumlahkan balik ke bahan_baku.stok tanpa
 * perlu menghitung ulang apa pun dari BOM.
 *
 * Tabel aditif murni (tidak mengubah kolom tabel manapun yang sudah ada).
 * Transaksi lama (sebelum migrasi ini) tidak akan punya baris di sini -
 * StokReversalService jatuh ke rumus lama (recompute dari BOM live) sebagai
 * fallback KHUSUS untuk baris lama itu, supaya alur reversal tidak pernah
 * berhenti bekerja untuk histori sebelum fitur ini ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi_detail_bahan_pakai', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('id_detail')->index();
            $t->unsignedBigInteger('id_bahan');
            $t->decimal('qty_pakai', 15, 4);
            $t->boolean('is_tinta')->default(false);
            $t->timestamps();

            $t->index(['id_detail', 'id_bahan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_detail_bahan_pakai');
    }
};
