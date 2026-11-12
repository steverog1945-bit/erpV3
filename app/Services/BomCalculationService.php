<?php

namespace App\Services;

/**
 * Satu-satunya tempat yang menerjemahkan `bom_detail.cara_hitung_pemakaian`
 * (rumus yang dipilih admin di form BOM produk) menjadi qty pemakaian bahan
 * baku total untuk satu baris order. Sebelumnya logika ini diduplikasi di 3
 * tempat (PenjualanController, StokReversalService, PenjualanApiController)
 * dan hanya mengenali literal '(P*L)/10000' - rumus 'Keliling (m)' dan
 * 'Bolak Balik (x2)' yang sudah tersedia di dropdown BOM (lihat
 * master/produk/index.blade.php) diam-diam ikut jatuh ke cabang quantity
 * biasa, sehingga pemakaian bahan untuk kedua rumus itu salah hitung.
 *
 * Rumus tetap: '(P*L)/10000' (luas m2), '(P*L)/10000*2', '2*(P+L)/100'
 * (keliling m), 'Q' (1:1).
 *
 * Rumus berfaktor (untuk pemakaian bahan yang bukan 1:1 dengan qty jual):
 *   'Q/N'  : N barang per satuan bahan. Contoh 'Q/4' = 4 foto 6R dalam 1
 *            lembar A3+, sehingga tiap foto memakai 0,25 lembar.
 *   'Q*K'  : K satuan bahan per barang. Contoh 'Q*5' = 5 lembar per box
 *            kartu nama; 'Q*0.22' = 0,22 m2 roll per foto 16R (termasuk
 *            sisa potong).
 *   '(P*L)/10000*K' : luas dikali faktor K (mis. 1.1 = tambah 10% sisa).
 *
 * Pakai mapping eksplisit + regex ketat (bukan eval()) supaya tidak ada
 * risiko rumus sembarangan dieksekusi sebagai kode PHP.
 */
class BomCalculationService
{
    private const ANGKA = '(\d+(?:\.\d+)?)';

    /**
     * Semua bahan yang terpakai untuk sebuah produk: komponen BOM + tinta/toner mesin produk itu
     * (tabel pemakaian_tinta: ml per m2 atau per lembar, satuan HITUNG bahan). Tinta yang sudah ada
     * di BOM produk tidak dihitung dua kali. Dipakai bersama oleh potong stok order, pengembalian
     * stok, rework, dan cek stok supaya semuanya konsisten.
     *
     * @return array<int, object{id_bahan:int, qty_pakai:float, is_tinta:bool}>
     */
    public static function komponen(int $idProduk, float $p, float $l, float $qty): array
    {
        $hasil = [];
        $idDiBom = [];

        foreach (\Illuminate\Support\Facades\DB::table('bom_detail')->where('id_produk', $idProduk)->get() as $bom) {
            $idDiBom[(int) $bom->id_bahan] = true;
            $hasil[] = (object) [
                'id_bahan' => (int) $bom->id_bahan,
                'qty_pakai' => self::hitungQtyPakai($bom->cara_hitung_pemakaian, $p, $l, $qty),
                'is_tinta' => false,
            ];
        }

        $produk = \Illuminate\Support\Facades\DB::table('produk')->where('id_produk', $idProduk)->first(['id_mesin', 'metode_hitung']);
        if ($produk && $produk->id_mesin) {
            $m2 = $produk->metode_hitung === 'ukuran' ? ($p * $l / 10000) * $qty : 0.0;
            $tinta = \Illuminate\Support\Facades\DB::table('pemakaian_tinta')->where('id_mesin', $produk->id_mesin)->where('is_active', true)->get();
            foreach ($tinta as $t) {
                if (isset($idDiBom[(int) $t->id_bahan])) {
                    continue;
                }
                // Dasar selain 'm2' (mis. 'pcs') = pemakaian per pcs barang,
                // imbuhan khusus produk metode quantity. Untuk produk ukuran,
                // m² sudah mencakup luas cetak, jadi baris 'pcs' dilewati
                // supaya tidak dobel-dihitung.
                if ($t->dasar !== 'm2' && $produk->metode_hitung === 'ukuran') {
                    continue;
                }
                $dasar = $t->dasar === 'm2' ? $m2 : $qty;
                $pakai = $dasar * (float) $t->pemakaian;
                if ($pakai > 0) {
                    $hasil[] = (object) ['id_bahan' => (int) $t->id_bahan, 'qty_pakai' => $pakai, 'is_tinta' => true];
                }
            }
        }

        return $hasil;
    }

    public static function hitungQtyPakai(string $rumus, float $panjang, float $lebar, float $qty): float
    {
        $rumus = str_replace(' ', '', $rumus);

        $tetap = match ($rumus) {
            '(P*L)/10000' => ($panjang * $lebar / 10000) * $qty,
            '(P*L)/10000*2' => ($panjang * $lebar / 10000) * 2 * $qty,
            '2*(P+L)/100' => (2 * ($panjang + $lebar) / 100) * $qty,
            'Q' => $qty,
            default => null,
        };
        if ($tetap !== null) {
            return $tetap;
        }

        if (preg_match('#^Q\*' . self::ANGKA . '$#', $rumus, $m)) {
            return $qty * (float) $m[1];
        }
        if (preg_match('#^Q/' . self::ANGKA . '$#', $rumus, $m) && (float) $m[1] > 0) {
            return $qty / (float) $m[1];
        }
        if (preg_match('#^\(P\*L\)/10000\*' . self::ANGKA . '$#', $rumus, $m)) {
            return ($panjang * $lebar / 10000) * (float) $m[1] * $qty;
        }

        return $qty;
    }

    /** Apakah rumus dikenali (dipakai validasi/penyaringan input BOM). */
    public static function rumusValid(string $rumus): bool
    {
        $rumus = str_replace(' ', '', $rumus);

        return in_array($rumus, ['(P*L)/10000', '(P*L)/10000*2', '2*(P+L)/100', 'Q'], true)
            || (bool) preg_match('#^Q\*' . self::ANGKA . '$#', $rumus)
            || (bool) preg_match('#^Q/' . self::ANGKA . '$#', $rumus)
            || (bool) preg_match('#^\(P\*L\)/10000\*' . self::ANGKA . '$#', $rumus);
    }
}
