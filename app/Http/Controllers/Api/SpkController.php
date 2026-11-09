<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SPK (Surat Perintah Kerja) - API Publik (read-only).
 *
 * GET /api/spk - Daftar item produksi (transaksi_detail) dengan status SPK.
 *                Filter: status_spk, id_transaksi.
 *
 * Status SPK: Antre | Proses | Selesai | Batal.
 */
class SpkController extends Controller
{
    public function index(Request $request)
    {
        $q = DB::table('transaksi_detail')
            ->join('transaksi', 'transaksi_detail.id_transaksi', '=', 'transaksi.id_transaksi')
            ->leftJoin('produk', 'transaksi_detail.id_produk', '=', 'produk.id_produk')
            ->select(
                'transaksi_detail.id_detail',
                'transaksi_detail.id_transaksi',
                'transaksi.nomor_nota',
                'transaksi_detail.nama_file',
                'produk.nama_produk',
                'transaksi_detail.qty',
                'transaksi_detail.status_spk',
                'transaksi_detail.status_urgent',
                'transaksi_detail.status_finishing',
                'transaksi_detail.catatan_produksi',
                'transaksi_detail.updated_at'
            )
            ->orderByDesc('transaksi_detail.updated_at');

        if ($request->filled('status_spk')) {
            $q->where('transaksi_detail.status_spk', $request->string('status_spk'));
        }
        if ($request->filled('id_transaksi')) {
            $q->where('transaksi_detail.id_transaksi', $request->integer('id_transaksi'));
        }

        $limit = min(200, max(1, $request->integer('limit', 100)));
        $rows = $q->limit($limit)->get();

        return response()->json([
            'status' => 'success',
            'data'   => $rows,
        ]);
    }
}
