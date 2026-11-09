<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahan harga per ukuran (jersey_sizes.price_surcharge, mis. 2XL-5XL
 * lebih mahal) sudah bisa diatur di Master Data sejak awal, tapi TIDAK
 * PERNAH benar-benar dijumlahkan ke subtotal saat order dibuat
 * (JerseyOrderController::store() cuma menghitung harga_satuan x qty dari
 * tier, tidak pernah membaca price_surcharge sama sekali) - baris order
 * dengan ukuran besar jadi kurang tagih. Kolom ini menyimpan total
 * tambahan harga ukuran per baris (auditable, bukan cuma angka tersembunyi
 * di dalam subtotal) supaya bisa ditampilkan terpisah di halaman detail.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('jersey_order_lines', 'size_surcharge_total')) {
            Schema::table('jersey_order_lines', function (Blueprint $table) {
                $table->decimal('size_surcharge_total', 12, 2)->default(0)->after('subtotal');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('jersey_order_lines', 'size_surcharge_total')) {
            Schema::table('jersey_order_lines', function (Blueprint $table) {
                $table->dropColumn('size_surcharge_total');
            });
        }
    }
};
