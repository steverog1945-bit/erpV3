<?php

namespace App\Http\Controllers;

use App\Services\LembarEvaluasiService as Evaluasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lembar Evaluasi Master: cetak (atau unduh CSV) bahan baku, BOM, dan harga
 * produk beserta seluruh tier - bahan evaluasi harian/bulanan.
 */
class LembarEvaluasiController extends Controller
{
    private const JENIS = ['semua', 'bahan', 'bom', 'harga'];

    public function index(Request $request)
    {
        $jenis = in_array($request->jenis, self::JENIS, true) ? $request->jenis : 'semua';
        $idMesin = $request->filled('id_mesin') ? (int) $request->id_mesin : null;
        $idKategori = $request->filled('id_kategori') ? (int) $request->id_kategori : null;
        $status = in_array($request->status, ['aktif', 'semua'], true) ? $request->status : 'aktif';
        $periode = $request->periode === 'bulanan' ? 'Bulanan' : 'Harian';
        $cekFisik = $request->boolean('cek_fisik', true);

        if ($request->get('format') === 'csv') {
            return $this->csv($jenis === 'semua' ? 'bahan' : $jenis, $idMesin, $idKategori, $status);
        }

        return view('laporan.evaluasi-master', [
            'jenis' => $jenis, 'periode' => $periode, 'cekFisik' => $cekFisik, 'status' => $status,
            'idMesin' => $idMesin, 'idKategori' => $idKategori,
            'mesin' => DB::table('mesin')->orderBy('nama_mesin')->get(),
            'kategori' => DB::table('kategori')->orderBy('nama_kategori')->get(),
            'bahan' => in_array($jenis, ['semua', 'bahan'], true) ? Evaluasi::bahanBaku($idMesin, $idKategori, $status) : collect(),
            'bom' => in_array($jenis, ['semua', 'bom'], true) ? Evaluasi::bom($idMesin) : collect(),
            'harga' => in_array($jenis, ['semua', 'harga'], true) ? Evaluasi::harga($idMesin) : ['golongan' => collect(), 'produk' => collect()],
        ]);
    }

    private function csv(string $jenis, ?int $idMesin, ?int $idKategori, string $status): StreamedResponse
    {
        $nama = 'evaluasi-' . $jenis . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($jenis, $idMesin, $idKategori, $status) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 supaya Excel membaca huruf dengan benar

            if ($jenis === 'bahan') {
                fputcsv($out, \App\Support\CsvAman::baris(['Kode', 'Bahan', 'Mesin', 'Kategori', 'Satuan Stok', 'Stok', 'Stok Minimal', 'Harga Rata-rata', 'Nilai Persediaan']), ';');
                foreach (Evaluasi::bahanBaku($idMesin, $idKategori, $status) as $b) {
                    fputcsv($out, \App\Support\CsvAman::baris([$b->kode_bahan, $b->nama_bahan, $b->nama_mesin, $b->nama_kategori, $b->satuan_stok, (float) $b->stok, (float) $b->stok_minimal, (float) $b->harga_rata_rata, $b->nilai]), ';');
                }
            } elseif ($jenis === 'bom') {
                fputcsv($out, \App\Support\CsvAman::baris(['Produk', 'Mesin', 'Satuan Jual', 'Bahan', 'Rumus', 'Pemakaian per Satuan Jual', 'Biaya Bahan per Satuan Jual', 'HPP per Satuan Jual']), ';');
                foreach (Evaluasi::bom($idMesin) as $p) {
                    foreach ($p->baris as $r) {
                        fputcsv($out, \App\Support\CsvAman::baris([$p->nama_produk, $p->nama_mesin, $p->satuan_jual, $r->nama_bahan, $r->cara_hitung_pemakaian, round($r->pakai, 4), $r->biaya, $p->hpp]), ';');
                    }
                    if ($p->tanpa_bom) {
                        fputcsv($out, \App\Support\CsvAman::baris([$p->nama_produk, $p->nama_mesin, $p->satuan_jual, '(belum ada BOM)', '', '', '', '']), ';');
                    }
                }
            } else {
                $h = Evaluasi::harga($idMesin);
                fputcsv($out, \App\Support\CsvAman::baris(array_merge(['Produk', 'Mesin', 'Satuan Jual', 'HPP per Satuan Jual', 'Qty Dari', 'Qty Sampai'], $h['golongan']->pluck('nama_golongan')->all())), ';');
                foreach ($h['produk'] as $p) {
                    foreach ($p->tier as $t) {
                        $baris = [$p->nama_produk, $p->nama_mesin, $p->satuan_jual, $p->hpp, $t->min, $t->max ?? 'tak terbatas'];
                        foreach ($h['golongan'] as $g) {
                            $baris[] = $t->per_golongan[$g->id_golongan] ?? '';
                        }
                        fputcsv($out, \App\Support\CsvAman::baris($baris), ';');
                    }
                }
            }
            fclose($out);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
