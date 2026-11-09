<?php

namespace App\Services;

use App\Models\JerseyOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Pemotongan stok kain (bahan baku yang tersinkron dengan bahan jersey) untuk order jersey.
 * Dipotong SEKALI saat order mulai diproses (status proses/selesai/diambil), sebesar estimasi kg per barang;
 * biayanya = kg x harga rata-rata bahan, dijurnal sebagai HPP. Order kembali ke status baru, diubah barangnya,
 * atau dibatalkan -> stok dikembalikan (dan dipotong ulang bila perlu). Stok boleh minus (peringatan),
 * sama seperti bahan cetak. Harus dipanggil di dalam DB::transaction() pemanggil.
 */
class JerseyStokService
{
    /** Kebutuhan kain per bahan baku tersinkron: [id_bahan => kg]. */
    public static function kebutuhan(int $idOrder): array
    {
        $baris = DB::table('jersey_order_lines as l')
            ->join('jersey_materials as m', 'm.id', '=', 'l.jersey_material_id')
            ->leftJoin('jersey_order_line_materials as lm', 'lm.jersey_order_line_id', '=', 'l.id')
            ->where('l.jersey_order_id', $idOrder)->whereNotNull('m.id_bahan')
            ->groupBy('m.id_bahan')
            ->selectRaw('m.id_bahan, COALESCE(SUM(lm.penggunaan_kg), 0) as kg')
            ->get();

        return $baris->filter(fn ($r) => (float) $r->kg > 0)->mapWithKeys(fn ($r) => [(int) $r->id_bahan => (float) $r->kg])->all();
    }

