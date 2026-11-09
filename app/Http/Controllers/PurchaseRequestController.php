<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequest;
use App\Services\ApprovalService;
use App\Services\DocumentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Purchase Request (roadmap LEVEL 1, bagian 10 dokumen audit): permintaan
 * pembelian dari gudang/operasional. Draft -> Diajukan (lewat generic
 * ApprovalService, tipe_aksi 'purchase_request') -> Disetujui/Ditolak ->
 * dijadikan PO (status jadi 'Diproses PO').
 */
class PurchaseRequestController extends Controller
{
    public function index(Request $request)
    {
        // Isi dan supplier ikut dimuat sekaligus (tanpa query per baris) untuk kolom Isi dan Supplier di daftar.
        $query = PurchaseRequest::with(['pembuat', 'details.bahan', 'supplierRencana', 'purchaseOrders.supplier'])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $daftar = $query->paginate(20)->withQueryString();

        return view('purchase-request.index', compact('daftar'));
    }

    public function create()
    {
        return view('purchase-request.form', [
            'pr' => null,
            'supplierList' => $this->supplierList(),
            'bahanList' => $this->bahanAktif(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validasi($request);

        $pr = DB::transaction(function () use ($validated) {
            $pr = PurchaseRequest::create([
                'nomor' => DocumentNumberService::generate('PR'),
                'tanggal' => $validated['tanggal'],
                'status' => 'Draft',
                'catatan' => $validated['catatan'] ?? null,
                'id_supplier_rencana' => $validated['id_supplier_rencana'] ?? null,
                'id_user_pembuat' => Auth::id(),
            ]);

            $this->simpanBaris($pr, $validated['items']);

            return $pr;
        });

        return redirect()->route('purchase-request.edit', $pr->id)->with('success', "Purchase Request {$pr->nomor} disimpan sebagai Draft.");
    }

    public function edit(PurchaseRequest $purchaseRequest)
    {
        $purchaseRequest->load('details.bahan');

        return view('purchase-request.form', [
            'pr' => $purchaseRequest,
            'supplierList' => $this->supplierList(),
            'bahanList' => $this->bahanAktif(),
        ]);
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'Draft') {
            return back()->with('error', 'Cuma Purchase Request berstatus Draft yang bisa diubah.');
        }

        $validated = $this->validasi($request);

        DB::transaction(function () use ($validated, $purchaseRequest) {
            $purchaseRequest->update([
                'tanggal' => $validated['tanggal'],
                'catatan' => $validated['catatan'] ?? null,
                'id_supplier_rencana' => $validated['id_supplier_rencana'] ?? null,
            ]);
            $purchaseRequest->details()->delete();
            $this->simpanBaris($purchaseRequest, $validated['items']);
        });

        return back()->with('success', 'Purchase Request diperbarui.');
    }

    public function ajukan(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'Draft') {
            return back()->with('error', 'Purchase Request ini sudah tidak berstatus Draft.');
        }

        if ($purchaseRequest->details()->count() === 0) {
            return back()->with('error', 'Purchase Request belum punya item.');
        }

        DB::transaction(function () use ($purchaseRequest) {
            ApprovalService::ajukan(
                'purchase_request',
                'purchase_requests',
                $purchaseRequest->id,
                [],
                "Purchase Request {$purchaseRequest->nomor} ({$purchaseRequest->details()->count()} item)",
                $purchaseRequest->catatan
            );

            $purchaseRequest->update(['status' => 'Diajukan']);
        });

        return redirect()->route('purchase-request.index')->with('success', "Purchase Request {$purchaseRequest->nomor} diajukan untuk persetujuan.");
    }

    public function destroy(PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'Draft') {
            return back()->with('error', 'Cuma Purchase Request berstatus Draft yang bisa dihapus.');
        }

        $purchaseRequest->delete();

        return redirect()->route('purchase-request.index')->with('success', 'Purchase Request dihapus.');
    }

    private function validasi(Request $request): array
    {
        // Pesan & nama atribut bahasa Indonesia (aplikasi ini belum memakai
        // lang/ id, dan APP_LOCALE=en - kalau kelak pindah ke lang file,
        // blok messages/attributes ini tinggal dihapus).
        return $request->validate([
            'tanggal' => 'required|date',
            'catatan' => 'nullable|string|max:1000',
            'id_supplier_rencana' => 'nullable|exists:supplier,id_supplier',
            'items' => 'required|array|min:1',
            'items.*.id_bahan' => 'required|exists:bahan_baku,id_bahan',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.catatan' => 'nullable|string|max:255',
        ], [
            'tanggal.required' => 'Tanggal wajib diisi.',
            'tanggal.date' => 'Tanggal tidak valid.',
            'items.required' => 'Minimal satu item bahan harus diisi.',
            'items.*.id_bahan.required' => 'Bahan pada tiap baris item wajib dipilih.',
            'items.*.id_bahan.exists' => 'Bahan yang dipilih tidak ditemukan.',
            'items.*.qty.required' => 'Qty pada tiap baris item wajib diisi.',
            'items.*.qty.min' => 'Qty pada tiap baris item minimal 0.01.',
            'items.*.catatan.max' => 'Catatan item maksimal 255 karakter.',
        ], [
            'items.*.id_bahan' => 'bahan',
            'items.*.qty' => 'qty',
            'items.*.catatan' => 'catatan item',
        ]);
    }

    private function simpanBaris(PurchaseRequest $pr, array $items): void
    {
        $rows = collect($items)->map(fn ($i) => [
            'id_purchase_request' => $pr->id,
            'id_bahan' => $i['id_bahan'],
            'qty' => $i['qty'],
            'catatan' => $i['catatan'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        DB::table('purchase_request_details')->insert($rows);
    }

    private function supplierList()
    {
        return DB::table('supplier')->orderBy('nama_supplier')->get(['id_supplier', 'nama_supplier']);
    }

    private function bahanAktif()
    {
        return DB::table('bahan_baku')->where('status', 'aktif')->orderBy('nama_bahan')->get(['id_bahan', 'nama_bahan', 'satuan_stok', 'stok']);
    }
}
