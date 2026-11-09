<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        // 1. Tabel Golongan Konsumen (Dibutuhkan oleh tabel Konsumen)
        if (!Schema::hasTable('golongan_konsumen')) {
            Schema::create('golongan_konsumen', function (Blueprint $table) {
                $table->id('id_golongan');
                $table->string('nama_golongan', 50);
            });
        }

        // 2. Tabel Konsumen
        if (!Schema::hasTable('konsumen')) {
            Schema::create('konsumen', function (Blueprint $table) {
                $table->id('id_konsumen');
                $table->string('nama_konsumen', 100);
                $table->string('telepon', 20);
                $table->text('alamat')->nullable();
                $table->unsignedBigInteger('id_golongan');
                $table->enum('tipe_konsumen', ['permanent', 'temporary'])->default('temporary');
                $table->timestamp('tgl_registrasi')->useCurrent();
                $table->foreign('id_golongan')->references('id_golongan')->on('golongan_konsumen');
            });
        }

        // 3. Tabel Setting Harga & Promo
        if (!Schema::hasTable('setting_harga')) {
            Schema::create('setting_harga', function (Blueprint $table) {
                $table->id('id_harga');
                $table->unsignedBigInteger('id_produk');
                $table->unsignedBigInteger('id_golongan');
                $table->integer('range_min');
                $table->integer('range_max');
                $table->decimal('harga_jual', 15, 2);
                $table->foreign('id_produk')->references('id_produk')->on('produk');
                $table->foreign('id_golongan')->references('id_golongan')->on('golongan_konsumen');
            });
        }

        if (!Schema::hasTable('harga_promo')) {
            Schema::create('harga_promo', function (Blueprint $table) {
                $table->id('id_promo');
                $table->unsignedBigInteger('id_produk');
                $table->decimal('harga_promo', 15, 2);
                $table->date('tgl_mulai');
                $table->date('tgl_selesai');
                $table->enum('status_promo', ['aktif', 'non-aktif'])->default('aktif');
                $table->foreign('id_produk')->references('id_produk')->on('produk');
            });
        }

        // 4. Tabel Transaksi Utama
        if (!Schema::hasTable('transaksi')) {
            Schema::create('transaksi', function (Blueprint $table) {
                $table->id('id_transaksi');
                $table->string('nomor_nota', 50)->unique();
                $table->unsignedBigInteger('id_konsumen');
                $table->unsignedBigInteger('id_user_kasir');
                $table->timestamp('tgl_order')->useCurrent();
                $table->decimal('total_bayar', 15, 2);
                $table->enum('status_pembayaran', ['Lunas', 'DP', 'Belum Bayar']);
                $table->foreign('id_konsumen')->references('id_konsumen')->on('konsumen');
                $table->foreign('id_user_kasir')->references('id_user')->on('users');
            });
        }

        // 5. Tabel Detail Transaksi (SPK)
        if (!Schema::hasTable('transaksi_detail')) {
            Schema::create('transaksi_detail', function (Blueprint $table) {
                $table->id('id_detail');
                $table->unsignedBigInteger('id_transaksi');
                $table->unsignedBigInteger('id_produk');
                $table->string('nama_file');
                $table->decimal('p_ukuran', 10, 2);
                $table->decimal('l_ukuran', 10, 2);
                $table->integer('qty');
                $table->decimal('subtotal', 15, 2);
                $table->enum('status_spk', ['Antre', 'Desain', 'Cetak', 'Selesai'])->default('Antre');
                $table->foreign('id_transaksi')->references('id_transaksi')->on('transaksi')->onDelete('cascade');
                $table->foreign('id_produk')->references('id_produk')->on('produk');
            });
        }

        // 6. Tabel Finansial & Jurnal
        if (!Schema::hasTable('supplier')) {
            Schema::create('supplier', function (Blueprint $table) {
                $table->id('id_supplier');
                $table->string('nama_supplier');
                $table->text('alamat')->nullable();
                $table->string('kontak');
            });
        }

        if (!Schema::hasTable('pembelian_bahan')) {
            Schema::create('pembelian_bahan', function (Blueprint $table) {
                $table->id('id_pembelian');
                $table->string('no_faktur_supplier')->unique();
                $table->unsignedBigInteger('id_supplier');
                $table->date('tgl_datang');
                $table->decimal('total_akhir', 15, 2);
                $table->enum('status_bayar', ['Lunas', 'Hutang'])->default('Hutang');
                $table->foreign('id_supplier')->references('id_supplier')->on('supplier');
            });
        }

        if (!Schema::hasTable('jurnal_umum')) {
            Schema::create('jurnal_umum', function (Blueprint $table) {
                $table->id('id_jurnal');
                $table->date('tgl_jurnal');
                $table->string('no_referensi');
                $table->string('keterangan');
                $table->string('kode_akun');
                $table->decimal('debet', 15, 2)->default(0);
                $table->decimal('kredit', 15, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down() {
        // Down method dikosongkan agar tidak menghapus tabel saat rollback jika ragu
    }
};