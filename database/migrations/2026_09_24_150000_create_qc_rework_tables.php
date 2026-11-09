<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// LEVEL 2 (bagian 21-22 dokumen audit): Quality Control & Rework Management.
// QC dilakukan per item order (transaksi_detail); qty reject otomatis
// membuka Rework, dan material tambahan rework dihitung dari BOM lalu
// dicatat ke stok, kartu stok, dan jurnal (akun 5200 Beban Rework & Susut).
return new class extends Migration {
    public function up()
    {
        if (Schema::hasTable('bag_akun') && !DB::table('bag_akun')->where('kode_akun', '5200')->exists()) {
            DB::table('bag_akun')->insert([
                'kode_akun' => '5200', 'nama_akun' => 'Beban Rework & Susut Bahan', 'tipe_akun' => 'Beban',
                'saldo_normal' => 'Debet', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::create('qc_inspeksi', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 50)->unique();
            $table->unsignedBigInteger('id_detail');
            $table->date('tanggal');
            $table->unsignedInteger('qty_diperiksa');
            $table->unsignedInteger('qty_lolos');
            $table->unsignedInteger('qty_reject')->default(0);
            // Checklist: ukuran, warna, posisi_desain, jumlah, finishing, cacat_cetak
            // (true = item bermasalah pada poin tsb).
            $table->json('checklist')->nullable();
            $table->enum('hasil', ['Lulus', 'Rework'])->default('Lulus');
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('id_user')->nullable();
            $table->timestamps();

            $table->foreign('id_detail')->references('id_detail')->on('transaksi_detail')->cascadeOnDelete();
        });

        Schema::create('rework', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 50)->unique();
            $table->foreignId('id_qc_inspeksi')->constrained('qc_inspeksi')->cascadeOnDelete();
            $table->unsignedBigInteger('id_detail');
            $table->unsignedInteger('qty_rework');
            $table->enum('status', ['Dibuka', 'Selesai'])->default('Dibuka');
            $table->decimal('biaya_material', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->timestamp('selesai_at')->nullable();
            $table->unsignedBigInteger('id_user')->nullable();
            $table->timestamps();

            $table->foreign('id_detail')->references('id_detail')->on('transaksi_detail')->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('rework');
        Schema::dropIfExists('qc_inspeksi');
        DB::table('bag_akun')->where('kode_akun', '5200')->delete();
    }
};