    /** @return array<int,string> peringatan (mis. stok kain minus) */
    public static function potong(JerseyOrder $order): array
    {
        $order = JerseyOrder::whereKey($order->id)->lockForUpdate()->first();
        if (!$order || $order->stok_dipotong_at) {
            return [];
        }

        $peringatan = [];
        $totalBiaya = 0.0;
        // Kain yang belum dihubungkan ke bahan baku tidak terpotong dan tidak ber-HPP; dulu dilewati tanpa kabar.
        $tanpaBahan = DB::table('jersey_order_lines as l')->join('jersey_materials as m', 'm.id', '=', 'l.jersey_material_id')
            ->where('l.jersey_order_id', $order->id)->whereNull('m.id_bahan')->distinct()->pluck('m.name');
        if ($tanpaBahan->isNotEmpty()) {
            $peringatan[] = 'Kain ' . $tanpaBahan->implode(', ') . ' belum dihubungkan ke bahan baku (Master Jersey), jadi stoknya tidak terpotong dan HPP-nya tidak tercatat.';
        }
        foreach (self::kebutuhan($order->id) as $idBahan => $kg) {
            $bahan = DB::table('bahan_baku')->where('id_bahan', $idBahan)->lockForUpdate()->first();
            if (!$bahan) {
                continue;
            }
            $sebelum = (float) $bahan->stok;
            $sesudah = $sebelum - $kg;
            DB::table('bahan_baku')->where('id_bahan', $idBahan)->update(['stok' => $sesudah]);
            DB::table('kartu_stok')->insert([
                'id_bahan' => $idBahan, 'tanggal' => now(), 'tipe' => 'OUT', 'qty' => $kg, 'stok_sebelum' => $sebelum, 'stok_sesudah' => $sesudah,
                'referensi' => $order->order_number, 'keterangan' => 'Pemakaian kain order jersey ' . $order->order_number,
                'created_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $biaya = round($kg * (float) $bahan->harga_rata_rata, 2);
            DB::table('jersey_order_potong_stok')->insert(['jersey_order_id' => $order->id, 'id_bahan' => $idBahan, 'qty' => $kg, 'biaya' => $biaya, 'created_at' => now(), 'updated_at' => now()]);
            $totalBiaya += $biaya;
            if ($biaya <= 0) {
                $peringatan[] = "Bahan {$bahan->nama_bahan} belum punya harga rata-rata, jadi HPP kainnya tercatat Rp 0. Isi harga lewat faktur pembelian.";
            }
            if ($sesudah < 0) {
                $peringatan[] = "Stok {$bahan->nama_bahan} menjadi minus (" . angka($sesudah, 2) . " {$bahan->satuan_stok}) - segera restock.";
            }
        }

        if ($totalBiaya > 0) {
            AccountingService::postHppJersey($order->order_number, $totalBiaya);
        }
        DB::table('jersey_orders')->where('id', $order->id)->update(['stok_dipotong_at' => now()]);

        return $peringatan;
    }

    public static function kembalikan(JerseyOrder $order): void
    {
        // Audit Fase 2: gembok baris order DULU (sama seperti potong()), baru
        // baca catatan potongan. Tanpa ini dua pengembalian bersamaan (batal
        // order + ubah status ke "baru", dua kasir) sama-sama membaca baris
        // potong_stok yang sama lalu menambah stok DUA KALI. Yang kedua kini
        // menunggu, lalu melihat tabel sudah kosong dan keluar. Pengembalian
        // memakai catatan potongan ASLI (qty & biaya saat dipotong), bukan
        // estimasi order saat ini - aman bila material/BOM berubah belakangan.
        DB::table('jersey_orders')->where('id', $order->id)->lockForUpdate()->first();

        $rows = DB::table('jersey_order_potong_stok')->where('jersey_order_id', $order->id)->lockForUpdate()->get();
        if ($rows->isEmpty()) {
            DB::table('jersey_orders')->where('id', $order->id)->update(['stok_dipotong_at' => null]);

            return;
        }

        $totalBiaya = 0.0;
        foreach ($rows as $r) {
            $bahan = DB::table('bahan_baku')->where('id_bahan', $r->id_bahan)->lockForUpdate()->first();
            if (!$bahan) {
                continue;
            }
            $sebelum = (float) $bahan->stok;
            $sesudah = $sebelum + (float) $r->qty;
            DB::table('bahan_baku')->where('id_bahan', $r->id_bahan)->update(['stok' => $sesudah]);
            DB::table('kartu_stok')->insert([
                'id_bahan' => $r->id_bahan, 'tanggal' => now(), 'tipe' => 'IN', 'qty' => $r->qty, 'stok_sebelum' => $sebelum, 'stok_sesudah' => $sesudah,
                'referensi' => 'KEMBALI-' . $order->order_number, 'keterangan' => 'Pengembalian kain order jersey ' . $order->order_number,
                'created_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $totalBiaya += (float) $r->biaya;
        }

        if ($totalBiaya > 0) {
            AccountingService::postHppJersey($order->order_number, -1 * $totalBiaya);
        }
        DB::table('jersey_order_potong_stok')->where('jersey_order_id', $order->id)->delete();
        DB::table('jersey_orders')->where('id', $order->id)->update(['stok_dipotong_at' => null]);
    }

    /** Barang order berubah setelah stok dipotong: kembalikan lalu potong ulang sesuai estimasi baru. */
    public static function segarkan(JerseyOrder $order): array
    {
        $order = $order->fresh();
        if (!$order || !$order->stok_dipotong_at) {
            return [];
        }
        self::kembalikan($order);

        return self::potong($order->fresh());
    }

    /** Sesuaikan dengan status order: proses/selesai/diambil -> potong; kembali ke baru -> kembalikan. */
    public static function selaraskan(JerseyOrder $order): array
    {
        $order = $order->fresh();
        $aktif = in_array($order->status, ['proses', 'selesai', 'diambil'], true);
        if ($aktif && !$order->stok_dipotong_at) {
            return self::potong($order);
        }
        if (!$aktif && $order->stok_dipotong_at) {
            self::kembalikan($order);
        }

        return [];
    }
}
