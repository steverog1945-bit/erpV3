<?php

namespace App\Http\Controllers;

use App\Services\KasSesiService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Konsumen;
class KonsumenController extends Controller
{
    /**
     * Pencarian konsumen untuk POS (select2 AJAX) - dibuat karena dataset
     * konsumen bisa puluhan ribu baris; menyeret seluruhnya ke <option>
     * tiap buka POS berat di memori/payload. Ketat kloning pola index():
     * LIKE nama/telepon, tanpa kolom alamat (TEXT), dibatasi 20 hasil.
     * Akses tetap dalam middleware auth+menu.access (route-nya di group sama).
     */
    public function searchAjax(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100']);

        $rows = DB::table('konsumen')
            ->when($request->filled('q'), fn ($q) => $q->where(function ($w) use ($request) {
                $w->where('konsumen.nama_konsumen', 'like', '%' . $request->q . '%')
                  ->orWhere('konsumen.telepon', 'like', '%' . $request->q . '%');
            }))
            ->orderBy('konsumen.nama_konsumen')
            ->limit(20)
            ->get(['id_konsumen', 'nama_konsumen', 'telepon', 'id_golongan']);

        // Format select2 AJAX: { results: [...] }.
        return response()->json([
            'results' => $rows->map(fn ($k) => [
                'id'       => $k->id_konsumen,
                'text'     => $k->nama_konsumen . ($k->telepon && $k->telepon !== '-' ? ' (' . $k->telepon . ')' : ''),
                'nama'     => $k->nama_konsumen,
                'telepon'  => $k->telepon ?? '-',
                'golongan' => $k->id_golongan,
            ]),
        ]);
    }

