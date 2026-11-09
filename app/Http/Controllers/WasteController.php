<?php

namespace App\Http\Controllers;

use App\Services\WasteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Waste Management (LEVEL 2). Aturan bisnis ada di App\Services\WasteService.
 */
class WasteController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('waste_log as w')
            ->join('bahan_baku as b', 'b.id_bahan', '=', 'w.id_bahan')
            ->leftJoin('users as u', 'u.id_user', '=', 'w.id_user')
            ->select('w.*', 'b.nama_bahan', 'b.satuan_stok', 'u.username')
            ->orderByDesc('w.id');

        foreach (['jenis', 'status'] as $f) {
            if ($request->filled($f)) {
                $query->where("w.$f", $request->$f);
            }
        }

        $daftar = $query->paginate(20)->withQueryString();

        $awalBulan = now()->startOfMonth()->toDateString();
        $ringkasan = DB::table('waste_log')
            ->where('status', 'Diterapkan')->where('tanggal', '>=', $awalBulan)
            ->selectRaw("jenis, COUNT(*) as jumlah, COALESCE(SUM(biaya), 0) as biaya")
            ->groupBy('jenis')->get()->keyBy('jenis');

        return view('waste.index', compact('daftar', 'ringkasan'));
    }

    /**
     * Mesin/divisi yang berlaku untuk pengguna ini: operator hanya divisinya sendiri; Administrator (atau yang belum
     * ditugaskan) boleh semua atau memilih satu divisi lewat ?divisi=. null = semua divisi.
     */
    private function mesinBerlaku(Request $request)
    {
        $diizinkan = \App\Services\DivisiService::mesinDiizinkan($request->user());
        if ($diizinkan !== null) {
            return $diizinkan;
        }

        return $request->filled('divisi') ? collect([(int) $request->divisi]) : null;
    }

    public function create(Request $request)
    {
        $mesin = $this->mesinBerlaku($request);
        $terbatas = \App\Services\DivisiService::mesinDiizinkan($request->user()) !== null;

        // Item order: hanya cetakan yang sedang masuk di divisi ini (sedang dicetak, atau selesai 2 hari terakhir).
        $items = DB::table('transaksi_detail as d')->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->join('produk as p', 'p.id_produk', '=', 'd.id_produk')
            ->where('t.status_nota', 'Aktif')
            ->where(function ($q) {
                $q->where('d.status_spk', 'Cetak')->orWhere(fn ($w) => $w->where('d.status_spk', 'Selesai')->where('t.updated_at', '>=', now()->subDays(2)));
            })
            ->when($mesin, fn ($q) => $q->whereIn('p.id_mesin', $mesin))
            ->orderByDesc('d.id_detail')->limit(300)->get(['d.id_detail', 'd.nama_file', 't.nomor_nota', 'p.nama_produk']);

        // Bahan: hanya yang terkait mesin/divisinya.
        $bahanList = DB::table('bahan_baku as b')->leftJoin('satuan as sh', 'sh.id_satuan', '=', 'b.id_satuan_hitung')
            ->where('b.status', 'aktif')
            ->when($mesin, fn ($q) => $q->whereIn('b.id_mesin', $mesin))
            ->orderBy('b.nama_bahan')
            ->get(['b.id_bahan', 'b.nama_bahan', 'b.satuan_stok', 'b.stok', 'b.nilai_konversi', 'b.id_satuan_hitung', 'b.id_satuan_stok', 'sh.nama_satuan as satuan_hitung']);

        return view('waste.create', [
            'items' => $items,
            'bahanList' => $bahanList,
            'terbatas' => $terbatas,
            'divisiAktif' => $mesin ? DB::table('mesin')->whereIn('id_mesin', $mesin)->pluck('nama_mesin')->implode(', ') : null,
            'daftarMesin' => DB::table('mesin')->where('tampil_divisi', true)->orderBy('nama_mesin')->get(['id_mesin', 'nama_mesin']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id_bahan' => 'required|exists:bahan_baku,id_bahan',
            'qty' => 'nullable|numeric|min:0',
            'panjang' => 'nullable|numeric|min:0',
            'lebar' => 'nullable|numeric|min:0',
            'jumlah' => 'nullable|numeric|min:0',
            'jenis' => 'required|in:Normal,Abnormal',
            'alasan' => 'required|string|max:255',
            'id_detail' => 'nullable|exists:transaksi_detail,id_detail',
        ]);

        // Operator hanya boleh mencatat waste bahan & item order dari divisinya sendiri (server menjaga, bukan hanya tampilan).
        $diizinkan = \App\Services\DivisiService::mesinDiizinkan($request->user());
        if ($diizinkan !== null) {
            $mesinBahan = (int) DB::table('bahan_baku')->where('id_bahan', $data['id_bahan'])->value('id_mesin');
            if (!$diizinkan->contains($mesinBahan)) {
                return back()->withInput()->with('error', 'Bahan ini bukan bagian dari divisi Anda.');
            }
            if ($request->filled('id_detail')) {
                $mesinItem = DB::table('transaksi_detail as d')->join('produk as p', 'p.id_produk', '=', 'd.id_produk')
                    ->where('d.id_detail', $request->id_detail)->value('p.id_mesin');
                if (!$diizinkan->contains((int) $mesinItem)) {
                    return back()->withInput()->with('error', 'Item order ini bukan dari divisi Anda.');
                }
            }
        }

        // Qty waste diisi menurut SATUAN HITUNG bahan (bukan satuan stok):
        //  - satuan luas (m2/cm2): isi Panjang x Lebar (cm) x jumlah -> luas -> dibagi nilai_konversi = satuan stok
        //  - satuan lain (pcs, lembar, meter, dst): isi jumlahnya dalam satuan hitung -> dibagi nilai_konversi
        //  - bahan tanpa satuan hitung/konversi: isi langsung dalam satuan stok
        // Hanya sebesar hasil konversi itu yang dikurangkan (bukan satu roll/dus penuh).
        $bahan = DB::table('bahan_baku as b')->leftJoin('satuan as sh', 'sh.id_satuan', '=', 'b.id_satuan_hitung')
            ->where('b.id_bahan', $data['id_bahan'])->first(['b.*', 'sh.nama_satuan as satuan_hitung']);
        $mode = \App\Services\WasteService::modeInput($bahan);
        $konversi = (float) $bahan->nilai_konversi;
        $ket = '';

        if ($mode === 'luas') {
            $pjg = (float) $request->input('panjang');
            $lbr = (float) $request->input('lebar');
            $jml = max(1.0, (float) $request->input('jumlah', 1));
            if ($pjg <= 0 || $lbr <= 0) {
                return back()->withInput()->with('error', 'Isi Panjang dan Lebar bagian yang rusak (cm).');
            }
            $luas = strtolower((string) $bahan->satuan_hitung) === 'cm2' ? $pjg * $lbr * $jml : ($pjg * $lbr / 10000) * $jml;
            $data['qty'] = $luas / $konversi;
            $ket = ' [' . angka($pjg, 2) . ' x ' . angka($lbr, 2) . ' cm x ' . angka($jml, 2) . ' = ' . angka($luas, 4) . ' ' . $bahan->satuan_hitung . ']';
        } elseif ($mode === 'hitung') {
            $q = (float) $request->input('qty');
            if ($q <= 0) {
                return back()->withInput()->with('error', 'Isi jumlah waste dalam ' . $bahan->satuan_hitung . '.');
            }
            $data['qty'] = $q / $konversi;
            $ket = ' [' . angka($q, 4) . ' ' . $bahan->satuan_hitung . ']';
        } else {
            if ((float) $request->input('qty') <= 0) {
                return back()->withInput()->with('error', 'Isi jumlah waste.');
            }
        }
        $data['alasan'] = mb_substr($data['alasan'] . $ket, 0, 255);
        $data['qty'] = round((float) $data['qty'], 6);
        if ($data['qty'] <= 0) {
            return back()->withInput()->with('error', 'Qty waste terlalu kecil.');
        }

        try {
            $hasil = WasteService::catat((int) $data['id_bahan'], (float) $data['qty'], $data['jenis'], $data['alasan'], $request->filled('id_detail') ? (int) $data['id_detail'] : null);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', \App\Support\PesanError::untuk($e));
        }

        return redirect()->route('waste.index')->with('success', $hasil['status'] === 'Diterapkan'
            ? "Waste {$hasil['nomor']} dicatat: stok dikurangi & biaya dijurnal."
            : "Waste abnormal {$hasil['nomor']} diajukan - stok berkurang setelah disetujui Administrator.");
    }
}
