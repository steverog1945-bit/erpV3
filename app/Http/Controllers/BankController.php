<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BankController extends Controller
{
    public function index(Request $request) {
        $query = DB::table('bank_perusahaan');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nama_bank', 'like', '%' . $request->search . '%')
                  ->orWhere('atas_nama', 'like', '%' . $request->search . '%')
                  ->orWhere('nomor_rekening', 'like', '%' . $request->search . '%');
            });
        }

        $bank = $query->orderBy('nama_bank')->paginate(20)->withQueryString();
        return view('master.bank.index', compact('bank'));
    }

    /** Gambar QRIS perusahaan: berkas tetap di storage/app/public/qris/qris.{ext}
     *  (tanpa kolom database). Panel barcode QRIS di Input Pembayaran membaca
     *  lokasi ini; mengunggah ulang menggantikan berkas lama. */
    public function updateQris(Request $request)
    {
        $request->validate(['qris' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048']);

        $ext = match ($request->file('qris')->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        // Hapus semua varian ekstensi lama supaya tidak ada dua gambar QRIS.
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $lama) {
            Storage::disk('public')->delete('qris/qris.' . $lama);
        }

        $request->file('qris')->storeAs('qris', 'qris.' . $ext, 'public');

        return redirect()->back()->with('success', 'Gambar QRIS berhasil diunggah.');
    }

    public function store(Request $request) {
        $request->validate(['nama_bank' => 'required|max:50', 'nomor_rekening' => 'required|max:50', 'atas_nama' => 'required|max:100']);
        $idBaru = DB::table('bank_perusahaan')->insertGetId($request->only('nama_bank', 'nomor_rekening', 'atas_nama'));
        \App\Services\AuditLogger::master('created', 'bank_perusahaan', 'id_bank', (int) $idBaru, null, 'Rekening bank baru');
        return redirect()->back()->with('success', 'Rekening bank berhasil disimpan.');
    }
    public function update(Request $request, $id)
{
    $request->validate([
        'nama_bank' => 'required|max:50',
        'nomor_rekening' => 'required|max:50',
        'atas_nama' => 'required|max:100'
    ]);

    $lama = DB::table('bank_perusahaan')->where('id_bank', $id)->first();

    DB::table('bank_perusahaan')->where('id_bank', $id)->update([
        'nama_bank'      => $request->nama_bank,
        'nomor_rekening' => $request->nomor_rekening,
        'atas_nama'      => $request->atas_nama,
    ]);
    // Nomor rekening tujuan transfer: perubahannya harus bisa ditelusuri (siapa, kapan, dari nomor berapa).
    \App\Services\AuditLogger::master('updated', 'bank_perusahaan', 'id_bank', (int) $id, $lama, 'Data rekening bank diubah');

    return redirect()->back()->with('success', 'Data rekening berhasil diperbarui.');
}

    public function destroy($id) {
        // Rekening yang sudah dipakai pembayaran tidak boleh dihapus: riwayat
        // pembayaran akan kehilangan identitas bank tujuannya.
        if (DB::table('pembayaran_riwayat')->where('id_bank', $id)->exists()) {
            return redirect()->back()->with('error', 'Rekening ini tidak bisa dihapus karena sudah tercatat pada riwayat pembayaran.');
        }

        $lama = DB::table('bank_perusahaan')->where('id_bank', $id)->first();
        DB::table('bank_perusahaan')->where('id_bank', $id)->delete();
        if ($lama) {
            \App\Services\AuditLogger::master('deleted', 'bank_perusahaan', 'id_bank', (int) $id, $lama, 'Rekening bank dihapus');
        }

        return redirect()->back()->with('success', 'Rekening dihapus.');
    }
}