    public function index(Request $request)
    {
        // Menggabungkan tabel konsumen dengan nama golongannya
        $query = DB::table('konsumen')
            ->join('golongan_konsumen', 'konsumen.id_golongan', '=', 'golongan_konsumen.id_golongan')
            ->select('konsumen.*', 'golongan_konsumen.nama_golongan');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('konsumen.nama_konsumen', 'like', '%' . $request->search . '%')
                  ->orWhere('konsumen.telepon', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('id_golongan')) {
            $query->where('konsumen.id_golongan', $request->id_golongan);
        }

        $konsumen = $query->orderBy('konsumen.nama_konsumen')->paginate(20)->withQueryString();

        $golongan = DB::table('golongan_konsumen')->get();

        return view('master.konsumen.index', compact('konsumen', 'golongan'));
    }
    public function update(Request $request, $id)
{
    // 1. Validasi input
    $request->validate([
        'nama_konsumen' => 'required|string|max:100',
        'telepon'       => 'required|max:20',
        'id_golongan'   => 'required|exists:golongan_konsumen,id_golongan',
        'tipe_konsumen' => 'nullable|in:permanent,temporary',
        'alamat'        => 'nullable|max:500',
    ]);

    // 2. Update data ke database
    $lama = DB::table('konsumen')->where('id_konsumen', $id)->first();

    DB::table('konsumen')->where('id_konsumen', $id)->update([
        'nama_konsumen' => $request->nama_konsumen,
        'telepon'       => $request->telepon,
        'id_golongan'   => $request->id_golongan,
        'tipe_konsumen' => $request->tipe_konsumen ?? 'temporary',
        'alamat'        => $request->alamat,
    ]);
    \App\Services\AuditLogger::master('updated', 'konsumen', 'id_konsumen', (int) $id, $lama, 'Data konsumen diubah');

    // 3. Kembali ke halaman sebelumnya dengan pesan sukses
    return redirect()->back()->with('success', 'Data konsumen berhasil diperbarui.');
}
public function storeAjax(Request $request)
{
    // PENDAFTARAN KONSUMEN ADALAH MASTER DATA, BUKAN TRANSAKSI.
    //
    // Guard sesi kas yang pernah ada di sini membuat pendaftaran konsumen gagal
    // dengan "Sesi Kas belum dibuka" walaupun laci sudah dibuka (oleh kasir
    // lain), atau sesi sudah melewati jam Closing. Itu tidak sesuai aturan
    // bisnis: master data tidak bergantung pada Sesi Kas OPEN, tidak
    // bergantung pada usia sesi, dan tidak bergantung pada siapa pemilik laci.
    //
    // Yang tetap berlaku persis seperti sebelum:
    //   - autentikasi + hak akses  (middleware route, TIDAK diubah)
    //   - proteksi CSRF           (middleware web, TIDAK diubah)
    //   - validasi field server   (baris di bawah, TIDAK diubah)
    //   - kontrak JSON response   (TIDAK diubah, tetap {status, data})
    //
    // Aturan sesi kas untuk TRANSAKSI (buat nota, bayar, kas manual) tetap
    // utuh di PenjualanController dan KasirController.
    //
    // 1. Validasi ketat di sisi Server
    $request->validate([
        'nama_konsumen' => 'required|string|max:100',
        'telepon'       => 'nullable|max:20',
        'id_golongan'   => 'required|exists:golongan_konsumen,id_golongan',
        'tipe_konsumen' => 'nullable|in:permanent,temporary',
    ]);

    try {
        $existing = DB::table('konsumen')
            ->whereRaw('LOWER(nama_konsumen) = ?', [mb_strtolower(trim($request->nama_konsumen))])
            ->first();
        if ($existing) {
            $gol = DB::table('golongan_konsumen')->where('id_golongan', $existing->id_golongan)->value('nama_golongan');
            return response()->json([
                'status'  => 'duplicate',
                'message' => 'Konsumen dengan nama ini sudah terdaftar. Memilih data yang ada.',
                'data'    => (object) array_merge((array) $existing, ['nama_golongan' => $gol]),
            ], 422);
        }

        // 2. Simpan menggunakan DB Table agar tidak masalah dengan Model fillable
        $id = DB::table('konsumen')->insertGetId([
            'nama_konsumen' => $request->nama_konsumen,
            'telepon'       => $request->telepon ?? '-',
            'id_golongan'   => $request->id_golongan,
            'tipe_konsumen' => $request->tipe_konsumen ?? 'temporary',
            'tgl_registrasi'=> now(),
            'nama_konsumen_manual' => $request->nama_konsumen // Seringkali kolom ini NOT NULL di DB
        ]);

        // 3. Ambil data lengkap untuk dikirim balik ke JavaScript
        $konsumen = DB::table('konsumen')
            ->join('golongan_konsumen', 'konsumen.id_golongan', '=', 'golongan_konsumen.id_golongan')
            ->where('id_konsumen', $id)
            ->select('konsumen.*', 'golongan_konsumen.nama_golongan')
            ->first();

        return response()->json([
            'status' => 'success',
            'data' => $konsumen
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => \App\Support\PesanError::untuk($e)
        ], 500);
    }
}
    public function store(Request $request)
    {
        $request->validate([
            'nama_konsumen' => 'required|string|max:100',
            'id_golongan'   => 'required|exists:golongan_konsumen,id_golongan',
            'telepon'       => 'required|max:20',
            'tipe_konsumen' => 'nullable|in:permanent,temporary',
            'alamat'        => 'nullable|max:500',
        ]);

        // Deteksi duplikat: nama yang sama (case-insensitive) sudah terdaftar.
        // Konsumen dengan nama sama boleh ada kalau dibedakan (mis. telepon/alamat),
        // tapi sebelum menyimpan, kasir harus diberi tahu dan diminta menambah
        // pembeda supaya tidak tertukar di nota/riwayat.
        $duplikat = DB::table('konsumen')
            ->whereRaw('LOWER(nama_konsumen) = ?', [mb_strtolower(trim($request->nama_konsumen))])
            ->first();
        if ($duplikat) {
            return redirect()->back()->withInput()
                ->with('error', 'Konsumen dengan nama "' . $request->nama_konsumen . '" sudah terdaftar (telepon ' . ($duplikat->telepon ?: '-') . '). Jika ini orang yang berbeda, tambahkan pembeda pada nama (mis. "' . $request->nama_konsumen . ' - Kantor") atau isi telepon yang berbeda, lalu simpan lagi.');
        }

        $idBaru = DB::table('konsumen')->insertGetId([
            'nama_konsumen' => $request->nama_konsumen,
            'telepon'       => $request->telepon,
            'alamat'        => $request->alamat,
            'id_golongan'   => $request->id_golongan,
            'tipe_konsumen' => $request->tipe_konsumen ?? 'temporary',
            'tgl_registrasi'=> now()
        ]);
        \App\Services\AuditLogger::master('created', 'konsumen', 'id_konsumen', (int) $idBaru, null, 'Konsumen baru');

        return redirect()->back()->with('success', 'Data konsumen berhasil disimpan.');
    }

    public function destroy($id)
    {
        // Dulu hanya try/catch. Tabel transaksi, jersey_orders, dan
        // transaksi_detail TIDAK punya foreign key ke konsumen, jadi tidak
        // ada error yang bisa ditangkap dan penghapusan selalu berhasil -
        // meninggalkan riwayat nota dan order yang menunjuk konsumen yang
        // sudah tidak ada. quotations punya FK, jadi yang itu memang
        // akan caught, tapi sisanya tidak.
        $pemakaian = [
            'Nota Penjualan' => DB::table('transaksi')->where('id_konsumen', $id)->exists(),
            'Order Jersey'   => DB::table('jersey_orders')->where('id_konsumen', $id)->exists(),
            'Deposit'        => DB::table('konsumen_deposit')->where('id_konsumen', $id)->exists(),
        ];
        $dipakai = array_keys(array_filter($pemakaian));
        if ($dipakai) {
            return back()->with('error',
                'Gagal hapus! Konsumen ini masih punya data di: ' . implode(', ', $dipakai) . '.'
            );
        }

        try {
            $lama = DB::table('konsumen')->where('id_konsumen', $id)->first();
            DB::table('konsumen')->where('id_konsumen', $id)->delete();
            if ($lama) {
                \App\Services\AuditLogger::master('deleted', 'konsumen', 'id_konsumen', (int) $id, $lama, 'Konsumen dihapus');
            }
            return redirect()->back()->with('success', 'Konsumen dihapus.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal hapus! Konsumen ini masih punya riwayat yang mengikat.');
        }
    }
}