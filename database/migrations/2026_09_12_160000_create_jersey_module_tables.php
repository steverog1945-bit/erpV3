<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Modul Order Jersey - transaksi TERPISAH dari alur transaksi/kasir/BOM
 * percetakan-app yang sudah ada (keputusan disetujui user: modul ringan
 * dengan alur sendiri, bukan terintegrasi ke tabel transaksi inti yang
 * baru saja pulih dari 2 insiden corruption sesi ini). Semua tabel diberi
 * prefix jersey_ supaya tidak pernah ambigu dengan tabel manapun - ini
 * persis kelas masalah yang menyebabkan insiden pertama sesi ini (tabel
 * jersey_order_system tanpa prefix ter-mix ke database percetakan_erp).
 *
 * Struktur mengikuti aplikasi jersey_order_system yang sudah terbukti
 * jalan (lihat audit skema sebelumnya) - bukan dikarang dari nol. Setelah
 * order jersey siap dibayar, kasir tetap membuat transaksi biasa di Kasir
 * dan mencatat nomor order jersey di catatan_referensi_transaksi (kolom
 * teks bebas, BUKAN foreign key) - tidak ada keterkaitan data dengan
 * transaksi/transaksi_detail/kartu_stok/BOM sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('jersey_customers')) {
            Schema::create('jersey_customers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('phone')->nullable();
                $table->text('address')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index('name');
            });
        }

        if (!Schema::hasTable('jersey_designers')) {
            Schema::create('jersey_designers', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('jersey_product_types')) {
            Schema::create('jersey_product_types', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('jersey_categories')) {
            Schema::create('jersey_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            DB::table('jersey_categories')->insert([
                ['name' => 'Jersey Futsal', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Jersey Bola', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Jersey Printing', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Jersey Esports', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Jersey Custom', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        if (!Schema::hasTable('jersey_sizes')) {
            Schema::create('jersey_sizes', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->decimal('price_surcharge', 12, 2)->default(0);
                $table->timestamps();
            });

            DB::table('jersey_sizes')->insert([
                ['name' => 'S', 'sort_order' => 1, 'is_active' => 1, 'price_surcharge' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'M', 'sort_order' => 2, 'is_active' => 1, 'price_surcharge' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'L', 'sort_order' => 3, 'is_active' => 1, 'price_surcharge' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'XL', 'sort_order' => 4, 'is_active' => 1, 'price_surcharge' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['name' => '2XL', 'sort_order' => 5, 'is_active' => 1, 'price_surcharge' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['name' => '3XL', 'sort_order' => 6, 'is_active' => 1, 'price_surcharge' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['name' => '4XL', 'sort_order' => 7, 'is_active' => 1, 'price_surcharge' => 0, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        if (!Schema::hasTable('jersey_price_settings')) {
            Schema::create('jersey_price_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jersey_product_type_id')->nullable()
                    ->constrained('jersey_product_types')->nullOnDelete();
                $table->foreignId('jersey_category_id')->nullable()
                    ->constrained('jersey_categories')->nullOnDelete();
                $table->unsignedInteger('qty_min');
                $table->unsignedInteger('qty_max')->nullable();
                $table->decimal('price', 12, 2);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['jersey_product_type_id', 'jersey_category_id', 'qty_min', 'qty_max'], 'jersey_price_tier_idx');
            });
        }

        if (!Schema::hasTable('jersey_orders')) {
            Schema::create('jersey_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_number')->unique();
                $table->date('order_date');

                $table->foreignId('jersey_customer_id')->nullable()
                    ->constrained('jersey_customers')->nullOnDelete();
                $table->string('customer_name');
                $table->string('customer_phone')->nullable();

                $table->string('team_name')->nullable();

                $table->foreignId('jersey_product_type_id')->nullable()
                    ->constrained('jersey_product_types')->nullOnDelete();
                $table->foreignId('jersey_category_id')->nullable()
                    ->constrained('jersey_categories')->nullOnDelete();

                $table->date('deadline')->nullable();
                $table->enum('status', ['baru', 'proses', 'selesai', 'diambil'])->default('baru');
                $table->enum('order_type', ['quantity_only', 'player_list', 'mixed'])->default('player_list');

                $table->foreignId('jersey_designer_id')->nullable()
                    ->constrained('jersey_designers')->nullOnDelete();
                $table->string('designer_name')->nullable();

                $table->text('notes')->nullable();

                $table->decimal('total', 12, 2)->default(0);
                $table->decimal('discount', 12, 2)->default(0);
                $table->decimal('additional_charge', 12, 2)->default(0);
                $table->json('price_breakdown')->nullable();
                $table->foreignId('jersey_price_setting_id')->nullable()
                    ->constrained('jersey_price_settings')->nullOnDelete();
                $table->decimal('deposit', 12, 2)->default(0);

                // Status pembayaran & referensi transaksi Kasir MURNI catatan -
                // modul ini sengaja tidak terhubung (tidak ada FK) ke tabel
                // transaksi inti, lihat catatan keputusan di atas migrasi ini.
                $table->enum('status_pembayaran', ['Belum Bayar', 'DP', 'Lunas'])->default('Belum Bayar');
                $table->string('catatan_referensi_transaksi')->nullable();

                $table->foreignId('created_by')->nullable()
                    ->constrained('users', 'id_user')->nullOnDelete();

                $table->timestamps();
            });
        }

        if (!Schema::hasTable('jersey_order_items')) {
            Schema::create('jersey_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jersey_order_id')->constrained('jersey_orders')->cascadeOnDelete();
                $table->string('player_name');
                $table->string('player_number')->nullable();
                $table->enum('size', ['S', 'M', 'L', 'XL', '2XL', '3XL', '4XL']);
                $table->enum('gender', ['L', 'P']);
                $table->enum('sleeve', ['Pendek', 'Panjang']);
                $table->string('collar')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('jersey_order_size_quantities')) {
            Schema::create('jersey_order_size_quantities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jersey_order_id')->constrained('jersey_orders')->cascadeOnDelete();
                $table->foreignId('jersey_size_id')->constrained('jersey_sizes');
                $table->unsignedInteger('quantity');
                $table->timestamps();
                $table->unique(['jersey_order_id', 'jersey_size_id'], 'jersey_order_size_unique');
            });
        }

        // Daftarkan menu baru supaya bisa diberi akses lewat Setting Akses -
        // sengaja TIDAK ditambahkan ke config/menu_access.php (lihat catatan
        // di SettingAplikasiController) supaya default-nya admin-only sampai
        // Administrator sengaja membuka akses untuk role lain.
        if (!DB::table('menus')->where('url_link', 'jersey')->exists()) {
            DB::table('menus')->insert([
                'nama_menu' => 'Order Jersey',
                'url_link' => 'jersey',
                'parent_id' => 0,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('menus')->where('url_link', 'jersey')->delete();
        Schema::dropIfExists('jersey_order_size_quantities');
        Schema::dropIfExists('jersey_order_items');
        Schema::dropIfExists('jersey_orders');
        Schema::dropIfExists('jersey_price_settings');
        Schema::dropIfExists('jersey_sizes');
        Schema::dropIfExists('jersey_categories');
        Schema::dropIfExists('jersey_product_types');
        Schema::dropIfExists('jersey_designers');
        Schema::dropIfExists('jersey_customers');
    }
};
