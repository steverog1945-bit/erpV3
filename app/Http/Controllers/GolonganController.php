<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GolonganController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('golongan_konsumen');

        if ($request->filled('search')) {
            $query->where('nama_golongan', 'like', '%' . $request->search . '%');
        }

        $golongan = $query->orderBy('nama_golongan')->paginate(20)->withQueryString();
        return view('master.golongan.index', compact('golongan'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_golongan' => 'required|max:50',
            'keterangan' => 'nullable|max:255'
        ]);

        $idBaru = DB::table('golongan_konsumen')->insertGetId([
            'nama_golongan' => $request->nama_golongan,
            'keterangan' => $request->keterangan
        ]);
        \App\Services\AuditLogger::master('created', 'golongan_konsumen', 'id_golongan', (int) $idBaru, null, 'Golongan konsumen baru');

        return redirect()->back()->with('success', 'Golongan konsumen berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
{
    $request->validate([
        'nama_golongan' => 'required|max:50'
    ]);

    $lama = DB::table('golongan_konsumen')->where('id_golongan', $id)->first();

    DB::table('golongan_konsumen')->where('id_golongan', $id)->update([
        'nama_golongan' => $request->nama_golongan,
        // Gunakan operator ?? untuk memberikan string kosong jika input null
        'keterangan'    => $request->keterangan ?? '-'
    ]);
    \App\Services\AuditLogger::master('updated', 'golongan_konsumen', 'id_golongan', (int) $id, $lama, 'Golongan konsumen diubah');

    return redirect()->back()->with('success', 'Golongan berhasil diperbarui.');
}

    public function destroy($id)
    {
        // Cek apakah ada konsumen yang masih pakai golongan ini
        $cek = DB::table('konsumen')->where('id_golongan', $id)->exists();
        if ($cek) {
            return redirect()->back()->with('error', 'Gagal hapus! Golongan ini masih digunakan oleh data konsumen.');
        }

        // Golongan juga menentukan harga: price list, harga file siap cetak, dan penawaran memakainya.
        $dipakaiHarga = [];
        foreach (['Price List' => 'setting_harga', 'Harga File Siap Cetak' => 'harga_siap_cetak', 'Penawaran' => 'quotations'] as $label => $tabel) {
            if (DB::table($tabel)->where('id_golongan', $id)->exists()) {
                $dipakaiHarga[] = $label;
            }
        }
        if ($dipakaiHarga) {
            return redirect()->back()->with('error', 'Gagal hapus! Golongan ini masih dipakai pada: ' . implode(', ', $dipakaiHarga) . '.');
        }

        $lama = DB::table('golongan_konsumen')->where('id_golongan', $id)->first();
        DB::table('golongan_konsumen')->where('id_golongan', $id)->delete();
        if ($lama) {
            \App\Services\AuditLogger::master('deleted', 'golongan_konsumen', 'id_golongan', (int) $id, $lama, 'Golongan konsumen dihapus');
        }
        return redirect()->back()->with('success', 'Golongan berhasil dihapus.');
    }
}