<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Info & Panduan: postingan internal ala blog (pengumuman, SOP, tips finishing, hasil karya).
 * Menu 67 "Info & Panduan": semua role boleh MEMBACA; menulis/mengubah/menghapus hanya Administrator
 * (bisa diberikan ke role lain lewat Pengaturan Hak Akses).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_kategori', function (Blueprint $t) {
            $t->id();
            $t->string('nama', 60)->unique();
            $t->string('slug', 80)->unique();
            $t->timestamps();
        });

        Schema::create('posts', function (Blueprint $t) {
            $t->id();
            $t->string('judul', 200);
            $t->string('slug', 220)->unique();
            $t->string('ringkasan', 300)->nullable();
            $t->longText('isi');
            $t->string('gambar_sampul', 255)->nullable();
            $t->unsignedBigInteger('id_kategori')->nullable()->index();
            $t->string('tags', 255)->nullable();
            $t->string('status', 10)->default('draft')->index(); // draft | terbit
            $t->timestamp('terbit_at')->nullable()->index();      // di masa depan = terjadwal
            $t->boolean('disematkan')->default(false)->index();
            $t->unsignedBigInteger('id_penulis')->nullable();
            $t->unsignedInteger('dibaca')->default(0);
            $t->unsignedSmallInteger('menit_baca')->default(1);
            $t->timestamps();
            $t->softDeletes();
        });

        DB::table('post_kategori')->insert([
            ['nama' => 'Pengumuman', 'slug' => 'pengumuman', 'created_at' => now(), 'updated_at' => now()],
            ['nama' => 'SOP & Panduan', 'slug' => 'sop-panduan', 'created_at' => now(), 'updated_at' => now()],
            ['nama' => 'Tips Finishing', 'slug' => 'tips-finishing', 'created_at' => now(), 'updated_at' => now()],
            ['nama' => 'Hasil Karya', 'slug' => 'hasil-karya', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('menus')->insertOrIgnore(['id_menu' => 67, 'nama_menu' => 'Info & Panduan', 'url_link' => 'postingan', 'parent_id' => 0]);
        $idAdmin = DB::table('roles')->where('nama_role', 'Administrator')->value('id_role');
        foreach (DB::table('roles')->get() as $r) {
            $penuh = (int) $r->id_role === (int) $idAdmin;
            DB::table('role_permissions')->updateOrInsert(
                ['id_role' => $r->id_role, 'id_menu' => 67],
                ['can_view' => 1, 'can_add' => $penuh ? 1 : 0, 'can_edit' => $penuh ? 1 : 0, 'can_delete' => $penuh ? 1 : 0]
            );
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('id_menu', 67)->delete();
        DB::table('menus')->where('id_menu', 67)->delete();
        Schema::dropIfExists('posts');
        Schema::dropIfExists('post_kategori');
    }
};
