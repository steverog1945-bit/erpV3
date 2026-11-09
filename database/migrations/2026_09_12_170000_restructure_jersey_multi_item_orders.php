<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Support\Concerns\SafelyDropsForeignKeys;

/**
 * Rombak modul jersey berdasarkan masukan langsung setelah demo Fase 5:
 * (1) pelanggan HARUS diambil dari tabel konsumen yang sudah ada (bukan
 *     tabel jersey_customers terpisah - dihapus di sini, belum ada data
 *     riil sama sekali sejak dibuat).
 * (2) satu order jersey bisa berisi BEBERAPA jenis barang sekaligus
 *     (Jersey + Celana + bahan sublime lainnya), masing-masing dengan
 *     ukuran/qty/harga/thumbnail/spesifikasi bahan sendiri - sebelumnya
 *     1 order cuma 1 kategori/tipe produk untuk keseluruhan order.
 *
 * Makanya "order" dipecah jadi order -> order_lines (satu baris per jenis
 * barang) -> (order_items untuk daftar pemain, order_size_quantities
 * untuk qty per ukuran, order_line_materials untuk tabel SPESIFIKASI
 * ORDER ala SPK referensi). Tidak ada data order riil yang hilang - satu-
 * satunya order yang sempat dibuat sudah dihapus sendiri sebagai bagian
 * pembersihan data uji sebelum commit Fase 5.
 */
return new class extends Migration
{
    use SafelyDropsForeignKeys;

    public function up(): void
    {
        // 1. Lepas kolom/FK lama di jersey_orders yang pindah ke level line,
        // ganti jersey_customer_id -> id_konsumen (tabel konsumen asli).
        if (Schema::hasColumn('jersey_orders', 'jersey_customer_id')) {
            $this->dropForeignIfExists('jersey_orders', 'jersey_orders_jersey_customer_id_foreign');
            $this->dropForeignIfExists('jersey_orders', 'jersey_orders_jersey_product_type_id_foreign');
            $this->dropForeignIfExists('jersey_orders', 'jersey_orders_jersey_category_id_foreign');
            $this->dropForeignIfExists('jersey_orders', 'jersey_orders_jersey_price_setting_id_foreign');
            Schema::table('jersey_orders', function (Blueprint $table) {
                $table->dropColumn([
                    'jersey_customer_id',
                    'jersey_product_type_id',
                    'jersey_category_id',
                    'order_type',
                    'price_breakdown',
                    'jersey_price_setting_id',
                ]);
            });
        }

        if (!Schema::hasColumn('jersey_orders', 'id_konsumen')) {
            Schema::table('jersey_orders', function (Blueprint $table) {
                $table->foreignId('id_konsumen')->nullable()->after('order_date')
                    ->constrained('konsumen', 'id_konsumen')->nullOnDelete();
            });
        }

        // 2. Drop tabel-tabel lama yang formatnya berubah (kosong, aman) -
        // dibuat ulang di bawah dengan struktur baru berbasis line.
        Schema::dropIfExists('jersey_order_size_quantities');
        Schema::dropIfExists('jersey_order_items');
        Schema::dropIfExists('jersey_customers');

        // 3. jersey_order_lines - satu baris per JENIS BARANG dalam 1 order
        // (Jersey, Celana, Sublime Lainnya, dst - jersey_product_type_id
        // menunjuk ke master Tipe Produk yang sudah ada).
        if (!Schema::hasTable('jersey_order_lines')) {
            Schema::create('jersey_order_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jersey_order_id')->constrained('jersey_orders')->cascadeOnDelete();
                $table->foreignId('jersey_product_type_id')->nullable()
                    ->constrained('jersey_product_types')->nullOnDelete();
                $table->foreignId('jersey_category_id')->nullable()
                    ->constrained('jersey_categories')->nullOnDelete();
                $table->string('nama_barang');
                $table->string('bahan')->nullable();
                $table->text('keterangan')->nullable();
                $table->string('thumbnail')->nullable();
                $table->enum('order_type', ['quantity_only', 'player_list', 'mixed'])->default('quantity_only');
                $table->unsignedInteger('qty')->default(0);
                $table->decimal('harga_satuan', 12, 2)->default(0);
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->foreignId('jersey_price_setting_id')->nullable()
                    ->constrained('jersey_price_settings')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 4. jersey_order_items (daftar pemain) & jersey_order_size_quantities
        // (qty per ukuran) sekarang menempel ke LINE, bukan langsung ke order -
        // satu order bisa punya beberapa line dengan daftar pemain/ukuran
        // yang berbeda-beda (mis. pemain jersey != pemain celana).
        Schema::create('jersey_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jersey_order_line_id')->constrained('jersey_order_lines')->cascadeOnDelete();
            $table->string('player_name');
            $table->string('player_number')->nullable();
            $table->enum('size', ['S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL']);
            $table->enum('gender', ['L', 'P']);
            $table->enum('sleeve', ['Pendek', 'Panjang']);
            $table->string('collar')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('jersey_order_size_quantities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jersey_order_line_id')->constrained('jersey_order_lines')->cascadeOnDelete();
            $table->foreignId('jersey_size_id')->constrained('jersey_sizes');
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['jersey_order_line_id', 'jersey_size_id'], 'jersey_order_line_size_unique');
        });

        // 5. jersey_order_line_materials - tabel "SPESIFIKASI ORDER" ala SPK
        // referensi (nama barang/warna/penggunaan bahan Kg-Meter-Yard/
        // keterangan) - beberapa baris per line, murni catatan produksi,
        // TIDAK memotong stok bahan_baku sama sekali (modul ini sengaja
        // tidak tersambung ke kartu_stok/BOM percetakan, lihat migrasi
        // pembuatan modul sebelumnya).
        if (!Schema::hasTable('jersey_order_line_materials')) {
            Schema::create('jersey_order_line_materials', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jersey_order_line_id')->constrained('jersey_order_lines')->cascadeOnDelete();
                $table->string('nama_barang')->nullable();
                $table->string('warna')->nullable();
                $table->decimal('penggunaan_kg', 10, 2)->nullable();
                $table->decimal('penggunaan_meter', 10, 2)->nullable();
                $table->decimal('penggunaan_yard', 10, 2)->nullable();
                $table->string('keterangan')->nullable();
                $table->timestamps();
            });
        }

        // Ukuran 5XL muncul di referensi SPK tapi belum ada di seed awal.
        if (!DB::table('jersey_sizes')->where('name', '5XL')->exists()) {
            DB::table('jersey_sizes')->insert([
                'name' => '5XL', 'sort_order' => 8, 'is_active' => 1, 'price_surcharge' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Kolom size di order_items lama dibatasi enum tanpa 5XL - sudah
        // dibuat ulang dengan 5XL di atas untuk tabel baru, tidak perlu
        // ALTER tambahan.
    }

    public function down(): void
    {
        Schema::dropIfExists('jersey_order_line_materials');
        Schema::dropIfExists('jersey_order_size_quantities');
        Schema::dropIfExists('jersey_order_items');
        Schema::dropIfExists('jersey_order_lines');

        if (Schema::hasColumn('jersey_orders', 'id_konsumen')) {
            Schema::table('jersey_orders', function (Blueprint $table) {
                $table->dropForeign(['id_konsumen']);
                $table->dropColumn('id_konsumen');
            });
        }
    }
};
