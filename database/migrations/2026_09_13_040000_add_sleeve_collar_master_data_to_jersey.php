<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estimasi bahan sebelumnya cuma berdasar Ukuran x Komponen (Body/Rib/
 * Celana, dst) - Lengan (Pendek/Panjang) & Kerah tidak pernah benar-benar
 * memengaruhi estimasi sama sekali (sleeve cuma enum tetap, collar cuma
 * teks bebas, keduanya murni kosmetik di daftar pemain). Supaya estimasi
 * lebih mendekati riil, Tipe Lengan & Tipe Kerah sekarang jadi master data
 * sendiri (seperti Bahan) dengan matriks estimasi ukuran x tipe masing-
 * masing, dipilih lewat dropdown (bukan manual/hardcode).
 *
 * jersey_order_lines dapat kolom jersey_sleeve_type_id/jersey_collar_type_id
 * sebagai pilihan DEFAULT di level barang - dipakai penuh untuk mode
 * "Qty per Ukuran" (tidak ada data per pemain), dan sebagai fallback untuk
 * bagian qty-per-ukuran pada mode "Campuran" (bagian daftar pemainnya tetap
 * pakai pilihan Lengan/Kerah masing-masing pemain).
 *
 * jersey_order_items.sleeve (enum) & collar (teks bebas) DIPERTAHANKAN apa
 * adanya sebagai snapshot nama (pola sama seperti jersey_order_lines.bahan
 * mendampingi jersey_material_id) - kolom FK baru ditambah di sampingnya,
 * bukan menggantikan, supaya data riwayat lama tidak pernah rusak/hilang.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('jersey_sleeve_types')) {
            Schema::create('jersey_sleeve_types', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('jersey_collar_types')) {
            Schema::create('jersey_collar_types', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('jersey_size_sleeve_usage')) {
            Schema::create('jersey_size_sleeve_usage', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jersey_size_id')->constrained('jersey_sizes')->cascadeOnDelete();
                $table->foreignId('jersey_sleeve_type_id')->constrained('jersey_sleeve_types')->cascadeOnDelete();
                $table->decimal('usage_meter', 8, 3)->nullable();
                $table->decimal('usage_weight_gram', 10, 2)->nullable();
                $table->timestamps();
                $table->unique(['jersey_size_id', 'jersey_sleeve_type_id'], 'jersey_size_sleeve_unique');
            });
        }

        if (!Schema::hasTable('jersey_size_collar_usage')) {
            Schema::create('jersey_size_collar_usage', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jersey_size_id')->constrained('jersey_sizes')->cascadeOnDelete();
                $table->foreignId('jersey_collar_type_id')->constrained('jersey_collar_types')->cascadeOnDelete();
                $table->decimal('usage_meter', 8, 3)->nullable();
                $table->decimal('usage_weight_gram', 10, 2)->nullable();
                $table->timestamps();
                $table->unique(['jersey_size_id', 'jersey_collar_type_id'], 'jersey_size_collar_unique');
            });
        }

        if (!Schema::hasColumn('jersey_order_lines', 'jersey_sleeve_type_id')) {
            Schema::table('jersey_order_lines', function (Blueprint $table) {
                $table->foreignId('jersey_sleeve_type_id')->nullable()->after('jersey_material_id')
                    ->constrained('jersey_sleeve_types')->nullOnDelete();
                $table->foreignId('jersey_collar_type_id')->nullable()->after('jersey_sleeve_type_id')
                    ->constrained('jersey_collar_types')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('jersey_order_items', 'jersey_sleeve_type_id')) {
            Schema::table('jersey_order_items', function (Blueprint $table) {
                $table->foreignId('jersey_sleeve_type_id')->nullable()->after('sleeve')
                    ->constrained('jersey_sleeve_types')->nullOnDelete();
                $table->foreignId('jersey_collar_type_id')->nullable()->after('collar')
                    ->constrained('jersey_collar_types')->nullOnDelete();
            });
        }

        // Seed Tipe Lengan dari nilai enum yang sudah ada supaya data lama
        // (sleeve='Pendek'/'Panjang') langsung bisa dipetakan ke FK baru.
        if (DB::table('jersey_sleeve_types')->count() === 0) {
            DB::table('jersey_sleeve_types')->insert([
                ['name' => 'Pendek', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Panjang', 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // Backfill jersey_sleeve_type_id pada baris lama berdasarkan nilai
        // enum sleeve yang sudah ada (murni penyambungan data, tidak
        // mengubah nilai sleeve aslinya sama sekali).
        $sleeveIdByName = DB::table('jersey_sleeve_types')->pluck('id', 'name');
        foreach ($sleeveIdByName as $name => $id) {
            DB::table('jersey_order_items')->where('sleeve', $name)->whereNull('jersey_sleeve_type_id')->update(['jersey_sleeve_type_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('jersey_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('jersey_order_items', 'jersey_sleeve_type_id')) {
                $table->dropConstrainedForeignId('jersey_sleeve_type_id');
            }
            if (Schema::hasColumn('jersey_order_items', 'jersey_collar_type_id')) {
                $table->dropConstrainedForeignId('jersey_collar_type_id');
            }
        });
        Schema::table('jersey_order_lines', function (Blueprint $table) {
            if (Schema::hasColumn('jersey_order_lines', 'jersey_sleeve_type_id')) {
                $table->dropConstrainedForeignId('jersey_sleeve_type_id');
            }
            if (Schema::hasColumn('jersey_order_lines', 'jersey_collar_type_id')) {
                $table->dropConstrainedForeignId('jersey_collar_type_id');
            }
        });
        Schema::dropIfExists('jersey_size_sleeve_usage');
        Schema::dropIfExists('jersey_size_collar_usage');
        Schema::dropIfExists('jersey_sleeve_types');
        Schema::dropIfExists('jersey_collar_types');
    }
};
