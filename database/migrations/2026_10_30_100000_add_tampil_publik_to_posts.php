<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tulisan bisa ditandai "tampil di halaman depan" (dibaca tanpa login). Bawaan: tidak, supaya SOP dan
 * pengumuman internal tidak ikut terbuka. Tulisan yang SUDAH terbit saat migrasi ini dijalankan ditandai
 * publik agar halaman depan langsung berisi; ubah per tulisan lewat form Postingan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('posts') || Schema::hasColumn('posts', 'tampil_publik')) {
            return;
        }
        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('tampil_publik')->default(false)->after('disematkan')->index();
        });
        DB::table('posts')->where('status', 'terbit')->whereNull('deleted_at')->update(['tampil_publik' => true]);
    }

    public function down(): void
    {
        if (Schema::hasTable('posts') && Schema::hasColumn('posts', 'tampil_publik')) {
            Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('tampil_publik'));
        }
    }
};
