<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Services\QuotationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Quotation / Penawaran (LEVEL 2). Aturan bisnis & perhitungan harga ada di
 * App\Services\QuotationService.
 */
class QuotationController extends Controller
{
    public function index(Request $request)
    {
        $query = Quotation::orderByDesc('id');

        if ($request->filled('q')) {
            $cari = $request->q;
            $query->where(fn ($w) => $w->where('nomor', 'like', "%{$cari}%")->orWhere('nama_pelanggan', 'like', "%{$cari}%"));
        }

        if ($request->filled('status')) {
            if ($request->status === 'Kedaluwarsa') {
                $query->whereIn('status', ['Draft', 'Terkirim'])->where('berlaku_sampai', '<', now()->toDateString());
            } else {
                $query->where('status', $request->status);
            }
        }

        $daftar = $query->paginate(20)->withQueryString();

        return view('quotation.index', compact('daftar'));
    }

    public function create()
    {
        return view('quotation.form', $this->dataForm(null));
    }

    public function store(Request $request)
    {
        try {
            $q = QuotationService::simpan($this->validasi($request));
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', \App\Support\PesanError::untuk($e));
        }

        return redirect()->route('quotation.edit', $q->id)->with('success', "Penawaran {$q->nomor} disimpan sebagai Draft.");
    }

    public function edit(Quotation $quotation)
    {
        $quotation->load('items');

        return view('quotation.form', $this->dataForm($quotation));
    }

    public function update(Request $request, Quotation $quotation)
    {
        if ($quotation->status !== 'Draft') {
            return back()->with('error', 'Cuma penawaran berstatus Draft yang bisa diubah.');
        }

        try {
            QuotationService::simpan($this->validasi($request), $quotation);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', \App\Support\PesanError::untuk($e));
        }

        return back()->with('success', 'Penawaran diperbarui.');
    }

    public function kirim(Quotation $quotation)
    {
        try {
            QuotationService::kirim($quotation);
        } catch (\RuntimeException $e) {
            return back()->with('error', \App\Support\PesanError::untuk($e));
        }

        return back()->with('success', 'Penawaran ditandai terkirim ke pelanggan.');
    }

    public function tanggapi(Request $request, Quotation $quotation)
    {
        $request->validate(['keputusan' => 'required|in:Disetujui,Ditolak']);

        try {
            QuotationService::tanggapi($quotation, $request->keputusan);
        } catch (\RuntimeException $e) {
            return back()->with('error', \App\Support\PesanError::untuk($e));
        }

        if ($request->keputusan === 'Disetujui') {
            \App\Support\JalurLanjut::set('Pelanggan setuju - buat nota dari penawaran ini', 'quotation.buat-nota', 'Buat Nota dari Penawaran', ['quotation' => $quotation->id]);
        }

        return back()->with('success', "Tanggapan pelanggan dicatat: {$request->keputusan}.");
    }

