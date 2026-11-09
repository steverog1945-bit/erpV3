<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Stock Opname (roadmap LEVEL 1 - WAJIB, bagian 7 dokumen audit). Alurnya
// SENGAJA tidak langsung mengubah stok saat dibuat - lihat catatan di
// App\Services\StockOpnameService: Draft -> Diajukan -> Approval (lewat
// generic ApprovalService yang sudah ada) -> baru stok benar-benar
// disesuaikan saat disetujui.
return new class extends Migration {
    public function up()
    {
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 50)->unique();
            $table->unsignedBigInteger('id_gudang')->nullable();
            $table->date('tanggal');
            $table->enum('status', ['Draft', 'Diajukan', 'Disetujui', 'Ditolak'])->default('Draft');
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('id_user_pembuat');
            $table->timestamps();

            $table->foreign('id_gudang')->references('id_gudang')->on('gudang')->nullOnDelete();
            $table->foreign('id_user_pembuat')->references('id_user')->on('users')->cascadeOnDelete();
        });

        Schema::create('stock_opname_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_stock_opname')->constrained('stock_opnames')->cascadeOnDelete();
            $table->unsignedBigInteger('id_bahan');
            // Snapshot stok sistem SAAT baris opname dibuat - dipakai untuk
            // ditampilkan sebagai pembanding di form, BUKAN dasar
            // perhitungan stok_sesudah saat approval (lihat
            // StockOpnameService::eksekusiAdjustment() - itu re-baca stok
            // TERKINI di dalam lockForUpdate supaya tidak menimpa mutasi
            // stok lain yang terjadi selama opname masih Diajukan).
            $table->decimal('stok_sistem', 15, 4);
            $table->decimal('stok_fisik', 15, 4)->nullable();
            $table->string('alasan', 255)->nullable();
            $table->timestamps();

            $table->foreign('id_bahan')->references('id_bahan')->on('bahan_baku')->cascadeOnDelete();
            $table->unique(['id_stock_opname', 'id_bahan']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_opname_details');
        Schema::dropIfExists('stock_opnames');
    }
};
