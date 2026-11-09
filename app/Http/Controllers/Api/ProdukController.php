<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Master Produk - API Publik (read-only).
 *
 * GET /api/produk
 * GET /api/produk/{id}
 */
class ProdukController extends Controller
{
    public function index()
    {
        $rows = DB::table('produk')
            ->select('id_produk', 'nama_produk', 'metode_hitung', 'status', 'id_mesin', 'spk_lengkap')
            ->where('status', 'Aktif')
            ->orderBy('nama_produk')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $rows,
        ]);
    }

    public function show($id)
    {
        $row = DB::table('produk')->where('id_produk', $id)->first();

        if (!$row) {
            return response()->json(['error' => 'Produk tidak ditemukan.'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $row,
        ]);
    }
}
