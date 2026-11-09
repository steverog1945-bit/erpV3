<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        // --- KELOMPOK PRODUKSI & MESIN ---
        Schema::create('mesin', function (Blueprint $table) {
            $table->id('id_mesin');
            $table->string('nama_mesin', 100);
            $table->string('tipe_mesin', 50);
            $table->timestamps();
        });

        Schema::create('bahan_baku', function (Blueprint $table) {
            $table->id('id_bahan');
            $table->string('kode_bahan', 50)->unique();
            $table->string('nama_bahan', 100);
            $table->unsignedBigInteger('id_mesin');
            $table->unsignedBigInteger('id_kategori');
            $table->decimal('stok', 15, 2)->default(0);
            $table->string('satuan_stok', 20);
            $table->foreign('id_mesin')->references('id_mesin')->on('mesin');
            $table->foreign('id_kategori')->references('id_kategori')->on('kategori');
        });

        Schema::create('produk', function (Blueprint $table) {
            $table->id('id_produk');
            $table->string('nama_produk', 100);
            $table->unsignedBigInteger('id_kategori');
            $table->enum('metode_hitung', ['ukuran', 'quantity']);
            $table->decimal('min_order', 10, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->foreign('id_kategori')->references('id_kategori')->on('kategori');
        });

        Schema::create('bom_detail', function (Blueprint $table) {
            $table->id('id_bom');
            $table->unsignedBigInteger('id_produk');
            $table->unsignedBigInteger('id_bahan');
            $table->string('cara_hitung_pemakaian'); // Rumus Logic
            $table->foreign('id_produk')->references('id_produk')->on('produk')->onDelete('cascade');
            $table->foreign('id_bahan')->references('id_bahan')->on('bahan_baku');
        });

        // --- KELOMPOK HARGA & PROMO ---
        Schema::create('setting_harga', function (Blueprint $table) {
            $table->id('id_harga');
            $table->unsignedBigInteger('id_produk');
            $table->unsignedBigInteger('id_golongan');
            $table->integer('range_min');
            $table->integer('range_max');
            $table->decimal('harga_jual', 15, 2);
            $table->string('satuan_berlaku', 20);
            $table->foreign('id_produk')->references('id_produk')->on('produk');
        });

        Schema::create('harga_promo', function (Blueprint $table) {
            $table->id('id_promo');
            $table->unsignedBigInteger('id_produk');
            $table->decimal('harga_promo', 15, 2);
            $table->date('tgl_mulai');
            $table->date('tgl_selesai');
            $table->boolean('is_active')->default(1);
            $table->foreign('id_produk')->references('id_produk')->on('produk');
        });

        // --- KELOMPOK TRANSAKSI & JURNAL (AKUNTANSI) ---
        Schema::create('pembelian_bahan', function (Blueprint $table) {
            $table->id('id_pembelian');
            $table->string('no_faktur_supplier')->unique();
            $table->unsignedBigInteger('id_supplier');
            $table->date('tgl_pembelian');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('pajak', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2);
            $table->enum('status_pajak', ['include', 'exclude', 'non-pajak']);
            $table->timestamps();
        });

        // Tabel Jurnal Umum (Kunci Kelengkapan Keuangan)
        Schema::create('jurnal_umum', function (Blueprint $table) {
            $table->id('id_jurnal');
            $table->date('tgl_jurnal');
            $table->string('no_referensi'); // No Faktur atau No Nota
            $table->string('keterangan');
            $table->decimal('debet', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->string('kode_akun'); // COA (Chart of Account)
            $table->timestamps();
        });

        Schema::create('supplier', function (Blueprint $table) {
            $table->id('id_supplier');
            $table->string('nama_supplier');
            $table->text('alamat')->nullable();
            $table->string('telepon');
        });
    }

    public function down() {
        Schema::dropIfExists('jurnal_umum');
        Schema::dropIfExists('pembelian_bahan');
        Schema::dropIfExists('harga_promo');
        Schema::dropIfExists('setting_harga');
        Schema::dropIfExists('bom_detail');
        Schema::dropIfExists('produk');
        Schema::dropIfExists('bahan_baku');
        Schema::dropIfExists('mesin');
        Schema::dropIfExists('supplier');
    }
};