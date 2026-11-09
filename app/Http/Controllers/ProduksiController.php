<?php

namespace App\Http\Controllers;

use App\Services\StokReversalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProduksiController extends Controller
{
    /**
     * Produksi membaca SPK yang sama (transaksi_detail). "ON PROGRESS" / "SELESAI"
     * diturunkan dari status_spk yang sudah ada (Antre/Desain/Cetak dianggap ON
     * PROGRESS, Selesai/Batal punya statusnya sendiri) - tidak menambah kolom
     * status baru yang duplikat dengan status_spk.
     */
    public function index(Request $request)
    {
        // §8I: tolak format tanggal yang rusak sebelum dipakai di
        // Carbon::parse() di bawah - tanpa ini input rusak melempar 500.
        $request->validate(['tgl_dari' => 'nullable|date', 'tgl_sampai' => 'nullable|date']);

        $query = DB::table('transaksi_detail')
            ->join('transaksi', 'transaksi_detail.id_transaksi', '=', 'transaksi.id_transaksi')
            ->join('konsumen', 'transaksi.id_konsumen', '=', 'konsumen.id_konsumen')
            ->join('produk', 'transaksi_detail.id_produk', '=', 'produk.id_produk')
            ->leftJoin('mesin', 'produk.id_mesin', '=', 'mesin.id_mesin')
            ->where('transaksi.status_nota', 'Aktif')
            ->where('transaksi.status_order', 'Diterima Kasir')
            ->select(
                'transaksi_detail.id_detail',
                'transaksi_detail.nama_file',
                'transaksi_detail.qty',
                'transaksi_detail.qty_cetak',
                'transaksi_detail.status_urgent',
                'transaksi_detail.status_spk',
                'transaksi_detail.catatan_produksi',
                'transaksi.nomor_nota',
                'transaksi.tgl_order',
                'konsumen.nama_konsumen',
                'produk.nama_produk',
                'produk.spk_lengkap',
                'mesin.nama_mesin as divisi'
            );

        if ($request->filled('konsumen')) {
            $query->where('konsumen.nama_konsumen', 'like', '%' . $request->konsumen . '%');
        }

        // Perf dataset besar: rentang sargable (DATE() mematikan index tgl_order).
        if ($request->filled('tgl_dari')) {
            $query->where('transaksi.tgl_order', '>=', $request->tgl_dari . ' 00:00:00');
        }

        if ($request->filled('tgl_sampai')) {
            $query->where('transaksi.tgl_order', '<', \Carbon\Carbon::parse($request->tgl_sampai)->addDay()->format('Y-m-d') . ' 00:00:00');
        }

        if ($request->filled('id_spk')) {
            $query->where('transaksi_detail.id_detail', $request->id_spk);
        }

        // Default 'progress' kalau parameter tidak dikirim sama sekali (mis. setelah
        // redirect back() dari aksi simpan/selesai/batal) - supaya perilakunya selalu
        // konsisten dengan pilihan default di dropdown "Status Produksi".
        $statusProduksi = $request->input('status_produksi', 'progress');

        if ($statusProduksi === 'selesai') {
            $query->where('transaksi_detail.status_spk', 'Selesai');
        } elseif ($statusProduksi === 'batal') {
            $query->where('transaksi_detail.status_spk', 'Batal');
        } elseif ($statusProduksi === 'pending') {
            // Item yang dibatalkan dengan tindakan "Pending" (lihat
            // ProduksiController::cancel) - diparkir di sini untuk ditinjau,
            // keputusan akhirnya (Antre lagi / Batal) diselesaikan dari
            // halaman SPK (lihat SpkController::resolvePending).
            $query->where('transaksi_detail.status_spk', 'Pending');
        } elseif ($statusProduksi === 'semua') {
            // Dipakai oleh quick-link "Lihat di Produksi" (SPK) dan "Lihat progres
            // di Produksi" (Collecting) - keduanya menuju item/konsumen spesifik
            // yang statusnya bisa Selesai/Batal, jadi TIDAK boleh disaring oleh
            // bucket default (Antre/Desain/Cetak) atau linknya mendarat di
            // halaman kosong walau datanya ada.
        } else {
            // 'Antre' SENGAJA tidak diikutkan di sini: SPK yang masih Antre
            // berarti belum ada yang mengambil pekerjaannya (lihat
            // SpkController::ambilPekerjaan) - Produksi hanya menampilkan
            // item yang sudah eksplisit diambil (Desain) atau sudah dicatat
            // qty cetaknya (Cetak). Antre tetap terlihat di halaman SPK.
            $query->whereIn('transaksi_detail.status_spk', ['Desain', 'Cetak']);
        }

        // Operator hanya melihat divisi (mesin) yang jadi tanggung jawabnya, sama seperti SPK.
        $mesinDiizinkan = \App\Services\DivisiService::mesinDiizinkan($request->user());
        if ($mesinDiizinkan) {
            $query->whereIn('produk.id_mesin', $mesinDiizinkan);
        }
        \App\Services\DivisiService::saring($query, $request->divisi);
        $divisis = \App\Services\DivisiService::daftar($mesinDiizinkan, ['Antre', 'Desain', 'Cetak']);

        $produksi = $query->orderByDesc('transaksi_detail.id_detail')->paginate(20)->withQueryString();

        $jumlahAntreAwal = DB::table('transaksi_detail as d')
            ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->whereIn('d.status_spk', ['Desain', 'Cetak'])
            ->where('t.status_nota', 'Aktif')
            ->where('t.status_order', 'Diterima Kasir')
            ->count();

        return view('produksi.index', compact('produksi', 'jumlahAntreAwal', 'divisis'));
    }

    /**
     * Gate order lifecycle untuk mutasi produksi - induk transaksi wajib
     * status_order='Diterima Kasir'.
     */
    private static function pastikanOrderDiterima(int $idDetail): ?string
    {
        $order = DB::table('transaksi_detail as d')
            ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->where('d.id_detail', $idDetail)
            ->select('t.status_nota', 't.status_order', 't.nomor_nota')
            ->first();

        if (!$order) {
            return 'Item SPK tidak ditemukan.';
        }
        if (($order->status_nota ?? null) === 'Dibatalkan' || ($order->status_order ?? null) === 'Dibatalkan') {
            return "Nota {$order->nomor_nota} sudah dibatalkan - produksi tidak bisa diproses.";
        }
        if (($order->status_order ?? 'Menunggu Kasir') !== 'Diterima Kasir') {
            return "Nota {$order->nomor_nota} belum diterima Kasir - produksi belum aktif.";
        }

        return null;
    }

    public function simpanQty(Request $request, $id)
    {
        $request->validate([
            'qty_cetak' => 'required|integer|min:0',
            'catatan_produksi' => 'nullable|string|max:1000',
        ]);

        $pesanError = null;

        DB::transaction(function () use ($request, $id, &$pesanError) {
            // lockForUpdate() + cek status DI DALAM transaction - sebelumnya
            // dibaca sebelum beginTransaction(), jadi dua request nyaris
            // bersamaan bisa sama-sama lolos cek status basi itu.
            $detail = DB::table('transaksi_detail')->where('id_detail', $id)->lockForUpdate()->first();

            if (!$detail) {
                $pesanError = 'Item SPK tidak ditemukan.';
                return;
            }

            if ($pesanError = self::pastikanOrderDiterima((int) $id)) {
                return;
            }

            if (in_array($detail->status_spk, ['Selesai', 'Batal'])) {
                $pesanError = 'Item ini sudah ' . $detail->status_spk . ', tidak bisa diubah lagi.';
                return;
            }

            DB::table('transaksi_detail')->where('id_detail', $id)->update([
                'qty_cetak' => $request->qty_cetak,
                'catatan_produksi' => $request->catatan_produksi,
                // Begitu jumlah cetak diinput, status bergerak maju ke "Cetak"
                // (kecuali sudah lebih maju dari itu). 'Antre' dipertahankan di
                // sini untuk jaga-jaga walau sekarang seharusnya sudah 'Desain'
                // dulu (lihat SpkController::ambilPekerjaan) sebelum item ini
                // bisa muncul di Produksi sama sekali.
                'status_spk' => in_array($detail->status_spk, ['Antre', 'Desain']) ? 'Cetak' : $detail->status_spk,
            ]);
            \App\Services\AuditLogger::log(
                'updated', 'transaksi_detail', (int) $id,
                ['status_spk' => $detail->status_spk, 'qty_cetak' => $detail->qty_cetak],
                ['status_spk' => in_array($detail->status_spk, ['Antre', 'Desain']) ? 'Cetak' : $detail->status_spk, 'qty_cetak' => (int) $request->qty_cetak],
                "SPK item #{$id}: jumlah cetak diinput"
            );
        });

        if ($pesanError) {
            return back()->with('error', $pesanError);
        }

        return back()->with('success', 'Jumlah produksi disimpan.');
    }

    public function selesai(Request $request, $id)
    {
        $pesanError = null;

        DB::transaction(function () use ($request, $id, &$pesanError) {
            $detail = DB::table('transaksi_detail')->where('id_detail', $id)->lockForUpdate()->first();

            if (!$detail) {
                $pesanError = 'Item SPK tidak ditemukan.';
                return;
            }

            if ($pesanError = self::pastikanOrderDiterima((int) $id)) {
                return;
            }

            if (in_array($detail->status_spk, ['Selesai', 'Batal'])) {
                $pesanError = 'Item ini sudah ' . $detail->status_spk . '.';
                return;
            }

            // §9: tolak transisi SPK yang tidak sah ke Selesai.
            \App\Services\StatusTransitionGuard::validateStatusSpk((string) $detail->status_spk, 'Selesai');

            DB::table('transaksi_detail')->where('id_detail', $id)->update([
                // Kalau qty_cetak belum pernah diisi, dianggap sesuai qty order.
                'qty_cetak' => $detail->qty_cetak ?? $detail->qty,
                'status_spk' => 'Selesai',
            ]);
            \App\Services\AuditLogger::log(
                'updated', 'transaksi_detail', (int) $id,
                ['status_spk' => $detail->status_spk],
                ['status_spk' => 'Selesai', 'qty_cetak' => $detail->qty_cetak ?? $detail->qty],
                "SPK item #{$id} diselesaikan"
            );
        });

        if ($pesanError) {
            return back()->with('error', $pesanError);
        }

        \App\Support\JalurLanjut::set('Berikutnya: QC / serah-terima ke pelanggan', 'collecting.index', 'Buka Pengambilan');
        return back()->with('success', 'Item produksi ditandai selesai.');
    }

    public function cancel(Request $request, $id)
    {
        // Sebelumnya "Batalkan SPK" MURNI berarti status_spk = 'Batal' - status
        // akhir mati tanpa jalan balik selain koreksi manual ke database.
        // Sekarang operator memilih TINDAKAN SELANJUTNYA secara eksplisit di
        // dialog konfirmasi: kembalikan ke antrian SPK biasa (item bisa
        // diambil ulang siapa saja), diparkir Pending (belum diputuskan, tidak
        // ikut antrian aktif), atau batal permanen (seperti sebelumnya).
        $tindakan = $request->input('tindakan', 'batal');
        $statusBaru = match ($tindakan) {
            'antre'   => 'Antre',
            'pending' => 'Pending',
            default   => 'Batal',
        };

        // Stok bahan baku yang sudah dipotong otomatis saat order dibuat
        // (lihat PenjualanController::store) TIDAK otomatis dikembalikan -
        // material kadang sudah terlanjur dipakai/rusak sebelum pembatalan
        // dicatat, jadi mengembalikan stok begitu saja bisa salah. Checkbox
        // ini independen dari tindakan (mau balik Antre/Pending/Batal, stok
        // yang sudah terlanjur terpotong tetap bisa dikembalikan kalau
        // memang belum dipakai). Rumusnya lihat StokReversalService - PERSIS
        // sama dengan rumus potong stok saat order dibuat.
        $kembalikanStok = $request->boolean('kembalikan_stok');
        // Bahan hanya boleh kembali ke stok bila item benar-benar berhenti (Batal). Item yang diantrekan ulang/ditunda
        // akan dicetak lagi, dan cetak ulang TIDAK memotong stok: bila bahannya dikembalikan sekarang, pemakaian
        // cetak ulang itu tidak pernah tercatat (stok sistem lebih besar dari fisik, HPP hilang).
        if ($kembalikanStok && $statusBaru !== 'Batal') {
            return back()->with('error', 'Pengembalian bahan ke stok hanya untuk "Batal permanen". Item yang dikembalikan ke antrean atau ditunda akan dicetak lagi memakai bahan yang sama, jadi bahannya tetap tercatat terpakai.');
        }
        $ringkasanStok = [];
        $pesanError = null;

        DB::transaction(function () use ($request, $id, $statusBaru, $kembalikanStok, &$ringkasanStok, &$pesanError) {
            // lockForUpdate() + cek status DI DALAM transaction - klik ganda
            // "Batalkan" pada item yang sama sebelumnya bisa lolos cek basi
            // dan memicu StokReversalService::kembalikan() dua kali (stok
            // dikembalikan dobel karena service-nya tidak idempoten).
            $detail = DB::table('transaksi_detail')->where('id_detail', $id)->lockForUpdate()->first();

            if (!$detail) {
                $pesanError = 'Item SPK tidak ditemukan.';
                return;
            }

            if ($pesanError = self::pastikanOrderDiterima((int) $id)) {
                return;
            }

            if (in_array($detail->status_spk, ['Selesai', 'Batal'])) {
                $pesanError = 'Item ini sudah ' . $detail->status_spk . ', tidak bisa dibatalkan.';
                return;
            }

            // §9: tolak transisi SPK yang tidak sah sebelum ubah status.
            \App\Services\StatusTransitionGuard::validateStatusSpk((string) $detail->status_spk, $statusBaru);

            DB::table('transaksi_detail')->where('id_detail', $id)->update([
                'status_spk' => $statusBaru,
                'catatan_produksi' => $request->input('alasan_batal'),
            ]);
            \App\Services\AuditLogger::log(
                'updated', 'transaksi_detail', (int) $id,
                ['status_spk' => $detail->status_spk],
                ['status_spk' => $statusBaru, 'alasan' => $request->input('alasan_batal'), 'stok_dikembalikan' => (bool) $kembalikanStok],
                "SPK item #{$id} dibatalkan"
            );

            if (!$kembalikanStok) {
                return;
            }

            $kartuSebelum = (int) DB::table('kartu_stok')->max('id');
            // Item era snapshot: bahan yang dipotong tercatat di transaksi_detail_bahan_pakai dan HPP-nya = hpp_snapshot.
            $adaSnapshot = DB::table('transaksi_detail_bahan_pakai')->where('id_detail', $id)->exists();
            $ringkasanStok = StokReversalService::kembalikan(
                $detail,
                'BATAL-SPK-' . $id,
                'Pembatalan produksi (SPK #' . $id . ') - stok dikembalikan'
            );

            // Bahan yang kembali ke gudang bukan lagi HPP: nilainya kembali ke Persediaan. Nilainya HPP yang DULU dijurnal
            // untuk item ini (hpp_snapshot, dihitung dengan harga rata-rata saat order), bukan harga rata-rata hari ini;
            // kalau tidak, Persediaan dan HPP bergeser sebesar selisih harga. Nota lama tanpa snapshot memakai harga
            // terkini (tidak ada data lain). Referensi nomor nota, jadi pembatalan nota sesudahnya membalik sisa HPP-nya saja.
            $nilaiKembali = 0.0;
            if ($ringkasanStok !== []) {
                $nilaiKembali = $adaSnapshot
                    ? (float) $detail->hpp_snapshot
                    : (float) DB::table('kartu_stok as k')->join('bahan_baku as b', 'b.id_bahan', '=', 'k.id_bahan')
                        ->where('k.id', '>', $kartuSebelum)->where('k.tipe', 'IN')->where('k.referensi', 'BATAL-SPK-' . $id)
                        ->sum(DB::raw('k.qty * b.harga_rata_rata'));
            }
            $noNota = DB::table('transaksi')->where('id_transaksi', $detail->id_transaksi)->value('nomor_nota');
            // Hanya bila HPP nota ini memang pernah dijurnal (nota lama sebelum integrasi akuntansi tidak punya).
            $adaHpp = $noNota && DB::table('jurnal_umum')->where('no_referensi', $noNota)->where('kode_akun', '5000')->exists();
            if ($adaHpp) {
                \App\Services\AccountingService::postPembalikanHppItem($noNota, "Pembalikan HPP batal SPK #{$id} (bahan kembali ke stok)", $nilaiKembali);
            }
        });

        if ($pesanError) {
            return back()->with('error', $pesanError);
        }

        $labelTindakan = [
            'Antre'   => 'dikembalikan ke antrian SPK',
            'Pending' => 'ditandai Pending, menunggu keputusan lebih lanjut',
            'Batal'   => 'dibatalkan permanen',
        ][$statusBaru];

        $pesan = "Item SPK {$labelTindakan}.";
        if ($kembalikanStok) {
            $pesan .= !empty($ringkasanStok)
                ? ' Stok dikembalikan: ' . implode(', ', $ringkasanStok) . '.'
                : ' Tidak ada bahan BOM untuk dikembalikan pada item ini.';
        }

        return back()->with('success', $pesan);
    }

    /**
     * Polling notifikasi "ada item baru masuk Produksi" - operator produksi
     * tahu ada pekerjaan baru (dipindah dari SPK lewat "Ambil Pekerjaan")
     * tanpa manual refresh.
     */
    public function cekAntrianBaru()
    {
        $total = DB::table('transaksi_detail as d')
            ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->whereIn('d.status_spk', ['Desain', 'Cetak'])
            ->where('t.status_nota', 'Aktif')
            ->where('t.status_order', 'Diterima Kasir')
            ->count();
        return response()->json(['total' => $total]);
    }
}
