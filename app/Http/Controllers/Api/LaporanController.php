<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Laporan - API Publik (read-only).
 *
 * GET /api/laporan/penjualan?dari=&sampai= - Ringkasan penjualan per hari
 * GET /api/laporan/piutang                 - Daftar nota belum lunas
 * GET /api/laporan/kas?dari=&sampai=       - Arus kas (kas harian)
 *
 * Semua angka dihitung ulang dari transaksi, bukan snapshot.
 */
class LaporanController extends Controller
{
    public function penjualan(Request $request)
    {
        $dari = $request->input('dari', now()->startOfMonth()->format('Y-m-d'));
        $sampai = $request->input('sampai', now()->format('Y-m-d'));

        $rows = DB::table('transaksi')
            ->where('status_nota', '!=', 'Dibatalkan')
            ->whereBetween('tgl_order', ["{$dari} 00:00:00", "{$sampai} 23:59:59"])
            ->selectRaw('DATE(tgl_order) as tanggal')
            ->selectRaw('COUNT(*) as jumlah_nota')
            ->selectRaw('SUM(total_bayar) as nilai')
            ->groupByRaw('DATE(tgl_order)')
            ->orderBy('tanggal')
            ->get();

        return response()->json([
            'status' => 'success',
            'periode' => ['dari' => $dari, 'sampai' => $sampai],
            'total_nilai' => (float) $rows->sum('nilai'),
            'data' => $rows,
        ]);
    }

    public function piutang()
    {
        $rows = DB::table('transaksi')
            ->where('status_pembayaran', '!=', 'Lunas')
            ->where('status_nota', '!=', 'Dibatalkan')
            ->select(
                'transaksi.id_transaksi',
                'transaksi.nomor_nota',
                'transaksi.tgl_order',
                'transaksi.total_bayar',
                'transaksi.status_pembayaran',
                'konsumen.nama_konsumen'
            )
            ->leftJoin('konsumen', 'transaksi.id_konsumen', '=', 'konsumen.id_konsumen')
            ->selectRaw('(transaksi.total_bayar - COALESCE((SELECT SUM(pr.nominal) FROM pembayaran_riwayat pr WHERE pr.id_transaksi = transaksi.id_transaksi), 0)) as sisa')
            ->orderBy('transaksi.tgl_order')
            ->get();

        return response()->json([
            'status' => 'success',
            'total_piutang' => (float) $rows->sum('sisa'),
            'data' => $rows,
        ]);
    }

    public function kas(Request $request)
    {
        $dari = $request->input('dari', now()->startOfMonth()->format('Y-m-d'));
        $sampai = $request->input('sampai', now()->format('Y-m-d'));

        $rows = DB::table('kas_harian')
            ->whereBetween('tgl_kas', [$dari, $sampai])
            ->orderBy('tgl_kas')
            ->get();

        $totalMasuk = (float) $rows->sum(fn ($r) => $r->total_tunai_masuk + $r->total_transfer_masuk + $r->total_debit_masuk);
        $totalKeluar = (float) $rows->sum('total_tunai_keluar');

        return response()->json([
            'status' => 'success',
            'periode' => ['dari' => $dari, 'sampai' => $sampai],
            'total_masuk' => $totalMasuk,
            'total_keluar' => $totalKeluar,
            'data' => $rows,
        ]);
    }
}
