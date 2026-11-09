<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Pengeluaran stok bahan baku non-penjualan (rework, waste): kunci baris
 * bahan, tolak kalau stok tidak cukup (kecuali $bolehMinus), kurangi stok, catat ke kartu_stok
 * (OUT), dan kembalikan nilai biaya (qty x harga_rata_rata) untuk dijurnal
 * pemanggil. Harus dipanggil di dalam DB::transaction() pemanggil.
 */
class StokKeluarService
{
    public static function keluarkan(int $idBahan, float $qty, string $referensi, string $keterangan, bool $bolehMinus = false): float
    {
        if ($qty <= 0) {
            return 0.0;
        }

        $bahan = DB::table('bahan_baku')->where('id_bahan', $idBahan)->lockForUpdate()->first();

        if (!$bahan) {
            throw new \RuntimeException("Bahan #{$idBahan} tidak ditemukan.");
        }

        $stokSebelum = (float) $bahan->stok;

        if (!$bolehMinus && $qty > $stokSebelum + 0.0001) {
            throw new \RuntimeException("Stok {$bahan->nama_bahan} tidak cukup (tersisa " . angka($stokSebelum, 2) . ", dibutuhkan " . angka($qty, 2) . ').');
        }

        $stokSesudah = $stokSebelum - $qty;

        DB::table('bahan_baku')->where('id_bahan', $idBahan)->update(['stok' => $stokSesudah]);

        DB::table('kartu_stok')->insert([
            'id_bahan' => $idBahan,
            'tanggal' => now(),
            'tipe' => 'OUT',
            'qty' => $qty,
            'stok_sebelum' => $stokSebelum,
            'stok_sesudah' => $stokSesudah,
            'referensi' => $referensi,
            'keterangan' => $keterangan,
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return round($qty * (float) $bahan->harga_rata_rata, 2);
    }
}
