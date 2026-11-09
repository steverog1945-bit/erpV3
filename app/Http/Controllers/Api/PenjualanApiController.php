<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HargaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class PenjualanApiController extends Controller
{
    public function getDetailProduk($id)
    {
        try {
            $produk = DB::table('produk')->where('id_produk', $id)->first();
            
            if (!$produk) {
                return response()->json(['message' => 'Produk tidak ditemukan'], 404);
            }

            return response()->json($produk);
        } catch (\Exception $e) {
            return response()->json(['error' => \App\Support\PesanError::untuk($e)], 500);
        }
    }

    public function getHarga(Request $request, $id_produk, $id_golongan, $qty)
    {
        try {
            $hasil = HargaService::getHargaEfektif((int) $id_produk, (int) $id_golongan, (float) $qty, $request->boolean('siap_cetak'));

            return response()->json($hasil);
        } catch (\Exception $e) {
            return response()->json(['error' => \App\Support\PesanError::untuk($e)], 500);
        }
    }

    /**
     * Harga acuan Finishing berdasarkan qty (finishing_harga tier, fallback
     * harga_default) - murni STARTING POINT untuk mengisi field biaya_finishing
     * di POS, kasir tetap bisa menimpa manual (lihat komentar di
     * PenjualanController: harga_default cuma starting point, bukan final).
     */
    public function getHargaFinishing($id_finishing, $qty)
    {
        try {
            $harga = HargaService::getHargaFinishingEfektif((int) $id_finishing, (float) $qty);

            return response()->json(['harga' => $harga]);
        } catch (\Exception $e) {
            return response()->json(['error' => \App\Support\PesanError::untuk($e)], 500);
        }
    }

    /**
     * Sama seperti getHargaFinishing tapi untuk Jasa - jasa sebelumnya tidak
     * punya harga tersimpan sama sekali, ini angka acuan baru, kasir tetap
     * menentukan harga akhir manual di POS.
     */
    public function getHargaJasa($id_jasa, $qty)
    {
        try {
            $harga = HargaService::getHargaJasaEfektif((int) $id_jasa, (float) $qty);

            return response()->json(['harga' => $harga]);
        } catch (\Exception $e) {
            return response()->json(['error' => \App\Support\PesanError::untuk($e)], 500);
        }
    }

    /**
     * Indikator ketersediaan stok bahan (BOM) SEBELUM checkout - murni
     * informasi untuk kasir, TIDAK memblokir apa pun. Order tetap boleh
     * dibuat walau bahan kurang (workflow percetakan biasa: order dulu,
     * bahan direstock belakangan sebelum naik cetak) - lihat catatan
     * keputusan di laporan audit sesi ini. Rumus qty_pakai & konversi satuan
     * HARUS sama persis dengan PenjualanController::store(), supaya
     * indikator ini tidak pernah bilang "cukup" padahal nanti sebenarnya
     * kurang (atau sebaliknya).
     */
    public function checkStok(Request $request)
    {
        $idProduk = (int) $request->query('id_produk');
        $p = (float) $request->query('p', 0);
        $l = (float) $request->query('l', 0);
        $qtyInput = (float) $request->query('qty_input', 0);

        $produk = DB::table('produk')->where('id_produk', $idProduk)->first();
        if (!$produk || $qtyInput <= 0) {
            return response()->json(['cukup' => true, 'detail' => []]);
        }

        $boms = \App\Services\BomCalculationService::komponen($idProduk, $p, $l, $qtyInput);
        $detail = [];
        $cukup = true;

        foreach ($boms as $bom) {
            $qtyPakai = $bom->qty_pakai;
            if ($qtyPakai <= 0) {
                continue;
            }

            $bahan = DB::table('bahan_baku')->where('id_bahan', $bom->id_bahan)->first();
            if (!$bahan) {
                continue;
            }

            $nilaiKonversi = (float) ($bahan->nilai_konversi ?: 1);
            $dibutuhkan = $qtyPakai / $nilaiKonversi;
            $tersedia = (float) $bahan->stok;

            if ($dibutuhkan > $tersedia) {
                $cukup = false;
            }

            $detail[] = [
                'nama_bahan' => $bahan->nama_bahan,
                'dibutuhkan' => round($dibutuhkan, 4),
                'tersedia'   => round($tersedia, 4),
                'satuan'     => $bahan->satuan_stok,
                'cukup'      => $dibutuhkan <= $tersedia,
            ];
        }

        return response()->json(['cukup' => $cukup, 'detail' => $detail]);
    }
}