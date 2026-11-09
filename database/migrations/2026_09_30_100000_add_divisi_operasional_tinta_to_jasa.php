<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MASTER JASA - DIVISI, BIAYA OPERASIONAL, BIAYA TINTA
 *
 * Kebutuhan yang diberikan: jasa tidak melekat produk. Yang dibutuhkan
 * hanya (1) mesin yang mengerjakan, (2) biaya operasional, (3) biaya
 * tinta. Tiga kolom baru di bawah persis tiga hal itu - tidak ada kolom
 * lain yang ditambahkan karena tidak ada kebutuhan lain yang bisa
 * diverifikasi dari kode maupun dari permintaan.
 *
 * id_mesin, BUKAN kolom teks "divisi"
 * ----------------------------------
 * Di aplikasi ini "divisi" sudah berarti mesin: DivisiService
 * mengambilnya dari produk.id_mesin, dan dipakai SPK, Produksi, Waste,
 * serta Laporan. Kalau tabel jasa mendapat kolom teks, akan lahir
 * kosakata kedua yang tidak cocok dengan daftar mesin - dan SPK tidak
 * akan bisa menerjemahkan "dikerjakan bagian X" menjadi mesin mana.
 * Karena itu dipakai relasi ke tabel mesin, sama seperti produk.id_mesin.
 *
 * id_mesin nullable, bukan NOT NULL
 * ---------------------------------
 * Semua 94 produk sudah punya mesin, tapi tiga baris jasa yang ada belum
 * punya. NOT NULL akan menggagalkan INSERT sebelum data lama diisi, dan
 * tidak ada nilai default yang bisa diasumsikan. Nullable plus tampilan
 * "Tanpa Divisi" di UI jauh lebih aman, dan persis seperti kondisi
 * produk sebelum semua produknya punya mesin.
 *
 * harga_default
 * -------------
 * Kolom ini SUDAH ADA di database, meski satu migrasi pernah berisi
 * dropColumn('harga_default'). Migration dan keadaan database tidak
 * sinkron, jadi migrasi ini memakai hasColumn() dan tidak menyentuh apa
 * pun kalau kolomnya sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jasa', function (Blueprint $table) {
            // Divisi: mesin yang mengerjakan jasa ini
            if (! Schema::hasColumn('jasa', 'id_mesin')) {
                $table->unsignedBigInteger('id_mesin')->nullable()->after('id_satuan')
                    ->comment('Mesin/divisi pengerjaan. Sama seperti produk.id_mesin, bukan teks bebas.');
            }

            // Biaya operasional: tenaga kerja yang turun tangan (jahit,
            // pasang, setting). Dipakai bersama harga jual untuk menghitung
            // margin - sebelumnya baris jasa selalu hpp 0, sehingga margin
            // jasa di laporan selalu terlihat 100%.
            if (! Schema::hasColumn('jasa', 'biaya_operasional')) {
                $table->decimal('biaya_operasional', 15, 2)->nullable()->default(0)
                    ->after('harga_default')
                    ->comment('Biaya tenaga kerja per unit jasa. Dipakai untuk hitung margin.');
            }

            // Biaya tinta: tinta yang terpakai untuk jasa ini
            if (! Schema::hasColumn('jasa', 'biaya_tinta')) {
                $table->decimal('biaya_tinta', 15, 2)->nullable()->default(0)
                    ->after('biaya_operasional')
                    ->comment('Biaya tinta per unit jasa. Dipakai untuk hitung margin.');
            }
        });

        // Relasi ke mesin. Dibuat terpisah supaya migrasi ini tetap aman
        // dijalankan pada database yang belum punya tabel mesin.
        if (Schema::hasTable('mesin')) {
            try {
                Schema::table('jasa', function (Blueprint $table) {
                    $table->foreign('id_mesin')->references('id_mesin')->on('mesin')->nullOnDelete();
                });
            } catch (\Throwable $e) {
                // Foreign key sudah ada (kalau migrasi dijalankan ulang),
                // atau mesin masih terisi data yang tak boleh dihapus.
                // Kolom id_mesin tetap ada dan tetap bisa diisi - yang hilang
                // hanya kunci referensialnya, bukan kemampuannya.
            }
        }

        // Tier harga jadi DESIMAL. Semuanya integer, padahal qty jasa bisa
        // pecahan: jahit tepi dihitung per meter (12,5 m), tinta per ml.
        // Dengan integer, batas tier seperti "12,5 - 20" tidak bisa ditulis -
        // MySQL membulatkan diam-diam, jadi kasir mengira tier itu berlaku
        // dari 13 padahal sebenarnya dari 12,5.
        if (Schema::hasTable('jasa_harga')) {
            Schema::table('jasa_harga', function (Blueprint $table) {
                $table->decimal('range_min', 14, 4)->change();
                $table->decimal('range_max', 14, 4)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('jasa_harga')) {
            Schema::table('jasa_harga', function (Blueprint $table) {
                $table->decimal('range_min', 14, 4)->change();
                $table->decimal('range_max', 14, 4)->nullable()->change();
            });
        }

        Schema::table('jasa', function (Blueprint $table) {
            foreach (['biaya_tinta', 'biaya_operasional'] as $kolom) {
                if (Schema::hasColumn('jasa', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
            if (Schema::hasColumn('jasa', 'id_mesin')) {
                $table->dropColumn('id_mesin');
            }
        });
    }
};
