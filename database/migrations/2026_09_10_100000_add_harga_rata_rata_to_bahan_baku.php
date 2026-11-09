<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * bahan_baku tidak pernah punya kolom biaya apa pun - tidak ada dasar untuk
 * menghitung HPP dari BOM. harga_rata_rata (moving average cost) diisi dari
 * histori pembelian_bahan_detail yang sudah ada, lalu dipelihara berjalan
 * oleh PembelianBahanController::store() setiap ada pembelian baru (lihat
 * migrasi berikutnya untuk pemakaiannya di transaksi_detail).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bahan_baku', function (Blueprint $table) {
            $table->decimal('harga_rata_rata', 15, 2)->default(0)->after('nilai_konversi');
        });

        // Backfill dari histori pembelian yang sudah ada: rata-rata tertimbang
        // (weighted average) per bahan dari seluruh baris pembelian_bahan_detail.
        $riwayat = DB::table('pembelian_bahan_detail')
            ->select('id_bahan', DB::raw('SUM(qty * harga) as total_nilai'), DB::raw('SUM(qty) as total_qty'))
            ->groupBy('id_bahan')
            ->havingRaw('SUM(qty) > 0')
            ->get();

        foreach ($riwayat as $row) {
            DB::table('bahan_baku')
                ->where('id_bahan', $row->id_bahan)
                ->update(['harga_rata_rata' => round($row->total_nilai / $row->total_qty, 2)]);
        }
    }

    public function down(): void
    {
        Schema::table('bahan_baku', function (Blueprint $table) {
            $table->dropColumn('harga_rata_rata');
        });
    }
};
