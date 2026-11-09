<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Stok Bahan Baku - API Publik (read-only).
 *
 * GET /api/stok              - Daftar stok bahan (filter: id_gudang, status, cari)
 * GET /api/stok/{id}/kartu   - Kartu stok (riwayat mutasi) satu bahan
 */
class StokController extends Controller
{
    public function index(Request $request)
    {
        $q = DB::table('bahan_baku')
            ->leftJoin('jenis_bahan', 'bahan_baku.id_jenis_bahan', '=', 'jenis_bahan.id_jenis_bahan')
            ->select(
                'bahan_baku.id_bahan',
                'bahan_baku.kode_bahan',
                'bahan_baku.nama_bahan',
                'bahan_baku.stok',
                'bahan_baku.stok_minimal',
                'bahan_baku.satuan_stok',
                'bahan_baku.status',
                'jenis_bahan.nama_jenis'
            )
            ->orderBy('bahan_baku.nama_bahan');

        if ($request->filled('status')) {
            $q->where('bahan_baku.status', $request->string('status'));
        }
        if ($request->filled('cari')) {
            $cari = $request->string('cari');
            $q->where(fn ($w) => $w->where('bahan_baku.nama_bahan', 'like', "%{$cari}%")
                ->orWhere('bahan_baku.kode_bahan', 'like', "%{$cari}%"));
        }

        $limit = min(500, max(1, $request->integer('limit', 200)));
        $rows = $q->limit($limit)->get();

        return response()->json([
            'status' => 'success',
            'data'   => $rows,
        ]);
    }

    public function kartu($id)
    {
        $bahan = DB::table('bahan_baku')->where('id_bahan', $id)->first();
        if (!$bahan) {
            return response()->json(['error' => 'Bahan tidak ditemukan.'], 404);
        }

        $mutasi = DB::table('kartu_stok')
            ->where('id_bahan', $id)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id_bahan'   => (int) $bahan->id_bahan,
                'nama_bahan' => $bahan->nama_bahan,
                'stok'       => (float) $bahan->stok,
                'mutasi'     => $mutasi,
            ],
        ]);
    }
}
