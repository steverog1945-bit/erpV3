<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sinyal perubahan data super ringan untuk refresh otomatis di browser.
 * Hanya mengembalikan SIDIK JARI (hash jumlah baris + updated_at terbaru) -
 * bukan data apa pun - sehingga aman untuk semua role & murah di server
 * (hasil di-cache 5 detik, dipakai bersama semua tab/pengguna).
 */
class SinyalController extends Controller
{
    private const TABEL = [
        'transaksi', 'transaksi_detail', 'pengambilan', 'approval_requests', 'kas_harian',
        'jersey_orders', 'purchase_requests', 'purchase_orders', 'qc_inspeksi', 'waste_log',
        'rework', 'quotations', 'nota_edit_request',
    ];

    public function index()
    {
        $data = Cache::remember('sinyal_perubahan', 5, function () {
            $bagian = [];
            foreach (self::TABEL as $t) {
                try {
                    $r = DB::table($t)->selectRaw('COUNT(*) c, MAX(updated_at) m')->first();
                    $bagian[] = $t . ':' . $r->c . ':' . $r->m;
                } catch (\Throwable $e) {
                    // tabel/kolom belum ada - abaikan
                }
            }

            return [
                'v' => substr(sha1(implode('|', $bagian)), 0, 12),
                'approval' => (int) DB::table('approval_requests')->where('status', 'Menunggu')->count(),
            ];
        });

        $user = request()->user();
        $adm = optional($user->role)->nama_role === 'Administrator';

        return response()->json([
            'v' => $data['v'],
            'approval' => $adm ? $data['approval'] : null,
            'notif' => \App\Services\NotifikasiService::untuk($user),
        ]);
    }
}
