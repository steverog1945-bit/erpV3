<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Root-cause dari HPP/stok yang tidak akurat untuk bahan roll (m2): bukan
 * cuma rumus (sudah diperbaiki di migrasi 230000), tapi nilai_konversi-nya
 * sendiri buram - angka mentah tanpa jejak bagaimana cara menghitungnya,
 * gampang ditinggal di default 1.000 (5 dari 8 bahan BOM saat ini begitu).
 *
 * lebar_roll & panjang_roll (meter) membuat nilai_konversi bisa dihitung
 * dari dimensi fisik roll yang sesungguhnya (lebar x panjang = total m2
 * per roll) - sama persis prinsip "hitungan panjang x lebar" yang sudah
 * dipakai untuk ukuran cetak (P*L)/10000 di form order, sekarang dipakai
 * juga untuk sisi bahan bakunya. Nullable & tidak dipakai sama sekali
 * untuk bahan non-roll (rim/pcs/kg) - nilai_konversi bahan itu tetap
 * diisi manual seperti sebelumnya (mis. 1 rim = 500 lembar, bukan hasil
 * perkalian dimensi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bahan_baku', function (Blueprint $table) {
            $table->decimal('lebar_roll', 10, 3)->nullable()->after('nilai_konversi');
            $table->decimal('panjang_roll', 10, 3)->nullable()->after('lebar_roll');
        });
    }

    public function down(): void
    {
        Schema::table('bahan_baku', function (Blueprint $table) {
            $table->dropColumn(['lebar_roll', 'panjang_roll']);
        });
    }
};
