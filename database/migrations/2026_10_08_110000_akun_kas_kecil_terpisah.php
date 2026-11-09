<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Kas Kecil dibukukan di akun sendiri (1005), terpisah dari Kas laci (1000). */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('bag_akun')->insertOrIgnore([
            'kode_akun' => '1005', 'nama_akun' => 'Kas Kecil', 'tipe_akun' => 'Aset', 'saldo_normal' => 'Debet', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('kategori_kas_kecil')->insertOrIgnore([
            'nama_kategori' => 'Isi dari Kas Laci', 'jenis' => 'Masuk', 'kode_akun' => '1000', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('kategori_kas_kecil')->where('nama_kategori', 'Isi dari Kas Laci')->delete();
        DB::table('bag_akun')->where('kode_akun', '1005')->delete();
    }
};
