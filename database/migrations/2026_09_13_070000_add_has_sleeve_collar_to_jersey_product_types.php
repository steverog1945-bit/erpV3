<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('jersey_product_types', 'has_sleeve_collar')) {
            Schema::table('jersey_product_types', function (Blueprint $table) {
                $table->boolean('has_sleeve_collar')->default(true)->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('jersey_product_types', 'has_sleeve_collar')) {
            Schema::table('jersey_product_types', function (Blueprint $table) {
                $table->dropColumn('has_sleeve_collar');
            });
        }
    }
};
