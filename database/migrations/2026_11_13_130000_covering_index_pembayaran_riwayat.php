<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Perf dataset besar (uji beban 756 ribu baris sintetis, 2026-11-13):
 * agregat terbayar per nota `SELECT id_transaksi, SUM(nominal) FROM
 * pembayaran_riwayat GROUP BY id_transaksi` dijalankan hampir setiap buka
 * Monitoring Kasir, Dashboard (piutang), dan Laporan Order. Dengan indeks
 * FK tunggal (id_transaksi) saja, tiap halaman memaksa MySQL membaca
 * seluruh baris pembayaran (baris lebar). Indeks covering (id_transaksi,
 * nominal) membuat GROUP BY itu jadi index-only scan - angka laporan tidak
 * berubah sama sekali, hanya sumber baca yang dipindah ke indeks.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempoten: tabel ini bersejarah dibuat manual di luar migration
        // (lihat komentar 2026_09_10_055000), jadi dijaga eksplisit.
        $ada = collect(DB::select('SHOW INDEX FROM pembayaran_riwayat'))
            ->contains(fn ($i) => $i->Key_name === 'pembayaran_riwayat_cover');

        if (! $ada) {
            Schema::table('pembayaran_riwayat', function ($table) {
                $table->index(['id_transaksi', 'nominal'], 'pembayaran_riwayat_cover');
            });
        }
    }

    public function down(): void
    {
        Schema::table('pembayaran_riwayat', function ($table) {
            $table->dropIndex('pembayaran_riwayat_cover');
        });
    }
};
