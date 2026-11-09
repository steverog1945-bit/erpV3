<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kas Kecil adalah pembukuan terpisah yang tidak menyentuh laci kasir. Sumber uangnya: saldo awal, pembayaran
 * order jersey, dan setoran pemilik. Kategori "Isi dari Kas Laci" (akun 1000) dihapus bila belum pernah dipakai;
 * bila sudah ada catatannya, kategori dibiarkan untuk riwayat tetapi tidak bisa dipakai lagi (dicegah di controller).
 */
return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('kategori_kas_kecil')->where('kode_akun', '1000')->pluck('id_kategori_kas_kecil');
        foreach ($ids as $id) {
            if (! DB::table('kas_kecil')->where('id_kategori', $id)->exists()) {
                DB::table('kategori_kas_kecil')->where('id_kategori_kas_kecil', $id)->delete();
            }
        }
    }

    public function down(): void
    {
        if (! DB::table('kategori_kas_kecil')->where('nama_kategori', 'Isi dari Kas Laci')->exists()) {
            DB::table('kategori_kas_kecil')->insert([
                'nama_kategori' => 'Isi dari Kas Laci', 'jenis' => 'Masuk', 'kode_akun' => '1000', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
};
