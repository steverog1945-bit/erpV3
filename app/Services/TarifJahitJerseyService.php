<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Rincian ongkos jahit borongan untuk satu order jersey.
 *
 * Tarif diatur manual di katalog Jasa Jahit: setiap tarif jersey boleh terikat tipe lengan, tipe kerah,
 * keduanya, atau tidak keduanya (tarif umum jersey). Untuk setiap baju, dipilih tarif yang PALING KHUSUS
 * yang cocok:
 *   1. lengan DAN kerah cocok      (mis. "Panjang + Wangki")
 *   2. kerah saja cocok            (mis. "Kerah wangki" 8.000, berlaku apa pun lengannya)
 *   3. lengan saja cocok           (mis. "Lengan pendek" 5.000, "Lengan panjang" 7.000)
 *   4. tarif umum jersey           (tanpa lengan dan kerah)
 * Baju yang tidak menemukan tarif apa pun dilaporkan terpisah supaya tidak diam-diam tidak dibayar.
 */
class TarifJahitJerseyService
{
    /**
     * @return array{baris: array<int, array{id_jasa_jahit:int, nama_jahit:string, harga:float, qty:int, sudah:float, subtotal:float}>, tanpa_tarif: array<int, array{keterangan:string, qty:int}>, total_baju:int}
     */
    public static function rincian(int $idOrder): array
    {
        $tarif = DB::table('jasa_jahit')->where('untuk_jersey', true)->get();

        // Jumlah baju per kombinasi lengan x kerah. Pemain yang terdaftar memakai lengan/kerahnya sendiri
        // (atau ikut barisnya bila kosong). Sisa qty baris di luar daftar pemain (tipe "jumlah saja" atau
        // campuran) memakai lengan/kerah baris.
        $kelompok = [];
        $tambah = function (?int $lengan, ?int $kerah, int $qty) use (&$kelompok) {
            if ($qty <= 0) {
                return;
            }
            $k = ($lengan ?? 0) . '-' . ($kerah ?? 0);
            $kelompok[$k] ??= ['lengan' => $lengan, 'kerah' => $kerah, 'qty' => 0];
            $kelompok[$k]['qty'] += $qty;
        };

        $lines = DB::table('jersey_order_lines')->where('jersey_order_id', $idOrder)->get(['id', 'qty', 'jersey_sleeve_type_id', 'jersey_collar_type_id']);
        $items = DB::table('jersey_order_items')->whereIn('jersey_order_line_id', $lines->pluck('id'))
            ->get(['jersey_order_line_id', 'jersey_sleeve_type_id', 'jersey_collar_type_id'])->groupBy('jersey_order_line_id');
        foreach ($lines as $line) {
            $pemain = $items->get($line->id, collect());
            foreach ($pemain as $it) {
                $tambah($it->jersey_sleeve_type_id ?? $line->jersey_sleeve_type_id, $it->jersey_collar_type_id ?? $line->jersey_collar_type_id, 1);
            }
            $tambah($line->jersey_sleeve_type_id, $line->jersey_collar_type_id, (int) $line->qty - $pemain->count());
        }

        $namaLengan = DB::table('jersey_sleeve_types')->pluck('name', 'id');
        $namaKerah = DB::table('jersey_collar_types')->pluck('name', 'id');

        $baris = [];
        $tanpaTarif = [];
        $totalBaju = 0;
        foreach ($kelompok as $g) {
            $totalBaju += $g['qty'];
            $cocok = $tarif->first(fn ($t) => $t->jersey_sleeve_type_id && $t->jersey_collar_type_id && $t->jersey_sleeve_type_id == $g['lengan'] && $t->jersey_collar_type_id == $g['kerah'])
                ?? $tarif->first(fn ($t) => !$t->jersey_sleeve_type_id && $t->jersey_collar_type_id && $t->jersey_collar_type_id == $g['kerah'])
                ?? $tarif->first(fn ($t) => $t->jersey_sleeve_type_id && !$t->jersey_collar_type_id && $t->jersey_sleeve_type_id == $g['lengan'])
                ?? $tarif->first(fn ($t) => !$t->jersey_sleeve_type_id && !$t->jersey_collar_type_id);

            if (!$cocok) {
                $ket = trim(($g['lengan'] ? 'lengan ' . ($namaLengan[$g['lengan']] ?? '?') : 'tanpa data lengan') . ', ' . ($g['kerah'] ? 'kerah ' . ($namaKerah[$g['kerah']] ?? '?') : 'tanpa data kerah'));
                $tanpaTarif[] = ['keterangan' => $ket, 'qty' => $g['qty']];
                continue;
            }
            $baris[$cocok->id_jasa_jahit] ??= ['id_jasa_jahit' => (int) $cocok->id_jasa_jahit, 'nama_jahit' => $cocok->nama_jahit, 'harga' => (float) $cocok->harga_borongan, 'qty' => 0, 'sudah' => 0.0, 'subtotal' => 0.0];
            $baris[$cocok->id_jasa_jahit]['qty'] += $g['qty'];
        }

        // Yang sudah pernah dicatat untuk order ini (per tarif), supaya tidak dibayar dua kali.
        $sudah = DB::table('jasa_jahit_catat')->where('id_jersey_order', $idOrder)->groupBy('id_jasa_jahit')
            ->selectRaw('id_jasa_jahit, SUM(qty) q')->pluck('q', 'id_jasa_jahit');
        foreach ($baris as $id => &$b) {
            $b['sudah'] = (float) ($sudah[$id] ?? 0);
            $b['subtotal'] = round(max(0, $b['qty'] - $b['sudah']) * $b['harga'], 2);
        }
        unset($b);

        return ['baris' => array_values($baris), 'tanpa_tarif' => $tanpaTarif, 'total_baju' => $totalBaju];
    }
}
