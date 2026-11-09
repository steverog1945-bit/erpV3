<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JASA JAHIT BORONGAN - PENGELUARAN
 *
 * Permintaan: fitur untuk mencatat pengeluaran jahit yang memakai
 * harga borongan. Setiap jenis jahit punya harga hardcode yang bisa
 * berbeda satu sama lain, dan data yang diambil berdasarkan orderan
 * yang sedang membutuhkan jahit.
 *
 * DUA TABEL, dipisah karena satu adalah katalog dan satu adalah transaksi:
 *
 * 1. jasa_jahit - KATALOG jenis jahit + harga borongannya.
 *    "Harga hardcode" di sini dimaknai sebagai: harga borongan yang
 *    disepakati dengan penjahit, bukan harga yang dihitung dari bahan.
 *    Karena itu harga ini disimpan langsung di katalog, bukan diturunkan
 *    dari harga bahan atau dari margin produk.
 *
 * 2. jasa_jahit_catat - CATATAN pengeluaran. Setiap baris menautkan satu
 *    jenis jahit ke satu baris order yang dijahit, lengkap dengan qty,
 *    tanggal, dan harga snapshot.
 *
 * kenapa harga di-SNAPSHOT, bukan diambil dari katalog saat laporan
 * ------------------------------------------------------------------
 * Kalau harga borongan dinaikkan tahun depan, catatan tahun lalu tidak
 * boleh ikut berubah. Kalau laporan diambil dari harga katalog sekarang,
 * catatan lama akan terlihat memakai harga baru - dan angka yang
 * dibayar penjahit tahun lalu berubah retroactive tanpa ada yang
 * menyentuhnya. Itu kesalahan akuntansi. Jadi harga saat pencatatan
 * disimpan apa adanya.
 *
 * id_finishing sebagai penanda "orderan ini butuh jahit"
 * -------------------------------------------------------
 * Katalog jenis jahit menunjuk finishing mana yang berarti "baris
 * order ini perlu dijahit". Cara ini dipakai, bukan pencocokan teks
 * nama (mis. nama LIKE '%jahit%'), karena nama finishing bebas diisi
 * user: bisa "Jahit Keliling", "Jahit Tepi Banner", atau "OB Jahitan".
 * Pencocokan teks akan:
 *   - lupa baris yang namanya agak berbeda
 *   - dan sebaliknya, salah menandai yang bukan jahit
 *
 * Panggil idempoten: aman dijalankan ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------ 1. katalog jenis jahit
        if (! Schema::hasTable('jasa_jahit')) {
            Schema::create('jasa_jahit', function (Blueprint $table) {
                $table->id('id_jasa_jahit');
                $table->string('nama_jahit', 100)->unique();
                $table->foreignId('id_satuan')->nullable()
                    ->constrained('satuan', 'id_satuan')->nullOnDelete();

                // Finishing yang menandai baris order perlu dijahit.
                // Nullable: jenis jahit boleh ada sebelum finishing-nya
                // dibuat - dan ada jenis jahit yang bukan finishing sama
                // sekali (mis. jahit manual yang tidak masuk daftar).
                $table->foreignId('id_finishing')->nullable()
                    ->constrained('finishing', 'id_finishing')->nullOnDelete();

                // Harga borongan: harga yang disepakati dengan penjahit
                // per satu satuan. Ini angka inti fitur ini.
                $table->decimal('harga_borongan', 15, 2)->default(0);

                $table->text('keterangan')->nullable();
                $table->timestamps();

                $table->index('id_finishing');
            });
        }

        // ------------------------------------------------ 2. catatan pengeluaran
        if (! Schema::hasTable('jasa_jahit_catat')) {
            Schema::create('jasa_jahit_catat', function (Blueprint $table) {
                $table->id('id_jasa_jahit_catat');
                $table->foreignId('id_jasa_jahit')->constrained('jasa_jahit', 'id_jasa_jahit')->cascadeOnDelete();

                // Baris order yang dijahit. NullOnDelete, bukan cascade:
                // kalau nota dihapus (mis. salah input), catatan pengeluaran
                // TIDAK ikut hilang - uang yang sudah keluar ke penjahit
                // tetap sudah keluar, dan kalau catatannya ikut terhapus,
                // pengeluaran jadi tidak bisa diaudit.
                $table->foreignId('id_transaksi_detail')->nullable()
                    ->constrained('transaksi_detail', 'id_detail')->nullOnDelete();

                $table->decimal('qty', 14, 4)->default(1);

                // Harga yang benar-benar dibayar waktu itu, dibekukan.
                // Lihat penjelasan panjang di docblock.
                $table->decimal('harga_snapshot', 15, 2)->default(0);

                $table->decimal('total', 15, 2)->default(0);
                $table->date('tgl_catat');
                $table->text('catatan')->nullable();
                $table->foreignId('id_user')->nullable()->constrained('users', 'id_user')->nullOnDelete();
                $table->timestamps();

                $table->index(['id_transaksi_detail', 'id_jasa_jahit']);
                $table->index('tgl_catat');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jasa_jahit_catat');
        Schema::dropIfExists('jasa_jahit');
    }
};
