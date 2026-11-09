<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Berkas Order (LEVEL 3, bagian 35 dokumen audit): satu tempat untuk semua
 * berkas sebuah nota, dikelompokkan per kategori:
 *   Design (thumbnail order), Production File, QC Photo, Lainnya (order_berkas), dan Delivery Proof
 *   (foto bukti serah terima di tabel pengambilan).
 * Berkas TIDAK bisa dihapus dari sini - berfungsi sebagai bukti.
 */
class BerkasOrderController extends Controller
{
    private const URUTAN = ['Design', 'Production File', 'QC Photo', 'Delivery Proof', 'Lainnya'];

    public function show(int $idTransaksi)
    {
        $nota = DB::table('transaksi as t')
            ->leftJoin('konsumen as k', 'k.id_konsumen', '=', 't.id_konsumen')
            ->where('t.id_transaksi', $idTransaksi)
            ->select('t.*', 'k.nama_konsumen')
            ->first();

        abort_if(!$nota, 404);

        $items = DB::table('transaksi_detail')->where('id_transaksi', $idTransaksi)->get();
        $namaItem = $items->pluck('nama_file', 'id_detail');
        $url = fn (?string $path) => $path ? asset('storage/' . ltrim($path, '/')) : null; // asset() mengikuti alamat akses (subfolder XAMPP / domain VPS)
        $berkas = collect();

        foreach ($items as $it) {
            if ($it->thumbnail) {
                $berkas->push(['kategori' => 'Design', 'item' => $it->nama_file, 'nama' => basename($it->thumbnail), 'url' => $url($it->thumbnail), 'info' => 'Thumbnail order', 'waktu' => $it->created_at]);
            }
        }

        foreach (DB::table('order_berkas')->where('id_transaksi', $idTransaksi)->orderBy('id')->get() as $b) {
            $berkas->push([
                'kategori' => $b->kategori, 'item' => $b->id_detail ? ($namaItem[$b->id_detail] ?? '-') : 'Seluruh nota', 'nama' => $b->nama_file_asli,
                'url' => $url($b->file_path), 'info' => $b->catatan, 'waktu' => $b->created_at,
            ]);
        }

        $pengambilan = DB::table('pengambilan')->where('id_transaksi', $idTransaksi)->first();
        if ($pengambilan && $pengambilan->bukti_path) {
            $berkas->push([
                'kategori' => 'Delivery Proof', 'item' => 'Seluruh nota', 'nama' => basename($pengambilan->bukti_path), 'url' => $url($pengambilan->bukti_path),
                'info' => "{$pengambilan->jenis}" . ($pengambilan->nama_penerima ? " - {$pengambilan->nama_penerima}" : ''), 'waktu' => $pengambilan->created_at,
            ]);
        }

        $grup = collect(self::URUTAN)->mapWithKeys(fn ($k) => [$k => $berkas->where('kategori', $k)->values()])->filter(fn ($g) => $g->isNotEmpty());

        return view('berkas-order.show', [
            'nota' => $nota, 'items' => $items, 'grup' => $grup, 'pengambilan' => $pengambilan,
            'kategoriUnggah' => ['Production File', 'QC Photo', 'Lainnya'],
        ]);
    }

    public function unggah(Request $request, int $idTransaksi)
    {
        $data = $request->validate([
            'kategori' => 'required|in:Production File,QC Photo,Lainnya',
            'id_detail' => 'nullable|integer',
            // Isi berkas ikut diperiksa (BerkasAman): aturan extensions: hanya membaca nama dari klien.
            'file' => ['required', 'file', 'max:51200', function ($atribut, $nilai, $gagal) {
                if ($nilai instanceof \Illuminate\Http\UploadedFile && ($pesan = \App\Support\BerkasAman::periksa($nilai))) {
                    $gagal($pesan);
                }
            }],
            'catatan' => 'nullable|string|max:255',
        ]);

        if (!DB::table('transaksi')->where('id_transaksi', $idTransaksi)->exists()) {
            abort(404);
        }

        $idDetail = null;
        if (!empty($data['id_detail'])) {
            $idDetail = DB::table('transaksi_detail')->where('id_detail', $data['id_detail'])->where('id_transaksi', $idTransaksi)->value('id_detail');

            if (!$idDetail) {
                return back()->with('error', 'Item tidak termasuk nota ini.');
            }
        }

        $file = $request->file('file');
        $path = \App\Support\BerkasAman::simpan($file, "berkas_order/{$idTransaksi}");

        try {
            DB::table('order_berkas')->insert([
                'id_transaksi' => $idTransaksi, 'id_detail' => $idDetail, 'kategori' => $data['kategori'], 'file_path' => $path,
                'nama_file_asli' => $file->getClientOriginalName(), 'ukuran_bytes' => $file->getSize() ?: 0, 'catatan' => $data['catatan'] ?? null,
                'id_user' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);

            throw $e;
        }

        return back()->with('success', "Berkas {$data['kategori']} diunggah.");
    }
}
