<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Services\AccountingPeriodService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Administrator-only: tutup/buka periode akuntansi (roadmap LEVEL 1).
 * Penegakan sebenarnya ada di AccountingService::postJournal() lewat
 * AccountingPeriodService::isClosed() - controller ini cuma UI untuk
 * mengubah status baris accounting_periods.
 */
class AccountingPeriodController extends Controller
{
    public function index()
    {
        $sekarang = Carbon::now();
        $daftarPeriode = [];

        // 12 bulan terakhir (termasuk bulan berjalan) - cukup untuk operasi
        // rutin tutup buku bulanan. Periode tanpa baris di DB dianggap Open
        // (lihat AccountingPeriodService::isClosed()).
        for ($i = 0; $i < 12; $i++) {
            $bulanKe = $sekarang->copy()->subMonths($i);
            $existing = AccountingPeriod::where('tahun', $bulanKe->year)->where('bulan', $bulanKe->month)->first();

            $daftarPeriode[] = [
                'tahun' => $bulanKe->year,
                'bulan' => $bulanKe->month,
                'nama' => $bulanKe->translatedFormat('F Y'),
                'status' => $existing->status ?? 'Open',
                'closed_at' => $existing->closed_at ?? null,
                'catatan' => $existing->catatan ?? null,
            ];
        }

        return view('accounting-period.index', compact('daftarPeriode'));
    }

    public function tutup(Request $request)
    {
        $data = $request->validate([
            'tahun' => 'required|integer|min:2000|max:2100',
            'bulan' => 'required|integer|min:1|max:12',
            'catatan' => 'nullable|string|max:255',
        ]);

        // Pemeriksaan sebelum tutup buku. Menutup bulan yang masih berjalan menghentikan seluruh transaksi
        // hari itu; menutup bulan yang kasnya belum direkonsiliasi atau jurnalnya timpang mengunci kesalahan.
        $awal = \Carbon\Carbon::create($data['tahun'], $data['bulan'], 1)->startOfDay();
        $akhir = $awal->copy()->endOfMonth();
        if ($akhir->isFuture()) {
            return back()->with('error', 'Periode ' . $awal->locale('id')->translatedFormat('F Y') . ' belum berakhir. Periode baru bisa ditutup setelah bulannya lewat.');
        }

        $kasTerbuka = \Illuminate\Support\Facades\DB::table('kas_harian')->where('status_lock', 'open')
            ->where('tgl_kas', '<=', $akhir->toDateString())->count();
        if ($kasTerbuka > 0) {
            return back()->with('error', "Masih ada {$kasTerbuka} sesi kas dari periode ini yang belum ditutup. Lakukan closing kas dulu.");
        }

        $saldo = \Illuminate\Support\Facades\DB::table('jurnal_umum')->whereBetween('tgl_jurnal', [$awal->toDateString(), $akhir->toDateString()])
            ->selectRaw('COALESCE(SUM(debet), 0) as d, COALESCE(SUM(kredit), 0) as k')->first();
        if (abs((float) $saldo->d - (float) $saldo->k) > 0.01) {
            return back()->with('error', 'Jurnal periode ini tidak seimbang (debet ' . number_format((float) $saldo->d, 2, ',', '.') . ', kredit ' . number_format((float) $saldo->k, 2, ',', '.') . '). Periksa dulu sebelum menutup.');
        }

        AccountingPeriodService::tutup($data['tahun'], $data['bulan'], auth()->id(), $data['catatan'] ?? null);

        return back()->with('success', "Periode {$data['bulan']}/{$data['tahun']} berhasil ditutup. Jurnal baru bertanggal periode ini akan ditolak.");
    }

    public function buka(Request $request)
    {
        $data = $request->validate([
            'tahun' => 'required|integer|min:2000|max:2100',
            'bulan' => 'required|integer|min:1|max:12',
        ]);

        AccountingPeriodService::buka($data['tahun'], $data['bulan']);

        return back()->with('success', "Periode {$data['bulan']}/{$data['tahun']} dibuka kembali.");
    }
}
