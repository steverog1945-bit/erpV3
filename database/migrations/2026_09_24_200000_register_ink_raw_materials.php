<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mendaftarkan bahan baku TINTA/TONER per mesin (sebelumnya belum ada sama
 * sekali sehingga biaya tinta tidak pernah masuk perhitungan). Hanya
 * menambah yang belum ada (berdasarkan kode_bahan), stok 0 dan harga kosong -
 * isi stok lewat Kedatangan Bahan/Stok Awal dan harga lewat Pembelian atau
 * tombol harga di Master Bahan Baku. Acuan pemakaian tinta per m2 ada di
 * docs/ACUAN_PEMAKAIAN_TINTA.md (dasar perhitungan laporan tinta nanti).
 * Dilewati bila mesin/kategori/satuan yang dirujuk tidak ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $satuan = fn (string $n) => DB::table('satuan')->whereRaw('LOWER(nama_satuan) = ?', [strtolower($n)])->value('id_satuan');
        $mesin = fn (string $n) => DB::table('mesin')->where('nama_mesin', $n)->value('id_mesin');
        $kategori = fn (string $n) => DB::table('kategori')->where('nama_kategori', $n)->value('id_kategori');

        // [kode, nama, mesin, kategori, satuan stok, satuan hitung, 1 stok = N hitung, stok minimal]
        $daftar = [
            ['TNT-S80670', 'TINTA ECO-SOLVENT EPSON S80670 (CMYK)', 'EPSON S80670', 'INDOOR', 'liter', 'ml', 1000, 2],
            ['TNT-FLORA', 'TINTA SOLVENT FLORA OUTDOOR (CMYK)', 'FLORA OUTDOOR', 'OUTDOOR', 'liter', 'ml', 1000, 4],
            ['TNR-KONICA', 'TONER KONICA MINOLTA (SET CMYK)', 'KONICA MINOLTA', 'A3', 'pcs', 'pcs', 1, 1],
            ['TNT-CENTURY', 'TINTA DIRECT PRINT CENTURY STAR', 'CENTURY STAR', 'CENTURY STAR', 'liter', 'ml', 1000, 2],
            ['TNT-DTF-CMYK', 'TINTA DTF CMYK', 'RHINO DTF', 'RHINO DTF', 'liter', 'ml', 1000, 2],
            ['TNT-DTF-WHT', 'TINTA DTF WHITE', 'RHINO DTF', 'RHINO DTF', 'liter', 'ml', 1000, 2],
            ['PWD-DTF', 'POWDER DTF (HOT MELT)', 'RHINO DTF', 'RHINO DTF', 'kg', 'gram', 1000, 2],
            ['TNT-SUBLIME', 'TINTA SUBLIME (CMYK)', 'ESPON F7270', 'SUBLIME', 'liter', 'ml', 1000, 2],
        ];

        foreach ($daftar as [$kode, $nama, $namaMesin, $namaKategori, $satStok, $satHitung, $konversi, $stokMin]) {
            if (DB::table('bahan_baku')->where('kode_bahan', $kode)->exists()) {
                continue;
            }

            $idMesin = $mesin($namaMesin);
            $idKategori = $kategori($namaKategori);
            $idSatStok = $satuan($satStok);
            $idSatHitung = $satuan($satHitung);
            if (!$idMesin || !$idKategori || !$idSatStok || !$idSatHitung) {
                continue;
            }

            DB::table('bahan_baku')->insert([
                'kode_bahan' => $kode, 'nama_bahan' => $nama, 'id_mesin' => $idMesin, 'id_kategori' => $idKategori,
                'id_satuan_stok' => $idSatStok, 'id_satuan_hitung' => $idSatHitung, 'nilai_konversi' => $konversi,
                'stok' => 0, 'stok_minimal' => $stokMin, 'satuan_stok' => $satStok, 'harga_rata_rata' => 0,
                'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Data master - tidak dihapus saat rollback.
    }
};
