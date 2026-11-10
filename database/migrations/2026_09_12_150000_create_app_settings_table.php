<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Tabel single-row untuk branding aplikasi (nama, logo, alamat, kontak) -
 * sebelumnya nama aplikasi/brand hardcode di layouts/app.blade.php
 * ("PRINT-ERP" di judul tab, string lain di sidebar), tidak ada logo, dan
 * tidak ada alamat perusahaan di mana pun. Selalu tepat 1 baris (id=1),
 * dibaca lewat AppSetting::current() (cached).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $table) {
                $table->id();
                $table->string('nama_aplikasi')->default('Orion Grafika');
                $table->text('alamat')->nullable();
                $table->string('telepon')->nullable();
                $table->string('email')->nullable();
                $table->string('logo_path')->nullable();
                $table->timestamps();
            });

            DB::table('app_settings')->insert([
                'nama_aplikasi' => 'Orion Grafika',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Daftarkan menu baru supaya bisa diberi akses lewat halaman
        // Setting Akses yang sudah ada (lihat config/menu_access.php untuk
        // penegakan akses di level route-nya) - tanpa baris ini, Setting
        // Akses tidak pernah bisa memberi role lain izin ke halaman ini.
        if (!DB::table('menus')->where('url_link', 'pengaturan-aplikasi')->exists()) {
            DB::table('menus')->insert([
                'nama_menu' => 'Pengaturan Aplikasi',
                'url_link' => 'pengaturan-aplikasi',
                'parent_id' => 0,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('menus')->where('url_link', 'pengaturan-aplikasi')->delete();
        Schema::dropIfExists('app_settings');
    }
};
