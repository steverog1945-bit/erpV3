<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard KPI - API Publik.
 *
 * GET /api/dashboard/kpi - Ringkasan KPI untuk dashboard real-time.
 * Hanya data yang relevan dengan user yang login.
 */
class DashboardController extends Controller
{
    public function kpi(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Tidak terautentikasi.'], 401);
        }

        $today = now()->format('Y-m-d');
        [$mulaiHariIni, $akhirHariIni] = self::rentangHari($today);

        $query = DB::table('transaksi')->where('id_user_kasir', $user->id_user);

        $piutangRow = DB::table('transaksi')
            ->where('id_user_kasir', $user->id_user)
            ->where('status_pembayaran', '!=', 'Lunas')
            ->where('status_nota', '!=', 'Dibatalkan')
            ->selectRaw('COALESCE(SUM(GREATEST(0, total_bayar - COALESCE((SELECT SUM(pr.nominal) FROM pembayaran_riwayat pr WHERE pr.id_transaksi = transaksi.id_transaksi), 0))), 0) as total, COUNT(*) as jumlah')
            ->first();

        $orderHariIni = DB::table('transaksi')
            ->where('id_user_kasir', $user->id_user)
            ->whereBetween('tgl_order', [$mulaiHariIni, $akhirHariIni])
            ->where('status_nota', '!=', 'Dibatalkan')
            ->count();

        $nilaiOrderHariIni = (float) DB::table('transaksi')
            ->where('id_user_kasir', $user->id_user)
            ->whereBetween('tgl_order', [$mulaiHariIni, $akhirHariIni])
            ->where('status_nota', '!=', 'Dibatalkan')
            ->sum('total_bayar');

        $kasMasukHariIni = (float) DB::table('pembayaran_riwayat')
            ->whereBetween('tgl_bayar', [$mulaiHariIni, $akhirHariIni])
            ->where('metode', '!=', 'Deposit')
            ->sum('nominal');

        $statusSpk = DB::table('transaksi_detail')
            ->join('transaksi', 'transaksi_detail.id_transaksi', '=', 'transaksi.id_transaksi')
            ->where('transaksi.id_user_kasir', $user->id_user)
            ->select('status_spk', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('status_spk')
            ->pluck('jumlah', 'status_spk');

        $spkAntre = (int) ($statusSpk['Antre'] ?? 0);
        $spkProses = (int) (($statusSpk['Desain'] ?? 0) + ($statusSpk['Cetak'] ?? 0));
        $spkSelesai = (int) ($statusSpk['Selesai'] ?? 0);

        $urgentAktif = DB::table('transaksi_detail')
            ->join('transaksi', 'transaksi_detail.id_transaksi', '=', 'transaksi.id_transaksi')
            ->where('transaksi.id_user_kasir', $user->id_user)
            ->where('transaksi_detail.status_urgent', 1)
            ->whereNotIn('transaksi_detail.status_spk', ['Selesai', 'Batal'])
            ->count();

        $kasAktifSemua = \App\Services\KasSesiService::aktif();
        $kasAktif = $kasAktifSemua ? (bool) $kasAktifSemua : false;

        return response()->json([
            'status' => 'success',
            'data'   => [
                'piutang'             => (float) ($piutangRow->total ?? 0),
                'piutang_count'       => (int) ($piutangRow->jumlah ?? 0),
                'order_hari_ini'      => (int) $orderHariIni,
                'nilai_order_hari_ini'=> $nilaiOrderHariIni,
                'kas_masuk_hari_ini'  => $kasMasukHariIni,
                'spk_antre'           => $spkAntre,
                'spk_proses'          => $spkProses,
                'spk_selesai'         => $spkSelesai,
                'urgent_aktif'        => (int) $urgentAktif,
                'kas_aktif'           => $kasAktif,
                'updated_at'          => now()->toDateTimeString(),
            ],
        ]);
    }

    private static function rentangHari(string $tgl): array
    {
        return [$tgl . ' 00:00:00', $tgl . ' 23:59:59'];
    }
}
