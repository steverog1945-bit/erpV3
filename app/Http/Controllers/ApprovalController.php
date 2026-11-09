<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Services\ApprovalService;
use Illuminate\Http\Request;

/**
 * Orkestrator generik Approval Workflow - lihat App\Services\
 * ApprovalService untuk siklus hidup pengajuan/keputusannya. Controller
 * ini yang tahu cara MENGEKSEKUSI tiap tipe_aksi (peta di eksekusi()) -
 * ApprovalService sendiri sengaja tidak tahu apa-apa soal logic bisnis
 * tiap jenis aksi.
 *
 * Menambah jenis approval baru ke depannya = tambah 1 case di eksekusi()
 * + panggil ApprovalService::ajukan() dari controller terkait (lihat
 * contoh PenjualanController::batalkanNota()).
 */
class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $query = ApprovalRequest::with(['requester', 'decider'])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'Menunggu'); // default: cuma yang perlu ditindak
        }

        $requests = $query->paginate(20)->withQueryString();

        return view('approval.index', compact('requests'));
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest)
    {
        $request->validate(['catatan' => 'nullable|string|max:500']);

        try {
            ApprovalService::setujui(
                $approvalRequest,
                fn (array $payload) => $this->eksekusi($approvalRequest, $payload),
                $request->input('catatan')
            );
        } catch (\Throwable $e) {
            return back()->with('error', \App\Support\PesanError::untuk($e, 'Gagal menyetujui'));
        }

        if ($approvalRequest->tipe_aksi === 'purchase_request') {
            \App\Support\JalurLanjut::set('PR disetujui - lanjutkan dengan membuat Purchase Order ke supplier', 'purchase-order.create', 'Buat Purchase Order');
        }

        return back()->with('success', 'Pengajuan disetujui & dieksekusi.');
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest)
    {
        $request->validate(['catatan' => 'required|string|max:500']);

        try {
            ApprovalService::tolak(
                $approvalRequest,
                $request->input('catatan'),
                fn (array $payload) => $this->eksekusiPenolakan($approvalRequest)
            );
        } catch (\Throwable $e) {
            return back()->with('error', \App\Support\PesanError::untuk($e, 'Gagal menolak'));
        }

        return back()->with('success', 'Pengajuan ditolak.');
    }

    /**
     * Peta tipe_aksi -> logic eksekusi sungguhan. Dipanggil DI DALAM
     * transaction ApprovalService::setujui() - kalau method ini
     * melempar exception, status approval TIDAK ikut berubah jadi
     * Disetujui (rollback bersama).
     */
    private function eksekusi(ApprovalRequest $req, array $payload): void
    {
        match ($req->tipe_aksi) {
            'batalkan_nota' => app(PenjualanController::class)->eksekusiPembatalanNota((int) $req->referensi_id, $payload),
            'stock_opname' => \App\Services\StockOpnameService::eksekusiAdjustment((int) $req->referensi_id),
            'purchase_request' => \App\Services\PurchaseRequestService::eksekusiPersetujuan((int) $req->referensi_id),
            'waste_abnormal' => \App\Services\WasteService::terapkan((int) $req->referensi_id),
            'penyesuaian_stok_manual' => \App\Services\PenyesuaianStokService::eksekusi($payload),
            'batalkan_kas_kecil' => \App\Services\KasKecilService::batalkan((int) $req->referensi_id),
            'void_pembayaran_jersey' => \App\Services\JerseyAccountingService::voidPembayaran((int) $req->referensi_id, $payload['alasan'] ?? '-'),
            'batalkan_order_jersey' => \App\Services\JerseyAccountingService::batalkanOrder((int) $req->referensi_id, $payload['alasan'] ?? '-'),
            'batalkan_pembelian' => \App\Services\PembayaranHutangService::batalkanFaktur((int) $req->referensi_id, $payload['alasan'] ?? '-'),
            'void_pembayaran_hutang' => \App\Services\PembayaranHutangService::voidPembayaran((int) $req->referensi_id, $payload['alasan'] ?? '-'),
            default => throw new \RuntimeException("Tipe aksi '{$req->tipe_aksi}' tidak dikenali - belum ada logic eksekusinya di ApprovalController::eksekusi()."),
        };
    }

    /**
     * Peta tipe_aksi -> logic tambahan saat DITOLAK. Kebanyakan tipe_aksi
     * (mis. batalkan_nota) tidak butuh apa-apa - entitasnya sudah diam di
     * status semula. Cuma tipe_aksi yang entitasnya butuh status eksplisit
     * "Ditolak" (mis. Stock Opname, supaya tidak nyangkut selamanya di
     * Diajukan) yang perlu case di sini.
     */
    private function eksekusiPenolakan(ApprovalRequest $req): void
    {
        match ($req->tipe_aksi) {
            'stock_opname' => \App\Services\StockOpnameService::eksekusiPenolakan((int) $req->referensi_id),
            'purchase_request' => \App\Services\PurchaseRequestService::eksekusiPenolakan((int) $req->referensi_id),
            'waste_abnormal' => \App\Services\WasteService::tolak((int) $req->referensi_id),
            default => null,
        };
    }
}
