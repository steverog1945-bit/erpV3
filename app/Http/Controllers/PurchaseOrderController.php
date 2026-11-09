<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Services\DocumentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchase Order (roadmap LEVEL 1, bagian 11 dokumen audit). Draft ->
 * Dipesan (diterbitkan ke supplier) -> Diterima Sebagian / Selesai
 * (diisi saat Kedatangan Bahan dikaitkan ke PO - tahap berikutnya) atau
 * Dibatalkan. Bisa dibuat dari Purchase Request yang sudah Disetujui.
 */
class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseOrder::with(['supplier', 'pembuat'])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $daftar = $query->paginate(20)->withQueryString();

        return view('purchase-order.index', compact('daftar'));
    }

    public function create(Request $request)
    {
        $prefill = [];

        if ($request->filled('from_pr')) {
            $pr = PurchaseRequest::with('details')->findOrFail($request->from_pr);

            if ($pr->status !== 'Disetujui') {
                return redirect()->route('purchase-request.index')->with('error', "Purchase Request {$pr->nomor} belum berstatus Disetujui.");
            }

            $bahanIds = $pr->details->pluck('id_bahan');

            // Harga satuan diambil dari PO TERAKHIR per bahan (PO dibatalkan
            // dikecualikan) - pembeli tidak perlu mengingat/mencari harga;
            // angka ini cuma default yang tetap boleh diubah sebelum simpan.
            $hargaTerakhir = DB::table('purchase_order_details as pod')
                ->join('purchase_orders as po', 'po.id', '=', 'pod.id_purchase_order')
                ->whereIn('pod.id_bahan', $bahanIds)
                ->where('po.status', '!=', 'Dibatalkan')
                ->orderByDesc('po.id')
                ->get(['pod.id_bahan', 'pod.harga'])
                ->unique('id_bahan')
                ->pluck('harga', 'id_bahan');

            // Supplier dipilih otomatis: yang paling sering memasok bahan-bahan
            // dalam PR ini menurut histori PO (kalau belum ada histori, kosong
            // dan pembeli memilih manual).
            $idSupplier = DB::table('purchase_order_details as pod')
                ->join('purchase_orders as po', 'po.id', '=', 'pod.id_purchase_order')
                ->whereIn('pod.id_bahan', $bahanIds)
                ->where('po.status', '!=', 'Dibatalkan')
                ->select('po.id_supplier', DB::raw('count(*) as total'))
                ->groupBy('po.id_supplier')
                ->orderByDesc('total')
                ->value('po.id_supplier');

            $prefill = [
                'id_purchase_request' => $pr->id,
                'nomor_pr' => $pr->nomor,
                'id_supplier' => $idSupplier,
                // Catatan & harga per item PR ikut terbawa (qty belum
                // dikurangi sisa PR yang sudah jadi PO - lihat catatan create()).
                'items' => $pr->details->map(fn ($d) => [
                    'id_bahan' => $d->id_bahan,
                    'qty' => $d->qty,
                    'harga' => (float) ($hargaTerakhir[$d->id_bahan] ?? 0),
                    'catatan' => $d->catatan,
                ])->all(),
            ];
            // Supplier yang direncanakan di PR menjadi isian awal; pembeli tetap bisa menggantinya.
            $prefill['id_supplier'] = $pr->id_supplier_rencana ?: $idSupplier;
        }

        return view('purchase-order.form', $this->dataForm(null) + ['prefill' => $prefill]);
    }

    public function store(Request $request)
    {
        $validated = $this->validasi($request);
        $hitung = $this->hitung($validated);

        $po = DB::transaction(function () use ($validated, $hitung, $request) {
            $idPr = $request->input('id_purchase_request');

            if ($idPr) {
                $pr = PurchaseRequest::whereKey($idPr)->lockForUpdate()->firstOrFail();

                if ($pr->status !== 'Disetujui') {
                    throw ValidationException::withMessages(['id_purchase_request' => "Purchase Request {$pr->nomor} tidak lagi berstatus Disetujui."]);
                }

                $pr->update(['status' => 'Diproses PO']);
            }

            $po = PurchaseOrder::create([
                'nomor' => DocumentNumberService::generate('PO'),
                'id_supplier' => $validated['id_supplier'],
                'id_purchase_request' => $idPr,
                'tanggal' => $validated['tanggal'],
                'tgl_pengiriman_estimasi' => $validated['tgl_pengiriman_estimasi'] ?? null,
                'termin_pembayaran' => $validated['termin_pembayaran'] ?? null,
                'status' => 'Draft',
                'subtotal' => $hitung['subtotal'],
                'diskon' => $hitung['diskon'],
                'pajak_persen' => $hitung['pajak_persen'],
                'ongkir' => $hitung['ongkir'],
                'total' => $hitung['total'],
                'catatan' => $validated['catatan'] ?? null,
                'id_user_pembuat' => Auth::id(),
            ]);

            $this->simpanBaris($po, $hitung['items']);

            return $po;
        });

        return redirect()->route('purchase-order.edit', $po->id)->with('success', "Purchase Order {$po->nomor} disimpan sebagai Draft.");
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load('details.bahan', 'supplier', 'purchaseRequest', 'kedatangan');

        return view('purchase-order.form', $this->dataForm($purchaseOrder) + ['prefill' => []]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'Draft') {
            return back()->with('error', 'Cuma Purchase Order berstatus Draft yang bisa diubah.');
        }

        if (\App\Support\VersiBaris::bentrok($request, $purchaseOrder->fresh())) {
            return back()->withInput()->with('error', \App\Support\VersiBaris::pesan($purchaseOrder->fresh()));
        }

        $validated = $this->validasi($request);
        $hitung = $this->hitung($validated);

        DB::transaction(function () use ($validated, $hitung, $purchaseOrder) {
            $purchaseOrder->update([
                'id_supplier' => $validated['id_supplier'],
                'tanggal' => $validated['tanggal'],
                'tgl_pengiriman_estimasi' => $validated['tgl_pengiriman_estimasi'] ?? null,
                'termin_pembayaran' => $validated['termin_pembayaran'] ?? null,
                'subtotal' => $hitung['subtotal'],
                'diskon' => $hitung['diskon'],
                'pajak_persen' => $hitung['pajak_persen'],
                'ongkir' => $hitung['ongkir'],
                'total' => $hitung['total'],
                'catatan' => $validated['catatan'] ?? null,
            ]);

            $purchaseOrder->details()->delete();
            $this->simpanBaris($purchaseOrder, $hitung['items']);
        });

        return back()->with('success', 'Purchase Order diperbarui.');
    }

    public function terbitkan(PurchaseOrder $purchaseOrder)
    {
        $ok = DB::transaction(function () use ($purchaseOrder) {
            $po = PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();

            if ($po->status !== 'Draft') {
                return false;
            }

            $po->update(['status' => 'Dipesan']);

            return true;
        });

        if ($ok) {
            \App\Support\JalurLanjut::set('Saat barang tiba, catat kedatangannya dari PO ini', 'purchase-order.edit', 'Buka PO', [$purchaseOrder->id]);
        }

        return $ok
            ? back()->with('success', "Purchase Order {$purchaseOrder->nomor} diterbitkan ke supplier.")
            : back()->with('error', 'Purchase Order harus berstatus Draft untuk diterbitkan.');
    }

    public function batalkan(PurchaseOrder $purchaseOrder)
    {
        $galat = DB::transaction(function () use ($purchaseOrder) {
            $po = PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();

            if (!in_array($po->status, ['Draft', 'Dipesan'], true)) {
                return "Purchase Order berstatus {$po->status} tidak bisa dibatalkan.";
            }

            if ($po->details()->where('qty_diterima', '>', 0)->exists()) {
                return 'Purchase Order ini sudah ada barang yang diterima, tidak bisa dibatalkan.';
            }

            // Kedatangan Draft dari PO ini masih bisa divalidasi belakangan dan menambah stok dari PO yang sudah batal.
            $draft = DB::table('kedatangan_bahan')->where('id_purchase_order', $po->id)->where('status', '!=', 'Selesai')->pluck('no_surat_jalan');
            if ($draft->isNotEmpty()) {
                return 'Purchase Order ini masih punya kedatangan yang belum divalidasi (' . $draft->implode(', ') . '). Hapus kedatangan itu dulu di Kedatangan Bahan, baru PO bisa dibatalkan.';
            }

            $po->update(['status' => 'Dibatalkan']);
            $this->lepaskanPr($po);

            return null;
        });

        return $galat ? back()->with('error', $galat) : back()->with('success', "Purchase Order {$purchaseOrder->nomor} dibatalkan.");
    }

    /**
     * Buat Kedatangan Bahan (Draft) dari sisa qty PO yang belum diterima.
     * Stok baru bertambah saat kedatangan itu divalidasi (alur lama di
     * KedatanganBahanController) - qty_diterima & status PO ikut tersinkron
     * lewat PurchaseOrderService::sinkronPenerimaan().
     */
    public function catatKedatangan(Request $request, PurchaseOrder $purchaseOrder)
    {
        $v = $request->validate([
            'no_surat_jalan' => 'required|string|max:255|unique:kedatangan_bahan,no_surat_jalan',
            'tgl_datang' => ['required', 'date', new \App\Rules\TanggalTransaksi],
            'id_gudang' => 'nullable|exists:gudang,id_gudang',
        ]);

        $hasil = DB::transaction(function () use ($purchaseOrder, $v) {
            $po = PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();

            if (!in_array($po->status, ['Dipesan', 'Diterima Sebagian'], true)) {
                return ['error' => "Purchase Order berstatus {$po->status} tidak bisa menerima barang."];
            }

            // Sisa PO dihitung dari kedatangan yang SUDAH divalidasi. Draft yang masih menunggu berisi sisa yang sama,
            // jadi Draft kedua akan memasukkan stok dua kali bila keduanya divalidasi.
            $draft = DB::table('kedatangan_bahan')->where('id_purchase_order', $po->id)->where('status', '!=', 'Selesai')->value('no_surat_jalan');
            if ($draft) {
                return ['error' => "Masih ada kedatangan {$draft} dari PO ini yang belum divalidasi. Validasi (atau hapus) kedatangan itu dulu, baru catat kedatangan berikutnya."];
            }

            $sisa = $po->details()->whereRaw('qty > qty_diterima')->get();

            if ($sisa->isEmpty()) {
                return ['error' => 'Semua item Purchase Order ini sudah diterima lengkap.'];
            }

            $kedatangan = \App\Models\KedatanganBahan::create([
                'no_surat_jalan' => $v['no_surat_jalan'],
                'id_supplier' => $po->id_supplier,
                'id_gudang' => $v['id_gudang'] ?? DB::table('gudang')->value('id_gudang'),
                'tgl_datang' => $v['tgl_datang'],
                'status' => 'Draft',
                'keterangan' => "Penerimaan dari {$po->nomor}",
                'id_purchase_order' => $po->id,
            ]);

            $satuan = DB::table('bahan_baku')->whereIn('id_bahan', $sisa->pluck('id_bahan'))->pluck('satuan_stok', 'id_bahan');

            DB::table('kedatangan_bahan_detail')->insert($sisa->map(fn ($d) => [
                'id_kedatangan' => $kedatangan->id_kedatangan,
                'id_bahan' => $d->id_bahan,
                'qty' => $d->qty - $d->qty_diterima,
                'satuan' => $satuan[$d->id_bahan] ?? '',
                'status' => 'OK',
            ])->all());

            return ['id' => $kedatangan->id_kedatangan];
        });

        if (isset($hasil['error'])) {
            return back()->with('error', $hasil['error']);
        }

        \App\Support\JalurLanjut::set('Cek qty fisik lalu validasi agar stok bertambah', 'kedatangan-bahan.edit', 'Buka Kedatangan', [$hasil['id']]);
        return redirect()->route('kedatangan-bahan.edit', $hasil['id'])
            ->with('success', 'Kedatangan Draft dibuat dari sisa PO. Cek qty fisik, lalu validasi supaya stok bertambah.');
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'Draft') {
            return back()->with('error', 'Cuma Purchase Order berstatus Draft yang bisa dihapus.');
        }

        DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder->delete();
            $this->lepaskanPr($purchaseOrder);
        });

        return redirect()->route('purchase-order.index')->with('success', 'Purchase Order dihapus.');
    }

    /**
     * PR yang PO-nya dibatalkan/dihapus kembali ke Disetujui supaya bisa dibuatkan PO pengganti tanpa persetujuan
     * ulang. Sebelumnya PR tertahan di 'Diproses PO' selamanya, padahal PO baru hanya bisa dibuat dari PR Disetujui.
     * Tetap 'Diproses PO' bila masih ada PO lain yang aktif untuk PR itu.
     */
    private function lepaskanPr(PurchaseOrder $po): void
    {
        if (!$po->id_purchase_request) {
            return;
        }

        $pr = PurchaseRequest::whereKey($po->id_purchase_request)->lockForUpdate()->first();
        if (!$pr || $pr->status !== 'Diproses PO') {
            return;
        }

        $masihAda = PurchaseOrder::where('id_purchase_request', $pr->id)
            ->where('id', '!=', $po->id)
            ->where('status', '!=', 'Dibatalkan')
            ->exists();
        if (!$masihAda) {
            $pr->update(['status' => 'Disetujui']);
        }
    }

    private function validasi(Request $request): array
    {
        // Pesan & nama atribut bahasa Indonesia (sama seperti PurchaseRequestController).
        return $request->validate([
            'id_supplier' => 'required|exists:supplier,id_supplier',
            'tanggal' => ['required', 'date', new \App\Rules\TanggalTransaksi],
            'tgl_pengiriman_estimasi' => 'nullable|date|after_or_equal:tanggal',
            'termin_pembayaran' => 'nullable|string|max:100',
            'diskon' => 'nullable|numeric|min:0',
            'pajak_persen' => 'nullable|numeric|min:0|max:100',
            'ongkir' => 'nullable|numeric|min:0',
            'catatan' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.id_bahan' => 'required|exists:bahan_baku,id_bahan',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.harga' => 'required|numeric|min:0',
            'items.*.catatan' => 'nullable|string|max:255',
        ], [
            'id_supplier.required' => 'Supplier wajib dipilih.',
            'id_supplier.exists' => 'Supplier yang dipilih tidak ditemukan.',
            'tanggal.required' => 'Tanggal PO wajib diisi.',
            'tanggal.date' => 'Tanggal PO tidak valid.',
            'tgl_pengiriman_estimasi.after_or_equal' => 'Estimasi kedatangan tidak boleh sebelum tanggal PO.',
            'items.required' => 'Minimal satu item bahan harus diisi.',
            'items.*.id_bahan.required' => 'Bahan pada tiap baris item wajib dipilih.',
            'items.*.id_bahan.exists' => 'Bahan yang dipilih tidak ditemukan.',
            'items.*.qty.required' => 'Qty pada tiap baris item wajib diisi.',
            'items.*.qty.min' => 'Qty pada tiap baris item minimal 0.01.',
            'items.*.harga.required' => 'Harga satuan pada tiap baris item wajib diisi.',
            'items.*.harga.min' => 'Harga satuan tidak boleh negatif.',
            'items.*.catatan.max' => 'Catatan item maksimal 255 karakter.',
        ], [
            'id_supplier' => 'supplier',
            'items.*.id_bahan' => 'bahan',
            'items.*.qty' => 'qty',
            'items.*.harga' => 'harga satuan',
            'items.*.catatan' => 'catatan item',
        ]);
    }

    /** Total dihitung di server - angka total dari form tidak pernah dipercaya. */
    private function hitung(array $v): array
    {
        $items = collect($v['items'])->map(fn ($i) => [
            'id_bahan' => $i['id_bahan'],
            'qty' => (float) $i['qty'],
            'harga' => (float) $i['harga'],
            'subtotal' => round((float) $i['qty'] * (float) $i['harga'], 2),
            'catatan' => $i['catatan'] ?? null,
        ])->all();

        $subtotal = round(array_sum(array_column($items, 'subtotal')), 2);
        $diskon = round((float) ($v['diskon'] ?? 0), 2);

        if ($diskon > $subtotal) {
            throw ValidationException::withMessages(['diskon' => 'Diskon tidak boleh melebihi subtotal.']);
        }

        $pajakPersen = (float) ($v['pajak_persen'] ?? 0);
        $pajak = round(($subtotal - $diskon) * $pajakPersen / 100, 2);
        $ongkir = round((float) ($v['ongkir'] ?? 0), 2);

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'diskon' => $diskon,
            'pajak_persen' => $pajakPersen,
            'ongkir' => $ongkir,
            'total' => round($subtotal - $diskon + $pajak + $ongkir, 2),
        ];
    }

    private function simpanBaris(PurchaseOrder $po, array $items): void
    {
        $rows = collect($items)->map(fn ($i) => $i + [
            'id_purchase_order' => $po->id,
            'qty_diterima' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        DB::table('purchase_order_details')->insert($rows);
    }

    private function dataForm(?PurchaseOrder $po): array
    {
        return [
            'po' => $po,
            'gudangList' => DB::table('gudang')->orderBy('id_gudang')->get(),
            'supplierList' => DB::table('supplier')->orderBy('nama_supplier')->get(),
            'bahanList' => DB::table('bahan_baku')->where('status', 'aktif')->orderBy('nama_bahan')->get(['id_bahan', 'nama_bahan', 'satuan_stok']),
        ];
    }
}
