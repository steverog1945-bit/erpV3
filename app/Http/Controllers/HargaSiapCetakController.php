<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Harga File Siap Cetak per produk (tier x golongan), terpisah dari Price List biasa. Satu produk diatur sekaligus lewat
 * grid: baris = rentang qty/ukuran, kolom = golongan konsumen. Menyimpan = mengganti seluruh tier produk itu.
 */
class HargaSiapCetakController extends Controller
{
    public function index(Request $request)
    {
        $q = DB::table('produk')->leftJoin('mesin', 'mesin.id_mesin', '=', 'produk.id_mesin')
            ->where('produk.status', 'aktif')
            ->select('produk.id_produk', 'produk.nama_produk', 'produk.metode_hitung', 'mesin.nama_mesin')
            ->selectRaw('(SELECT COUNT(*) FROM harga_siap_cetak h WHERE h.id_produk = produk.id_produk) as jumlah_tier')
            ->when($request->filled('cari'), fn ($x) => $x->where('produk.nama_produk', 'like', '%' . $request->cari . '%'))
            ->when($request->filled('mesin'), fn ($x) => $x->where('produk.id_mesin', (int) $request->mesin))
            ->when($request->status === 'sudah', fn ($x) => $x->whereRaw('EXISTS (SELECT 1 FROM harga_siap_cetak h WHERE h.id_produk = produk.id_produk)'))
            ->when($request->status === 'belum', fn ($x) => $x->whereRaw('NOT EXISTS (SELECT 1 FROM harga_siap_cetak h WHERE h.id_produk = produk.id_produk)'))
            ->orderBy('mesin.nama_mesin')->orderBy('produk.nama_produk');

        $produk = $q->paginate(20)->withQueryString();
        $mesinList = DB::table('mesin')->orderBy('nama_mesin')->get(['id_mesin', 'nama_mesin']);
        $totalSudah = DB::table('harga_siap_cetak')->distinct()->count('id_produk');

        return view('master.harga_siap_cetak.index', compact('produk', 'mesinList', 'totalSudah'));
    }

    /** Data grid satu produk (untuk modal). */
    public function tampil(int $idProduk)
    {
        $produk = DB::table('produk')->where('id_produk', $idProduk)->firstOrFail();
        $golongan = DB::table('golongan_konsumen')->orderBy('id_golongan')->get(['id_golongan', 'nama_golongan']);
        $baris = DB::table('harga_siap_cetak')->where('id_produk', $idProduk)->orderBy('range_min')->get();

        $tier = $baris->groupBy(fn ($b) => (float) $b->range_min . '|' . ($b->range_max === null ? '' : (float) $b->range_max))->map(function ($grup) {
            $f = $grup->first();

            return ['min' => (float) $f->range_min, 'max' => $f->range_max === null ? null : (float) $f->range_max, 'harga' => $grup->pluck('harga_jual', 'id_golongan')->map(fn ($v) => (float) $v)];
        })->sortBy('min')->values();

        return response()->json([
            'produk' => $produk->nama_produk, 'satuan' => $produk->metode_hitung === 'ukuran' ? 'm²' : 'pcs',
            'golongan' => $golongan, 'tier' => $tier,
        ]);
    }

    /** Ganti seluruh tier produk. Payload: tier[] = {min, max|null, harga: {id_golongan: angka}}. */
    public function simpan(Request $request, int $idProduk)
    {
        DB::table('produk')->where('id_produk', $idProduk)->firstOrFail();
        $data = $request->validate([
            'tier' => 'nullable|array|max:20',
            'tier.*.min' => 'required|numeric|min:0',
            'tier.*.max' => 'nullable|numeric|gt:tier.*.min',
            'tier.*.harga' => 'nullable|array',
            'tier.*.harga.*' => 'nullable|numeric|min:0',
        ]);

        $tier = collect($data['tier'] ?? [])->sortBy(fn ($t) => (float) $t['min'])->values();
        // Rentang tidak boleh saling tumpang tindih (harga jadi ambigu).
        foreach ($tier as $i => $t) {
            if ($i > 0) {
                $sebelum = $tier[$i - 1];
                if ($sebelum['max'] === null || $sebelum['max'] === '' || (float) $t['min'] <= (float) $sebelum['max']) {
                    return response()->json(['message' => 'Rentang saling tumpang tindih atau rentang tanpa batas atas tidak berada di baris terakhir.'], 422);
                }
            }
        }

        $idGolonganSah = DB::table('golongan_konsumen')->pluck('id_golongan')->map(fn ($v) => (int) $v)->all();
        $rows = [];
        foreach ($tier as $t) {
            foreach (($t['harga'] ?? []) as $idGol => $harga) {
                if ($harga === null || $harga === '' || (float) $harga <= 0 || !in_array((int) $idGol, $idGolonganSah, true)) {
                    continue;
                }
                $rows[] = [
                    'id_produk' => $idProduk, 'id_golongan' => (int) $idGol, 'range_min' => (float) $t['min'],
                    'range_max' => ($t['max'] ?? null) === null || $t['max'] === '' ? null : (float) $t['max'], 'harga_jual' => (float) $harga,
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
        }

        DB::transaction(function () use ($idProduk, $rows) {
            $lama = DB::table('harga_siap_cetak')->where('id_produk', $idProduk)->get()->toArray();
            DB::table('harga_siap_cetak')->where('id_produk', $idProduk)->delete();
            if ($rows) {
                DB::table('harga_siap_cetak')->insert($rows);
            }
            // Hardening P0-3: lihat catatan yang sama di ProdukController/SettingHargaController.
            AuditLogger::log('updated', 'harga_siap_cetak', $idProduk, ['tier' => $lama], ['tier' => $rows], 'Harga File Siap Cetak produk #' . $idProduk . ' disimpan ulang');
        });

        return response()->json(['status' => 'ok', 'jumlah_tier' => collect($rows)->unique(fn ($r) => $r['range_min'] . '|' . $r['range_max'])->count()]);
    }
}
