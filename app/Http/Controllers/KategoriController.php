<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KategoriController extends Controller
{
    /**
     * Tampilkan data kategori yang ada di database.
     */
    public function index(Request $request)
    {
        // Mengambil data kategori untuk ditampilkan di tabel
        $query = DB::table('kategori');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nama_kategori', 'like', '%' . $request->search . '%')
                  ->orWhere('kode_kategori', 'like', '%' . $request->search . '%');
            });
        }

        $kategori = $query->orderBy('nama_kategori')->paginate(20)->withQueryString();
        return view('master.kategori.index', compact('kategori'));
    }

    /**
     * Store: Input data kategori baru.
     * Menggunakan validasi agar nama kategori tidak duplikat.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nama_kategori' => 'required|unique:kategori,nama_kategori|max:50',
            'kode_kategori' => 'required|unique:kategori,kode_kategori|max:5',
        ], [
            'nama_kategori.unique' => 'Nama kategori sudah ada!',
            'kode_kategori.unique' => 'Kode kategori sudah terdaftar!',
        ]);

        DB::table('kategori')->insert([
            'nama_kategori' => $request->nama_kategori,
            'kode_kategori' => strtoupper($request->kode_kategori),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return redirect()->route('kategori.index')->with('success', 'Kategori baru berhasil disimpan ke sistem.');
    }

    /**
     * Update: Mengubah data kategori yang sudah ada.
     */
    public function update(Request $request, $id)
{
    $request->validate([
        'nama_kategori' => 'required|max:50|unique:kategori,nama_kategori,'.$id.',id_kategori',
        'kode_kategori' => 'required|max:5|unique:kategori,kode_kategori,'.$id.',id_kategori',
    ]);

    // Hapus baris 'updated_at' agar tidak error
    DB::table('kategori')->where('id_kategori', $id)->update([
        'nama_kategori' => $request->nama_kategori,
        'kode_kategori' => strtoupper($request->kode_kategori)
    ]);

    return redirect()->route('kategori.index')->with('success', 'Data kategori berhasil diperbarui.');
}

    /**
     * Destroy: Menghapus kategori dengan pengamanan.
     */
    public function destroy($id)
    {
        // PENTING: Cek apakah kategori ini sedang digunakan di tabel bahan_baku
        $cekBahan = DB::table('bahan_baku')->where('id_kategori', $id)->exists();

        if ($cekBahan) {
            return redirect()->route('kategori.index')->with('error', 'Gagal Hapus! Kategori ini masih digunakan oleh data Bahan Baku.');
        }

        DB::table('kategori')->where('id_kategori', $id)->delete();
        return redirect()->route('kategori.index')->with('success', 'Kategori telah dihapus dari sistem.');
    }
}