    /**
     * Konversi penawaran yang disetujui pelanggan menjadi nota penjualan (transaksi)
     * beserta item-itemnya, stok, dan jurnal. Tidak perlu input ulang di keranjang.
     */
    public function buatNota(Request $request, Quotation $quotation)
    {
        if ($quotation->status !== 'Disetujui') {
            return back()->with('error', 'Hanya penawaran berstatus Disetujui yang bisa dibuat nota.');
        }
        if ($quotation->status_efektif === 'Kedaluwarsa') {
            return back()->with('error', 'Penawaran sudah kedaluwarsa - buat penawaran baru.');
        }

        $items = $quotation->items;
        if (!$items || $items->count() === 0) {
            return back()->with('error', 'Penawaran tidak memiliki item.');
        }

        $kasHariIni = \App\Services\KasSesiService::aktifUntukTransaksi();
        if (!$kasHariIni) {
            return back()->with('error', 'Kasir belum dibuka! Buka kas terlebih dahulu sebelum membuat order baru.');
        }

        $prefixNota = \App\Models\AppSetting::current()->nomor_nota_prefix ?: 'INV';
        $noNota = \App\Services\DocumentNumberService::generate($prefixNota);
        $tglOrder = now();

        $grandTotal = (float) $quotation->total;

        DB::beginTransaction();
        try {
            $idTransaksi = DB::table('transaksi')->insertGetId([
                'nomor_nota'        => $noNota,
                'tipe_order'        => 'Reguler',
                'id_konsumen'       => $quotation->id_konsumen,
                'id_user_kasir'     => Auth::id() ?? abort(401, 'Sesi login tidak valid.'),
                'tgl_order'         => $tglOrder,
                'total_bayar'       => $grandTotal,
                'status_pembayaran' => 'Belum Bayar',
                'status_nota'       => 'Aktif',
                'status_order'      => 'Menunggu Kasir',
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            $totalHppSemua = 0;

            foreach ($items as $item) {
                if ($item->tipe === 'Jasa') {
                    DB::table('transaksi_detail')->insert([
                        'id_transaksi'     => $idTransaksi,
                        'id_produk'        => null,
                        'id_jasa'          => $item->id_jasa,
                        'nama_file'        => $item->nama_item,
                        'p_ukuran'         => 0,
                        'l_ukuran'         => 0,
                        'qty'              => (int) $item->qty,
                        'biaya_cetak'      => 0,
                        'biaya_desain'     => (float) $item->biaya_desain,
                        'biaya_finishing'  => (float) $item->biaya_finishing,
                        'subtotal'         => (float) $item->subtotal,
                        'potongan'         => 0,
                        'hpp_snapshot'     => 0,
                        'harga_jual_snapshot' => (float) $item->subtotal,
                        'status_spk'       => 'Selesai',
                        'status_urgensi'   => 'Normal',
                        'status_finishing' => 'Standar',
                        'catatan_produksi' => $item->catatan,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                    continue;
                }

                // Produk
                $produk = \App\Models\Produk::where('id_produk', $item->id_produk)->first();
                if (!$produk) {
                    throw new \RuntimeException('Produk pada item "' . $item->nama_item . '" tidak ditemukan.');
                }

                $qtyHitung = (float) ($item->qty_hitung ?? $item->qty);
                $biayaCetak = (float) ($item->harga_satuan ?? 0) * $qtyHitung;
                $biayaDesain = (float) ($item->biaya_desain ?? 0);
                $biayaFinishing = (float) ($item->biaya_finishing ?? 0);
                $subtotal = (float) $item->subtotal;

                $idDetail = DB::table('transaksi_detail')->insertGetId([
                    'id_transaksi'     => $idTransaksi,
                    'id_produk'        => $item->id_produk,
                    'id_finishing'     => $item->id_finishing,
                    'biaya_finishing'  => $biayaFinishing,
                    'biaya_cetak'      => $biayaCetak,
                    'biaya_desain'     => $biayaDesain,
                    'file_siap_cetak'  => 0,
                    'id_desainer'      => null,
                    'nama_file'        => $item->nama_item,
                    'thumbnail'        => '',
                    'p_ukuran'         => (float) ($item->p_ukuran ?? 0),
                    'l_ukuran'         => (float) ($item->l_ukuran ?? 0),
                    'qty'              => (int) $item->qty,
                    'status_urgensi'   => 'Normal',
                    'status_finishing' => 'Standar',
                    'qty_cetak'        => null,
                    'catatan_produksi' => $item->catatan,
                    'subtotal'         => $subtotal,
                    'potongan'         => 0,
                    'hpp_snapshot'     => 0,
                    'harga_jual_snapshot' => $subtotal,
                    'status_spk'       => 'Antre',
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);

                // BOM + stok deduction (sama dengan PenjualanController::store)
                $boms = \App\Services\BomCalculationService::komponen(
                    (int) $item->id_produk,
                    (float) ($item->p_ukuran ?? 0),
                    (float) ($item->l_ukuran ?? 0),
                    (float) $item->qty
                );

                $hppItemIni = 0;
                foreach ($boms as $bom) {
                    $qtyPakai = (float) $bom->qty_pakai;
                    if ($qtyPakai <= 0) {
                        continue;
                    }
                    $bahan = DB::table('bahan_baku')
                        ->where('id_bahan', $bom->id_bahan)
                        ->lockForUpdate()
                        ->first();
                    if (!$bahan) {
                        continue;
                    }
                    $nilaiKonversi = (float) ($bahan->nilai_konversi ?? 1);
                    if ($nilaiKonversi <= 0) {
                        $nilaiKonversi = 1;
                    }
                    $qtyPakaiSatuanStok = $qtyPakai / $nilaiKonversi;
                    $stokSebelum = (float) ($bahan->stok ?? 0);
                    $stokSesudah = $stokSebelum - $qtyPakaiSatuanStok;
                    DB::table('bahan_baku')
                        ->where('id_bahan', $bom->id_bahan)
                        ->update(['stok' => $stokSesudah]);
                    DB::table('kartu_stok')->insert([
                        'id_bahan'     => $bom->id_bahan,
                        'tanggal'      => now(),
                        'tipe'         => 'OUT',
                        'qty'          => $qtyPakaiSatuanStok,
                        'stok_sebelum' => $stokSebelum,
                        'stok_sesudah' => $stokSesudah,
                        'referensi'    => $noNota . ' (dari penawaran)',
                        'keterangan'   => 'Pemakaian produksi order dari penawaran ' . $noNota,
                        'created_by'   => Auth::id(),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                    DB::table('transaksi_detail_bahan_pakai')->insert([
                        'id_detail'  => $idDetail,
                        'id_bahan'   => $bom->id_bahan,
                        'qty_pakai'  => $qtyPakaiSatuanStok,
                        'is_tinta'   => $bom->is_tinta ?? 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $hppItemIni += $qtyPakaiSatuanStok * (float) ($bahan->harga_rata_rata ?? 0);
                }

                if ($hppItemIni > 0) {
                    DB::table('transaksi_detail')->where('id_detail', $idDetail)->update(['hpp_snapshot' => $hppItemIni]);
                }
                $totalHppSemua += $hppItemIni;
            }

            // Update total_bayar + status pembayaran (belum ada pembayaran)
            DB::table('transaksi')
                ->where('id_transaksi', $idTransaksi)
                ->update(['total_bayar' => $grandTotal, 'updated_at' => now()]);

            // Jurnal pendapatan (sama dengan store(): postPenjualan)
            if ($grandTotal > 0) {
                \App\Services\AccountingService::postPenjualan($noNota . '-dari-penawaran', $grandTotal, $totalHppSemua);
            }

            // Tandai penawaran menjadi order
            $quotation->update(['status' => 'Menjadi Order', 'id_transaksi' => $idTransaksi]);

            DB::commit();

            return redirect()->route('penjualan.show', $idTransaksi)
                ->with('success', 'Nota berhasil dibuat dari penawaran ' . $quotation->nomor . '. Item dan stok telah dicatat.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', \App\Support\PesanError::untuk($e, 'Gagal membuat nota dari penawaran'));
        }
    }

    public function cetak(Quotation $quotation)
    {
        $quotation->load(['items', 'pembuat.karyawan']);

        return view('quotation.print', compact('quotation'));
    }

    public function destroy(Quotation $quotation)
    {
        if ($quotation->status !== 'Draft') {
            return back()->with('error', 'Cuma penawaran berstatus Draft yang bisa dihapus.');
        }

        $quotation->delete();

        return redirect()->route('quotation.index')->with('success', 'Penawaran dihapus.');
    }

    private function validasi(Request $request): array
    {
        return $request->validate([
            'tanggal' => 'required|date',
            'berlaku_sampai' => 'required|date|after_or_equal:tanggal',
            'id_konsumen' => 'nullable|exists:konsumen,id_konsumen',
            'nama_pelanggan' => 'nullable|string|max:150',
            'telepon' => 'nullable|string|max:30',
            'id_golongan' => 'nullable|exists:golongan_konsumen,id_golongan',
            'diskon' => 'nullable|numeric|min:0',
            'catatan' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.tipe' => 'required|in:Produk,Jasa',
            'items.*.id_produk' => 'nullable|exists:produk,id_produk',
            'items.*.id_jasa' => 'nullable|exists:jasa,id_jasa',
            'items.*.id_finishing' => 'nullable|exists:finishing,id_finishing',
            'items.*.p' => 'nullable|numeric|min:0',
            'items.*.l' => 'nullable|numeric|min:0',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.harga_satuan' => 'nullable|numeric|min:0',
            'items.*.biaya_finishing' => 'nullable|numeric|min:0',
            'items.*.biaya_desain' => 'nullable|numeric|min:0',
            'items.*.catatan' => 'nullable|string|max:255',
        ]);
    }

    private function dataForm(?Quotation $quotation): array
    {
        return [
            'quotation' => $quotation,
            'produkList' => DB::table('produk')->where('status', 'aktif')->orderBy('nama_produk')->get(['id_produk', 'nama_produk', 'metode_hitung']),
            'jasaList' => DB::table('jasa')->orderBy('nama_jasa')->get(['id_jasa', 'nama_jasa']),
            'finishingList' => DB::table('finishing')->orderBy('nama_finishing')->get(['id_finishing', 'nama_finishing']),
            'konsumenList' => DB::table('konsumen')->orderBy('nama_konsumen')->get(['id_konsumen', 'nama_konsumen', 'telepon', 'id_golongan']),
            'golonganList' => DB::table('golongan_konsumen')->orderBy('nama_golongan')->get(),
        ];
    }
}
