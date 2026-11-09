<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\Izin;

/**
 * Pembayaran - API Publik (read-only).
 *
 * GET /api/pembayaran               - Daftar pembayaran (filter: id_transaksi)
 * GET /api/transaksi/{id}/pembayaran - Riwayat pembayaran satu nota
 *
 * Endpoint POST sengaja TIDAK disediakan di API. Pencatatan pembayaran
 * menyentuh jurnal, stok, dan status nota (lihat KasirController::
 * prosesBayar) dan harus lewat alur kasir web yang sudah diuji.
 */
class PembayaranController extends Controller
{
    public function index(Request $request)
    {
        $q = DB::table('pembayaran_riwayat')
            ->join('transaksi', 'pembayaran_riwayat.id_transaksi', '=', 'transaksi.id_transaksi')
            ->select(
                'pembayaran_riwayat.id_pembayaran',
                'pembayaran_riwayat.id_transaksi',
                'transaksi.nomor_nota',
                'pembayaran_riwayat.nominal',
                'pembayaran_riwayat.metode',
                'pembayaran_riwayat.id_bank',
                'pembayaran_riwayat.tgl_bayar',
                'pembayaran_riwayat.keterangan'
            )
            ->orderByDesc('pembayaran_riwayat.tgl_bayar')
            ->orderByDesc('pembayaran_riwayat.id_pembayaran');

        // Kasir hanya boleh melihat pembayaran atas nota miliknya.
        // Administrator boleh melihat seluruh nota untuk audit/pemulihan.
        if (!Izin::adalahAdmin($request->user())) {
            $q->where('transaksi.id_user_kasir', $request->user()->id_user);
        }

        if ($request->filled('id_transaksi')) {
            $q->where('pembayaran_riwayat.id_transaksi', $request->integer('id_transaksi'));
        }

        $limit = min(200, max(1, $request->integer('limit', 100)));
        $rows = $q->limit($limit)->get();

        return response()->json([
            'status' => 'success',
            'data'   => $rows,
        ]);
    }

    public function untukTransaksi(Request $request, $id)
    {
        $q = DB::table('transaksi')->where('id_transaksi', $id);

        // Endpoint ini harus memiliki proteksi kepemilikan setara
        // TransaksiController::show(), bukan sekadar pemeriksaan menu Kasir.
        if (!Izin::adalahAdmin($request->user())) {
            $q->where('id_user_kasir', $request->user()->id_user);
        }

        $transaksi = $q->first();
        if (!$transaksi) {
            return response()->json(['error' => 'Nota tidak ditemukan.'], 404);
        }

        $bayar = DB::table('pembayaran_riwayat')
            ->where('id_transaksi', $id)
            ->orderBy('tgl_bayar')
            ->orderBy('id_pembayaran')
            ->get();

        $totalBayar = (float) $bayar->sum('nominal');
        $sisa = max(0, (float) $transaksi->total_bayar - $totalBayar);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id_transaksi'    => (int) $transaksi->id_transaksi,
                'nomor_nota'      => $transaksi->nomor_nota,
                'total_bayar'     => (float) $transaksi->total_bayar,
                'total_dibayar'   => $totalBayar,
                'sisa_tagihan'    => $sisa,
                'status_pembayaran' => $transaksi->status_pembayaran,
                'riwayat'         => $bayar,
            ],
        ]);
    }
}
