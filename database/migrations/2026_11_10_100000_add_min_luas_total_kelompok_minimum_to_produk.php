<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('produk', 'min_luas_total') && Schema::hasColumn('produk', 'kelompok_minimum')) {
            return;
        }

        Schema::table('produk', function (Blueprint $table) {
            if (!Schema::hasColumn('produk', 'min_luas_total')) {
                $table->decimal('min_luas_total', 10, 4)->default(0)->after('min_lebar');
            }
            if (!Schema::hasColumn('produk', 'kelompok_minimum')) {
                $table->string('kelompok_minimum', 100)->nullable()->after('min_luas_total');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('produk', 'min_luas_total') && !Schema::hasColumn('produk', 'kelompok_minimum')) {
            return;
        }

        Schema::table('produk', function (Blueprint $table) {
            if (Schema::hasColumn('produk', 'min_luas_total')) {
                $table->dropColumn('min_luas_total');
            }
            if (Schema::hasColumn('produk', 'kelompok_minimum')) {
                $table->dropColumn('kelompok_minimum');
            }
        });
    }
};
