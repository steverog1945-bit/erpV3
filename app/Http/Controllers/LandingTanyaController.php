<?php

namespace App\Http\Controllers;

use App\Services\AsistenLandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint publik asisten chat di landing page. HANYA menjawab seputar
 * orderan (lihat AsistenLandingService) - omzet, laba, gaji, dan angka
 * internal lainnya ditolak di sana. Tidak butuh login; dibatasi laju.
 */
class LandingTanyaController extends Controller
{
    public function tanya(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pesan' => ['required', 'string', 'max:300'],
        ]);

        $hasil = app(AsistenLandingService::class)->jawab($data['pesan']);

        return response()->json($hasil);
    }
}
