<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Satuan;
class SatuanController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('satuan');

        if ($request->filled('search')) {
            $query->where('nama_satuan', 'like', '%' . $request->search . '%');
        }

        $satuan = $query->orderBy('nama_satuan')->paginate(20)->withQueryString();
        return view('master.satuan.index', compact('satuan'));
    }

    public function store(Request $request)
    {
        // Redirect biasa (bukan JSON) - form di master.satuan.index adalah
        // form POST langsung tanpa AJAX, jadi response JSON sebelumnya bikin
        // browser menampilkan halaman JSON mentah alih-alih kembali ke
        // daftar satuan.
        $request->validate([
            'nama_satuan' => 'required|max:20|unique:satuan,nama_satuan',
            'nilai_konversi' => 'required|numeric|min:0',
        ]);

        DB::table('satuan')->insert([
            'nama_satuan' => $request->nama_satuan,
            'nilai_konversi' => $request->nilai_konversi,
        ]);

        return redirect()->route('satuan.index')->with('success', 'Satuan berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_satuan' => 'required|max:20|unique:satuan,nama_satuan,'.$id.',id_satuan',
            'nilai_konversi' => 'required|numeric',
        ]);

        DB::table('satuan')->where('id_satuan', $id)->update([
            'nama_satuan' => $request->nama_satuan,
            'nilai_konversi' => $request->nilai_konversi,
        ]);

        return redirect()->route('satuan.index')->with('success', 'Satuan berhasil diperbarui.');
    }

    public function destroy($id)
    {
        // Cek berdasarkan kolom FK yang sesungguhnya dipakai BahanBaku
        // (id_satuan_stok / id_satuan_hitung), bukan kolom lama 'satuan_stok'.
        $terpakai = DB::table('bahan_baku')
            ->where('id_satuan_stok', $id)
            ->orWhere('id_satuan_hitung', $id)
            ->exists();
        if ($terpakai) {
            return redirect()->route('satuan.index')->with('error', 'Satuan masih digunakan di Bahan Baku, tidak bisa dihapus.');
        }

        DB::table('satuan')->where('id_satuan', $id)->delete();
        return redirect()->route('satuan.index')->with('success', 'Satuan berhasil dihapus.');
    }
}