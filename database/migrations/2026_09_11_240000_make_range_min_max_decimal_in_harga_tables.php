<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bug ditemukan saat audit Price List & Level: range_min/range_max di
 * setting_harga (dan finishing_harga/jasa_harga, pola sama persis)
 * tersimpan sebagai int(11) dengan validasi 'integer|min:1' di
 * controller-nya. Untuk produk metode_hitung='ukuran', satuan hitung tier
 * ini adalah LUAS DALAM M² (qtyHitung = p*l/10000*qty) - sebuah bilangan
 * PECAHAN (mis. stiker 30x30cm = 0.09 m²), bukan bilangan bulat.
 *
 * Karena kolom & validasinya integer-only dengan minimum 1, tier
 * TERKECIL yang bisa dibuat untuk produk 'ukuran' SELALU mulai dari
 * "1" (m²) - tidak mungkin membuat tier "0.01 s/d 0.99 m²". Akibatnya
 * order dengan luas di bawah 1 m² (order kecil, sangat umum di
 * percetakan - stiker, sample, dsb) TIDAK PERNAH cocok dengan tier
 * manapun di HargaService::getHargaEfektif() (range_min <= qty selalu
 * false karena qty pecahan < 1), dan order tersebut DITOLAK total
 * ("Harga belum diatur") - bukan ditagih lebih mahal, tapi customer
 * dengan order kecil sama sekali tidak bisa checkout. Sama-sama
 * merugikan customer seperti yang dikhawatirkan.
 *
 * decimal(10,2) dipilih (bukan lebih presisi) karena satuan terkecil
 * yang realistis di sini adalah cm² dibulatkan ke m² 2 desimal - cukup
 * untuk luas maupun qty pcs (bilangan bulat tetap valid sebagai kasus
 * khusus desimal .00).
 */
return new class extends Migration
{
    public function up(): void
    {
        // DB::statement (bukan Schema::table(...)->change()) supaya tidak
        // butuh paket doctrine/dbal yang tidak terpasang di project ini.
        foreach (['setting_harga', 'finishing_harga', 'jasa_harga'] as $tabel) {
            DB::statement("ALTER TABLE `{$tabel}` MODIFY `range_min` DECIMAL(10,2) NOT NULL DEFAULT 1");
            DB::statement("ALTER TABLE `{$tabel}` MODIFY `range_max` DECIMAL(10,2) NULL DEFAULT NULL");
        }
    }

    public function down(): void
    {
        foreach (['setting_harga', 'finishing_harga', 'jasa_harga'] as $tabel) {
            DB::statement("ALTER TABLE `{$tabel}` MODIFY `range_min` INT(11) NOT NULL DEFAULT 1");
            DB::statement("ALTER TABLE `{$tabel}` MODIFY `range_max` INT(11) NULL DEFAULT NULL");
        }
    }
};
