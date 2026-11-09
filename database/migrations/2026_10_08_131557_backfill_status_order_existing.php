<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill status_order untuk baris transaksi yang sudah ada SEBELUM kolom
 * status_order diperkenalkan (default migration = 'Menunggu Kasir' untuk
 * semua baris lama).
 *
 * Mapping berdasarkan kondisi nyata, bukan pukul rata:
 * - status_nota = Dibatalkan            -> status_order = Dibatalkan
 * - sudah jalan (bayar sebagian/lunas, item keluar dari Antre,
 *   atau sudah diambil)                 -> status_order = Diterima Kasir
 * - sisanya (murni Belum Bayar + semua item masih Antre)
 *                                       -> tetap Menunggu Kasir
 *
 * Idempoten: hanya menyentuh baris yang masih 'Menunggu Kasir'.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasColumn('transaksi', 'status_order')) {
            return;
        }

        DB::table('transaksi')
            ->where('status_order', 'Menunggu Kasir')
            ->where('status_nota', 'Dibatalkan')
            ->update(['status_order' => 'Dibatalkan']);

        $idsJalan = DB::table('transaksi as t')
            ->where('t.status_order', 'Menunggu Kasir')
            ->where('t.status_nota', 'Aktif')
            ->where(function ($q) {
                $q->where('t.status_pembayaran', '!=', 'Belum Bayar')
                    ->orWhere('t.status_collecting', 'Sudah Diambil')
                    ->orWhereExists(function ($e) {
                        $e->select(DB::raw(1))->from('pembayaran_riwayat as p')
                            ->whereColumn('p.id_transaksi', 't.id_transaksi');
                    })
                    ->orWhereExists(function ($e) {
                        $e->select(DB::raw(1))->from('transaksi_detail as d')
                            ->whereColumn('d.id_transaksi', 't.id_transaksi')
                            ->where('d.status_spk', '!=', 'Antre');
                    });
            })
            ->pluck('t.id_transaksi');

        if ($idsJalan->isNotEmpty()) {
            DB::table('transaksi')->whereIn('id_transaksi', $idsJalan)->update(['status_order' => 'Diterima Kasir']);
        }
    }

    public function down(): void
    {
        // Backfill data tidak di-rollback (tidak ada cara aman mengembalikan
        // status semula) - kolomnya sendiri di-drop oleh migration pembuatnya.
    }
};
