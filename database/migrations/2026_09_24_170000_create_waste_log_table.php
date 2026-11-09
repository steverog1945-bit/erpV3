<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// LEVEL 2 (bagian 47 dokumen audit): Waste Management. Normal waste (susut
// wajar proses cetak) langsung diterapkan; Abnormal waste (rusak, salah,
// hilang) menunggu persetujuan - keduanya mengurangi stok, tercatat di
// kartu stok, dan dijurnal ke akun 5200 Beban Rework & Susut Bahan.
return new class extends Migration {
    public function up()
    {
        Schema::create('waste_log', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 50)->unique();
            $table->date('tanggal');
            $table->unsignedBigInteger('id_bahan');
            $table->decimal('qty', 15, 4);
            $table->enum('jenis', ['Normal', 'Abnormal']);
            $table->string('alasan', 255);
            $table->unsignedBigInteger('id_detail')->nullable();
            $table->enum('status', ['Diterapkan', 'Menunggu', 'Ditolak'])->default('Diterapkan');
            $table->decimal('biaya', 15, 2)->default(0);
            $table->unsignedBigInteger('id_user')->nullable();
            $table->timestamps();

            $table->foreign('id_bahan')->references('id_bahan')->on('bahan_baku')->cascadeOnDelete();
            $table->foreign('id_detail')->references('id_detail')->on('transaksi_detail')->nullOnDelete();
            $table->index(['tanggal', 'jenis']);
        });

        if (Schema::hasTable('menus') && !DB::table('menus')->where('url_link', 'waste')->exists()) {
            $newId = (int) DB::table('menus')->max('id_menu') + 1;
            DB::table('menus')->insert(['id_menu' => $newId, 'nama_menu' => 'Waste Management', 'url_link' => 'waste', 'parent_id' => 0]);

            $refIds = DB::table('menus')->whereIn('url_link', ['bahan-baku', 'produksi'])->pluck('id_menu');
            $roles = Schema::hasTable('role_permissions')
                ? DB::table('role_permissions')->whereIn('id_menu', $refIds)->pluck('id_role')->unique()
                : collect();

            foreach ($roles as $idRole) {
                DB::table('role_permissions')->insert(['id_role' => $idRole, 'id_menu' => $newId]);
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('waste_log');

        $id = DB::table('menus')->where('url_link', 'waste')->value('id_menu');
        if ($id) {
            DB::table('role_permissions')->where('id_menu', $id)->delete();
            DB::table('menus')->where('id_menu', $id)->delete();
        }
    }
};
