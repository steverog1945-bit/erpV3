<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'foto_profil')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('foto_profil', 255)->nullable()->after('username');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'foto_profil')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('foto_profil');
        });
    }
};
