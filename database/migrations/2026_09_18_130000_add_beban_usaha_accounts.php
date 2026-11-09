<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rincian akun Beban Usaha - sebelumnya cuma ada 2 akun Beban (5000 HPP,
 * 5100 Biaya Lain-lain), jadi Laporan Laba Rugi tidak bisa menampilkan
 * rincian kategori beban usaha (ATK, listrik, marketing, dst) seperti
 * lazimnya laporan laba rugi bisnis nyata. 10 akun baru ditambahkan,
 * mengikuti daftar kategori yang diminta user (kode 5110-5200, sengaja
 * di atas 5100 yang sudah dipakai "Biaya Lain-lain" supaya tidak
 * bentrok dan tetap terurut rapi saat kode_akun diurutkan ASC).
 */
return new class extends Migration
{
    public function up(): void
    {
        $akunBaru = [
            ['kode_akun' => '5110', 'nama_akun' => 'Beban Administrasi & Umum'],
            ['kode_akun' => '5120', 'nama_akun' => 'Beban BPJS'],
            ['kode_akun' => '5130', 'nama_akun' => 'Beban Internet'],
            ['kode_akun' => '5140', 'nama_akun' => 'Beban Listrik & Telepon'],
            ['kode_akun' => '5150', 'nama_akun' => 'Beban PDAM'],
            ['kode_akun' => '5160', 'nama_akun' => 'Beban Perlengkapan Kantor'],
            ['kode_akun' => '5170', 'nama_akun' => 'Beban Perlengkapan Toko'],
            ['kode_akun' => '5180', 'nama_akun' => 'Biaya Marketing'],
            ['kode_akun' => '5190', 'nama_akun' => 'Biaya Ongkos Kirim & Ekspedisi'],
            ['kode_akun' => '5200', 'nama_akun' => 'Biaya Operasional Kantor'],
        ];

        foreach ($akunBaru as $akun) {
            if (!DB::table('bag_akun')->where('kode_akun', $akun['kode_akun'])->exists()) {
                DB::table('bag_akun')->insert([
                    'kode_akun'    => $akun['kode_akun'],
                    'nama_akun'    => $akun['nama_akun'],
                    'tipe_akun'    => 'Beban',
                    'saldo_normal' => 'Debet',
                    'is_active'    => 1,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $kode = ['5110', '5120', '5130', '5140', '5150', '5160', '5170', '5180', '5190', '5200'];
        // Tidak dihapus kalau sudah dipakai jurnal - selaras dengan proteksi
        // yang sama di AkunController::destroy().
        $dipakai = DB::table('jurnal_umum')->whereIn('kode_akun', $kode)->pluck('kode_akun')->unique();
        DB::table('bag_akun')->whereIn('kode_akun', $kode)->whereNotIn('kode_akun', $dipakai)->delete();
    }
};
