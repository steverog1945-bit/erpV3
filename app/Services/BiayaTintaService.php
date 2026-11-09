<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Perhitungan biaya tinta/toner untuk laporan (murni baca, tidak menyentuh
 * stok atau HPP order). Dasar: setiap baris pesanan produk pada nota yang
 * tidak dibatalkan dalam periode -> luas cetak (m2 = p x l / 10000 x qty)
 * atau jumlah lembar, dikali pemakaian per mesin (tabel pemakaian_tinta),
 * dikonversi ke satuan stok dan dikali harga rata-rata bahan tinta.
 */
class BiayaTintaService
{
    /**
     * @return array{per_bahan: \Illuminate\Support\Collection, per_produk: \Illuminate\Support\Collection, total_biaya: float, total_m2: float, total_omzet: float, bahan_tanpa_harga: array<int,string>}
     */
    public static function hitung(Carbon $dari, Carbon $sampai): array
    {
        $baris = DB::table('transaksi_detail as d')
            ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->join('produk as p', 'p.id_produk', '=', 'd.id_produk')
            ->where('t.status_nota', '!=', 'Dibatalkan')
            ->whereTanggalAntara('t.tgl_order', [$dari->toDateString(), $sampai->toDateString()])
            ->get(['p.id_produk', 'p.nama_produk', 'p.id_mesin', 'p.metode_hitung', 'd.p_ukuran', 'd.l_ukuran', 'd.qty', 'd.subtotal']);

        // Ukuran per produk: m2, lembar (qty), omzet.
        $produk = [];
        foreach ($baris as $r) {
            $m2 = $r->metode_hitung === 'ukuran' ? ((float) $r->p_ukuran * (float) $r->l_ukuran / 10000) * (float) $r->qty : 0.0;
            $p = $produk[$r->id_produk] ??= ['id_produk' => $r->id_produk, 'nama' => $r->nama_produk, 'id_mesin' => $r->id_mesin, 'm2' => 0.0, 'lembar' => 0.0, 'omzet' => 0.0];
            $p['m2'] += $m2;
            $p['lembar'] += (float) $r->qty;
            $p['omzet'] += (float) $r->subtotal;
            $produk[$r->id_produk] = $p;
        }

        $pengaturan = DB::table('pemakaian_tinta as pt')
            ->join('bahan_baku as b', 'b.id_bahan', '=', 'pt.id_bahan')
            ->join('mesin as m', 'm.id_mesin', '=', 'pt.id_mesin')
            ->where('pt.is_active', true)
            ->get(['pt.id_mesin', 'pt.id_bahan', 'pt.dasar', 'pt.pemakaian', 'm.nama_mesin', 'b.nama_bahan', 'b.satuan_stok', 'b.nilai_konversi', 'b.harga_rata_rata', 'b.id_satuan_hitung']);

        $satuanHitung = DB::table('satuan')->pluck('nama_satuan', 'id_satuan');
        $perBahan = [];
        $biayaPerProduk = [];
        $tanpaHarga = [];

        foreach ($pengaturan as $s) {
            $konversi = (float) $s->nilai_konversi > 0 ? (float) $s->nilai_konversi : 1.0;
            $harga = (float) $s->harga_rata_rata;
            if ($harga <= 0) {
                $tanpaHarga[$s->id_bahan] = $s->nama_bahan;
            }

            foreach ($produk as $p) {
                if ((int) $p['id_mesin'] !== (int) $s->id_mesin) {
                    continue;
                }
                $dasar = $s->dasar === 'm2' ? $p['m2'] : $p['lembar'];
                if ($dasar <= 0) {
                    continue;
                }

                $pakai = $dasar * (float) $s->pemakaian;
                $biaya = round($pakai / $konversi * $harga, 2);

                $kunci = $s->id_mesin . '-' . $s->id_bahan . '-' . $s->dasar;
                $perBahan[$kunci] ??= [
                    'mesin' => $s->nama_mesin, 'bahan' => $s->nama_bahan, 'dasar' => $s->dasar, 'per_unit' => (float) $s->pemakaian,
                    'satuan_hitung' => $satuanHitung[$s->id_satuan_hitung] ?? '', 'volume_dasar' => 0.0, 'pakai' => 0.0, 'biaya' => 0.0, 'harga_ada' => $harga > 0,
                ];
                $perBahan[$kunci]['volume_dasar'] += $dasar;
                $perBahan[$kunci]['pakai'] += $pakai;
                $perBahan[$kunci]['biaya'] += $biaya;

                $biayaPerProduk[$p['id_produk']] = ($biayaPerProduk[$p['id_produk']] ?? 0.0) + $biaya;
            }
        }

        $perProduk = collect($produk)->map(function ($p) use ($biayaPerProduk) {
            $biaya = round($biayaPerProduk[$p['id_produk']] ?? 0.0, 2);

            return (object) ($p + [
                'biaya_tinta' => $biaya,
                'biaya_per_m2' => $p['m2'] > 0 ? round($biaya / $p['m2'], 2) : null,
                'persen_omzet' => $p['omzet'] > 0 ? round($biaya / $p['omzet'] * 100, 1) : 0.0,
            ]);
        })->filter(fn ($p) => $p->biaya_tinta > 0 || $p->m2 > 0)->sortByDesc('biaya_tinta')->values();

        return [
            'per_bahan' => collect($perBahan)->map(fn ($x) => (object) $x)->sortByDesc('biaya')->values(),
            'per_produk' => $perProduk,
            'total_biaya' => round(array_sum(array_column($perBahan, 'biaya')), 2),
            'total_m2' => round(array_sum(array_column($produk, 'm2')), 2),
            'total_omzet' => round(array_sum(array_column($produk, 'omzet')), 2),
            'bahan_tanpa_harga' => array_values($tanpaHarga),
        ];
    }
}
