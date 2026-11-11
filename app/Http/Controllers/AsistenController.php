<?php

namespace App\Http\Controllers;

use App\Services\AsistenLokalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint tanya-jawab Asisten Orion (lokal, dari data ERP).
 * Dipanggil halaman /premium/asisten; tidak ada panggilan ke internet.
 */
class AsistenController extends Controller
{
    public function tanya(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pesan' => ['required', 'string', 'max:500'],
        ]);

        $hasil = app(AsistenLokalService::class)->jawab($data['pesan'], $request->user());

        return response()->json($hasil);
    }
}
