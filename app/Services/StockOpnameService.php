<?php

namespace App\Services;

use App\Models\StockOpname;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Stock Opname (roadmap LEVEL 1 - WAJIB). Perbedaan dengan penyesuaian
 * manual satu-item yang sudah ada di KartuStokController::storeAdjustment
 * (langsung mengubah stok tanpa approval): modul ini menahan seluruh hasil
 * hitung fisik (bisa banyak bahan sekaligus) sebagai Draft -> Diajukan,
 * stok TIDAK berubah sama sekali sampai pengajuan disetujui lewat generic
 * ApprovalService yang sudah ada (tipe_aksi 'stock_opname').
 */
class StockOpnameService
{
    public static function eksekusiAdjustment(int $idStockOpname): void
    {
        DB::transaction(function () use ($idStockOpname) {
            $opname = StockOpname::whereKey($idStockOpname)->lockForUpdate()->firstOrFail();

            if ($opname->status !== 'Diajukan') {
                throw new \RuntimeException("Stock Opname {$opname->nomor} tidak dalam status Diajukan (status saat ini: {$opname->status}).");
            }

            $details = $opname->details()->whereNotNull('stok_fisik')->get();

            foreach ($details as $detail) {
                $stokFisik = (float) $detail->stok_fisik;

                // Re-baca stok TERKINI (bukan snapshot stok_sistem saat
                // opname dibuat) di dalam lockForUpdate - supaya mutasi
                // stok lain (penjualan, kedatangan bahan, dst) yang terjadi
                // SELAMA opname masih Diajukan tidak ikut tertimpa begitu
                // saja. Fisik yang sudah dihitung tetap dianggap nilai
                // benar (prinsip stock opname: hasil hitung fisik adalah
                // ground truth), tapi kalau ternyata sudah sama persis
                // dengan stok terkini, tidak perlu bikin baris kartu_stok
                // kosong.
                $bahan = DB::table('bahan_baku')->where('id_bahan', $detail->id_bahan)->lockForUpdate()->first();
                if (!$bahan) {
                    continue;
                }

                $stokSebelum = (float) $bahan->stok;
                if (abs($stokFisik - $stokSebelum) < 0.0001) {
                    continue;
                }

                DB::table('bahan_baku')->where('id_bahan', $detail->id_bahan)->update(['stok' => $stokFisik]);

                DB::table('kartu_stok')->insert([
                    'id_bahan'     => $detail->id_bahan,
                    'tanggal'      => now(),
                    'tipe'         => 'ADJUSTMENT',
                    'qty'          => abs($stokFisik - $stokSebelum),
                    'stok_sebelum' => $stokSebelum,
                    'stok_sesudah' => $stokFisik,
                    'referensi'    => $opname->nomor,
                    'keterangan'   => 'Stock Opname ' . $opname->nomor . ($detail->alasan ? ': ' . $detail->alasan : ''),
                    'created_by'   => Auth::id(),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                // Nilai Persediaan di jurnal ikut stok fisik (selisih x harga rata-rata).
                AccountingService::postSelisihPersediaan(
                    $opname->nomor,
                    "Selisih stock opname {$opname->nomor} - {$bahan->nama_bahan}",
                    ($stokFisik - $stokSebelum) * (float) $bahan->harga_rata_rata
                );
            }

            $opname->update(['status' => 'Disetujui']);
        });
    }

    public static function eksekusiPenolakan(int $idStockOpname): void
    {
        $opname = StockOpname::whereKey($idStockOpname)->lockForUpdate()->firstOrFail();

        if ($opname->status !== 'Diajukan') {
            throw new \RuntimeException("Stock Opname {$opname->nomor} tidak dalam status Diajukan (status saat ini: {$opname->status}).");
        }

        $opname->update(['status' => 'Ditolak']);
    }
}
