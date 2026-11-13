<?php

namespace App\Http\Controllers;

use App\Services\QcService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * QC & Rework (LEVEL 2). Lihat App\Services\QcService untuk aturan bisnisnya.
 */
class QcController extends Controller
{
    public function index()
    {
        $inspeksi = DB::table('qc_inspeksi as q')
            ->join('transaksi_detail as d', 'd.id_detail', '=', 'q.id_detail')
            ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->leftJoin('users as u', 'u.id_user', '=', 'q.id_user')
            ->select('q.*', 't.nomor_nota', 'd.nama_file', 'u.username')
            ->orderByDesc('q.id')
            ->paginate(15, ['*'], 'qc_page');

        $rework = DB::table('rework as r')
            ->join('transaksi_detail as d', 'd.id_detail', '=', 'r.id_detail')
            ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->select('r.*', 't.nomor_nota', 'd.nama_file')
            ->orderByRaw("FIELD(r.status, 'Dibuka', 'Selesai')")
            ->orderByDesc('r.id')
            ->paginate(15, ['*'], 'rw_page');

        return view('qc.index', compact('inspeksi', 'rework'));
    }

    public function create()
    {
        $items = DB::table('transaksi_detail as d')
            ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->where('t.status_nota', 'Aktif')
            // Item JASA (id_produk NULL) dikecualikan: tidak punya barang
            // fisik untuk diperiksa, dan statusnya 'Selesai' sejak nota dibuat.
            ->whereNotNull('d.id_produk')
            ->whereIn('d.status_spk', ['Cetak', 'Selesai'])
            ->orderByDesc('d.id_detail')
            ->limit(300)
            ->get(['d.id_detail', 'd.nama_file', 'd.qty', 'd.qty_cetak', 't.nomor_nota']);

        return view('qc.create', ['items' => $items, 'checklist' => QcService::CHECKLIST]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id_detail' => 'required|integer',
            'qty_diperiksa' => 'required|integer|min:1',
            'qty_reject' => 'required|integer|min:0',
            'catatan' => 'nullable|string|max:1000',
            'checklist' => 'nullable|array',
        ]);

        try {
            $hasil = QcService::catat(
                (int) $data['id_detail'], (int) $data['qty_diperiksa'], (int) $data['qty_reject'],
                array_map(fn ($v) => (bool) $v, $data['checklist'] ?? []), $data['catatan'] ?? null
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', \App\Support\PesanError::untuk($e));
        }

        $pesan = "QC {$hasil['nomor']} tersimpan.";
        if ($hasil['id_rework']) {
            $pesan .= " Ada reject - Rework dibuka ({$data['qty_reject']} pcs).";
        }

        return redirect()->route('qc.index')->with('success', $pesan);
    }

    public function prosesRework(int $id)
    {
        try {
            $biaya = QcService::prosesRework($id);
        } catch (\RuntimeException $e) {
            return back()->with('error', \App\Support\PesanError::untuk($e));
        }

        $kembali = back()->with('success', 'Rework selesai diproses. Biaya material Rp ' . number_format($biaya, 0, ',', '.') . ' dicatat ke stok & jurnal.');
        $minus = QcService::reworkStokMinus($id);

        return $minus ? $kembali->with('warning', 'Stok ' . implode('; ', $minus) . '. Segera restock atau lakukan stock opname.') : $kembali;
    }

    public function tutupRework(Request $request, int $id)
    {
        $data = $request->validate(['alasan' => 'required|string|max:500'], ['alasan.required' => 'Alasan menutup rework wajib diisi.']);

        try {
            QcService::tutupRework($id, $data['alasan']);
        } catch (\RuntimeException $e) {
            return back()->with('error', \App\Support\PesanError::untuk($e));
        }

        return back()->with('success', 'Rework ditutup tanpa cetak ulang.');
    }
}
