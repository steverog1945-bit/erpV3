<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SatuanSeeder extends Seeder
{
    public function run()
    {
        DB::table('satuan')->insert([
    ['nama_satuan' => 'cm', 'nilai_konversi' => 1, 'tipe_satuan' => 'Panjang'],
    ['nama_satuan' => 'm', 'nilai_konversi' => 100, 'tipe_satuan' => 'Panjang'],
    ['nama_satuan' => 'gram', 'nilai_konversi' => 1, 'tipe_satuan' => 'Berat'],
    ['nama_satuan' => 'kg', 'nilai_konversi' => 1000, 'tipe_satuan' => 'Berat'],
    ['nama_satuan' => 'roll', 'nilai_konversi' => 1, 'tipe_satuan' => 'Barang'],
    ['nama_satuan' => 'lembar', 'nilai_konversi' => 1, 'tipe_satuan' => 'Barang'],
    ['nama_satuan' => 'pcs', 'nilai_konversi' => 1, 'tipe_satuan' => 'Barang'],
]);
        foreach($satuan as $s) {
            DB::table('satuan')->updateOrInsert(
                ['nama_satuan' => $s['nama_satuan']],
                ['nilai_konversi' => $s['nilai_konversi']]
            );
        }
    }
}