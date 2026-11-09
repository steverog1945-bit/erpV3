<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Siklus Purchasing (roadmap LEVEL 1, bagian 9-12 dokumen audit): tahap
// hulu Purchase Request -> Purchase Order. Hilirnya (Kedatangan Bahan ->
// Pembelian/faktur -> hutang -> pelunasan) sudah ada di modul
// kedatangan_bahan & pembelian_bahan.
return new class extends Migration {
    public function up()
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 50)->unique();
            $table->date('tanggal');
            $table->enum('status', ['Draft', 'Diajukan', 'Disetujui', 'Ditolak', 'Diproses PO'])->default('Draft');
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('id_user_pembuat');
            $table->timestamps();

            $table->foreign('id_user_pembuat')->references('id_user')->on('users')->cascadeOnDelete();
        });

        Schema::create('purchase_request_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_purchase_request')->constrained('purchase_requests')->cascadeOnDelete();
            $table->unsignedBigInteger('id_bahan');
            $table->decimal('qty', 15, 2);
            $table->string('catatan', 255)->nullable();
            $table->timestamps();

            $table->foreign('id_bahan')->references('id_bahan')->on('bahan_baku')->cascadeOnDelete();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 50)->unique();
            $table->unsignedBigInteger('id_supplier');
            $table->foreignId('id_purchase_request')->nullable()->constrained('purchase_requests')->nullOnDelete();
            $table->date('tanggal');
            $table->date('tgl_pengiriman_estimasi')->nullable();
            $table->string('termin_pembayaran', 100)->nullable();
            $table->enum('status', ['Draft', 'Dipesan', 'Diterima Sebagian', 'Selesai', 'Dibatalkan'])->default('Draft');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('diskon', 15, 2)->default(0);
            $table->decimal('pajak_persen', 5, 2)->default(0);
            $table->decimal('ongkir', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('id_user_pembuat');
            $table->timestamps();

            $table->foreign('id_supplier')->references('id_supplier')->on('supplier')->restrictOnDelete();
            $table->foreign('id_user_pembuat')->references('id_user')->on('users')->cascadeOnDelete();
        });

        Schema::create('purchase_order_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_purchase_order')->constrained('purchase_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('id_bahan');
            $table->decimal('qty', 15, 2);
            $table->decimal('harga', 15, 2);
            $table->decimal('subtotal', 15, 2);
            // Diisi saat Kedatangan Bahan dikaitkan ke PO ini (tahap berikutnya).
            $table->decimal('qty_diterima', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('id_bahan')->references('id_bahan')->on('bahan_baku')->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_order_details');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_request_details');
        Schema::dropIfExists('purchase_requests');
    }
};
