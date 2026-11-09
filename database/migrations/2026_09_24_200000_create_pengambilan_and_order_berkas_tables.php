<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// LEVEL 3 (bagian 35 dokumen audit + Pickup/Delivery Management):
//  - pengambilan: catatan serah terima barang per nota (siapa penerima,
//    diambil sendiri/perwakilan/dikirim, bukti foto).
//  - order_berkas: berkas order berkategori (Production File, QC Photo,
//    Lainnya) selain Proof (design_proofs) dan bukti serah terima
//    (pengambilan) yang sudah punya tabel sendiri.
return new class extends Migration {
    public function up()
    {
        Schema::create('pengambilan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_transaksi')->unique();
            $table->enum('jenis', ['Diambil Sendiri', 'Diambil Perwakilan', 'Dikirim'])->default('Diambil Sendiri');
            $table->string('nama_penerima', 150)->nullable();
            $table->text('catatan')->nullable();
            $table->string('bukti_path', 500)->nullable();
            $table->unsignedBigInteger('id_user')->nullable();
            $table->timestamps();

            $table->foreign('id_transaksi')->references('id_transaksi')->on('transaksi')->cascadeOnDelete();
        });

        Schema::create('order_berkas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_transaksi');
            $table->unsignedBigInteger('id_detail')->nullable();
            $table->enum('kategori', ['Production File', 'QC Photo', 'Lainnya']);
            $table->string('file_path', 500);
            $table->string('nama_file_asli', 255);
            $table->unsignedBigInteger('ukuran_bytes')->default(0);
            $table->string('catatan', 255)->nullable();
            $table->unsignedBigInteger('id_user')->nullable();
            $table->timestamps();

            $table->foreign('id_transaksi')->references('id_transaksi')->on('transaksi')->cascadeOnDelete();
            $table->foreign('id_detail')->references('id_detail')->on('transaksi_detail')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_berkas');
        Schema::dropIfExists('pengambilan');
    }
};
