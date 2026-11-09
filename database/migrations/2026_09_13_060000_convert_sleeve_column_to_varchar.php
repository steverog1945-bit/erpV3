<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * jersey_order_items.sleeve masih ENUM('Pendek','Panjang') tetap - begitu
 * admin menambah Tipe Lengan baru lewat Master Data (mis. "3/4 Lengan"),
 * menyimpan snapshot nama itu ke kolom sleeve akan GAGAL (bukan salah satu
 * nilai enum yang diizinkan). Diubah jadi varchar biasa supaya snapshot
 * nama Tipe Lengan apa pun (bebas ditambah admin) selalu bisa tersimpan -
 * nilai data yang sudah ada ('Pendek'/'Panjang') tidak berubah sama sekali,
 * cuma batasan tipe kolomnya yang dilonggarkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('jersey_order_items', 'sleeve')) {
            DB::statement("ALTER TABLE jersey_order_items MODIFY sleeve VARCHAR(50) NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('jersey_order_items', 'sleeve')) {
            DB::statement("ALTER TABLE jersey_order_items MODIFY sleeve ENUM('Pendek','Panjang') NOT NULL DEFAULT 'Pendek'");
        }
    }
};
