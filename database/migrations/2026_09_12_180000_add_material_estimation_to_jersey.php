<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Masukan lanjutan setelah demo kedua modul jersey:
 * (1) "Bahan" pada baris barang harus data riil dari master, bukan
 *     diketik manual - tabel jersey_materials baru + FK di jersey_order_lines.
 * (2) Estimasi pemakaian bahan per ukuran (body/lengan/rib/celana dst,
 *     dalam meter) supaya "Spesifikasi Order" bisa dihitung otomatis dari
 *     qty per ukuran, bukan diisi manual satu-satu - tabel jersey_components
 *     (daftar komponen/bagian) + jersey_size_component_usage (matriks
 *     ukuran x komponen -> meter). Estimasi murni, bukan pemotongan stok
 *     riil (tidak menyentuh bahan_baku/kartu_stok sama sekali - modul ini
 *     tetap terpisah dari BOM percetakan, lihat catatan di migrasi
 *     pembuatan modul jersey sebelumnya).
 * (3) thumbnail wajib diisi (acuan warna/bentuk produksi) - dikunci lewat
 *     kolom NOT NULL di jersey_order_lines mulai baris baru (baris lama
 *     tanpa thumbnail, kalau ada, tetap dibiarkan apa adanya).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('jersey_materials')) {
            Schema::create('jersey_materials', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('jersey_components')) {
            Schema::create('jersey_components', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            DB::table('jersey_components')->insert([
                ['name' => 'Body', 'sort_order' => 1, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Lengan', 'sort_order' => 2, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Rib', 'sort_order' => 3, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Celana', 'sort_order' => 4, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        if (!Schema::hasTable('jersey_size_component_usage')) {
            Schema::create('jersey_size_component_usage', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jersey_size_id')->constrained('jersey_sizes')->cascadeOnDelete();
                $table->foreignId('jersey_component_id')->constrained('jersey_components')->cascadeOnDelete();
                $table->decimal('usage_meter', 8, 3)->nullable();
                $table->timestamps();
                $table->unique(['jersey_size_id', 'jersey_component_id'], 'jersey_size_component_unique');
            });
        }

        if (!Schema::hasColumn('jersey_order_lines', 'jersey_material_id')) {
            Schema::table('jersey_order_lines', function (Blueprint $table) {
                $table->foreignId('jersey_material_id')->nullable()->after('jersey_category_id')
                    ->constrained('jersey_materials')->nullOnDelete();
            });
        }

        // thumbnail wajib diisi mulai sekarang - tabel dipastikan kosong
        // dulu (data uji sudah dibersihkan) sebelum dikunci NOT NULL,
        // supaya ALTER tidak gagal karena baris lama bernilai NULL.
        if (DB::table('jersey_order_lines')->whereNull('thumbnail')->doesntExist()) {
            DB::statement('ALTER TABLE jersey_order_lines MODIFY thumbnail VARCHAR(255) NOT NULL');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE jersey_order_lines MODIFY thumbnail VARCHAR(255) NULL');
        if (Schema::hasColumn('jersey_order_lines', 'jersey_material_id')) {
            Schema::table('jersey_order_lines', function (Blueprint $table) {
                $table->dropForeign(['jersey_material_id']);
                $table->dropColumn('jersey_material_id');
            });
        }
        Schema::dropIfExists('jersey_size_component_usage');
        Schema::dropIfExists('jersey_components');
        Schema::dropIfExists('jersey_materials');
    }
};
