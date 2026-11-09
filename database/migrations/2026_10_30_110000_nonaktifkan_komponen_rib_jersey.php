<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Komponen estimasi "Rib" dinonaktifkan: rib adalah bahan tersendiri yang jarang dipakai, sedangkan kerah
 * jersey dipotong dari kain jerseynya sendiri (sudah dihitung di matriks Tipe Kerah). Dengan Rib aktif,
 * setiap order jersey ikut memunculkan kebutuhan rib di SPK. Order lama tidak diubah. Aktifkan kembali
 * dari Master Jersey > Komponen bila ada pesanan yang memang memakai rib.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jersey_components')) {
            DB::table('jersey_components')->where('name', 'Rib')->where('is_active', 1)->update(['is_active' => 0, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('jersey_components')) {
            DB::table('jersey_components')->where('name', 'Rib')->update(['is_active' => 1, 'updated_at' => now()]);
        }
    }
};
