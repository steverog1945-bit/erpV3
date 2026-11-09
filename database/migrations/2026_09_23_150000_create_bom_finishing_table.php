<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabel `bom_finishing` sudah lama ada & dipakai (ProdukController,
// BomFinishingController) tapi dibuat manual di luar migration (kemungkinan
// lewat phpMyAdmin) - jadi tidak pernah tercatat di riwayat migration.
// Migration ini murni MENDOKUMENTASIKAN struktur yang sudah ada persis
// seperti live schema saat ini (audit Phase 1/2), supaya instalasi baru
// (clone project, environment lain) ikut mendapat tabel ini. Untuk
// database yang sudah berjalan sekarang, migration ini ditandai "sudah
// diterapkan" di tabel `migrations` tanpa dijalankan ulang (tabelnya
// sudah ada, CREATE TABLE ulang akan gagal/berbahaya).
return new class extends Migration {
    public function up()
    {
        Schema::create('bom_finishing', function (Blueprint $table) {
            $table->id('id_bom_finishing');
            $table->unsignedBigInteger('id_produk');
            $table->unsignedBigInteger('id_finishing');
            $table->decimal('qty', 10, 2)->default(1.00);
            $table->timestamps();

            $table->foreign('id_produk')->references('id_produk')->on('produk');
            $table->foreign('id_finishing')->references('id_finishing')->on('finishing');
        });
    }

    public function down()
    {
        Schema::dropIfExists('bom_finishing');
    }
};
