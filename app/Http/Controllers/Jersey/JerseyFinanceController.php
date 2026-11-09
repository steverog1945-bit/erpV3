<?php

namespace App\Http\Controllers\Jersey;

use App\Http\Controllers\Controller;
use App\Models\JerseyOrder;
use App\Models\JerseyOrderPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Modul Keuangan Jersey - piutang, DP, & pelunasan order jersey, TERPISAH
 * dari kas_harian & transaksi Kasir utama (permintaan eksplisit user: dua
 * arus uang ini sengaja tidak dicampur). Satu order bisa punya banyak baris
 * pembayaran (ledger) lewat jersey_order_payments - lihat
 * JerseyOrder::recalculatePembayaran() untuk bagaimana status_pembayaran &
 * kolom deposit (turunan, bukan lagi diisi manual) dihitung ulang tiap
 * ledger berubah.
 */
class JerseyFinanceController extends Controller
{
    public function index(Request $request)
    {
        $query = JerseyOrder::with(['konsumen', 'payments'])
            ->whereIn('status_pembayaran', ['Belum Bayar', 'DP'])
            ->orderByRaw("deadline IS NULL, deadline ASC");

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('order_number', 'like', "%$s%")
                    ->orWhere('customer_name', 'like', "%$s%");
            });
        }

        $piutang = $query->paginate(20)->withQueryString();

        // Ringkasan dashboard - dihitung dari SELURUH order (bukan cuma
        // halaman yang sedang tampil) supaya angkanya benar-benar total.
        // Dihitung DATABASE (satu query agregat), bukan memuat semua order ke PHP lalu sum/filter.
        $ringkas = JerseyOrder::whereIn('status_pembayaran', ['Belum Bayar', 'DP'])
            ->selectRaw('COALESCE(SUM(GREATEST(total - deposit, 0)), 0) as piutang')
            // Sama dengan JerseyOrder::getJatuhTempoAttribute(): deadline (kolom tanggal) hari ini atau lebih lama = telat.
            ->selectRaw('COALESCE(SUM(deadline IS NOT NULL AND deadline <= ?), 0) as telat', [now()->toDateString()])
            ->first();
        $totalPiutang = (float) $ringkas->piutang;
        $jumlahJatuhTempo = (int) $ringkas->telat;

        $awalBulan = now()->startOfMonth();
        $totalDiterimaBulanIni = JerseyOrderPayment::where('tanggal_bayar', '>=', $awalBulan)->sum('jumlah');

        return view('jersey.keuangan.index', compact('piutang', 'totalPiutang', 'jumlahJatuhTempo', 'totalDiterimaBulanIni'));
    }

    public function storePayment(Request $request, JerseyOrder $order)
    {
        $data = $request->validate([
            'tanggal_bayar' => ['required', 'date', new \App\Rules\TanggalTransaksi],
            'jumlah' => 'required|numeric|min:1',
            'uang_diterima' => 'nullable|numeric|min:0',
            'metode_bayar' => 'required|in:Tunai,Transfer,QRIS,Lainnya',
            // bukti_path HANYA boleh berasal dari uploadBukti() (folder & berkas
            // harus benar-benar ada) - bukan string bebas dari klien.
            'bukti_path' => ['nullable', 'string', 'max:255', 'regex:#^jersey_bukti_bayar/[A-Za-z0-9._-]+$#'],
            'keterangan' => 'nullable|string|max:500',
        ]);

        if (!empty($data['bukti_path']) && !\Illuminate\Support\Facades\Storage::disk('public')->exists($data['bukti_path'])) {
            return back()->withInput()->with('error', 'Berkas bukti bayar tidak ditemukan. Unggah ulang bukti bayarnya.');
        }

        try {
        $kembalian = 0.0;
        DB::transaction(function () use (&$data, &$order, &$kembalian) {
            // Audit H-1: gembok baris order DULU, baru hitung sisa & tulis.
            // Dua kasir / klik ganda untuk order yang sama kini antre; yang
            // kedua melihat sisa yang sudah berkurang dan ditolak, bukan
            // menghasilkan pembayaran + jurnal ganda.
            $order = JerseyOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $sudahDibayar = (float) $order->payments()->sum('jumlah');
            $sisa = round((float) $order->total - $sudahDibayar, 2);

            if ($sisa <= 0) {
                throw new \RuntimeException('Order ini sudah lunas - tidak ada sisa tagihan.');
            }
            // Tunai: uang fisik yang diserahkan (uang_diterima) boleh lebih
            // dari sisa tagihan; yang dicatat sebesar sisa, selebihnya kembalian.
            // Mendukung dua cara input: jumlah=uang diserahkan (test 5) atau
            // jumlah=sisa + uang_diterima=uang diserahkan (test 4).
            $uangDiterima = (float) ($data['uang_diterima'] ?? $data['jumlah']);
            if ($data['metode_bayar'] === 'Tunai' && $uangDiterima - $sisa > 0.004) {
                $kembalian = round($uangDiterima - $sisa, 2);
                $data['jumlah'] = $sisa;
            }
            if ((float) $data['jumlah'] - $sisa > 0.004) {
                throw new \RuntimeException('Jumlah melebihi sisa tagihan (Rp ' . number_format($sisa, 0, ',', '.') . '). Masukkan maksimal sebesar sisa tagihan.');
            }

            $payment = $order->payments()->create([
                'tanggal_bayar' => $data['tanggal_bayar'],
                'jumlah' => $data['jumlah'],
                'metode_bayar' => $data['metode_bayar'],
                'bukti_path' => $data['bukti_path'] ?? null,
                'keterangan' => $data['keterangan'] ?? null,
                'uang_diterima' => round((float) $data['jumlah'] + $kembalian, 2),
                'kembalian' => $kembalian,
                'created_by' => Auth::id(),
            ]);
            \App\Services\JerseyAccountingService::catatPembayaran($order, $payment);
            $order->recalculatePembayaran();
        });
        } catch (\RuntimeException $e) {
            // Mis. periode akuntansi sudah ditutup - seluruh transaksi
            // (termasuk baris pembayaran) sudah di-rollback.
            return back()->withInput()->with('error', \App\Support\PesanError::untuk($e, 'Pembayaran gagal dicatat'));
        }

        return back()->with('success', 'Pembayaran berhasil dicatat.' . ($kembalian > 0 ? ' Kembalian untuk pelanggan: Rp ' . number_format($kembalian, 0, ',', '.') . '.' : ''));
    }

    /**
     * Menghapus pembayaran = MENGAJUKAN void lewat Approval Workflow
     * (tipe_aksi 'void_pembayaran_jersey'); jurnalnya dibalik & status order
     * dihitung ulang baru saat disetujui.
     */
    public function destroyPayment(Request $request, JerseyOrderPayment $payment)
    {
        $request->validate(['alasan' => 'nullable|string|max:255']);

        $sudahAda = \App\Models\ApprovalRequest::where('tipe_aksi', 'void_pembayaran_jersey')
            ->where('referensi_id', $payment->id)->where('status', 'Menunggu')->exists();

        if ($sudahAda) {
            return back()->with('error', 'Pembayaran ini sudah punya pengajuan void yang menunggu.');
        }

        $alasan = $request->input('alasan') ?: 'Void pembayaran jersey';

        \App\Services\ApprovalService::ajukan(
            'void_pembayaran_jersey',
            'jersey_order_payments',
            $payment->id,
            ['alasan' => $alasan],
            "Void pembayaran jersey {$payment->order->order_number} (Rp " . number_format($payment->jumlah, 0, ',', '.') . ')',
            $alasan
        );

        return back()->with('success', 'Pengajuan void pembayaran dikirim untuk persetujuan Administrator.');
    }

    public function uploadBukti(Request $request)
    {
        $request->validate([
            'bukti' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $path = $request->file('bukti')->store('jersey_bukti_bayar', 'public');

        return response()->json([
            'status' => 'success',
            'path' => $path,
            'url' => asset('storage/' . $path),
        ]);
    }
}
