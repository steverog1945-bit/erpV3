<?php

namespace App\Http\Controllers;

use App\Models\StockOpname;
use App\Services\ApprovalService;
use App\Services\DocumentNumberService;
use App\Services\StockOpnameService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Stock Opname (roadmap LEVEL 1 - WAJIB). Alur: Draft (isi hasil hitung
 * fisik per bahan) -> Diajukan (lewat generic ApprovalService, tipe_aksi
 * 'stock_opname') -> Disetujui (baru stok benar-benar disesuaikan, lihat
 * App\Services\StockOpnameService::eksekusiAdjustment) / Ditolak.
 */
class StockOpnameController extends Controller
{
    public function index(Request $request)
    {
        $query = StockOpname::with('pembuat')->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $daftar = $query->paginate(20)->withQueryString();

        return view('stock-opname.index', compact('daftar'));
    }

    public function create()
    {
        $gudangList = DB::table('gudang')->orderBy('nama_gudang')->get();

        return view('stock-opname.create', compact('gudangList'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_gudang' => 'nullable|exists:gudang,id_gudang',
            'tanggal' => ['required', 'date', new \App\Rules\TanggalTransaksi],
            'catatan' => 'nullable|string|max:1000',
        ]);

        $opname = DB::transaction(function () use ($validated) {
            $opname = StockOpname::create([
                'nomor' => DocumentNumberService::generate('SO'),
                'id_gudang' => $validated['id_gudang'] ?? null,
                'tanggal' => $validated['tanggal'],
                'status' => 'Draft',
                'catatan' => $validated['catatan'] ?? null,
                'id_user_pembuat' => Auth::id(),
            ]);

            // Auto-isi baris untuk semua bahan aktif dengan snapshot stok
            // sistem saat ini - operator tinggal mengisi hasil hitung fisik
            // per baris, tidak perlu menambah bahan satu-satu (lihat bagian
            // 7 dokumen audit: alur stock opname yang dimaksud memang
            // menghitung seluruh stok gudang, bukan sebagian).
            $bahanAktif = DB::table('bahan_baku')->where('status', 'aktif')->get(['id_bahan', 'stok']);

            $rows = $bahanAktif->map(fn ($b) => [
                'id_stock_opname' => $opname->id,
                'id_bahan' => $b->id_bahan,
                'stok_sistem' => $b->stok,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            if (!empty($rows)) {
                DB::table('stock_opname_details')->insert($rows);
            }

            return $opname;
        });

        return redirect()->route('stock-opname.edit', $opname->id)->with('success', "Stock Opname {$opname->nomor} dibuat. Silakan isi hasil hitung fisik.");
    }

    public function edit(StockOpname $stockOpname)
    {
        if ($stockOpname->status !== 'Draft') {
            return redirect()->route('stock-opname.index')->with('error', 'Stock Opname ini sudah tidak berstatus Draft, tidak bisa diedit lagi.');
        }

        $details = $stockOpname->details()->with('bahan')->orderBy('id')->get();

        return view('stock-opname.edit', compact('stockOpname', 'details'));
    }

    public function updateDetails(Request $request, StockOpname $stockOpname)
    {
        if ($stockOpname->status !== 'Draft') {
            return back()->with('error', 'Stock Opname ini sudah tidak berstatus Draft.');
        }

        $validated = $request->validate([
            'detail' => 'required|array',
            'detail.*.stok_fisik' => 'nullable|numeric|min:0',
            'detail.*.alasan' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($validated, $stockOpname) {
            foreach ($validated['detail'] as $idDetail => $data) {
                DB::table('stock_opname_details')
                    ->where('id', $idDetail)
                    ->where('id_stock_opname', $stockOpname->id)
                    ->update([
                        'stok_fisik' => $data['stok_fisik'] ?? null,
                        'alasan' => $data['alasan'] ?? null,
                        'updated_at' => now(),
                    ]);
            }
        });

        return back()->with('success', 'Hasil hitung fisik tersimpan.');
    }

    public function ajukan(StockOpname $stockOpname)
    {
        if ($stockOpname->status !== 'Draft') {
            return back()->with('error', 'Stock Opname ini sudah tidak berstatus Draft.');
        }

        $details = $stockOpname->details()->whereNotNull('stok_fisik')->get();

        if ($details->isEmpty()) {
            return back()->with('error', 'Belum ada hasil hitung fisik yang diisi.');
        }

        $selisih = $details->filter(fn ($d) => abs($d->stok_fisik - $d->stok_sistem) >= 0.0001);
        $adaSelisihTanpaAlasan = $selisih->contains(fn ($d) => empty($d->alasan));

        if ($adaSelisihTanpaAlasan) {
            return back()->with('error', 'Semua bahan yang selisih hasil hitungnya harus diisi alasan sebelum diajukan.');
        }

        DB::transaction(function () use ($stockOpname, $selisih) {
            ApprovalService::ajukan(
                'stock_opname',
                'stock_opnames',
                $stockOpname->id,
                [],
                "Stock Opname {$stockOpname->nomor} ({$selisih->count()} bahan selisih)",
                "Hasil hitung fisik {$stockOpname->tanggal->translatedFormat('d M Y')}"
            );

            $stockOpname->update(['status' => 'Diajukan']);
        });

        return redirect()->route('stock-opname.index')->with('success', "Stock Opname {$stockOpname->nomor} diajukan untuk persetujuan.");
    }

    public function destroy(StockOpname $stockOpname)
    {
        if ($stockOpname->status !== 'Draft') {
            return back()->with('error', 'Cuma Stock Opname berstatus Draft yang bisa dihapus.');
        }

        $stockOpname->delete();

        return redirect()->route('stock-opname.index')->with('success', 'Stock Opname dihapus.');
    }
}
