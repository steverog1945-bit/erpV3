<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom yang dipakai kode dan ada di DB dev, tetapi tidak pernah dibuat oleh
 * rangkaian migrasi (migrate dari nol) karena guard hasTable pada
 * add_missing_erp_tables atau karena ditambahkan manual di DB dev:
 *
 *  - harga_promo.status_promo         (HargaPromoController, HargaService)
 *  - kategori.kode_kategori + timestamps (KategoriController)
 *  - golongan_konsumen.keterangan     (GolonganController)
 *  - konsumen.nama_konsumen_manual    (KonsumenController)
 *  - transaksi.metode_pembayaran      (KasirController, LaporanController)
 *  - bom_detail & kedatangan_bahan timestamps
 *
 * Idempotent: hanya menambah kolom yang belum ada, DB dev tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('harga_promo') && !Schema::hasColumn('harga_promo', 'status_promo')) {
            Schema::table('harga_promo', function (Blueprint $t) {
                $t->enum('status_promo', ['aktif', 'non-aktif'])->default('aktif');
            });
            // Pertahankan status lama (is_active) untuk data yang sudah ada.
            if (Schema::hasColumn('harga_promo', 'is_active')) {
                DB::table('harga_promo')->where('is_active', 0)->update(['status_promo' => 'non-aktif']);
                Schema::table('harga_promo', fn (Blueprint $t) => $t->dropColumn('is_active'));
            }
        }

        if (Schema::hasTable('kategori')) {
            Schema::table('kategori', function (Blueprint $t) {
                if (!Schema::hasColumn('kategori', 'kode_kategori')) {
                    $t->string('kode_kategori', 5)->nullable();
                }
                if (!Schema::hasColumn('kategori', 'created_at')) {
                    $t->timestamps();
                }
            });
        }

        // Dev: NOT NULL tanpa default; dibuat nullable supaya update dengan
        // keterangan kosong (GolonganController@update) tidak gagal.
        if (Schema::hasTable('golongan_konsumen') && !Schema::hasColumn('golongan_konsumen', 'keterangan')) {
            Schema::table('golongan_konsumen', function (Blueprint $t) {
                $t->string('keterangan')->nullable();
            });
        }

        if (Schema::hasTable('konsumen') && !Schema::hasColumn('konsumen', 'nama_konsumen_manual')) {
            Schema::table('konsumen', function (Blueprint $t) {
                $t->string('nama_konsumen_manual', 50)->nullable()->after('nama_konsumen');
            });
        }

        if (Schema::hasTable('transaksi') && !Schema::hasColumn('transaksi', 'metode_pembayaran')) {
            Schema::table('transaksi', function (Blueprint $t) {
                $t->string('metode_pembayaran', 50)->nullable();
            });
        }

        foreach (['bom_detail', 'kedatangan_bahan'] as $tabel) {
            if (!Schema::hasTable($tabel)) {
                continue;
            }
            Schema::table($tabel, function (Blueprint $t) use ($tabel) {
                if (!Schema::hasColumn($tabel, 'created_at')) {
                    $t->timestamp('created_at')->nullable();
                }
                if (!Schema::hasColumn($tabel, 'updated_at')) {
                    $t->timestamp('updated_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Tidak dibalik: kolom-kolom ini dipakai kode.
    }
};
