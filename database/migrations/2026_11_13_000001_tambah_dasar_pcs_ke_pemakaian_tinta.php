<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tinta mesin printer bisa diatur per m² (produk ukuran, mis. banner)
 * atau per pcs (produk quantity tanpa ukuran, mis. lanyard 20mm yang
 * dicetak 24x110cm di atas sublime). Sebelumnya enum dasar hanya
 * menampung 'm2' dan 'lembar', sehingga pemakaian_tinta hanya bisa hidup
 * di dua basis itu. Ditambahkan 'pcs'. Additive, tidak mengubah nilai
 * yang ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $kolom = DB::selectOne("SELECT column_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'pemakaian_tinta' AND column_name = 'dasar'");
        $tipe = $kolom?->column_type ?? '';
        if (!str_contains($tipe, 'pcs')) {
            DB::statement("ALTER TABLE pemakaian_tinta MODIFY dasar ENUM('m2','lembar','pcs') NOT NULL DEFAULT 'm2'");
        }
    }

    public function down(): void
    {
        // Tidak diturunkan kembali: baris dengan dasar 'pcs' (bila ada) akan
        // tidak sesuai enum lama, dan DROP enum value merusak data. Biarkan
        // nilai penambahan permanen, persis seperti nilai enum lain di app ini.
    }
};
