<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data untuk Lembar Evaluasi Master (cetak harian/bulanan): bahan baku
 * (stok, harga, nilai persediaan), BOM per produk (pemakaian & biaya bahan
 * per satuan jual), dan harga jual per tier x golongan lengkap dengan
 * margin terhadap HPP dari BOM. Murni baca, tidak mengubah data.
 *
 * Satuan jual produk: 'ukuran' = per m2 (BOM dihitung untuk 1 m2 = 100 x
 * 100 cm), 'quantity' = per 1 pcs/lembar - sama dengan cara order dihitung.
 */
class LembarEvaluasiService
{
    public static function bahanBaku(?int $idMesin = null, ?int $idKategori = null, string $status = 'aktif'): Collection
    {
        $q = DB::table('bahan_baku as b')
            ->leftJoin('mesin as m', 'm.id_mesin', '=', 'b.id_mesin')
            ->leftJoin('kategori as k', 'k.id_kategori', '=', 'b.id_kategori')
            ->leftJoin('satuan as sh', 'sh.id_satuan', '=', 'b.id_satuan_hitung')
            ->select('b.*', 'm.nama_mesin', 'k.nama_kategori', 'sh.nama_satuan as satuan_hitung')
            ->orderBy('m.nama_mesin')->orderBy('b.nama_bahan');

        if ($status !== 'semua') {
            $q->where('b.status', $status);
        }
        if ($idMesin) {
            $q->where('b.id_mesin', $idMesin);
        }
        if ($idKategori) {
            $q->where('b.id_kategori', $idKategori);
        }

        return $q->get()->map(function ($b) {
            $b->nilai = round((float) $b->stok * (float) $b->harga_rata_rata, 2);
            $b->menipis = (float) $b->stok <= (float) $b->stok_minimal;

            return $b;
        });
    }

    /**
     * BOM per produk aktif: tiap baris bahan dengan pemakaian & biaya untuk
     * 1 satuan jual, plus total HPP per satuan jual.
     */
    public static function bom(?int $idMesin = null): Collection
    {
        $produk = DB::table('produk as p')
            ->leftJoin('mesin as m', 'm.id_mesin', '=', 'p.id_mesin')
            ->where('p.status', 'aktif')
            ->when($idMesin, fn ($q) => $q->where('p.id_mesin', $idMesin))
            ->orderBy('m.nama_mesin')->orderBy('p.nama_produk')
            ->get(['p.id_produk', 'p.nama_produk', 'p.metode_hitung', 'm.nama_mesin']);

        $bom = DB::table('bom_detail as d')
            ->join('bahan_baku as b', 'b.id_bahan', '=', 'd.id_bahan')
            ->leftJoin('satuan as sh', 'sh.id_satuan', '=', 'b.id_satuan_hitung')
            ->get(['d.id_produk', 'd.cara_hitung_pemakaian', 'b.nama_bahan', 'b.satuan_stok', 'b.nilai_konversi', 'b.harga_rata_rata', 'sh.nama_satuan as satuan_hitung'])
            ->groupBy('id_produk');

        return $produk->map(function ($p) use ($bom) {
            $ukuran = $p->metode_hitung === 'ukuran';
            $p->satuan_jual = $ukuran ? 'm²' : 'pcs';
            $total = 0.0;
            $adaKosong = false;

            $p->baris = ($bom[$p->id_produk] ?? collect())->map(function ($r) use ($ukuran, &$total, &$adaKosong) {
                $pakai = BomCalculationService::hitungQtyPakai($r->cara_hitung_pemakaian, $ukuran ? 100 : 0, $ukuran ? 100 : 0, 1);
                $konversi = (float) $r->nilai_konversi > 0 ? (float) $r->nilai_konversi : 1.0;
                $r->pakai = $pakai;
                $r->biaya = round($pakai / $konversi * (float) $r->harga_rata_rata, 2);
                $r->tanpa_harga = (float) $r->harga_rata_rata <= 0;
                $total += $r->biaya;
                $adaKosong = $adaKosong || $r->tanpa_harga;

                return $r;
            })->values();

            $p->hpp = round($total, 2);
            $p->ada_harga_kosong = $adaKosong;
            $p->tanpa_bom = $p->baris->isEmpty();

            return $p;
        });
    }

    /**
     * Harga jual per produk: baris = tier (rentang), kolom = golongan,
     * lengkap dengan HPP per satuan jual (dari BOM) untuk menghitung margin.
     *
     * @return array{golongan: Collection, produk: Collection}
     */
    public static function harga(?int $idMesin = null): array
    {
        $golongan = DB::table('golongan_konsumen')->orderBy('id_golongan')->get(['id_golongan', 'nama_golongan']);
        $hppProduk = self::bom($idMesin)->keyBy('id_produk');

        $tier = DB::table('setting_harga')->get()->groupBy('id_produk');
        $promo = DB::table('harga_promo')->where('status_promo', 'Aktif')->get()->keyBy('id_produk');

        $produk = $hppProduk->map(function ($p) use ($tier, $promo, $golongan) {
            $baris = ($tier[$p->id_produk] ?? collect())
                ->groupBy(fn ($t) => (float) $t->range_min . '|' . ($t->range_max === null ? '' : (float) $t->range_max))
                ->map(function ($grup) {
                    $first = $grup->first();

                    return (object) [
                        'min' => (float) $first->range_min,
                        'max' => $first->range_max === null ? null : (float) $first->range_max,
                        'per_golongan' => $grup->pluck('harga_jual', 'id_golongan')->map(fn ($v) => (float) $v)->all(),
                    ];
                })->sortBy('min')->values();

            $p->tier = $baris;
            $p->per_gol = $golongan->mapWithKeys(function ($g) use ($tier, $p) {
                $daftar = ($tier[$p->id_produk] ?? collect())->where('id_golongan', $g->id_golongan)
                    ->sortBy(fn ($t) => (float) $t->range_min)->values()
                    ->map(fn ($t) => (object) [
                        'min' => (float) $t->range_min,
                        'max' => $t->range_max === null ? null : (float) $t->range_max,
                        'harga' => (float) $t->harga_jual,
                    ]);

                return [$g->id_golongan => $daftar];
            });
            $p->promo = $promo[$p->id_produk]->harga_promo ?? null;
            $p->jumlah_tier = $baris->count();

            return $p;
        })->values();

        return ['golongan' => $golongan, 'produk' => $produk];
    }

    public static function margin(float $harga, float $hpp): ?float
    {
        return $harga > 0 && $hpp > 0 ? round(($harga - $hpp) / $harga * 100, 1) : null;
    }
}
