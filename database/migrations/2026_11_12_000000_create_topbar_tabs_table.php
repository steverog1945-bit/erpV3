<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('topbar_tabs')) {
            Schema::create('topbar_tabs', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('urutan')->default(0);
                $table->string('label', 30);
                $table->string('url', 255);
                $table->string('icon', 30)->default('dasbor');
                $table->boolean('aktif')->default(true);
                $table->timestamps();
                $table->index(['aktif', 'urutan']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('topbar_tabs');
    }
};
