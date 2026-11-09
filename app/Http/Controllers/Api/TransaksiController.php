<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PenjualanCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Transaksi Penjualan - API Publik.
 *
 * GET /api/transaksi - Daftar nota (dibatasi milik user yang login)
 * GET /api/transaksi/{id} - Detail nota
 * POST /api/transaksi - Checkout (API-3)
 */

class TransaksiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = DB::table('transaksi')
            ->select('id_transaksi', 'nomor_nota', 'tgl_order', 'total_bayar', 'status_pembayaran', 'status_nota', 'status_order', 'id_konsumen')
            ->where('id_user_kasir', $user->id_user)
            ->orderByDesc('id_transaksi');

        if ($request->filled('status_nota')) {
            $query->where('status_nota', $request->status_nota);
        }

        $rows = $query->paginate(20);

        return response()->json([
            'status' => 'success',
            'data'   => $rows->items(),
            'meta'   => [
                'current_page' => $rows->currentPage(),
                'last_page'    => $rows->lastPage(),
                'per_page'     => $rows->perPage(),
                'total'        => $rows->total(),
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        $row = DB::table('transaksi')
            ->where('id_transaksi', $id)
            ->where('id_user_kasir', $user->id_user)
            ->first();

        if (!$row) {
            return response()->json(['error' => 'Transaksi tidak ditemukan.'], 404);
        }

        // Detail item
        $items = DB::table('transaksi_detail')
            ->where('id_transaksi', $id)
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => array_merge((array) $row, ['items' => $items]),
        ]);
    }

    /*
    |=====================================================================
    | API-3: CHECKOUT via jalur bisnis yang SAMA dengan web
    |=====================================================================
    |
    | PenjualanCheckoutService (sumber kebenaran checkout web) dipakai
    | langsung - BUKAN satu implementasi checkout kedua. Adapter hanya:
    |  1. menegakkan gate param (orion_api.checkout.enabled, default OFF,
    |     terpisah dari gate baca),
    |  2. mengidentifikasi aktor dari TOKEN (auth()->user), bukan sesi web,
    |  3. menegakkan whitelist payload ketat (order Reguler, produk
    |     sederhana; jasa/susulan/finishing/koreksi/tipe PO ditolak 422
    |     eksplisit - tidak diproses sebagian),
    |  4. menghitung id_golongan dari konsumen di DB (request tidak bisa
    |     memalsukan golongan harga) dan menandai konteks jalur 'api' agar
    |     harga selalu hasil hitungan server,
    |  5. menjaga idempotensi X-Submit-Id yang tahan-crash: klaim lewat
    |     UNIQUE (id_user, submit_id) di tabel api_checkout_idempotensi,
    |     finalisasi klaim DALAM transaksi DB yang sama dengan nota,
    |     detail, stok, kartu stok, dan jurnal.
    |
    | Tidak ada sesi/cookie/flash/CSRF web yang menjadi prasyarat logika
    | bisnis di sini.
    */
    private const KUNCI_ITEM_API = ['id_produk', 'qty_input', 'p', 'l', 'nama_file', 'catatan_produksi', 'status_urgensi'];

    public function store(Request $request)
    {
        // ===================== 1. Gate checkout =====================
        // Pesan 503 ini KONTRAK dari guard owner (ApiCheckoutGuardTest):
        // meski gate baca menyala, POST tetap tertutup sampai cheout gate
        // pula disetel - default OFF di semua lingkungan.
        if (!config('orion_api.checkout.enabled', false)) {
            return response()->json(['message' => 'Checkout API belum tersedia; gunakan alur ERP web.'], 503)
                ->header('Cache-Control', 'no-store');
        }

        $user = $request->user();

        // Whitelist top-level ketat: hanya id_konsumen, items, tipe_order.
        // Field lain (id_golongan, susulan_id, quotation_id, no_po, dsb.)
        // ditolak 422 - nilai final selalu ditarik dari DB/kanal web.
        $takDidukungTop = array_diff(array_keys($request->all()), ['id_konsumen', 'items', 'tipe_order']);
        if ($takDidukungTop !== []) {
            return $this->tolak('Field [' . implode(', ', $takDidukungTop) . '] tidak didukung API checkout (golongan/discount/susulan/PO dikelola server).');
        }

        // ===================== 2. X-Submit-Id =====================
        $submitId = trim((string) $request->header('X-Submit-Id'));
        if ($submitId === '' || strlen($submitId) > 100) {
            return response()->json(['message' => 'Header X-Submit-Id wajib dan maksimal 100 karakter.'], 422)
                ->header('Cache-Control', 'no-store');
        }

        // ===================== 3. Whitelist payload ketat =====================
        $idKonsumen = (int) $request->input('id_konsumen');
        if ($idKonsumen <= 0) {
            return response()->json(['message' => 'id_konsumen wajib.'], 422)->header('Cache-Control', 'no-store');
        }

        $tipeOrder = $request->input('tipe_order', 'Reguler') ?: 'Reguler';
        if ($tipeOrder !== 'Reguler') {
            return response()->json(['message' => "Tipe order {$tipeOrder} belum didukung API; hanya Reguler."], 422)
                ->header('Cache-Control', 'no-store');
        }

        if (!is_array($request->input('items')) || count($request->input('items')) === 0) {
            return response()->json(['message' => 'items wajib diisi (array, minimal 1).'], 422)
                ->header('Cache-Control', 'no-store');
        }

        $items = [];
        foreach ($request->input('items') as $n => $item) {
            if (!is_array($item)) {
                return $this->tolak("items[{$n}] harus berupa objek.");
            }
            $takDidukung = array_diff(array_keys($item), self::KUNCI_ITEM_API);
            if ($takDidukung !== []) {
                return $this->tolak(
                    "Kolom item [" . implode(', ', $takDidukung) . "] (baris items[{$n}]) belum didukung API " .
                    '(jasa, susulan, koreksi nota, finishing, tipe order, atau biaya manual tidak dibuka lewat kanal ini).'
                );
            }
            if ((!isset($item['id_produk']) && !isset($item['qty_input']))) {
                return $this->tolak("items[{$n}] wajib setidaknya berisi id_produk dan qty_input.");
            }
            if (empty($item['id_produk'])) {
                return $this->tolak("items[{$n}].id_produk wajib.");
            }
            if (!array_key_exists('qty_input', $item) || !is_numeric($item['qty_input'])) {
                return $this->tolak("items[{$n}].qty_input wajib ada dan berupa angka.");
            }
            // Bentuk item sederhana yang didukung adapter, KANONikal (urutan
            // kunci & tipe sudah normalisasi) - dipakai juga untuk sidik payload.
            $bentuk = [
                'id_produk' => (int) $item['id_produk'],
                'qty_input' => (float) $item['qty_input'],
                'p' => isset($item['p']) ? (float) $item['p'] : 0.0,
                'l' => isset($item['l']) ? (float) $item['l'] : 0.0,
            ];
            if (isset($item['nama_file']) && is_string($item['nama_file']) && trim($item['nama_file']) !== '') {
                $bentuk['nama_file'] = trim($item['nama_file']);
            }
            if (isset($item['catatan_produksi']) && is_string($item['catatan_produksi']) && trim($item['catatan_produksi']) !== '') {
                $bentuk['catatan_produksi'] = trim($item['catatan_produksi']);
            }
            if (isset($item['status_urgensi']) && in_array($item['status_urgensi'], ['Normal', 'Urgent', 'Very Urgent'], true)) {
                $bentuk['status_urgensi'] = $item['status_urgensi'];
            }
            $items[] = $bentuk;
        }

        // ===================== 4. Konsumen & golongan & produk dari DB =====================
        $konsumen = DB::table('konsumen')->where('id_konsumen', $idKonsumen)->first();
        if (!$konsumen) {
            return $this->tolak('Konsumen tidak ditemukan.');
        }
        $idGolongan = (int) $konsumen->id_golongan;
        if ($idGolongan <= 0) {
            return $this->tolak('Konsumen belum memiliki golongan harga yang sah.');
        }

        // Produk wajib AKTIF (produk.status) - diperiksa di adapter langsung
        // supaya 422 dan TANPA perubahan DB (stok/detail internal menunggu
        // jalur service yang belum disentuh). Produk tak dikenal juga 422.
        $idItems = collect($items)->pluck('id_produk')->unique()->values()->all();
        $pub = DB::table('produk')->whereIn('id_produk', $idItems)->get()->keyBy('id_produk');
        foreach ($items as $xn => $item) {
            $prd = $pub->get($item['id_produk']);
            if (!$prd) {
                return $this->tolak("Produk pada items[{$xn}] tidak ditemukan.");
            }
            if (strtolower((string) $prd->status) !== 'aktif') {
                return $this->tolak("Produk '{$prd->nama_produk}' nonaktif - tidak bisa diorder lewat API.");
            }
        }

        // Request dibangun ulang HANYA dari tuple yang sudah divalidasi - id_golongan
        // final dari DB (bukan request), id_konsumen/iitem tetap kanonikal.
        $reqService = $request;
        $reqService->replace([
            'id_konsumen' => $idKonsumen,
            'id_golongan' => $idGolongan,
            'tipe_order' => 'Reguler',
            'items' => $items,
        ]);

        // Sidik payload untuk idempotensi: apapun perbedaan yang terjadi
        // HANYA bila payload bisnis berbeda.
        $payloadHash = hash('sha256', (string) json_encode([
            'tipe' => 'checkout-api-v1',
            'id_konsumen' => $idKonsumen,
            'items' => $items,
        ], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));

        // ===================== 5. Klaim idempotensi =====================
        $tabel = 'api_checkout_idempotensi';
        $percobaan = 0;

        while (true) {
            if (++$percobaan > 4) {
                return response()->json(['message' => 'Sistem sibuk melayani checkout ini. Coba lagi sebentar.'], 503)
                    ->header('Cache-Control', 'no-store');
            }

            try {
                DB::table($tabel)->insert([
                    'id_user' => $user->id_user,
                    'submit_id' => $submitId,
                    'payload_hash' => $payloadHash,
                    'id_transaksi' => null,
                    'status' => 'diproses',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Klaim SAH dari kita: eksekusi checkout dalam transaksi luar.
                return $this->jalankanCheckout($reqService, $tabel, $user->id_user, $submitId);
            } catch (\Illuminate\Database\QueryException $e) {
                if (((int) ($e->errorInfo[1] ?? 0)) !== 1062) {
                    throw $e; // kerusakan lain bukan soal idempotensi
                }
            }

            // Klaim sudah dipunyai orang lain - baca keadaannya.
            $row = DB::table($tabel)->where('id_user', $user->id_user)->where('submit_id', $submitId)->first();
            if (!$row) {
                continue; // baru saja dilepas; rebut lagi
            }
            if ($row->payload_hash !== $payloadHash) {
                return response()->json(['message' => 'X-Submit-Id ini sudah dipakai untuk payload berbeda.'], 409)
                    ->header('Cache-Control', 'no-store');
            }
            if ($row->status === 'sah') {
                // Klaim sudah sah adalah bukti pembayaran/nota pernah dibuat.
                // Hilang atau tidak cocoknya nota adalah masalah integritas;
                // JANGAN lepaskan klaim dan membuat nota kedua.
                $replay = $row->id_transaksi
                    ? $this->responDariNota((int) $row->id_transaksi)
                    : null;
                if ($replay === null) {
                    report(new \RuntimeException('API-3: klaim checkout sudah sah tetapi nota tidak bisa ditemukan; user='
                        . $user->id_user . ', idempotensi=' . $row->id));
                    return response()->json([
                        'message' => 'Checkout ini sudah tercatat tetapi nota belum bisa diverifikasi; hubungi administrator.',
                    ], 409)->header('Cache-Control', 'no-store');
                }
                return $replay; // 200 + ulang=true
            }
            if ($row->status === 'gagal'
                || Carbon::parse($row->updated_at)->lt(now()->subSeconds((int) config('orion_api.idempotensi_tua_detik', 300)))) {
                // >300 detik BUKAN bukti runner telah mati! Bila checkout
                // pertama masih hidup, menghapus klaim ini dapat melahirkan
                // nota/stok/jurnal ganda. Wajib fail-closed dan rekonsiliasi
                // klaim oleh administrator dengan bukti DB, bukan auto-reclaim.
                return response()->json([
                    'message' => 'Checkout masih memerlukan verifikasi; X-Submit-Id tidak boleh dipakai ulang otomatis.',
                ], 409)->header('Cache-Control', 'no-store');
            }

            // diproses, masih muda: tunduk menunggu hasil runner.
            $batas = now()->copy()->addMilliseconds((int) (config('orion_api.idempotensi_tunggu_detik', 10) * 1000));
            while (now()->lt($batas)) {
                usleep(50_000);
                $row = DB::table($tabel)->where('id_user', $user->id_user)->where('submit_id', $submitId)->first();
                if (!$row) {
                    break; // gagal & sudah dilepas → rebutan ulang
                }
                if ($row->status === 'sah' && $row->id_transaksi) {
                    return $this->responDariNota((int) $row->id_transaksi) ?? response()->json(
                        ['message' => 'Checkout dengan X-Submit-Id ini sudah sah, tetapi nota tidak dapat dibaca.'], 409
                    )->header('Cache-Control', 'no-store');
                }
            }
            if ($row) {
                // Kunci masih dipunyai runner lain dan tidak selesai → 409. jANGAN
                // memproses checkout kedua di belakang punggung runner.
                return response()->json(['message' => 'Checkout dengan X-Submit-Id ini masih direbut. Tunggu sebentar atau diganti id.'], 409)
                    ->header('Cache-Control', 'no-store');
            }
        }
    }

    /** Response 422s standar untuk penolakan adapter. */
    private function tolak(string $pesan)
    {
        return response()->json(['message' => $pesan], 422)->header('Cache-Control', 'no-store');
    }

    /**
     * Eksekusi checkout dengan klaim yang sah milik request ini.
     * Transaksi luar DI SINI yang menyatukan commit:
     *   nota + detail + stok + kartu + jurnal + finalisasi klaim idempotensi.
     * store() di service membuka begin/commit sendiri - di dalam transaksi luar
     * itu level savepoint: keberhasilannya hanya dipastikan commit luar.
     */
    private function jalankanCheckout(Request $request, string $tabel, int $idUser, string $submitId)
    {
        DB::beginTransaction();

        try {
            $resp = app(PenjualanCheckoutService::class)->store($request, ['jalur' => 'api']);
        } catch (\Throwable $e) {
            DB::rollBack();
            // Lepas klaim: tidak ada catatan sukses palsu; ubah-kirim yang
            // sah tetap mungkin.
            DB::table($tabel)->where('id_user', $idUser)->where('submit_id', $submitId)->delete();
            throw $e;
        }

        $data = $resp->getData(true);
        $sukses = is_array($data) && (($data['status'] ?? null) === 'success');

        if (!$sukses) {
            DB::rollBack();
            DB::table($tabel)->where('id_user', $idUser)->where('submit_id', $submitId)->delete();

            $kode = $resp->getStatusCode();
            // Penolakan validasi bisnis web (400/422) satu keluarga di API
            // → 422; 403 (susulan/operator lain) & 500 dipertahankan.
            $kodeApi = in_array($kode, [400, 422], true) ? 422 : min($kode, 500);

            return response()->json([
                'message' => $data['msg'] ?? ($data['message'] ?? 'Checkout gagal.'),
            ], $kodeApi)->header('Cache-Control', 'no-store');
        }

        if (!isset($data['id_transaksi'])) {
            // Response success tanpa id - tidak boleh direkayasa jadi sukses:
            DB::rollBack();
            DB::table($tabel)->where('id_user', $idUser)->where('submit_id', $submitId)->delete();
            throw new \RuntimeException('Checkout API: response tanpa id_transaksi.');
        }

        // Finalisasi klaim DALAM transaksi luar sehingga ter-commit atomik
        // bersama nota/detail/stok/jurnal.
        DB::table($tabel)
            ->where('id_user', $idUser)
            ->where('submit_id', $submitId)
            ->update([
                'status' => 'sah',
                'id_transaksi' => (int) $data['id_transaksi'],
                'updated_at' => now(),
            ]);

        DB::commit();

        return response()->json([
            'status' => 'success',
            'id_transaksi' => (int) $data['id_transaksi'],
            'nomor_nota' => $data['nomor_nota'],
            'total_bayar' => (float) DB::table('transaksi')->where('id_transaksi', $data['id_transaksi'])->value('total_bayar'),
            'status_order' => 'Menunggu Kasir',
            'ulang' => false,
            'deposit_terpakai' => $data['deposit_terpakai'] ?? null,
        ], 201)->header('Cache-Control', 'no-store');
    }

    /** Jawaban kiriman-ulang untuk nota yang sudah disimpan: 200 + ulang=true. */
    private function responDariNota(int $idTransaksi)
    {
        $nota = DB::table('transaksi')->where('id_transaksi', $idTransaksi)->first();
        if (!$nota) {
            return null;
        }

        return response()->json([
            'status' => 'success',
            'id_transaksi' => (int) $nota->id_transaksi,
            'nomor_nota' => $nota->nomor_nota,
            'total_bayar' => (float) $nota->total_bayar,
            'status_order' => $nota->status_order,
            'ulang' => true,
        ], 200)->header('Cache-Control', 'no-store');
    }
}
