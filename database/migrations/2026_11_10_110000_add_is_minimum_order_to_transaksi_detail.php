<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('transaksi_detail', 'is_minimum_order')) {
            return;
        }

        Schema::table('transaksi_detail', function (Blueprint $table) {
            $table->boolean('is_minimum_order')->default(false)->after('is_susulan');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('transaksi_detail', 'is_minimum_order')) {
            return;
        }

        Schema::table('transaksi_detail', function (Blueprint $table) {
            $table->dropColumn('is_minimum_order');
        });
    }
};
