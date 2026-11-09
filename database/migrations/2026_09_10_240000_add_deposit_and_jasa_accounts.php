<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 2 akun baru untuk 2 alur yang sedang dihubungkan ke jurnal_umum:
 * - 2100 Hutang Deposit Konsumen: saldo deposit yang sudah diterima tapi
 *   belum dipakai bayar nota (kewajiban - uang customer yang masih
 *   "dititipkan"), dipakai oleh postSetoranDeposit/postPembayaranDeposit.
 * - 4100 Pendapatan Jasa: pendapatan dari penjualan Jasa (dipisah dari
 *   4000 Pendapatan Penjualan produk fisik supaya laporan tidak
 *   mencampur dua jenis pendapatan yang berbeda sifat).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('bag_akun')->insert([
            ['kode_akun' => '2100', 'nama_akun' => 'Hutang Deposit Konsumen', 'tipe_akun' => 'Kewajiban',  'saldo_normal' => 'Kredit', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['kode_akun' => '4100', 'nama_akun' => 'Pendapatan Jasa',         'tipe_akun' => 'Pendapatan', 'saldo_normal' => 'Kredit', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('bag_akun')->whereIn('kode_akun', ['2100', '4100'])->delete();
    }
};
