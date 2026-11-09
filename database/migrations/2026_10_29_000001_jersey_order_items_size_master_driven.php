<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit Order Jersey - akar masalah "Data truncated for column 'size'":
 * kolom jersey_order_items.size masih ENUM hard-code
 * ('S','M','L','XL','2XL','3XL','4XL','5XL') padahal ukuran sudah
 * dikelola sebagai master data (tabel jersey_sizes). Validasi controller
 * sudah membaca master, tapi INSERT ukuran baru dari master ditolak MySQL
 * karena nilainya tidak ada di enum.
 *
 * Pola yang dipakai MENGIKUTI preseden modul ini sendiri (migrasi
 * 2026_09_13_040000 add_sleeve_collar_master_data_to_jersey): kolom teks
 * jadi snapshot nama (seperti jersey_order_lines.bahan dan
 * jersey_order_items.sleeve), dan FK ke master ditambah DI SAMPINGNYA
 * sebagai rujukan relasional yang sebenarnya. Data lama TIDAK berubah
 * sama sekali: semua nilai enum yang ada (S..5XL) adalah nama ukuran
 * master yang valid, konversi enum -> varchar lossless, dan FK di-backfill
 * dari nama.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. ENUM hard-code -> VARCHAR (snapshot nama ukuran master).
        //    Lossless: nilai yang ada semuanya nama master yang valid.
        DB::statement("ALTER TABLE jersey_order_items MODIFY COLUMN size VARCHAR(50) NOT NULL");

        // 2. FK ke master ukuran - rujukan relasional yang sebenarnya.
        if (!Schema::hasColumn('jersey_order_items', 'jersey_size_id')) {
            Schema::table('jersey_order_items', function (Blueprint $table) {
                $table->foreignId('jersey_size_id')->nullable()->after('size')
                    ->constrained('jersey_sizes')->nullOnDelete();
            });
        }

        // 3. Backfill FK dari nama (penyambungan data, nilai size asli
        //    tidak disentuh). Nama yang tidak cocok dengan master manapun
        //    dibiarkan jersey_size_id NULL - snapshot teksnya tetap tampil.
        DB::statement(
            'UPDATE jersey_order_items i
             JOIN jersey_sizes s ON s.name = i.size
             SET i.jersey_size_id = s.id
             WHERE i.jersey_size_id IS NULL'
        );
    }

    public function down(): void
    {
        // Pulihkan enum HANYA kalau tidak ada nilai di luar enum lama -
        // kalau ada (artinya master sudah dipakai menambah ukuran baru),
        // rollback di tolak daripada memotong data historis.
        $diLuarEnum = DB::table('jersey_order_items')
            ->whereNotIn('size', ['S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'])
            ->count();

        if ($diLuarEnum > 0) {
            throw new RuntimeException(
                "Tidak bisa rollback: {$diLuarEnum} baris jersey_order_items memakai ukuran di luar enum lama (dari Master Data). Rollback akan memotong data historis."
            );
        }

        Schema::table('jersey_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('jersey_order_items', 'jersey_size_id')) {
                $table->dropConstrainedForeignId('jersey_size_id');
            }
        });

        DB::statement("ALTER TABLE jersey_order_items MODIFY COLUMN size ENUM('S','M','L','XL','2XL','3XL','4XL','5XL') NOT NULL");
    }
};
