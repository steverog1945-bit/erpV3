<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ditemukan lewat pengujian langsung: bahan_baku.stok dan kartu_stok.qty/
 * stok_sebelum/stok_sesudah cuma decimal(15,2) - hanya 2 angka desimal.
 * Setelah PenjualanController::store() diperbaiki untuk mengonversi
 * qty_pakai dari satuan HITUNG ke satuan STOK lewat nilai_konversi (lihat
 * commit sebelumnya), bahan dengan nilai_konversi besar (mis. AC 210gr:
 * 1 rim = 500 lembar) menghasilkan pengurangan stok sepert 1/500 = 0.002
 * rim per lembar - yang DIBULATKAN JADI 0.00 oleh presisi 2 desimal,
 * sehingga stok kelihatan tidak berkurang sama sekali untuk order kecil.
 * nilai_konversi & stok_minimal sudah decimal(15,3); stok & kartu_stok
 * dilebarkan ke decimal(15,4) supaya lebih longgar untuk nilai_konversi
 * yang lebih besar lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE bahan_baku MODIFY stok DECIMAL(15,4) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE kartu_stok MODIFY qty DECIMAL(15,4) NOT NULL');
        DB::statement('ALTER TABLE kartu_stok MODIFY stok_sebelum DECIMAL(15,4) NOT NULL');
        DB::statement('ALTER TABLE kartu_stok MODIFY stok_sesudah DECIMAL(15,4) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE bahan_baku MODIFY stok DECIMAL(15,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE kartu_stok MODIFY qty DECIMAL(15,2) NOT NULL');
        DB::statement('ALTER TABLE kartu_stok MODIFY stok_sebelum DECIMAL(15,2) NOT NULL');
        DB::statement('ALTER TABLE kartu_stok MODIFY stok_sesudah DECIMAL(15,2) NOT NULL');
    }
};
