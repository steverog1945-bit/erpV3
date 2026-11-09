<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabel `finishing` (master jenis finishing: laminasi, mata ayam, dst) sudah
// lama ada & dipakai luas (FinishingController, ProdukController,
// transaksi_detail.id_finishing, dll) tapi dibuat manual di luar migration -
// jadi tidak pernah tercatat, dan setiap kali proyek ini di-migrate dari
// kosong (instalasi baru/CI/testing), migration
// 2026_09_10_050000_add_finishing_fields_to_transaksi_detail gagal keras
// ("Foreign key constraint is incorrectly formed") karena tabel yang
// dirujuknya tidak pernah ada. Migration ini murni MENDOKUMENTASIKAN
// struktur yang sudah ada persis seperti live schema saat ini. Untuk
// database yang sudah berjalan sekarang, ditandai "sudah diterapkan" tanpa
// dijalankan ulang (tabelnya sudah ada).
return new class extends Migration {
    public function up()
    {
        Schema::create('finishing', function (Blueprint $table) {
            $table->id('id_finishing');
            $table->string('nama_finishing', 100)->nullable();
            $table->decimal('harga_default', 15, 2)->nullable()->default(0);
            $table->unsignedBigInteger('id_satuan')->nullable();
            $table->unsignedBigInteger('id_kategori')->nullable();
            $table->unsignedBigInteger('id_mesin')->nullable();
            $table->string('metode_hitung', 20)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index('id_mesin', 'fk_finishing_mesin');
        });
    }

    public function down()
    {
        Schema::dropIfExists('finishing');
    }
};
