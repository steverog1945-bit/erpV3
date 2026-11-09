<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bahan terbuang (waste) dan bahan untuk cetak ulang (rework) sebelumnya dijurnal ke 5200, akun yang sama dengan
 * "Biaya Operasional Kantor" (kategori Kas Kecil yang dibayar pemilik), sehingga kerugian bahan tercampur dengan
 * biaya kantor di Laba Rugi. Disetujui pemilik 2026-10-02: akun terpisah.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('bag_akun')->insertOrIgnore([
            'kode_akun' => '5240', 'nama_akun' => 'Beban Waste & Rework', 'tipe_akun' => 'Beban', 'saldo_normal' => 'Debet',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! DB::table('jurnal_umum')->where('kode_akun', '5240')->exists()) {
            DB::table('bag_akun')->where('kode_akun', '5240')->delete();
        }
    }
};
