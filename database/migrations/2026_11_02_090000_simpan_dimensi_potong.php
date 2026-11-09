<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simpan dimensi potong finishing LINEAR_GRID (Potong Manual).
 *
 * Latar belakang: PenjualanController sudah menghitung lebar_potong dan
 * tinggi_potong, memakainya untuk menghitung biaya lewat
 * HargaService::hitungBiayaFinishingLinearGrid(), lalu menaruhnya di array
 * $daftarFinishing (baris 329-330) dengan komentar "Dimensi potong disimpan
 * supaya nota/SPK bisa menampilkan dan menghitung ulang Potong Manual apa
 * adanya di kemudian hari". Tapi INSERT di baris 501-505 hanya menulis
 * id_detail, id_finishing, status_finishing, qty_pakai, biaya, dan catatan -
 * kedua dimensi itu TIDAK pernah masuk database.
 *
 * Akibatnya: hanya kolom `biaya` yang bertahan. Dimensi media-nya sendiri
 * tersedia di transaksi_detail.p_ukuran / l_ukuran / qty_cetak, tapi satu
 * sisi rumus - ukuran keping yang dipotong - hilang. Biaya Potong Manual
 * yang sudah tersimpan jadi angka yang tidak bisa ditelusuri ulang, bukan
 * hanya saat nota dibuka ulang tetapi juga saat harga tarif finishing diubah
 * atau baris finishing diganti di SPK.
 *
 * Migration ini menambah dua kolom nullable. Nullable, bukan default 0,
 * karena finishing non-LINEAR_GRID memang tidak punya dimensi potong, dan
 * membedakannya dari "LINEAR_GRID dengan dimensi 0" itu penting: dimensi 0
 * membuat server menolak dengan HTTP 422, sedangkan NULL berarti mode ini
 * tidak berlaku. Semua baris lama tetap NULL dan tidak ada data yang berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_detail_finishing', function (Blueprint $t) {
            if (!Schema::hasColumn('transaksi_detail_finishing', 'lebar_potong')) {
                $t->decimal('lebar_potong', 10, 2)->nullable()->after('qty_pakai');
            }

            if (!Schema::hasColumn('transaksi_detail_finishing', 'tinggi_potong')) {
                $t->decimal('tinggi_potong', 10, 2)->nullable()->after('lebar_potong');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_detail_finishing', function (Blueprint $t) {
            if (Schema::hasColumn('transaksi_detail_finishing', 'tinggi_potong')) {
                $t->dropColumn('tinggi_potong');
            }

            if (Schema::hasColumn('transaksi_detail_finishing', 'lebar_potong')) {
                $t->dropColumn('lebar_potong');
            }
        });
    }
};