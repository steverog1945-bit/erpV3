<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fondasi untuk filter SPK per-divisi berbasis siapa yang login: sebelum
 * ini tidak ada satu pun tempat di skema yang menyimpan "karyawan X
 * bertanggung jawab atas mesin/divisi Y" - role_permissions cuma
 * mengontrol akses per HALAMAN (lihat CheckMenuAccess), bukan filter
 * data di dalam halaman. karyawan.bagian juga terlalu kasar (cuma 5
 * kategori: Admin/Desain/Produksi/Kasir/Gudang), tidak menunjuk ke
 * mesin spesifik.
 *
 * Many-to-many (bukan kolom id_mesin tunggal di karyawan) karena di
 * dunia nyata operator bisa saja menangani lebih dari satu mesin,
 * terutama di tim kecil - downgrade ke "cuma satu" tinggal assign satu
 * saja, tapi upgrade dari kolom tunggal ke banyak nanti butuh migrasi
 * lagi. Cascade delete di kedua sisi karena ini murni data penugasan
 * (siapa pegang apa SAAT INI), bukan ledger/riwayat - tidak ada
 * kebutuhan audit trail seperti kartu_stok.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('karyawan_mesin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_karyawan')->constrained('karyawan', 'id_karyawan')->onDelete('cascade');
            $table->foreignId('id_mesin')->constrained('mesin', 'id_mesin')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['id_karyawan', 'id_mesin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karyawan_mesin');
    }
};
