<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_detail', function (Blueprint $table) {
            if (!Schema::hasColumn('transaksi_detail', 'thumbnail_preview')) {
                $table->string('thumbnail_preview', 255)->nullable()->after('thumbnail');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_detail', function (Blueprint $table) {
            if (Schema::hasColumn('transaksi_detail', 'thumbnail_preview')) {
                $table->dropColumn('thumbnail_preview');
            }
        });
    }
};
