<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Stok kain (bahan baku tersinkron) dipotong saat order jersey mulai diproses; rinciannya dicatat untuk pengembalian yang akurat. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jersey_orders', function (Blueprint $t) {
            $t->timestamp('stok_dipotong_at')->nullable();
        });

        Schema::create('jersey_order_potong_stok', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('jersey_order_id')->index();
            $t->unsignedBigInteger('id_bahan');
            $t->decimal('qty', 14, 4);
            $t->decimal('biaya', 15, 2)->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jersey_order_potong_stok');
        Schema::table('jersey_orders', fn (Blueprint $t) => $t->dropColumn('stok_dipotong_at'));
    }
};
