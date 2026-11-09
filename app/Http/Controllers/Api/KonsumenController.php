<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Master Konsumen - API Publik (read-only).
 *
 * GET /api/konsumen
 * GET /api/konsumen/{id}
 */
class KonsumenController extends Controller
{
    public function index()
    {
        $rows = DB::table('konsumen')
            ->select('id_konsumen', 'nama_konsumen', 'telepon', 'alamat', 'id_golongan', 'tipe_konsumen', 'tgl_registrasi')
            ->orderBy('nama_konsumen')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $rows,
        ]);
    }

    public function show($id)
    {
        $row = DB::table('konsumen')->where('id_konsumen', $id)->first();

        if (!$row) {
            return response()->json(['error' => 'Konsumen tidak ditemukan.'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $row,
        ]);
    }
}
