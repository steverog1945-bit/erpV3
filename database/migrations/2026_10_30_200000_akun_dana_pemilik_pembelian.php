<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pembelian bahan dan pelunasan hutangnya dibayar pemilik di luar semua kas (bukan laci, bukan Kas Kecil, bukan
 * rekening yang dipantau admin). Tetap dicatat apa adanya, dengan lawan akun modal tersendiri supaya tidak ada kas
 * yang berkurang (tidak muncul saldo minus) dan pemilik bisa melihat total dana yang ia keluarkan untuk bahan.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('bag_akun')->insertOrIgnore([
            'kode_akun' => '3100', 'nama_akun' => 'Dana Pemilik untuk Pembelian Bahan', 'tipe_akun' => 'Modal', 'saldo_normal' => 'Kredit',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! DB::table('jurnal_umum')->where('kode_akun', '3100')->exists()) {
            DB::table('bag_akun')->where('kode_akun', '3100')->delete();
        }
    }
};
