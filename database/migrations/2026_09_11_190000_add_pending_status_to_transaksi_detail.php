<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sebelumnya "Batal" di Produksi adalah status akhir mati - begitu SPK
 * dibatalkan tidak ada jalan balik selain koreksi manual langsung ke
 * database. Ditambahkan 'Pending' supaya operator yang membatalkan
 * produksi punya pilihan ketiga: bukan otomatis balik ke antrian (Antre)
 * dan bukan juga final batal, tapi "diparkir" dulu menunggu keputusan
 * (mis. nunggu konfirmasi ke konsumen). Item Pending tidak muncul di
 * antrian aktif SPK (Antre) maupun Produksi (Desain/Cetak) - baru
 * diselesaikan lewat halaman SPK (lihat SpkController::resolvePending)
 * jadi Antre lagi atau Batal permanen.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE transaksi_detail MODIFY status_spk ENUM('Antre','Desain','Cetak','Selesai','Batal','Pending') NOT NULL DEFAULT 'Antre'");
    }

    public function down(): void
    {
        // Turunkan 'Pending' yang mungkin sudah terlanjur dipakai ke 'Antre'
        // dulu sebelum enum-nya dipersempit lagi - MySQL akan menolak/
        // mengosongkan diam-diam nilai enum yang tidak lagi valid kalau ini
        // dilewati.
        DB::table('transaksi_detail')->where('status_spk', 'Pending')->update(['status_spk' => 'Antre']);
        DB::statement("ALTER TABLE transaksi_detail MODIFY status_spk ENUM('Antre','Desain','Cetak','Selesai','Batal') NOT NULL DEFAULT 'Antre'");
    }
};
