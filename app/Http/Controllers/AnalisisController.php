<?php

namespace App\Http\Controllers;

use App\Services\DashboardEksekutifService as Analisis;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Dashboard Eksekutif, Profitabilitas, dan Analisis Pemakaian Bahan
 * (LEVEL 4). Perhitungan ada di App\Services\DashboardEksekutifService.
 */
class AnalisisController extends Controller
{
    private function periode(Request $request): array
    {
        $dari = $request->filled('tgl_dari') ? Carbon::parse($request->tgl_dari) : now()->startOfMonth();
        $sampai = $request->filled('tgl_sampai') ? Carbon::parse($request->tgl_sampai) : now();

        if ($sampai->lt($dari)) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        return [$dari, $sampai];
    }

    public function dashboard(Request $request)
    {
        [$dari, $sampai] = $this->periode($request);

        return view('analisis.dashboard', [
            'dari' => $dari, 'sampai' => $sampai,
            'r' => Analisis::ringkasan($dari, $sampai),
            'tren' => Analisis::trenOmzet(6),
            'topPelanggan' => Analisis::topPelanggan($dari, $sampai),
            'topProduk' => Analisis::topProduk($dari, $sampai),
            'peringatan' => Analisis::peringatan(),
            'estimasi' => Analisis::estimasiBulanDepan(),
        ]);
    }

    public function profitabilitas(Request $request)
    {
        [$dari, $sampai] = $this->periode($request);
        $dimensi = in_array($request->dimensi, ['produk', 'pelanggan', 'mesin'], true) ? $request->dimensi : 'produk';

        return view('analisis.profitabilitas', [
            'dari' => $dari, 'sampai' => $sampai, 'dimensi' => $dimensi,
            'data' => Analisis::profitabilitas($dimensi, $dari, $sampai),
        ]);
    }

    public function pemakaianBahan(Request $request)
    {
        [$dari, $sampai] = $this->periode($request);

        return view('analisis.pemakaian-bahan', ['dari' => $dari, 'sampai' => $sampai, 'data' => Analisis::pemakaianBahan($dari, $sampai)]);
    }
    public function biayaTinta(Request $request)
    {
        [$dari, $sampai] = $this->periode($request);

        return view('analisis.biaya-tinta', [
            'dari' => $dari, 'sampai' => $sampai,
            'h' => \App\Services\BiayaTintaService::hitung($dari, $sampai),
            'pengaturan' => \Illuminate\Support\Facades\DB::table('pemakaian_tinta as pt')
                ->join('mesin as m', 'm.id_mesin', '=', 'pt.id_mesin')
                ->join('bahan_baku as b', 'b.id_bahan', '=', 'pt.id_bahan')
                ->orderBy('m.nama_mesin')->orderBy('b.nama_bahan')
                ->get(['pt.*', 'm.nama_mesin', 'b.nama_bahan']),
            'mesin' => \Illuminate\Support\Facades\DB::table('mesin')->orderBy('nama_mesin')->get(),
            'bahanTinta' => \Illuminate\Support\Facades\DB::table('bahan_baku')->where('status', 'aktif')->orderBy('nama_bahan')->get(['id_bahan', 'nama_bahan', 'kode_bahan']),
        ]);
    }

    public function simpanPemakaianTinta(Request $request)
    {
        $data = $request->validate([
            'id_mesin' => 'required|exists:mesin,id_mesin',
            'id_bahan' => 'required|exists:bahan_baku,id_bahan',
            'dasar' => 'required|in:m2,lembar',
            'pemakaian' => 'required|numeric|min:0',
            'keterangan' => 'nullable|string|max:255',
        ]);

        \Illuminate\Support\Facades\DB::table('pemakaian_tinta')->updateOrInsert(
            ['id_mesin' => $data['id_mesin'], 'id_bahan' => $data['id_bahan'], 'dasar' => $data['dasar']],
            ['pemakaian' => $data['pemakaian'], 'keterangan' => $data['keterangan'] ?? null, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]
        );
        \App\Services\AuditLogger::log('updated', 'pemakaian_tinta', null, null, $data, 'Pengaturan pemakaian tinta');

        return back()->with('success', 'Pengaturan pemakaian tinta disimpan.');
    }

    public function hapusPemakaianTinta(int $id)
    {
        $baris = \Illuminate\Support\Facades\DB::table('pemakaian_tinta')->where('id', $id)->first();
        abort_if(!$baris, 404);
        \Illuminate\Support\Facades\DB::table('pemakaian_tinta')->where('id', $id)->delete();
        \App\Services\AuditLogger::log('deleted', 'pemakaian_tinta', $id, (array) $baris, null, 'Hapus pengaturan pemakaian tinta');

        return back()->with('success', 'Pengaturan dihapus.');
    }
}
