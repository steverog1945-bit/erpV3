<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tier harga jersey sesuai spek owner 2026-11-09:
 *   Dewasa: Jersey 1-20=80.000, 21-50=78.000, 51-100=75.000
 *           Celana Pendek 1-50=30.000, 51-100=28.000
 *           Celana Panjang 1-50=65.000, 51-100=60.000
 *   Anak:   Jersey 1-20=60.000, 21-100=58.000
 *   Member (khusus Jersey): Dewasa 1-100=55.000; Anak 1-100=50.000
 * id_golongan = null (berlaku umum) kecuali tier member (id_golongan = Member Jersey).
 * Idempoten: tier yang sudah ada dengan kunci yang sama tidak diduplikasi.
 */
return new class extends Migration
{
    private function tambahTier(array $rows): void
    {
        foreach ($rows as $r) {
            $ada = DB::table('jersey_price_settings')
                ->where('jersey_product_type_id', $r['tipe'])
                ->whereNull('jersey_material_id')
                ->whereNull('jersey_category_id')
                ->where('kelompok', $r['kelompok'])
                ->where('qty_min', $r['qty_min'])
                ->where('qty_max', $r['qty_max'])
                ->where('id_golongan', $r['id_golongan'])
                ->exists();
            if ($ada) {
                continue;
            }
            DB::table('jersey_price_settings')->insert([
                'jersey_product_type_id' => $r['tipe'],
                'jersey_category_id' => null,
                'jersey_material_id' => null,
                'qty_min' => $r['qty_min'],
                'qty_max' => $r['qty_max'],
                'price' => $r['price'],
                'is_active' => 1,
                'kelompok' => $r['kelompok'],
                'id_golongan' => $r['id_golongan'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function up(): void
    {
        $idMember = (int) DB::table('golongan_konsumen')->where('nama_golongan', 'Member Jersey')->value('id_golongan');

        $umum = [];
        // Dewasa - Jersey
        foreach ([[1, 20, 80000], [21, 50, 78000], [51, 100, 75000]] as [$a, $b, $h]) {
            $umum[] = ['tipe' => 1, 'kelompok' => 'dewasa', 'qty_min' => $a, 'qty_max' => $b, 'price' => $h, 'id_golongan' => null];
        }
        // Dewasa - Celana Pendek
        foreach ([[1, 50, 30000], [51, 100, 28000]] as [$a, $b, $h]) {
            $umum[] = ['tipe' => 2, 'kelompok' => 'dewasa', 'qty_min' => $a, 'qty_max' => $b, 'price' => $h, 'id_golongan' => null];
        }
        // Dewasa - Celana Panjang
        foreach ([[1, 50, 65000], [51, 100, 60000]] as [$a, $b, $h]) {
            $umum[] = ['tipe' => 4, 'kelompok' => 'dewasa', 'qty_min' => $a, 'qty_max' => $b, 'price' => $h, 'id_golongan' => null];
        }
        // Anak - Jersey
        foreach ([[1, 20, 60000], [21, 100, 58000]] as [$a, $b, $h]) {
            $umum[] = ['tipe' => 1, 'kelompok' => 'anak', 'qty_min' => $a, 'qty_max' => $b, 'price' => $h, 'id_golongan' => null];
        }
        $this->tambahTier($umum);

        // Member (khusus Jersey)
        if ($idMember) {
            $member = [];
            $member[] = ['tipe' => 1, 'kelompok' => 'dewasa', 'qty_min' => 1, 'qty_max' => 100, 'price' => 55000, 'id_golongan' => $idMember];
            $member[] = ['tipe' => 1, 'kelompok' => 'anak', 'qty_min' => 1, 'qty_max' => 100, 'price' => 50000, 'id_golongan' => $idMember];
            $this->tambahTier($member);
        }
    }

    public function down(): void
    {
        // Tidak menghapus data (tier harga bisa dipakai transaksi lama).
    }
};
