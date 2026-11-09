<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Selisih stok dari stock opname dan penyesuaian manual (bahan rusak, salah hitung) dinilai harga rata-rata dan
 * dijurnal ke akun ini lawan Persediaan, supaya nilai Persediaan di neraca selalu sama dengan stok fisik.
 * Kekurangan = beban (debet), kelebihan = pengurang beban (kredit).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('bag_akun')->insertOrIgnore([
            'kode_akun' => '5230', 'nama_akun' => 'Selisih Persediaan (Opname & Penyesuaian)', 'tipe_akun' => 'Beban', 'saldo_normal' => 'Debet',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! DB::table('jurnal_umum')->where('kode_akun', '5230')->exists()) {
            DB::table('bag_akun')->where('kode_akun', '5230')->delete();
        }
    }
};
