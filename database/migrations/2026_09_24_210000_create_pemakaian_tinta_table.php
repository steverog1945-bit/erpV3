<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan pemakaian tinta/toner per mesin untuk Laporan Biaya Tinta:
 * berapa satuan hitung bahan (ml/gram/pcs) terpakai per m2 cetak atau per
 * lembar. Angka awal = acuan (docs/ACUAN_PEMAKAIAN_TINTA.md), bisa diubah
 * di halaman laporan. Murni dasar perhitungan laporan - tidak menyentuh
 * stok maupun HPP order.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pemakaian_tinta')) {
            Schema::create('pemakaian_tinta', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('id_mesin');
                $table->unsignedInteger('id_bahan');
                $table->enum('dasar', ['m2', 'lembar'])->default('m2');
                $table->decimal('pemakaian', 12, 6);
                $table->string('keterangan')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['id_mesin', 'id_bahan', 'dasar'], 'pemakaian_tinta_unik');
            });
        }

        if (app()->environment('testing') || DB::table('pemakaian_tinta')->exists()) {
            return;
        }

        // [nama mesin, kode bahan, dasar, pemakaian, keterangan]
        $awal = [
            ['EPSON S80670', 'TNT-S80670', 'm2', 10, 'ml per m2 (eco-solvent)'],
            ['FLORA OUTDOOR', 'TNT-FLORA', 'm2', 12, 'ml per m2 (solvent)'],
            ['CENTURY STAR', 'TNT-CENTURY', 'm2', 18, 'ml per m2 (direct print)'],
            ['RHINO DTF', 'TNT-DTF-CMYK', 'm2', 12, 'ml per m2 (CMYK)'],
            ['RHINO DTF', 'TNT-DTF-WHT', 'm2', 8, 'ml per m2 (white)'],
            ['RHINO DTF', 'PWD-DTF', 'm2', 25, 'gram per m2 (powder)'],
            ['ESPON F7270', 'TNT-SUBLIME', 'm2', 9, 'ml per m2 (sublime)'],
            ['KONICA MINOLTA', 'TNR-KONICA', 'lembar', 0.00005, 'pcs toner per lembar (asumsi 1 set = 20.000 lembar)'],
        ];

        foreach ($awal as [$namaMesin, $kodeBahan, $dasar, $pemakaian, $ket]) {
            $idMesin = DB::table('mesin')->where('nama_mesin', $namaMesin)->value('id_mesin');
            $idBahan = DB::table('bahan_baku')->where('kode_bahan', $kodeBahan)->value('id_bahan');
            if (!$idMesin || !$idBahan) {
                continue;
            }
            DB::table('pemakaian_tinta')->insert([
                'id_mesin' => $idMesin, 'id_bahan' => $idBahan, 'dasar' => $dasar, 'pemakaian' => $pemakaian,
                'keterangan' => $ket, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pemakaian_tinta');
    }
};
