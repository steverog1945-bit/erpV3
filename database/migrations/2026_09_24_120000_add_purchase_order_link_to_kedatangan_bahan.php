<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tautan Kedatangan Bahan -> Purchase Order (siklus Purchasing). Nullable:
// kedatangan tanpa PO (alur lama) tetap valid.
return new class extends Migration {
    public function up()
    {
        if (!Schema::hasColumn('kedatangan_bahan', 'id_purchase_order')) {
            Schema::table('kedatangan_bahan', function (Blueprint $table) {
                $table->unsignedBigInteger('id_purchase_order')->nullable();
                $table->foreign('id_purchase_order')->references('id')->on('purchase_orders')->nullOnDelete();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('kedatangan_bahan', 'id_purchase_order')) {
            Schema::table('kedatangan_bahan', function (Blueprint $table) {
                $table->dropForeign(['id_purchase_order']);
                $table->dropColumn('id_purchase_order');
            });
        }
    }
};
