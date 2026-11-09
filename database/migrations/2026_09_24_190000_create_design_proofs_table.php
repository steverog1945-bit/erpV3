<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// LEVEL 3 (bagian 33-35 dokumen audit): Design Proof, Customer Approval,
// dan Revision Management per item order (transaksi_detail).
//
// Status proof: Menunggu Review -> Disetujui / Revisi Diminta; proof yang
// tergantikan revisi berikutnya jadi Digantikan; proof Disetujui yang
// dibuka kembali lewat Approval Workflow jadi Dibuka Kembali.
return new class extends Migration {
    public function up()
    {
        Schema::create('design_proofs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_detail');
            $table->unsignedInteger('revisi_no');
            $table->string('file_path', 500);
            $table->string('nama_file_asli', 255);
            $table->unsignedBigInteger('ukuran_bytes')->default(0);
            $table->text('catatan')->nullable();
            $table->enum('status', ['Menunggu Review', 'Disetujui', 'Revisi Diminta', 'Digantikan', 'Dibuka Kembali'])->default('Menunggu Review');
            $table->unsignedBigInteger('id_user_upload')->nullable();
            $table->string('keputusan_kanal', 30)->nullable();
            $table->text('keputusan_catatan')->nullable();
            $table->timestamp('keputusan_at')->nullable();
            $table->unsignedBigInteger('id_user_keputusan')->nullable();
            $table->timestamps();

            $table->foreign('id_detail')->references('id_detail')->on('transaksi_detail')->cascadeOnDelete();
            $table->unique(['id_detail', 'revisi_no']);
        });

        if (Schema::hasTable('menus') && !DB::table('menus')->where('url_link', 'design-proof')->exists()) {
            $newId = (int) DB::table('menus')->max('id_menu') + 1;
            DB::table('menus')->insert(['id_menu' => $newId, 'nama_menu' => 'Design Proof', 'url_link' => 'design-proof', 'parent_id' => 0]);

            // Akses mengikuti Produksi (23) dan Input Order (3, 41).
            $roles = Schema::hasTable('role_permissions')
                ? DB::table('role_permissions')->whereIn('id_menu', [3, 23, 41])->pluck('id_role')->unique()
                : collect();

            foreach ($roles as $idRole) {
                DB::table('role_permissions')->insert(['id_role' => $idRole, 'id_menu' => $newId]);
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('design_proofs');

        $id = DB::table('menus')->where('url_link', 'design-proof')->value('id_menu');
        if ($id) {
            DB::table('role_permissions')->where('id_menu', $id)->delete();
            DB::table('menus')->where('id_menu', $id)->delete();
        }
    }
};
